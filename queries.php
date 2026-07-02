<?php
// queries.php

// On s'assure que la session est démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// On récupère l'ID, si la session n'existe pas, on arrête tout
$id_player = $_SESSION['player_id'] ?? null;

if (!$id_player) {
    // Redirige vers la page de connexion si aucun joueur n'est identifié
    header("Location: index.php");
    exit();
}

require_once 'config.php';
require_once 'functions.php';

// 1. Niveau du QG
$stmt_qg = $pdo->prepare("SELECT qg FROM joueurs WHERE id_player = ?");
$stmt_qg->execute([$id_player]);

$qg_data = $stmt_qg->fetch(PDO::FETCH_ASSOC);
$qg = $qg_data ? (int)$qg_data['qg'] : 1;

// 2. Visuels et infos du QG
$sql_all_qg = "SELECT Niveau, ExportName FROM buildings WHERE TID = 'TID_BUILDING_PALACE' ORDER BY Niveau ASC";
$all_qg_images = $pdo->query($sql_all_qg)->fetchAll(PDO::FETCH_ASSOC);

$stmt_img = $pdo->prepare("SELECT ExportName FROM buildings WHERE TID = 'TID_BUILDING_PALACE' AND Niveau = :qg LIMIT 1");
$stmt_img->execute(['qg' => $qg]);
$qg_info = $stmt_img->fetch(PDO::FETCH_ASSOC);
$qg_image_name = ($qg_info) ? $qg_info['ExportName'] : 'townhall_lvl1';

// 3. Liste des bâtiments (débloqués ou non) + progression du joueur
// =========================================================================
/**
 * Construit la liste complète des instances de bâtiments disponibles au QG du joueur,
 * fusionnée avec sa progression réelle (progress_building : niveau + Debloque).
 * Une instance sans ligne en base = bâtiment non construit (niveau 0, Debloque 0).
 */
