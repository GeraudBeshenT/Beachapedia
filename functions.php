<?php

/**
 * Rendu du Tableau de Bord (Dashboard) avec les 4 graphiques en anneau complet (360°)
 */
function renderDashboard($dashboard_categories, $dashboard_buildings) {
    echo "
    <div class='dashboard-container'>
        <h2 style='font-family: \"Bangers\", cursive; font-size: 2.3em; color: #ecf0f1; margin-top: 0; margin-bottom: 25px; letter-spacing: 1px;'>Tableau de Bord</h2>
        
        <h3 style='font-family: \"Bangers\", cursive; font-size: 1.8em; color: #1abc9c; margin-top: 10px; margin-bottom: 15px; letter-spacing: 0.5px;'>Progression de l'Armée</h3>
        <div class='dashboard-widgets'>";

    foreach ($dashboard_categories as $class_key => $cat) {
        $pourcentage = $cat['pourcentage'];
        $actuel = $cat['actuel'];
        $maximum = $cat['max'];
        $label = $cat['label'];

        echo "
            <div class='widget-card'>
                <h3 class='widget-title'>{$label}</h3>
                <div class='gauge-circle-container'>
                    <div class='gauge-circle-bar' style='--value: {$pourcentage};'>
                        <div class='gauge-circle-inner'>
                            <span class='gauge-percentage-text'>{$pourcentage}%</span>
                        </div>
                    </div>
                </div>
                <p class='gauge-legend'>{$actuel} / {$maximum} Niveaux</p>
            </div>";
    }

    echo "
        </div>

        <h3 style='font-family: \"Bangers\", cursive; font-size: 1.8em; color: #e67e22; margin-top: 40px; margin-bottom: 15px; letter-spacing: 0.5px;'>Progression de la Base</h3>
        <div class='dashboard-widgets'>";

    foreach ($dashboard_buildings as $class_key => $build) {
        $pourcentage = $build['pourcentage'];
        $actuel = $build['actuel'];
        $maximum = $build['max'];
        $label = $build['label'];

        echo "
            <div class='widget-card'>
                <h3 class='widget-title'>{$label}</h3>
                <div class='gauge-circle-container'>
                    <div class='gauge-circle-bar' style='--value: {$pourcentage};' data-type='building'>
                        <div class='gauge-circle-inner'>
                            <span class='gauge-percentage-text'>{$pourcentage}%</span>
                        </div>
                    </div>
                </div>
                <p class='gauge-legend'>{$actuel} / {$maximum} Niveaux</p>
            </div>";
    }

    echo "
        </div>
    </div>
    ";
}

// --- Dans functions.php ---
/**
 * Affiche la liste des bâtiments par catégorie.
 * Inclut un sélecteur de niveau et un bouton d'action pour la mise à jour.
 */
/**
 * Bouton "Masquer les X au max" réutilisable sur toutes les grilles (bâtiments,
 * troupes/héros/proto/canonnière, tribus, gravures). Le JS (toggleHideMaxed)
 * ajoute/retire la classe 'hide-maxed' sur le conteneur .tab-content parent,
 * et le CSS masque tout élément portant data-maxed="1" à l'intérieur.
 * L'état est mémorisé par onglet dans localStorage pour survivre au rechargement.
 */
function renderHideMaxedToggle($label = 'les éléments') {
    echo "
    <button type='button' class='btn-hide-maxed' data-label='" . htmlspecialchars($label) . "'>
        <span class='hide-maxed-icon'>👁️</span>
        <span class='hide-maxed-label'>Masquer " . htmlspecialchars($label) . " au max</span>
    </button>";
}

function renderBuildingsTable($buildings_list) {
    renderHideMaxedToggle('les bâtiments');

    foreach ($buildings_list as $category => $buildings) {
        if (empty($buildings)) continue;

        echo "<div class='buildings-grid hide-maxed-container'>";

        foreach ($buildings as $b) {
            // Sécurisation avec ?? pour éviter les Warnings
            $tid      = $b['TID'] ?? '';
            $nom      = htmlspecialchars($b['nom_building'] ?? '???');
            $inst     = (int)($b['id_instance'] ?? 1);
            $niv      = (int)($b['niveau_actuel'] ?? 0);
            $max      = (int)($b['niveau_max'] ?? 1);
            $img      = $b['ExportName'] ?? 'default-building';
            $debloque = (int)($b['Debloque'] ?? 0);
            $is_maxed = ($niv >= $max);
            $maxed_attr = $is_maxed ? "1" : "0";

            // Mines (Mine / Super mine / Électromine) : plus de construction à l'or par
            // instance, on recherche un niveau unique à l'Arsenal qui s'applique à toutes
            // les mines posées. Voir MINE_TIDS / getBuildingsDisplay dans queries.php.
            $is_mine          = !empty($b['is_mine']);
            $required_arsenal = (int)($b['required_arsenal'] ?? 0);
            $arsenal_ok       = $b['arsenal_ok'] ?? true;

            // L'instance reste purement interne (data-instance), jamais affichée à l'écran
            $safe_id = "bld-" . preg_replace('/[^a-zA-Z0-9]/', '', $tid) . "-{$inst}";

            // Libellé du bouton : Construire si jamais débloqué, sinon Améliorer
            if ($is_maxed) {
                $btn_text = "Max !";
            } elseif ($is_mine && !$arsenal_ok) {
                $btn_text = "Verrouillé";
            } elseif ($debloque === 0) {
                $btn_text = "Construire";
            } else {
                $btn_text = "Améliorer";
            }

            $display_text = ($niv === 0) ? "Non construit" : "Niveau {$niv}";

            echo "
            <div class='building-card' id='card-{$safe_id}' data-tid='{$tid}' data-instance='{$inst}' data-maxed='{$maxed_attr}'>
                <div class='building-card-visual'>
                    <img class='building-card-img' src='images/{$img}.webp' alt='{$nom}' onerror=\"this.src='images/default-building.png'\">
                </div>

                <div class='building-card-info'>
                    <span class='building-card-name'>{$nom}</span>
                    <span class='building-card-level' id='lvl-{$safe_id}'>{$display_text} / {$max}</span>
                </div>";

            if (!$is_maxed) {
                $is_trap = (($b['Class'] ?? '') === 'Trap');
                $temps_j     = (int)($b['BuildTimeD'] ?? 0);
                $temps_h     = (int)($b['BuildTimeH'] ?? 0);
                $temps_m     = (int)($b['BuildTimeM'] ?? 0);
                $temps_txt   = trim(($temps_j > 0 ? "{$temps_j}j " : "") . ($temps_h > 0 ? "{$temps_h}h " : "") . ($temps_m > 0 ? "{$temps_m}m" : ""));
                if ($temps_txt === '') $temps_txt = "3 sec";

                if ($is_trap) {
                    // Les Pièges se paient uniquement en Or (amélioration d'Arsenal)
                    $cout_or = $b['BuildCostGold'] ?? 0;
                    echo "
                <div class='building-card-costs'>
                    <span class='building-cost-item'><img class='building-cost-icon' src='images/icons/Gold.png' alt='Or'>{$cout_or}</span>
                </div>
                <div class='building-card-time'>
                    <img class='building-time-icon' src='images/icons/Time Icon.png' alt='Temps'>{$temps_txt}
                </div>";

                    if ($is_mine && !$arsenal_ok) {
                        echo "
                <div class='building-card-requirement' style='color:#e74c3c; font-size:0.85em; font-weight:600; margin-top:4px; text-align:center;'>
                    Arsenal niveau {$required_arsenal} requis
                </div>";
                    }
                } else {
                    $cout_bois   = $b['BuildCostWood']  ?? 0;
                    $cout_pierre = $b['BuildCostStone'] ?? 0;
                    $cout_fer    = $b['BuildCostIron']  ?? 0;

                    echo "
                <div class='building-card-costs'>
                    <span class='building-cost-item'><img class='building-cost-icon' src='images/icons/Wood.png' alt='Bois'>{$cout_bois}</span>
                    <span class='building-cost-item'><img class='building-cost-icon' src='images/icons/Stone.png' alt='Pierre'>{$cout_pierre}</span>
                    <span class='building-cost-item'><img class='building-cost-icon' src='images/icons/Iron.png' alt='Fer'>{$cout_fer}</span>
                </div>
                <div class='building-card-time'>
                    <img class='building-time-icon' src='images/icons/Time Icon.png' alt='Temps'>{$temps_txt}
                </div>";
                }
            }

            $disabled = ($is_maxed || ($is_mine && !$arsenal_ok)) ? "disabled" : "";
            echo "
                <div class='building-card-action'>
                    <button class='btn-upgrade' {$disabled}
                            onclick=\"triggerUpgradeBuilding('{$tid}', {$inst}, {$niv}, {$max}, '{$safe_id}')\">
                        <span class='btn-text'>{$btn_text}</span>
                    </button>
                </div>
            </div>";
        }

        echo "</div>";
    }
}

function calculateCategoryProgress($buildings) {
    $total_current = 0;
    $total_max = 0;

    foreach ($buildings as $b) {
        $total_current += $b['niveau_actuel'];
        $total_max += $b['niveau_max']; // Le niveau_max est déjà défini par instance
    }

    $percent = ($total_max > 0) ? round(($total_current / $total_max) * 100, 1) : 0;

    return [
        'current' => $total_current,
        'max'     => $total_max,
        'percent' => $percent
    ];
}

function getCategoryStats($buildings) {
    $total_current = 0;
    $total_max = 0;
    $total_max_absolu = 0;
    $restant_a_construire = 0;

    foreach ($buildings as $b) {
        $total_current += $b['niveau_actuel'];
        $total_max += $b['niveau_max'];
        // niveau_max_absolu n'existe que pour les bâtiments (getBuildingsDisplay) ; on retombe
        // sur niveau_max pour les autres appelants de cette fonction (ex. gravures) qui n'ont
        // pas cette notion de "max du max" indépendant du QG.
        $total_max_absolu += $b['niveau_max_absolu'] ?? $b['niveau_max'];
        if ((int)$b['niveau_actuel'] === 0) $restant_a_construire++;
    }

    $percent = ($total_max > 0) ? round(($total_current / $total_max) * 100, 1) : 0;
    $percent_absolu = ($total_max_absolu > 0) ? round(($total_current / $total_max_absolu) * 100, 1) : 0;

    return [
        'current' => $total_current,
        'max'     => $total_max,
        'percent' => $percent,
        'max_absolu'     => $total_max_absolu,
        'percent_absolu' => $percent_absolu,
        'restant_a_construire' => $restant_a_construire,
    ];
}

/**
 * Équivalent de getCategoryStats() mais pour les unités (Troupes, Proto-troupes, Héros),
 * qui utilisent les champs niveau_joueur / niveau_autorise au lieu de niveau_actuel / niveau_max.
 */
function getUnitsStats($units_list) {
    $total_current = 0;
    $total_max = 0;

    foreach ($units_list as $u) {
        $total_current += (int)($u['niveau_joueur'] ?? 0);
        $total_max     += (int)($u['niveau_autorise'] ?? 0);
    }

    $percent = ($total_max > 0) ? round(($total_current / $total_max) * 100, 1) : 0;

    return [
        'current' => $total_current,
        'max'     => $total_max,
        'percent' => $percent
    ];
}

/**
 * Stats agrégées des capacités (passive + active) de tous les Chefs de bataillon,
 * SANS compter les talents (volontairement exclus, voir demande produit).
 * Reprend la même logique que renderLeadersStatsSidebar() mais retourne des totaux
 * exploitables pour une carte de dashboard au lieu d'un tableau HTML détaillé.
 */
