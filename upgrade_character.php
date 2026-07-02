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

        $stmt = $pdo->prepare("UPDATE progress_character SET Debloque = 1 WHERE id_player = ? AND id_character = ?");
        $stmt->execute([$id_player, $id_character]);

        // --- Insertion des capacités (Active + Passive) par défaut ---
        // Chaîne : id_character -> characterid.TID -> officer_talents.ActiveAbility/PassiveAbility -> abilitieid.id
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
            $stmt_insert = $pdo->prepare("
                INSERT IGNORE INTO progress_ability (id_player, id_character, id_ability, niveau) 
                VALUES (?, ?, ?, 1)
            ");
            foreach ($abilities as $id_ability) {
                $stmt_insert->execute([$id_player, $id_character, $id_ability]);
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