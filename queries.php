<?php
// queries.php

// 1. Démarrage de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Récupère la langue depuis la SESSION (prioritaire), sinon cookie, sinon FR
$selected_lang = $_SESSION['lang'] ?? $_COOKIE['lang'] ?? 'FR';
$allowed_langs = ['EN', 'DE', 'ES', 'FR', 'IT', 'JP', 'PT', 'ZH_HANS', 'NL', 'NO', 'TR', 'KR', 'RU', 'ZH_HANT', 'AR', 'ID', 'MS', 'VI', 'TH', 'FI'];
$selected_lang = in_array($selected_lang, $allowed_langs) ? $selected_lang : 'FR';

// 3. Le reste de ton code existant...
$id_player = $_SESSION['player_id'] ?? null;
require_once 'config.php';
require_once 'functions.php';

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
// TID des Pièges qui ne se construisent plus individuellement (à l'or, par instance),
// mais qui se recherchent une seule fois à l'Arsenal et s'appliquent à TOUTES les mines
// posées sur la base (comme une amélioration de Troupe). Une seule carte doit donc être
// affichée pour chacun de ces TID, quel que soit le nombre d'instances en base.
const MINE_TIDS = ['TID_TRAP_MINE', 'TID_TRAP_TANK_MINE', 'TID_TRAP_SHOCK_MINE'];