function getOfficersCapaciteStats($pdo, $id_player, $officers_list) {
    if (empty($officers_list)) {
        return ['current' => 0, 'max' => 0, 'percent' => 0, 'debloques' => 0, 'total' => 0];
    }

    $stmt_prog = $pdo->prepare("SELECT id_character, id_ability, niveau FROM progress_ability WHERE id_player = ?");
    $stmt_prog->execute([$id_player]);
    $abilities_progress = [];
    while ($row = $stmt_prog->fetch(PDO::FETCH_ASSOC)) {
        $abilities_progress[(int)$row['id_character']][(int)$row['id_ability']] = (int)$row['niveau'];
    }

    $current = 0;
    $max = 0;
    $debloques = 0;

    foreach ($officers_list as $u) {
        if ((int)($u['Debloque'] ?? 0) === 1) $debloques++;

        $char_id     = (int)$u['id_character'];
        $passive_lvl = null;
        $active_lvl  = null;

        if (!empty($u['abilities'])) {
            if (isset($u['abilities']['passive'])) {
                $aid = $u['abilities']['passive']['id_ability'];
                $passive_lvl = $abilities_progress[$char_id][$aid] ?? null;
            }
            if (isset($u['abilities']['active'])) {
                $aid = $u['abilities']['active']['id_ability'];
                $active_lvl = $abilities_progress[$char_id][$aid] ?? null;
            }
        }

        // -1 car le niveau 1 correspond à "pas encore amélioré" (cohérent avec renderLeadersStatsSidebar)
        $current += ($passive_lvl !== null) ? ($passive_lvl - 1) : 0;
        $current += ($active_lvl  !== null) ? ($active_lvl - 1)  : 0;

        // Plafond réel de chaque capacité (gère les paliers "terminateurs" à UpgradeCost = 0,
        // voir getAbilityRealMaxLevel) ; -1 pour la même raison que $current ci-dessus.
        $passive_max_level = !empty($u['abilities']['passive']['levels']) ? getAbilityRealMaxLevel($u['abilities']['passive']['levels']) : 15;
        $active_max_level  = !empty($u['abilities']['active']['levels'])  ? getAbilityRealMaxLevel($u['abilities']['active']['levels'])  : 15;
        $max += ($passive_max_level - 1) + ($active_max_level - 1); // talents volontairement exclus
    }

    $percent = ($max > 0) ? round(($current / $max) * 100, 1) : 0;

    return [
        'current'   => $current,
        'max'       => $max,
        'percent'   => $percent,
        'debloques' => $debloques,
        'total'     => count($officers_list),
    ];
}

/**
 * ==========================================================================
 * RÉSUMÉ "RESSOURCES POUR TOUT FINIR AU MAX" (carte du Tableau de Bord)
 * ==========================================================================
 * Deux tableaux : Bâtiments (Or/Bois/Pierre/Fer/Temps) et Armée
 * (Or/Jetons Proto/Manuels de terrain/Temps), 1 ligne par catégorie + Total.
 * Réutilise les totaux "remaining_*" déjà calculés dans queries.php pour
 * chaque instance de bâtiment / chaque troupe-proto-héros-officier-capacité,
 * donc même périmètre que le reste du dashboard (jusqu'au niveau max
 * ATTEIGNABLE au QG actuel, pas le max absolu de fin de jeu).
 */

/**
 * Bâtiments : agrège $buildings_display (issu de getBuildingsDisplay()) catégorie par
 * catégorie, à partir des champs remaining_gold/wood/stone/iron/time_seconds déjà
 * présents sur chaque instance.
 */
function getBuildingsResourceSummary($buildings_display) {
    $categories = [
        'Ressource' => 'Bâtiments Économiques',
        'Defense'   => 'Bâtiments Défensifs',
        'Army'      => 'Bâtiments de Renfort',
        'Trap'      => 'Pièges',
    ];

    $resume = [];
    $total = ['gold' => 0, 'wood' => 0, 'stone' => 0, 'iron' => 0, 'time_seconds' => 0];

    foreach ($categories as $cat => $label) {
        $s = ['gold' => 0, 'wood' => 0, 'stone' => 0, 'iron' => 0, 'time_seconds' => 0];
        foreach (($buildings_display[$cat] ?? []) as $b) {
            $s['gold']         += (int)($b['remaining_gold']         ?? 0);
            $s['wood']         += (int)($b['remaining_wood']         ?? 0);
            $s['stone']        += (int)($b['remaining_stone']        ?? 0);
            $s['iron']         += (int)($b['remaining_iron']         ?? 0);
            $s['time_seconds'] += (int)($b['remaining_time_seconds'] ?? 0);
        }
        foreach ($s as $k => $v) $total[$k] += $v;
        $resume[$cat] = array_merge(['label' => $label], $s);
    }

    $resume['TOTAL'] = array_merge(['label' => 'Total'], $total);
    return $resume;
}

/**
 * Petit total (coût + temps en heures) sur une liste homogène issue de getFilteredUnits()
 * (Troupes, Proto-troupes, Héros ou Capacités de canonnière), à partir des champs
 * remaining_cost / remaining_time_h déjà calculés.
 */
function muSumRemainingBaseList($list) {
    $cost = 0;
    $time_h = 0.0;
    foreach ($list as $u) {
        $cost   += (int)($u['remaining_cost']    ?? 0);
        $time_h += (float)($u['remaining_time_h'] ?? 0);
    }
    return ['cost' => $cost, 'time_h' => $time_h];
}

/**
 * Coût restant (jusqu'au vrai plafond achetable, voir getAbilityRealMaxLevel) d'UNE
 * capacité (active/passive de héros ou d'officier), à partir de son tableau 'levels'
 * (Niveau, UpgradeCost, UpgradeTimeH — convention : la ligne Niveau=N décrit le coût
 * du passage N -> N+1, donc on somme de current_level à real_max exclu).
 */
function muSumRemainingAbilityLevels($levels, $current_level) {
    if (empty($levels)) return ['cost' => 0, 'time_h' => 0.0];

    $real_max = getAbilityRealMaxLevel($levels);
    $cost = 0;
    $time_h = 0.0;
    foreach ($levels as $row) {
        $niveau = (int)$row['Niveau'];
        if ($niveau >= (int)$current_level && $niveau < $real_max) {
            $cost   += (float)($row['UpgradeCost']   ?? 0);
            $time_h += (float)($row['UpgradeTimeH']  ?? 0);
        }
    }
    return ['cost' => $cost, 'time_h' => $time_h];
}

/**
 * Même principe que muSumRemainingAbilityLevels, mais en séparant le coût restant en
 * 2 paliers : les capacités d'officier niveau 1 à 10 se paient en Manuels de terrain,
 * les niveaux 11 à 13 se paient en Rapports d'activité. La ligne 'Niveau = N' décrit le
 * coût du passage N -> N+1 : donc N < $tier_threshold reste dans le palier "manuel"
 * (jusqu'à N=9 inclus pour atteindre le niveau 10), et N >= $tier_threshold bascule sur
 * le palier "rapport" (dès la transition 10 -> 11).
 */
function muSumRemainingAbilityLevelsSplit($levels, $current_level, $tier_threshold = 10) {
    $empty = ['manuel' => ['cost' => 0, 'time_h' => 0.0], 'rapport' => ['cost' => 0, 'time_h' => 0.0]];
    if (empty($levels)) return $empty;

    $real_max = getAbilityRealMaxLevel($levels);
    $result = $empty;
    foreach ($levels as $row) {
        $niveau = (int)$row['Niveau'];
        if ($niveau >= (int)$current_level && $niveau < $real_max) {
            $tier   = ($niveau < $tier_threshold) ? 'manuel' : 'rapport';
            $result[$tier]['cost']   += (float)($row['UpgradeCost']  ?? 0);
            $result[$tier]['time_h'] += (float)($row['UpgradeTimeH'] ?? 0);
        }
    }
    return $result;
}

/**
 * Coût restant des TALENTS (Talent 1 à 5) des Chefs de bataillon passés en paramètre :
 * pour chaque talent pas encore débloqué, son coût de déblocage (1ère et unique ligne
 * officer_abilities pour ce TID). Nécessite quelques requêtes dédiées (pas de données
 * pré-chargées côté getFilteredUnits pour les talents), regroupées au maximum.
 */
function getOfficersTalentsRemainingCost(PDO $pdo, $id_player, array $officer_ids) {
    $result = ['cost' => 0, 'time_h' => 0.0];
    $officer_ids = array_values(array_unique(array_filter(array_map('intval', $officer_ids))));
    if (empty($officer_ids)) return $result;

    // TID de chaque officier
    $ph = implode(',', array_fill(0, count($officer_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, TID FROM characterid WHERE id IN ($ph)");
    $stmt->execute($officer_ids);
    $id_by_tid = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id_by_tid[$row['TID']] = (int)$row['id'];
    }
    if (empty($id_by_tid)) return $result;

    // Talents (TalentTID1..5) de ces officiers
    $ph2 = implode(',', array_fill(0, count($id_by_tid), '?'));
    $stmt2 = $pdo->prepare("SELECT * FROM officer_talents WHERE TID IN ($ph2)");
    $stmt2->execute(array_keys($id_by_tid));
    $talent_rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    if (empty($talent_rows)) return $result;

    // Talents déjà débloqués du joueur (progress_ability.Debloque = 1)
    $stmt3 = $pdo->prepare("SELECT id_character, id_ability FROM progress_ability WHERE id_player = ? AND Debloque = 1");
    $stmt3->execute([$id_player]);
    $unlocked = [];
    foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $unlocked[$row['id_character'] . '-' . $row['id_ability']] = true;
    }

    // TID de talent -> id_ability (abilitieid), en 1 seule requête groupée
    $all_talent_tids = [];
    foreach ($talent_rows as $row) {
        for ($i = 1; $i <= 5; $i++) {
            $tid = trim($row["TalentTID$i"] ?? '');
            if ($tid !== '') $all_talent_tids[$tid] = true;
        }
    }
    if (empty($all_talent_tids)) return $result;

    $tids = array_keys($all_talent_tids);
    $ph3 = implode(',', array_fill(0, count($tids), '?'));
    $stmt4 = $pdo->prepare("SELECT id, TID FROM abilitieid WHERE TID IN ($ph3)");
    $stmt4->execute($tids);
    $ability_id_by_tid = [];
    foreach ($stmt4->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ability_id_by_tid[$row['TID']] = (int)$row['id'];
    }

    // Coût de déblocage de chaque talent (1ère ligne officer_abilities pour ce TID)
    $stmt5 = $pdo->prepare("SELECT TID, UpgradeCost, UpgradeTimeH FROM officer_abilities WHERE TID IN ($ph3) ORDER BY Niveau ASC");
    $stmt5->execute($tids);
    $cost_by_tid = [];
    foreach ($stmt5->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!isset($cost_by_tid[$row['TID']])) { // garde la 1ère (Niveau le plus bas)
            $cost_by_tid[$row['TID']] = ['cost' => (float)($row['UpgradeCost'] ?? 0), 'time_h' => (float)($row['UpgradeTimeH'] ?? 0)];
        }
    }

    foreach ($talent_rows as $row) {
        $officer_id = $id_by_tid[$row['TID']] ?? null;
        if (!$officer_id) continue;

        for ($i = 1; $i <= 5; $i++) {
            $talent_tid = trim($row["TalentTID$i"] ?? '');
            if ($talent_tid === '' || !isset($ability_id_by_tid[$talent_tid])) continue;

            $ability_id = $ability_id_by_tid[$talent_tid];
            $key = $officer_id . '-' . $ability_id;
            if (!empty($unlocked[$key])) continue; // déjà débloqué, rien à ajouter

            $cost = $cost_by_tid[$talent_tid] ?? null;
            if ($cost) {
                $result['cost']   += $cost['cost'];
                $result['time_h'] += $cost['time_h'];
            }
        }
    }

    return $result;
}

/**
 * Armée : 1 ligne par catégorie (Troupes / Proto-troupes / Héros / Chefs de bataillon /
 * Capacités de canonnière) + Total, colonnes Or / Jetons Proto / Manuels de terrain / Temps.
 * - Troupes & Capacités de canonnière : Or (remaining_cost de getFilteredUnits).
 * - Proto-troupes : Jetons Proto (remaining_cost de getFilteredUnits).
 * - Héros : Or pour le niveau du héros lui-même + Manuels de terrain pour ses 3 capacités.
 * - Chefs de bataillon : Manuels de terrain pour capacité active + passive + talents restants
 *   (pas de "niveau" propre aux officiers, donc pas d'Or ici).
 */
