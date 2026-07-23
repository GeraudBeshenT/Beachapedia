<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'upgrade_level';
$id_character = (int)($input['id_character'] ?? 0);
$id_ability = (int)($input['id_ability'] ?? 0);
$id_player = $_SESSION['player_id'] ?? null;

if (!$id_player || !$id_character || !$id_ability) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

try {
    if ($action === 'unlock_talent') {
        // --- DÉBLOCAGE D'UN TALENT D'OFFICIER ---
        // Contrairement aux capacités Active/Passive, les talents n'ont AUCUNE ligne dans
        // officer_abilities (pas de palier, pas de coût) : c'est un déblocage binaire
        // (Debloque 0 -> 1), dans l'ordre strict Talent 1 -> 2 -> 3 -> 4 -> 5. La vérification
        // d'appartenance doit donc se faire via TalentTID1..5 (et non ActiveAbility/
        // PassiveAbility, réservées aux capacités classiques — c'était le bug : toute demande
        // de déblocage de talent tombait dans cette mauvaise branche et échouait toujours).

        $stmt_char = $pdo->prepare("
            SELECT ci.TID AS char_tid, ot.TalentTID1, ot.TalentTID2, ot.TalentTID3, ot.TalentTID4, ot.TalentTID5
            FROM characterid ci
            LEFT JOIN officer_talents ot ON ot.TID = ci.TID
            WHERE ci.id = ?
        ");
        $stmt_char->execute([$id_character]);
        $char_row = $stmt_char->fetch(PDO::FETCH_ASSOC);
        if (!$char_row) {
            echo json_encode(['success' => false, 'message' => 'Personnage introuvable']);
            exit;
        }

        $stmt_ab = $pdo->prepare("SELECT ai.TID, t.FR AS nom FROM abilitieid ai LEFT JOIN texts t ON t.TID = ai.TID WHERE ai.id = ?");
        $stmt_ab->execute([$id_ability]);
        $ab_row = $stmt_ab->fetch(PDO::FETCH_ASSOC);
        if (!$ab_row) {
            echo json_encode(['success' => false, 'message' => 'Capacité introuvable']);
            exit;
        }
        $nom_du_talent = $ab_row['nom'] ?? 'Talent';

        // Slot (1 à 5) correspondant à cette capacité pour CE personnage précis
        $slot = 0;
        for ($i = 1; $i <= 5; $i++) {
            if (!empty($char_row["TalentTID$i"]) && $char_row["TalentTID$i"] === $ab_row['TID']) {
                $slot = $i;
                break;
            }
        }
        if ($slot === 0) {
            echo json_encode(['success' => false, 'message' => "Cette capacité n'appartient pas à ce personnage"]);
            exit;
        }

        // Ordre progressif : le talent précédent doit déjà être débloqué
        if ($slot > 1) {
            $stmt_prev_id = $pdo->prepare("SELECT id FROM abilitieid WHERE TID = ? LIMIT 1");
            $stmt_prev_id->execute([$char_row["TalentTID" . ($slot - 1)]]);
            $prev_ability_id = (int)$stmt_prev_id->fetchColumn();

            $stmt_prev_unlocked = $pdo->prepare("SELECT Debloque FROM progress_ability WHERE id_player = ? AND id_character = ? AND id_ability = ?");
            $stmt_prev_unlocked->execute([$id_player, $id_character, $prev_ability_id]);
            $prev_debloque = (int)($stmt_prev_unlocked->fetchColumn() ?: 0);

            if (!$prev_debloque) {
                echo json_encode(['success' => false, 'message' => "Vous devez d'abord débloquer le talent précédent"]);
                exit;
            }
        }

        // Déjà débloqué ?
        $stmt_current = $pdo->prepare("SELECT Debloque FROM progress_ability WHERE id_player = ? AND id_character = ? AND id_ability = ?");
        $stmt_current->execute([$id_player, $id_character, $id_ability]);
        if ((int)($stmt_current->fetchColumn() ?: 0) === 1) {
            echo json_encode(['success' => false, 'message' => 'Talent déjà débloqué']);
            exit;
        }

        // Déblocage : niveau = 1 en placeholder (les talents ne sont pas "nivelés")
        $pdo->prepare("
            INSERT INTO progress_ability (id_player, id_character, id_ability, niveau, Debloque)
            VALUES (?, ?, ?, 1, 1)
            ON DUPLICATE KEY UPDATE Debloque = 1, niveau = 1
        ")->execute([$id_player, $id_character, $id_ability]);

        echo json_encode([
            'success' => true,
            'talent_nom' => $nom_du_talent,
            'message' => 'Talent débloqué'
        ]);
        exit;
    }

    if ($action === 'lock_talent') {
        // --- RÉTROGRADATION D'UN TALENT D'OFFICIER (miroir exact de unlock_talent) ---
        // Comme pour le déblocage (1 -> 2 -> 3 -> 4 -> 5), la rétrogradation doit se faire
        // dans l'ordre strict inverse (5 -> 4 -> 3 -> 2 -> 1) : on ne peut re-verrouiller que
        // le DERNIER talent débloqué, jamais un talent "au milieu" de la chaîne.
        $stmt_char = $pdo->prepare("
            SELECT ci.TID AS char_tid, ot.TalentTID1, ot.TalentTID2, ot.TalentTID3, ot.TalentTID4, ot.TalentTID5
            FROM characterid ci
            LEFT JOIN officer_talents ot ON ot.TID = ci.TID
            WHERE ci.id = ?
        ");
        $stmt_char->execute([$id_character]);
        $char_row = $stmt_char->fetch(PDO::FETCH_ASSOC);
        if (!$char_row) {
            echo json_encode(['success' => false, 'message' => 'Personnage introuvable']);
            exit;
        }

        $stmt_ab = $pdo->prepare("SELECT ai.TID, t.FR AS nom FROM abilitieid ai LEFT JOIN texts t ON t.TID = ai.TID WHERE ai.id = ?");
        $stmt_ab->execute([$id_ability]);
        $ab_row = $stmt_ab->fetch(PDO::FETCH_ASSOC);
        if (!$ab_row) {
            echo json_encode(['success' => false, 'message' => 'Capacité introuvable']);
            exit;
        }
        $nom_du_talent = $ab_row['nom'] ?? 'Talent';

        // Slot (1 à 5) correspondant à cette capacité pour CE personnage précis
        $slot = 0;
        for ($i = 1; $i <= 5; $i++) {
            if (!empty($char_row["TalentTID$i"]) && $char_row["TalentTID$i"] === $ab_row['TID']) {
                $slot = $i;
                break;
            }
        }
        if ($slot === 0) {
            echo json_encode(['success' => false, 'message' => "Cette capacité n'appartient pas à ce personnage"]);
            exit;
        }

        // Doit être actuellement débloqué
        $stmt_current = $pdo->prepare("SELECT Debloque FROM progress_ability WHERE id_player = ? AND id_character = ? AND id_ability = ?");
        $stmt_current->execute([$id_player, $id_character, $id_ability]);
        if ((int)($stmt_current->fetchColumn() ?: 0) !== 1) {
            echo json_encode(['success' => false, 'message' => "Ce talent n'est pas débloqué"]);
            exit;
        }

        // Le talent suivant (s'il existe) doit déjà avoir été rétrogradé
        if ($slot < 5) {
            $stmt_next_id = $pdo->prepare("SELECT id FROM abilitieid WHERE TID = ? LIMIT 1");
            $stmt_next_id->execute([$char_row["TalentTID" . ($slot + 1)]]);
            $next_ability_id = (int)$stmt_next_id->fetchColumn();

            if ($next_ability_id) {
                $stmt_next_unlocked = $pdo->prepare("SELECT Debloque FROM progress_ability WHERE id_player = ? AND id_character = ? AND id_ability = ?");
                $stmt_next_unlocked->execute([$id_player, $id_character, $next_ability_id]);
                if ((int)($stmt_next_unlocked->fetchColumn() ?: 0) === 1) {
                    echo json_encode(['success' => false, 'message' => "Vous devez d'abord rétrograder le talent suivant"]);
                    exit;
                }
            }
        }

        // Re-verrouillage : Debloque -> 0, niveau remis au placeholder (1, comme un talent
        // jamais débloqué)
        $pdo->prepare("UPDATE progress_ability SET Debloque = 0, niveau = 1 WHERE id_player = ? AND id_character = ? AND id_ability = ?")
            ->execute([$id_player, $id_character, $id_ability]);

        echo json_encode([
            'success' => true,
            'talent_nom' => $nom_du_talent,
            'message' => 'Talent rétrogradé'
        ]);
        exit;
    }

    // 1. Récupération de la capacité + vérification qu'elle appartient bien à ce personnage
    //    - Capacité de Héros  : abilitieid.hero = id_character
    //    - Capacité d'Officier: liée via officer_talents.ActiveAbility/PassiveAbility -> characterid.TID
    $stmt_info = $pdo->prepare("
        SELECT ai.TID AS ability_tid, ai.hero, t.FR AS talent_nom, c.TID AS troupe_nom
        FROM abilitieid ai
        JOIN texts t ON ai.TID = t.TID
        JOIN characterid c ON c.id = ?
        WHERE ai.id = ?
    ");
    $stmt_info->execute([$id_character, $id_ability]);
    $info = $stmt_info->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        echo json_encode(['success' => false, 'message' => 'Capacité introuvable']);
        exit;
    }

    // 🔥 CORRECTIF : abilitieid.hero contient le TID (chaîne, ex. 'TID_HEROJETPACK') du héros
    // propriétaire, PAS characterid.id — même convention que dans queries.php
    // ("ai.hero = ?" comparé à $u['TID']). L'ancienne comparaison (int)$info['hero'] ===
    // $id_character valait donc toujours faux (cast d'une chaîne TID_* en entier = 0), ce qui
    // faisait tomber TOUTE amélioration de capacité de héros dans la branche "officier" ci-
    // dessous, où elle échouait systématiquement avec "Cette capacité n'appartient pas à ce
    // personnage". c.TID (alias troupe_nom) est justement le TID du personnage ciblé.
    $is_hero_ability = (!empty($info['hero']) && $info['hero'] === $info['troupe_nom']);

    if (!$is_hero_ability) {
        // Capacité d'officier : on vérifie qu'elle fait bien partie de ActiveAbility/PassiveAbility
        // du personnage ciblé
        $stmt_owns = $pdo->prepare("
            SELECT 1
            FROM characterid ci
            JOIN officer_talents ot ON ot.TID = ci.TID
            WHERE ci.id = ?
              AND ? IN (ot.ActiveAbility, ot.PassiveAbility)
            LIMIT 1
        ");
        $stmt_owns->execute([$id_character, $info['ability_tid']]);
        if (!$stmt_owns->fetch()) {
            echo json_encode(['success' => false, 'message' => "Cette capacité n'appartient pas à ce personnage"]);
            exit;
        }
    }

    $nom_du_talent  = $info['talent_nom'] ?? "Talent";
    $nom_de_la_troupe = $info['troupe_nom'] ?? "l'unité";
    $ability_tid    = $info['ability_tid'];

    // 2. Niveau actuel du personnage (conditionne le déblocage via HeroLevel)
    $stmt_char_lvl = $pdo->prepare("SELECT niveau FROM progress_character WHERE id_player = ? AND id_character = ? LIMIT 1");
    $stmt_char_lvl->execute([$id_player, $id_character]);
    $character_niveau = (int)($stmt_char_lvl->fetchColumn() ?: 1);

    // 3. Niveau actuel de la capacité
    $stmt = $pdo->prepare("SELECT niveau, Debloque FROM progress_ability WHERE id_player = ? AND id_character = ? AND id_ability = ?");
    $stmt->execute([$id_player, $id_character, $id_ability]);
    $prog = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_ability_niveau = $prog ? (int)$prog['niveau'] : 1;

    // Capacité de héros #2/#3 pas encore débloquée (Debloque = 0, ou aucune ligne du tout) :
    // Debloque reste la source de vérité, mise à jour dans upgrade_character.php quand le
    // héros atteint le niveau requis — on ne se base pas ici sur une simple comparaison de
    // niveau recalculée à la volée (garde-fou serveur, en plus du bouton désactivé côté vue).
    if ($is_hero_ability && (!$prog || (int)$prog['Debloque'] === 0)) {
        echo json_encode([
            'success' => false,
            'message' => 'Cette capacité est encore verrouillée'
        ]);
        exit;
    }

    if ($action === 'downgrade') {
        // --- RÉTROGRADATION D'UNE CAPACITÉ (active/passive d'officier, ou capacité de héros) ---
        // Miroir de l'amélioration classique ci-dessous : on redescend simplement niveau - 1,
        // sans jamais reverrouiller (Debloque reste à 1 pour une capacité de héros déjà
        // débloquée — seul le NIVEAU redescend, même choix que downgrade_character.php).
        if ($current_ability_niveau <= 1) {
            echo json_encode([
                'success' => false,
                'message' => 'Cette capacité est déjà à son niveau minimum.'
            ]);
            exit;
        }

        // Garde-fou anti-course/anti-triche : le niveau en base doit correspondre à ce que
        // la card affichait au moment du clic.
        $client_current_level = isset($input['current_level']) ? (int)$input['current_level'] : null;
        if ($client_current_level !== null && $client_current_level !== $current_ability_niveau) {
            echo json_encode([
                'success' => false,
                'message' => 'Le niveau a changé entre-temps, recharge la page et réessaie.'
            ]);
            exit;
        }

        $new_level = $current_ability_niveau - 1;

        $pdo->prepare("UPDATE progress_ability SET niveau = ? WHERE id_player = ? AND id_character = ? AND id_ability = ?")
            ->execute([$new_level, $id_player, $id_character, $id_ability]);

        echo json_encode([
            'success' => true,
            'new_level' => $new_level,
            'talent_nom' => $nom_du_talent,
            'troupe_nom' => $nom_de_la_troupe,
            'message' => 'Capacité rétrogradée'
        ]);
        exit;
    }

    // 4. Palier suivant dans officer_abilities (même table pour héros et officiers)
    // Convention confirmée (voir muSumRemainingAbilityLevels dans functions.php) : la ligne
    // Niveau = N décrit le coût du PASSAGE N -> N+1, donc pour passer de current_ability_niveau
    // à new_level, il faut lire la ligne Niveau = current_ability_niveau (pas new_level : ça
    // pointait sur le palier suivant, d'où le décalage de coût et le niveau 7 bloqué à tort).
    $new_level = $current_ability_niveau + 1;

    $stmt_next = $pdo->prepare("
        SELECT Niveau, HeroLevel, UpgradeCost, UpgradeResource, UpgradeTimeH
        FROM officer_abilities
        WHERE TID = ? AND Niveau = ?
        LIMIT 1
    ");
    $stmt_next->execute([$ability_tid, $current_ability_niveau]);
    $next = $stmt_next->fetch(PDO::FETCH_ASSOC);

    if (!$next || (float)($next['UpgradeCost'] ?? 0) <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Capacité déjà au niveau maximum',
            'is_max' => true
        ]);
        exit;
    }

    $required_hero_level = (int)$next['HeroLevel'];
    if ($character_niveau < $required_hero_level) {
        echo json_encode([
            'success' => false,
            'message' => "Niveau {$required_hero_level} requis pour améliorer cette capacité"
        ]);
        exit;
    }

    // (Optionnel : c'est ici qu'il faudrait vérifier/débiter UpgradeCost en UpgradeResource...)

    // 5. Mise à jour de la progression
    // Garde-fou anti-course (même principe que la rétrogradation juste au-dessus) : le
    // UPDATE ne s'applique QUE si le niveau en base est encore celui qu'on a lu au début
    // ($current_ability_niveau). Sans ça, 2 requêtes quasi simultanées (double clic, double
    // gestionnaire côté client, etc.) lisent toutes les deux le même niveau de départ et
    // écrivent chacune +1 l'une après l'autre -> +2 pour 1 seul clic.
    if (!$prog) {
        $pdo->prepare("INSERT INTO progress_ability (id_player, id_character, id_ability, niveau) VALUES (?,?,?,?)")
            ->execute([$id_player, $id_character, $id_ability, $new_level]);
    } else {
        $stmt_upd = $pdo->prepare("UPDATE progress_ability SET niveau = ? WHERE id_player = ? AND id_character = ? AND id_ability = ? AND niveau = ?");
        $stmt_upd->execute([$new_level, $id_player, $id_character, $id_ability, $current_ability_niveau]);

        if ($stmt_upd->rowCount() === 0) {
            // Le niveau a changé entre-temps (requête concurrente déjà passée) : on ne
            // double-incrémente pas, on renvoie juste l'état actuel sans erreur bruyante.
            $stmt_recheck = $pdo->prepare("SELECT niveau FROM progress_ability WHERE id_player = ? AND id_character = ? AND id_ability = ?");
            $stmt_recheck->execute([$id_player, $id_character, $id_ability]);
            echo json_encode([
                'success' => false,
                'message' => 'Le niveau a changé entre-temps, recharge la page et réessaie.',
                'current_level' => (int)($stmt_recheck->fetchColumn() ?: $current_ability_niveau)
            ]);
            exit;
        }
    }

    // Y a-t-il encore un VRAI palier après celui qu'on vient d'atteindre ? La ligne
    // Niveau = new_level décrit le coût du passage new_level -> new_level+1.
    $stmt_check_max = $pdo->prepare("SELECT UpgradeCost FROM officer_abilities WHERE TID = ? AND Niveau = ? LIMIT 1");
    $stmt_check_max->execute([$ability_tid, $new_level]);
    $next_row = $stmt_check_max->fetch(PDO::FETCH_ASSOC);
    $is_max = (!$next_row) || ((float)($next_row['UpgradeCost'] ?? 0) <= 0);

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