function getBuildingsDisplay($pdo, $id_player, $qg, $arsenal_level_reel = 0) {
    global $selected_lang;

    // Définir le mappage entre la langue session et le nom de la colonne SQL
    $lang_map = [
        'ZH-HANS' => 'ZH_HANS',
        'ZH-HANT' => 'ZH_HANT',
        // ... ajoutez les autres si nécessaire, ex: 'EN' => 'EN'
    ];

    // Si la langue a un mappage, on l'utilise, sinon on prend le code tel quel
    $colonne_lang = $lang_map[$selected_lang] ?? $selected_lang;

    // Puis utilisez $colonne_lang dans votre requête :
    $stmt_prog = $pdo->prepare("
        SELECT pb.id_instance, pb.niveau, pb.Debloque, bi.TID, bi.Class, bi.Ordre, t.$colonne_lang AS nom
        FROM progress_building pb
        JOIN buildingid bi ON bi.ID = pb.id_building
        JOIN texts t ON bi.TID = t.TID
        WHERE pb.id_player = ?
        ORDER BY bi.Ordre ASC, pb.id_instance ASC
    ");
    $stmt_prog->execute([$id_player]);
    $rows = $stmt_prog->fetchAll(PDO::FETCH_ASSOC);

    // Pour les mines (voir MINE_TIDS ci-dessus) : plusieurs instances existent peut-être
    // encore en base (une par mine posée), mais on ne veut garder qu'UNE seule ligne par
    // TID — celle avec le niveau le plus élevé (au cas où les instances seraient
    // désynchronisées) — et on retient l'id_instance le plus bas comme "instance
    // canonique" à utiliser pour l'amélioration (voir note upgrade_building.php plus bas).
    $mine_canonical = []; // TID => ['niveau' => int, 'Debloque' => int, 'id_instance' => int]
    foreach ($rows as $r) {
        if (!in_array($r['TID'], MINE_TIDS, true)) continue;
        $tid = $r['TID'];
        if (!isset($mine_canonical[$tid]) || (int)$r['niveau'] > $mine_canonical[$tid]['niveau']) {
            $mine_canonical[$tid] = [
                'niveau'      => (int)$r['niveau'],
                'Debloque'    => (int)$r['Debloque'],
                'id_instance' => (int)$r['id_instance'],
            ];
        } else {
            $mine_canonical[$tid]['id_instance'] = min($mine_canonical[$tid]['id_instance'], (int)$r['id_instance']);
        }
        if ((int)$r['Debloque'] === 1) $mine_canonical[$tid]['Debloque'] = 1;
    }
    $mine_seen = []; // TID déjà traités, pour ne garder qu'une occurrence dans la boucle principale

    // Niveau max atteignable par TID (dépend toujours du QG via buildings.TownHallLevel,
    // indépendamment de la vue) — mis en cache pour ne pas refaire la requête à chaque instance.
    $niveau_max_cache = [];
    // Niveau max ABSOLU par TID, celui-là INDÉPENDANT du QG (tous niveaux confondus, jusqu'à
    // la fin du jeu) — utilisé uniquement pour le %age "jusqu'au max du max" du Tableau de Bord,
    // qui reste identique quel que soit le QG actuel, contrairement à niveau_max ci-dessus.
    $niveau_max_absolu_cache = [];

    $buildings_display = ['Ressource' => [], 'Defense' => [], 'Army' => [], 'Trap' => []];

    foreach ($rows as $b) {
        $category = $b['Class'];
        if (!isset($buildings_display[$category])) continue;

        $tid = $b['TID'];
        $is_mine = in_array($tid, MINE_TIDS, true);

        // Mines : une seule carte par TID (voir $mine_canonical plus haut). On saute
        // toutes les occurrences après la première, et on utilise le niveau agrégé.
        if ($is_mine) {
            if (isset($mine_seen[$tid])) continue;
            $mine_seen[$tid] = true;
            $b['niveau']      = $mine_canonical[$tid]['niveau'];
            $b['Debloque']    = $mine_canonical[$tid]['Debloque'];
            $b['id_instance'] = $mine_canonical[$tid]['id_instance'];
        }

        // Les Pièges (Class 'Trap') sont des bâtiments comme les autres, MAIS leur palier
        // (colonne buildings.TownHallLevel) représente en réalité le niveau d'ARSENAL requis,
        // pas le niveau de QG — contrairement à toutes les autres catégories de bâtiments.
        $is_trap = ($category === 'Trap');
        $gating_level = $is_trap ? $arsenal_level_reel : $qg;

        $cache_key = "{$tid}|{$gating_level}";
        if (!isset($niveau_max_cache[$cache_key])) {
            $stmt_max = $pdo->prepare("SELECT MAX(Niveau) FROM buildings WHERE TID = ? AND TownHallLevel <= ?");
            $stmt_max->execute([$tid, $gating_level]);
            $niveau_max_cache[$cache_key] = (int)($stmt_max->fetchColumn() ?: 1);
        }
        $niveau_max = $niveau_max_cache[$cache_key];

        if (!isset($niveau_max_absolu_cache[$tid])) {
            $stmt_max_abs = $pdo->prepare("SELECT MAX(Niveau) FROM buildings WHERE TID = ?");
            $stmt_max_abs->execute([$tid]);
            $niveau_max_absolu_cache[$tid] = (int)($stmt_max_abs->fetchColumn() ?: $niveau_max);
        }
        $niveau_max_absolu = $niveau_max_absolu_cache[$tid];

        $niveau_actuel = (int)$b['niveau'];
        $debloque      = (int)$b['Debloque'];

        // Image : niveau actuel si construit, sinon aperçu niveau 1
        $lvl_img = $niveau_actuel > 0 ? $niveau_actuel : 1;
        $stmt_img = $pdo->prepare("SELECT ExportName FROM buildings WHERE TID = ? AND Niveau = ? LIMIT 1");
        $stmt_img->execute([$tid, $lvl_img]);
        $export_name = $stmt_img->fetchColumn() ?: 'default';

        // Coût / temps / XP du PROCHAIN niveau (niveau_actuel + 1), si pas déjà au max
        // Les Pièges se paient en OR (BuildCostGold) et non en bois/pierre/fer.
        $next = null;
        $remaining_gold = 0;
        $remaining_wood = 0;
        $remaining_stone = 0;
        $remaining_iron = 0;
        $remaining_time_seconds = 0;
        // Pour les mines, le "vrai" plafond affiché est le max ABSOLU (indépendant de
        // l'arsenal actuel) : on veut voir qu'il reste des niveaux à débloquer même si
        // l'Arsenal n'est pas encore assez haut, avec un message dédié (voir plus bas),
        // exactement comme pour les Troupes.
        if ($is_mine) $niveau_max = $niveau_max_absolu;

        if ($niveau_actuel < $niveau_max) {
            $stmt_next = $pdo->prepare("
                SELECT BuildCostGold, BuildCostWood, BuildCostStone, BuildCostIron,
                       BuildTimeD, BuildTimeH, BuildTimeM, BuildTimeS, XpGain, TownHallLevel
                FROM buildings WHERE TID = ? AND Niveau = ? LIMIT 1
            ");
            $stmt_next->execute([$tid, $niveau_actuel + 1]);
            $next = $stmt_next->fetch(PDO::FETCH_ASSOC) ?: null;

            // Total restant : somme de TOUS les niveaux entre l'actuel et le max
            // (utilisé pour le récap "coût total restant" de la sidebar).
            $stmt_remaining = $pdo->prepare("
                SELECT SUM(BuildCostGold) AS gold, SUM(BuildCostWood) AS wood, SUM(BuildCostStone) AS stone, SUM(BuildCostIron) AS iron,
                       SUM(BuildTimeD*86400 + BuildTimeH*3600 + BuildTimeM*60 + BuildTimeS) AS time_seconds
                FROM buildings WHERE TID = ? AND Niveau > ? AND Niveau <= ?
            ");
            $stmt_remaining->execute([$tid, $niveau_actuel, $niveau_max]);
            $row_remaining = $stmt_remaining->fetch(PDO::FETCH_ASSOC);
            $remaining_gold         = (int)($row_remaining['gold'] ?? 0);
            $remaining_wood         = (int)($row_remaining['wood'] ?? 0);
            $remaining_stone        = (int)($row_remaining['stone'] ?? 0);
            $remaining_iron         = (int)($row_remaining['iron'] ?? 0);
            $remaining_time_seconds = (int)($row_remaining['time_seconds'] ?? 0);
        }

        // Arsenal requis pour le PROCHAIN niveau (uniquement pertinent pour les mines,
        // qui ne se gatent plus via le calcul de niveau_max mais via un vrai message,
        // comme les Troupes / Proto-troupes avec UpgradeHouseLevel).
        $required_arsenal = $is_mine && $next ? (int)($next['TownHallLevel'] ?? 0) : 0;
        $arsenal_ok       = !$is_mine || ($required_arsenal <= $arsenal_level_reel);

        $buildings_display[$category][] = [
            'TID'           => $tid,
            'Class'         => $category,
            'nom_building'  => $b['nom'],
            'id_instance'   => (int)$b['id_instance'],
            'ExportName'    => $export_name,
            'niveau_actuel' => $niveau_actuel,
            'niveau_max'    => $niveau_max,
            'niveau_max_absolu' => $niveau_max_absolu,
            'Debloque'      => $debloque,
            'is_mine'          => $is_mine,
            'required_arsenal' => $required_arsenal,
            'arsenal_ok'       => $arsenal_ok,
            // Pièges : uniquement l'Or compte. Autres catégories : bois/pierre/fer.
            'BuildCostGold'  => $is_trap ? ($next['BuildCostGold']  ?? null) : null,
            'BuildCostWood'  => $is_trap ? null : ($next['BuildCostWood']  ?? null),
            'BuildCostStone' => $is_trap ? null : ($next['BuildCostStone'] ?? null),
            'BuildCostIron'  => $is_trap ? null : ($next['BuildCostIron']  ?? null),
            'BuildTimeD'     => $next['BuildTimeD']     ?? null,
            'BuildTimeH'     => $next['BuildTimeH']     ?? null,
            'BuildTimeM'     => $next['BuildTimeM']     ?? null,
            'BuildTimeS'     => $next['BuildTimeS']     ?? null,
            'XpGain'         => $next['XpGain']         ?? null,
            // Totaux restants (jusqu'au niveau max), pour le récap de la sidebar
            'remaining_gold'         => $is_trap ? $remaining_gold : 0,
            'remaining_wood'         => $is_trap ? 0 : $remaining_wood,
            'remaining_stone'        => $is_trap ? 0 : $remaining_stone,
            'remaining_iron'         => $is_trap ? 0 : $remaining_iron,
            'remaining_time_seconds' => $remaining_time_seconds,
        ];
    }

    return $buildings_display;
}

// Niveau d'Arsenal RÉEL construit par le joueur (nécessaire ici pour gater les Pièges,
// qui dépendent de l'Arsenal et non du QG — voir getBuildingsDisplay). Le reste du calcul
// (arsenal_current_max théorique, Atelier de Proto-troupes, plafonnement) est fait plus bas
// et réutilise ce qui a déjà été récupéré ici pour ne pas dupliquer les requêtes.
$stmt_ars_early = $pdo->prepare("SELECT MAX(Niveau) as arsenal_max FROM buildings WHERE TID = 'TID_BUILDING_LABORATORY' AND TownHallLevel <= :qg");
$stmt_ars_early->execute(['qg' => (int)$qg]);
$arsenal_current_max = (int)($stmt_ars_early->fetch(PDO::FETCH_ASSOC)['arsenal_max'] ?? 1);

$stmt_arsenal_reel_early = $pdo->prepare("SELECT MAX(niveau) AS niveau FROM progress_building WHERE id_player = ? AND id_building = 12");
$stmt_arsenal_reel_early->execute([$id_player]);
$arsenal_level_reel = (int)($stmt_arsenal_reel_early->fetch(PDO::FETCH_ASSOC)['niveau'] ?? 0);
$arsenal_level_reel = min($arsenal_level_reel, $arsenal_current_max);

$buildings_display = getBuildingsDisplay($pdo, $id_player, $qg, $arsenal_level_reel);

// --- Agrégation pour les jauges du Tableau de Bord (renderDashboard) ---
$dashboard_buildings = [
    'Ressource' => ['label' => 'Bâtiments Économiques', 'actuel' => 0, 'max' => 0, 'pourcentage' => 0],
    'Defense'   => ['label' => 'Bâtiments Défensifs', 'actuel' => 0, 'max' => 0, 'pourcentage' => 0],
    'Army'      => ['label' => 'Bâtiments de Renfort', 'actuel' => 0, 'max' => 0, 'pourcentage' => 0],
    'Trap'      => ['label' => 'Pièges', 'actuel' => 0, 'max' => 0, 'pourcentage' => 0]
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
function getFilteredUnits($pdo, $qg, $arsenal_max, $typeFilterSQL, $player_progress = [], $house_levels = []) {
    global $selected_lang;
    // 1. Requête principale
    // NOTE : la jointure sur progress_character passe par une sous-requête agrégée
    // (MAX(niveau)/MAX(Debloque) GROUP BY id_character) au lieu d'un simple LEFT JOIN direct.
    // Si jamais plusieurs lignes progress_character existent pour le même (id_player,
    // id_character) — par exemple si progress_character n'a pas de contrainte UNIQUE et que
    // update_qg.php y insère plusieurs fois via INSERT IGNORE — un LEFT JOIN direct dupliquerait
    // le personnage autant de fois qu'il y a de lignes (symptôme observé : "Dr Kavan" affiché
    // plusieurs fois). Cette sous-requête garantit toujours UNE seule ligne par personnage.
    $sql = "SELECT ci.id AS id_character, c.*, t.$selected_lang AS nom, ci.Class, ci.IconExportName, ci.Officer,
               ot.TalentTID1, ot.TalentTID2, ot.TalentTID3, ot.TalentTID4, ot.TalentTID5,
               pc.niveau, pc.Debloque
        FROM characters c
        INNER JOIN (
            SELECT TID, MAX($selected_lang) AS $selected_lang
            FROM texts
            GROUP BY TID
        ) t ON c.TID = t.TID
        INNER JOIN characterid ci ON c.TID = ci.TID
        LEFT JOIN (
            SELECT TID,
                   MAX(TalentTID1) AS TalentTID1, MAX(TalentTID2) AS TalentTID2,
                   MAX(TalentTID3) AS TalentTID3, MAX(TalentTID4) AS TalentTID4,
                   MAX(TalentTID5) AS TalentTID5
            FROM officer_talents
            GROUP BY TID
        ) ot ON ci.TID = ot.TID
        LEFT JOIN characterid base_troop ON base_troop.TID = ci.Officer
        LEFT JOIN (
            SELECT id_character, MAX(niveau) AS niveau, MAX(Debloque) AS Debloque
            FROM progress_character
            WHERE id_player = :id_player
            GROUP BY id_character
        ) pc ON ci.id = pc.id_character
        WHERE $typeFilterSQL 
        AND c.Niveau = 1
        AND ci.HQUnlock <= :qg
        ORDER BY ci.Type ASC, ci.Display ASC"; 

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
    $abilities_debloque = [];
    if ($id_player) {
        $stmt_prog = $pdo->prepare("SELECT id_character, id_ability, niveau, Debloque FROM progress_ability WHERE id_player = ?");
        $stmt_prog->execute([$id_player]);
        while ($row = $stmt_prog->fetch(PDO::FETCH_ASSOC)) {
            $key = "{$row['id_character']}-{$row['id_ability']}";
            $abilities_progress[$key] = (int)$row['niveau'];
            $abilities_debloque[$key] = (int)$row['Debloque'];
        }
    }

    $final_list = [];
    foreach ($results as $u) {
        $is_officer = (strcasecmp(trim($u['Class']), 'Officier') === 0);
        $is_hero    = (strcasecmp(trim($u['Class']), 'Hero') === 0);
        $tid_to_check = (!empty($u['Officer'])) ? $u['Officer'] : $u['TID'];
        $niveau_joueur = $player_progress[$tid_to_check] ?? 1;

        $officer_talents = []; 
        $officer_abilities = [];
        $total_talents_unlocked = 0;
        $next_talent_id = 0;
        $hero_abilities = [];

        if ($is_officer) {
            // --- BLOC A : Compter les talents débloqués et préparer le prochain ---
            $talent3_unlocked = false; // débloquer le talent 3 donne +2 niveaux (affichage) aux capacités active/passive
            for ($i = 1; $i <= 5; $i++) {
                $tid_talent = $u["TalentTID$i"] ?? null;
                if (!empty($tid_talent)) {
                    $stmt_ab_id = $pdo->prepare("SELECT id FROM abilitieid WHERE TID = ? LIMIT 1");
                    $stmt_ab_id->execute([$tid_talent]);
                    $ab_id = $stmt_ab_id->fetchColumn();

                    if ($ab_id) {
                        $composite_key = "{$u['id_character']}-{$ab_id}";
                        if (!empty($abilities_debloque[$composite_key])) {
                            $total_talents_unlocked++;
                            if ($i === 3) $talent3_unlocked = true;
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
                    
                    $stmt_info = $pdo->prepare("SELECT ai.id, ai.IconExportName, t.$selected_lang FROM abilitieid ai 
                                                LEFT JOIN texts t ON ai.TID = t.TID WHERE ai.TID = ?");
                    $stmt_info->execute([$ab_tid]);
                    $info = $stmt_info->fetch(PDO::FETCH_ASSOC);
                    
                    $stmt_ab_lvls = $pdo->prepare("SELECT Niveau, HeroLevel, UpgradeCost, UpgradeResource FROM officer_abilities WHERE TID = ? ORDER BY Niveau ASC");
                    $stmt_ab_lvls->execute([$ab_tid]);
                    $ab_levels = $stmt_ab_lvls->fetchAll(PDO::FETCH_ASSOC);

                    // Niveau réel (celui qui sert de base au calcul du coût du prochain niveau) :
                    $real_level = $abilities_progress["{$u['id_character']}-{$info['id']}"] ?? 1;

                    // Plafond du bonus d'affichage talent 3 : le vrai max DE TABLE (terminateurs
                    // à coût 0 inclus, ex. 13/14/15), PAS le max "réellement achetable"
                    // (getAbilityRealMaxLevel). C'est volontaire côté jeu : ces paliers à coût 0
                    // au-delà du max achetable existent justement pour être atteints via le bonus
                    // du talent 3, voir getAbilityTableMaxLevel() dans functions.php.
                    $ability_table_max_level = $ab_levels ? getAbilityTableMaxLevel($ab_levels) : $real_level;

                    // 🔥 Bonus talent 3 : +2 niveaux affichés (n'affecte PAS le coût, qui reste
                    // calculé sur le niveau réel juste au-dessus), plafonné au vrai max de table.
                    $display_level = $talent3_unlocked ? min($ability_table_max_level, $real_level + 2) : $real_level;

                    $officer_abilities[$type] = [
                        'id_ability' => $info['id'] ?? 0, 
                        'TID' => $ab_tid,
                        'IconExportName' => $info['IconExportName'] ?? 'default',
                        'nom' => $info['FR'] ?? $ab_tid,
                        'current_level' => $real_level,
                        'display_level' => $display_level,
                        'levels' => $ab_levels
                    ];
                }
            }
        }

        if ($is_hero) {
            // Les Héros ont exactement 3 capacités (Type = 'HeroAbility' dans abilitieid),
            // reliées via la colonne abilitieid.hero = characterid.TID (une CHAÎNE, ex.
            // 'TID_HEROJETPACK' — PAS characterid.id), ordonnées par id.
            // Mêmes coûts/temps que les officiers : table officer_abilities (TID, Niveau,
            // HeroLevel, UpgradeTimeH, UpgradeCost, UpgradeResource).
            $stmt_hero_ab = $pdo->prepare("
                SELECT ai.id, ai.TID, ai.IconExportName, t.$selected_lang AS nom
                FROM abilitieid ai
                LEFT JOIN texts t ON ai.TID = t.TID
                WHERE ai.hero = ?
                ORDER BY unlock_order ASC
            ");
            $stmt_hero_ab->execute([$u['TID']]);
            $hero_ab_rows = $stmt_hero_ab->fetchAll(PDO::FETCH_ASSOC);

            foreach ($hero_ab_rows as $hab) {
                $stmt_hero_lvls = $pdo->prepare("
                    SELECT Niveau, HeroLevel, UpgradeTimeH, UpgradeCost, UpgradeResource
                    FROM officer_abilities WHERE TID = ? ORDER BY Niveau ASC
                ");
                $stmt_hero_lvls->execute([$hab['TID']]);

                $hero_abilities[] = [
                    'id_ability'     => (int)$hab['id'],
                    'TID'            => $hab['TID'],
                    'IconExportName' => $hab['IconExportName'] ?? 'default',
                    'nom'            => $hab['nom'] ?? $hab['TID'],
                    'current_level'  => $abilities_progress["{$u['id_character']}-{$hab['id']}"] ?? 1,
                    'levels'         => $stmt_hero_lvls->fetchAll(PDO::FETCH_ASSOC),
                ];
            }
        }

        // --- Niveau max ATTEIGNABLE + coût du prochain niveau ---
        // Une seule colonne UpgradeCost en base (pas de distinction Or/Proto-jetons stockée) :
        // la distinction se fait côté affichage selon la Class ('Troupe' => Or, 'Proto' => Jetons Proto).
        // UpgradeTimeH est exprimé en HEURES (formatUnitsTime() le convertit en j/h/m à l'affichage).
        // Le coût pour passer de niveau_joueur à niveau_joueur+1 est stocké sur la ligne
        // "characters" du niveau ACTUEL (même convention que upgrade_character.php : Niveau = target_level - 1).
        $stmt_max_char = $pdo->prepare("SELECT MAX(Niveau) FROM characters WHERE TID = ?");
        $stmt_max_char->execute([$u['TID']]);
        $niveau_max_absolu = (int)($stmt_max_char->fetchColumn() ?: $niveau_joueur);

        // Le "vrai" plafond du moment dépend du bâtiment/QG réel du joueur, pas du dernier
        // palier théorique de l'unité (characters.UpgradeHouseLevel) :
        // Troupes/Capacités de canonnière -> Arsenal (id_building 12)
        // Proto-troupes                   -> Atelier de Proto-troupes (id_building 29)
        // Héros                           -> QG du joueur DIRECTEMENT (pas l'Arsenal/l'Atelier)
        // Officiers                       -> pas de palier de bâtiment, le max reste absolu.
        $class_trim      = trim($u['Class'] ?? '');
        $is_troop_class  = (strcasecmp($class_trim, 'Troupe') === 0);
        $is_proto_class  = (strcasecmp($class_trim, 'Proto') === 0);
        $is_spell_class  = (strcasecmp($class_trim, 'Spell') === 0);
        $is_hero_class   = (strcasecmp($class_trim, 'Hero') === 0);

        if ($is_troop_class || $is_spell_class) {
            $relevant_house_level = (int)($house_levels['arsenal'] ?? 0);
        } elseif ($is_proto_class) {
            $relevant_house_level = (int)($house_levels['proto_factory'] ?? 0);
        } elseif ($is_hero_class) {
            $relevant_house_level = (int)$qg;
        } else {
            $relevant_house_level = null;
        }

        if ($relevant_house_level !== null) {
            // characters.UpgradeHouseLevel sur la ligne Niveau=N est le niveau (Arsenal / Atelier
            // / QG selon la classe) requis pour ATTEINDRE le niveau N — confirmé contre le wiki
            // Boom Beach : à Arsenal 1, Fusilier et Gros bras plafonnent tous les deux à Niveau 2.
            // Donc pas de +1 : le niveau max atteignable est le plus haut Niveau dont le palier
            // est satisfait par le niveau réel (bâtiment ou QG) du joueur.
            $stmt_cap = $pdo->prepare("SELECT MAX(Niveau) FROM characters WHERE TID = ? AND UpgradeHouseLevel <= ?");
            $stmt_cap->execute([$u['TID'], $relevant_house_level]);
            $niveau_max = (int)($stmt_cap->fetchColumn() ?: 1);
            $niveau_max = min($niveau_max_absolu, max(1, $niveau_max));
        } else {
            $niveau_max = $niveau_max_absolu;
        }

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

                // Total restant : somme de tous les niveaux entre l'actuel et le plafond ATTEIGNABLE
                // (donc jusqu'à ce que l'Arsenal/Atelier soit lui-même amélioré, pas jusqu'au max absolu).
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
            'is_hero' => $is_hero,
            'niveau_joueur' => $niveau_joueur,
            'Debloque' => $u['Debloque'] ?? 0,
            'total_talents_unlocked' => $total_talents_unlocked,
            'next_talent_id' => $next_talent_id,
            'abilities' => $officer_abilities,
            'hero_abilities' => $hero_abilities,
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
    global $selected_lang;
    // 1. Récupérer la progression du joueur pour les bâtiments
    $stmt_prog = $pdo->prepare("SELECT id_building, id_instance, niveau FROM progress_buildings WHERE id_player = ?");
    $stmt_prog->execute([$id_player]);
    $progress = $stmt_prog->fetchAll(PDO::FETCH_KEY_PAIR); // Retourne [id_batiment => niveau]

    // 2. Requête unifiée : Bâtiments + Noms + Catégories
    $sql = "SELECT b.id, b.TID, b.Category, t.$selected_lang AS nom, b.IconExportName
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



// Le Monument mystique (TID_BUILDING_CC, id_building = 32) est un bâtiment comme les autres :
// son niveau se lit dans progress_building, exactement comme le Radar ou l'Arsenal.
// (progress_monument, plus bas, sert à tout autre chose : le nombre de bonus du Cercle des
// Chefs choisis par le joueur, pas le niveau du monument lui-même.)
$stmt_monument = $pdo->prepare("SELECT niveau FROM progress_building WHERE id_player = ? AND id_building = 32 LIMIT 1");
$stmt_monument->execute([$id_player]);
$monument_level = (int)($stmt_monument->fetchColumn() ?: 0);

// 2. Récupérer tous les bonus disponibles
$stmt_bonuses = $pdo->prepare("SELECT id_bonus, t.$selected_lang AS TID, MaxCount, BoostAmount, MinBuildingLevel FROM cc_bonuses INNER JOIN texts t ON t.TID = cc_bonuses.TID ORDER BY MinBuildingLevel ASC, TID ASC");
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

// Le Graveur (TID_BUILDING_ARTIFACT_RESEARCH, id_building = 1) conditionne l'accès aux Gravures :
// l'onglet entier nécessite qu'il soit construit (niveau >= 1), et la sous-catégorie Défensive
// nécessite en plus qu'il soit au niveau 2.
$stmt_graveur = $pdo->prepare("SELECT niveau FROM progress_building WHERE id_player = ? AND id_building = 1 LIMIT 1");
$stmt_graveur->execute([$id_player]);
$graveur_level = (int)($stmt_graveur->fetchColumn() ?: 0);


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

// Trouver l'Arsenal Max permis par le QG actuel (déjà calculé plus haut, en amont de
// getBuildingsDisplay(), dans $arsenal_current_max — on ne refait pas la requête ici).
$qg = (int)$qg;

// Idem pour l'Atelier de Proto-troupes (même principe, symétrique)
$stmt_proto_max = $pdo->prepare("SELECT MAX(Niveau) as proto_max FROM buildings WHERE TID = 'TID_PROTOTROOP_FACTORY' AND TownHallLevel <= :qg");
$stmt_proto_max->execute(['qg' => $qg]);
$proto_factory_current_max = (int)($stmt_proto_max->fetch(PDO::FETCH_ASSOC)['proto_max'] ?? 1);

// --- NIVEAU RÉEL CONSTRUIT PAR LE JOUEUR : Atelier de Proto-troupes (id_building 29) ---
// Le niveau réel de l'Arsenal (id_building 12) a déjà été récupéré plus haut dans
// $arsenal_level_reel (nécessaire à getBuildingsDisplay pour gater les Pièges).
$house_levels = ['arsenal' => $arsenal_level_reel, 'proto_factory' => 0];
try {
    $stmt_house = $pdo->prepare("
        SELECT MAX(niveau) AS niveau
        FROM progress_building
        WHERE id_player = ? AND id_building = 29
    ");
    $stmt_house->execute([$id_player]);
    $house_levels['proto_factory'] = (int)($stmt_house->fetch(PDO::FETCH_ASSOC)['niveau'] ?? 0);
} catch (PDOException $e) {
    error_log("Erreur récupération niveau Atelier Proto : " . $e->getMessage());
}

// Le niveau "réel" ne peut jamais dépasser ce que le QG actuel permet pour ce bâtiment.
// Exemple concret : Arsenal niveau 2 nécessite QG5. Si le joueur est encore QG4 (donc
// arsenal_current_max = 1), même si progress_building contenait par erreur "niveau 2"
// (résidu de bug), on le ramène à 1 : impossible d'aller plus loin tant qu'on n'a pas QG5.
// Ce plafonnement sert à la fois pour le message "Arsenal Niv. X requis" (bouton verrouillé)
// ET pour le niveau max affiché (Niveau X / Y) des troupes/proto-troupes/capacités.
// (arsenal déjà plafonné plus haut, dans $arsenal_level_reel)
$house_levels['proto_factory'] = min($house_levels['proto_factory'], $proto_factory_current_max);

// Génération des listes
$troupes_list  = getFilteredUnits($pdo, $qg, $arsenal_current_max, "ci.Class = 'Troupe'", $character_progress, $house_levels);
$heros_list    = getFilteredUnits($pdo, $qg, $arsenal_current_max, "ci.Class = 'Hero'", $character_progress, $house_levels);
$proto_list    = getFilteredUnits($pdo, $qg, $arsenal_current_max, "ci.Class = 'Proto'", $character_progress, $house_levels);
$officers_list = getFilteredUnits($pdo, $qg, $arsenal_current_max, "ci.Class = 'Officier'", $character_progress, $house_levels);
$capacanon_list = getFilteredUnits($pdo, $qg, $arsenal_current_max, "ci.Class = 'Spell'", $character_progress, $house_levels);




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
        SELECT ei.id AS id_engraving, e.TID, ei.Category, ei.Type, ei.IconExportName, IFNULL(t.$selected_lang, e.TID) AS nom, MAX(e.Quality) as niveau_max
        FROM engravings e
        JOIN engravingid ei ON e.TID = ei.TID
        LEFT JOIN texts t ON e.TID = t.TID
        GROUP BY ei.id, e.TID, ei.Category, ei.Type, ei.IconExportName, t.$selected_lang
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

// ========================================================
// 13. RECUPERATION DES TRIBUS ET DE LA PROGRESSION
// ========================================================
// Schéma réel :
//   - tribsid(id, TID, RadarLvlReq, IconExportName)      -> 6 tribus, 1 ligne chacune
//   - tribs(TID, Niveau, UpgradeCost, UpgradeTimeM)       -> coût en Cristaux bruts, temps en MINUTES
//   - progress_tribs(id_player, id_trib, niveau, Debloque) -> id_trib = tribsid.id
//   - Le Radar (Salle des cartes) = buildings.TID 'TID_BUILDING_MAP_ROOM' / id_building = 14,
//     son niveau joueur est lu dans progress_building comme n'importe quel autre bâtiment.
//   - Quand le Radar atteint le niveau requis, seedTribusUnlockRows() (functions.php) crée
//     une ligne "placeholder" (niveau=0, Debloque=0) ; le joueur doit ensuite cliquer sur
//     "Débloquer" (upgrade_tribs.php) pour faire passer Debloque à 1.

$tribus_list = [];

// Niveau actuel du Radar du joueur : conditionne le déblocage de chaque tribu
$radar_level = 0;
try {
    $stmt_radar = $pdo->prepare("SELECT niveau FROM progress_building WHERE id_player = ? AND id_building = 14 LIMIT 1");
    $stmt_radar->execute([$id_player]);
    $radar_level = (int)($stmt_radar->fetchColumn() ?: 0);
} catch (PDOException $e) {
    error_log("Erreur radar (tribus) : " . $e->getMessage());
}

// --- CONDITIONS DE DÉBLOCAGE DES ONGLETS DE LA SIDEBAR ---
// (radar_level, monument_level et graveur_level sont déjà calculés plus haut)
$tab_tribus_unlocked        = ($radar_level >= 18);
$tab_monument_unlocked      = ($monument_level > 0);
$tab_gravures_unlocked      = ($graveur_level > 0);
$tab_gravures_off_unlocked  = ($graveur_level > 0);
$tab_gravures_def_unlocked  = ($graveur_level >= 2);

// Progression du joueur sur chaque tribu, indexée par id_trib (= tribsid.id).
// 'niveau' = palier atteint ; 'debloque' = le joueur a bien cliqué sur "Débloquer"
// (une ligne peut exister avec Debloque = 0 : c'est le "placeholder" créé quand le
// Radar atteint le niveau requis).
$tribus_progress = [];
try {
    $stmt_trib_prog = $pdo->prepare("SELECT id_trib, niveau, Debloque FROM progress_tribs WHERE id_player = ?");
    $stmt_trib_prog->execute([$id_player]);
    while ($row = $stmt_trib_prog->fetch(PDO::FETCH_ASSOC)) {
        $tribus_progress[(int)$row['id_trib']] = [
            'niveau'   => (int)$row['niveau'],
            'debloque' => (int)$row['Debloque'],
        ];
    }
} catch (PDOException $e) {
    error_log("Erreur progress_tribs : " . $e->getMessage());
}

// Coûts (Cristaux bruts) + temps (en minutes) par TID + palier de niveau.
// IMPORTANT : construit AVANT la boucle principale, qui s'en sert pour chaque tribu.
$tribus_costs = [];
try {
    $stmt_trib_costs = $pdo->query("
        SELECT TID, Niveau, UpgradeCost, UpgradeTimeM
        FROM tribs
        ORDER BY TID ASC, Niveau ASC
    ");
    while ($cost_row = $stmt_trib_costs->fetch(PDO::FETCH_ASSOC)) {
        $cost_tid = $cost_row['TID'];
        $lvl      = (int)$cost_row['Niveau'];

        $tribus_costs[$cost_tid][$lvl] = [
            'cost'     => (int)($cost_row['UpgradeCost']  ?? 0),
            // On garde le temps en MINUTES ici ; la conversion en j/h/m se fait à l'affichage
            // via formatUnitsTime(), qui attend des HEURES (d'où le /60 dans functions.php).
            'time_min' => (int)($cost_row['UpgradeTimeM'] ?? 0),
        ];
    }
} catch (PDOException $e) {
    error_log("Erreur coûts tribs : " . $e->getMessage());
}

// Niveau max de chaque tribu = nombre de paliers définis dans la table tribs pour ce TID
$tribus_niveau_max = [];
foreach ($tribus_costs as $tid => $levels) {
    $tribus_niveau_max[$tid] = max(array_keys($levels));
}

try {
    // Requête principale : les 6 tribus + leur condition de déblocage (RadarLvlReq)
    $stmt_tribsid = $pdo->query("
        SELECT ti.id AS id_trib, ti.TID, ti.RadarLvlReq, ti.IconExportName, IFNULL(t.$selected_lang, ti.TID) AS nom
        FROM tribsid ti
        LEFT JOIN texts t ON ti.TID = t.TID
        ORDER BY ti.RadarLvlReq ASC, ti.TID ASC
    ");

    while ($row = $stmt_tribsid->fetch(PDO::FETCH_ASSOC)) {
        $id_trib   = (int)$row['id_trib'];
        $tid       = $row['TID'];
        $radar_req = (int)$row['RadarLvlReq'];
        $progress  = $tribus_progress[$id_trib] ?? ['niveau' => 0, 'debloque' => 0];

        $row['niveau_actuel'] = $progress['niveau'];
        $row['niveau_max']    = $tribus_niveau_max[$tid] ?? 5;
        $row['costs']         = $tribus_costs[$tid] ?? [];
        $row['debloque']      = ($progress['debloque'] === 1);
        // 'radar_ok' = le Radar est assez haut pour que la tribu soit au moins visible
        // (normalement, dès que c'est le cas, seedTribusUnlockRows() a déjà créé la ligne
        // progress_tribs correspondante ; on garde ce check en filet de sécurité).
        $row['radar_ok']      = ($radar_level >= $radar_req);
        $row['radar_actuel']  = $radar_level;

        $tribus_list[] = $row;
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des tribus : " . $e->getMessage());
}
// ========================================================