function getArmyResourceSummary(PDO $pdo, $id_player, $troupes_list, $proto_list, $heros_list, $officers_list, $capacanon_list) {
    $sum_troupes   = muSumRemainingBaseList($troupes_list);
    $sum_proto     = muSumRemainingBaseList($proto_list);
    $sum_heros_niv = muSumRemainingBaseList($heros_list);
    $sum_capacanon = muSumRemainingBaseList($capacanon_list);

    // Capacités de Héros : payées en Jetons de héros, pas de palier (contrairement aux
    // officiers), donc on garde la somme complète telle quelle.
    $heros_jetons_cost = 0;
    $heros_jetons_time = 0.0;
    foreach ($heros_list as $h) {
        foreach (($h['hero_abilities'] ?? []) as $ab) {
            $r = muSumRemainingAbilityLevels($ab['levels'] ?? [], (int)($ab['current_level'] ?? 1));
            $heros_jetons_cost += $r['cost'];
            $heros_jetons_time += $r['time_h'];
        }
    }

    // Capacités d'officier : palier 1-10 = Manuels de terrain, palier 11-13 = Rapports
    // d'activité (voir muSumRemainingAbilityLevelsSplit).
    $officiers_manuel_cost  = 0; $officiers_manuel_time  = 0.0;
    $officiers_rapport_cost = 0; $officiers_rapport_time = 0.0;
    $officier_ids = [];
    foreach ($officers_list as $o) {
        $officier_ids[] = (int)$o['id_character'];
        foreach (($o['abilities'] ?? []) as $ab) { // 'active' + 'passive'
            $r = muSumRemainingAbilityLevelsSplit($ab['levels'] ?? [], (int)($ab['current_level'] ?? 1));
            $officiers_manuel_cost  += $r['manuel']['cost'];
            $officiers_manuel_time  += $r['manuel']['time_h'];
            $officiers_rapport_cost += $r['rapport']['cost'];
            $officiers_rapport_time += $r['rapport']['time_h'];
        }
    }
    // Talents (déblocage unique, pas de notion de palier) : comptés avec les Manuels.
    $talents = getOfficersTalentsRemainingCost($pdo, $id_player, $officier_ids);
    $officiers_manuel_cost += $talents['cost'];
    $officiers_manuel_time += $talents['time_h'];

    $resume = [
        'Troupe' => [
            'label' => 'Troupes',
            'gold' => $sum_troupes['cost'], 'proto' => 0, 'manuels' => 0, 'rapport' => 0, 'jetons_heros' => 0,
            'time_h' => $sum_troupes['time_h'],
        ],
        'Proto' => [
            'label' => 'Proto-troupes',
            'gold' => 0, 'proto' => $sum_proto['cost'], 'manuels' => 0, 'rapport' => 0, 'jetons_heros' => 0,
            'time_h' => $sum_proto['time_h'],
        ],
        'Hero' => [
            'label' => 'Héros',
            'gold' => $sum_heros_niv['cost'], 'proto' => 0, 'manuels' => 0, 'rapport' => 0, 'jetons_heros' => $heros_jetons_cost,
            'time_h' => $sum_heros_niv['time_h'] + $heros_jetons_time,
        ],
        'Officier' => [
            'label' => 'Chefs de bataillon',
            'gold' => 0, 'proto' => 0, 'manuels' => $officiers_manuel_cost, 'rapport' => $officiers_rapport_cost, 'jetons_heros' => 0,
            'time_h' => $officiers_manuel_time + $officiers_rapport_time,
        ],
        'Spell' => [
            'label' => 'Capacités de canonnière',
            'gold' => $sum_capacanon['cost'], 'proto' => 0, 'manuels' => 0, 'rapport' => 0, 'jetons_heros' => 0,
            'time_h' => $sum_capacanon['time_h'],
        ],
    ];

    $total = ['gold' => 0, 'proto' => 0, 'manuels' => 0, 'rapport' => 0, 'jetons_heros' => 0, 'time_h' => 0.0];
    foreach ($resume as $row) {
        $total['gold']         += $row['gold'];
        $total['proto']        += $row['proto'];
        $total['manuels']      += $row['manuels'];
        $total['rapport']      += $row['rapport'];
        $total['jetons_heros'] += $row['jetons_heros'];
        $total['time_h']       += $row['time_h'];
    }
    $resume['TOTAL'] = array_merge(['label' => 'Total'], $total);

    return $resume;
}

/**
 * Tableau récapitulatif "Ressources pour tout finir au max" — Bâtiments.
 * Pensé pour être affiché juste sous les cartes de Building-Overview.
 */
function renderBuildingResourceSummaryTable($resume_batiments) {
    echo "<div class='dash-section resource-summary'>";
    echo "<h3 class='resource-summary-title'>🏗️ Ressources pour tout finir au max</h3>";
    echo "<div class='resource-summary-table-wrap'>";
    echo "<table class='resource-summary-table'>
            <thead>
                <tr>
                    <th>Catégorie</th>
                    <th><img src='images/icons/Gold.png' alt='Or' title='Or'></th>
                    <th><img src='images/icons/Wood.png' alt='Bois' title='Bois'></th>
                    <th><img src='images/icons/Stone.png' alt='Pierre' title='Pierre'></th>
                    <th><img src='images/icons/Iron.png' alt='Fer' title='Fer'></th>
                    <th>Temps</th>
                </tr>
            </thead><tbody>";
    foreach ($resume_batiments as $key => $row) {
        $is_total = ($key === 'TOTAL');
        $tr_class = $is_total ? " class='resource-summary-total'" : "";
        echo "<tr{$tr_class}>
                <td>{$row['label']}</td>
                <td>" . number_format($row['gold'], 0, ',', ' ') . "</td>
                <td>" . number_format($row['wood'], 0, ',', ' ') . "</td>
                <td>" . number_format($row['stone'], 0, ',', ' ') . "</td>
                <td>" . number_format($row['iron'], 0, ',', ' ') . "</td>
                <td>" . formatSecondsToText($row['time_seconds']) . "</td>
              </tr>";
    }
    echo "</tbody></table></div>";
    echo "</div>"; // .dash-section.resource-summary
}

/**
 * Tableau récapitulatif "Ressources pour tout finir au max" — Armée.
 * Pensé pour être affiché juste sous les cartes de Army-Overview.
 * Colonnes détaillées : Or / Jetons Proto / Manuels de terrain (capa officier niv. 1-10)
 * / Rapports d'activité (capa officier niv. 11-13) / Jetons de héros (capa héros) / Temps.
 */
function renderArmyResourceSummaryTable($resume_armee) {
    echo "<div class='dash-section resource-summary'>";
    echo "<h3 class='resource-summary-title'>⚔️ Ressources pour tout finir au max</h3>";
    echo "<div class='resource-summary-table-wrap'>";
    echo "<table class='resource-summary-table'>
            <thead>
                <tr>
                    <th>Catégorie</th>
                    <th><img src='images/icons/Gold.png' alt='Or' title='Or'></th>
                    <th><img src='images/icons/Proto_Token.png' alt='Jetons Proto' title='Jetons Proto'></th>
                    <th title=\"Capacités d'officier niveau 1 à 10\">📖 Manuels de terrain</th>
                    <th title=\"Capacités d'officier niveau 11 à 13\">📰 Rapports d'activité</th>
                    <th title=\"Capacités de héros\">🎖️ Jetons de héros</th>
                    <th>Temps</th>
                </tr>
            </thead><tbody>";
    foreach ($resume_armee as $key => $row) {
        $is_total = ($key === 'TOTAL');
        $tr_class = $is_total ? " class='resource-summary-total'" : "";
        echo "<tr{$tr_class}>
                <td>{$row['label']}</td>
                <td>" . number_format($row['gold'], 0, ',', ' ') . "</td>
                <td>" . number_format($row['proto'], 0, ',', ' ') . "</td>
                <td>" . number_format($row['manuels'], 0, ',', ' ') . "</td>
                <td>" . number_format($row['rapport'], 0, ',', ' ') . "</td>
                <td>" . number_format($row['jetons_heros'], 0, ',', ' ') . "</td>
                <td>" . formatUnitsTime($row['time_h']) . "</td>
              </tr>";
    }
    echo "</tbody></table></div>";
    echo "</div>"; // .dash-section.resource-summary
}

function renderStatsSidebar($title, $buildings_data, $stats) {
    // --- Total restant (coût + temps) pour amener TOUTE la catégorie au niveau max ---
    $total_gold = 0;
    $total_wood = 0;
    $total_stone = 0;
    $total_iron = 0;
    $total_seconds = 0;
    foreach ($buildings_data as $b) {
        $total_gold    += (int)($b['remaining_gold']         ?? 0);
        $total_wood    += (int)($b['remaining_wood']         ?? 0);
        $total_stone   += (int)($b['remaining_stone']        ?? 0);
        $total_iron    += (int)($b['remaining_iron']         ?? 0);
        $total_seconds += (int)($b['remaining_time_seconds'] ?? 0);
    }

    echo "<div class='stats-sidebar' style='background: #2c3e50; padding: 15px; border-radius: 8px; color: #fff; border: 1px solid #456789;'>";
    echo "<h4 style='margin: 0 0 10px 0; border-bottom: 2px solid #1abc9c; padding-bottom: 5px;'>{$title}</h4>";
    echo "<div style='font-size: 1.8em; font-weight: bold; color: #1abc9c; text-align: center; margin-bottom: 10px;'>{$stats['percent']}%</div>";

    echo "<ul style='list-style: none; padding: 0; margin: 0; font-size: 0.85em;'>";
    
    $unique_buildings = [];
    foreach ($buildings_data as $b) {
        $tid = $b['TID'];
        if (!isset($unique_buildings[$tid])) {
            $unique_buildings[$tid] = ['nom' => $b['nom_building'], 'current' => 0, 'max' => 0];
        }
        $unique_buildings[$tid]['current'] += $b['niveau_actuel'];
        $unique_buildings[$tid]['max'] += $b['niveau_max'];
    }

    foreach ($unique_buildings as $b) {
        $color = ($b['current'] >= $b['max']) ? '#2ecc71' : '#f1c40f';
        echo "<li style='padding: 5px 0; border-bottom: 1px solid #3e4a56;'>
                {$b['nom']} 
                <span style='float:right; color:{$color};'>{$b['current']}/{$b['max']}</span>
              </li>";
    }
    echo "</ul>";
    
    if ($total_gold > 0 || $total_wood > 0 || $total_stone > 0 || $total_iron > 0 || $total_seconds > 0) {
        $total_time_txt = formatSecondsToText($total_seconds);
        echo "<div class='sidebar-total-remaining'>
                <div class='sidebar-total-title'>Total restant pour tout terminer</div>
                <div class='sidebar-total-costs'>";
        if ($total_gold > 0) {
            echo "<span class='sidebar-total-item'><img src='images/icons/Gold.png' alt='Or'>" . number_format($total_gold, 0, ',', ' ') . "</span>";
        }
        if ($total_wood > 0 || $total_stone > 0 || $total_iron > 0) {
            echo "
                    <span class='sidebar-total-item'><img src='images/icons/Wood.png' alt='Bois'>" . number_format($total_wood, 0, ',', ' ') . "</span>
                    <span class='sidebar-total-item'><img src='images/icons/Stone.png' alt='Pierre'>" . number_format($total_stone, 0, ',', ' ') . "</span>
                    <span class='sidebar-total-item'><img src='images/icons/Iron.png' alt='Fer'>" . number_format($total_iron, 0, ',', ' ') . "</span>";
        }
        echo "
                </div>
                <div class='sidebar-total-time'><img src='images/icons/Time Icon.png' alt='Temps'>{$total_time_txt}</div>
              </div>";
    }
    echo"</div>";
}

/**
 * Convertit un temps d'amélioration exprimé en HEURES (ex: UpgradeTimeH, potentiellement décimal)
 * en texte lisible "Xj Yh Zm".
 */
function formatUnitsTime($hours) {
    $hours = (float)$hours;
    if ($hours <= 0) return "3 sec";

    $total_minutes = (int)round($hours * 60);
    $days = intdiv($total_minutes, 1440);
    $remaining_hours = intdiv($total_minutes % 1440, 60);
    $remaining_minutes = $total_minutes % 60;

    $res = "";
    if ($days > 0) $res .= $days . "j ";
    if ($remaining_hours > 0) $res .= $remaining_hours . "h ";
    if ($remaining_minutes > 0) $res .= $remaining_minutes . "m";

    $res = trim($res);
    return ($res === '') ? "3 sec" : $res;
}

/**
 * Même conversion que formatUnitsTime, mais à partir d'un nombre de SECONDES
 * (utilisé pour les temps de construction des bâtiments : BuildTimeD/H/M/S).
 */
function formatSecondsToText($seconds) {
    return formatUnitsTime(((float)$seconds) / 3600);
}

/**
 * Rendu des cartes d'unités : Officiers, Héros, Troupes et Proto-troupes.
 *
 * - Officiers / Héros : design "unit-card" (talents + capacités pour les officiers).
 * - Troupes / Proto-troupes : design "troop-card" (niveau + coût + temps), avec
 *   vérification du bâtiment requis via la colonne UpgradeHouseLevel :
 *      - Class 'Troupe' -> niveau de l'Arsenal   (TID_BUILDING_LABORATORY / id 13)
 *      - Class 'Proto'  -> niveau de l'Atelier de Proto-troupes (TID_PROTOTROOP_FACTORY / id 30)
 *      - Class 'Spell' -> niveau de l'Arsenal   (TID_BUILDING_LABORATORY / id 13)
 *
 * $house_levels attend un tableau ['arsenal' => int, 'proto_factory' => int]
 * représentant le niveau RÉEL construit par le joueur pour ces deux bâtiments.
 * (Non pertinent pour les listes d'Officiers/Héros, laisser à [] dans ce cas.)
 */
