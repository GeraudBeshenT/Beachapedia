<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);
$id_character = (int)($input['id_character'] ?? 0);
$id_ability = (int)($input['id_ability'] ?? 0);
$id_player = $_SESSION['player_id'] ?? null;

if (!$id_player || !$id_character || !$id_ability) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

try {
    // 1. Récupération des noms pour ton message (Ajouté ici)
    $stmt_info = $pdo->prepare("
        SELECT t.FR as talent_nom, c.TID as troupe_nom 
        FROM abilitieid ai 
        JOIN texts t ON ai.TID = t.TID 
        JOIN characterid c ON c.id = ?
        WHERE ai.id = ?");
    $stmt_info->execute([$id_character, $id_ability]);
    $info = $stmt_info->fetch();
    
    $nom_du_talent = $info['talent_nom'] ?? "Talent";
    $nom_de_la_troupe = $info['troupe_nom'] ?? "l'unité";

    // 2. Mise à jour de la progression
    $stmt = $pdo->prepare("SELECT niveau FROM progress_ability WHERE id_player = ? AND id_character = ? AND id_ability = ?");
    $stmt->execute([$id_player, $id_character, $id_ability]);
    $prog = $stmt->fetch();

    $new_level = 0;
    if (!$prog) {
        $new_level = 2;
        $pdo->prepare("INSERT INTO progress_ability (id_player, id_character, id_ability, niveau) VALUES (?,?,?,?)")
            ->execute([$id_player, $id_character, $id_ability, $new_level]);
    } else {
        $new_level = (int)$prog['niveau'] + 1;
        $pdo->prepare("UPDATE progress_ability SET niveau = ? WHERE id_player = ? AND id_character = ? AND id_ability = ?")
            ->execute([$new_level, $id_player, $id_character, $id_ability]);
    }

    $is_max = ($new_level >= 15);

    // 3. Retour du succès avec les variables bien définies
    echo json_encode([
        'success' => true, 
        'new_level' => $new_level,
        'is_max' => $is_max,
        'talent_nom' => $nom_du_talent,
        'troupe_nom' => $nom_de_la_troupe,
        'message' => 'Mise à jour réussie'
    ]);

} catch (Exception $e) {
    error_log("ERREUR SQL UPGRADE_ABILITY : " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur technique'
    ]);
}   