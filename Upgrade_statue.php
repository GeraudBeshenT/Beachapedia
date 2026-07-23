<?php
// upgrade_statue.php
// Sauvegarde (ou efface) le contenu d'un emplacement de statue (onglet Profil > Statue).
// Même convention que upgrade_monument.php : un seul fichier, dispatch simple.
//
// POST attendus :
//   - id_slot   (int, obligatoire)  numéro d'emplacement, 1..ArtifactCapacity
//   - id_statue (int, optionnel)    référence statueid.id ; absent/0/vide = on
//                                   efface l'emplacement (le joueur retire sa statue)
//   - boost     (int, requis si id_statue fourni) valeur choisie par le joueur
//
// Règle métier : un joueur ne peut posséder qu'un seul exemplaire d'un
// "Chef d'œuvre" (statues de palier 3, TID commençant par TID_BUILDING_ARTIFACT3 :
// base / _ICE / _FIRE / _DARK) pour un même TID_BONUS donné -- en pratique,
// pour un même id_statue, puisque chaque ligne statueid = une combinaison
// unique TID + TID_BONUS. On interdit donc de réutiliser un id_statue de palier 3
// déjà enregistré par ce joueur dans un AUTRE emplacement.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
header('Content-Type: application/json');

$id_player = $_SESSION['player_id'] ?? null;
if (!$id_player) {
    echo json_encode(['success' => false, 'message' => 'Non connecté.']);
    exit;
}

$id_slot   = isset($_POST['id_slot']) ? (int)$_POST['id_slot'] : null;
$id_statue = isset($_POST['id_statue']) && $_POST['id_statue'] !== '' ? (int)$_POST['id_statue'] : null;
$boost_raw = isset($_POST['boost']) ? $_POST['boost'] : null;

if (!$id_slot || $id_slot < 1) {
    echo json_encode(['success' => false, 'message' => 'Emplacement invalide.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // --- Cas 1 : id_statue absent/0 -> le joueur vide l'emplacement ---
    if (!$id_statue) {
        $stmt_del = $pdo->prepare("DELETE FROM progress_statue WHERE id_player = ? AND id_slot = ?");
        $stmt_del->execute([$id_player, $id_slot]);

        $pdo->commit();
        echo json_encode(['success' => true, 'action' => 'cleared', 'id_slot' => $id_slot]);
        exit;
    }

    // --- Cas 2 : le joueur enregistre/modifie une statue sur cet emplacement ---

    // 1. Vérification que la statue existe + récupération des bornes MinValue/MaxValue
    $stmt_statue = $pdo->prepare("SELECT `TID`, `TID_BONUS`, `MinValue`, `MaxValue` FROM `statueid` WHERE `id` = ? LIMIT 1");
    $stmt_statue->execute([$id_statue]);
    $statue = $stmt_statue->fetch(PDO::FETCH_ASSOC);
    if (!$statue) {
        throw new Exception("Statue inconnue.");
    }

    $tid = $statue['TID'];
    $min_value = (int)$statue['MinValue'];
    $max_value = (int)$statue['MaxValue'];

    // 2. Clamp de la valeur saisie dans les bornes autorisées pour cette statue
    $boost = ($boost_raw === null || $boost_raw === '') ? $min_value : (int)$boost_raw;
    if ($boost < $min_value) $boost = $min_value;
    if ($boost > $max_value) $boost = $max_value;

    // 3. Règle "Chef d'œuvre unique" : uniquement pour le palier 3
    //    (TID_BUILDING_ARTIFACT3, _ICE, _FIRE, _DARK -- tous commencent par ce préfixe).
    if (strpos($tid, 'TID_BUILDING_ARTIFACT3') === 0) {
        $stmt_dup = $pdo->prepare("
            SELECT id_slot FROM progress_statue
            WHERE id_player = ? AND id_statue = ? AND id_slot != ?
            LIMIT 1
        ");
        $stmt_dup->execute([$id_player, $id_statue, $id_slot]);
        if ($stmt_dup->fetch()) {
            throw new Exception("Vous possédez déjà ce Chef d'œuvre (un seul exemplaire par type de bonus).");
        }
    }

    // 4. Upsert de l'emplacement
    $stmt_check = $pdo->prepare("SELECT 1 FROM progress_statue WHERE id_player = ? AND id_slot = ? LIMIT 1");
    $stmt_check->execute([$id_player, $id_slot]);

    if ($stmt_check->fetch()) {
        $stmt_upd = $pdo->prepare("UPDATE progress_statue SET id_statue = ?, boost = ? WHERE id_player = ? AND id_slot = ?");
        $stmt_upd->execute([$id_statue, $boost, $id_player, $id_slot]);
    } else {
        $stmt_ins = $pdo->prepare("INSERT INTO progress_statue (id_player, id_slot, id_statue, boost) VALUES (?, ?, ?, ?)");
        $stmt_ins->execute([$id_player, $id_slot, $id_statue, $boost]);
    }

    $pdo->commit();

    echo json_encode([
        'success'   => true,
        'action'    => 'saved',
        'id_slot'   => $id_slot,
        'id_statue' => $id_statue,
        'boost'     => $boost,
        'message'   => 'Statue enregistrée.'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}