function renderUnitsTable($data, $progress = [], $house_levels = [], $pdo = null, $id_player = null) {
    if (empty($data)) {
        echo "<p class='empty-msg'>Aucune unité débloquée.</p>";
        return;
    }

    // Chaque liste passée à cette fonction ($heros_list, $officers_list, $troupes_list,
    // $proto_list, $capacanon_list) est homogène en Class : on choisit donc le conteneur
    // global (grille "unit-card" ou grille "troop-card") une seule fois, à partir du 1er élément.
    $premiere_class      = trim($data[0]['Class'] ?? '');
    $is_troop_list       = (strcasecmp($premiere_class, 'Troupe') === 0);
    $is_prototroop_list  = (strcasecmp($premiere_class, 'Proto') === 0);
    $is_spell_list       = (strcasecmp($premiere_class, 'Spell') === 0);
    $use_troop_design    = ($is_troop_list || $is_prototroop_list || $is_spell_list);

    $label_categorie = $is_prototroop_list ? 'les proto-troupes' : ($is_spell_list ? 'les capacités' : ($is_troop_list ? 'les troupes' : 'les unités'));
    renderHideMaxedToggle($label_categorie);

    echo $use_troop_design
        ? "<div class='troops-grid hide-maxed-container'>"
        : "<div class='main-layout-container hide-maxed-container' style='display: flex; gap: 20px; align-items: flex-start; width: 100%;'><div class='units-grid-left' style='display: flex; flex-direction: row; flex-wrap: wrap; gap: 15px; flex: 1; justify-content: center;'>";

    foreach ($data as $u) {
        $tid         = $u['TID'];
        $safe_id     = str_replace(" ", "-", $u['nom']);
        $max_lvl     = intval($u['niveau_autorise'] ?? 1);
        $current_lvl = $progress[$tid] ?? $u['niveau_joueur'] ?? 1;
        if ($current_lvl === 0) $current_lvl = 1;
        $is_maxed_unit = ($current_lvl >= $max_lvl);
        $maxed_attr    = $is_maxed_unit ? "1" : "0";

        $is_officer    = !empty($u['is_officer']);
        $is_hero       = !empty($u['is_hero']);
        $is_troop      = (strcasecmp(trim($u['Class'] ?? ''), 'Troupe') === 0);
        $is_prototroop = (strcasecmp(trim($u['Class'] ?? ''), 'Proto') === 0);
        $is_spell      = (strcasecmp(trim($u['Class'] ?? ''), 'Spell') === 0);

        // =====================================================================
        // DESIGN "TROUPE" / "PROTO-TROUPE" / "CAPACITÉ DE CANONNIÈRE" (repris de l'ex-renderTroopsTable)
        // =====================================================================
        if ($is_troop || $is_prototroop || $is_spell) {
            $safe_id_trp = "trp-" . preg_replace('/[^a-zA-Z0-9]/', '', $tid);
            $nom         = htmlspecialchars($u['nom'] ?? '???');
            $icon        = $u['IconExportName'] ?? 'default';
            $class_css   = htmlspecialchars($u['Class'] ?? '');


            $max         = $max_lvl;
            $is_maxed    = ($current_lvl >= $max);

            // --- Vérification du bâtiment requis pour le PROCHAIN niveau ---
            // (Arsenal pour les Troupes, Atelier de Proto-troupes pour les Proto).
            // Les Capacités de canonnière n'ont pas de bâtiment requérant connu pour
            // l'instant : UpgradeHouseLevel vaut 0 dans ce cas, donc jamais bloquant.
            $required_house_level = (int)($u['next_cost']['UpgradeHouseLevel'] ?? 0);
            $player_house_level   = $is_prototroop ? (int)($house_levels['proto_factory'] ?? 0) : (int)($house_levels['arsenal'] ?? 0);
            $house_label = $is_prototroop ? "Atelier de Proto-troupes" : "Arsenal";
            $house_ok    = ($required_house_level <= $player_house_level);

            echo "
            <div class='troop-card' id='card-{$safe_id_trp}' data-tid='{$tid}' data-maxed='{$maxed_attr}'>
                <div class='troop-card-visual'>
                    <img class='troop-card-img' src='images/characters/{$class_css}/{$icon}.png' alt='{$nom}' onerror=\"this.src='images/default-building.png'\">
                </div>

                <div class='troop-card-info'>
                    <span class='troop-card-name'>{$nom}</span>
                    <span class='troop-card-level' id='lvl-{$safe_id_trp}'>Niveau {$current_lvl} / {$max}</span>
                </div>";

            if (!$is_maxed && !empty($u['next_cost'])) {
                $cost      = $u['next_cost'];
                $cout      = $cost['UpgradeCost'] ?? 0;
                $temps_txt = formatUnitsTime($cost['UpgradeTimeH'] ?? 0);

                // Pas de distinction en base : Troupe => Or, Proto => Jetons Proto
                $cost_icon  = $is_prototroop ? 'images/icons/Proto_Token.png' : 'images/icons/Gold.png';
                $cost_label = $is_prototroop ? 'Jetons Proto' : 'Or';

                echo "
                <div class='troop-card-costs'>
                    <span class='troop-cost-item'><img class='troop-cost-icon' src='{$cost_icon}' alt='{$cost_label}'>{$cout}</span>
                </div>
                <div class='troop-card-time'>
                    <img class='troop-time-icon' src='images/icons/Time Icon.png' alt='Temps'>{$temps_txt}
                </div>";

                if (!$house_ok) {
                    echo "
                <div class='troop-card-requirement' style='color:#e74c3c; font-size:0.85em; font-weight:600; margin-top:4px; text-align:center;'>
                    {$house_label} Niv. {$required_house_level} requis
                </div>";
                }
            }

            $disabled = ($is_maxed || !$house_ok) ? "disabled" : "";
            if ($is_maxed) {
                $btn_text = "Max !";
            } elseif (!$house_ok) {
                $btn_text = "Verrouillé";
            } else {
                $btn_text = "Améliorer";
            }

            echo "
                <div class='troop-card-action'>
                    <button class='btn-upgrade' {$disabled} onclick=\"triggerUpgradeCharacter('{$tid}', '{$safe_id_trp}', {$max})\">
                        <span class='btn-text'>{$btn_text}</span>
                    </button>
                </div>
            </div>";

            continue;
        }

        // =====================================================================
        // DESIGN "OFFICIER" / "HÉROS" (inchangé)
        // =====================================================================
        $display_text = "Niveau " . $current_lvl;

        // --- LOGIQUE DÉBLOCAGE ---
        $is_locked = ($is_officer && (($u['Debloque'] ?? 0) != 1));
        $locked_class = $is_locked ? 'unit-locked' : '';

        echo "<div class='unit-card {$locked_class} " . ($is_hero ? 'unit-card-hero' : ($is_officer ? 'unit-card-officer' : '')) . "' id='card-{$safe_id}' data-tid='{$tid}' data-id-character='{$u['id_character']}' data-maxed='{$maxed_attr}'>";

        // Overlay de déblocage si nécessaire
        if ($is_locked) {
            $officer_nom_esc = htmlspecialchars(addslashes($u['nom'] ?? $tid), ENT_QUOTES);
            echo "<div class='unlock-overlay'>
                    <button class='btn-unlock-officer' onclick=\"unlockOfficer({$u['id_character']}, '{$officer_nom_esc}')\">
                        Débloquer l'Officier
                    </button>
                  </div>";
        }

        if ($is_hero) {
            // =================================================================
            // DESIGN "HÉROS" (séparé du design Officier) :
            // le bouton de montée de niveau + le coût/temps du prochain niveau
            // sont maintenant DANS unit-info-wrapper, sous unit-details.
            // =================================================================
            $hero_maxed = ($current_lvl >= $max_lvl);

            echo "<div class='unit-info-wrapper'>
                <div class='unit-img-wrapper'>
                    <img class='img-unit' src='images/characters/" . htmlspecialchars($u['Class']) . "/{$u['IconExportName']}.png' alt='" . htmlspecialchars($u['nom']) . "'>
                </div>
                <div class='unit-details' style='display: flex; flex-direction: column; width: 100%;'>
                    <div class='unit-name' style='font-weight: bold; font-size: 1.1em; color: #ffffff;'>" . htmlspecialchars($u['nom']) . "</div>
                    <div class='lvl-display' id='lvl-{$safe_id}' style='color: #1abc9c; font-weight: bold;'>{$display_text}</div>
                </div>";

            // Coût / temps du prochain niveau, entre unit-details et le bouton
            if (!$hero_maxed && !empty($u['next_cost'])) {
                $cost      = $u['next_cost'];
                $cout      = $cost['UpgradeCost'] ?? 0;
                $temps_txt = formatUnitsTime($cost['UpgradeTimeH'] ?? 0);

                echo "<div class='hero-upgrade-cost'>
                        <span class='hero-cost-item'><img class='hero-cost-icon' src='images/icons/Gold.png' alt='Or' style='width:25px'>{$cout}</span>
                        <span class='hero-time-item'><img class='hero-time-icon' src='images/icons/Time Icon.png'  style='width:25px' alt='Temps'>{$temps_txt}</span>
                    </div>";
            }

            echo "<button class='btn-upgrade' " . ($hero_maxed ? "disabled" : "") . " onclick=\"triggerUpgradeCharacter('{$tid}', '{$safe_id}', {$max_lvl})\">
                        " . ($hero_maxed ? "Max !" : "Améliorer") . "
                    </button>
                </div>"; // fin unit-info-wrapper

            // Rendu des 3 Capacités du héros côte à côte, en colonnes (pas de talents,
            // pas de distinction active/passive contrairement aux officiers)
            echo "<div class='hero-ability-columns'>";
            if (empty($u['hero_abilities'])) {
                echo "<div class='officer-ability-info-placeholder'>
                        <span style='color:#7f8c8d; font-weight:600; font-size: 0.9em;'>Aucune capacité configurée</span>
                    </div>";
            } else {
                foreach ($u['hero_abilities'] as $talent) {
                    echo "<div class='hero-ability-column'>";
                    echo renderOfficerAbilityRow($talent, $u['id_character'], $current_lvl, "");
                    echo "</div>";
                }
            }
            echo "</div>";

        } else {
            // =================================================================
            // DESIGN "OFFICIER" (et repli générique) : structure inchangée
            // =================================================================
            echo "<div class='unit-info-wrapper'>
                <div class='unit-img-wrapper'>
                    <img class='img-unit' src='images/characters/" . htmlspecialchars($u['Class']) . "/{$u['IconExportName']}.png' alt='" . htmlspecialchars($u['nom']) . "'>
                </div>
                <div class='unit-details' style='display: flex; flex-direction: column; width: 100%;'>
                    <div class='unit-name' style='font-weight: bold; font-size: 1.1em; color: #ffffff;'>" . htmlspecialchars($u['nom']) . "</div>
                    <div class='lvl-display' id='lvl-{$safe_id}' style='color: #1abc9c; font-weight: bold;'>{$display_text}</div>
                </div>
            </div>";

            if ($is_officer && $pdo && $id_player) {
    // 1. Compter les talents débloqués
    $stmt_count = $pdo->prepare("
        SELECT SUM(Debloque) as total_debloque
        FROM progress_ability
        WHERE id_player = ? AND id_character = ?
        AND id_ability IN (
            SELECT ai.id
            FROM characterid ci
            INNER JOIN officer_talents ot ON ot.TID = ci.TID
            INNER JOIN abilitieid ai ON ai.TID IN (ot.TalentTID1, ot.TalentTID2, ot.TalentTID3, ot.TalentTID4, ot.TalentTID5)
            WHERE ci.id = ?
        )
    ");
    $stmt_count->execute([$id_player, $u['id_character'], $u['id_character']]);
    $talents_debloques = max(0, min(5, (int)($stmt_count->fetchColumn() ?: 0)));
    $imageFile = "talent_{$talents_debloques}_icon.png";

    // 2. Trouver le PROCHAIN talent à débloquer (le premier avec Debloque=0)
    $next_talent_id = null;
    $next_talent_nom = null;
    $stmt_next = $pdo->prepare("
        SELECT ai.id, ai.TID, t.FR AS nom
        FROM characterid ci
        INNER JOIN officer_talents ot ON ot.TID = ci.TID
        INNER JOIN abilitieid ai ON ai.TID IN (ot.TalentTID1, ot.TalentTID2, ot.TalentTID3, ot.TalentTID4, ot.TalentTID5)
        LEFT JOIN texts t ON t.TID = ai.TID
        LEFT JOIN progress_ability pa ON pa.id_player = ? AND pa.id_character = ? AND pa.id_ability = ai.id
        WHERE ci.id = ? AND ai.TID IS NOT NULL
          AND (pa.Debloque IS NULL OR pa.Debloque = 0)
        ORDER BY
            CASE ai.TID
                WHEN ot.TalentTID1 THEN 1
                WHEN ot.TalentTID2 THEN 2
                WHEN ot.TalentTID3 THEN 3
                WHEN ot.TalentTID4 THEN 4
                WHEN ot.TalentTID5 THEN 5
            END
        LIMIT 1
    ");
    $stmt_next->execute([$id_player, $u['id_character'], $u['id_character']]);
    $next_talent = $stmt_next->fetch(PDO::FETCH_ASSOC);
    if ($next_talent && ($talents_debloques < 5)) {
        $next_talent_id = $next_talent['id'];
        $next_talent_nom = $next_talent['nom'] ?: $next_talent['TID'];
    }

    // 3. Affichage
    echo "<div class='officer-talent' style='text-align: center; margin: 10px 0;'>
            <img src='images/icons/{$imageFile}' style='width: 80px;' alt='Talents débloqués: {$talents_debloques}'>
            <div class='talent-status'>
                <p class='talent-count'>Talents débloqués : {$talents_debloques}/5</p>";

    // 🔥 Bouton "Débloquer talent suivant" (seulement si un talent est disponible)
    if ($next_talent_id && $talents_debloques < 5) {
        $next_talent_nom_esc = htmlspecialchars($next_talent_nom, ENT_QUOTES);
        echo "<button class='btn-unlock-talent' data-character='{$u['id_character']}' data-ability='{$next_talent_id}' data-tid='{$next_talent_nom_esc}'
                      onclick='unlockTalent(this)' style='margin-top: 8px; padding: 6px 12px;'>
                  Débloquer talent suivant
              </button>";
    } elseif ($talents_debloques >= 5) {
        echo "<p style='color: #2ecc71; margin-top: 8px;'>Tous les talents débloqués !</p>";
    }

    echo "</div></div>";

                // Rendu des Capacités (Active / Passive) - SANS les talents
                echo "<div class='officer-ability'>";
                foreach (['passive', 'active'] as $type) {
                    $talent = $u['abilities'][$type] ?? null;
                    if (empty($talent) || empty($talent['id_ability'])) {
                        echo "<div class='officer-ability-info-placeholder'>
                                <span style='color:#7f8c8d; font-weight:600; font-size: 0.9em;'>Capacité {$type} : Aucune</span>
                            </div>";
                        continue;
                    }
                    echo renderOfficerAbilityRow($talent, $u['id_character'], $current_lvl, "[{$type}]");
                }
                echo "</div>";
            } else {
                echo "<div class='building-action'>
                        <button class='btn-upgrade' onclick=\"triggerUpgradeCharacter('{$tid}', '{$safe_id}', {$max_lvl})\">Améliorer</button>
                    </div>";
            }
        }
        echo "</div>";
    }

    echo $use_troop_design ? "</div>" : "</div></div>";
}

/**
 * Rendu d'une ligne de capacité (officier ACTIVE/PASSIVE ou capacité de héros) — factorisé
 * pour être réutilisé à l'identique par les deux designs, avec un simple libellé de type
 * optionnel ("[active]"/"[passive]" pour les officiers, vide pour les héros).
 */
/**
 * Calcule le VRAI niveau max atteignable pour une capacité, à partir de sa liste de paliers
 * officer_abilities (Niveau, UpgradeCost, ...).
 *
 * Convention des données (vérifiée sur la table réelle) : la ligne dont Niveau = niveau ACTUEL
 * décrit le coût pour passer au niveau suivant. Certaines capacités ont, après leur dernier palier
 * payant, une ou plusieurs lignes "terminateurs" avec UpgradeCost = 0 : elles ne représentent PAS
 * un palier supplémentaire réel, juste "il n'y a plus rien à acheter à partir d'ici".
 * On ne peut donc pas se fier à MAX(Niveau) brut (certaines capacités ont plusieurs lignes
 * terminateurs consécutives avec un Niveau très supérieur au vrai plafond) : il faut simuler
 * la progression pas à pas et s'arrêter au premier palier manquant OU à coût 0.
 */
function getAbilityRealMaxLevel($levels) {
    if (empty($levels)) return 1;

    $cost_by_niveau = [];
    foreach ($levels as $row) {
        $cost_by_niveau[(int)$row['Niveau']] = (float)($row['UpgradeCost'] ?? 0);
    }

    $level = 1;
    while (isset($cost_by_niveau[$level]) && $cost_by_niveau[$level] > 0) {
        $level++;
    }
    return $level;
}

/**
 * Niveau max "table" d'une capacité, TERMINATEURS INCLUS (contrairement à
 * getAbilityRealMaxLevel, qui s'arrête au premier palier à coût 0 = max réellement
 * achetable). Certaines capacités d'officier ont volontairement des lignes
 * supplémentaires à UpgradeCost = 0 au-delà du max achetable (ex. Niveau 12 achetable,
 * puis 13/14/15 listés à coût 0) : ces niveaux existent pour être atteints via le bonus
 * d'affichage du Talent 3 (+2 niveaux, voir queries.php), PAS pour être achetés. Le jeu
 * fonctionne ainsi : le coût affiché reste calculé sur le niveau réel, mais le niveau
 * affiché (sidebar chefs de bataillon + dashboard) doit pouvoir grimper jusqu'à ce vrai
 * plafond de table.
 */
function getAbilityTableMaxLevel($levels) {
    if (empty($levels)) return 1;
    $niveaux = array_column($levels, 'Niveau');
    return $niveaux ? (int)max($niveaux) : 1;
}

function renderOfficerAbilityRow($talent, $id_character, $current_lvl, $type_label) {
    $ab_id      = $talent['id_ability'];
    $ab_icon    = $talent['IconExportName'];
    $ab_tid     = $talent['TID'];
    $safe_ab_id = "ab-" . preg_replace('/[^a-zA-Z0-9]/', '', $ab_tid);
    $ab_lvl     = (int)$talent['current_level'];
    // Niveau affiché au joueur (avec bonus talent 3 le cas échéant) — n'intervient QUE dans le
    // texte "Niveau X" ; tout le calcul du prochain palier / coût reste basé sur $ab_lvl (réel).
    $ab_lvl_display = (int)($talent['display_level'] ?? $ab_lvl);

    $next_lvl = $ab_lvl + 1; // le niveau qui sera atteint APRÈS l'amélioration (texte/affichage uniquement)
    $next_lvl_data = null;
    if (!empty($talent['levels'])) {
        foreach ($talent['levels'] as $row) {
            // La ligne officer_abilities dont Niveau = niveau ACTUEL décrit le coût pour passer
            // au niveau suivant (convention utilisée par upgrade_ability.php : WHERE Niveau =
            // $current_ability_niveau).
            if ((int)$row['Niveau'] === $ab_lvl) {
                $next_lvl_data = $row;
                break;
            }
        }
    }
    // Un palier avec UpgradeCost = 0 (ou absent) est un "terminateur" : il ne s'agit pas d'un
    // vrai palier supplémentaire, juste du marqueur "rien de plus à acheter à partir d'ici".
    $is_max = ($next_lvl_data === null) || ((float)($next_lvl_data['UpgradeCost'] ?? 0) <= 0);

    $cost_display = "";
    if ($next_lvl_data) {
        $resource_name = htmlspecialchars($next_lvl_data['UpgradeResource']);
        $cost_display  = "<img src='images/{$resource_name}.png' alt='{$resource_name}' style='width: 20px; vertical-align: middle; margin-left: 4px;'>" . $next_lvl_data['UpgradeCost'];
    }

    $html = "<div class='officer-ability-row' data-id-ability='{$ab_id}'>
            <div class='officer-ability-info'>
                <div class='officer-ability-header'>
                    <div><span class='officer-ability-name'>{$talent['nom']}</span> <span class='officer-ability-type'>{$type_label}</span></div>
                    <div><img src='images/characters/Officier/talent/{$ab_icon}.webp' class='officer-ability-icon'></div>
                </div>
                <span class='officer-ability-level'>Niveau {$ab_lvl_display} " . ($is_max ? "" : $cost_display) . "</span>
            </div>
            <div class='officer-ability-upgrade'>";
                if (!$is_max) {
                    $req_h    = (int)$next_lvl_data['HeroLevel'];
                    $can_up   = ($current_lvl >= $req_h);
                    $btn_txt  = $can_up ? "Améliorer" : "Niv. {$req_h} requis";
                    $disabled = $can_up ? "" : "disabled style='opacity:0.6; cursor:not-allowed;'";

                    $ab_nom_esc = htmlspecialchars(addslashes($talent['nom'] ?? $ab_tid), ENT_QUOTES);
                    $html .= "<button class='btn-upgrade-ability' {$disabled} data-character='{$id_character}' data-ability='{$ab_id}' data-tid='{$ab_nom_esc}' data-next-level='{$next_lvl}' onclick=\"triggerUpgradeAbility(this, '{$ab_id}', '{$safe_ab_id}')\">{$btn_txt}</button>";
                } else {
                    $html .= "<span class='officer-ability-lvl-max' style='color: #e74c3c; font-weight: bold;'>Niveau max</span>";
                }
    $html .= "</div>
        </div>";

    return $html;
}

/**
 * Sidebar de progression pour les Troupes / Proto-troupes (sans capacités/talents).
 */
function renderUnitsStatsSidebar($title, $units_list) {
    if (empty($units_list)) return;

    $total_current = 0;
    $total_max = 0;
    $total_cost = 0;
    $total_time_h = 0.0;
    $rows_html = "";

    // Liste homogène en Class à chaque appel (Troupes seules ou Proto-troupes seules)
    $is_proto  = (strcasecmp(trim($units_list[0]['Class'] ?? ''), 'Proto') === 0);
    $cost_icon = $is_proto ? 'images/icons/Proto_Token.png' : 'images/icons/Gold.png';
    $cost_label = $is_proto ? 'Jetons Proto' : 'Or';

    foreach ($units_list as $u) {
        $nom = htmlspecialchars($u['nom'] ?? '???');
        $cur = (int)($u['niveau_joueur'] ?? 1);
        $max = (int)($u['niveau_autorise'] ?? 1);

        $total_current += $cur;
        $total_max += $max;
        $total_cost   += (int)($u['remaining_cost']   ?? 0);
        $total_time_h += (float)($u['remaining_time_h'] ?? 0);

        $color = ($cur >= $max) ? '#2ecc71' : '#f1c40f';
        $rows_html .= "<li style='padding: 5px 0; border-bottom: 1px solid #3e4a56;'>
                {$nom}
                <span style='float:right; color:{$color};'>{$cur}/{$max}</span>
              </li>";
    }

    $percent = ($total_max > 0) ? round(($total_current / $total_max) * 100) : 0;

    echo "<div class='stats-sidebar' style='background: #2c3e50; padding: 15px; border-radius: 8px; color: #fff; border: 1px solid #456789;'>";
    echo "<h4 style='margin: 0 0 10px 0; border-bottom: 2px solid #1abc9c; padding-bottom: 5px;'>{$title}</h4>";
    echo "<div style='font-size: 1.8em; font-weight: bold; color: #1abc9c; text-align: center; margin-bottom: 10px;'>{$percent}%</div>";
    echo "<ul style='list-style: none; padding: 0; margin: 0; font-size: 0.85em;'>{$rows_html}</ul>";
    if ($total_cost > 0 || $total_time_h > 0) {
        $total_time_txt = formatUnitsTime($total_time_h);
        echo "<div class='sidebar-total-remaining'>
                <div class='sidebar-total-title'>Total restant pour tout terminer</div>
                <div class='sidebar-total-costs'>
                    <span class='sidebar-total-item'><img src='{$cost_icon}' alt='{$cost_label}'>" . number_format($total_cost, 0, ',', ' ') . "</span>
                </div>
                <div class='sidebar-total-time'><img src='images/icons/Time Icon.png' alt='Temps'>{$total_time_txt}</div>
              </div>";
    }

    
    echo "</div>";
}

function renderLeadersStatsSidebar($pdo, $id_player, $officers_list) {
    if (empty($officers_list)) return;

    $global_current = 0;
    $global_max = 0; 
    $rows_html = "";

    foreach ($officers_list as $u) {
        $char_id = (int)$u['id_character'];
        
        $talents_count = $u['total_talents_unlocked'] ?? 0;
        
        // On initialise à NULL pour détecter l'absence de progression
        $passive_lvl = null;
        $active_lvl = null;
        $passive_max_lvl = 0;
        $active_max_lvl = 0;
        
        if (!empty($u['abilities']['passive'])) {
            // display_level = niveau réel + bonus talent 3 (déjà calculé et plafonné dans queries.php)
            $passive_lvl = $u['abilities']['passive']['display_level'] ?? ($u['abilities']['passive']['current_level'] ?? null);
            if (!empty($u['abilities']['passive']['levels'])) {
                $lvls = array_column($u['abilities']['passive']['levels'], 'Niveau');
                $passive_max_lvl = $lvls ? (int)max($lvls) : 0;
            }
        }
        if (!empty($u['abilities']['active'])) {
            $active_lvl = $u['abilities']['active']['display_level'] ?? ($u['abilities']['active']['current_level'] ?? null);
            if (!empty($u['abilities']['active']['levels'])) {
                $lvls = array_column($u['abilities']['active']['levels'], 'Niveau');
                $active_max_lvl = $lvls ? (int)max($lvls) : 0;
            }
        }

        // Calcul du progrès : si NULL ou niveau 0 (pas encore débloqué), on considère que c'est 0
        $prog_talents = $talents_count; 
        $prog_passive = ($passive_lvl !== null && $passive_lvl > 0) ? ($passive_lvl - 1) : 0;
        $prog_active  = ($active_lvl !== null && $active_lvl > 0) ? ($active_lvl - 1) : 0;

        $current_sum = $prog_talents + $prog_passive + $prog_active;
        // Max dynamique : un chef sans capacité active (ou passive) ne doit pas être pénalisé
        // par un dénominateur qui suppose les deux capacités présentes et plafonnées à 15.
        $max_sum = 5 + max(0, $passive_max_lvl - 1) + max(0, $active_max_lvl - 1);
        
        $global_current += $current_sum;
        $global_max += $max_sum;

        // Affichage : Si NULL ou niveau 1, on affiche "-", sinon le niveau
        $passive_display = ($passive_lvl !== null) ? $passive_lvl : "-";
        $active_display  = ($active_lvl !== null) ? $active_lvl : "-";

        $rows_html .= "
            <tr style='border-bottom:1px solid #3e4a56;'>
                <td style='padding:5px;'>{$u['nom']}</td>
                <td style='padding:5px; text-align:center;'>{$talents_count}/5</td>
                <td style='padding:5px; text-align:center;'>{$passive_display}</td>
                <td style='padding:5px; text-align:center;'>{$active_display}</td>
                <td style='padding:5px; text-align:right; font-weight:bold; color:#1abc9c;'>" . round(($current_sum / $max_sum) * 100) . "%</td>
            </tr>";
    }

    $global_percent = ($global_max > 0) ? round(($global_current / $global_max) * 100) : 0;

    echo "<div class='stats-sidebar' style='background: #2c3e50; padding: 15px; border-radius: 8px; color: #fff; width: 100%;'>";
    echo "<h4 style='margin-top:0;'>Progression Officiers</h4>";
    echo "<div style='font-size: 2em; text-align:center; margin-bottom:10px; color:#1abc9c;'>{$global_percent}%</div>";
    echo "<table style='width:100%; font-size:0.8em; border-collapse:collapse;'>
            <thead>
                <tr style='color:#bdc3c7;'>
                    <th style='text-align:left;'>Nom</th>
                    <th>Talent</th><th>Pass</th><th>Act</th><th>%</th>
                </tr>
            </thead>
            <tbody>{$rows_html}</tbody>
        </table>";
    echo "</div>";
}


/**
 * Sidebar de progression des Héros : % global (niveau + 3 capacités confondus),
 * puis une ligne par héros avec son propre % combiné.
 * Contrairement aux Officiers, pas de talents ici — juste le niveau du héros
 * (plafonné par le QG) + ses 3 capacités (plafonnées par officer_abilities).
 */
function renderHeroesStatsSidebar($heros_list) {
    if (empty($heros_list)) return;

    $global_current = 0;
    $global_max = 0;
    $rows_html = "";

    foreach ($heros_list as $u) {
        $nom         = htmlspecialchars($u['nom'] ?? '???');
        $lvl_current = (int)($u['niveau_joueur'] ?? 1);
        $lvl_max     = (int)($u['niveau_autorise'] ?? 1);

        $abilities_current = 0;
        $abilities_max = 0;
        foreach ($u['hero_abilities'] ?? [] as $ab) {
            $abilities_current += (int)($ab['current_level'] ?? 1);
            // Le vrai plafond d'une capacité n'est PAS forcément MAX(Niveau)+1 : certaines
            // capacités ont, après leur dernier palier payant, une ou plusieurs lignes
            // "terminateurs" à UpgradeCost = 0 (parfois avec un Niveau élevé) qui ne sont pas de
            // vrais paliers. getAbilityRealMaxLevel() simule la progression et s'arrête au bon
            // endroit, pour correspondre exactement à ce que "Niveau max" affiche réellement.
            $abilities_max += getAbilityRealMaxLevel($ab['levels'] ?? []);
        }

        $current_sum = $lvl_current + $abilities_current;
        $max_sum     = $lvl_max + $abilities_max;
        $percent     = ($max_sum > 0) ? round(($current_sum / $max_sum) * 100) : 0;

        $global_current += $current_sum;
        $global_max     += $max_sum;

        $color = ($current_sum >= $max_sum) ? '#2ecc71' : '#1abc9c';

        $rows_html .= "
            <tr style='border-bottom:1px solid #3e4a56;'>
                <td style='padding:6px 4px;'>{$nom}</td>
                <td style='padding:6px 4px; text-align:center; color:#bdc3c7;'>{$lvl_current}/{$lvl_max}</td>
                <td style='padding:6px 4px; text-align:right; font-weight:bold; color:{$color};'>{$percent}%</td>
            </tr>";
    }

    $global_percent = ($global_max > 0) ? round(($global_current / $global_max) * 100) : 0;

    echo "<div class='stats-sidebar' style='background: #2c3e50; padding: 15px; border-radius: 8px; color: #fff; width: 100%;'>";
    echo "<h4 style='margin-top:0; border-bottom: 2px solid #1abc9c; padding-bottom: 5px;'>Progression Héros</h4>";
    echo "<div style='font-size: 2em; text-align:center; margin-bottom:10px; color:#1abc9c;'>{$global_percent}%</div>";
    echo "<table style='width:100%; font-size:0.85em; border-collapse:collapse;'>
            <thead>
                <tr style='color:#bdc3c7;'>
                    <th style='text-align:left;'>Héros</th>
                    <th>Niveau</th>
                    <th style='text-align:right;'>%</th>
                </tr>
            </thead>
            <tbody>{$rows_html}</tbody>
        </table>";
    echo "</div>";
}


/**
 * Rendu de l'onglet Monument Mystique
 */
function renderMysticMonument($current_mm_level, $all_bonuses, $user_bonuses) {
    echo "
    <div class='monument-section-wrapper' style='font-family: \"Montserrat\", sans-serif; max-width: 1200px; margin: 0 auto;'>
        <h2 style='font-family: \"Bangers\", cursive; font-size: 2.2em; color: #f1c40f; margin-bottom: 20px; letter-spacing: 1px;'>🗿 Monument Mystique</h2>
        
        <div class='monument-top-card' style='background: #34495e; padding: 20px; border-radius: 8px; margin-bottom: 25px; display: flex; align-items: center; gap: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.05);'>
            <div style='background: rgba(0,0,0,0.2); padding: 10px; border-radius: 8px; display: flex; align-items: center; justify-content: center;'>
                <img src='images/mystic_monument.png' alt='Monument Mystique' style='width: 90px; height: 90px; object-fit: contain;' onerror=\"this.src='images/default-building.png'\">
            </div>
            <div style='flex-grow: 1;'>
                <label for='mm_global_level' style='display: block; font-weight: 600; font-size: 1.15em; margin-bottom: 8px; color: #ecf0f1;'>Quel est votre niveau de monument ?</label>
                <div style='display: flex; align-items: center; gap: 12px;'>
                    <input type='number' id='mm_global_level' value='{$current_mm_level}' min='0' max='800' 
                           style='padding: 10px; border-radius: 4px; border: 2px solid #2c3e50; background: #2c3e50; color: #fff; font-size: 1.1em; width: 110px; font-weight: bold; text-align: center;'
                           onchange='updateMonumentLevel(this.value)'>
                    <span style='color: #bdc3c7; font-size: 0.95em;'>(Sauvegarde instantanée — Max 800)</span>
                </div>
            </div>
        </div>

        <table class='monument-bonus-table' style='width: 100%; border-collapse: collapse; background: #34495e; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.2);'>
            <thead>
                <tr style='background: #e67e22; color: white;'>
                    <th style='padding: 14px; text-align: left; font-size: 0.95em;'>Bonus</th>
                    <th style='padding: 14px; text-align: center; font-size: 0.95em; width: 180px;'>Niveau MM Requis</th>
                    <th style='padding: 14px; text-align: right; font-size: 0.95em; width: 180px;'>Nombre de bonus</th>
                </tr>
            </thead>
            <tbody>";

    // ICI : La seule et unique boucle pour afficher les lignes
    foreach ($all_bonuses as $bonus) {
        $id_b = (int)$bonus['id_bonus'];
        $min_lvl = (int)$bonus['MinBuildingLevel'];
        $max_count = isset($bonus['MaxCount']) ? (int)$bonus['MaxCount'] : 0;
        $display_name = !empty($bonus['FR']) ? $bonus['FR'] : $bonus['TID'];
        $qty = $user_bonuses[$id_b] ?? 0;
        
        $is_locked = ($current_mm_level < $min_lvl);
        $row_class = $is_locked ? "mm-bonus-row bonus-locked" : "mm-bonus-row";
        $disabled_attr = $is_locked ? "disabled" : "";

        echo "
                <tr class='{$row_class}' data-min-mm-lvl='{$min_lvl}' style='border-bottom: 1px solid rgba(255,255,255,0.05); transition: background-color 0.2s ease, opacity 0.3s ease;'>
                    <td style='padding: 14px; font-weight: 600; color: #fff;'>✨ " . htmlspecialchars($display_name) . "</td>
                    <td style='padding: 14px; text-align: center; font-weight: bold;' class='mm-req-cell'>Niveau {$min_lvl}</td>
                    <td style='padding: 14px; text-align: right;'>
                        <input type='number' 
                               value='{$qty}' 
                               min='0' 
                               max='{$max_count}' 
                               class='number monument-qty-field'
                               {$disabled_attr}
                               onchange='updateMonumentBonus({$id_b}, this.value, {$max_count})'
                               style='padding: 8px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); background: #2c3e50; color: #fff; width: 90px; text-align: right; font-weight: bold;'>
                    </td>
                </tr>";
    }

    echo "
            </tbody>
        </table>
    </div>";
}

/**
 * Rendu de l'onglet Gravures séparé par catégories (Offensive / Defensive).
 * Reprend exactement le même design de carte que les Troupes (.troop-card),
 * avec un coût unique en Jetons de recherche (au lieu de Or/Temps) et un
 * tableau détaillé des coûts par niveau, repliable sous la carte.
 */
/**
 * Insère, pour un joueur donné, une ligne "placeholder" (niveau=0, Debloque=0)
 * dans progress_tribs pour chaque tribu qui devient éligible à un nouveau niveau
 * de Radar (Salle des cartes, TID_BUILDING_MAP_ROOM / id_building 14), si cette
 * ligne n'existe pas déjà. Le joueur devra ensuite cliquer sur "Débloquer" dans
 * la page Tribus pour faire passer Debloque à 1 (voir upgrade_tribs.php).
 *
 * À appeler juste après avoir persisté une amélioration du Radar dans
 * progress_building, par exemple dans upgrade_building.php :
 *
 *     if ((int)$id_building === 14) {
 *         seedTribusUnlockRows($pdo, $id_player, $target_level);
 *     }
 */
function seedTribusUnlockRows($pdo, $id_player, $new_radar_level) {
    try {
        $stmt_eligible = $pdo->prepare("SELECT id FROM tribsid WHERE RadarLvlReq <= ?");
        $stmt_eligible->execute([(int)$new_radar_level]);
        $eligible_ids = $stmt_eligible->fetchAll(PDO::FETCH_COLUMN);

        if (empty($eligible_ids)) return;

        $stmt_check  = $pdo->prepare("SELECT 1 FROM progress_tribs WHERE id_player = ? AND id_trib = ?");
        $stmt_insert = $pdo->prepare("INSERT INTO progress_tribs (id_player, id_trib, niveau, Debloque) VALUES (?, ?, 0, 0)");

        foreach ($eligible_ids as $id_trib) {
            $stmt_check->execute([$id_player, $id_trib]);
            if (!$stmt_check->fetch()) {
                $stmt_insert->execute([$id_player, $id_trib]);
            }
        }
    } catch (PDOException $e) {
        error_log("Erreur seedTribusUnlockRows : " . $e->getMessage());
    }
}

/**
 * Rendu de la page "Tribus". Chaque tribu se débloque à partir d'un niveau
 * de Radar (Salle des cartes, TID_BUILDING_MAP_ROOM / id_building 14) donné
 * par tribsid.RadarLvlReq (voir queries.php, bloc 13). Coût en Cristaux bruts
 * + temps, comme les bâtiments — d'où la réutilisation du design 'building-card'.
 *
 * Deux états bien distincts :
 *   - radar_ok = false  -> Radar pas encore assez haut, carte grisée "Verrouillé"
 *   - radar_ok = true, debloque = false -> carte visible, bouton "Débloquer"
 *   - debloque = true   -> tribu active, bouton "Améliorer" (ou "Max !")
 */
function renderTribusTable($tribus_list) {
    if (empty($tribus_list)) {
        echo "<p class='empty-msg'>Aucune tribu n'a été trouvée.</p>";
        return;
    }

    renderHideMaxedToggle('les tribus');

    echo "<div class='buildings-grid hide-maxed-container'>";

    foreach ($tribus_list as $trb) {
        $id_trib  = (int)($trb['id_trib'] ?? 0);
        $tid      = $trb['TID'] ?? '';

        $nom_raw  = $trb['nom'] ?? $tid;
        $nom      = htmlspecialchars(is_array($nom_raw) ? reset($nom_raw) : $nom_raw);

        $icon_raw = $trb['IconExportName'] ?? 'default-building';
        $icon     = htmlspecialchars(is_array($icon_raw) ? reset($icon_raw) : $icon_raw);

        $niv      = (int)($trb['niveau_actuel'] ?? 0);
        $max      = (int)($trb['niveau_max'] ?? 5);

        $radar_req    = (int)($trb['RadarLvlReq'] ?? 0);
        $radar_actuel = (int)($trb['radar_actuel'] ?? 0);
        $radar_ok     = !empty($trb['radar_ok']);
        $debloque     = !empty($trb['debloque']);
        $is_maxed     = ($debloque && $niv >= $max);
        $maxed_attr   = $is_maxed ? "1" : "0";

        $costs     = $trb['costs'] ?? [];
        $next_lvl  = $niv + 1;
        $next_cost = $costs[$next_lvl] ?? null;

        $safe_id      = "trb-" . preg_replace('/[^a-zA-Z0-9]/', '', $tid) . "-{$id_trib}";
        $display_text = !$debloque ? "Non débloquée" : "Niveau {$niv}";
        $locked_class = !$radar_ok ? ' unit-locked' : '';

        echo "
        <div class='building-card{$locked_class}' id='card-{$safe_id}' data-tid='{$tid}' data-id-trib='{$id_trib}' data-maxed='{$maxed_attr}'>
            <div class='building-card-visual'>
                <img class='building-card-img' src='images/{$icon}.webp' alt='{$nom}' onerror=\"this.src='images/default-building.png'\">
            </div>

            <div class='building-card-info'>
                <span class='building-card-name'>{$nom}</span>
                <span class='building-card-level' id='lvl-{$safe_id}'>{$display_text} / {$max}</span>
            </div>";

        if (!$radar_ok) {
            echo "
            <div class='troop-card-requirement' style='color:#e74c3c; font-size:0.85em; font-weight:600; margin:8px 0; text-align:center;'>
                🔒 Radar niv. {$radar_req} requis (actuel : niv. {$radar_actuel})
            </div>";
        } elseif ($debloque && !$is_maxed && $next_cost) {
            $temps_txt = formatUnitsTime(($next_cost['time_min'] ?? 0) / 60);
            echo "
            <div class='building-card-costs'>
                <span class='building-cost-item'><img class='building-cost-icon' src='images/icons/Raw Crystals.png' alt='Cristaux bruts' onerror=\"this.src='images/default.png'\">{$next_cost['cost']}</span>
            </div>
            <div class='building-card-time'>
                <img class='building-time-icon' src='images/icons/Time Icon.png' alt='Temps'>{$temps_txt}
            </div>";
        }

        if (!$radar_ok) {
            $disabled = "disabled";
            $btn_text = "Verrouillé";
        } elseif ($is_maxed) {
            $disabled = "disabled";
            $btn_text = "Max !";
        } elseif (!$debloque) {
            $disabled = "";
            $btn_text = "Débloquer";
        } else {
            $disabled = "";
            $btn_text = "Améliorer";
        }

        echo "
            <div class='building-card-action'>
                <button class='btn-upgrade' {$disabled}
                        onclick=\"triggerUpgradeTribu({$id_trib}, '{$safe_id}', {$max})\">
                    <span class='btn-text'>{$btn_text}</span>
                </button>
            </div>
        </div>";
    }

    echo "</div>";
}

function renderEngravingsTable($engravings, $cat_color = "#1abc9c") {
    if (empty($engravings)) {
        echo "<p class='empty-msg'>Aucune gravure n'a été trouvée dans cette catégorie.</p>";
        return;
    }

    renderHideMaxedToggle('les gravures');

    echo "<div class='troops-grid hide-maxed-container'>";

    foreach ($engravings as $e) {
        $id_engraving = (int)($e['id_engraving'] ?? 0);
        $tid = $e['TID'] ?? '';

        // Sécurité : si 'nom' est un tableau, on prend le premier élément ou une chaîne vide
        $nom_raw = $e['nom'] ?? $tid;
        $nom = htmlspecialchars(is_array($nom_raw) ? reset($nom_raw) : $nom_raw);

        // Sécurité pour l'icône
        $icon_raw = $e['IconExportName'] ?? $e['ExportName'] ?? 'default';
        if (is_array($icon_raw)) {
            $icon_raw = reset($icon_raw);
        }
        $icon = htmlspecialchars($icon_raw);

        // Sécurité pour le Type
        $type_raw = $e['Type'] ?? 'Inconnu';
        $type = htmlspecialchars(is_array($type_raw) ? implode(', ', $type_raw) : $type_raw);

        $niv     = (int)($e['niveau_actuel'] ?? 0);
        $max     = (int)($e['niveau_max'] ?? 10);
        $is_maxed = ($niv >= $max);
        $maxed_attr = $is_maxed ? "1" : "0";

        // Coûts en Jetons de recherche, indexés par palier de qualité : [1 => 50, 2 => 120, ...]
        $costs = $e['costs'] ?? [];

        $next_lvl  = $niv + 1;
        $next_cost = $costs[$next_lvl] ?? null;
        $cost_display = ($next_cost !== null) ? $next_cost : "Max";

        // Création d'un ID valide pour le HTML, basé sur l'id numérique (utilisé pour l'upgrade)
        $safe_id = "eng-" . preg_replace('/[^a-zA-Z0-9]/', '', $tid) . "-{$id_engraving}";

        $display_text = ($niv === 0) ? "Non débloqué" : "Niveau {$niv} / {$max}";
        $btn_text = $is_maxed ? "Max !" : (($niv === 0) ? "Débloquer" : "Améliorer");

        echo "
        <div class='troop-card' id='card-{$safe_id}' data-tid='{$tid}' data-id-engraving='{$id_engraving}' data-maxed='{$maxed_attr}' style='border-top: 3px solid {$cat_color};'>
            <div class='troop-card-visual'>
                <img class='troop-card-img' src='images/engravings/{$icon}.png' alt='{$nom}' onerror=\"this.src='images/engravings/{$icon}.webp'\">
            </div>

            <div class='troop-card-info'>
                <span class='troop-card-name'>{$nom}</span>
                <span style='font-size: 0.8em; color: #bdc3c7; background: rgba(255,255,255,0.1); padding: 2px 8px; border-radius: 12px;'>Type : {$type}</span>
                <span class='troop-card-level' id='lvl-{$safe_id}'>{$display_text}</span>
            </div>";

        if (!$is_maxed) {
            echo "
            <div class='troop-card-costs'>
                <span class='troop-cost-item'>
                    <img class='troop-cost-icon' src='images/engravings/Epic_research_token.webp' alt='Jetons de recherche' onerror=\"this.src='images/default.png'\">
<span class='cost-value' id='cost-{$safe_id}' data-costs='" . htmlspecialchars(json_encode($costs)) . "'>" . ($next_cost !== null ? $next_cost : "Max") . "</span>                    <span style='font-weight:400; color:#bdc3c7;'>Jetons de recherche</span>
                </span>
            </div>";
        }

        $disabled = $is_maxed ? "disabled" : "";
        echo "
            <div class='troop-card-action'>
                <button class='btn-upgrade' {$disabled}
                        onclick=\"triggerUpgradeEngraving({$id_engraving}, '{$safe_id}', {$max}, '{$nom}', {$niv})\">
                    <span class='btn-text'>{$btn_text}</span>
                </button>
            </div>";

        echo "
        </div>";
    }

    echo "</div>";
}

/**
 * Sidebar de progression pour les Gravures (Offensive/Defensive)
 * Affiche le % basé sur les niveaux, la liste des gravures, et le coût total restant en Jetons de recherche
 */
function renderEngravingsStatsSidebar($title, $engravings_list, $stats) {
    if (empty($engravings_list)) return;

    $total_cost = 0;
    $rows_html = "";

    foreach ($engravings_list as $e) {
        $nom = htmlspecialchars($e['nom'] ?? '???');
        $cur = (int)($e['niveau_actuel'] ?? 0);
        $max = (int)($e['niveau_max'] ?? 1);

        // Calcul du coût restant pour cette gravure
        $remaining_cost = 0;
        if (!empty($e['costs']) && $cur < $max) {
            for ($lvl = $cur + 1; $lvl <= $max; $lvl++) {
                $remaining_cost += $e['costs'][$lvl] ?? 0;
            }
        }
        $total_cost += $remaining_cost;

        $color = ($cur >= $max) ? '#2ecc71' : '#f1c40f';
        $rows_html .= "<li style='padding: 5px 0; border-bottom: 1px solid #3e4a56;'>
                {$nom}
                <span style='float:right; color:{$color};'>{$cur}/{$max}</span>
              </li>";
    }

    $percent = $stats['percent'] ?? 0;

    echo "<div class='stats-sidebar' style='background: #2c3e50; padding: 15px; border-radius: 8px; color: #fff; border: 1px solid #456789;'>";
    echo "<h4 style='margin: 0 0 10px 0; border-bottom: 2px solid #e74c3c; padding-bottom: 5px;'>{$title}</h4>";
    echo "<div style='font-size: 1.8em; font-weight: bold; color: #e74c3c; text-align: center; margin-bottom: 10px;'>{$percent}%</div>";
    echo "<ul style='list-style: none; padding: 0; margin: 0; font-size: 0.85em;'>{$rows_html}</ul>";

    if ($total_cost > 0) {
        echo "<div class='sidebar-total-remaining'>
                <div class='sidebar-total-title'>Jetons de recherche restants</div>
                <div class='sidebar-total-costs'>
                    <span class='sidebar-total-item'><img src='images/icons/Proto_Token.png' alt='Jetons' style='width:20px;'>" . number_format($total_cost, 0, ',', ' ') . "</span>
                </div>
              </div>";
    }

    echo "</div>";
}
/* ==========================================================================
   TABLEAU DE BORD PRINCIPAL — Cartes "graph-card" façon ARCTracker
   ========================================================================== */

/**
 * Rendu du Tableau de Bord principal (onglet "Dashboard") : une grille de
 * "graph-card" résumant la progression des grandes catégories du jeu.
 *
 * $stats_buildings et $stats_troupes attendent un tableau ['current' => int, 'max' => int, 'percent' => float]
 * (ex. retour de getCategoryStats()).
 */
function renderMainDashboard(
    $stats_buildings, $stats_res, $stats_def, $stats_army, $stats_trap,
    $stats_troupes, $stats_proto, $stats_heros, $stats_officiers_capa, $chefs_debloques, $chefs_total,
    $stats_capacanon,
    $stats_gravures, $stats_gravures_off, $stats_gravures_def,
    $stats_tribus, $stats_monument,
    $qg = null
) {
    // % global "Armée" : agrégation de Troupes, Proto-troupes, Héros, Capacités des Chefs et Capacités de canonnière
    $armee_current = $stats_troupes['current'] + $stats_proto['current'] + $stats_heros['current'] + $stats_officiers_capa['current'] + $stats_capacanon['current'];
    $armee_max     = $stats_troupes['max'] + $stats_proto['max'] + $stats_heros['max'] + $stats_officiers_capa['max'] + $stats_capacanon['max'];
    $armee_percent = ($armee_max > 0) ? round(($armee_current / $armee_max) * 100, 1) : 0;

    echo "<div class='dashboard-container'><h2>Tableau de Bord</h2>";

    // ================= SECTION BÂTIMENTS =================
    // "X / Y bâtiments débloqués" : X = types de bâtiments (TID distincts) débloqués
    // au QG actuel du joueur, Y = nombre total de types existant dans la catégorie
    // (tous QG confondus, jusqu'à la fin du jeu).
    echo "<div class='dash-section'>";
    renderDashSectionHeader('🏗️', 'Bâtiments', $stats_buildings['percent'], 'Building-Overview');
    echo "<div class='dash-subcards-row'>";
    echo renderDashSubcard('🏦', 'Économie', $stats_res['current'], $stats_res['max'], $stats_res['percent'], "{$stats_res['types_debloques']} / {$stats_res['types_total']} bâtiments débloqués", 'Building-Ressource', false, false, $stats_res['percent_absolu']);
    echo renderDashSubcard('🛡️', 'Défense', $stats_def['current'], $stats_def['max'], $stats_def['percent'], "{$stats_def['types_debloques']} / {$stats_def['types_total']} bâtiments débloqués", 'Building-Defense', false, false, $stats_def['percent_absolu']);
    echo renderDashSubcard('🏰', 'Renfort', $stats_army['current'], $stats_army['max'], $stats_army['percent'], "{$stats_army['types_debloques']} / {$stats_army['types_total']} bâtiments débloqués", 'Building-Army', false, false, $stats_army['percent_absolu']);
    echo renderDashSubcard('💣', 'Pièges', $stats_trap['current'], $stats_trap['max'], $stats_trap['percent'], "{$stats_trap['types_debloques']} / {$stats_trap['types_total']} bâtiments débloqués", 'Building-Trap', false, false, $stats_trap['percent_absolu']);
    echo "</div></div>";

    // ================= SECTION ARMÉE =================
    // Toutes les cartes sur UNE seule rangée (Troupes / Proto-troupes / Héros / Capacité de
    // canonnière / Chefs de bataillon désormais alignés ensemble, carte carrée comme les autres
    // — elle n'est plus en pleine largeur).
    echo "<div class='dash-section'>";
    renderDashSectionHeader('⚔️', 'Armée', $armee_percent, 'Army-Overview');

    echo "<div class='dash-subcards-row'>";
    echo renderDashSubcard('🪖', 'Troupes', $stats_troupes['current'], $stats_troupes['max'], $stats_troupes['percent'], round($stats_troupes['percent']) . '% de progression', 'Character-Troop');
    echo renderDashSubcard('🧪', 'Proto-troupes', $stats_proto['current'], $stats_proto['max'], $stats_proto['percent'], round($stats_proto['percent']) . '% de progression', 'Character-Proto');
    echo renderDashSubcard('🦸', 'Héros', $stats_heros['current'], $stats_heros['max'], $stats_heros['percent'], round($stats_heros['percent']) . '% de progression', 'Character-Hero');
    echo renderDashSubcard('🚤', 'Capacité de canonnière', $stats_capacanon['current'], $stats_capacanon['max'], $stats_capacanon['percent'], round($stats_capacanon['percent']) . '% de progression', 'Character-Spell');
    echo renderDashSubcard(
        '🎖️',
        'Chefs de bataillon',
        $stats_officiers_capa['current'],
        $stats_officiers_capa['max'],
        $stats_officiers_capa['percent'],
        "{$chefs_debloques} / {$chefs_total} chefs débloqués",
        'Character-Leader'
    );
    echo "</div>";
    echo "</div>";

    // ================= SECTION GRAVURES =================
    echo "<div class='dash-section'>";
    renderDashSectionHeader('🪶', 'Gravures', $stats_gravures['percent'], 'Engraving-Overview');
    echo "<div class='dash-subcards-row'>";
    echo renderDashSubcard('🗡️', 'Gravures Offensives', $stats_gravures_off['current'], $stats_gravures_off['max'], $stats_gravures_off['percent'], "{$stats_gravures_off['current']} / {$stats_gravures_off['max']} débloquées", 'Engraving-Offensive');
    echo renderDashSubcard('🛡️', 'Gravures Défensives', $stats_gravures_def['current'], $stats_gravures_def['max'], $stats_gravures_def['percent'], "{$stats_gravures_def['current']} / {$stats_gravures_def['max']} débloquées", 'Engraving-Defensive');
    echo "</div></div>";

    // ================= SECTION AUTRES =================
    echo "<div class='dash-section'>";
    renderDashSectionHeader('✨', 'Autres');
    echo "<div class='dash-subcards-row'>";
    echo renderDashSubcard('🏝️', 'Tribus', $stats_tribus['current'], $stats_tribus['max'], $stats_tribus['percent'], "{$stats_tribus['debloquees']} tribus débloquées, dont {$stats_tribus['total']} max", 'Tribes');
    echo renderDashSubcard('🗿', 'Monument mystique', $stats_monument['level'], $stats_monument['max_level'], $stats_monument['percent'], "{$stats_monument['bonus_obtenus']} / {$stats_monument['bonus_total']} bonus obtenus", 'Monument');
    echo "</div></div>";

    echo "</div>"; // .dashboard-container
}

/**
 * En-tête de section du Tableau de Bord : titre + (optionnellement) un pourcentage
 * global et sa barre de progression. Cliquable si $target_tab est fourni.
 */
function renderDashSectionHeader($icon, $title, $percent = null, $target_tab = null) {
    $onclick_attr = $target_tab ? " onclick=\"showTab('{$target_tab}')\"" : "";
    $header_class = $target_tab ? 'dash-section-header clickable' : 'dash-section-header';
    $arrow_html   = $target_tab ? "<span class='dash-section-arrow'>›</span>" : "";

    $percent_html = "";
    $track_html   = "";
    if ($percent !== null) {
        $percent_safe = max(0, min(100, (float)$percent));
        $percent_html = "<div class='dash-section-percent'>{$percent_safe}%</div>";
        $track_html   = "<div class='dash-section-track'><div class='dash-section-fill' style='width: {$percent_safe}%;'></div></div>";
    }

    echo "
    <div class='{$header_class}'{$onclick_attr}>
        <div class='dash-section-title'><span class='dash-section-icon'>{$icon}</span><span>" . htmlspecialchars($title) . "</span></div>
        <div style='display:flex; align-items:center;'>{$percent_html}{$arrow_html}</div>
    </div>
    {$track_html}";
}

/**
 * Carte compacte utilisée dans les sous-sections du Tableau de Bord
 * (ex: Économie/Défense/Renfort sous "Bâtiments"). Même logique que
 * renderOverviewCard, mais gabarit plus petit pensé pour être groupé par section.
 */
/**
 * Carte compacte utilisée dans les sous-sections du Tableau de Bord
 * (ex: Économie/Défense/Renfort sous "Bâtiments"). Reprend le style visuel des
 * cartes "Succès" du jeu : icône qui déborde en haut de la carte, titre en
 * dessous, une div d'info, puis une barre de progression AVEC le chiffre à
 * l'intérieur, et à la place de la "Prime" du jeu, le %age vers le "max du max"
 * (= le plafond de fin de jeu, indépendant du QG actuel — voir $percent_absolu).
 *
 * $percent_absolu : si null, retombe sur $percent (cas des sections qui n'ont pas
 * de notion de plafond "au-delà du QG actuel", ex. gravures, dont le niveau_max
 * est déjà le vrai plafond du jeu).
 */
function renderDashSubcard($icon, $title, $current, $max, $percent, $subtitle, $target_tab = null, $disabled = false, $full_width = false, $percent_absolu = null) {
    $classes = 'dash-subcard';
    if ($full_width) $classes .= ' full-width';
    if ($disabled) $classes .= ' disabled';
    if ($target_tab && !$disabled) $classes .= ' clickable';

    $onclick_attr   = ($target_tab && !$disabled) ? " onclick=\"showTab('{$target_tab}')\"" : "";
    $percent_safe   = max(0, min(100, (float)$percent));
    $percent_abs_safe = ($percent_absolu !== null) ? max(0, min(100, (float)$percent_absolu)) : $percent_safe;

    $value_display  = $disabled ? "—" : "{$current}/{$max}";
    $percent_col_html = $disabled
        ? "<span class='dash-subcard-badge'>Bientôt</span>"
        : "<span class='dash-subcard-percent-label'>Complétion</span><span class='dash-subcard-percent-value'>{$percent_abs_safe}%</span>";

    return "
        <div class='{$classes}'{$onclick_attr}>
            <div class='dash-subcard-icon-badge'>{$icon}</div>
            <div class='dash-subcard-title'>" . htmlspecialchars($title) . "</div>
            <div class='dash-subcard-info'>" . htmlspecialchars($subtitle) . "</div>
            <div class='dash-subcard-bottom-row'>
                <div class='dash-subcard-progress-col'>
                    <div class='dash-subcard-track'>
                        <div class='dash-subcard-fill' style='width: {$percent_safe}%;'></div>
                        <span class='dash-subcard-track-label'>{$value_display}</span>
                    </div>
                </div>
                <div class='dash-subcard-percent-col'>{$percent_col_html}</div>
            </div>
        </div>";
}

/**
 * Construit une "graph-card" individuelle du Tableau de Bord.
 * Si $target_tab est fourni (et la carte non désactivée), un clic sur la carte
 * navigue directement vers l'onglet correspondant (showTab côté JS).
 */
function renderOverviewCard($icon, $title, $current, $max, $percent, $subtitle, $target_tab = null, $disabled = false) {
    $disabled_class = $disabled ? ' disabled' : '';
    $onclick_attr   = ($target_tab && !$disabled) ? " onclick=\"showTab('{$target_tab}')\"" : "";
    $soon_badge     = $disabled ? "<span class='badge-soon'>Bientôt</span>" : "";
    $value_display  = $disabled ? "—" : "{$current}<span class='value-max'> / {$max}</span>";
    $percent_safe   = max(0, min(100, (float)$percent));

    return "
        <div class='overview-card{$disabled_class}'{$onclick_attr}>
            <div class='overview-card-header'>{$icon}
                <div class='overview-card-title'>" . htmlspecialchars($title) . "</div>
                {$soon_badge}
            </div>
            <div class='overview-card-value'>{$value_display}</div>
            <div class='overview-progress-track'>
                <div class='overview-progress-fill' style='width: {$percent_safe}%;'></div>
            </div>
            <div class='overview-card-sub'>" . htmlspecialchars($subtitle) . "</div>
        </div>";
}

/**
 * Rendu d'une page "mini-onglets" (cartes de navigation) pour les menus
 * possédant un sous-menu (Bâtiments, Armée, Gravures). Chaque carte envoie
 * directement vers le sous-onglet correspondant via showTab().
 *
 * $items est un tableau de ['label' => string, 'tab' => string, 'icon' => string (optionnel), 'sub' => string (optionnel)]
 */
function renderCategoryNav($title, $items) {
    echo "<div class='category-nav-wrapper'>";
    echo "<h2>" . htmlspecialchars($title) . "</h2>";
    echo "<div class='category-nav-grid'>";

    foreach ($items as $item) {
        $label = htmlspecialchars($item['label'] ?? '');
        $sub   = htmlspecialchars($item['sub'] ?? '');
        $tab   = $item['tab'] ?? '';
        $icon  = $item['icon'] ?? '📁';

        echo "
        <div class='category-nav-card' onclick=\"showTab('{$tab}')\">
            <div class='category-nav-card-main'>
                <div class='overview-card-icon'>{$icon}</div>
                <div>
                    <div class='category-nav-card-label'>{$label}</div>";
        if ($sub !== '') {
            echo "<div class='category-nav-card-sub'>{$sub}</div>";
        }
        echo "
                </div>
            </div>
            <div class='category-nav-card-arrow'>›</div>
        </div>";
    }

    echo "</div></div>";
}