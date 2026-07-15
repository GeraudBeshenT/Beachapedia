<?php
/**
 * mass_upgrade_helpers.php
 *
 * Toute la logique de lecture pour la fonctionnalité "Mass Upgrade" :
 * - déterminer la liste des bâtiments / instances disponibles à un QG donné
 *   (même logique que update_qg.php, basée sur townhall_levels)
 * - déterminer la liste des troupes / héros / officiers disponibles à un QG donné
 * - déterminer, pour chaque héros/officier, la liste de ses capacités (actives,
 *   passives, talents) avec leur niveau max
 * - fusionner tout ça avec la progression réelle du joueur (progress_building,
 *   progress_character, progress_ability) pour pré-remplir le formulaire
 *
 * Ce fichier ne fait AUCUNE écriture en base : lecture seule.
 */

/**
 * Niveau max de QG actuellement défini en base (utilisé pour peupler le <select>).
 */
function muGetMaxQG(PDO $pdo): int {
    $stmt = $pdo->query("SELECT MAX(Niveau) FROM buildings WHERE TID = 'TID_BUILDING_PALACE'");
    return (int)($stmt->fetchColumn() ?: 1);
}

/**
 * Construit la structure complète (bâtiments + troupes/héros/officiers + capacités)
 * pour un QG donné, fusionnée avec la progression réelle du joueur.
 *
 * @return array{buildings: array, characters: array}
 */
function muBuildData(PDO $pdo, string $id_player, int $qg): array {
    return [
        'buildings'   => muGetBuildingsForQG($pdo, $id_player, $qg),
        'characters'  => muGetCharactersForQG($pdo, $id_player, $qg),
        'engravings'  => muGetEngravings($pdo, $id_player),
    ];
}

/**
 * Bâtiments : on s'appuie sur townhall_levels (comme update_qg.php) pour savoir,
 * pour le QG choisi, combien d'instances de chaque bâtiment sont débloquées.
 * Chaque instance est ensuite fusionnée avec progress_building pour le niveau actuel.
 */
