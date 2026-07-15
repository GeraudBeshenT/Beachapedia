<?php
/**
 * mass_upgrade_render.php - NOUVELLE VERSION
 * Rendu avec volets, visuels, talents à cocher, et limitations QG
 */

function muFillAllControl(string $label = 'Tout mettre à'): string {
    return "
        <div class='mu-fill-row'>
            <span class='mu-fill-label'>{$label}</span>
            <input type='number' class='mu-fill-input' min='0' placeholder='0'>
            <button type='button' class='mu-fill-btn' onclick='muFillGroup(this)'>Appliquer</button>
        </div>";
}

function resolveImagePath(string $basePathWithoutExt, array $extensions = ['webp', 'png', 'jpg', 'jpeg']): string {
    foreach ($extensions as $ext) {
        $fullPath = __DIR__ . '/' . $basePathWithoutExt . '.' . $ext;
        if (file_exists($fullPath)) {
            return $basePathWithoutExt . '.' . $ext;
        }
    }
    // Rien trouvé sur le disque : on retourne quand même une valeur par défaut cohérente
    return $basePathWithoutExt . '.' . $extensions[0];
}

function getBuildingImage($tid, $niveau = 1) {
    // Récupère l'image du niveau 1 pour les bâtiments
    static $cache = [];
    if (isset($cache[$tid])) return $cache[$tid];

    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT ExportName FROM buildings WHERE TID = ? AND Niveau = 1 LIMIT 1");
        $stmt->execute([$tid]);
        $export = $stmt->fetchColumn() ?: 'default-building';
        $cache[$tid] = resolveImagePath("images/{$export}");
    } catch (Exception $e) {
        $cache[$tid] = "images/default-building.png";
    }
    return $cache[$tid];
}

function getCharacterImage($tid) {
    // Récupère l'icône du personnage
    static $cache = [];
    if (isset($cache[$tid])) return $cache[$tid];

    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT IconExportName, Class FROM characterid WHERE TID = ? LIMIT 1");
        $stmt->execute([$tid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $cache[$tid] = resolveImagePath("images/characters/{$row['Class']}/{$row['IconExportName']}");
        } else {
            $cache[$tid] = "images/default-building.png";
        }
    } catch (Exception $e) {
        $cache[$tid] = "images/default-building.png";
    }
    return $cache[$tid];
}

function getEngravingImage($icon) {
    // Récupère l'icône d'une gravure. Le dashboard (functions.php) sert ces images en
    // .png avec repli .webp côté navigateur (onerror) ; on reproduit le même ordre côté
    // serveur avec resolveImagePath pour éviter un aller-retour HTTP inutile en cas de 404.
    static $cache = [];
    if (isset($cache[$icon])) return $cache[$icon];
    $cache[$icon] = resolveImagePath("images/engravings/{$icon}", ['png', 'webp', 'jpg', 'jpeg']);
    return $cache[$icon];
}

