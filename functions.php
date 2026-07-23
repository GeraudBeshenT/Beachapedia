<?php

/**
 * Formate un nombre de coût (Or, Bois, Cristaux, etc.) avec séparateur de milliers,
 * selon la langue actuellement sélectionnée ($selected_lang, définie dans queries.php) :
 *   - FR : espace comme séparateur de milliers -> 1 250 000
 *   - toute autre langue (EN, DE, ...) : point comme séparateur de milliers -> 1.250.000
 * Aucune décimale affichée (les coûts du jeu sont toujours des entiers).
 */
function formatCost($n) {
    global $selected_lang;
    $sep = (($selected_lang ?? 'FR') === 'FR') ? ' ' : '.';
    return number_format((float)$n, 0, ',', $sep);
}

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
    <button type='button' class='btn-hide-maxed' data-label='" . htmlspecialchars($label) . "' onclick='toggleHideMaxed(this)'>
        <img class='hide-maxed-icon' src='images/icons/Show.png' alt=''>
        <span class='hide-maxed-label'>Masquer " . htmlspecialchars($label) . " au max</span>
    </button>";
}

/**
 * Sélecteur de tri réutilisable (bâtiments, troupes) : trie les cartes par XP gagné
 * ou par temps d'amélioration du PROCHAIN niveau, croissant ou décroissant. Le JS
 * (sortCards, voir script.js) réordonne les cartes DANS chaque conteneur
 * .hide-maxed-container de l'onglet, en se basant sur les attributs data-xp /
 * data-time posés sur chaque carte (voir renderBuildingsTable / renderUnitsTable).
 * Une carte sans niveau suivant (déjà au max) porte data-xp="-1" / data-time="-1"
 * et est toujours reléguée en fin de liste, quel que soit le sens choisi.
 * L'état est mémorisé par onglet dans localStorage pour survivre au rechargement.
 */
function renderSortControls() {
    echo "
    <label class='sort-cards-control'>
        <span class='sort-cards-label'>Trier par :</span>
        <select class='sort-cards-select' onchange='sortCards(this)'>
            <option value='default'>Par défaut</option>
            <option value='xp-desc'>XP gagné (décroissant)</option>
            <option value='xp-asc'>XP gagné (croissant)</option>
            <option value='time-desc'>Temps d'amélioration (décroissant)</option>
            <option value='time-asc'>Temps d'amélioration (croissant)</option>
        </select>
    </label>";
}

