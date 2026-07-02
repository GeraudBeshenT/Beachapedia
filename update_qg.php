<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

if (!isset($_SESSION['player_id'])) {
    exit(json_encode(['success' => false, 'message' => 'Non connecté']));
}

$new_qg = isset($_POST['qg_level']) ? (int)$_POST['qg_level'] : null;

if ($new_qg === null) {
    exit(json_encode(['success' => false, 'message' => 'Données manquantes']));
}

try {
    $pdo->beginTransaction(); // On sécurise le tout avec une transaction

    // 1. Mise à jour du QG
    $stmt = $pdo->prepare("UPDATE joueurs SET qg = ? WHERE id_player = ?");
    $stmt->execute([$new_qg, $_SESSION['player_id']]);

    // --- SECTION UNITÉS (Ton code actuel) ---
    $stmt_unlock = $pdo->prepare("SELECT id, Class FROM characterid WHERE HQUnlock = ?");
    $stmt_unlock->execute([$new_qg]);
    $troupes = $stmt_unlock->fetchAll(PDO::FETCH_ASSOC);

    $stmt_ins_unit = $pdo->prepare("INSERT IGNORE INTO progress_character (id_player, id_character, niveau, Debloque) VALUES (?, ?, 1, ?)");
    foreach ($troupes as $troupe) {
        $id = $troupe['id'] ?? $troupe['ID'];
        $unlock_val = (trim($troupe['Class']) === 'Officier') ? 0 : 1;
        $stmt_ins_unit->execute([$_SESSION['player_id'], $id, $unlock_val]);
    }

    // --- SECTION BÂTIMENTS (Nouvelle logique) ---
    // On récupère le TID des bâtiments débloqués pour ce niveau de QG
    // --- SECTION BÂTIMENTS (Logique filtrée et corrigée) ---
    $stmt_b = $pdo->prepare("
        SELECT v.TID, b.id as id_building, v.Amount as id_instance 
        FROM v_all_unlocks v
        INNER JOIN buildingid b ON v.TID = b.TID
        LEFT JOIN progress_building pb ON pb.id_building = b.id 
                                       AND pb.id_player = ? 
                                       AND pb.id_instance = v.Amount
        WHERE v.TownHallLevel = ?
        AND v.Amount > 0
        AND (pb.id_building IS NULL)
    ");
    
    // Ici, il faut DEUX paramètres : 
    // 1. L'id_player (pour le LEFT JOIN)
    // 2. Le new_qg (pour le WHERE)
    $stmt_b->execute([$_SESSION['player_id'], $new_qg]); 
    $batiments = $stmt_b->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($batiments)) {
        $stmt_ins_b = $pdo->prepare("
            INSERT IGNORE INTO progress_building (id_player, id_building, id_instance, niveau, Debloque) 
            VALUES (?, ?, ?, 0, 0)
        ");
        
        foreach ($batiments as $bat) {
            // Ici, il faut TROIS paramètres qui correspondent aux trois '?'
            $stmt_ins_b->execute([
                $_SESSION['player_id'], 
                $bat['id_building'], 
                $bat['id_instance']
            ]);
        }
    }

    $pdo->commit(); // Validation finale
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack(); // Annulation en cas d'erreur
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;