function muGetBuildingsForQG(PDO $pdo, string $id_player, int $qg): array {
    // 1. Ligne townhall_levels correspondant au QG choisi
    $stmt_th = $pdo->prepare("SELECT * FROM townhall_levels WHERE TownHallLevel = ?");
    $stmt_th->execute([$qg]);
    $qg_row = $stmt_th->fetch(PDO::FETCH_ASSOC);
    if (!$qg_row) return [];

    $colonnes_ignorees = [
        'TownHallLevel', 'XP', 'RequiredBuilding',
        'RequiredBuildingLevel', 'RequiredTroopLevel', 'MaterialSlots'
    ];

    // 2. buildingid complet (ID, Class, Ordre, nom FR) indexé par TID
    $stmt_bid = $pdo->prepare("
        SELECT bi.ID, bi.TID, bi.Class, bi.Ordre, t.FR AS nom
        FROM buildingid bi
        LEFT JOIN texts t ON t.TID = bi.TID
    ");
    $stmt_bid->execute();
    $buildingid_by_tid = [];
    foreach ($stmt_bid->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $buildingid_by_tid[$row['TID']] = $row;
    }

    // 3. Niveau max par TID pour CE QG (une seule requête groupée)
    $stmt_max = $pdo->prepare("SELECT TID, MAX(Niveau) AS maxlvl FROM buildings WHERE TownHallLevel <= ? GROUP BY TID");
    $stmt_max->execute([$qg]);
    $niveau_max_by_tid = [];
    foreach ($stmt_max->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $niveau_max_by_tid[$row['TID']] = (int)$row['maxlvl'];
    }

    // 4. Progression actuelle du joueur (niveau + Debloque) indexée par "id_building_id_instance"
    $stmt_prog = $pdo->prepare("SELECT id_building, id_instance, niveau, Debloque FROM progress_building WHERE id_player = ?");
    $stmt_prog->execute([$id_player]);
    $progress = [];
    foreach ($stmt_prog->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $progress[$row['id_building'] . '_' . $row['id_instance']] = $row;
    }

    // 5. Construction de la liste finale, groupée par catégorie
    $buildings = ['Ressource' => [], 'Defense' => [], 'Army' => [], 'Trap' => []];

    foreach ($qg_row as $colonne => $valeur) {
        if (in_array($colonne, $colonnes_ignorees, true)) continue;
        if (!isset($buildingid_by_tid[$colonne])) continue; // colonne sans équivalent bâtiment

        $nb_instances = (int)$valeur;
        if ($nb_instances <= 0) continue;

        $bid_info  = $buildingid_by_tid[$colonne];
        $category  = $bid_info['Class'] ?: 'Army';
        if (!isset($buildings[$category])) $buildings[$category] = [];

        $niveau_max = $niveau_max_by_tid[$colonne] ?? 1;

        $instances = [];
        for ($i = 1; $i <= $nb_instances; $i++) {
            $prog = $progress[$bid_info['ID'] . '_' . $i] ?? null;
            $instances[] = [
                'id_instance' => $i,
                'niveau'      => $prog ? (int)$prog['niveau'] : 0,
                'Debloque'    => $prog ? (int)$prog['Debloque'] : 0,
            ];
        }

        $buildings[$category][] = [
            'id_building' => (int)$bid_info['ID'],
            'TID'         => $colonne,
            'nom'         => $bid_info['nom'] ?: $colonne,
            'niveau_max'  => $niveau_max,
            'Ordre'       => (int)$bid_info['Ordre'],
            'instances'   => $instances,
        ];
    }

    foreach ($buildings as &$list) {
        usort($list, fn($a, $b) => $a['Ordre'] <=> $b['Ordre']);
    }
    unset($list);

    return $buildings;
}

/**
 * Troupes / Héros / Officiers disponibles au QG choisi (ci.HQUnlock <= qg),
 * fusionnés avec progress_character, et pour chaque héros/officier, la liste
 * de leurs capacités (actives / passives / talents) fusionnée avec progress_ability.
 */
function muGetCharactersForQG(PDO $pdo, string $id_player, int $qg): array {
    // Modifie la requête pour inclure 'Spell'
    $stmt = $pdo->prepare("
        SELECT ci.id AS id_character, ci.TID, ci.Class, ci.Officer, ci.HQUnlock, ci.Type, ci.Display, t.FR AS nom
        FROM characterid ci
        LEFT JOIN texts t ON t.TID = ci.TID
        WHERE ci.HQUnlock <= ?
          AND ci.Class IN ('Troupe', 'Proto', 'Officier', 'Hero', 'Spell')
        ORDER BY
          CASE ci.Class
            WHEN 'Hero' THEN 1
            WHEN 'Officier' THEN 2
            WHEN 'Troupe' THEN 3
            WHEN 'Proto' THEN 4
            WHEN 'Spell' THEN 5
            ELSE 6
          END,
          ci.Type ASC, ci.Display ASC
    ");
    $stmt->execute([$qg]);
    $chars = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$chars) return [];

    // 2. Niveau max par TID (table characters)
    $stmt_max = $pdo->query("SELECT TID, MAX(Niveau) AS maxlvl FROM characters GROUP BY TID");
    $niveau_max_by_tid = [];
    foreach ($stmt_max->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $niveau_max_by_tid[$row['TID']] = (int)$row['maxlvl'];
    }

    // 3. Progression actuelle des personnages
    $stmt_prog = $pdo->prepare("SELECT id_character, niveau, Debloque FROM progress_character WHERE id_player = ?");
    $stmt_prog->execute([$id_player]);
    $char_progress = [];
    foreach ($stmt_prog->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $char_progress[(int)$row['id_character']] = $row;
    }

    // 4. Toutes les capacités (abilitieid + nom texts), indexées par TID
    $stmt_ab = $pdo->query("
        SELECT ai.id, ai.TID, ai.hero, ai.Type, t.FR AS nom
        FROM abilitieid ai
        LEFT JOIN texts t ON t.TID = ai.TID
    ");
    $abilities_by_tid = [];
    foreach ($stmt_ab->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $abilities_by_tid[$row['TID']] = $row;
    }

    // 5. Niveau max par capacité (officer_abilities), indexé par TID
    $stmt_ab_max = $pdo->query("SELECT TID, MAX(Niveau) AS maxlvl FROM officer_abilities GROUP BY TID");
    $ability_max_by_tid = [];
    foreach ($stmt_ab_max->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ability_max_by_tid[$row['TID']] = (int)$row['maxlvl'];
    }

    // 6. officer_talents, indexé par TID de personnage
    $stmt_ot = $pdo->query("SELECT * FROM officer_talents");
    $talents_by_char_tid = [];
    foreach ($stmt_ot->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $talents_by_char_tid[$row['TID']] = $row;
    }

    // 7. Progression actuelle des capacités : "id_character-id_ability" => niveau
    $stmt_prog_ab = $pdo->prepare("SELECT id_character, id_ability, niveau FROM progress_ability WHERE id_player = ?");
    $stmt_prog_ab->execute([$id_player]);
    $ability_progress = [];
    foreach ($stmt_prog_ab->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ability_progress[$row['id_character'] . '-' . $row['id_ability']] = (int)$row['niveau'];
    }

    // 8. Construction finale
    $result = ['Troupe' => [], 'Hero' => [], 'Officier' => []];

    foreach ($chars as $c) {
        $id_character = (int)$c['id_character'];
        $prog = $char_progress[$id_character] ?? null;

        $entry = [
            'id_character' => $id_character,
            'TID'          => $c['TID'],
            'nom'          => $c['nom'] ?: $c['TID'],
            'niveau'       => $prog ? (int)$prog['niveau'] : 0,
            'niveau_max'   => $niveau_max_by_tid[$c['TID']] ?? 1,
            'Debloque'     => $prog ? (int)$prog['Debloque'] : 0,
            'abilities'    => [],
        ];

        // --- Héros : capacités actives via abilitieid.hero = TID du héros ---
        if ($c['Class'] === 'Hero') {
            foreach ($abilities_by_tid as $ab_tid => $ab) {
                if (($ab['hero'] ?? null) !== $c['TID']) continue;
                $key = $id_character . '-' . $ab['id'];
                $entry['abilities'][] = [
                    'id_ability' => (int)$ab['id'],
                    'TID'        => $ab_tid,
                    'nom'        => $ab['nom'] ?: $ab_tid,
                    'kind'       => 'Capacité de héros',
                    'niveau'     => $ability_progress[$key] ?? 0,
                    'niveau_max' => $ability_max_by_tid[$ab_tid] ?? 7,
                ];
            }
        }

        // --- Officiers : Active / Passive / 5 talents via officer_talents ---
        if ($c['Class'] === 'Officier' && isset($talents_by_char_tid[$c['TID']])) {
            $ot = $talents_by_char_tid[$c['TID']];
            $slots = [
                'ActiveAbility'  => 'Capacité active',
                'PassiveAbility' => 'Capacité passive',
                'TalentTID1'     => 'Talent 1',
                'TalentTID2'     => 'Talent 2',
                'TalentTID3'     => 'Talent 3',
                'TalentTID4'     => 'Talent 4',
                'TalentTID5'     => 'Talent 5',
            ];
            foreach ($slots as $col => $label) {
                $ab_tid = trim($ot[$col] ?? '');
                if ($ab_tid === '' || !isset($abilities_by_tid[$ab_tid])) continue;
                $ab = $abilities_by_tid[$ab_tid];
                $key = $id_character . '-' . $ab['id'];
                $entry['abilities'][] = [
                    'id_ability' => (int)$ab['id'],
                    'TID'        => $ab_tid,
                    'nom'        => $ab['nom'] ?: $ab_tid,
                    'kind'       => $label,
                    'niveau'     => $ability_progress[$key] ?? 0,
                    'niveau_max' => $ability_max_by_tid[$ab_tid] ?? 16,
                ];
            }
        }

        $category = $c['Class'];
        if (!isset($result[$category])) $result[$category] = [];
        $result[$category][] = $entry;
    }

    return $result;
}

/**
 * Gravures (Offensives / Défensives), fusionnées avec la progression réelle du joueur.
 * Même logique / mêmes tables que la section 12 de queries.php (engravingid, engravings,
 * progress_engraving), mais sans filtrage par QG : les gravures ne sont pas gatées par le
 * QG comme les bâtiments/troupes, seulement par le niveau du Graveur (géré côté affichage
 * dashboard via $tab_gravures_unlocked, pas pertinent pour Mass Upgrade qui liste tout).
 *
 * @return array{Offensive: array, Defensive: array}
 */
function muGetEngravings(PDO $pdo, string $id_player): array {
    // 1. Progression actuelle, indexée par id_engraving numérique
    $engravings_progress = [];
    try {
        $stmt_prog = $pdo->prepare("SELECT id_engraving, niveau FROM progress_engraving WHERE id_player = ?");
        $stmt_prog->execute([$id_player]);
        foreach ($stmt_prog->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $engravings_progress[(int)$row['id_engraving']] = (int)$row['niveau'];
        }
    } catch (PDOException $e) {
        error_log("Erreur muGetEngravings (progress_engraving) : " . $e->getMessage());
    }

    // 2. Liste des gravures + niveau max (MAX(Quality) par TID)
    $result = ['Offensive' => [], 'Defensive' => []];
    try {
        $stmt_eng = $pdo->query("
            SELECT ei.id AS id_engraving, e.TID, ei.Category, ei.Type, ei.IconExportName,
                   IFNULL(t.FR, e.TID) AS nom, MAX(e.Quality) AS niveau_max
            FROM engravings e
            JOIN engravingid ei ON e.TID = ei.TID
            LEFT JOIN texts t ON e.TID = t.TID
            GROUP BY ei.id, e.TID, ei.Category, ei.Type, ei.IconExportName, t.FR
            ORDER BY ei.Type ASC, e.TID ASC
        ");
        foreach ($stmt_eng->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id_engraving = (int)$row['id_engraving'];
            $cat = $row['Category'];
            if (!isset($result[$cat])) continue; // catégorie inconnue, on ignore

            $result[$cat][] = [
                'id_engraving' => $id_engraving,
                'TID'          => $row['TID'],
                'nom'          => $row['nom'] ?: $row['TID'],
                'IconExportName' => $row['IconExportName'],
                'Type'         => $row['Type'],
                'niveau'       => $engravings_progress[$id_engraving] ?? 0,
                'niveau_max'   => (int)$row['niveau_max'],
            ];
        }
    } catch (PDOException $e) {
        error_log("Erreur muGetEngravings (liste) : " . $e->getMessage());
    }

    return $result;
}

/**
 * Recalcule l'expérience totale du joueur à partir de l'état final de sa
 * progression (bâtiments + personnages), après un Mass Upgrade.
 * On additionne :
 *  - pour chaque instance de bâtiment construite : la somme de buildings.XpGain
 *    pour tous les niveaux de 1 jusqu'à son niveau actuel
 *  - pour chaque personnage débloqué : la somme de characters.XpGain pour tous
 *    les niveaux de 1 jusqu'à (niveau actuel - 1) [même convention que
 *    upgrade_character.php, où XpGain(N) récompense le passage N -> N+1]
 */
function muRecomputeExperience(PDO $pdo, string $id_player): int {
    $total = 0;

    // Bâtiments
    $stmt_b = $pdo->prepare("
        SELECT pb.id_building, pb.niveau, bi.TID
        FROM progress_building pb
        JOIN buildingid bi ON bi.ID = pb.id_building
        WHERE pb.id_player = ? AND pb.niveau > 0
    ");
    $stmt_b->execute([$id_player]);
    $rows_b = $stmt_b->fetchAll(PDO::FETCH_ASSOC);

    if ($rows_b) {
        // Cache des sommes cumulées XpGain par TID/niveau pour éviter les requêtes répétées
        $cache = [];
        foreach ($rows_b as $row) {
            $tid = $row['TID'];
            $niv = (int)$row['niveau'];
            if (!isset($cache[$tid])) $cache[$tid] = [];
            if (!isset($cache[$tid][$niv])) {
                $stmt_sum = $pdo->prepare("SELECT SUM(XpGain) FROM buildings WHERE TID = ? AND Niveau <= ?");
                $stmt_sum->execute([$tid, $niv]);
                $cache[$tid][$niv] = (int)($stmt_sum->fetchColumn() ?: 0);
            }
            $total += $cache[$tid][$niv];
        }
    }

    // Personnages
    $stmt_c = $pdo->prepare("
        SELECT pc.id_character, pc.niveau, ci.TID
        FROM progress_character pc
        JOIN characterid ci ON ci.id = pc.id_character
        WHERE pc.id_player = ? AND pc.niveau > 1
    ");
    $stmt_c->execute([$id_player]);
    $rows_c = $stmt_c->fetchAll(PDO::FETCH_ASSOC);

    if ($rows_c) {
        $cache = [];
        foreach ($rows_c as $row) {
            $tid = $row['TID'];
            $niv = (int)$row['niveau'];
            if (!isset($cache[$tid])) $cache[$tid] = [];
            if (!isset($cache[$tid][$niv])) {
                // XpGain(N) récompense le passage N -> N+1, donc on somme de 1 à (niveau-1)
                $stmt_sum = $pdo->prepare("SELECT SUM(XpGain) FROM characters WHERE TID = ? AND Niveau <= ?");
                $stmt_sum->execute([$tid, $niv - 1]);
                $cache[$tid][$niv] = (int)($stmt_sum->fetchColumn() ?: 0);
            }
            $total += $cache[$tid][$niv];
        }
    }

    return $total;
}