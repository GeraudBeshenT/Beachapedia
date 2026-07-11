<?php
// upgrade_tribs.php

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

// 2. Récupération de l'ID de la tribu envoyé en POST (ou JSON)
$input = json_decode(file_get_contents('php://input'), true);
$id_trib = isset($input['id_trib']) ? (int)$input['id_trib'] : (isset($_POST['id_trib']) ? (int)$_POST['id_trib'] : null);

if (!$id_trib) {
    echo json_encode(['success' => false, 'message' => 'ID de tribu manquant.']);
    exit();
}

try {
    // 3. Sécurité serveur : on revérifie que le Radar du joueur autorise bien cette tribu
    //    (le bouton est déjà désactivé côté client, mais on ne fait jamais confiance au front).
    $stmt_trib = $pdo->prepare("SELECT TID, RadarLvlReq FROM tribsid WHERE id = ?");
    $stmt_trib->execute([$id_trib]);
    $trib_info = $stmt_trib->fetch(PDO::FETCH_ASSOC);

    if (!$trib_info) {
        echo json_encode(['success' => false, 'message' => 'Tribu introuvable.']);
        exit();
    }

    $stmt_radar = $pdo->prepare("SELECT niveau FROM progress_building WHERE id_player = ? AND id_building = 14 LIMIT 1");
    $stmt_radar->execute([$id_player]);
    $radar_level = (int)($stmt_radar->fetchColumn() ?: 0);

    if ($radar_level < (int)$trib_info['RadarLvlReq']) {
        echo json_encode([
            'success' => false,
            'message' => 'Radar niveau ' . $trib_info['RadarLvlReq'] . ' requis pour accéder à cette tribu (niveau actuel : ' . $radar_level . ').'
        ]);
        exit();
    }

    // 4. Charger la ligne de progression existante, s'il y en a une.
    //    Normalement, dès que le Radar atteint le niveau requis, seedTribusUnlockRows()
    //    (voir functions.php, appelée depuis upgrade_building.php) a déjà créé une ligne
    //    (niveau=0, Debloque=0) — mais on gère aussi le cas où elle n'existerait pas
    //    encore, en filet de sécurité.
    $stmt_check = $pdo->prepare("SELECT niveau, Debloque FROM progress_tribs WHERE id_player = ? AND id_trib = ?");
    $stmt_check->execute([$id_player, $id_trib]);
    $current = $stmt_check->fetch(PDO::FETCH_ASSOC);

    $was_debloque = $current && (int)$current['Debloque'] === 1;

    if (!$was_debloque) {
        // --- Action "Débloquer" : on passe (ou on crée) Debloque = 1. Le niveau reste à 0. ---
        if ($current === false) {
            $stmt_insert = $pdo->prepare("INSERT INTO progress_tribs (id_player, id_trib, niveau, Debloque) VALUES (?, ?, 0, 1)");
            $stmt_insert->execute([$id_player, $id_trib]);
        } else {
            $stmt_update = $pdo->prepare("UPDATE progress_tribs SET Debloque = 1 WHERE id_player = ? AND id_trib = ?");
            $stmt_update->execute([$id_player, $id_trib]);
        }

        echo json_encode([
            'success'   => true,
            'action'    => 'unlocked',
            'new_level' => $current ? (int)$current['niveau'] : 0,
            'id_trib'   => $id_trib,
            'message'   => 'Tribu débloquée avec succès !'
        ]);
        exit();
    }

    // --- Action "Améliorer" : la tribu est déjà débloquée, on incrémente son niveau ---
    $new_level = (int)$current['niveau'] + 1;

    // Optionnel : ajouter ici une sécurité pour vérifier que le niveau ne dépasse pas
    // le niveau max défini dans la table `tribs` pour ce TID.

    $stmt_update = $pdo->prepare("UPDATE progress_tribs SET niveau = ? WHERE id_player = ? AND id_trib = ?");
    $stmt_update->execute([$new_level, $id_player, $id_trib]);

    echo json_encode([
        'success'   => true,
        'action'    => 'updated',
        'new_level' => $new_level,
        'id_trib'   => $id_trib,
        'message'   => 'Tribu améliorée avec succès !'
    ]);

} catch (PDOException $e) {
    // En cas d'erreur de base de données
    error_log("Erreur upgrade_tribs : " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la mise à jour de la base de données.'
    ]);
}