<?php
echo '<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">';

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
function renderBuildingsTable($buildings_list) {
    foreach ($buildings_list as $category => $buildings) {
        if (empty($buildings)) continue;

        echo "<div class='buildings-grid'>";

        foreach ($buildings as $b) {
            // Sécurisation avec ?? pour éviter les Warnings
            $tid      = $b['TID'] ?? '';
            $nom      = htmlspecialchars($b['nom_building'] ?? '???');
            $inst     = (int)($b['id_instance'] ?? 1);
            $niv      = (int)($b['niveau_actuel'] ?? 0);
            $max      = (int)($b['niveau_max'] ?? 1);
            $img      = $b['ExportName'] ?? 'default';
            $debloque = (int)($b['Debloque'] ?? 0);
            $is_maxed = ($niv >= $max);

            // L'instance reste purement interne (data-instance), jamais affichée à l'écran
            $safe_id = "bld-" . preg_replace('/[^a-zA-Z0-9]/', '', $tid) . "-{$inst}";

            // Libellé du bouton : Construire si jamais débloqué, sinon Améliorer
            if ($is_maxed) {
                $btn_text = "Max !";
            } elseif ($debloque === 0) {
                $btn_text = "Construire";
            } else {
                $btn_text = "Améliorer";
            }

            $display_text = ($niv === 0) ? "Non construit" : "Niveau {$niv}";

            echo "
            <div class='building-card' id='card-{$safe_id}' data-tid='{$tid}' data-instance='{$inst}'>
                <div class='building-card-visual'>
                    <img class='building-card-img' src='images/{$img}.WEBP' alt='{$nom}' onerror=\"this.src='images/default.png'\">
                </div>

                <div class='building-card-info'>
                    <span class='building-card-name'>{$nom}</span>
                    <span class='building-card-level' id='lvl-{$safe_id}'>{$display_text} / {$max}</span>
                </div>";

            if (!$is_maxed) {
                $cout_bois   = $b['BuildCostWood']  ?? 0;
                $cout_pierre = $b['BuildCostStone'] ?? 0;
                $cout_fer    = $b['BuildCostIron']  ?? 0;
                $temps_j     = (int)($b['BuildTimeD'] ?? 0);
                $temps_h     = (int)($b['BuildTimeH'] ?? 0);
                $temps_m     = (int)($b['BuildTimeM'] ?? 0);
                $temps_txt   = trim(($temps_j > 0 ? "{$temps_j}j " : "") . ($temps_h > 0 ? "{$temps_h}h " : "") . ($temps_m > 0 ? "{$temps_m}m" : ""));
                if ($temps_txt === '') $temps_txt = "3 sec";

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

            $disabled = $is_maxed ? "disabled" : "";
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

    foreach ($buildings as $b) {
        $total_current += $b['niveau_actuel'];
        $total_max += $b['niveau_max']; 
    }

    $percent = ($total_max > 0) ? round(($total_current / $total_max) * 100, 1) : 0;

    return [
        'current' => $total_current,
        'max'     => $total_max,
        'percent' => $percent
    ];
}

function renderStatsSidebar($title, $buildings_data, $stats) {
    // --- Total restant (coût + temps) pour amener TOUTE la catégorie au niveau max ---
    $total_wood = 0;
    $total_stone = 0;
    $total_iron = 0;
    $total_seconds = 0;
    foreach ($buildings_data as $b) {
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
    
    if ($total_wood > 0 || $total_stone > 0 || $total_iron > 0 || $total_seconds > 0) {
        $total_time_txt = formatSecondsToText($total_seconds);
        echo "<div class='sidebar-total-remaining'>
                <div class='sidebar-total-title'>Total restant pour tout terminer</div>
                <div class='sidebar-total-costs'>
                    <span class='sidebar-total-item'><img src='images/icons/Wood.png' alt='Bois'>" . number_format($total_wood, 0, ',', ' ') . "</span>
                    <span class='sidebar-total-item'><img src='images/icons/Stone.png' alt='Pierre'>" . number_format($total_stone, 0, ',', ' ') . "</span>
                    <span class='sidebar-total-item'><img src='images/icons/Iron.png' alt='Fer'>" . number_format($total_iron, 0, ',', ' ') . "</span>
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
 *
 * $house_levels attend un tableau ['arsenal' => int, 'proto_factory' => int]
 * représentant le niveau RÉEL construit par le joueur pour ces deux bâtiments.
 * (Non pertinent pour les listes d'Officiers/Héros, laisser à [] dans ce cas.)
 */
function renderUnitsTable($data, $progress = [], $house_levels = []) {
    if (empty($data)) {
        echo "<p class='empty-msg'>Aucune unité débloquée.</p>";
        return;
    }

    // Chaque liste passée à cette fonction ($heros_list, $officers_list, $troupes_list,
    // $proto_list) est homogène en Class : on choisit donc le conteneur global
    // (grille "unit-card" ou grille "troop-card") une seule fois, à partir du 1er élément.
    $premiere_class      = trim($data[0]['Class'] ?? '');
    $is_troop_list       = (strcasecmp($premiere_class, 'Troupe') === 0);
    $is_prototroop_list  = (strcasecmp($premiere_class, 'Proto') === 0);
    $use_troop_design    = ($is_troop_list || $is_prototroop_list);

    echo $use_troop_design
        ? "<div class='troops-grid'>"
        : "<div class='main-layout-container' style='display: flex; gap: 20px; align-items: flex-start; width: 100%;'><div class='units-grid-left' style='display: flex; flex-direction: row; flex-wrap: wrap; gap: 15px; flex: 1; justify-content: center;'>";

    foreach ($data as $u) {
        $tid         = $u['TID'];
        $safe_id     = str_replace(" ", "-", $u['nom']);
        $max_lvl     = intval($u['niveau_autorise'] ?? 1);
        $current_lvl = $progress[$tid] ?? $u['niveau_joueur'] ?? 1;
        if ($current_lvl === 0) $current_lvl = 1;

        $is_officer    = !empty($u['is_officer']);
        $is_troop      = (strcasecmp(trim($u['Class'] ?? ''), 'Troupe') === 0);
        $is_prototroop = (strcasecmp(trim($u['Class'] ?? ''), 'Proto') === 0);

        // =====================================================================
        // DESIGN "TROUPE" / "PROTO-TROUPE" (repris de l'ex-renderTroopsTable)
        // =====================================================================
        if ($is_troop || $is_prototroop) {
            $safe_id_trp = "trp-" . preg_replace('/[^a-zA-Z0-9]/', '', $tid);
            $nom         = htmlspecialchars($u['nom'] ?? '???');
            $icon        = $u['IconExportName'] ?? 'default';
            $class_css   = htmlspecialchars($u['Class'] ?? '');
            $niv         = $current_lvl;
            $max         = $max_lvl;
            $is_maxed    = ($niv >= $max);

            // --- Vérification du bâtiment requis pour le PROCHAIN niveau ---
            // (Arsenal pour les Troupes, Atelier de Proto-troupes pour les Proto)
            $required_house_level = (int)($u['next_cost']['UpgradeHouseLevel'] ?? 0);
            $player_house_level   = $is_prototroop
                ? (int)($house_levels['proto_factory'] ?? 0)
                : (int)($house_levels['arsenal'] ?? 0);
            $house_label = $is_prototroop ? "Atelier de Proto-troupes" : "Arsenal";
            $house_ok    = ($required_house_level <= $player_house_level);

            echo "
            <div class='troop-card' id='card-{$safe_id_trp}' data-tid='{$tid}'>
                <div class='troop-card-visual'>
                    <img class='troop-card-img' src='images/characters/{$class_css}/{$icon}.png' alt='{$nom}' onerror=\"this.src='images/default-building.png'\">
                </div>

                <div class='troop-card-info'>
                    <span class='troop-card-name'>{$nom}</span>
                    <span class='troop-card-level' id='lvl-{$safe_id_trp}'>Niveau {$niv} / {$max}</span>
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

        echo "<div class='unit-card {$locked_class}' id='card-{$safe_id}' data-tid='{$tid}' data-id-character='{$u['id_character']}'>";

        // Overlay de déblocage si nécessaire
        if ($is_locked) {
            echo "<div class='unlock-overlay'>
                    <button class='btn-unlock-officer' onclick=\"unlockOfficer({$u['id_character']})\">
                        Débloquer l'Officier
                    </button>
                  </div>";
        }

        echo "<div class='unit-info-wrapper'>
            <div class='unit-img-wrapper'>
                <img class='img-unit' src='images/characters/" . htmlspecialchars($u['Class']) . "/{$u['IconExportName']}.png' alt='" . htmlspecialchars($u['nom']) . "'>
            </div>
            <div class='unit-details' style='display: flex; flex-direction: column; width: 100%;'>
                <div class='unit-name' style='font-weight: bold; font-size: 1.1em; color: #ffffff;'>" . htmlspecialchars($u['nom']) . "</div>
                <div class='lvl-display' id='lvl-{$safe_id}' style='color: #1abc9c; font-weight: bold;'>{$display_text}</div>
            </div>
        </div>";

        if ($is_officer) {
            // Rendu des Talents
            $count = $u['total_talents_unlocked'] ?? 0;
            $imageFile = "talent_{$count}_icon.png";
            $can_upgrade = ($count < 5);

            echo "<div class='officer-talent'>
                <img src='images/icons/{$imageFile}' style='width: 80px;' alt='Talents débloqués: {$count}'>
                <div class='talent-status'>
                    <p class='talent-count'>Talents débloqués : {$count}/5</p>
                    <button class='btn-upgrade-talent' data-character='{$u['id_character']}' data-ability='{$u['next_talent_id']}' " . (!$can_upgrade ? "disabled" : "") . " onclick=\"triggerUpgradeTalent(this)\">
                        " . ($can_upgrade ? "Améliorer (Talent " . ($count + 1) . ")" : "Max !") . "
                    </button>
                </div>
            </div>";

            // Rendu des Capacités
            echo "<div class='officer-ability'>";
            foreach (['passive', 'active'] as $type) {
                $talent = $u['abilities'][$type] ?? null;
                if (empty($talent) || empty($talent['id_ability'])) {
                    echo "<div class='officer-ability-info-placeholder'>
                            <span style='color:#7f8c8d; font-weight:600; font-size: 0.9em;'>Capacité {$type} : Aucune</span>
                        </div>";
                    continue;
                }

                $ab_id      = $talent['id_ability'];
                $ab_icon    = $talent['IconExportName'];
                $ab_tid     = $talent['TID'];
                $safe_ab_id = "ab-" . preg_replace('/[^a-zA-Z0-9]/', '', $ab_tid);
                $ab_lvl     = (int)$talent['current_level'];

                $next_lvl_data = null;
                if (!empty($talent['levels'])) {
                    foreach ($talent['levels'] as $row) {
                        if ((int)$row['Niveau'] === $ab_lvl) {
                            $next_lvl_data = $row;
                            break;
                        }
                    }
                }

                $is_max = ($next_lvl_data === null);
                $cost_display = "";
                if ($next_lvl_data) {
                    $resource_name = htmlspecialchars($next_lvl_data['UpgradeResource']);
                    $cost_display  = "<img src='images/{$resource_name}.png' alt='{$resource_name}' style='width: 20px; vertical-align: middle; margin-left: 4px;'>" . $next_lvl_data['UpgradeCost'];
                }

                echo "<div class='officer-ability-row' data-id-ability='{$ab_id}'>
                        <div class='officer-ability-info'>
                            <div class='officer-ability-header'>
                                <div><span class='officer-ability-name'>{$talent['nom']}</span> <span class='officer-ability-type'>[{$type}]</span></div>
                                <div><img src='images/characters/Officier/talent/{$ab_icon}.webp' class='officer-ability-icon'></div>
                            </div>
                            <span class='officer-ability-level'>Niveau {$ab_lvl} " . ($is_max ? "" : $cost_display) . "</span>
                        </div>
                        <div class='officer-ability-upgrade'>";
                            if (!$is_max) {
                                $req_h    = (int)$next_lvl_data['HeroLevel'];
                                $can_up   = ($current_lvl >= $req_h);
                                $btn_txt  = $can_up ? "Améliorer" : "Niv. {$req_h} requis";
                                $disabled = $can_up ? "" : "disabled style='opacity:0.6; cursor:not-allowed;'";

                                echo "<button class='btn-upgrade-ability' {$disabled} data-character='{$u['id_character']}' data-ability='{$ab_id}' onclick=\"triggerUpgradeAbility(this, '{$ab_id}', '{$safe_ab_id}')\">{$btn_txt}</button>";
                            } else {
                                echo "<span class='officer-ability-lvl-max'>Max !</span>";
                            }
                        echo "</div>
                    </div>";
            }
            echo "</div>";
        } else {
            echo "<div class='building-action'>
                    <button class='btn-upgrade' onclick=\"triggerUpgradeCharacter('{$tid}', '{$safe_id}', {$max_lvl})\">Améliorer</button>
                </div>";
        }
        echo "</div>";
    }

    echo $use_troop_design ? "</div>" : "</div></div>";
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

    // Récupération des niveaux de capacités depuis la base de données
    $stmt_prog = $pdo->prepare("SELECT id_character, id_ability, niveau FROM progress_ability WHERE id_player = ?");
    $stmt_prog->execute([$id_player]);
    $abilities_progress = [];
    while ($row = $stmt_prog->fetch(PDO::FETCH_ASSOC)) {
        $abilities_progress[(int)$row['id_character']][(int)$row['id_ability']] = (int)$row['niveau'];
    }

    $global_current = 0;
    $global_max = 0; 
    $rows_html = "";

    foreach ($officers_list as $u) {
        $char_id = (int)$u['id_character'];
        
        $talents_count = $u['total_talents_unlocked'] ?? 0;
        
        // On initialise à NULL pour détecter l'absence de progression
        $passive_lvl = null;
        $active_lvl = null;
        
        if (!empty($u['abilities'])) {
            if (isset($u['abilities']['passive'])) {
                $aid = $u['abilities']['passive']['id_ability'];
                $passive_lvl = $abilities_progress[$char_id][$aid] ?? null; // NULL si non trouvé
            }
            if (isset($u['abilities']['active'])) {
                $aid = $u['abilities']['active']['id_ability'];
                $active_lvl = $abilities_progress[$char_id][$aid] ?? null; // NULL si non trouvé
            }
        }

        // Calcul du progrès : si NULL, on considère que c'est 0 (car pas encore débloqué/initialisé)
        $prog_talents = $talents_count; 
        $prog_passive = ($passive_lvl !== null) ? ($passive_lvl - 1) : 0;
        $prog_active  = ($active_lvl !== null) ? ($active_lvl - 1) : 0;

        $current_sum = $prog_talents + $prog_passive + $prog_active;
        $max_sum = 5 + 14 + 14; 
        
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
function renderEngravingsTable($engravings, $cat_color = "#1abc9c") {
    if (empty($engravings)) {
        echo "<p class='empty-msg'>Aucune gravure n'a été trouvée dans cette catégorie.</p>";
        return;
    }

    echo "<div class='troops-grid'>";

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
        <div class='troop-card' id='card-{$safe_id}' data-tid='{$tid}' data-id-engraving='{$id_engraving}' style='border-top: 3px solid {$cat_color};'>
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
                    <span class='cost-value' id='cost-{$safe_id}' data-costs='" . htmlspecialchars(json_encode($costs)) . "'>{$cost_display}</span>
                    <span style='font-weight:400; color:#bdc3c7;'>Jetons de recherche</span>
                </span>
            </div>";
        }

        $disabled = $is_maxed ? "disabled" : "";
        echo "
            <div class='troop-card-action'>
                <button class='btn-upgrade' {$disabled}
                        onclick=\"triggerUpgradeEngraving({$id_engraving}, '{$safe_id}', {$max})\">
                    <span class='btn-text'>{$btn_text}</span>
                </button>
            </div>";

        if (!empty($costs)) {
            echo "
            <button type='button' class='engraving-costs-toggle' onclick=\"toggleCostTable('{$safe_id}')\" id='chevron-{$safe_id}'>
                Détail des coûts <span class='chevron-icon'>🔽</span>
            </button>
            <div id='table-cost-{$safe_id}' class='engraving-costs-table'>
                <table>
                    <thead>
                        <tr>
                            <th>Niveau</th>
                            <th>Jetons</th>
                            <th style='text-align:right;'>Statut</th>
                        </tr>
                    </thead>
                    <tbody>";

            foreach ($costs as $q_lvl => $tokens) {
                if ($q_lvl <= $niv) {
                    $status = "✅ Acquis";
                    $row_class = "cost-row-done";
                } elseif ($q_lvl == $niv + 1) {
                    $status = "⏳ Suivant";
                    $row_class = "cost-row-next";
                } else {
                    $status = "🔒 Bloqué";
                    $row_class = "";
                }

                echo "
                        <tr class='{$row_class}'>
                            <td>Niv. {$q_lvl}</td>
                            <td>{$tokens}</td>
                            <td class='status-cell' style='text-align:right;'>{$status}</td>
                        </tr>";
            }

            echo "
                    </tbody>
                </table>
            </div>";
        }

        echo "
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
function renderMainDashboard($stats_buildings, $stats_troupes, $chefs_debloques, $chefs_total) {
    $chefs_percent = ($chefs_total > 0) ? round(($chefs_debloques / $chefs_total) * 100) : 0;

    echo "
    <div class='dashboard-container'>
        <h2>Tableau de Bord</h2>
        <div class='overview-cards-grid'>";

    echo renderOverviewCard(
        '🏗️',
        'Bâtiments',
        $stats_buildings['current'],
        $stats_buildings['max'],
        $stats_buildings['percent'],
        round($stats_buildings['percent']) . '% des niveaux construits',
        'Building-Ressource'
    );

    echo renderOverviewCard(
        '⚔️',
        'Troupes',
        $stats_troupes['current'],
        $stats_troupes['max'],
        $stats_troupes['percent'],
        round($stats_troupes['percent']) . '% de progression',
        'Character-Troop'
    );

    echo renderOverviewCard(
        '🎖️',
        'Chefs débloqués',
        $chefs_debloques,
        $chefs_total,
        $chefs_percent,
        "{$chefs_debloques} / {$chefs_total} officiers débloqués",
        'Character-Leader'
    );

    // Capacité de canonnière : fonctionnalité pas encore développée en base,
    // la carte est affichée en avance (grisée, non cliquable) pour préparer le terrain.
    echo renderOverviewCard(
        '🚤',
        'Capacité de canonnière',
        0,
        0,
        0,
        'Bientôt disponible',
        null,
        true
    );

    echo "
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
            <div class='overview-card-header'>
                <div class='overview-card-icon'>{$icon}</div>
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