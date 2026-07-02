<?php
// upgrade_monument.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

header('Content-Type: application/json');

// 1. Vérification de la méthode et de la session
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

$id_player = $_SESSION['player_id'] ?? null;
if (!$id_player) {
    echo json_encode(['success' => false, 'message' => 'Non connecté.']);
    exit;
}

// 2. Récupération des données POST
$action_type  = isset($_POST['type']) ? trim($_POST['type']) : null; 
$target_id    = isset($_POST['id']) ? trim($_POST['id']) : null;     
$target_value = isset($_POST['value']) ? intval($_POST['value']) : 0;

if (!$action_type || !$target_id) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes.']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($action_type === 'level') {
        // --- MISE À JOUR NIVEAU MM ---
        $stmt_bid = $pdo->prepare("SELECT id FROM buildingid WHERE TID = 'TID_BUILDING_CC' LIMIT 1");
        $stmt_bid->execute();
        $id_building = $stmt_bid->fetchColumn();

        if (!$id_building) {
            throw new Exception("Monument Mystique introuvable.");
        }

        $stmt_check = $pdo->prepare("SELECT 1 FROM progress_building WHERE id_player = ? AND id_building = ? AND id_instance = 1 LIMIT 1");
        $stmt_check->execute([$id_player, $id_building]);
        
        if ($stmt_check->fetch()) {
            $stmt_upd = $pdo->prepare("UPDATE progress_building SET niveau = ? WHERE id_player = ? AND id_building = ? AND id_instance = 1");
            $stmt_upd->execute([$target_value, $id_player, $id_building]);
        } else {
            $stmt_ins = $pdo->prepare("INSERT INTO progress_building (id_player, id_building, id_instance, niveau) VALUES (?, ?, 1, ?)");
            $stmt_ins->execute([$id_player, $id_building, $target_value]);
        }
        
    } elseif ($action_type === 'bonus') {
        // --- MISE À JOUR BONUS ---
        // Vérification de limite MaxCount
        $stmt_limit = $pdo->prepare("SELECT MaxCount FROM cc_bonuses WHERE id_bonus = ? LIMIT 1");
        $stmt_limit->execute([$target_id]);
        $max_allowed = (int)$stmt_limit->fetchColumn();

        if ($target_value > $max_allowed) {
            $target_value = $max_allowed;
        }

        // Vérification existence en BDD
        $stmt_check_b = $pdo->prepare("SELECT 1 FROM progress_monument WHERE id_player = ? AND id_bonus = ? LIMIT 1");
        $stmt_check_b->execute([$id_player, $target_id]);

        if ($stmt_check_b->fetch()) {
            $stmt_action = $pdo->prepare("UPDATE progress_monument SET nb_bonus = ? WHERE id_player = ? AND id_bonus = ?");
            $stmt_action->execute([$target_value, $id_player, $target_id]);
        } else {
            $stmt_action = $pdo->prepare("INSERT INTO progress_monument (id_player, id_bonus, nb_bonus) VALUES (?, ?, ?)");
            $stmt_action->execute([$id_player, $target_id, $target_value]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}