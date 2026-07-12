<?php
// upgrade_character.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

// Récupération de l'action : soit 'upgrade' (par défaut), soit 'unlock_officer'
$action = $_POST['action'] ?? 'upgrade';

try {
    if ($action === 'unlock_officer') {
        // --- LOGIQUE DÉBLOCAGE OFFICIER ---
        $id_character = isset($_POST['id_character']) ? (int)$_POST['id_character'] : null;
        if (!$id_character) throw new Exception("ID de l'officier manquant.");

        // Niveau à donner à l'officier au déblocage : celui de sa troupe correspondante
        // (characterid.Officer, sur la ligne de l'officier, contient le TID de sa troupe),
        // pas "1" en dur — sinon un officier débloqué tardivement (troupe déjà haut niveau)
        // se retrouvait bloqué sur des capacités qu'il n'aurait jamais dû avoir à re-débloquer.
        $niveau_officier = 1;
        $stmt_troop_tid = $pdo->prepare("SELECT Officer FROM characterid WHERE ID = ?");
        $stmt_troop_tid->execute([$id_character]);
        $troop_tid = $stmt_troop_tid->fetchColumn();
        if ($troop_tid) {
            $stmt_troop_id = $pdo->prepare("SELECT ID FROM characterid WHERE TID = ? LIMIT 1");
            $stmt_troop_id->execute([$troop_tid]);
            $troop_char_id = $stmt_troop_id->fetchColumn();
            if ($troop_char_id) {
                $stmt_troop_lvl = $pdo->prepare("SELECT niveau FROM progress_character WHERE id_player = ? AND id_character = ?");
                $stmt_troop_lvl->execute([$id_player, (int)$troop_char_id]);
                $niveau_officier = (int)($stmt_troop_lvl->fetchColumn() ?: 1);
            }
        }

        // 🔥 CORRECTION : Vérifier si l'officier existe déjà dans progress_character
        $stmt_check = $pdo->prepare("SELECT 1 FROM progress_character WHERE id_player = ? AND id_character = ?");
        $stmt_check->execute([$id_player, $id_character]);

        if ($stmt_check->fetch()) {
            // Si existe déjà : mettre à jour Debloque (et resynchroniser le niveau au passage)
            $stmt = $pdo->prepare("UPDATE progress_character SET Debloque = 1, niveau = ? WHERE id_player = ? AND id_character = ?");
            $stmt->execute([$niveau_officier, $id_player, $id_character]);
        } else {
            // 🔥 SINON : INSÉRER avec Debloque=1 (c'était le problème !)
            $stmt = $pdo->prepare("INSERT INTO progress_character (id_player, id_character, niveau, Debloque) VALUES (?, ?, ?, 1)");
            $stmt->execute([$id_player, $id_character, $niveau_officier]);
        }

        // --- Insertion des CAPACITÉS (Active + Passive) avec Debloque=1 ---
        $stmt_abilities = $pdo->prepare("
            SELECT ai.id
            FROM characterid ci
            INNER JOIN officer_talents ot ON ot.TID = ci.TID
            INNER JOIN abilitieid ai ON ai.TID IN (ot.ActiveAbility, ot.PassiveAbility)
            WHERE ci.id = ?
            AND ai.TID IS NOT NULL
        ");
        $stmt_abilities->execute([$id_character]);
        $abilities = $stmt_abilities->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($abilities)) {
    // 🔥 CORRECTION : D'abord mettre à jour les capacités existantes
    $placeholders = implode(',', array_fill(0, count($abilities), '?'));
    $stmt_update = $pdo->prepare("
        UPDATE progress_ability
        SET Debloque = 1, niveau = 1
        WHERE id_player = ? AND id_character = ? AND id_ability IN ($placeholders)
    ");
    $stmt_update->execute(array_merge([$id_player, $id_character], $abilities));

    // 🔥 Puis insérer celles qui n'existent pas encore
    $stmt_insert = $pdo->prepare("
        INSERT IGNORE INTO progress_ability
        (id_player, id_character, id_ability, niveau, Debloque)
        VALUES (?, ?, ?, 1, 1)
    ");
    foreach ($abilities as $id_ability) {
        $stmt_insert->execute([$id_player, $id_character, $id_ability]);
    }
}





        // 🔥 Insertion des TALENTS avec Debloque=0
        $stmt_talents = $pdo->prepare("
            SELECT ai.id
            FROM characterid ci
            INNER JOIN officer_talents ot ON ot.TID = ci.TID
            INNER JOIN abilitieid ai ON ai.TID IN (ot.TalentTID1, ot.TalentTID2, ot.TalentTID3, ot.TalentTID4, ot.TalentTID5)
            WHERE ci.id = ?
            AND ai.TID IS NOT NULL
        ");
        $stmt_talents->execute([$id_character]);
        $talents = $stmt_talents->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($talents)) {
            $stmt_insert_talent = $pdo->prepare("INSERT IGNORE INTO progress_ability (id_player, id_character, id_ability, niveau, Debloque) VALUES (?, ?, ?, 1, 0)");
            foreach ($talents as $id_talent) {
                $stmt_insert_talent->execute([$id_player, $id_character, $id_talent]);
            }
        }

        echo json_encode(['success' => true, 'message' => 'Officier débloqué !']);
        exit;
    } else {
        // --- LOGIQUE AMÉLIORATION CLASSIQUE ---
        $tid = isset($_POST['tid']) ? trim($_POST['tid']) : null;
        $target_level = isset($_POST['target_level']) ? intval($_POST['target_level']) : null;

        if (!$tid || $target_level === null) throw new Exception("Données manquantes.");

        $pdo->beginTransaction();

        // 1. Récupération ID troupe
        $stmt_id = $pdo->prepare("SELECT id FROM characterid WHERE TID = ? LIMIT 1");
        $stmt_id->execute([$tid]);
        $char_row = $stmt_id->fetch(PDO::FETCH_ASSOC);
        if (!$char_row) throw new Exception("Troupe introuvable.");
        $id_character = (int)$char_row['id'];

        // 2. Mise à jour ou Insertion dans progress_character
        $stmt_check = $pdo->prepare("SELECT 1 FROM progress_character WHERE id_player = ? AND id_character = ? LIMIT 1");
        $stmt_check->execute([$id_player, $id_character]);

        if ($stmt_check->fetch()) {
            $stmt_action = $pdo->prepare("UPDATE progress_character SET niveau = ? WHERE id_player = ? AND id_character = ?");
            $stmt_action->execute([$target_level, $id_player, $id_character]);
        } else {
            $stmt_action = $pdo->prepare("INSERT INTO progress_character (id_player, id_character, niveau, Debloque) VALUES (?, ?, ?, 1)");
            $stmt_action->execute([$id_player, $id_character, $target_level]);
        }

        // 2bis. Synchronisation des Officiers liés à cette troupe.
        // characterid.Officer (sur la ligne de l'OFFICIER) contient le TID de la TROUPE dont il
        // dépend — plusieurs officiers peuvent partager la même troupe (ex. TID_BOMBARDIER en a 5).
        // Leur niveau (utilisé pour le déblocage des paliers de capacité via HeroLevel) doit
        // rester calqué sur celui de la troupe, qu'ils soient déjà débloqués ou non : sans ça,
        // un officier reste bloqué au niveau qu'il avait lors de son déblocage pour toujours,
        // même si sa troupe continue de monter en niveau (d'où "Niveau 6 requis" qui ne
        // débloquait jamais).
        $stmt_officers = $pdo->prepare("SELECT ID FROM characterid WHERE Officer = ?");
        $stmt_officers->execute([$tid]);
        $officer_ids = $stmt_officers->fetchAll(PDO::FETCH_COLUMN);

        foreach ($officer_ids as $officer_id) {
            $officer_id = (int)$officer_id;
            $stmt_check_ofc = $pdo->prepare("SELECT 1 FROM progress_character WHERE id_player = ? AND id_character = ? LIMIT 1");
            $stmt_check_ofc->execute([$id_player, $officer_id]);

            if ($stmt_check_ofc->fetch()) {
                $stmt_update_ofc = $pdo->prepare("UPDATE progress_character SET niveau = ? WHERE id_player = ? AND id_character = ?");
                $stmt_update_ofc->execute([$target_level, $id_player, $officer_id]);
            } else {
                // Pas encore débloqué : on garde Debloque = 0 (le déblocage reste un choix
                // explicite du joueur), mais on pré-remplit déjà le niveau pour qu'il soit
                // cohérent dès qu'il sera débloqué.
                $stmt_insert_ofc = $pdo->prepare("INSERT INTO progress_character (id_player, id_character, niveau, Debloque) VALUES (?, ?, ?, 0)");
                $stmt_insert_ofc->execute([$id_player, $officer_id, $target_level]);
            }
        }

        // 3. Calcul du gain d'XP
        $stmt_xp = $pdo->prepare("SELECT XpGain FROM characters WHERE TID = ? AND Niveau = ?");
        $stmt_xp->execute([$tid, $target_level - 1]);
        $xp_gain = $stmt_xp->fetchColumn();

        if ($xp_gain === false) throw new Exception("Aucun gain XP trouvé.");

        $xp_gain = (int)$xp_gain;

        // 4. Mise à jour de l'expérience du joueur
        if ($xp_gain > 0) {
            $stmt_update_xp = $pdo->prepare("UPDATE joueurs SET experience = experience + ? WHERE id_player = ?");
            $stmt_update_xp->execute([$xp_gain, $id_player]);
        }

        $pdo->commit();

        // 5. Récupération du nouvel XP total
        $stmt_new = $pdo->prepare("SELECT experience FROM joueurs WHERE id_player = ?");
        $stmt_new->execute([$id_player]);
        $new_xp_total = (int)$stmt_new->fetchColumn();

        echo json_encode(['success' => true, 'new_xp' => $new_xp_total, 'message' => 'Amélioration réussie !']);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}