function muRenderBuildingsSection(array $buildings): string {
    $labels = [
        'Ressource' => '💰 Bâtiments économiques',
        'Defense'   => '🛡️ Bâtiments défensifs',
        'Army'      => '⚔️ Bâtiments de renfort',
        'Trap'      => '💣 Pièges',
    ];

    $html = "<div class='mu-section' id='mu-buildings'>";
    $html .= "<div class='mu-section-header'><h3>🏗️ Bâtiments</h3>" . muFillAllControl('Tout mettre à') . "</div>";

    $has_any = false;
    foreach ($labels as $cat => $label) {
        $list = $buildings[$cat] ?? [];
        if (empty($list)) continue;
        $has_any = true;

        $html .= "<div class='mu-subcategory'>";
        $html .= "<div class='mu-subcategory-header'><h4>{$label}</h4>" . muFillAllControl('Tout mettre à') . "</div>";
        $html .= "<div class='mu-grid mu-fillable'>";

        foreach ($list as $b) {
            $tid = htmlspecialchars($b['TID']);
            $nom = htmlspecialchars($b['nom']);
            $max = (int)$b['niveau_max'];
            $nb_instances = count($b['instances']);

            // 🔥 Pour les pièges (Trap) : 1 seule instance
            $is_trap = ($cat === 'Trap');
            if ($is_trap && $nb_instances > 1) {
                $b['instances'] = [reset($b['instances'])]; // On garde que la première
                $nb_instances = 1;
            }

            $img_src = getBuildingImage($tid);

            $html .= "<div class='mu-item mu-fillable' data-tid='{$tid}'>";
            $html .= "<div class='mu-item-header'>";
            $html .= "<img src='{$img_src}' class='mu-item-icon' alt='{$nom}' onerror=\"this.src='images/default-building.png'\"'>";
            $html .= "<div class='mu-item-name-wrapper'><span class='mu-item-name'>{$nom}</span> <span class='mu-item-max'>(max {$max})</span></div>";
            $html .= "</div>";

            // 🔥 Boutons de niveau rapide (1 à niveau_max) : "comme Clash Ninja", appliquent
            // le niveau choisi à TOUTES les instances de cette carte (= même famille de bâtiment,
            // ex. les 4 Scieries d'un coup).
            if ($max > 0) {
                $html .= "<div class='mu-quick-levels'>";
                for ($lvl = 1; $lvl <= $max; $lvl++) {
                    $html .= "<button type='button' class='mu-quick-btn' onclick='muSetFamilyLevel(this, {$lvl})'>{$lvl}</button>";
                }
                $html .= "</div>";
            }

            if ($nb_instances === 1) {
                $inst = $b['instances'][0];
                $html .= "<div class='mu-level-control'>
                    <input type='number' class='mu-input mu-building' min='0' max='{$max}'
                           data-building='{$b['id_building']}' data-instance='{$inst['id_instance']}'
                           value='{$inst['niveau']}' oninput='muSyncLevelControl(this)'>
                    <input type='range' class='mu-slider mu-building-slider' min='0' max='{$max}'
                           data-building='{$b['id_building']}' data-instance='{$inst['id_instance']}'
                           value='{$inst['niveau']}' oninput='muSyncLevelControl(this)'>
                </div>";
            } else {
                $html .= "<div class='mu-instances'>";
                foreach ($b['instances'] as $inst) {
                    $html .= "<label class='mu-instance'>#{$inst['id_instance']}
                        <div class='mu-level-control'>
                            <input type='number' class='mu-input mu-building' min='0' max='{$max}'
                                   data-building='{$b['id_building']}' data-instance='{$inst['id_instance']}'
                                   value='{$inst['niveau']}' oninput='muSyncLevelControl(this)'>
                            <input type='range' class='mu-slider mu-building-slider' min='0' max='{$max}'
                                   data-building='{$b['id_building']}' data-instance='{$inst['id_instance']}'
                                   value='{$inst['niveau']}' oninput='muSyncLevelControl(this)'>
                        </div></label>";
                }
                $html .= "</div>";
            }

            $html .= "</div>";
        }

        $html .= "</div></div>";
    }

    if (!$has_any) {
        $html .= "<p class='mu-empty'>Aucun bâtiment disponible à ce niveau de QG.</p>";
    }

    $html .= "</div>";
    return $html;
}

function muRenderTalentCheckbox($char_id, $talent_id, $talent_num, $is_checked, $talent_name) {
    $disabled = ($talent_num > 1) ? "disabled" : "";
    $style = ($talent_num > 1 && !$is_checked) ? "style='display:none;'" : "";
    return "
        <label class='mu-talent-row' data-char='{$char_id}' data-talent='{$talent_id}' data-talent-num='{$talent_num}' {$style}>
            <input type='checkbox' class='mu-talent-checkbox' data-char='{$char_id}' data-talent='{$talent_id}' data-talent-num='{$talent_num}'
                   onchange='muUpdateTalentVisibility(this)' " . ($is_checked ? "checked" : "") . " {$disabled}>
            <span class='mu-talent-name'>{$talent_name} (Talent {$talent_num})</span>
        </label>";
}

