<?php
// upgrade_engraving.php

// 1. Démarrage de la session et sécurité
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$id_player = $_SESSION['player_id'] ?? null;
if (!$id_player) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté.']);
    exit();
}

require_once 'config.php'; // Contient ta connexion $pdo

// 2. Récupération de l'ID de la gravure envoyé en POST (ou JSON)
$input = json_decode(file_get_contents('php://input'), true);
$id_engraving = isset($input['id_engraving']) ? (int)$input['id_engraving'] : (isset($_POST['id_engraving']) ? (int)$_POST['id_engraving'] : null);

if (!$id_engraving) {
    echo json_encode(['success' => false, 'message' => 'ID de gravure manquant.']);
    exit();
}

try {
    // 3. Vérifier si le joueur possède déjà une progression pour cette gravure
    $stmt_check = $pdo->prepare("SELECT niveau FROM progress_engraving WHERE id_player = ? AND id_engraving = ?");
    $stmt_check->execute([$id_player, $id_engraving]);
    $current_progress = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($current_progress === false) {
        // Le joueur n'a jamais amélioré cette gravure -> On l'insère au Niveau 1
        $new_level = 1;
        $stmt_insert = $pdo->prepare("INSERT INTO progress_engraving (id_player, id_engraving, niveau) VALUES (?, ?, ?)");
        $stmt_insert->execute([$id_player, $id_engraving, $new_level]);
        $action = 'inserted';
    } else {
        // La gravure existe déjà -> On augmente son niveau de 1
        $new_level = (int)$current_progress['niveau'] + 1;
        
        // Optionnel : Tu peux ajouter ici une sécurité pour vérifier si le niveau ne dépasse pas le MaxQuality de engravingid
        
        $stmt_update = $pdo->prepare("UPDATE progress_engraving SET niveau = ? WHERE id_player = ? AND id_engraving = ?");
        $stmt_update->execute([$new_level, $id_player, $id_engraving]);
        $action = 'updated';
    }

    // 4. Réponse au format JSON pour ton script JavaScript (AJAX)
    echo json_encode([
        'success' => true,
        'action' => $action,
        'new_level' => $new_level,
        'id_engraving' => $id_engraving,
        'message' => 'Gravure améliorée avec succès !'
    ]);

} catch (PDOException $e) {
    // En cas d'erreur de base de données
    error_log("Erreur upgrade_engraving : " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la mise à jour de la base de données.'
    ]);
}