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
    // 1. Récupération de la capacité + vérification de l'appartenance
    $stmt_info = $pdo->prepare("
        SELECT ai.TID AS ability_tid, ai.hero, ai.Type, t.FR AS talent_nom, c.TID AS character_tid, c.id AS character_id
        FROM abilitieid ai
        JOIN texts t ON ai.TID = t.TID
        LEFT JOIN characterid c ON c.TID = ai.hero
        WHERE ai.id = ?
    ");
    $stmt_info->execute([$id_ability]);
    $info = $stmt_info->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        echo json_encode(['success' => false, 'message' => 'Capacité introuvable']);
        exit;
    }

    $ability_tid = $info['ability_tid'];
    $is_hero_ability = !empty($info['hero']);
    $is_officer_ability = (strpos($info['Type'], 'OfficerAbility') === 0);
    $nom_du_talent = $info['talent_nom'] ?? "Talent";
    $nom_de_la_troupe = $info['character_tid'] ?? "l'unité";

    // Vérification de l'appartenance
    if ($is_hero_ability) {
        if ((int)$info['character_id'] !== $id_character) {
            echo json_encode(['success' => false, 'message' => "Cette capacité n'appartient pas à ce personnage"]);
            exit;
        }
    } else {
        // Pour les officiers : vérifier via officer_talents
        $stmt_owns = $pdo->prepare("
            SELECT 1
            FROM characterid ci
            JOIN officer_talents ot ON ot.TID = ci.TID
            WHERE ci.id = ?
              AND ? IN (ot.ActiveAbility, ot.PassiveAbility)
            LIMIT 1
        ");
        $stmt_owns->execute([$id_character, $ability_tid]);
        if (!$stmt_owns->fetch()) {
            echo json_encode(['success' => false, 'message' => "Cette capacité n'appartient pas à ce personnage"]);
            exit;
        }
    }

    // 2. Niveau actuel de la capacité
    $stmt = $pdo->prepare("SELECT niveau FROM progress_ability WHERE id_player = ? AND id_character = ? AND id_ability = ?");
    $stmt->execute([$id_player, $id_character, $id_ability]);
    $prog = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_ability_niveau = $prog ? (int)$prog['niveau'] : 1;

    // 3. Niveau MAX autorisé pour cette capacité
    $stmt_max_level = $pdo->prepare("SELECT MAX(Niveau) FROM officer_abilities WHERE TID = ?");
    $stmt_max_level->execute([$ability_tid]);
    $max_level = (int)($stmt_max_level->fetchColumn() ?: ($is_hero_ability ? 7 : 16));

    if ($current_ability_niveau >= $max_level) {
        echo json_encode([
            'success' => false,
            'message' => 'Capacité déjà au niveau maximum',
            'is_max' => true
        ]);
        exit;
    }

    // 4. 🔥 CORRECTION : Niveau de la troupe/du héros (selon le type)
    if ($is_hero_ability) {
        // Pour les héros : niveau du héros
        $stmt_char_lvl = $pdo->prepare("SELECT niveau FROM progress_character WHERE id_player = ? AND id_character = ? LIMIT 1");
        $stmt_char_lvl->execute([$id_player, $id_character]);
        $character_niveau = (int)($stmt_char_lvl->fetchColumn() ?: 1);
    } else {
        // Pour les officiers : niveau de la TROUPE DE BASE (via colonne Officer)
        $stmt_base_troop = $pdo->prepare("SELECT Officer FROM characterid WHERE id = ? LIMIT 1");
        $stmt_base_troop->execute([$id_character]);
        $base_troop = $stmt_base_troop->fetch(PDO::FETCH_ASSOC);

        if ($base_troop && $base_troop['Officer']) {
            $stmt_base_troop_id = $pdo->prepare("SELECT id FROM characterid WHERE TID = ? LIMIT 1");
            $stmt_base_troop_id->execute([$base_troop['Officer']]);
            $base_troop_id = $stmt_base_troop_id->fetch(PDO::FETCH_ASSOC);

            if ($base_troop_id) {
                $stmt_char_lvl = $pdo->prepare("SELECT niveau FROM progress_character WHERE id_player = ? AND id_character = ? LIMIT 1");
                $stmt_char_lvl->execute([$id_player, $base_troop_id['id']]);
                $character_niveau = (int)($stmt_char_lvl->fetchColumn() ?: 1);
            } else {
                $character_niveau = 1; // Fallback
            }
        } else {
            $character_niveau = 1; // Fallback
        }
    }

    // 5. Récupérer les infos du niveau SUIVANT
    $next_level = $current_ability_niveau + 1;
    $stmt_next = $pdo->prepare("
        SELECT Niveau, HeroLevel, UpgradeCost, UpgradeResource, UpgradeTimeH
        FROM officer_abilities
        WHERE TID = ? AND Niveau = ?
        LIMIT 1
    ");
    $stmt_next->execute([$ability_tid, $next_level]);
    $next = $stmt_next->fetch(PDO::FETCH_ASSOC);

    if (!$next) {
        echo json_encode([
            'success' => false,
            'message' => 'Niveau suivant introuvable',
            'is_max' => true
        ]);
        exit;
    }

    // 6. Vérifier le niveau requis (héros OU troupe de base)
    $required_hero_level = (int)$next['HeroLevel'];
    if ($character_niveau < $required_hero_level) {
        echo json_encode([
            'success' => false,
            'message' => "Niveau {$required_hero_level} requis pour améliorer cette capacité"
        ]);
        exit;
    }

    // 7. Mise à jour de la progression
    $new_level = $current_ability_niveau + 1;
    if (!$prog) {
        $pdo->prepare("INSERT INTO progress_ability (id_player, id_character, id_ability, niveau) VALUES (?,?,?,?)")
            ->execute([$id_player, $id_character, $id_ability, $new_level]);
    } else {
        $pdo->prepare("UPDATE progress_ability SET niveau = ? WHERE id_player = ? AND id_character = ? AND id_ability = ?")
            ->execute([$new_level, $id_player, $id_character, $id_ability]);
    }

    // 8. Vérifier si c'est le niveau max après cette amélioration
    $is_max = ($new_level >= $max_level);

    // 9. Retour du succès
    echo json_encode([
        'success' => true,
        'new_level' => $new_level,
        'is_max' => $is_max,
        'talent_nom' => $nom_du_talent,
        'troupe_nom' => $nom_de_la_troupe,
        'message' => 'Amélioration réussie'
    ]);

} catch (Exception $e) {
    error_log("ERREUR SQL UPGRADE_ABILITY : " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur technique: ' . $e->getMessage()
    ]);
}