function muRenderCharactersSection(array $characters): string {
    $labels = [
        'Troupe'   => '⚔️ Troupes',
        'Proto'    => '🧪 Proto-troupes',
        'Hero'     => '🦸 Héros',
        'Officier' => '👑 Chefs de bataillon',
        'Spell'    => '🎯 Capacités de canonnière',
    ];

    $html = "<div class='mu-section' id='mu-characters'>";
    $html .= "<div class='mu-section-header'><h3>⚔️ Armée</h3>" . muFillAllControl('Tout mettre à') . "</div>";

    $has_any = false;
    foreach ($labels as $cat => $label) {
        $list = $characters[$cat] ?? [];
        if (empty($list)) continue;
        $has_any = true;

        $html .= "<div class='mu-subcategory'>";
        $html .= "<div class='mu-subcategory-header'><h4>{$label}</h4>";
        if ($cat !== 'Officier') {
            $html .= muFillAllControl('Tout mettre à');
        }
        $html .= "</div>";
        $html .= "<div class='mu-grid mu-fillable'>";

        foreach ($list as $c) {
            $nom = htmlspecialchars($c['nom']);
            $max = (int)$c['niveau_max'];
            $img_src = getCharacterImage($c['TID']);

            $html .= "<div class='mu-item mu-character-item" . ($cat === 'Officier' ? " mu-officer-item'" : "'") . " data-tid='{$c['TID']}'>";
            $html .= "<div class='mu-item-header'>";
            $html .= "<img src='{$img_src}' class='mu-item-icon' alt='{$nom}' onerror=\"this.src='images/default-building.png'\">";
            $html .= "<div class='mu-item-name-wrapper'><span class='mu-item-name'>{$nom}</span> <span class='mu-item-max'>(max {$max})</span></div>";
            $html .= "</div>";

            // 🔥 Gestion différente pour les officiers (cases à cocher pour les talents)
            if ($cat === 'Officier') {
                // Pas de champ niveau pour les officiers
                if (!empty($c['abilities'])) {
                    $html .= "<div class='mu-abilities mu-talents-container'>";
                    $talent_num = 1;
                    foreach ($c['abilities'] as $ab) {
                        if (strpos($ab['kind'], 'Talent') !== false) {
                            $is_checked = ($ab['niveau'] > 0);
                            $html .= muRenderTalentCheckbox($c['id_character'], $ab['id_ability'], $talent_num, $is_checked, htmlspecialchars($ab['nom']));
                            $talent_num++;
                        }
                    }
                    $html .= "</div>";
                }

                // Capacités active/passive (restent en champs numériques)
                if (!empty($c['abilities'])) {
                    $html .= "<div class='mu-abilities mu-capacities-container'>";
                    foreach ($c['abilities'] as $ab) {
                        if (strpos($ab['kind'], 'Capacité') !== false) {
                            $ab_nom = htmlspecialchars($ab['nom']);
                            $ab_kind = htmlspecialchars($ab['kind']);
                            $ab_max = (int)$ab['niveau_max'];
                            $html .= "<label class='mu-ability-row' title='{$ab_kind}'>
                                <span>{$ab_nom}</span>
                                <input type='number' class='mu-input mu-ability' min='0' max='{$ab_max}'
                                       data-character='{$c['id_character']}' data-ability='{$ab['id_ability']}'
                                       value='{$ab['niveau']}'>
                            </label>";
                        }
                    }
                    $html .= "</div>";
                }
            }
            // 🔥 Pour les héros : champ niveau + capacités
            elseif ($cat === 'Hero') {
                $html .= "<input type='number' class='mu-input mu-character' min='0' max='{$max}'
                            data-character='{$c['id_character']}' value='{$c['niveau']}'>";

                if (!empty($c['abilities'])) {
                    $html .= "<div class='mu-abilities'>";
                    foreach ($c['abilities'] as $ab) {
                        $ab_nom = htmlspecialchars($ab['nom']);
                        $ab_kind = htmlspecialchars($ab['kind']);
                        $ab_max = (int)$ab['niveau_max'];
                        $html .= "<label class='mu-ability-row' title='{$ab_kind}'>
                            <span>{$ab_nom}</span>
                            <input type='number' class='mu-input mu-ability' min='0' max='{$ab_max}'
                                   data-character='{$c['id_character']}' data-ability='{$ab['id_ability']}'
                                   value='{$ab['niveau']}'>
                        </label>";
                    }
                    $html .= "</div>";
                }
            }
            // 🔥 Pour les autres (Troupes, Proto, Spell) : champ niveau classique
            else {
                $html .= "<input type='number' class='mu-input mu-character' min='0' max='{$max}'
                            data-character='{$c['id_character']}' value='{$c['niveau']}'>";
            }

            $html .= "</div>";
        }

        $html .= "</div></div>";
    }

    if (!$has_any) {
        $html .= "<p class='mu-empty'>Aucune unité disponible à ce niveau de QG.</p>";
    }

    $html .= "</div>";
    return $html;
}

