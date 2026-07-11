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

    // On vérifie l'existence AVANT d'insérer : INSERT IGNORE ne protège que s'il existe une
    // contrainte UNIQUE sur (id_player, id_character), ce qui n'est apparemment pas le cas ici
    // (symptôme observé : un même héros dupliqué plusieurs fois dans la liste des Héros).
    $stmt_check_unit = $pdo->prepare("SELECT 1 FROM progress_character WHERE id_player = ? AND id_character = ? LIMIT 1");
    $stmt_ins_unit = $pdo->prepare("INSERT INTO progress_character (id_player, id_character, niveau, Debloque) VALUES (?, ?, 1, ?)");
    foreach ($troupes as $troupe) {
        $id = $troupe['id'] ?? $troupe['ID'];
        $unlock_val = (trim($troupe['Class']) === 'Officier') ? 0 : 1;

        $stmt_check_unit->execute([$_SESSION['player_id'], $id]);
        if (!$stmt_check_unit->fetch()) {
            $stmt_ins_unit->execute([$_SESSION['player_id'], $id, $unlock_val]);
        }
    }

    // --- SECTION BÂTIMENTS (Nouvelle logique basée sur townhall_levels) ---
    // v_all_unlocks n'existe plus. On lit directement la ligne du QG obtenu dans
    // townhall_levels : chaque colonne "TID_xxx" contient le nombre CUMULÉ d'instances
    // débloquées à ce niveau (ex: TID_BUILDING_LANDING_SHIP = 3 => les instances 1, 2 et 3
    // doivent exister pour ce joueur). On complète donc les instances manquantes.
    $stmt_th = $pdo->prepare("SELECT * FROM townhall_levels WHERE TownHallLevel = ?");
    $stmt_th->execute([$new_qg]);
    $qg_row = $stmt_th->fetch(PDO::FETCH_ASSOC);

    if ($qg_row) {
        // Colonnes de la table qui ne représentent pas des bâtiments
        $colonnes_ignorees = [
            'TownHallLevel', 'XP', 'RequiredBuilding',
            'RequiredBuildingLevel', 'RequiredTroopLevel', 'MaterialSlots'
        ];

        // Mapping TID (colonne) -> id_building
        $tid_to_id = [];
        foreach ($pdo->query("SELECT id, TID FROM buildingid")->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $tid_to_id[$b['TID']] = $b['id'];
        }

        // Instances déjà présentes pour ce joueur (pour ne rien insérer en double)
        $stmt_existing = $pdo->prepare("SELECT id_building, id_instance FROM progress_building WHERE id_player = ?");
        $stmt_existing->execute([$_SESSION['player_id']]);
        $existing = [];
        foreach ($stmt_existing->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existing[$row['id_building'] . '_' . $row['id_instance']] = true;
        }

        $stmt_ins_b = $pdo->prepare("
            INSERT INTO progress_building (id_player, id_building, id_instance, niveau, Debloque)
            VALUES (?, ?, ?, 0, 0)
        ");

        foreach ($qg_row as $colonne => $valeur) {
            if (in_array($colonne, $colonnes_ignorees, true)) {
                continue;
            }
            if (!isset($tid_to_id[$colonne])) {
                // Colonne qui n'a pas d'équivalent dans buildingid (ex: pièges TID_TRAP_*)
                continue;
            }

            $id_building = $tid_to_id[$colonne];
            $nb_instances = (int)$valeur;

            for ($id_instance = 1; $id_instance <= $nb_instances; $id_instance++) {
                $cle = $id_building . '_' . $id_instance;
                if (!isset($existing[$cle])) {
                    $stmt_ins_b->execute([$_SESSION['player_id'], $id_building, $id_instance]);
                    $existing[$cle] = true; // évite les doublons dans la même boucle
                }
            }
        }
    }

    $pdo->commit(); // Validation finale
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack(); // Annulation en cas d'erreur
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;