function renderBuildingsTable($buildings_list) {
    echo "<div class='grid-toolbar'>";
    renderHideMaxedToggle('les bâtiments');
    renderSortControls();
    echo "</div>";

    foreach ($buildings_list as $category => $buildings) {
        if (empty($buildings)) continue;

        echo "<div class='buildings-grid hide-maxed-container'>";

        // Compteur d'ordre d'affichage ORIGINAL (avant tout tri), remis à zéro à
        // chaque grille/catégorie : permet à l'option "Par défaut" du tri de
        // restaurer exactement l'ordre initial (voir sortCards dans script.js).
        $order_index = 0;

        foreach ($buildings as $b) {
            // Sécurisation avec ?? pour éviter les Warnings
            $tid      = $b['TID'] ?? '';
            $nom      = htmlspecialchars($b['nom_building'] ?? '???');
            $inst     = (int)($b['id_instance'] ?? 1);
            $niv      = (int)($b['niveau_actuel'] ?? 0);
            // $max_qg  : plafond atteignable avec le QG/Arsenal ACTUEL (peut être < au vrai max table)
            // $max_abs : VRAI plafond, celui de la table entière, indépendant du QG/Arsenal
            $max_qg   = (int)($b['niveau_max'] ?? 1);
            $max_abs  = (int)($b['niveau_max_absolu'] ?? $max_qg);
            $img      = $b['ExportName'] ?? 'default-building';
            $debloque = (int)($b['Debloque'] ?? 0);

            // 🔥 "Niveau max !" ne s'affiche désormais QUE si le VRAI maximum (niveau_max_absolu)
            // est atteint. Si le bâtiment est seulement plafonné par le QG/Arsenal actuel
            // (niv >= max_qg mais niv < max_abs), on garde le bloc "Améliorer" avec ses coûts/
            // temps, et c'est la condition "QG/Arsenal niveau X" qui affiche un cadenas.
            $is_maxed = ($niv >= $max_abs);
            $maxed_attr = $is_maxed ? "1" : "0";

            // Mines (Mine / Super mine / Électromine) : plus de construction à l'or par
            // instance, on recherche un niveau unique à l'Arsenal qui s'applique à toutes
            // les mines posées. Voir MINE_TIDS / getBuildingsDisplay dans queries.php.
            $is_mine          = !empty($b['is_mine']);

            // Niveau réel actuel (QG ou Arsenal selon la catégorie) qui gate ce bâtiment,
            // utilisé uniquement pour savoir si l'icône doit être un cadenas ouvert ou fermé.
            $gating_level_actuel = (int)($b['gating_level_actuel'] ?? 0);

            // L'instance reste purement interne (data-instance), jamais affichée à l'écran
            $safe_id = "bld-" . preg_replace('/[^a-zA-Z0-9]/', '', $tid) . "-{$inst}";

            // Libellé du bouton
            $btn_text = ($debloque === 0) ? "Construire au niveau 1" : "Améliorer au niveau " . ($niv + 1);

            $display_text = ($niv === 0) ? "Non construit" : "Niveau {$niv}";

            // Bouton rétrograder : toujours affiché sous la carte, logique métier à brancher plus tard
            $niv_precedent = max(0, $niv - 1);
            $downgrade_disabled = ($niv <= 0) ? 'disabled' : '';

            // Attributs de tri (voir renderSortControls / sortCards) : XP gagné et temps
            // d'amélioration du PROCHAIN niveau. -1 = pas de niveau suivant (déjà au VRAI
            // max), toujours relégué en fin de liste quel que soit le sens de tri choisi.
            // 'XpGain' (et les colonnes BuildTime*) ne sont posées dans $b (voir
            // getBuildingsDisplay) QUE si un niveau suivant existe, d'où ce test.
            $xp_attr_bld = isset($b['XpGain']) && $b['XpGain'] !== null ? (int)$b['XpGain'] : -1;
            $time_attr_bld = isset($b['XpGain']) && $b['XpGain'] !== null
                ? ((int)($b['BuildTimeD'] ?? 0) * 86400 + (int)($b['BuildTimeH'] ?? 0) * 3600 + (int)($b['BuildTimeM'] ?? 0) * 60 + (int)($b['BuildTimeS'] ?? 0))
                : -1;

            echo "
            <div class='building-card' id='card-{$safe_id}' data-tid='{$tid}' data-instance='{$inst}' data-maxed='{$maxed_attr}' data-xp='{$xp_attr_bld}' data-time='{$time_attr_bld}' data-order='{$order_index}'>

                <div class='building-card-info'>
                    <span class='building-card-name'>{$nom}</span>
                    <span class='building-card-level' id='lvl-{$safe_id}'>{$display_text}</span>
                </div>

                <div class='building-card-visual'>
                    <img class='building-card-img' src='images/{$img}.webp' alt='{$nom}' onerror=\"this.src='images/default-building.png'\">
                </div>

                <div class='building-card-body'>";

            if ($is_maxed) {
                echo "
                    <span class='building-card-maxed'>Niveau max atteint</span>";
            } else {
                $is_trap = (($b['Class'] ?? '') === 'Trap');
                $temps_seconds = (int)($b['BuildTimeD'] ?? 0) * 86400
                                + (int)($b['BuildTimeH'] ?? 0) * 3600
                                + (int)($b['BuildTimeM'] ?? 0) * 60
                                + (int)($b['BuildTimeS'] ?? 0);
                // Pièges/Mines s'améliorent via l'Arsenal -> bonus ArmoryBoost, tous les
                // autres bâtiments (Économiques/Défensifs/Support) -> bonus BuildingBoost.
                $temps_txt = formatSecondsToText($temps_seconds, $is_trap ? 'armory' : 'building');

                // Condition informative "QG niveau X" (bâtiments classiques) / "Arsenal niveau X"
                // (Pièges + Mines), affichée entre le titre et les coûts, avec un cadenas
                // ouvert/fermé selon que le QG/Arsenal actuel du joueur satisfait ou non cette
                // condition pour le PROCHAIN palier.
                $required_level_bat = (int)($b['required_level'] ?? 0);
                $required_label_bat = $is_trap ? 'Arsenal' : 'QG';

                echo "
                    <span class='building-card-upgrade-title'>Améliorer au niveau " . ($niv + 1) . " :</span>";

                // Verrouillage du bouton "Améliorer" : uniquement si une condition de QG/Arsenal
                // est définie pour ce palier ET que le niveau actuel du QG/Arsenal ne la
                // satisfait pas encore. Sert à la fois pour l'icône cadenas et pour le style/
                // comportement du bouton plus bas (rouge + inerte tant que verrouillé, vert +
                // fonctionnel dès que le QG/Arsenal a été suffisamment amélioré).
                $is_locked_bat = false;

                if ($required_level_bat > 0) {
                    $is_unlocked_bat = ($gating_level_actuel >= $required_level_bat);
                    $is_locked_bat = !$is_unlocked_bat;
                    $lock_icon_bat = $is_unlocked_bat ? 'images/icons/Unlock.png' : 'images/icons/Lock.png';
                    echo "
                    <span class='building-card-condition'>
                        <img class='building-card-condition-icon' src='{$lock_icon_bat}' alt=''>
                        {$required_label_bat} niveau {$required_level_bat}
                    </span>";
                }

                echo "
                    <div class='building-card-costs'>";

                if ($is_trap) {
                    // Les Pièges se paient uniquement en Or (amélioration d'Arsenal)
                    $cout_or = $b['BuildCostGold'] ?? 0;
                    echo "
                        <div class='building-cost-row'>
                            <span class='building-cost-label'><img class='building-cost-icon' src='images/icons/Gold.png' alt='Or'></span>
                            <span class='building-cost-value'>" . formatCost($cout_or) . "</span>
                        </div>";
                } else {
                    $cout_bois   = $b['BuildCostWood']  ?? 0;
                    $cout_pierre = $b['BuildCostStone'] ?? 0;
                    $cout_fer    = $b['BuildCostIron']  ?? 0;

                    echo "
                        <div class='building-cost-row'>
                            <span class='building-cost-label'><img class='building-cost-icon' src='images/icons/Wood.png' alt='Bois'></span>
                            <span class='building-cost-value'>" . formatCost($cout_bois) . "</span>
                        </div>
                        <div class='building-cost-row'>
                            <span class='building-cost-label'><img class='building-cost-icon' src='images/icons/Stone.png' alt='Pierre'></span>
                            <span class='building-cost-value'>" . formatCost($cout_pierre) . "</span>
                        </div>
                        <div class='building-cost-row'>
                            <span class='building-cost-label'><img class='building-cost-icon' src='images/icons/Iron.png' alt='Fer'></span>
                            <span class='building-cost-value'>" . formatCost($cout_fer) . "</span>
                        </div>";
                }

                echo "
                    </div>
                    <div class='building-card-time'>
                        <img class='building-time-icon' src='images/icons/Time Icon.png' alt='Temps'>{$temps_txt}
                    </div>
                    <button class='btn-upgrade" . ($is_locked_bat ? " btn-locked" : "") . "' " . ($is_locked_bat ? "disabled" : "") . "
                            onclick=\"triggerUpgradeBuilding('{$tid}', {$inst}, {$niv}, {$max_abs}, '{$safe_id}')\">
                        <span class='btn-text'>{$btn_text}</span>
                    </button>";
            }

            echo "
                    <button class='btn-downgrade' {$downgrade_disabled}
                            onclick=\"triggerDowngradeBuilding('{$tid}', {$inst}, {$niv}, '{$safe_id}')\">
                        <span class='btn-text'>&minus; Rétrograder au niveau {$niv_precedent}</span>
                    </button>
                </div>
            </div>";

            $order_index++;
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
    global $pdo;
    $id_player = $_SESSION['player_id'] ?? null;
    $boosts = getPlayerBoosts($pdo, $id_player);

    $categories = [
        'Ressource' => 'Bâtiments Économiques',
        'Defense'   => 'Bâtiments Défensifs',
        'Army'      => 'Bâtiments de Renfort',
        'Trap'      => 'Pièges',
    ];

    $resume = [];
    $total = ['gold' => 0, 'wood' => 0, 'stone' => 0, 'iron' => 0, 'time_seconds' => 0];

    foreach ($categories as $cat => $label) {
        // Pièges/Mines s'améliorent via l'Arsenal -> ArmoryBoost, le reste -> BuildingBoost.
        // Appliqué ICI (avant la somme dans $total, qui mélange les catégories) car une fois
        // les secondes de catégories différentes additionnées, on ne peut plus leur appliquer
        // rétroactivement des pourcentages différents.
        $pct = ($cat === 'Trap') ? $boosts['armory'] : $boosts['building'];
        $factor = 1 - (max(0, min(99, $pct)) / 100);

        $s = ['gold' => 0, 'wood' => 0, 'stone' => 0, 'iron' => 0, 'time_seconds' => 0];
        foreach (($buildings_display[$cat] ?? []) as $b) {
            $s['gold']         += (int)($b['remaining_gold']         ?? 0);
            $s['wood']         += (int)($b['remaining_wood']         ?? 0);
            $s['stone']        += (int)($b['remaining_stone']        ?? 0);
            $s['iron']         += (int)($b['remaining_iron']         ?? 0);
            $s['time_seconds'] += (int)round((int)($b['remaining_time_seconds'] ?? 0) * $factor);
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
    echo "<table class='resource-summary-table'><tbody>";
    foreach ($resume_batiments as $key => $row) {
        $is_total = ($key === 'TOTAL');
        $tr_class = $is_total ? " class='resource-summary-total'" : "";
        echo "<tr{$tr_class}>
                <td>{$row['label']}</td>
                <td>" . number_format($row['gold'], 0, ',', ' ') . " <img src='images/icons/Gold.png' alt='Or' title='Or'></td>
                <td>" . number_format($row['wood'], 0, ',', ' ') . " <img src='images/icons/Wood.png' alt='Bois' title='Bois'></td>
                <td>" . number_format($row['stone'], 0, ',', ' ') . " <img src='images/icons/Stone.png' alt='Pierre' title='Pierre'></td>
                <td>" . number_format($row['iron'], 0, ',', ' ') . " <img src='images/icons/Iron.png' alt='Fer' title='Fer'></td>
                <td>" . formatSecondsToText($row['time_seconds'], 'none') . "</td>
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
    echo "<table class='resource-summary-table'><tbody>";
    foreach ($resume_armee as $key => $row) {
        $is_total = ($key === 'TOTAL');
        $tr_class = $is_total ? " class='resource-summary-total'" : "";
        echo "<tr{$tr_class}>
                <td>{$row['label']}</td>
                <td>" . number_format($row['gold'], 0, ',', ' ') . " <img src='images/icons/Gold.png' alt='Or' title='Or'></td>
                <td>" . number_format($row['proto'], 0, ',', ' ') . " <img src='images/icons/Proto_Token.png' alt='Jetons Proto' title='Jetons Proto'></td>
                <td>" . number_format($row['manuels'], 0, ',', ' ') . " <img src='images/AbilityUpgradeToken.png' alt='Manuels de terrain' title='Manuels de terrain'></td>
                <td>" . number_format($row['rapport'], 0, ',', ' ') . " <img src='images/ExpCapToken.png' alt=\"Rapports d'activité\" title=\"Rapports d'activité\"></td>
                <td>" . number_format($row['jetons_heros'], 0, ',', ' ') . " <img src='images/HeroToken.png' alt='Jetons de héros' title='Jetons de héros'></td>
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

    echo "<div class='stats-sidebar'>";
    echo "<details class='stats-sidebar-accordion'>";
    echo "<summary class='stats-sidebar-summary'>
            <span class='stats-sidebar-title'>{$title}</span>
            <span class='stats-sidebar-percent'>{$stats['percent']}%</span>
            <span class='stats-sidebar-arrow'>&#9662;</span>
          </summary>";
    echo "<div class='stats-sidebar-content'>";

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
        // Cette liste est toujours homogène (une seule catégorie de bâtiments à la fois) :
        // on peut donc déterminer le bonus à appliquer depuis le 1er élément.
        $is_trap_list = !empty($buildings_data) && (($buildings_data[0]['Class'] ?? '') === 'Trap');
        $total_time_txt = formatSecondsToText($total_seconds, $is_trap_list ? 'armory' : 'building');
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
    echo "</div>"; // .stats-sidebar-content
    echo "</details>";
    echo "</div>"; // .stats-sidebar
}

/**
 * Convertit un temps d'amélioration exprimé en HEURES (ex: UpgradeTimeH, potentiellement décimal)
 * en texte lisible "Xj Yh Zm".
 */
/**
 * Convertit un temps d'amélioration exprimé en HEURES (ex: UpgradeTimeH, potentiellement décimal)
 * en texte lisible "Xj Xh Xm". $category détermine quel bonus de vitesse du Profil
 * (voir getPlayerBoosts) est appliqué avant formatage :
 *   - 'armory'   (par défaut) : Pièges/Mines/Troupes/Proto/Héros/Officiers/Canonnière
 *   - 'building' : bâtiments classiques (QG, Économiques/Défensifs/Support)
 *   - 'none'     : aucune réduction (ex : Tribus)
 * Uniquement un raccourci d'AFFICHAGE : n'affecte jamais les valeurs stockées/calculées
 * en amont (voir applyTimeBoostHours).
 */
function formatUnitsTime($hours, $category = 'armory') {
    $hours = applyTimeBoostHours((float)$hours, $category);
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
 * $category : 'building' par défaut (voir formatUnitsTime).
 */
function formatSecondsToText($seconds, $category = 'building') {
    return formatUnitsTime(((float)$seconds) / 3600, $category);
}

/**
 * Bonus de vitesse (%) définis par le joueur dans Profil > Boost, stockés dans
 * joueurs.BuildingBoost / joueurs.ArmoryBoost. Purement un raccourci d'AFFICHAGE : réduit
 * le temps affiché sur le site, ne modifie JAMAIS BuildTimeD/H/M/S, UpgradeTimeH ni
 * aucune autre valeur en base.
 *   - BuildingBoost : bâtiments améliorés via le QG (Économiques / Défensifs / Support).
 *   - ArmoryBoost   : tout ce qui s'améliore via l'Arsenal/Atelier : Pièges, Mines,
 *     Troupes, Proto-troupes, Héros, Chefs de bataillon, Capacités de canonnière.
 * Valeurs bornées 0-99 et mises en cache statiquement (1 seule requête par page).
 */
function getPlayerBoosts($pdo, $id_player) {
    static $cache = [];
    if (!$pdo || !$id_player) return ['building' => 0, 'armory' => 0];
    if (isset($cache[$id_player])) return $cache[$id_player];

    $stmt = $pdo->prepare("SELECT BuildingBoost, ArmoryBoost FROM joueurs WHERE id_player = ?");
    $stmt->execute([$id_player]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $result = [
        'building' => max(0, min(99, (float)($row['BuildingBoost'] ?? 0))),
        'armory'   => max(0, min(99, (float)($row['ArmoryBoost']   ?? 0))),
    ];
    $cache[$id_player] = $result;
    return $result;
}

/**
 * Applique le bonus de vitesse correspondant à $category à une durée en HEURES.
 * Voir formatUnitsTime / formatSecondsToText : c'est le seul endroit où le bonus est
 * réellement appliqué, toujours au moment du formatage, jamais en amont.
 */
function applyTimeBoostHours($hours, $category) {
    global $pdo;
    $id_player = $_SESSION['player_id'] ?? null;
    if ($category === 'none' || !$id_player || empty($pdo)) return $hours;

    $boosts = getPlayerBoosts($pdo, $id_player);
    $pct = ($category === 'armory') ? $boosts['armory'] : (($category === 'building') ? $boosts['building'] : 0);
    if ($pct <= 0) return $hours;

    return $hours * (1 - ($pct / 100));
}

/**
 * Barre horizontale de déblocage rapide des Chefs de bataillon, affichée
 * au-dessus du dashboard-wrapper de l'onglet "Chef de bataillon". Affiche
 * TOUS les officiers (Class = 'Officier') en petites icônes (img-unit), au
 * moins 6 par ligne. Un clic sur une icône ouvre une popup (voir modal
 * #officer-unlock-modal dans dashboard.php + script.js) qui demande si le
 * joueur a débloqué ou non ce chef :
 *   - Débloqué  -> capacités Active + Passive au niveau 1, Debloque = 1
 *     (upgrade_character.php, action=unlock_officer, déjà existant)
 *   - Reverrouillé -> capacités Active + Passive au niveau 0, Debloque = 0
 *     (upgrade_character.php, action=lock_officer, nouveau)
 *
 * $officers_list : même source que renderUnitsTable($officers_list, ...)
 * (chaque entrée contient au moins id_character, TID, nom, IconExportName, Debloque).
 * $officer_ranks_by_id : map [id_character => 'Lt'|'Sgt'] (voir queries.php), pour
 * colorer le fond de chaque icône selon le rang.
 */
function renderOfficerQuickBar($officers_list, $officer_ranks_by_id = []) {
    if (empty($officers_list)) return;

    echo "<div class='officer-quickbar'>";
    echo "<h3 class='officer-quickbar-title'>⚔️ Déblocage rapide des Chefs de bataillon</h3>";
    echo "<div class='officer-quickbar-grid'>";

    foreach ($officers_list as $o) {
        $id_character = (int)($o['id_character'] ?? 0);
        $tid          = htmlspecialchars($o['TID'] ?? '', ENT_QUOTES);
        $nom          = htmlspecialchars($o['nom'] ?? $tid, ENT_QUOTES);
        $icon         = htmlspecialchars($o['IconExportName'] ?? '', ENT_QUOTES);
        $is_unlocked  = ((int)($o['Debloque'] ?? 0) === 1);
        $status_class = $is_unlocked ? 'officer-quick-unlocked' : 'officer-quick-locked';
        $status_badge = $is_unlocked ? '<img class="troop-card-condition-icon" src="images/icons/Unlock.png" >' : '<img class="troop-card-condition-icon" src="images/icons/Lock.png" >';

        // Fond selon le rang : Lieutenant (Lt) = jaune, Sergent (Sgt) = bleu.
        // Sur ces fonds clairs, le texte gris clair par défaut manque de contraste
        // -> on bascule le nom en texte sombre quand un fond de rang est appliqué.
        $rank = trim($officer_ranks_by_id[$id_character] ?? '');
        $rank_bg = ($rank === 'Lt') ? '#FFC702' : (($rank === 'Sgt') ? '#5AC8FE' : null);
        $rank_style = $rank_bg ? " style='background:{$rank_bg};'" : '';
        $name_style = $rank_bg ? " style='color:#1a252f;'" : '';

        echo "
            <div class='officer-quick-item {$status_class}'{$rank_style}
                 data-id-character='{$id_character}'
                 data-nom='{$nom}'
                 data-icon='{$icon}'
                 data-unlocked='" . ($is_unlocked ? '1' : '0') . "'
                 onclick='openOfficerUnlockModal(this)'>
                <div class='officer-quick-img-wrapper'>
                    <img class='img-unit img-unit-small' src='images/characters/Officier/{$icon}.png' alt='{$nom}'>
                    <span class='officer-quick-badge'>{$status_badge}</span>
                </div>
                <span class='officer-quick-name'{$name_style}>{$nom}</span>
            </div>";
    }

    echo "</div></div>";
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

    echo "<div class='grid-toolbar'>";
    renderHideMaxedToggle($label_categorie);
    // Le tri par XP/temps n'a de sens que pour les troupes/proto-troupes (les
    // capacités de canonnière n'ont pas de coût XP, voir tribsid) — pas pour les
    // héros/officiers, qui ont leur propre design de carte (unit-card).
    if ($is_troop_list || $is_prototroop_list) {
        renderSortControls();
    }
    echo "</div>";

    // Compteur d'ordre d'affichage ORIGINAL (avant tout tri) : permet à l'option
    // "Par défaut" du tri de restaurer exactement l'ordre initial (voir sortCards
    // dans script.js). Uniquement pertinent pour le design troupe (seul concerné
    // par le tri), mais posé sur toutes les cartes sans effet de bord ailleurs.
    $order_index = 0;

    echo $use_troop_design
        ? "<div class='troops-grid hide-maxed-container'>"
        : "<div class='main-layout-container hide-maxed-container' style='display: flex; gap: 20px; align-items: flex-start; width: 100%;'><div class='units-grid-left' style='display: flex; flex-direction: row; flex-wrap: wrap; gap: 15px; flex: 1; justify-content: center;'>";

    foreach ($data as $u) {
        $tid         = $u['TID'];
        $safe_id     = str_replace(" ", "-", $u['nom']);
        $max_lvl     = intval($u['niveau_autorise'] ?? 1);
        // 🔥 data-maxed (utilisé par le bouton "Masquer au max") doit refléter le VRAI plafond
        // (niveau_max_absolu), pas le plafond QG/Arsenal actuel — sinon une unité seulement
        // plafonnée temporairement se retrouverait masquée à tort.
        $max_lvl_absolu_unit = intval($u['niveau_max_absolu'] ?? $max_lvl);
        $current_lvl = $progress[$tid] ?? $u['niveau_joueur'] ?? 1;
        if ($current_lvl === 0) $current_lvl = 1;
        $is_maxed_unit = ($current_lvl >= $max_lvl_absolu_unit);
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
            // 🔥 "Niveau max !" ne s'affiche désormais QUE si le VRAI maximum (niveau_max_absolu)
            // est atteint, plus lorsque l'Arsenal/Atelier actuel plafonne seulement temporairement
            // l'unité (voir queries.php : niveau_max_absolu / gating_level_actuel calculés pour
            // toutes les classes).
            $niveau_max_absolu_trp = (int)($u['niveau_max_absolu'] ?? $max);
            $is_maxed    = ($current_lvl >= $niveau_max_absolu_trp);

            // --- Vérification du bâtiment requis pour le PROCHAIN niveau ---
            // (Arsenal pour les Troupes, Atelier de Proto-troupes pour les Proto).
            // Les Capacités de canonnière n'ont pas de bâtiment requérant connu pour
            // l'instant : UpgradeHouseLevel vaut 0 dans ce cas, donc jamais bloquant.
            $required_house_level = (int)($u['next_cost']['UpgradeHouseLevel'] ?? 0);
            $player_house_level   = (int)($u['gating_level_actuel'] ?? ($is_prototroop ? ($house_levels['proto_factory'] ?? 0) : ($house_levels['arsenal'] ?? 0)));
            $house_label = $is_prototroop ? "Atelier de Proto-troupes" : "Arsenal";
            $house_ok    = ($required_house_level <= $player_house_level);

            // Attributs de tri (voir renderSortControls / sortCards) : XP gagné et temps
            // d'amélioration du PROCHAIN niveau (UpgradeTimeH converti en secondes pour
            // rester comparable au data-time des bâtiments). -1 = pas de niveau suivant
            // (déjà au VRAI max), toujours relégué en fin de liste quel que soit le sens.
            $xp_attr_trp = (!empty($u['next_cost']) && isset($u['next_cost']['XpGain']) && $u['next_cost']['XpGain'] !== null)
                ? (int)$u['next_cost']['XpGain'] : -1;
            $time_attr_trp = (!empty($u['next_cost']) && isset($u['next_cost']['UpgradeTimeH']) && $u['next_cost']['UpgradeTimeH'] !== null)
                ? (int)round(((float)$u['next_cost']['UpgradeTimeH']) * 3600) : -1;

            echo "
            <div class='troop-card' id='card-{$safe_id_trp}' data-tid='{$tid}' data-maxed='{$maxed_attr}' data-xp='{$xp_attr_trp}' data-time='{$time_attr_trp}' data-order='{$order_index}'>
                <div class='troop-card-info'>
                    <span class='troop-card-name'>{$nom}</span>
                    <span class='troop-card-level' id='lvl-{$safe_id_trp}'>Niveau {$current_lvl} / {$max}</span>
                </div>

                <div class='troop-card-visual'>
                    <img class='troop-card-img' src='images/characters/{$class_css}/{$icon}.png' alt='{$nom}' onerror=\"this.src='images/default-building.png'\">
                </div>";

            if (!$is_maxed && !empty($u['next_cost'])) {
                $cost      = $u['next_cost'];
                $cout      = $cost['UpgradeCost'] ?? 0;
                $temps_txt = formatUnitsTime($cost['UpgradeTimeH'] ?? 0);

                // Pas de distinction en base : Troupe => Or, Proto => Jetons Proto
                $cost_icon  = $is_prototroop ? 'images/icons/Proto_Token.png' : 'images/icons/Gold.png';
                $cost_label = $is_prototroop ? 'Jetons Proto' : 'Or';

                echo "
                <span class='troop-card-upgrade-title'>Améliorer au niveau " . ($current_lvl + 1) . " :</span>";

                // Condition informative "Arsenal niveau X" / "Atelier de Proto-troupes niveau X",
                // affichée entre le titre et les coûts, avec un cadenas ouvert/fermé selon que
                // le bâtiment gating actuel du joueur satisfait ou non cette condition.
                if ($required_house_level > 0) {
                    $condition_class = $house_ok ? 'troop-card-condition' : 'troop-card-condition locked';
                    $lock_icon_trp = $house_ok ? 'images/icons/Unlock.png' : 'images/icons/Lock.png';
                    echo "
                <span class='{$condition_class}'>
                    <img class='troop-card-condition-icon' src='{$lock_icon_trp}' alt=''>
                    {$house_label} niveau {$required_house_level}
                </span>";
                }

                echo "
                <div class='troop-card-costs'>
                    <span class='troop-cost-item'><img class='troop-cost-icon' src='{$cost_icon}' alt='{$cost_label}'>" . formatCost($cout) . "</span>
                </div>
                <div class='troop-card-time'>
                    <img class='troop-time-icon' src='images/icons/Time Icon.png' alt='Temps'>{$temps_txt}
                </div>";
            }

            // Bouton rétrograder : toujours affiché sous la carte
            $niv_precedent_trp = max(1, $current_lvl - 1);
            $downgrade_disabled_trp = ($current_lvl <= 1) ? 'disabled' : '';
            $btn_downgrade_trp = "
                    <button class='btn-downgrade' {$downgrade_disabled_trp}
                            onclick=\"triggerDowngradeCharacter('{$tid}', '{$safe_id_trp}', {$current_lvl})\">
                        <span class='btn-text'>&minus; Rétrograder au niveau {$niv_precedent_trp}</span>
                    </button>";

            if ($is_maxed) {
                // Vrai max (niveau_max_absolu) atteint : plus rien à améliorer.
                echo "
                <div class='troop-card-action'>
                    <span class='officer-ability-locked' style='color:#e74c3c;font-weight:bold;'>Niveau max !</span>
                    {$btn_downgrade_trp}
                </div>
            </div>";
            } else {
                // Le bouton Améliorer reste affiché tant que le vrai max n'est pas atteint,
                // même si l'Arsenal/Atelier actuel ne permet pas encore ce palier (déjà
                // signalé par le cadenas fermé sur la condition ci-dessus).
                echo "
                <div class='troop-card-action'>
                    <button class='btn-upgrade" . (!$house_ok ? " btn-locked" : "") . "' " . (!$house_ok ? "disabled" : "") . "
                            onclick=\"triggerUpgradeCharacter('{$tid}', '{$safe_id_trp}', {$niveau_max_absolu_trp})\">
                        <span class='btn-text'>Améliorer</span>
                    </button>
                    {$btn_downgrade_trp}
                </div>
            </div>";
            }

            $order_index++;
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
            // 🔥 "Niveau max !" ne s'affiche désormais QUE si le VRAI maximum (niveau_max_absolu,
            // indépendant du QG) est atteint — plus lorsque le QG actuel plafonne seulement
            // temporairement le héros.
            $niveau_max_absolu = (int)($u['niveau_max_absolu'] ?? $max_lvl);
            $hero_maxed = ($current_lvl >= $niveau_max_absolu);
            // Niveau de QG requis pour le PROCHAIN palier (toujours dispo via next_cost, pas
            // seulement quand déjà plafonné) + niveau de QG réel actuel du joueur, pour le cadenas.
            $required_qg_hero    = (int)($u['next_cost']['UpgradeHouseLevel'] ?? 0);
            $gating_level_actuel_hero = (int)($u['gating_level_actuel'] ?? 0);

            echo "<div class='unit-info-wrapper'>
                <div class='unit-img-wrapper'>
                    <img class='img-unit' src='images/characters/" . htmlspecialchars($u['Class']) . "/{$u['IconExportName']}.png' alt='" . htmlspecialchars($u['nom']) . "'>
                </div>
                <div class='unit-details' style='display: flex; flex-direction: column; width: 100%;'>
                    <div class='unit-name' style='font-weight: bold; font-size: 1.1em; color: #ffffff;'>" . htmlspecialchars($u['nom']) . "</div>
                    <div class='lvl-display' id='lvl-{$safe_id}' style='color: #1abc9c; font-weight: bold;'>{$display_text}</div>
                </div>";

            // Coût / temps du prochain niveau, entre unit-details et le bouton — affiché tant
            // que le vrai max n'est pas atteint, même si le QG actuel ne permet pas encore ce
            // palier (signalé par le cadenas sur la condition ci-dessous).
            if (!$hero_maxed && !empty($u['next_cost'])) {
                $cost      = $u['next_cost'];
                $cout      = $cost['UpgradeCost'] ?? 0;
                $temps_txt = formatUnitsTime($cost['UpgradeTimeH'] ?? 0);

                if ($required_qg_hero > 0) {
                    $is_unlocked_hero = ($gating_level_actuel_hero >= $required_qg_hero);
                    $lock_icon_hero = $is_unlocked_hero ? 'images/icons/Unlock.png' : 'images/icons/Lock.png';
                    echo "<span class='hero-upgrade-condition'>
                            <img class='hero-condition-icon' src='{$lock_icon_hero}' alt=''>
                            QG niveau {$required_qg_hero}
                        </span>";
                }

                echo "<div class='hero-upgrade-cost'>
                        <span class='hero-cost-item'><img class='hero-cost-icon' src='images/icons/Gold.png' alt='Or' style='width:25px'>" . formatCost($cout) . "</span>
                        <span class='hero-time-item'><img class='hero-time-icon' src='images/icons/Time Icon.png'  style='width:25px' alt='Temps'>{$temps_txt}</span>
                    </div>";
            }

            if ($hero_maxed) {
                // Même logique que les capacités d'officier verrouillées (officer-ability-locked) :
                // texte rouge seul, PAS de bouton autour (sinon le fond/bordure du bouton reste
                // visible même avec un texte rouge dedans).
                echo "<span class='officer-ability-locked' style='color:#e74c3c;font-weight:bold;'>Niveau max !</span>
                </div>"; // fin unit-info-wrapper
            } else {
                echo "<button class='btn-upgrade' onclick=\"triggerUpgradeCharacter('{$tid}', '{$safe_id}', {$niveau_max_absolu})\">
                            Améliorer
                        </button>
                    </div>"; // fin unit-info-wrapper
            }

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

    // 2bis. Trouver le DERNIER talent débloqué (le seul qu'on puisse rétrograder, ordre
    // inverse strict — miroir du "prochain talent" ci-dessus, ORDER BY ... DESC)
    $last_talent_id = null;
    $last_talent_nom = null;
    $stmt_last = $pdo->prepare("
        SELECT ai.id, ai.TID, t.FR AS nom
        FROM characterid ci
        INNER JOIN officer_talents ot ON ot.TID = ci.TID
        INNER JOIN abilitieid ai ON ai.TID IN (ot.TalentTID1, ot.TalentTID2, ot.TalentTID3, ot.TalentTID4, ot.TalentTID5)
        LEFT JOIN texts t ON t.TID = ai.TID
        INNER JOIN progress_ability pa ON pa.id_player = ? AND pa.id_character = ? AND pa.id_ability = ai.id
        WHERE ci.id = ? AND ai.TID IS NOT NULL AND pa.Debloque = 1
        ORDER BY
            CASE ai.TID
                WHEN ot.TalentTID1 THEN 1
                WHEN ot.TalentTID2 THEN 2
                WHEN ot.TalentTID3 THEN 3
                WHEN ot.TalentTID4 THEN 4
                WHEN ot.TalentTID5 THEN 5
            END DESC
        LIMIT 1
    ");
    $stmt_last->execute([$id_player, $u['id_character'], $u['id_character']]);
    $last_talent = $stmt_last->fetch(PDO::FETCH_ASSOC);
    if ($last_talent) {
        $last_talent_id = $last_talent['id'];
        $last_talent_nom = $last_talent['nom'] ?: $last_talent['TID'];
    }

    // 3. Affichage
    echo "<div class='officer-talent' style='text-align: center; margin: 10px 0;'>
            <img src='images/icons/{$imageFile}' style='width: 80px;' alt='Talents débloqués: {$talents_debloques}'>
            <div class='talent-status'>
                <p class='talent-count'>Talents débloqués : {$talents_debloques}/5</p>";

    // 🔥 Boutons "Débloquer talent suivant" / "Rétrograder", côte à côte
    echo "<div class='talent-actions'>";
    if ($next_talent_id && $talents_debloques < 5) {
        $next_talent_nom_esc = htmlspecialchars($next_talent_nom, ENT_QUOTES);
        echo "<button class='btn-unlock-talent' data-character='{$u['id_character']}' data-ability='{$next_talent_id}' data-tid='{$next_talent_nom_esc}'
                      onclick='unlockTalent(this)'>
                  Débloquer talent suivant
              </button>";
    } elseif ($talents_debloques >= 5) {
        echo "<p style='color: #2ecc71; margin: 0;'>Tous les talents débloqués !</p>";
    }
    if ($last_talent_id) {
        $last_talent_nom_esc = htmlspecialchars($last_talent_nom, ENT_QUOTES);
        echo "<button class='btn-downgrade' data-character='{$u['id_character']}' data-ability='{$last_talent_id}' data-tid='{$last_talent_nom_esc}'
                      onclick='downgradeTalent(this)'>
                  Rétrograder
              </button>";
    }
    echo "</div>";

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

    // <img class="troop-card-condition-icon" src="images/icons/Lock.png"> Capacité de héros pas encore débloquée (Debloque = 0 en base, voir progress_ability).
    // N'affecte que les entrées qui exposent explicitement ce flag (capacités de héros) ; les
    // officiers, qui n'en ont pas, gardent leur comportement existant.
    $is_locked = isset($talent['debloque']) && (int)$talent['debloque'] === 0;

    $next_lvl = $ab_lvl + 1; // le niveau qui sera atteint APRÈS l'amélioration (texte/affichage ET calcul)

    // Capacité verrouillée : ni niveau ni coût n'ont de sens tant que Debloque = 0, on
    // n'affiche que le niveau de héros requis ("Niveau X requis"), pas de bouton.
    // `niveau` vaut 1 en base pour une capacité jamais débloquée (placeholder, comme les
    // talents d'officier) : le HeroLevel requis pour la DÉBLOQUER se lit donc sur la ligne
    // Niveau = 1 (= $ab_lvl ici) — pas de "niveau suivant" à calculer, donc pas de +1 ici,
    // contrairement au cas d'une capacité déjà débloquée traité plus bas.
    if ($is_locked) {
        $required_lvl = 0;
        foreach ($talent['levels'] ?? [] as $row) {
            if ((int)$row['Niveau'] === $ab_lvl) {
                $required_lvl = (int)$row['HeroLevel'];
                break;
            }
        }
        return "<div class='officer-ability-row' data-id-ability='{$ab_id}'>
                <div class='officer-ability-info'>
                    <div class='officer-ability-header'>
                        <div><span class='officer-ability-name'>{$talent['nom']}</span> <span class='officer-ability-type'>{$type_label}</span></div>
                        <div><img src='images/characters/Officier/talent/{$ab_icon}.webp' class='officer-ability-icon'></div>
                    </div>
                </div>
                <div class='officer-ability-upgrade'>
                    <span class='officer-ability-locked' style='color: #e74c3c; font-weight: bold;'>Niveau {$required_lvl} requis</span>
                </div>
            </div>";
    }

    // Capacité débloquée : la ligne officer_abilities dont Niveau = $ab_lvl (niveau ACTUEL)
    // décrit les conditions (HeroLevel, coût) du PASSAGE $ab_lvl -> $next_lvl — même
    // convention que muSumRemainingAbilityLevels() plus haut dans ce fichier, et même
    // correctif appliqué côté serveur dans upgrade_ability.php.
    $next_lvl_data = null;
    foreach ($talent['levels'] ?? [] as $row) {
        if ((int)$row['Niveau'] === $ab_lvl) {
            $next_lvl_data = $row;
            break;
        }
    }

    // Un palier avec UpgradeCost = 0 (ou absent) est un "terminateur" : il ne s'agit pas d'un
    // vrai palier supplémentaire, juste du marqueur "rien de plus à acheter à partir d'ici".
    $is_max = ($next_lvl_data === null) || ((float)($next_lvl_data['UpgradeCost'] ?? 0) <= 0);

    $cost_display = "";
    if ($next_lvl_data) {
        $resource_name = htmlspecialchars($next_lvl_data['UpgradeResource']);
        $cost_display  = "<img src='images/{$resource_name}.png' alt='{$resource_name}' style='width: 20px; vertical-align: middle; margin-left: 4px;'>" . formatCost($next_lvl_data['UpgradeCost']);
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
                    $req_h  = (int)$next_lvl_data['HeroLevel'];
                    $can_up = ($current_lvl >= $req_h);

                    if ($can_up) {
                        $ab_nom_esc = htmlspecialchars(addslashes($talent['nom'] ?? $ab_tid), ENT_QUOTES);
                        // 🔥 CORRECTIF double-soumission : PLUS d'onclick="triggerUpgradeAbility(...)" ici.
                        // Ce bouton porte la classe .btn-upgrade-ability, déjà interceptée par un
                        // gestionnaire délégué (document.body, voir script.js) qui gère la confirmation,
                        // l'appel serveur ET la mise à jour de l'affichage sans reload. Garder l'onclick
                        // EN PLUS de cette classe faisait tourner les DEUX gestionnaires sur un seul clic
                        // (2 confirmations, 2 requêtes serveur, 2 incréments de niveau pour 1 clic).
                        // data-tid et data-next-level restent lus par le gestionnaire délégué pour
                        // afficher un message de confirmation précis (nom + niveau cible).
                        $html .= "<button class='btn-upgrade-ability' data-character='{$id_character}' data-ability='{$ab_id}' data-tid='{$ab_nom_esc}' data-next-level='{$next_lvl}'>Améliorer</button>";
                    } else {
                        // Même style que la capacité verrouillée (Debloque = 0) plus haut : texte
                        // rouge, pas de bouton grisé, dès que le palier suivant n'est pas encore
                        // accessible.
                        $html .= "<span class='officer-ability-locked' style='color: #e74c3c; font-weight: bold;'>Niveau {$req_h} requis</span>";
                    }
                } else {
                    $html .= "<span class='officer-ability-locked' style='color: #e74c3c; font-weight: bold;'>Niveau max</span>";
                }
                // Bouton "Rétrograder" : redescend d'un niveau, indépendamment du fait que la
                // capacité soit ou non au max — seul le niveau RÉEL ($ab_lvl) compte ici.
                if ($ab_lvl > 1) {
                    $ab_nom_esc = htmlspecialchars(addslashes($talent['nom'] ?? $ab_tid), ENT_QUOTES);
                    $html .= "<button class='btn-downgrade' data-character='{$id_character}' data-ability='{$ab_id}' data-tid='{$ab_nom_esc}' data-current-level='{$ab_lvl}' onclick=\"triggerDowngradeAbility(this, '{$ab_id}', '{$safe_ab_id}')\">Rétrograder</button>";
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

    echo "<div class='stats-sidebar'>";
    echo "<details class='stats-sidebar-accordion'>";
    echo "<summary class='stats-sidebar-summary'>
            <span class='stats-sidebar-title'>{$title}</span>
            <span class='stats-sidebar-percent'>{$percent}%</span>
            <span class='stats-sidebar-arrow'>&#9662;</span>
          </summary>";
    echo "<div class='stats-sidebar-content'>";
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

    echo "</div>"; // .stats-sidebar-content
    echo "</details>";
    echo "</div>"; // .stats-sidebar
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

    echo "<div class='stats-sidebar' style='width: 100%;'>";
    echo "<details class='stats-sidebar-accordion'>";
    echo "<summary class='stats-sidebar-summary'>
            <span class='stats-sidebar-title'>Progression Officiers</span>
            <span class='stats-sidebar-percent'>{$global_percent}%</span>
            <span class='stats-sidebar-arrow'>&#9662;</span>
          </summary>";
    echo "<div class='stats-sidebar-content'>";
    echo "<table style='width:100%; font-size:0.8em; border-collapse:collapse;'>
            <thead>
                <tr style='color:#bdc3c7;'>
                    <th style='text-align:left;'>Nom</th>
                    <th>Talent</th><th>Pass</th><th>Act</th><th>%</th>
                </tr>
            </thead>
            <tbody>{$rows_html}</tbody>
        </table>";
    echo "</div>"; // .stats-sidebar-content
    echo "</details>";
    echo "</div>"; // .stats-sidebar
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

    echo "<div class='stats-sidebar' style='width: 100%;'>";
    echo "<details class='stats-sidebar-accordion'>";
    echo "<summary class='stats-sidebar-summary'>
            <span class='stats-sidebar-title'>Progression Héros</span>
            <span class='stats-sidebar-percent'>{$global_percent}%</span>
            <span class='stats-sidebar-arrow'>&#9662;</span>
          </summary>";
    echo "<div class='stats-sidebar-content'>";
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
    echo "</div>"; // .stats-sidebar-content
    echo "</details>";
    echo "</div>"; // .stats-sidebar
}


/**
 * Rendu de l'onglet Monument Mystique
 */
function renderMysticMonument($current_mm_level, $all_bonuses, $user_bonuses) {
    echo "
    <div class='mm-panel'>
        <h2 class='mm-title'>🗿 Monument Mystique</h2>

        <div class='mm-top-card'>
            <div class='mm-top-card-visual'>
                <img src='images/mystic_monument.png' alt='Monument Mystique' class='mm-top-card-img' onerror=\"this.src='images/default-building.png'\">
            </div>
            <div class='mm-top-card-body'>
                <label for='mm_global_level' class='mm-top-card-label'>Quel est votre niveau de monument ?</label>
                <div class='mm-top-card-controls'>
                    <input type='number' id='mm_global_level' value='{$current_mm_level}' min='0' max='800'
                           class='mm-level-input'
                           oninput='updateMonumentLevel(this.value)'>
                    <button type='button' class='mm-save-btn' onclick='saveMonumentLevel()'>Enregistrer</button>
                    <span id='mm-level-save-status' class='mm-save-status'></span>
                </div>
            </div>
        </div>

        <div class='mm-bonus-list'>";

    // ICI : La seule et unique boucle pour afficher les lignes
    foreach ($all_bonuses as $bonus) {
        $id_b = (int)$bonus['id_bonus'];
        $min_lvl = (int)$bonus['MinBuildingLevel'];
        $max_count = isset($bonus['MaxCount']) ? (int)$bonus['MaxCount'] : 0;
        $boost_amount = isset($bonus['BoostAmount']) ? (float)$bonus['BoostAmount'] : 0;
        $display_name = !empty($bonus['FR']) ? $bonus['FR'] : $bonus['TID'];
        $qty = $user_bonuses[$id_b] ?? 0;
        $total = $boost_amount * $qty;
        $total_display = (fmod($total, 1) == 0) ? number_format($total, 0, ',', ' ') : number_format($total, 2, ',', ' ');

        $is_locked = ($current_mm_level < $min_lvl);
        $row_class = $is_locked ? "mm-bonus-row mm-bonus-row-locked" : "mm-bonus-row";
        $disabled_attr = $is_locked ? "disabled" : "";

        echo "
            <div class='{$row_class}' data-min-mm-lvl='{$min_lvl}'>
                <span class='mm-bonus-name'>" . htmlspecialchars($display_name) . "</span>
                <input type='number'
                       value='{$qty}'
                       min='0'
                       max='{$max_count}'
                       class='mm-qty-field monument-qty-field'
                       data-id-bonus='{$id_b}'
                       data-max-count='{$max_count}'
                       data-boost-amount='{$boost_amount}'
                       oninput='updateMonumentBonusTotal(this)'
                       {$disabled_attr}>
                <span class='mm-bonus-total' data-total-for='{$id_b}'>+{$total_display} %</span>
            </div>";
    }

    echo "
        </div>

        <div class='mm-bonus-save-row'>
            <button type='button' class='mm-save-btn' onclick='saveMonumentBonuses()'>Enregistrer</button>
            <span id='mm-bonus-save-status' class='mm-save-status'></span>
        </div>
    </div>";
}

/**
 * Rendu de l'onglet Profil > Statue : un datatable avec autant de lignes
 * ("emplacements") que le niveau de l'Atelier du sculpteur en autorise
 * ($artifact_capacity, ex-"Colonne 14" / ArtifactCapacity). Pour chaque
 * emplacement :
 *   1. un menu déroulant "Emplacement" (palier + élément, une seule entrée
 *      par TID distinct, voir $statue_emplacements dans queries.php) ;
 *   2. un menu déroulant "Bonus" peuplé dynamiquement en JS à partir de
 *      window.STATUE_OPTIONS_BY_TID selon l'emplacement choisi ;
 *   3. un champ numérique borné (min/max de la ligne statueid choisie).
 *
 * $statue_emplacements : liste de ['tid','label','element'] (queries.php).
 * $player_statues      : progression déjà enregistrée, indexée par id_slot.
 */
function renderStatuesTable($artifact_capacity, $statue_emplacements, $player_statues) {
    echo "<div class='statue-panel'>";
    echo "<h2 class='statue-title'>🗿 Statues</h2>";

    if ($artifact_capacity <= 0) {
        echo "<p class='statue-empty-msg'>Construisez l'Atelier du sculpteur pour débloquer des emplacements de statues.</p></div>";
        return;
    }

    echo "
        <p class='statue-subtitle'>Emplacements disponibles : {$artifact_capacity} (selon le niveau de votre Atelier du sculpteur).</p>
        <div class='statue-table'>
            <div class='statue-row statue-row-header'>
                <span>Emplacement</span>
                <span>Emplacement (palier)</span>
                <span>Bonus</span>
                <span>Valeur</span>
                <span></span>
            </div>";

    for ($slot = 1; $slot <= $artifact_capacity; $slot++) {
        $current = $player_statues[$slot] ?? null;
        $current_id_statue = $current['id_statue'] ?? 0;
        $current_boost     = $current['boost'] ?? '';

        echo "
            <div class='statue-row' data-slot='{$slot}'>
                <span class='statue-slot-number'>#{$slot}</span>
                <select class='statue-select statue-select-tid' data-slot='{$slot}' onchange='onStatueTidChange(this)'>
                    <option value=''>— Choisir —</option>";
        foreach ($statue_emplacements as $empl) {
            $selected = '';
            // Pré-sélection de l'emplacement si une statue est déjà enregistrée sur ce slot
            // (on ne connaît le TID qu'indirectement via id_statue, résolu côté JS au chargement).
            echo "<option value='" . htmlspecialchars($empl['tid']) . "' data-element='" . htmlspecialchars($empl['element']) . "'>" . htmlspecialchars($empl['label']) . "</option>";
        }
        echo "
                </select>
                <select class='statue-select statue-select-bonus' data-slot='{$slot}' onchange='onStatueBonusChange(this)' disabled>
                    <option value=''>— Choisir un emplacement —</option>
                </select>
                <input type='number' class='statue-input-boost' data-slot='{$slot}' value='{$current_boost}' disabled onchange='saveStatueSlot({$slot})'>
                <button type='button' class='statue-clear-btn' onclick='clearStatueSlot({$slot})' title='Vider cet emplacement'>🗑️</button>
                <input type='hidden' class='statue-current-id-statue' data-slot='{$slot}' value='{$current_id_statue}'>
            </div>";
    }

    echo "
        </div>
        <div class='statue-save-row'>
            <span id='statue-save-status' class='mm-save-status'></span>
        </div>
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
                <img class='troop-card-condition-icon' src='images/icons/Lock.png' style='width:50px;'> Radar niv. {$radar_req} requis (actuel : niv. {$radar_actuel})
            </div>";
        } elseif ($debloque && !$is_maxed && $next_cost) {
            $temps_txt = formatUnitsTime(($next_cost['time_min'] ?? 0) / 60, 'none');
            echo "
            <div class='building-card-costs'>
                <span class='building-cost-item'><img class='building-cost-icon' src='images/icons/Raw Crystals.png' alt='Cristaux bruts' onerror=\"this.src='images/default.png'\">" . formatCost($next_cost['cost']) . "</span>
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

        // Sécurité pour le Type : nécessaire numérique pour le nom de fichier de l'icône
        // (images/icons/Shard{n}.png -> Type 1 = Shard1.png, Type 4 = Shard4.png, etc.)
        $type_raw = $e['Type'] ?? 0;
        $type_num = (int)(is_array($type_raw) ? reset($type_raw) : $type_raw);

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

        $display_text = ($niv === 0) ? "Non débloqué" : "Qualité {$niv} / {$max}";
        $btn_text = $is_maxed ? "Max !" : (($niv === 0) ? "Débloquer" : "Améliorer");

        // Bouton rétrograder : même principe que sur les cartes bâtiments/personnages
        $niv_precedent = max(0, $niv - 1);
        $downgrade_disabled = ($niv <= 0) ? 'disabled' : '';

        echo "
        <div class='troop-card' id='card-{$safe_id}' data-tid='{$tid}' data-id-engraving='{$id_engraving}' data-maxed='{$maxed_attr}' style='border-top: 3px solid {$cat_color};'>
            <div class='engraving-title-row'>
                <div class='engraving-name-quality'>
                    <span class='troop-card-name'>{$nom}</span>
                    <span class='troop-card-level' id='lvl-{$safe_id}'>{$display_text}</span>
                </div>
                <img class='engraving-type-icon' src='images/icons/Shard{$type_num}.png' alt='Type {$type_num}' title='Type {$type_num}' onerror=\"this.style.display='none'\">
            </div>

            <div class='troop-card-visual'>
                <img class='troop-card-img' src='images/engravings/{$icon}.png' alt='{$nom}' onerror=\"this.src='images/engravings/{$icon}.webp'\">
            </div>";

        if (!$is_maxed) {
            echo "
            <span class='troop-card-upgrade-title'>Améliorer au niveau {$next_lvl} :</span>
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
                <button class='btn-downgrade' {$downgrade_disabled}
                        onclick=\"triggerDowngradeEngraving({$id_engraving}, '{$safe_id}', {$niv})\">
                    <span class='btn-text'>&minus; Rétrograder au niveau {$niv_precedent}</span>
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

    // Même structure <details>/<summary> que les autres sidebars (bâtiments, troupes,
    // chefs, héros) pour un comportement d'accordéon cohérent dans toute l'appli.
    echo "<div class='stats-sidebar'>";
    echo "<details class='stats-sidebar-accordion'>";
    echo "<summary class='stats-sidebar-summary'>
            <span class='stats-sidebar-title'>{$title}</span>
            <span class='stats-sidebar-percent'>{$percent}%</span>
            <span class='stats-sidebar-arrow'>&#9662;</span>
          </summary>";
    echo "<div class='stats-sidebar-content'>";

    echo "<ul style='list-style: none; padding: 0; margin: 0; font-size: 0.85em;'>{$rows_html}</ul>";

    if ($total_cost > 0) {
        echo "<div class='sidebar-total-remaining'>
                <div class='sidebar-total-title'>Jetons de recherche restants</div>
                <div class='sidebar-total-costs'>
                    <span class='sidebar-total-item'><img src='images/icons/Proto_Token.png' alt='Jetons' style='width:20px;'>" . number_format($total_cost, 0, ',', ' ') . "</span>
                </div>
              </div>";
    }

    echo "</div>"; // .stats-sidebar-content
    echo "</details>";
    echo "</div>"; // .stats-sidebar
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
    echo "<div class='dashboard-container'><h2>Tableau de Bord</h2>";

    // ================= SECTION BÂTIMENTS =================
    // %age affiché = percent_absolu (progression vers le VRAI max du jeu, indépendant
    // du QG actuel), cohérent avec ce qu'affichait l'ancienne carte "Complétion".
    renderDashSectionHeader('🏗️ Bâtiments');
    echo "<div class='dash-section-box'>";
    renderDashRow('🏦 Économie', $stats_res['percent_absolu'], 'Building-Ressource');
    renderDashRow('🛡️ Défense', $stats_def['percent_absolu'], 'Building-Defense');
    renderDashRow('🏰 Renfort', $stats_army['percent_absolu'], 'Building-Army');
    renderDashRow('💣 Pièges', $stats_trap['percent_absolu'], 'Building-Trap');
    echo "</div>";

    // ================= SECTION ARMÉE =================
    renderDashSectionHeader('⚔️ Armée');
    echo "<div class='dash-section-box'>";
    renderDashRow('🪖 Troupes', $stats_troupes['percent'], 'Character-Troop');
    renderDashRow('🧪 Proto-troupes', $stats_proto['percent'], 'Character-Proto');
    renderDashRow('🦸 Héros', $stats_heros['percent'], 'Character-Hero');
    renderDashRow('🚤 Capacité de canonnière', $stats_capacanon['percent'], 'Character-Spell');
    renderDashRow('🎖️ Chefs de bataillon', $stats_officiers_capa['percent'], 'Character-Leader');
    echo "</div>";

    // ================= SECTION GRAVURES =================
    renderDashSectionHeader('🪶 Gravures');
    echo "<div class='dash-section-box'>";
    renderDashRow('🗡️ Gravures Offensives', $stats_gravures_off['percent'], 'Engraving-Offensive');
    renderDashRow('🛡️ Gravures Défensives', $stats_gravures_def['percent'], 'Engraving-Defensive');
    echo "</div>";

    // ================= SECTION AUTRES =================
    renderDashSectionHeader('✨ Autres');
    echo "<div class='dash-section-box'>";
    renderDashRow('🏝️ Tribus', $stats_tribus['percent'], 'Tribes');
    renderDashRow('🗿 Monument mystique', $stats_monument['percent'], 'Monument');
    echo "</div>";

    echo "</div>"; // .dashboard-container
}

/**
 * Titre de section du Tableau de Bord (Bâtiments / Armée / Gravures / Autres).
 * Volontairement minimal : un simple h3, pas de carte ni de barre de progression.
 */
function renderDashSectionHeader($title) {
    echo "<h3 class='dash-section-title'>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</h3>";
}

/**
 * Une ligne "sous-catégorie" du Tableau de Bord : libellé à gauche, %age à droite,
 * façon écran "Stats de joueur" du jeu. Cliquable si $target_tab est fourni.
 */
function renderDashRow($label, $percent, $target_tab = null) {
    $percent_safe = max(0, min(100, (float)$percent));
    $classes      = $target_tab ? 'dash-row clickable' : 'dash-row';
    $onclick_attr = $target_tab ? " onclick=\"showTab('{$target_tab}')\"" : "";

    echo "
    <div class='{$classes}'{$onclick_attr}>
        <span class='dash-row-label'>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</span>
        <span class='dash-row-value'>{$percent_safe}%</span>
    </div>";
}

/* ==========================================================================
   BANDEAU DE PROGRESSION PAR QG (sous le Tableau de Bord principal)
   ========================================================================== */

/**
 * Petit utilitaire : à partir d'une liste de lignes ['Niveau' => int, 'Gate' => int] pour
 * un même TID (palier de bâtiment ou de personnage), renvoie le plus haut Niveau dont le
 * palier (Gate = TownHallLevel ou UpgradeHouseLevel) est <= $threshold. C'est l'équivalent
 * du "niveau_max" (borné par le QG/bâtiment ACTUEL) calculé dans queries.php, mais rejouable
 * pour n'importe quel QG N — pas seulement le QG réel du joueur.
 */
function maxLevelAtThreshold($rows, $threshold) {
    $max = 0;
    foreach ($rows as $r) {
        if ($r['Gate'] <= $threshold && $r['Niveau'] > $max) {
            $max = $r['Niveau'];
        }
    }
    return $max;
}

/**
 * Calcule, pour chaque niveau de QG (voir $all_qg_images dans queries.php), si le joueur
 * a atteint le niveau maximum ATTEIGNABLE À CE QG (comme si toutes les sections étaient à
 * 100% à ce QG précis — PAS le vrai maximum absolu de fin de jeu, qui rendrait la coche
 * quasi impossible à obtenir sur les premiers QG) sur TOUT ce qui est débloqué à ce niveau :
 *   - Bâtiments : nombre CUMULÉ d'instances débloquées par QG (townhall_levels, même logique
 *     que la section BÂTIMENTS de update_qg.php), plafond de niveau = MAX(Niveau) FROM
 *     buildings WHERE TID=X AND TownHallLevel <= N (même calcul que niveau_max dans
 *     getBuildingsDisplay(), mais évalué pour CE QG N plutôt que le QG réel du joueur) ;
 *   - Troupes / Capacités de canonnière / Héros (characterid.HQUnlock <= N) : plafond de
 *     niveau = MAX(Niveau) FROM characters WHERE TID=X AND UpgradeHouseLevel <= N.
 *     Simplification volontaire : en jeu, Troupes/Capa. canonnière dépendent normalement du
 *     niveau réel de l'Arsenal (pas directement du QG, voir getFilteredUnits() dans
 *     queries.php) ; ici on simule "si on était à ce QG-là", donc on utilise directement N
 *     comme palier de référence pour les 3 classes (c'est déjà exactement le calcul utilisé
 *     pour les Héros en jeu).
 *
 * Volontairement HORS scope (non demandés pour ce badge) : Officiers/Chefs de bataillon
 * (dépendent des talents débloqués, pas d'un simple niveau), Proto-troupes, Gravures,
 * Tribus, Monument mystique.
 *
 * Retourne un tableau [niveau_qg => bool].
 */
function getQgMaxedMap($pdo, $id_player, $all_qg_images) {
    // ---- Bâtiments : paliers Niveau/TownHallLevel par TID ----
    $building_rows_by_tid = [];
    foreach ($pdo->query("SELECT TID, Niveau, TownHallLevel FROM buildings")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $building_rows_by_tid[$r['TID']][] = ['Niveau' => (int)$r['Niveau'], 'Gate' => (int)$r['TownHallLevel']];
    }

    // Progression du joueur : niveau par (id_building, id_instance)
    $player_buildings = [];
    $stmt_pb = $pdo->prepare("SELECT id_building, id_instance, niveau FROM progress_building WHERE id_player = ?");
    $stmt_pb->execute([$id_player]);
    foreach ($stmt_pb->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $player_buildings[(int)$r['id_building']][(int)$r['id_instance']] = (int)$r['niveau'];
    }

    // Mapping TID (colonne townhall_levels) -> id_building
    $tid_to_id = [];
    foreach ($pdo->query("SELECT id, TID FROM buildingid")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $tid_to_id[$r['TID']] = (int)$r['id'];
    }

    // Nombre CUMULÉ d'instances débloquées par QG, par colonne/TID (même table/logique
    // que la section BÂTIMENTS de update_qg.php).
    $th_rows = $pdo->query("SELECT * FROM townhall_levels ORDER BY TownHallLevel ASC")->fetchAll(PDO::FETCH_ASSOC);
    $colonnes_ignorees = [
        'TownHallLevel', 'XP', 'RequiredBuilding',
        'RequiredBuildingLevel', 'RequiredTroopLevel', 'MaterialSlots'
    ];

    // Pièges spéciaux (Mine / Super mine / Électromine) : un seul niveau de RECHERCHE
    // s'applique à TOUTES les instances placées sur la base (voir MINE_TIDS / $mine_canonical
    // dans queries.php, et la note dans upgrade_building.php). Le nombre d'"instances" dans
    // townhall_levels pour ces TID représente le nombre de mines posables, PAS un nombre de
    // niveaux à monter individuellement : on ne doit donc PAS exiger que CHAQUE instance ait
    // atteint le niveau max, seulement que la meilleure instance existante (celle qui porte le
    // vrai niveau de recherche) l'ait atteint.
    $mine_tids = ['TID_TRAP_MINE', 'TID_TRAP_TANK_MINE', 'TID_TRAP_SHOCK_MINE'];

    // ---- Troupes / Capacités de canonnière / Héros : paliers Niveau/UpgradeHouseLevel par TID ----
    $char_rows_by_tid = [];
    foreach ($pdo->query("SELECT TID, Niveau, UpgradeHouseLevel FROM characters")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $char_rows_by_tid[$r['TID']][] = ['Niveau' => (int)$r['Niveau'], 'Gate' => (int)$r['UpgradeHouseLevel']];
    }

    $chars = $pdo->query("SELECT id, TID, Class, HQUnlock FROM characterid WHERE Class IN ('Troupe','Spell','Hero')")->fetchAll(PDO::FETCH_ASSOC);

    // IMPORTANT : characters.UpgradeHouseLevel n'est PAS exprimé en niveau de QG pour les
    // Troupes/Capacités de canonnière — c'est le niveau d'ARSENAL requis (id_building 12,
    // TID_BUILDING_LABORATORY), sur une toute autre échelle numérique que le QG (ex : Arsenal
    // niveau 1 nécessite QG4, niveau 2 nécessite QG5...). Utiliser directement N (le QG) comme
    // palier serait donc totalement incohérent (bien trop permissif) — voir le commentaire
    // "Le vrai plafond du moment..." dans getFilteredUnits() de queries.php. Pour "simuler QG N",
    // on doit donc d'abord simuler le plafond d'Arsenal ATTEIGNABLE à ce QG N (même logique
    // maxLevelAtThreshold que pour les bâtiments), puis l'utiliser comme palier pour ces
    // classes. Les Héros, eux, sont gated directement par le QG (characters.UpgradeHouseLevel
    // vaut d'ailleurs toujours 0 pour les Héros dans cette table — donc en pratique niveau_max
    // = niveau_max_absolu pour eux, comme dans le vrai calcul de queries.php).
    $arsenal_rows = $building_rows_by_tid['TID_BUILDING_LABORATORY'] ?? [];

    $player_chars = [];
    $stmt_pc = $pdo->prepare("SELECT id_character, niveau FROM progress_character WHERE id_player = ?");
    $stmt_pc->execute([$id_player]);
    foreach ($stmt_pc->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $player_chars[(int)$r['id_character']] = (int)$r['niveau'];
    }

    $result = [];
    foreach ($all_qg_images as $qg_row) {
        $N  = (int)$qg_row['Niveau'];
        $ok = true;

        // -- Bâtiments débloqués à ce QG --
        $th_row = null;
        foreach ($th_rows as $row) {
            if ((int)$row['TownHallLevel'] === $N) { $th_row = $row; break; }
        }
        if ($th_row) {
            foreach ($th_row as $col => $val) {
                if (in_array($col, $colonnes_ignorees, true)) continue;
                if (!isset($tid_to_id[$col])) continue; // colonne sans équivalent buildingid
                $nb_instances = (int)$val;
                if ($nb_instances <= 0) continue;

                $id_building = $tid_to_id[$col];
                $max_at_n    = maxLevelAtThreshold($building_rows_by_tid[$col] ?? [], $N);
                if ($max_at_n <= 0) continue;

                if (in_array($col, $mine_tids, true)) {
                    // Mines : on prend le meilleur niveau parmi les instances existantes
                    // (équivalent de $mine_canonical dans queries.php), pas une exigence
                    // par instance.
                    $best_niveau = !empty($player_buildings[$id_building]) ? max($player_buildings[$id_building]) : 0;
                    if ($best_niveau < $max_at_n) { $ok = false; break 2; }
                    continue;
                }

                for ($inst = 1; $inst <= $nb_instances; $inst++) {
                    $niv = $player_buildings[$id_building][$inst] ?? 0;
                    if ($niv < $max_at_n) { $ok = false; break 2; }
                }
            }
        }

        // -- Troupes / Capacités de canonnière / Héros débloqués à ce QG --
        if ($ok) {
            // Plafond d'Arsenal simulé "si on était à ce QG" (voir note plus haut) : sert de
            // palier pour les Troupes/Capacités de canonnière, PAS pour les Héros.
            $arsenal_max_at_n = maxLevelAtThreshold($arsenal_rows, $N);

            foreach ($chars as $c) {
                if ((int)$c['HQUnlock'] > $N) continue;

                $is_hero_class = (strcasecmp(trim($c['Class'] ?? ''), 'Hero') === 0);
                $gate          = $is_hero_class ? $N : $arsenal_max_at_n;

                $max_at_n = maxLevelAtThreshold($char_rows_by_tid[$c['TID']] ?? [], $gate);
                if (empty($char_rows_by_tid[$c['TID']])) continue; // TID inconnu de la table characters
                // Comme dans getFilteredUnits() (queries.php) : si aucun palier n'est encore
                // satisfait (ex. Arsenal pas encore construit), le niveau max retombe à 1
                // (le personnage reste utilisable à son niveau de base), jamais à 0.
                $max_at_n = max(1, $max_at_n);
                $niv = $player_chars[(int)$c['id']] ?? 0;
                if ($niv < $max_at_n) { $ok = false; break; }
            }
        }

        $result[$N] = $ok;
    }

    return $result;
}

/**
 * Bandeau horizontal scrollable listant tous les niveaux de QG (visuels de townhall_levels
 * via $all_qg_images, voir queries.php), affiché juste sous le Tableau de Bord principal.
 * Une pastille apparaît sur chaque niveau :
 *   - images/icons/notcheck.png : QG pas encore atteint (niveau > QG actuel du joueur) ;
 *   - images/icons/speedup.png  : QG actuel du joueur ;
 *   - images/icons/check.png    : QG déjà dépassé ET entièrement maxé à ce palier (voir
 *     getQgMaxedMap()) ;
 *   - rien : QG déjà dépassé mais pas encore entièrement maxé à ce palier.
 */
function renderQgProgressStrip($all_qg_images, $qg_maxed_map, $current_qg) {
    if (empty($all_qg_images)) return;

    $current_qg = (int)$current_qg;

    echo "<div class='qg-strip-section'>";
    echo "<h3 class='dash-section-title'>🏛️ Progression par QG</h3>";
    echo "<div class='qg-progress-strip'>";

    foreach ($all_qg_images as $row) {
        $niveau = (int)$row['Niveau'];
        $img    = htmlspecialchars($row['ExportName'] ?: 'default-building', ENT_QUOTES);

        $is_future  = ($niveau > $current_qg);
        $is_current = ($niveau === $current_qg);
        $is_maxed   = !$is_future && !empty($qg_maxed_map[$niveau]);

        if ($is_future) {
            $badge_src = 'images/icons/notcheck.png';
            $badge_alt = 'QG non atteint';
        } elseif ($is_maxed) {
            // La coche prime sur la pastille "QG actuel" : si tout est déjà maxé à ce
            // palier (y compris pour le QG actuel du joueur), on affiche check.png.
            $badge_src = 'images/icons/check.png';
            $badge_alt = 'Maxé';
        } elseif ($is_current) {
            $badge_src = 'images/icons/speedup.png';
            $badge_alt = 'QG actuel';
        } else {
            $badge_src = null;
            $badge_alt = '';
        }

        $card_class = 'qg-strip-card';
        if ($is_current) $card_class .= ' qg-strip-card-current';
        if ($is_future)  $card_class .= ' qg-strip-card-future';
        if ($is_maxed)   $card_class .= ' qg-strip-card-maxed';

        $badge_html = $badge_src
            ? "<img class='qg-strip-check' src='{$badge_src}' alt='" . htmlspecialchars($badge_alt, ENT_QUOTES) . "'>"
            : "";

        // Fallback en cascade .webp -> .png -> default-building.png : on ne connaît pas
        // avec certitude l'extension réelle utilisée pour les visuels de QG, donc on essaie
        // les deux avant de retomber sur l'image générique (même ExportName que buildings).
        echo "
        <div class='{$card_class}'>
            <div class='qg-strip-img-wrap'>
                <img class='qg-strip-img' src='images/{$img}.webp' alt='QG {$niveau}' onerror=\"this.onerror=function(){this.src='images/default-building.png';this.onerror=null;};this.src='images/{$img}.png';\">
                {$badge_html}
            </div>
            <div class='qg-strip-level'>QG {$niveau}</div>
        </div>";
    }

    echo "</div>"; // .qg-progress-strip
    echo "</div>"; // .qg-strip-section
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
        $label   = htmlspecialchars($item['label'] ?? '');
        $sub     = htmlspecialchars($item['sub'] ?? '');
        $tab     = $item['tab'] ?? '';
        $icon    = $item['icon'] ?? '📁';
        // 'locked' : quand présent et vrai, la carte n'est plus cliquable et affiche
        // un cadenas (même principe que les entrées verrouillées de la sidebar).
        $locked  = !empty($item['locked']);
        $tooltip = htmlspecialchars($item['lock_tooltip'] ?? '');

        $card_classes = 'category-nav-card' . ($locked ? ' locked' : '');
        $onclick_attr = $locked ? '' : " onclick=\"showTab('{$tab}')\"";
        $title_attr   = $locked ? " title=\"{$tooltip}\"" : "";
        $lock_html    = $locked ? " <span class='menu-lock'><img src='images/icons/Lock.png'></span>" : "";

        echo "
        <div class='{$card_classes}'{$onclick_attr}{$title_attr}>
            <div class='category-nav-card-main'>
                <div class='overview-card-icon'>{$icon}</div>
                <div>
                    <div class='category-nav-card-label'>{$label}{$lock_html}</div>";
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

/* ==========================================================================
   ÉVÉNEMENTS EN COURS (bannière "event-panel" à côté du Tableau de Bord)
   --------------------------------------------------------------------------
   Schéma attendu (voir demande) :
     - eventid(id PK, TID FK->texts.TID, HQUnlock, ExportName)
     - tmob(id FK->eventid.id, debut DATETIME, end DATETIME)
   Un événement est "actif" quand NOW() est compris entre debut et end.
   ========================================================================== */

/**
 * Récupère les événements actuellement actifs (debut <= NOW() <= end),
 * triés par date de fin la plus proche en premier (l'urgent en haut de liste).
 */
/**
 * Événements irréguliers (programmés dans tmob) + événements récurrents
 * (hebdomadaires, calculés à la volée depuis eventid — voir plus bas).
 *
 * Colonnes attendues :
 *   - tmob(id, debut, end NULL-able, gba, troop1, troop2)
 *     gba/troop1/troop2 stockent le TID d'un characterid (pas l'icône
 *     directement) : on va chercher l'IconExportName via jointure.
 *   - eventid(id, TID, hqunlock, ExportName, recurring_weekday, recurring_start, recurring_end)
 *     recurring_weekday : liste de jours séparés par des virgules, ex "1,5"
 *     (0=Lundi ... 6=Dimanche, un événement peut tomber sur plusieurs jours
 *     dans la semaine — ex mardi+samedi = "1,5"). NULL = événement non
 *     récurrent (géré via tmob).
 *
 * Chaque événement retourné a une clé 'has_end' : false => pas de compte à
 * rebours à afficher (événement permanent / sans date de fin connue).
 */
function getActiveEvents($pdo) {
    global $selected_lang;

    // --- 1) Événements irréguliers programmés dans tmob ---
    $sql = "SELECT ev.id, ev.TID, ev.hqunlock, ev.ExportName,
                   tm.debut, tm.`end`,
                   gba_c.IconExportName AS gba_icon,
                   t1_c.IconExportName  AS troop1_icon,
                   t2_c.IconExportName  AS troop2_icon,
                   IFNULL(t.$selected_lang, ev.TID) AS nom
            FROM tmob tm
            INNER JOIN eventid ev ON ev.id = tm.id
            LEFT JOIN texts t ON t.TID = ev.TID COLLATE utf8mb4_unicode_ci
            LEFT JOIN characterid gba_c ON gba_c.TID = tm.gba    COLLATE utf8mb4_unicode_ci
            LEFT JOIN characterid t1_c  ON t1_c.TID  = tm.troop1 COLLATE utf8mb4_unicode_ci
            LEFT JOIN characterid t2_c  ON t2_c.TID  = tm.troop2 COLLATE utf8mb4_unicode_ci
            WHERE tm.debut <= NOW() AND (tm.`end` IS NULL OR tm.`end` >= NOW())";
    $stmt = $pdo->query($sql);
    $events = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($events as &$e) {
        $e['has_end'] = ($e['end'] !== null);
        $e['is_recurring'] = false;
    }
    unset($e);

    // --- 2) Événements récurrents (hebdomadaires) : pas de ligne dans tmob,
    //        on calcule "actif ou pas" directement depuis eventid. Un même
    //        événement peut revenir plusieurs jours dans la semaine
    //        (recurring_weekday = liste "1,5" par ex.), ET la plage horaire
    //        peut chevaucher minuit (ex: 10:00:00 -> 09:59:59 le lendemain,
    //        comme sur GEARHEART/IMITATION/STRIKEBACK/TROPICAL/VOLCANO).
    //        On récupère toutes les définitions récurrentes et on évalue
    //        l'horaire en PHP plutôt qu'en SQL, plus simple à lire/déboguer.
    $has_recurring_cols = admin_table_has_column($pdo, 'eventid', 'recurring_weekday');
    if ($has_recurring_cols) {
        $sql_rec = "SELECT ev.id, ev.TID, ev.hqunlock, ev.ExportName,
                           ev.recurring_weekday, ev.recurring_start, ev.recurring_end,
                           IFNULL(t.$selected_lang, ev.TID) AS nom
                    FROM eventid ev
                    LEFT JOIN texts t ON t.TID = ev.TID COLLATE utf8mb4_unicode_ci
                    WHERE ev.recurring_weekday IS NOT NULL AND ev.recurring_weekday <> ''";
        $stmt_rec = $pdo->query($sql_rec);
        $definitions = $stmt_rec ? $stmt_rec->fetchAll(PDO::FETCH_ASSOC) : [];

        $now = new DateTime();
        foreach ($definitions as $r) {
            $eval = evaluateRecurringEvent($r['recurring_weekday'], $r['recurring_start'], $r['recurring_end'], $now);
            if (!$eval['active']) continue;

            $events[] = [
                'id'          => $r['id'],
                'TID'         => $r['TID'],
                'hqunlock'    => $r['hqunlock'],
                'ExportName'  => $r['ExportName'],
                'debut'       => null,
                'end'         => $eval['end_datetime'],
                'has_end'     => ($eval['end_datetime'] !== null),
                'is_recurring'=> true,
                'gba_icon'    => null,
                'troop1_icon' => null,
                'troop2_icon' => null,
                'nom'         => $r['nom'],
            ];
        }
    }

    // Tri : ceux qui se terminent bientôt en premier, permanents à la fin.
    usort($events, function ($a, $b) {
        if ($a['has_end'] && $b['has_end']) return strtotime($a['end']) <=> strtotime($b['end']);
        if ($a['has_end'] xor $b['has_end']) return $a['has_end'] ? -1 : 1;
        return 0;
    });

    return $events;
}

/**
 * Détermine si un événement récurrent est actif "maintenant", et calcule sa
 * date de fin réelle (calendaire) pour le compte à rebours — en gérant le
 * cas où la plage horaire chevauche minuit (recurring_end < recurring_start,
 * ex: 10:00:00 -> 09:59:59 le jour suivant).
 *
 * @param string      $weekdaysCsv  ex "1,5" — 0=Lundi ... 6=Dimanche (convention MySQL WEEKDAY())
 * @param string      $start        "HH:MM:SS"
 * @param string|null $end          "HH:MM:SS" ou null (pas de fin affichée)
 * @param DateTime    $now
 * @return array{active: bool, end_datetime: ?string}
 */
function evaluateRecurringEvent($weekdaysCsv, $start, $end, DateTime $now) {
    $weekdays = array_map('intval', array_filter(array_map('trim', explode(',', $weekdaysCsv)), fn($v) => $v !== ''));
    if (empty($weekdays) || !$start) {
        return ['active' => false, 'end_datetime' => null];
    }

    $todayIdx     = ((int)$now->format('N')) - 1;      // 0=Lundi ... 6=Dimanche
    $yesterdayIdx = ($todayIdx + 6) % 7;                // jour précédent, pour le cas "chevauche minuit"
    $curTime      = $now->format('H:i:s');
    $wraps        = ($end !== null && $end < $start);   // ex: start=10:00:00, end=09:59:59 -> traverse minuit

    // Cas A : l'événement a démarré AUJOURD'HUI (jour de la semaine correspondant + heure >= début)
    if (in_array($todayIdx, $weekdays, true) && $curTime >= $start) {
        if (!$wraps) {
            // Même jour : actif jusqu'à $end (ou sans fin si $end est NULL)
            if ($end === null || $curTime <= $end) {
                $end_datetime = $end !== null ? ($now->format('Y-m-d') . ' ' . $end) : null;
                return ['active' => true, 'end_datetime' => $end_datetime];
            }
            return ['active' => false, 'end_datetime' => null];
        }
        // Chevauche minuit : actif jusqu'à $end demain
        $tomorrow = (clone $now)->modify('+1 day')->format('Y-m-d');
        return ['active' => true, 'end_datetime' => $tomorrow . ' ' . $end];
    }

    // Cas B : l'événement a démarré HIER et chevauche minuit -> encore actif
    // aujourd'hui si l'heure actuelle n'a pas dépassé $end.
    if ($wraps && in_array($yesterdayIdx, $weekdays, true) && $curTime <= $end) {
        return ['active' => true, 'end_datetime' => $now->format('Y-m-d') . ' ' . $end];
    }

    return ['active' => false, 'end_datetime' => null];
}

/**
 * Petit utilitaire : vérifie si une colonne existe (pour rester compatible
 * si les colonnes "recurring_*" n'ont pas encore été ajoutées à eventid).
 */
function admin_table_has_column($pdo, $table, $column) {
    static $cache = [];
    $key = $table . '.' . $column;
    if (!isset($cache[$key])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        $cache[$key] = (int)$stmt->fetchColumn() > 0;
    }
    return $cache[$key];
}

/**
 * Affiche la bannière "event-panel" : liste scrollable des événements actifs
 * (image + titre + compte à rebours j-h-m-s si une fin est connue, mis à jour
 * côté JS via data-end="<timestamp ms>" — voir script.js). Les événements
 * "gba" (event 5) et "troopmania" (event 12) affichent en plus 1 ou 2
 * icônes en incrustation, en bas à droite de la bannière (50x50px).
 */
function renderEventPanel($events) {
    echo "<div class='event-panel'>";
    echo "
        <div class='event-panel-header'>
            <span class='event-panel-icon'>🎉</span>
            <h2>Événements en cours</h2>
        </div>";
    echo "<div class='event-list'>";

    if (empty($events)) {
        echo "<div class='event-empty'>Aucun événement en cours pour le moment.</div>";
    } else {
        foreach ($events as $ev) {
            $nom     = htmlspecialchars($ev['nom']);
            $img     = "images/event/" . htmlspecialchars($ev['ExportName']) . ".png";
            $endAttr = '';
            // event-ending-soon (surbrillance orange) réservé aux événements récurrents,
            // demandé explicitement : les événements irréguliers (sans recurring_start/end,
            // gérés via tmob) gardent toujours l'affichage neutre.
            $recurringAttr = " data-recurring='" . ($ev['is_recurring'] ? '1' : '0') . "'";

            if ($ev['has_end']) {
                $endMs   = strtotime($ev['end']) * 1000;
                $endAttr = " data-end='{$endMs}'";
            }

            echo "<div class='event-card'{$endAttr}{$recurringAttr}>";
            echo "<div class='event-card-img-wrap'>";
            echo "<img class='event-card-img' src='{$img}' alt='{$nom}' onerror=\"this.style.visibility='hidden'\">";

            if (!empty($ev['gba_icon'])) {
                $gbaImg = "images/characters/TempSpell/" . htmlspecialchars($ev['gba_icon']) . ".png";
                echo "<img class='event-overlay event-overlay-gba' src='{$gbaImg}' alt='' onerror=\"this.style.display='none'\">";
            }
            if (!empty($ev['troop1_icon'])) {
                $t1Img = "images/characters/Troupe/" . htmlspecialchars($ev['troop1_icon']) . ".png";
                echo "<img class='event-overlay event-overlay-troop1' src='{$t1Img}' alt='' onerror=\"this.style.display='none'\">";
            }
            if (!empty($ev['troop2_icon'])) {
                $t2Img = "images/characters/Troupe/" . htmlspecialchars($ev['troop2_icon']) . ".png";
                echo "<img class='event-overlay event-overlay-troop2' src='{$t2Img}' alt='' onerror=\"this.style.display='none'\">";
            }

            echo "</div>"; // .event-card-img-wrap

            echo "<div class='event-card-info'>";
            echo "<div class='event-card-title'>{$nom}</div>";
            if ($ev['has_end']) {
                echo "<div class='event-card-countdown'>--j --h --m --s</div>";
            } else {
                echo "<div class='event-card-countdown event-permanent'></div>";
            }
            echo "</div>"; // .event-card-info

            echo "</div>"; // .event-card
        }
    }

    echo "</div></div>";
}