function getBuildingsDisplay($pdo, $id_player, $qg) {

    // 1. Bâtiments autorisés à ce QG, avec le nombre d'exemplaires (Amount) et le niveau max atteignable
    $stmt_limits = $pdo->prepare("
        SELECT v.TID, v.Amount, t.FR AS nom, bi.Class,
               (SELECT MAX(Niveau) FROM buildings b WHERE b.TID = v.TID AND b.TownHallLevel <= :qg) AS niveau_max
        FROM v_all_unlocks v
        JOIN texts t ON v.TID = t.TID
        JOIN buildingid bi ON v.TID = bi.TID
        WHERE v.TownHallLevel = :qg AND v.Amount > 0
        ORDER BY bi.Ordre ASC
    ");
    $stmt_limits->execute(['qg' => $qg]);
    $authorized_buildings = $stmt_limits->fetchAll(PDO::FETCH_ASSOC);

    // 2. Progression réelle du joueur (niveau + Debloque), indexée par TID puis par id_instance
    $stmt_prog = $pdo->prepare("
        SELECT bi.TID, pb.id_instance, pb.niveau, pb.Debloque
        FROM progress_building pb
        JOIN buildingid bi ON bi.ID = pb.id_building
        WHERE pb.id_player = ?
    ");
    $stmt_prog->execute([$id_player]);
    $progress = [];
    while ($row = $stmt_prog->fetch(PDO::FETCH_ASSOC)) {
        $progress[$row['TID']][(int)$row['id_instance']] = [
            'niveau'   => (int)$row['niveau'],
            'Debloque' => (int)$row['Debloque']
        ];
    }

    // 3. Assemblage final, instance par instance
    $buildings_display = ['Ressource' => [], 'Defense' => [], 'Army' => []];

    foreach ($authorized_buildings as $b) {
        $category = $b['Class'];
        if (!isset($buildings_display[$category])) continue;

        $niveau_max = (int)($b['niveau_max'] ?? 1);

        for ($i = 1; $i <= (int)$b['Amount']; $i++) {
            $etat          = $progress[$b['TID']][$i] ?? ['niveau' => 0, 'Debloque' => 0];
            $niveau_actuel = $etat['niveau'];
            $debloque      = $etat['Debloque'];

            // Image : niveau actuel si construit, sinon aperçu niveau 1
            $lvl_img = $niveau_actuel > 0 ? $niveau_actuel : 1;
            $stmt_img = $pdo->prepare("SELECT ExportName FROM buildings WHERE TID = ? AND Niveau = ? LIMIT 1");
            $stmt_img->execute([$b['TID'], $lvl_img]);
            $export_name = $stmt_img->fetchColumn() ?: 'default';

            // Coût / temps / XP du PROCHAIN niveau (niveau_actuel + 1), si pas déjà au max
            $next = null;
            $remaining_wood = 0;
            $remaining_stone = 0;
            $remaining_iron = 0;
            $remaining_time_seconds = 0;
            if ($niveau_actuel < $niveau_max) {
                $stmt_next = $pdo->prepare("
                    SELECT BuildCostWood, BuildCostStone, BuildCostIron,
                           BuildTimeD, BuildTimeH, BuildTimeM, BuildTimeS, XpGain
                    FROM buildings WHERE TID = ? AND Niveau = ? LIMIT 1
                ");
                $stmt_next->execute([$b['TID'], $niveau_actuel + 1]);
                $next = $stmt_next->fetch(PDO::FETCH_ASSOC) ?: null;

                // Total restant : somme de TOUS les niveaux entre l'actuel et le max
                // (utilisé pour le récap "coût total restant" de la sidebar).
                $stmt_remaining = $pdo->prepare("
                    SELECT SUM(BuildCostWood) AS wood, SUM(BuildCostStone) AS stone, SUM(BuildCostIron) AS iron,
                           SUM(BuildTimeD*86400 + BuildTimeH*3600 + BuildTimeM*60 + BuildTimeS) AS time_seconds
                    FROM buildings WHERE TID = ? AND Niveau > ? AND Niveau <= ?
                ");
                $stmt_remaining->execute([$b['TID'], $niveau_actuel, $niveau_max]);
                $row_remaining = $stmt_remaining->fetch(PDO::FETCH_ASSOC);
                $remaining_wood         = (int)($row_remaining['wood'] ?? 0);
                $remaining_stone        = (int)($row_remaining['stone'] ?? 0);
                $remaining_iron         = (int)($row_remaining['iron'] ?? 0);
                $remaining_time_seconds = (int)($row_remaining['time_seconds'] ?? 0);
            }

            $buildings_display[$category][] = [
                'TID'           => $b['TID'],
                'nom_building'  => $b['nom'],
                'id_instance'   => $i,
                'ExportName'    => $export_name,
                'niveau_actuel' => $niveau_actuel,
                'niveau_max'    => $niveau_max,
                'Debloque'      => $debloque,
                'BuildCostWood'  => $next['BuildCostWood']  ?? null,
                'BuildCostStone' => $next['BuildCostStone'] ?? null,
                'BuildCostIron'  => $next['BuildCostIron']  ?? null,
                'BuildTimeD'     => $next['BuildTimeD']     ?? null,
                'BuildTimeH'     => $next['BuildTimeH']     ?? null,
                'BuildTimeM'     => $next['BuildTimeM']     ?? null,
                'BuildTimeS'     => $next['BuildTimeS']     ?? null,
                'XpGain'         => $next['XpGain']         ?? null,
                // Totaux restants (jusqu'au niveau max), pour le récap de la sidebar
                'remaining_wood'         => $remaining_wood,
                'remaining_stone'        => $remaining_stone,
                'remaining_iron'         => $remaining_iron,
                'remaining_time_seconds' => $remaining_time_seconds,
            ];
        }
    }

    return $buildings_display;
}

$buildings_display = getBuildingsDisplay($pdo, $id_player, $qg);

// --- Agrégation pour les jauges du Tableau de Bord (renderDashboard) ---
$dashboard_buildings = [
    'Ressource' => ['label' => 'Bâtiments Économiques', 'actuel' => 0, 'max' => 0, 'pourcentage' => 0],
    'Defense'   => ['label' => 'Bâtiments Défensifs', 'actuel' => 0, 'max' => 0, 'pourcentage' => 0],
    'Army'      => ['label' => 'Bâtiments de Renfort', 'actuel' => 0, 'max' => 0, 'pourcentage' => 0]
];

foreach ($buildings_display as $category => $instances) {
    if (!isset($dashboard_buildings[$category])) continue;
    foreach ($instances as $b) {
        $dashboard_buildings[$category]['actuel'] += $b['niveau_actuel'];
        $dashboard_buildings[$category]['max']    += $b['niveau_max'];
    }
}

foreach ($dashboard_buildings as $cat => &$data) {
    if ($data['max'] > 0) {
        $data['pourcentage'] = round(($data['actuel'] / $data['max']) * 100);
    }
}
unset($data);

// ========================================================
// 1. Fonction dédiée aux Troupes // Proto // Héros // Chefs de bataillon
// ========================================================
function getFilteredUnits($pdo, $qg, $arsenal_max, $typeFilterSQL, $player_progress = []) {
    // 1. Requête principale
    $sql = "SELECT ci.id AS id_character, c.*, t.FR AS nom, ci.Class, ci.IconExportName, ci.Officer,
               ot.TalentTID1, ot.TalentTID2, ot.TalentTID3, ot.TalentTID4, ot.TalentTID5,
               pc.niveau, pc.Debloque
        FROM characters c
        INNER JOIN texts t ON c.TID = t.TID
        INNER JOIN characterid ci ON c.TID = ci.TID
        LEFT JOIN officer_talents ot ON ci.TID = ot.TID
        LEFT JOIN progress_character pc ON ci.id = pc.id_character AND pc.id_player = :id_player
        WHERE $typeFilterSQL 
        AND c.Niveau = 1
        AND ci.HQUnlock <= :qg
        ORDER BY ci.HQUnlock ASC, c.TID ASC"; 

    $stmt = $pdo->prepare($sql);
    // On passe un tableau avec les deux paramètres nommés
    $stmt->execute([
        'id_player' => $_SESSION['player_id'],
        'qg' => $qg
    ]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupération de la progression des capacités
    $id_player = $_SESSION['player_id'] ?? null;
    $abilities_progress = [];
    if ($id_player) {
        $stmt_prog = $pdo->prepare("SELECT id_character, id_ability, niveau FROM progress_ability WHERE id_player = ?");
        $stmt_prog->execute([$id_player]);
        while ($row = $stmt_prog->fetch(PDO::FETCH_ASSOC)) {
            $abilities_progress["{$row['id_character']}-{$row['id_ability']}"] = (int)$row['niveau'];
        }
    }

    $final_list = [];
    foreach ($results as $u) {
        $is_officer = (strcasecmp(trim($u['Class']), 'Officier') === 0);
        $tid_to_check = (!empty($u['Officer'])) ? $u['Officer'] : $u['TID'];
        $niveau_joueur = $player_progress[$tid_to_check] ?? 1;

        $officer_talents = []; 
        $officer_abilities = [];
        $total_talents_unlocked = 0;
        $next_talent_id = 0;

        if ($is_officer) {
            // --- BLOC A : Compter les talents débloqués et préparer le prochain ---
            for ($i = 1; $i <= 5; $i++) {
                $tid_talent = $u["TalentTID$i"] ?? null;
                if (!empty($tid_talent)) {
                    $stmt_ab_id = $pdo->prepare("SELECT id FROM abilitieid WHERE TID = ? LIMIT 1");
                    $stmt_ab_id->execute([$tid_talent]);
                    $ab_id = $stmt_ab_id->fetchColumn();

                    if ($ab_id) {
                        $composite_key = "{$u['id_character']}-{$ab_id}";
                        if (isset($abilities_progress[$composite_key])) {
                            $total_talents_unlocked++;
                        } elseif ($next_talent_id === 0) {
                            // On stocke l'ID du premier talent non débloqué trouvé (le prochain à améliorer)
                            $next_talent_id = (int)$ab_id;
                        }
                    }
                }
            }

            // --- BLOC B : Chargement des Capacités (Active/Passive) ---
            $parts = explode('_OFC_', $u['TID']);
            $suffixe = '%' . ($parts[1] ?? $u['TID']);
            $stmt_ab = $pdo->prepare("SELECT ActiveAbility, PassiveAbility FROM officer_talents WHERE TID LIKE ? LIMIT 1");
            $stmt_ab->execute([$suffixe]);
            $ab_row = $stmt_ab->fetch(PDO::FETCH_ASSOC);

            if ($ab_row) {
                foreach (['active' => $ab_row['ActiveAbility'], 'passive' => $ab_row['PassiveAbility']] as $type => $ab_tid) {
                    if (!$ab_tid) continue;
                    
                    $stmt_info = $pdo->prepare("SELECT ai.id, ai.IconExportName, t.FR FROM abilitieid ai 
                                                LEFT JOIN texts t ON ai.TID = t.TID WHERE ai.TID = ?");
                    $stmt_info->execute([$ab_tid]);
                    $info = $stmt_info->fetch(PDO::FETCH_ASSOC);
                    
                    $stmt_ab_lvls = $pdo->prepare("SELECT Niveau, HeroLevel, UpgradeCost, UpgradeResource FROM officer_abilities WHERE TID = ? ORDER BY Niveau ASC");
                    $stmt_ab_lvls->execute([$ab_tid]);
                    
                    $officer_abilities[$type] = [
                        'id_ability' => $info['id'] ?? 0, 
                        'TID' => $ab_tid,
                        'IconExportName' => $info['IconExportName'] ?? 'default',
                        'nom' => $info['FR'] ?? $ab_tid,
                        'current_level' => $abilities_progress["{$u['id_character']}-{$info['id']}"] ?? 1,
                        'levels' => $stmt_ab_lvls->fetchAll(PDO::FETCH_ASSOC)
                    ];
                }
            }
        }

        // --- Niveau max atteignable + coût du prochain niveau ---
        // Une seule colonne UpgradeCost en base (pas de distinction Or/Proto-jetons stockée) :
        // la distinction se fait côté affichage selon la Class ('Troupe' => Or, 'Proto' => Jetons Proto).
        // UpgradeTimeH est exprimé en HEURES (formatUnitsTime() le convertit en j/h/m à l'affichage).
        // Le coût pour passer de niveau_joueur à niveau_joueur+1 est stocké sur la ligne
        // "characters" du niveau ACTUEL (même convention que upgrade_character.php : Niveau = target_level - 1).
        $stmt_max_char = $pdo->prepare("SELECT MAX(Niveau) FROM characters WHERE TID = ?");
        $stmt_max_char->execute([$u['TID']]);
        $niveau_max = (int)($stmt_max_char->fetchColumn() ?: $niveau_joueur);

        $next_cost = null;
        $remaining_cost = 0;
        $remaining_time_h = 0.0;
        if ($niveau_joueur < $niveau_max) {
            try {
                $stmt_next_cost = $pdo->prepare("
                    SELECT UpgradeCost, UpgradeTimeH, XpGain, UpgradeHouseLevel
                    FROM characters WHERE TID = ? AND Niveau = ? LIMIT 1
                ");
                $stmt_next_cost->execute([$u['TID'], $niveau_joueur]);
                $next_cost = $stmt_next_cost->fetch(PDO::FETCH_ASSOC) ?: null;

                // Total restant : somme de TOUS les niveaux entre l'actuel et le max
                // (utilisé pour le récap "coût total restant" de la sidebar).
                $stmt_remaining = $pdo->prepare("
                    SELECT SUM(UpgradeCost) AS total_cost, SUM(UpgradeTimeH) AS total_time
                    FROM characters WHERE TID = ? AND Niveau >= ? AND Niveau < ?
                ");
                $stmt_remaining->execute([$u['TID'], $niveau_joueur, $niveau_max]);
                $row_remaining = $stmt_remaining->fetch(PDO::FETCH_ASSOC);
                $remaining_cost   = (int)($row_remaining['total_cost'] ?? 0);
                $remaining_time_h = (float)($row_remaining['total_time'] ?? 0);
            } catch (PDOException $e) {
                // On log l'erreur mais on ne casse jamais l'affichage de la page pour un souci de coût
                error_log("Erreur récupération coût troupe {$u['TID']} niveau {$niveau_joueur} : " . $e->getMessage());
                $next_cost = null;
            }
        }

        $final_list[] = [
            'id_character' => (int)$u['id_character'],
            'TID' => $u['TID'],
            'nom' => $u['nom'],
            'IconExportName' => $u['IconExportName'],
            'Class' => $u['Class'],
            'is_officer' => $is_officer,
            'niveau_joueur' => $niveau_joueur,
            'Debloque' => $u['Debloque'] ?? 0,
            'total_talents_unlocked' => $total_talents_unlocked,
            'next_talent_id' => $next_talent_id,
            'abilities' => $officer_abilities,
            'niveau_autorise' => $niveau_max,
            'next_cost' => $next_cost,
            // Totaux restants (jusqu'au niveau max), pour le récap de la sidebar
            'remaining_cost' => $remaining_cost,
            'remaining_time_h' => $remaining_time_h
        ];
    }
    return $final_list;
}

// DONNÉES DU DASHBOARD ---
$dashboard_categories = [
    'Troupe'   => ['label' => 'Troupes', 'actuel' => 0, 'max' => 0, 'pourcentage' => 0],
    'Proto'    => ['label' => 'Proto-troupes', 'actuel' => 0, 'max' => 0, 'pourcentage' => 0],
    'Hero'     => ['label' => 'Héros', 'actuel' => 0, 'max' => 0, 'pourcentage' => 0],
    'Officier' => ['label' => 'Chefs de Bataillon', 'actuel' => 0, 'max' => 0, 'pourcentage' => 0]
];


/**
 * Récupère les bâtiments avec leur progression
 */
function getBuildings($pdo, $id_player) {
    // 1. Récupérer la progression du joueur pour les bâtiments
    $stmt_prog = $pdo->prepare("SELECT id_building, id_instance, niveau FROM progress_buildings WHERE id_player = ?");
    $stmt_prog->execute([$id_player]);
    $progress = $stmt_prog->fetchAll(PDO::FETCH_KEY_PAIR); // Retourne [id_batiment => niveau]

    // 2. Requête unifiée : Bâtiments + Noms + Catégories
    $sql = "SELECT b.id, b.TID, b.Category, t.FR AS nom, b.IconExportName
            FROM buildings b
            LEFT JOIN texts t ON b.TID = t.TID
            ORDER BY b.Category ASC, b.id ASC";
    
    $stmt = $pdo->query($sql);
    $buildings = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id = $row['id'];
        $row['niveau'] = $progress[$id] ?? 1; // Niveau 1 par défaut
        $buildings[$row['Category']][] = $row; // Classement automatique par catégorie
    }
    
    return $buildings;
}



$monument_level = 0;
if (isset($player_progress['MYSTIC_MONUMENT'][1])) {
    $monument_level = (int)$player_progress['MYSTIC_MONUMENT'][1];
} else {
    // Alternative : si vous le stockez dans une autre table ou variable
    $monument_level = 250; // Valeur d'exemple pour test
}

// 2. Récupérer tous les bonus disponibles
$stmt_bonuses = $pdo->prepare("SELECT id_bonus, t.FR AS TID, MaxCount, BoostAmount, MinBuildingLevel FROM cc_bonuses INNER JOIN texts t ON t.TID = cc_bonuses.TID ORDER BY MinBuildingLevel ASC, TID ASC");
$stmt_bonuses->execute();
$cc_bonuses = $stmt_bonuses->fetchAll(PDO::FETCH_ASSOC);

// 3. Récupérer les bonus actuels saisis par le joueur
$stmt_player_bonuses = $pdo->prepare("SELECT id_bonus, nb_bonus FROM progress_monument WHERE id_player = ?");
$stmt_player_bonuses->execute([$id_player]);
$player_bonuses_raw = $stmt_player_bonuses->fetchAll(PDO::FETCH_ASSOC);

// Indexer par id_bonus pour un accès rapide
$player_bonuses = [];
foreach ($player_bonuses_raw as $pb) {
    $player_bonuses[$pb['id_bonus']] = (int)$pb['nb_bonus'];
}

// --- RÉCUPÉRATION DE LA PROGRESSION DES TROUPES ---
$character_progress = [];

try {
    $stmt_char_prog = $pdo->prepare("
        SELECT ci.TID, pc.niveau 
        FROM progress_character pc
        INNER JOIN characterid ci ON pc.id_character = ci.id
        WHERE pc.id_player = :id_player
    ");
    $stmt_char_prog->execute(['id_player' => $id_player]);
    
    while ($row = $stmt_char_prog->fetch(PDO::FETCH_ASSOC)) {
        // On indexe par TID
        $character_progress[$row['TID']] = (int)$row['niveau'];
    }
} catch (PDOException $e) {
    // Log l'erreur pour comprendre pourquoi ça ne charge pas
    error_log("Erreur chargement progression troupes: " . $e->getMessage());
}

// Trouver l'Arsenal Max
$qg = (int)$qg;
$stmt_ars = $pdo->prepare("SELECT MAX(Niveau) as arsenal_max FROM buildings WHERE TID = 'TID_BUILDING_LABORATORY' AND TownHallLevel <= :qg");
$stmt_ars->execute(['qg' => $qg]);
$arsenal_current_max = $stmt_ars->fetch(PDO::FETCH_ASSOC)['arsenal_max'] ?? 1;

// --- NIVEAU RÉEL CONSTRUIT PAR LE JOUEUR : Arsenal (id_building 13) et Atelier de Proto-troupes (id_building 30) ---
// Ces deux bâtiments conditionnent le déblocage des améliorations de Troupes / Proto-troupes
// via la colonne characters.UpgradeHouseLevel.
$house_levels = ['arsenal' => 0, 'proto_factory' => 0];
try {
    $stmt_house = $pdo->prepare("
        SELECT id_building, niveau
        FROM progress_building
        WHERE id_player = ? AND id_building IN (13, 30)
    ");
    $stmt_house->execute([$id_player]);
    while ($row = $stmt_house->fetch(PDO::FETCH_ASSOC)) {
        if ((int)$row['id_building'] === 13) $house_levels['arsenal'] = (int)$row['niveau'];
        if ((int)$row['id_building'] === 30) $house_levels['proto_factory'] = (int)$row['niveau'];
    }
} catch (PDOException $e) {
    error_log("Erreur récupération niveau Arsenal / Atelier Proto : " . $e->getMessage());
}

// Génération des listes
$troupes_list  = getFilteredUnits($pdo, $qg, $arsenal_current_max, "ci.Class = 'Troupe'", $character_progress);
$heros_list    = getFilteredUnits($pdo, $qg, $arsenal_current_max, "ci.Class = 'Hero'", $character_progress);
$proto_list    = getFilteredUnits($pdo, $qg, $arsenal_current_max, "ci.Class = 'Proto'", $character_progress);
$officers_list = getFilteredUnits($pdo, $qg, $arsenal_current_max, "ci.Class = 'Officier'", $character_progress);




// ========================================================
// 12. RECUPERATION DES GRAVURES ET DE LA PROGRESSION
// ========================================================
$engravings_offensive = [];
$engravings_defensive = [];

// Récupération de la progression basée sur l'id_engraving numérique
$engravings_progress = [];
try {
    // MODIFICATION ICI : On sélectionne id_engraving au lieu de TID
    $stmt_eng_prog = $pdo->prepare("SELECT id_engraving, niveau FROM progress_engraving WHERE id_player = :id_player");
    $stmt_eng_prog->execute(['id_player' => $id_player]);
    while ($row = $stmt_eng_prog->fetch(PDO::FETCH_ASSOC)) {
        // Clé du tableau = l'id numérique de la gravure
        $engravings_progress[(int)$row['id_engraving']] = (int)$row['niveau'];
    }
} catch (PDOException $e) {
    error_log("Erreur progress_engraving : " . $e->getMessage());
}

// Coût en Jetons de recherche par TID + palier de qualité (Quality).
// IMPORTANT : ce tableau doit être construit AVANT la boucle principale ci-dessous,
// qui s'en sert pour attacher les coûts à chaque gravure.
$engravings_costs = [];
try {
    $stmt_costs = $pdo->query("SELECT TID, Quality, TokensNeeded FROM engravings ORDER BY TID ASC, Quality ASC");
    while ($cost_row = $stmt_costs->fetch(PDO::FETCH_ASSOC)) {
        $cost_tid = $cost_row['TID'];
        $q_lvl = (int)$cost_row['Quality'];
        $tokens = (int)$cost_row['TokensNeeded'];

        $engravings_costs[$cost_tid][$q_lvl] = $tokens;
    }
} catch (PDOException $e) {
    error_log("Erreur engravings costs : " . $e->getMessage());
}

try {
    // Requête principale qui récupère les gravures et leur ID unique (id) de la table engravingid
    $stmt_eng = $pdo->query("
        SELECT ei.id AS id_engraving, e.TID, ei.Category, ei.Type, ei.IconExportName, IFNULL(t.FR, e.TID) AS nom, MAX(e.Quality) as niveau_max
        FROM engravings e
        JOIN engravingid ei ON e.TID = ei.TID
        LEFT JOIN texts t ON e.TID = t.TID
        GROUP BY ei.id, e.TID, ei.Category, ei.Type, ei.IconExportName, t.FR
        ORDER BY ei.Type ASC, e.TID ASC
    ");
    
    while ($row = $stmt_eng->fetch(PDO::FETCH_ASSOC)) {
        $id_engraving = (int)$row['id_engraving'];
        $cat = $row['Category'];
        $tid = $row['TID'];
        
        // MODIFICATION ICI : On va chercher dans notre tableau de progression via l'id numérique
        $row['niveau_actuel'] = $engravings_progress[$id_engraving] ?? 0;

        // Coûts (Jetons de recherche) indexés par palier de qualité, ex: [1 => 50, 2 => 120, ...]
        $row['costs'] = $engravings_costs[$tid] ?? [];
        
        // Dispatch dans les catégories pour l'affichage
        if ($cat === 'Offensive') {
            $engravings_offensive[] = $row;
        } elseif ($cat === 'Defensive') {
            $engravings_defensive[] = $row;
        }
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des gravures : " . $e->getMessage());
}
// ========================================================