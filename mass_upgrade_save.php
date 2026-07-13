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
        // Pré-chargement pour la synchro Troupe -> Officiers liés (même logique que le
        // "2bis" de upgrade_character.php) : characterid.Officer (sur la ligne de l'OFFICIER)
        // contient le TID de la troupe dont il dépend. On a besoin :
        //  - du TID de chaque id_character envoyé (pour savoir si c'est une troupe qui a des
        //    officiers rattachés),
        //  - de la liste des officiers rattachés à chaque TID de troupe.
        $char_ids = array_values(array_unique(array_filter(
            array_map(fn($r) => (int)($r['id_character'] ?? 0), $characters),
            fn($v) => $v > 0
        )));
        $troop_tid_by_id = [];
        if ($char_ids) {
            $ph = implode(',', array_fill(0, count($char_ids), '?'));
            $stmt_tid = $pdo->prepare("SELECT ID, TID FROM characterid WHERE ID IN ($ph)");
            $stmt_tid->execute($char_ids);
            foreach ($stmt_tid->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $troop_tid_by_id[(int)$row['ID']] = $row['TID'];
            }
        }
        $officers_by_troop_tid = [];
        $stmt_officers = $pdo->query("SELECT ID, Officer FROM characterid WHERE Officer IS NOT NULL AND Officer <> ''");
        foreach ($stmt_officers->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $officers_by_troop_tid[$row['Officer']][] = (int)$row['ID'];
        }

        $stmt_c = $pdo->prepare("
            INSERT INTO progress_character (id_player, id_character, niveau, Debloque)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE niveau = VALUES(niveau), Debloque = VALUES(Debloque)
        ");
        // Synchro des officiers : niveau aligné sur la troupe, MAIS Debloque jamais touché
        // (ni forcé à 1, ni à 0) — le déblocage d'un chef reste un choix explicite du joueur,
        // que ce soit via l'onglet Chefs ou via une capacité (voir 4bis plus bas).
        $stmt_ofc = $pdo->prepare("
            INSERT INTO progress_character (id_player, id_character, niveau, Debloque)
            VALUES (?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE niveau = VALUES(niveau)
        ");

        foreach ($characters as $row) {
            $id_character = (int)($row['id_character'] ?? 0);
            $niveau       = max(0, (int)($row['niveau'] ?? 0));
            if ($id_character <= 0) continue;

            $debloque = $niveau > 0 ? 1 : 0;
            $stmt_c->execute([$id_player, $id_character, $niveau, $debloque]);
            $nb_characters++;

            $troop_tid = $troop_tid_by_id[$id_character] ?? null;
            if ($troop_tid && !empty($officers_by_troop_tid[$troop_tid])) {
                foreach ($officers_by_troop_tid[$troop_tid] as $officer_id) {
                    $stmt_ofc->execute([$id_player, $officer_id, $niveau]);
                    $nb_characters++;
                }
            }
        }
    }

    // 4. Capacités (héros + officiers) : upsert sur (id_player, id_character, id_ability)
    $nb_abilities = 0;
    $characters_to_unlock = []; // id_character => true, dès qu'une capacité passe à Debloque=1
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

            if ($debloque === 1) {
                $characters_to_unlock[$id_character] = true;
            }
        }
    }

    // 4bis. Si au moins une capacité (active/passive/talent) d'un personnage a été débloquée
    // via Mass Upgrade, on force aussi le déblocage du PERSONNAGE lui-même
    // (progress_character.Debloque = 1). Indispensable pour les officiers : ils n'ont pas de
    // champ "niveau" propre dans le formulaire Mass Upgrade (voir mass_upgrade_render.php),
    // donc rien d'autre ne vient jamais mettre à jour progress_character pour eux — sans ce
    // correctif, on pouvait monter à fond leurs capacités tout en laissant le bouton
    // "Débloquer le chef" affiché côté dashboard, incohérent avec ce qui venait d'être
    // enregistré. GREATEST(niveau, 1) préserve un niveau déjà renseigné (ex. pour un héros,
    // dont le niveau est envoyé séparément dans $characters juste au-dessus).
    if (!empty($characters_to_unlock)) {
        $stmt_cu = $pdo->prepare("
            INSERT INTO progress_character (id_player, id_character, niveau, Debloque)
            VALUES (?, ?, 1, 1)
            ON DUPLICATE KEY UPDATE Debloque = 1, niveau = GREATEST(niveau, 1)
        ");
        foreach (array_keys($characters_to_unlock) as $id_character) {
            $stmt_cu->execute([$id_player, $id_character]);
            $nb_characters++;
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