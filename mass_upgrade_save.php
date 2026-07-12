<?php
// mass_upgrade_save.php
// Endpoint AJAX (POST, JSON) : enregistre en masse la progression saisie dans
// l'onglet "Mass Upgrade". Upsert (INSERT ... ON DUPLICATE KEY UPDATE) sur les
// tables progress_building / progress_character / progress_ability, en une
// seule transaction, avec des requêtes préparées réutilisées dans les boucles
// (donc UNE préparation par table, quel que soit le nombre de lignes envoyées).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'mass_upgrade_helpers.php';
header('Content-Type: application/json');

$id_player = $_SESSION['player_id'] ?? null;
if (!$id_player) {
    echo json_encode(['success' => false, 'message' => 'Non connecté.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Payload invalide.']);
    exit;
}

$qg          = isset($input['qg']) ? (int)$input['qg'] : null;
$buildings   = is_array($input['buildings'] ?? null) ? $input['buildings'] : [];
$characters  = is_array($input['characters'] ?? null) ? $input['characters'] : [];
$abilities   = is_array($input['abilities'] ?? null) ? $input['abilities'] : [];

// Petites limites de sécurité pour éviter un payload absurde/malveillant
$MAX_ROWS = 20000;
if (count($buildings) + count($characters) + count($abilities) > $MAX_ROWS) {
    echo json_encode(['success' => false, 'message' => 'Trop de lignes envoyées en une seule fois.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Niveau de QG choisi
    if ($qg !== null && $qg > 0) {
        $stmt_qg = $pdo->prepare("UPDATE joueurs SET qg = ? WHERE id_player = ?");
        $stmt_qg->execute([$qg, $id_player]);
    }

    // 2. Bâtiments : upsert sur (id_player, id_building, id_instance)
    $nb_buildings = 0;
    if (!empty($buildings)) {
        $stmt_b = $pdo->prepare("
            INSERT INTO progress_building (id_player, id_building, id_instance, niveau, Debloque)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE niveau = VALUES(niveau), Debloque = VALUES(Debloque)
        ");
        foreach ($buildings as $row) {
            $id_building  = (int)($row['id_building'] ?? 0);
            $id_instance  = (int)($row['id_instance'] ?? 0);
            $niveau       = max(0, (int)($row['niveau'] ?? 0));
            if ($id_building <= 0 || $id_instance <= 0) continue;

            $debloque = $niveau > 0 ? 1 : 0;
            $stmt_b->execute([$id_player, $id_building, $id_instance, $niveau, $debloque]);
            $nb_buildings++;
        }
    }

    // 3. Personnages (troupes / héros / officiers) : upsert sur (id_player, id_character)
    $nb_characters = 0;
    if (!empty($characters)) {
        $stmt_c = $pdo->prepare("
            INSERT INTO progress_character (id_player, id_character, niveau, Debloque)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE niveau = VALUES(niveau), Debloque = VALUES(Debloque)
        ");
        foreach ($characters as $row) {
            $id_character = (int)($row['id_character'] ?? 0);
            $niveau       = max(0, (int)($row['niveau'] ?? 0));
            if ($id_character <= 0) continue;

            $debloque = $niveau > 0 ? 1 : 0;
            $stmt_c->execute([$id_player, $id_character, $niveau, $debloque]);
            $nb_characters++;
        }
    }

    // 4. Capacités (héros + officiers) : upsert sur (id_player, id_character, id_ability)
    $nb_abilities = 0;
    if (!empty($abilities)) {
        $stmt_a = $pdo->prepare("
            INSERT INTO progress_ability (id_player, id_character, id_ability, niveau, Debloque)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE niveau = VALUES(niveau), Debloque = VALUES(Debloque)
        ");
        foreach ($abilities as $row) {
            $id_character = (int)($row['id_character'] ?? 0);
            $id_ability   = (int)($row['id_ability'] ?? 0);
            $niveau       = max(0, (int)($row['niveau'] ?? 0));
            $debloque     = isset($row['debloque']) ? (int)$row['debloque'] : ($niveau > 0 ? 1 : 0);

            if ($id_character <= 0 || $id_ability <= 0) continue;

            $stmt_a->execute([$id_player, $id_character, $id_ability, $niveau, $debloque]);
            $nb_abilities++;
        }
    }

    // 5. Recalcul de l'XP totale du joueur à partir de l'état final
    $nouvelle_xp = muRecomputeExperience($pdo, $id_player);
    $stmt_xp = $pdo->prepare("UPDATE joueurs SET experience = ? WHERE id_player = ?");
    $stmt_xp->execute([$nouvelle_xp, $id_player]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Mass Upgrade enregistré avec succès.',
        'nb_buildings'  => $nb_buildings,
        'nb_characters' => $nb_characters,
        'nb_abilities'  => $nb_abilities,
        'experience'    => $nouvelle_xp,
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Erreur mass_upgrade_save : " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur technique : ' . $e->getMessage()]);
}