function muRenderEngravingsSection(array $engravings): string {
    $labels = [
        'Offensive' => '🗡️ Gravures offensives',
        'Defensive' => '🛡️ Gravures défensives',
    ];

    $html = "<div class='mu-section' id='mu-engravings'>";
    $html .= "<div class='mu-section-header'><h3>🪶 Gravures</h3>" . muFillAllControl('Tout mettre à') . "</div>";

    $has_any = false;
    foreach ($labels as $cat => $label) {
        $list = $engravings[$cat] ?? [];
        if (empty($list)) continue;
        $has_any = true;

        $html .= "<div class='mu-subcategory'>";
        $html .= "<div class='mu-subcategory-header'><h4>{$label}</h4>" . muFillAllControl('Tout mettre à') . "</div>";
        $html .= "<div class='mu-grid mu-fillable'>";

        foreach ($list as $e) {
            $nom = htmlspecialchars($e['nom']);
            $max = (int)$e['niveau_max'];
            $type = htmlspecialchars($e['Type'] ?? '');
            $img_src = getEngravingImage($e['IconExportName'] ?: 'default');

            $html .= "<div class='mu-item mu-fillable' data-tid='" . htmlspecialchars($e['TID']) . "'>";
            $html .= "<div class='mu-item-header'>";
            $html .= "<img src='{$img_src}' class='mu-item-icon' alt='{$nom}' onerror=\"this.src='images/default-building.png'\">";
            $html .= "<div class='mu-item-name-wrapper'><span class='mu-item-name'>{$nom}</span>";
            if ($type !== '') {
                $html .= " <span class='mu-item-max'>({$type})</span>";
            }
            $html .= " <span class='mu-item-max'>(max {$max})</span></div>";
            $html .= "</div>";

            $html .= "<input type='number' class='mu-input mu-engraving' min='0' max='{$max}'
                        data-engraving='{$e['id_engraving']}' value='{$e['niveau']}'>";

            $html .= "</div>";
        }

        $html .= "</div></div>";
    }

    if (!$has_any) {
        $html .= "<p class='mu-empty'>Aucune gravure disponible.</p>";
    }

    $html .= "</div>";
    return $html;
}

function muRenderForm(array $data): string {
    $html = "<div class='mu-tabs'>";
    $html .= "<div class='mu-tab-buttons'>";
    $html .= "<button class='mu-tab-btn active' onclick='muSwitchTab(\"buildings\")'>🏗️ Bâtiments</button>";
    $html .= "<button class='mu-tab-btn' onclick='muSwitchTab(\"characters\")'>⚔️ Armée</button>";
    $html .= "<button class='mu-tab-btn' onclick='muSwitchTab(\"engravings\")'>🪶 Gravures</button>";
    $html .= "</div>";

    $html .= "<div class='mu-tab-content active' id='mu-tab-buildings'>";
    $html .= muRenderBuildingsSection($data['buildings']);
    $html .= "</div>";

    $html .= "<div class='mu-tab-content' id='mu-tab-characters'>";
    $html .= muRenderCharactersSection($data['characters']);
    $html .= "</div>";

    $html .= "<div class='mu-tab-content' id='mu-tab-engravings'>";
    $html .= muRenderEngravingsSection($data['engravings'] ?? []);
    $html .= "</div>";

    $html .= "</div>";

    return $html;
}