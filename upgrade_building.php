<?php
// upgrade_building.php
// Gère à la fois l'amélioration ($action='upgrade', par défaut/implicite) et la
// rétrogradation ($action='downgrade') d'un bâtiment, dans le même fichier — même
// convention que upgrade_ability.php / upgrade_monument.php (dispatch sur $action).
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

$action      = $_POST['action'] ?? 'upgrade';
$tid         = isset($_POST['tid']) ? trim($_POST['tid']) : null;
$id_instance = isset($_POST['id_instance']) ? intval($_POST['id_instance']) : null;

// S'il est vide, le script s'arrête ici (ajoute ce log si besoin)
if (!$id_instance) {
    error_log("ERREUR: id_instance est null dans upgrade_building.php");
    echo json_encode(['success' => false, 'message' => 'Instance manquante']);
    exit;
}

if (!$tid) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Récupération ID numérique du bâtiment (commun aux deux actions)
    $stmt_id = $pdo->prepare("SELECT ID FROM buildingid WHERE TID = ? LIMIT 1");
    $stmt_id->execute([$tid]);
    $b_row = $stmt_id->fetch(PDO::FETCH_ASSOC);
    if (!$b_row) throw new Exception("Bâtiment inconnu.");
    $id_building = (int)$b_row['ID'];

    // 2. Ligne de progression existante pour cette instance (commun aux deux actions)
    $stmt_check = $pdo->prepare("SELECT niveau FROM progress_building WHERE id_player = ? AND id_building = ? AND id_instance = ? LIMIT 1");
    $stmt_check->execute([$id_player, $id_building, $id_instance]);
    $existing_row = $stmt_check->fetch(PDO::FETCH_ASSOC);
    $niveau_en_base = $existing_row ? (int)$existing_row['niveau'] : 0;

    if ($action === 'downgrade') {
        // --- RÉTROGRADATION ---
        $current_level = isset($_POST['current_level']) ? intval($_POST['current_level']) : null;
        if ($current_level === null) throw new Exception("Données manquantes.");
        if ($current_level <= 0) throw new Exception("Ce bâtiment est déjà à son niveau minimum.");

        // Garde-fou anti-course/anti-triche : le niveau en base doit correspondre à ce que
        // la card affichait au moment du clic.
        if ($niveau_en_base !== $current_level) {
            throw new Exception("Le niveau a changé entre-temps, recharge la page et réessaie.");
        }

        $target_level = $current_level - 1;

        // XP à RETIRER : même formule que pour un gain (mines = niveau-1, sinon niveau),
        // appliquée au niveau qu'on quitte (current_level).
        $xp_level = in_array($tid, ['TID_TRAP_MINE', 'TID_TRAP_TANK_MINE', 'TID_TRAP_SHOCK_MINE'])
            ? $current_level - 1
            : $current_level;

        $stmt_xp = $pdo->prepare("SELECT XpGain FROM buildings WHERE TID = ? AND Niveau = ?");
        $stmt_xp->execute([$tid, $xp_level]);
        $xp_delta = -1 * (int)($stmt_xp->fetchColumn() ?: 0);

    } else {
        // --- AMÉLIORATION (comportement historique, inchangé) ---
        $target_level = isset($_POST['target_level']) ? intval($_POST['target_level']) : null;
        if ($target_level === null) throw new Exception("Données manquantes.");

        $xp_delta = 0;
        if ($target_level > 0) {
            // Les mines utilisent le niveau ACTUEL (comme les personnages)
            $xp_level = in_array($tid, ['TID_TRAP_MINE', 'TID_TRAP_TANK_MINE', 'TID_TRAP_SHOCK_MINE'])
                ? $target_level - 1  // Mines : XP du niveau ACTUEL
                : $target_level;     // Bâtiments : XP du niveau CIBLE

            $stmt_xp = $pdo->prepare("SELECT XpGain FROM buildings WHERE TID = ? AND Niveau = ?");
            $stmt_xp->execute([$tid, $xp_level]);
            $xp_delta = (int)($stmt_xp->fetchColumn() ?: 0);
        }
    }

    // 3. Mise à jour du niveau (commun) : Construire / Améliorer / Rétrograder passent tous par ici.
    //    Le bâtiment est considéré comme débloqué (construit) dès que son niveau est >= 1.
    $debloque = ($target_level > 0) ? 1 : 0;

    if ($existing_row) {
        $stmt_action = $pdo->prepare("UPDATE progress_building SET niveau = ?, Debloque = ? WHERE id_player = ? AND id_building = ? AND id_instance = ?");
        $stmt_action->execute([$target_level, $debloque, $id_player, $id_building, $id_instance]);
    } else {
        $stmt_action = $pdo->prepare("INSERT INTO progress_building (id_player, id_building, id_instance, niveau, Debloque) VALUES (?, ?, ?, ?, ?)");
        $stmt_action->execute([$id_player, $id_building, $id_instance, $target_level, $debloque]);
    }

    // 4. Mise à jour XP joueur (commun) : positif à l'amélioration, négatif à la rétrogradation,
    //    jamais en dessous de 0.
    if ($xp_delta !== 0) {
        $stmt_update_xp = $pdo->prepare("UPDATE joueurs SET experience = GREATEST(0, experience + ?) WHERE id_player = ?");
        $stmt_update_xp->execute([$xp_delta, $id_player]);
    }

    $pdo->commit();

    // Le Radar (id_building = 14, "Salle des cartes") conditionne le déblocage des Tribus.
    // Uniquement à l'amélioration : on ne retire jamais de progression de tribu à la rétrogradation
    // (voir seedTribusUnlockRows, appelée aussi depuis update_qg.php).
    if ($action !== 'downgrade' && $id_building === 14) {
        seedTribusUnlockRows($pdo, $id_player, $target_level);
    }

    echo json_encode([
        'success'   => true,
        'new_level' => $target_level,
        'xp_delta'  => $xp_delta,
        'message'   => ($action === 'downgrade') ? 'Bâtiment rétrogradé.' : 'Mise à jour réussie.'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}