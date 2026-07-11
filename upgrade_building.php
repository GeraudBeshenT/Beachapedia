<?php
// upgrade_building.php
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'functions.php'; // Pour seedTribusUnlockRows()
header('Content-Type: application/json');

$id_player = $_SESSION['player_id'] ?? null;
if (!$id_player) {
    echo json_encode(['success' => false, 'message' => 'Non connecté.']);
    exit;
}

$tid = isset($_POST['tid']) ? trim($_POST['tid']) : null;
$id_instance = isset($_POST['id_instance']) ? intval($_POST['id_instance']) : null;

// S'il est vide, le script s'arrête ici (ajoute ce log si besoin)
if (!$id_instance) {
    error_log("ERREUR: id_instance est null dans upgrade_building.php");
    echo json_encode(['success' => false, 'message' => 'Instance manquante']);
    exit;
}

$target_level = isset($_POST['target_level']) ? intval($_POST['target_level']) : null;

if (!$tid || !$id_instance || $target_level === null) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Récupération ID numérique du bâtiment
    $stmt_id = $pdo->prepare("SELECT ID FROM buildingid WHERE TID = ? LIMIT 1");
    $stmt_id->execute([$tid]);
    $b_row = $stmt_id->fetch(PDO::FETCH_ASSOC);
    if (!$b_row) throw new Exception("Bâtiment inconnu.");
    $id_building = (int)$b_row['ID'];

    // 2. VÉRIFICATION : Est-ce que cette instance existe déjà pour ce joueur ?
    $stmt_check = $pdo->prepare("SELECT id_instance FROM progress_building WHERE id_player = ? AND id_building = ? AND id_instance = ? LIMIT 1");
    $stmt_check->execute([$_SESSION['player_id'], $id_building, $id_instance]);
    $existing_row = $stmt_check->fetch(PDO::FETCH_ASSOC);

    // Le bâtiment est considéré comme débloqué (construit) dès que son niveau passe à 1 ou plus.
    // Construire (0 -> 1) et Améliorer (n -> n+1) utilisent donc le même endpoint.
    $debloque = ($target_level > 0) ? 1 : 0;

    if ($existing_row) {
        // La ligne existe : on fait un UPDATE
        $stmt_action = $pdo->prepare("UPDATE progress_building SET niveau = ?, Debloque = ? WHERE id_player = ? AND id_building = ? AND id_instance = ?");
        $stmt_action->execute([$target_level, $debloque, $_SESSION['player_id'], $id_building, $id_instance]);
    } else {
        // La ligne n'existe pas : on fait un INSERT
        $stmt_action = $pdo->prepare("INSERT INTO progress_building (id_player, id_building, id_instance, niveau, Debloque) VALUES (?, ?, ?, ?, ?)");
        $stmt_action->execute([$_SESSION['player_id'], $id_building, $id_instance, $target_level, $debloque]);
    }

    // 3. Calcul du gain d'XP (Uniquement si le niveau > 0)
    $xp_gain = 0;
    if ($target_level > 0) {
        $stmt_xp = $pdo->prepare("SELECT XpGain FROM buildings WHERE TID = ? AND Niveau = ?");
        $stmt_xp->execute([$tid, $target_level]); 
        $xp_gain = (int)($stmt_xp->fetchColumn() ?? 0);
    }

    // 4. Mise à jour XP Joueur
    if ($xp_gain > 0) {
        $stmt_update_xp = $pdo->prepare("UPDATE joueurs SET experience = experience + ? WHERE id_player = ?");
        $stmt_update_xp->execute([$xp_gain, $_SESSION['player_id']]);
    }

    $pdo->commit();

    // Le Radar (id_building = 14, "Salle des cartes") conditionne le déblocage des Tribus.
    // À chaque amélioration du Radar, on pré-remplit progress_tribs (niveau=0, Debloque=0)
    // pour toute tribu nouvellement éligible ; le joueur cliquera ensuite sur "Débloquer"
    // dans la page Tribus pour faire passer Debloque à 1 (voir upgrade_tribs.php).
    if ($id_building === 14) {
        seedTribusUnlockRows($pdo, $id_player, $target_level);
    }

    echo json_encode(['success' => true, 'message' => 'Mise à jour réussie.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}