<?php
// admin_config.php
// Config centralisée + helpers pour la page d'administration (admin.php / admin_api.php).
// Toute la logique d'accès aux données passe par ici pour éviter de dupliquer les
// requêtes entre la page (rendu initial) et l'API (AJAX : CRUD + upload d'images).

// --- Accès réservé --------------------------------------------------------
// Seul ce compte joueur peut accéder à l'admin. id_player est la clé texte
// (ex: '2PQJ9CPQ8'), pas un id numérique — voir table `joueurs`.
define('ADMIN_PLAYER_ID', '2PQJ9CPQ8');

function admin_check_access() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $current = $_SESSION['player_id'] ?? null;
    return ($current === ADMIN_PLAYER_ID);
}

// --- Définition des types d'entités éditables ------------------------------
// Chaque entité a :
//   - id_table / id_pk   : table de "définition" (1 ligne par TID) + sa clé
//   - id_label_col       : colonne(s) affichées dans la liste déroulante
//   - id_fields          : champs éditables sur la ligne de définition (hors niveaux)
//   - data_table          : table contenant une ligne PAR NIVEAU
//   - level_col           : nom de la colonne "niveau" dans data_table (Niveau / Quality)
//   - fields              : champs éditables de la table de niveaux
//   - image               : où/comment est stockée l'image liée (voir resolveImagePath)
$GLOBALS['ADMIN_ENTITIES'] = [

    'building' => [
        'label'        => 'Bâtiments',
        'id_table'     => 'buildingid',
        'id_pk'        => 'TID',
        'class_field'  => 'Class',
        'id_fields'    => [
            'Class' => ['label' => 'Catégorie', 'type' => 'text'],
            'Ordre' => ['label' => 'Ordre d\'affichage', 'type' => 'int'],
        ],
        'data_table'   => 'buildings',
        'level_col'    => 'Niveau',
        'fields'       => [
            'Niveau'          => ['label' => 'Niveau',        'type' => 'int', 'required' => true],
            'TownHallLevel'   => ['label' => 'QG requis',     'type' => 'int'],
            'BuildTimeD'      => ['label' => 'Temps (j)',     'type' => 'int'],
            'BuildTimeH'      => ['label' => 'Temps (h)',     'type' => 'int'],
            'BuildTimeM'      => ['label' => 'Temps (m)',     'type' => 'int'],
            'BuildTimeS'      => ['label' => 'Temps (s)',     'type' => 'int'],
            'BuildCostGold'   => ['label' => 'Coût Or',       'type' => 'int'],
            'BuildCostWood'   => ['label' => 'Coût Bois',     'type' => 'int'],
            'BuildCostStone'  => ['label' => 'Coût Pierre',   'type' => 'int'],
            'BuildCostIron'   => ['label' => 'Coût Fer',      'type' => 'int'],
            'XpGain'          => ['label' => 'XP gagné',      'type' => 'int'],
            'ExportName'      => ['label' => 'Nom image (ExportName)', 'type' => 'text'],
        ],
        // Une image PAR NIVEAU (chaque palier a sa propre illustration).
        // Chemin réel utilisé par le site : images/{ExportName}.WEBP (majuscules, voir functions.php)
        'image' => [
            'scope'     => 'level',        // 'level' = liée à la ligne de niveau, 'id' = liée au TID
            'name_field'=> 'ExportName',
            'dir'       => 'images',
            'ext'       => 'WEBP',
            'subdir'    => null,
        ],
    ],

    'character' => [
        'label'        => 'Troupes / Héros / Proto-troupes / Officiers / Sorts',
        'id_table'     => 'characterid',
        'id_pk'        => 'TID',
        'class_field'  => 'Class',
        'id_fields'    => [
            'Class'          => ['label' => 'Classe (Troupe/Hero/Proto/Officier/Spell)', 'type' => 'text'],
            'HQUnlock'       => ['label' => 'QG requis',            'type' => 'int'],
            'IconExportName' => ['label' => 'Nom image (IconExportName)', 'type' => 'text'],
            'Officer'        => ['label' => 'TID troupe liée (si Officier)', 'type' => 'text'],
            'Display'        => ['label' => 'Display (hérité de la troupe liée)', 'type' => 'int'],
            'Type'           => ['label' => 'Type (Lt = Lieutenant / Sgt = Sergent)', 'type' => 'text'],
        ],
        'data_table'   => 'characters',
        'level_col'    => 'Niveau',
        'fields'       => [
            'Niveau'            => ['label' => 'Niveau', 'type' => 'int', 'required' => true],
            'UpgradeHouseLevel' => ['label' => 'Niveau bâtiment requis (Arsenal/Atelier/QG)', 'type' => 'int'],
            'UpgradeTimeH'      => ['label' => 'Temps (h)', 'type' => 'int'],
            'UpgradeCost'       => ['label' => 'Coût', 'type' => 'int'],
            'XpGain'            => ['label' => 'XP gagné', 'type' => 'int'],
        ],
        // L'icône est liée au TID (characterid.IconExportName), pas au niveau.
        // Chemin réel : images/characters/{Class}/{IconExportName}.png
        'image' => [
            'scope'      => 'id',
            'name_field' => 'IconExportName',
            'dir'        => 'images/characters',
            'ext'        => 'png',
            'subdir_from'=> 'Class', // sous-dossier = valeur de characterid.Class
        ],
    ],

    'engraving' => [
        'label'        => 'Gravures',
        'id_table'     => 'engravingid',
        'id_pk'        => 'TID',
        'class_field'  => 'Category',
        'id_fields'    => [
            'Category'       => ['label' => 'Catégorie (Offensive/Defensive)', 'type' => 'text'],
            'Type'           => ['label' => 'Type (rareté)', 'type' => 'int'],
            'IconExportName' => ['label' => 'Nom image (IconExportName)', 'type' => 'text'],
        ],
        'data_table'   => 'engravings',
        'level_col'    => 'Quality',
        'fields'       => [
            'Quality'        => ['label' => 'Palier (Quality)', 'type' => 'int', 'required' => true],
            'ResearchNeeded' => ['label' => 'Recherche requise', 'type' => 'int'],
            'TokensNeeded'   => ['label' => 'Jetons requis', 'type' => 'int'],
            'Values'         => ['label' => 'Valeur du bonus', 'type' => 'int'],
            'MaxQuality'     => ['label' => 'Palier max', 'type' => 'int'],
        ],
        // Icône liée au TID (engravingid.IconExportName).
        // Chemin réel : images/engravings/{IconExportName}.png
        'image' => [
            'scope'      => 'id',
            'name_field' => 'IconExportName',
            'dir'        => 'images/engravings',
            'ext'        => 'png',
            'subdir_from'=> null,
        ],
    ],
];

function admin_get_entity($type) {
    return $GLOBALS['ADMIN_ENTITIES'][$type] ?? null;
}

/**
 * Valeurs distinctes du champ "classe/catégorie" (characterid.Class,
 * buildingid.Class, engravingid.Category) pour peupler le 2e filtre en cascade.
 */
function admin_list_classes(PDO $pdo, $type) {
    $entity = admin_get_entity($type);
    if (!$entity || empty($entity['class_field'])) return [];
    $col = $entity['class_field'];
    $sql = "SELECT DISTINCT `{$col}` AS val FROM `{$entity['id_table']}`
            WHERE `{$col}` IS NOT NULL AND `{$col}` <> ''
            ORDER BY `{$col}` ASC";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Liste des TID pour la liste déroulante, avec le libellé FR (table texts) quand dispo.
 * $class (optionnel) filtre sur le champ classe/catégorie de l'entité.
 */
function admin_list_tids(PDO $pdo, $type, $class = null) {
    $entity = admin_get_entity($type);
    if (!$entity) return [];

    $idTable = $entity['id_table'];
    $idPk    = $entity['id_pk'];

    $sql = "SELECT e.{$idPk} AS tid, t.FR AS label
            FROM `{$idTable}` e
            LEFT JOIN texts t ON t.TID = e.{$idPk}";
    $params = [];
    if ($class !== null && $class !== '' && !empty($entity['class_field'])) {
        $sql .= " WHERE e.`{$entity['class_field']}` = ?";
        $params[] = $class;
    }
    $sql .= " ORDER BY e.{$idPk} ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['label'] = $r['label'] ?: $r['tid'];
    }
    return $rows;
}

/**
 * Liste des troupes (characterid.Class = 'Troupe') pour peupler le menu déroulant
 * "Officer" du formulaire d'ajout — on n'affiche QUE leur nom, mais la valeur
 * envoyée/stockée est bien leur TID (voir renderisation côté admin.js).
 */
function admin_list_troop_names(PDO $pdo) {
    $sql = "SELECT ci.TID AS tid, t.FR AS label
            FROM characterid ci
            LEFT JOIN texts t ON t.TID = ci.TID
            WHERE ci.Class = 'Troupe'
            ORDER BY t.FR ASC, ci.TID ASC";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['label'] = $r['label'] ?: $r['tid'];
    }
    return $rows;
}

/**
 * Ligne de "définition" (characterid / buildingid / engravingid) pour un TID donné.
 */
function admin_get_id_row(PDO $pdo, $type, $tid) {
    $entity = admin_get_entity($type);
    if (!$entity) return null;
    $stmt = $pdo->prepare("SELECT * FROM `{$entity['id_table']}` WHERE `{$entity['id_pk']}` = ? LIMIT 1");
    $stmt->execute([$tid]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Toutes les lignes de niveaux (buildings/characters/engravings) pour un TID, triées.
 */
function admin_get_levels(PDO $pdo, $type, $tid) {
    $entity = admin_get_entity($type);
    if (!$entity) return [];
    $stmt = $pdo->prepare("SELECT * FROM `{$entity['data_table']}` WHERE TID = ? ORDER BY `{$entity['level_col']}` ASC");
    $stmt->execute([$tid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Insère ou met à jour une ligne de niveau. $data doit contenir toutes les colonnes
 * définies dans $entity['fields'] (dont le niveau lui-même). $original_level permet
 * de gérer le cas d'une modification DU niveau (clé primaire) : on retrouve l'ancienne
 * ligne pour faire un UPDATE ciblé plutôt qu'un doublon.
 */
function admin_save_level(PDO $pdo, $type, $tid, array $data, $original_level = null) {
    $entity = admin_get_entity($type);
    if (!$entity) throw new Exception("Type d'entité inconnu.");

    $table     = $entity['data_table'];
    $levelCol  = $entity['level_col'];
    $fields    = $entity['fields'];

    // Validation + nettoyage des types
    $clean = [];
    foreach ($fields as $col => $def) {
        $val = $data[$col] ?? null;
        if (($def['required'] ?? false) && ($val === null || $val === '')) {
            throw new Exception("Le champ « {$def['label']} » est obligatoire.");
        }
        if ($def['type'] === 'int') {
            $clean[$col] = ($val === null || $val === '') ? 0 : (int)$val;
        } else {
            $clean[$col] = (string)$val;
        }
    }

    $checkLevel = $original_level !== null ? $original_level : $clean[$levelCol];
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE TID = ? AND `{$levelCol}` = ?");
    $stmt_check->execute([$tid, $checkLevel]);
    $exists = (int)$stmt_check->fetchColumn() > 0;

    if ($exists) {
        $setParts = [];
        $params = [];
        foreach ($clean as $col => $val) {
            $setParts[] = "`{$col}` = ?";
            $params[] = $val;
        }
        $params[] = $tid;
        $params[] = $checkLevel;
        $sql = "UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE TID = ? AND `{$levelCol}` = ?";
        $pdo->prepare($sql)->execute($params);
    } else {
        $cols = array_merge(['TID'], array_keys($clean));
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $params = array_merge([$tid], array_values($clean));
        $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $cols) . "`) VALUES ({$placeholders})";
        $pdo->prepare($sql)->execute($params);
    }
}

function admin_delete_level(PDO $pdo, $type, $tid, $level) {
    $entity = admin_get_entity($type);
    if (!$entity) throw new Exception("Type d'entité inconnu.");
    $stmt = $pdo->prepare("DELETE FROM `{$entity['data_table']}` WHERE TID = ? AND `{$entity['level_col']}` = ?");
    $stmt->execute([$tid, $level]);
}

/**
 * Met à jour les champs éditables de la ligne "définition" (id_table), ex: Class,
 * HQUnlock, IconExportName... Le TID lui-même n'est jamais modifiable ici (clé
 * étrangère utilisée par toutes les tables de progression des joueurs).
 */
function admin_save_id_row(PDO $pdo, $type, $tid, array $data) {
    $entity = admin_get_entity($type);
    if (!$entity) throw new Exception("Type d'entité inconnu.");

    $setParts = [];
    $params = [];
    foreach ($entity['id_fields'] as $col => $def) {
        $val = $data[$col] ?? null;
        $val = ($def['type'] === 'int') ? (int)$val : (string)$val;
        $setParts[] = "`{$col}` = ?";
        $params[] = $val;
    }
    if (!$setParts) return;
    $params[] = $tid;
    $sql = "UPDATE `{$entity['id_table']}` SET " . implode(', ', $setParts) . " WHERE `{$entity['id_pk']}` = ?";
    $pdo->prepare($sql)->execute($params);
}

/**
 * Calcule où doit être physiquement enregistrée une image uploadée pour ce
 * type/TID(/niveau), en respectant EXACTEMENT les chemins déjà utilisés par le
 * reste du site (voir functions.php : renderBuildingsTable / renderUnitsTable /
 * renderEngravingsTable) — sinon l'image uploadée ne serait jamais affichée.
 *
 * Retourne ['abs_path' => ..., 'name' => ...] ou lève une Exception si le nom
 * d'export (ExportName / IconExportName) n'est pas encore renseigné.
 */
function admin_resolve_image_path(PDO $pdo, $type, $tid, $level = null) {
    $entity = admin_get_entity($type);
    if (!$entity) throw new Exception("Type d'entité inconnu.");
    $img = $entity['image'];

    if ($img['scope'] === 'level') {
        if ($level === null) throw new Exception("Niveau manquant pour l'upload.");
        $stmt = $pdo->prepare("SELECT * FROM `{$entity['data_table']}` WHERE TID = ? AND `{$entity['level_col']}` = ? LIMIT 1");
        $stmt->execute([$tid, $level]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $name = $row[$img['name_field']] ?? null;
        $dir  = $img['dir'];
    } else {
        $row = admin_get_id_row($pdo, $type, $tid);
        $name = $row[$img['name_field']] ?? null;
        $dir  = $img['dir'];
        if (!empty($img['subdir_from']) && $row) {
            $subdir = trim((string)($row[$img['subdir_from']] ?? ''));
            if ($subdir !== '') {
                $dir .= '/' . $subdir;
            }
        }
    }

    if (!$name) {
        throw new Exception("Renseigne d'abord le nom d'export de l'image (champ « {$img['name_field']} ») avant de l'uploader.");
    }

    $filename = $name . '.' . $img['ext'];
    return [
        'dir'      => $dir,
        'filename' => $filename,
        'abs_path' => rtrim(__DIR__, '/') . '/' . $dir . '/' . $filename,
    ];
}

/**
 * Ajoute un nouveau personnage dans characterid, et — uniquement si Class = 'Officier' —
 * la ligne de talents correspondante dans officer_talents.
 *
 * Convention de nommage des capacités/talents (déduite des données existantes) :
 *   ActiveAbility  = {TID}_ACTIVE_ABILITY_TITLE
 *   PassiveAbility = {TID}_PASSIVE_ABILITY_TITLE
 *   TalentTID1     = {TID}_TALENT_1
 *   TalentTID2     = {TID}_TALENT_2
 *   TalentTID3     = TID_GENERIC_OFC_SKILLED (ou TID_GENERIC_OFC_SKILLED_CYBER si "CYBER"
 *                     apparaît dans le TID du nouvel officier)
 *   TalentTID4     = {TID}_TALENT_4
 *   TalentTID5     = {TID}_TALENT_5
 *
 * $officer_rank vaut 'Sergent' ou 'Lieutenant' :
 *   - Lieutenant → ActiveAbility ET PassiveAbility sont toutes les deux renseignées.
 *   - Sergent    → un seul talent au total : $officer_ability ('Active' ou 'Passive')
 *                  indique laquelle des deux colonnes est renseignée (l'autre reste vide).
 *
 * En plus de la ligne officer_talents, une ligne est insérée dans `abilitieid` pour
 * chaque talent/capacité effectivement créé (TalentTID1/2/4/5 + ActiveAbility et/ou
 * PassiveAbility selon le rang) :
 *   - Talent 1/2/4/5 → IconExportName = talent_1_icon / talent_2_icon / talent_4_icon / talent_5_icon
 *   - Talent 3       → jamais inséré ici (ligne générique déjà existante et partagée :
 *                       TID_GENERIC_OFC_SKILLED ou _CYBER selon $is_cyber)
 *   - Capa passive   → IconExportName = icon_passive
 *   - Capa active    → IconExportName = icon_squad_command si "CYBER" apparaît dans le
 *                       TID de l'officier, sinon $active_icon (saisi manuellement, requis
 *                       dans ce cas — voir validation plus bas)
 *
 * Pour chaque capacité active/passive créée, 15 niveaux sont aussi insérés dans
 * `officer_abilities` (UpgradeCost/UpgradeResource copiés depuis la progression de
 * TID_RIFLEMAN_OFC_ASSAULT_PASSIVE_ABILITY_TITLE utilisée comme gabarit, HeroLevel
 * toujours à 1) — à corriger ensuite via les tableaux "Niveaux — Capacité active/passive"
 * si le coût réel diffère du gabarit.
 */
function admin_add_character(PDO $pdo, array $data) {
    $tid            = trim((string)($data['tid'] ?? ''));
    $class          = trim((string)($data['class'] ?? ''));
    $hq_unlock      = $data['hq_unlock'] ?? 0;
    $icon           = trim((string)($data['icon'] ?? ''));
    $officer_troop  = trim((string)($data['officer_troop'] ?? ''));   // TID de la troupe liée
    $officer_rank   = trim((string)($data['officer_rank'] ?? ''));    // 'Sergent' / 'Lieutenant'
    $officer_ability= trim((string)($data['officer_ability'] ?? '')); // 'Active' / 'Passive' (Sergent uniquement)
    $active_icon    = trim((string)($data['active_icon'] ?? ''));     // IconExportName de la capa active (non-CYBER uniquement)

    if ($tid === '') throw new Exception("Le TID est obligatoire.");
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tid)) throw new Exception("Le TID ne doit contenir que des lettres, chiffres et underscores.");
    if ($class === '') throw new Exception("La classe est obligatoire.");

    $stmt_dup = $pdo->prepare("SELECT 1 FROM characterid WHERE TID = ? LIMIT 1");
    $stmt_dup->execute([$tid]);
    if ($stmt_dup->fetch()) {
        throw new Exception("Ce TID existe déjà dans characterid.");
    }

    $is_officer = ($class === 'Officier');
    $is_cyber   = ($is_officer && stripos($tid, 'CYBER') !== false);

    if ($is_officer) {
        // Règle métier : le QG de déblocage d'un officier est toujours 7.
        $hq_unlock = 7;
        if ($officer_troop === '') throw new Exception("Choisis la troupe liée à cet officier.");
        if (!in_array($officer_rank, ['Sergent', 'Lieutenant'], true)) {
            throw new Exception("Précise si l'officier est Sergent ou Lieutenant.");
        }
        if ($officer_rank === 'Sergent' && !in_array($officer_ability, ['Active', 'Passive'], true)) {
            throw new Exception("Un Sergent n'a qu'un seul talent : précise s'il est Actif ou Passif.");
        }

        // Capa active : Lieutenant en a toujours une, Sergent seulement s'il a
        // choisi "Active". Pour un officier non-CYBER, l'IconExportName de cette
        // capa varie d'un officier à l'autre et doit donc être saisi à la main
        // (pour les CYBER, l'icône est toujours icon_squad_command, voir plus bas).
        $has_active = ($officer_rank === 'Lieutenant') || ($officer_rank === 'Sergent' && $officer_ability === 'Active');
        if ($has_active && !$is_cyber && $active_icon === '') {
            throw new Exception("Renseigne le nom d'image (IconExportName) de la capacité active.");
        }
    } else {
        $hq_unlock = (int)$hq_unlock;
        $officer_troop = null;
    }

    $pdo->beginTransaction();
    try {
        $new_id = (int)$pdo->query("SELECT COALESCE(MAX(ID), 0) + 1 FROM characterid")->fetchColumn();

        // Display : un officier hérite du Display de sa troupe liée (ex: TID_FLAMER a
        // Display=8 -> TID_FLAMER_OFC_DITTO reçoit aussi Display=8), pour que l'officier
        // se range visuellement au même endroit que sa troupe dans les listes triées.
        $display_value = null;
        if ($is_officer && $officer_troop) {
            $stmt_disp = $pdo->prepare("SELECT Display FROM characterid WHERE TID = ? LIMIT 1");
            $stmt_disp->execute([$officer_troop]);
            $fetched_display = $stmt_disp->fetchColumn();
            $display_value = ($fetched_display !== false && $fetched_display !== null) ? $fetched_display : null;
        }

        // Type : abréviation du rang, uniquement pour les officiers (Lt = Lieutenant, Sgt = Sergent).
        $type_value = $is_officer ? ($officer_rank === 'Lieutenant' ? 'Lt' : 'Sgt') : null;

        $stmt_insert = $pdo->prepare("
            INSERT INTO characterid (ID, TID, Class, HQUnlock, IconExportName, Officer, Display, Type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_insert->execute([$new_id, $tid, $class, $hq_unlock, $icon ?: null, $officer_troop, $display_value, $type_value]);

        if ($is_officer) {
            $talent3  = $is_cyber ? 'TID_GENERIC_OFC_SKILLED_CYBER' : 'TID_GENERIC_OFC_SKILLED';

            $active_ability  = '';
            $passive_ability = '';
            if ($officer_rank === 'Lieutenant') {
                $active_ability  = $tid . '_ACTIVE_ABILITY_TITLE';
                $passive_ability = $tid . '_PASSIVE_ABILITY_TITLE';
            } elseif ($officer_ability === 'Active') {
                $active_ability = $tid . '_ACTIVE_ABILITY_TITLE';
            } else { // 'Passive'
                $passive_ability = $tid . '_PASSIVE_ABILITY_TITLE';
            }

            $stmt_talents = $pdo->prepare("
                INSERT INTO officer_talents
                    (TID, ActiveAbility, PassiveAbility, TalentTID1, TalentTID2, TalentTID3, TalentTID4, TalentTID5)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_talents->execute([
                $tid,
                $active_ability,
                $passive_ability,
                $tid . '_TALENT_1',
                $tid . '_TALENT_2',
                $talent3,
                $tid . '_TALENT_4',
                $tid . '_TALENT_5',
            ]);

            // --- Lignes correspondantes dans `abilitieid` ------------------------
            // Talent 3 est exclu : c'est toujours une des deux lignes génériques
            // déjà existantes (TID_GENERIC_OFC_SKILLED / _CYBER), partagées par
            // tous les officiers — on ne la duplique jamais.
            $next_ability_id = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM abilitieid")->fetchColumn();
            $stmt_ability = $pdo->prepare("
                INSERT INTO abilitieid (id, TID, Type, IconExportName, hero, unlock_order)
                VALUES (?, ?, 'OfficerTalent', ?, NULL, NULL)
            ");

            $talent_icons = [
                1 => 'talent_1_icon',
                2 => 'talent_2_icon',
                4 => 'talent_4_icon',
                5 => 'talent_5_icon',
            ];
            foreach ($talent_icons as $num => $icon) {
                $stmt_ability->execute([$next_ability_id, $tid . '_TALENT_' . $num, $icon]);
                $next_ability_id++;
            }

            // Capacité passive : icône générique icon_passive.
            if ($passive_ability !== '') {
                $stmt_passive = $pdo->prepare("
                    INSERT INTO abilitieid (id, TID, Type, IconExportName, hero, unlock_order)
                    VALUES (?, ?, 'OfficerAbilityPassive', 'icon_passive', NULL, NULL)
                ");
                $stmt_passive->execute([$next_ability_id, $passive_ability]);
                $next_ability_id++;
            }

            // Capacité active : icône générique icon_squad_command pour les CYBER,
            // sinon l'icône saisie manuellement (elle varie d'un officier à l'autre).
            if ($active_ability !== '') {
                $active_icon_name = $is_cyber ? 'icon_squad_command' : $active_icon;
                $stmt_active = $pdo->prepare("
                    INSERT INTO abilitieid (id, TID, Type, IconExportName, hero, unlock_order)
                    VALUES (?, ?, 'OfficerAbilityActive', ?, NULL, NULL)
                ");
                $stmt_active->execute([$next_ability_id, $active_ability, $active_icon_name]);
                $next_ability_id++;
            }

            // --- Niveaux (officer_abilities) pour la/les capacités actives/passives --
            // Talents exclus (ils n'ont pas de progression coût/ressource dans cette table).
            // Le coût (UpgradeCost) et la ressource (UpgradeResource) de chaque palier
            // sont copiés depuis une capacité de référence dont la progression sert de
            // gabarit générique pour toute nouvelle capacité (à ajuster ensuite si le
            // coût réel diffère). HeroLevel est toujours forcé à 1.
            if ($active_ability !== '' || $passive_ability !== '') {
                $stmt_ref = $pdo->prepare("
                    SELECT Niveau, UpgradeCost, UpgradeResource
                    FROM officer_abilities
                    WHERE TID = 'TID_RIFLEMAN_OFC_ASSAULT_PASSIVE_ABILITY_TITLE'
                    ORDER BY Niveau ASC
                ");
                $stmt_ref->execute();
                $ref_levels = $stmt_ref->fetchAll(PDO::FETCH_ASSOC);

                if ($ref_levels) {
                    $stmt_ins_ability_lvl = $pdo->prepare("
                        INSERT INTO officer_abilities (TID, Niveau, HeroLevel, UpgradeTimeH, UpgradeCost, UpgradeResource)
                        VALUES (?, ?, 1, 0, ?, ?)
                    ");
                    foreach ([$active_ability, $passive_ability] as $ability_tid) {
                        if ($ability_tid === '') continue;
                        foreach ($ref_levels as $ref) {
                            $stmt_ins_ability_lvl->execute([
                                $ability_tid,
                                $ref['Niveau'],
                                $ref['UpgradeCost'],
                                $ref['UpgradeResource'],
                            ]);
                        }
                    }
                }
            }

            // --- Niveaux de l'officier dans `characters` -------------------------
            // On récupère le nombre de niveaux existants pour la troupe de base
            // (ex : TID_FLAMER -> 16 niveaux) et on crée le même nombre de lignes
            // vierges (Niveau 1 à N) pour le nouvel officier, à compléter ensuite
            // via le tableau "Niveaux" de sa fiche admin.
            $stmt_lvl_count = $pdo->prepare("SELECT COUNT(*) FROM characters WHERE TID = ?");
            $stmt_lvl_count->execute([$officer_troop]);
            $max_level = (int)$stmt_lvl_count->fetchColumn();

            if ($max_level > 0) {
                $stmt_ins_level = $pdo->prepare("INSERT INTO characters (TID, Niveau) VALUES (?, ?)");
                for ($lvl = 1; $lvl <= $max_level; $lvl++) {
                    $stmt_ins_level->execute([$tid, $lvl]);
                }
            }

            // --- Backfill progress_character pour tous les joueurs existants ----
            // Même logique que la synchronisation officier faite par upgrade_character.php
            // lors d'une amélioration de troupe : chaque joueur reçoit une ligne pour
            // ce nouvel officier, non débloquée (Debloque = 0), mais avec un niveau déjà
            // calé sur celui de SA troupe de base (pour rester cohérent dès le déblocage).
            $stmt_troop_char_id = $pdo->prepare("SELECT ID FROM characterid WHERE TID = ? LIMIT 1");
            $stmt_troop_char_id->execute([$officer_troop]);
            $troop_char_id = (int)$stmt_troop_char_id->fetchColumn();

            if ($troop_char_id) {
                $player_ids = $pdo->query("SELECT id_player FROM joueurs")->fetchAll(PDO::FETCH_COLUMN);
                $stmt_get_troop_lvl = $pdo->prepare("SELECT niveau FROM progress_character WHERE id_player = ? AND id_character = ? LIMIT 1");
                $stmt_ins_progress   = $pdo->prepare("INSERT INTO progress_character (id_player, id_character, niveau, Debloque) VALUES (?, ?, ?, 0)");

                foreach ($player_ids as $pid) {
                    $stmt_get_troop_lvl->execute([$pid, $troop_char_id]);
                    $troop_niveau = $stmt_get_troop_lvl->fetchColumn();
                    $troop_niveau = ($troop_niveau !== false) ? (int)$troop_niveau : 1;
                    $stmt_ins_progress->execute([$pid, $new_id, $troop_niveau]);
                }
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $tid;
}

/**
 * Libellé FR (table texts) pour un TID donné, avec repli sur le TID brut si absent.
 * Utilisé pour afficher un nom lisible dans l'en-tête de la fiche admin.
 */
function admin_get_tid_label(PDO $pdo, $tid) {
    $stmt = $pdo->prepare("SELECT FR FROM texts WHERE TID = ? LIMIT 1");
    $stmt->execute([$tid]);
    $label = $stmt->fetchColumn();
    return $label !== false && $label !== null && $label !== '' ? $label : $tid;
}

/**
 * Récapitulatif des talents/capacités d'un officier (table officer_talents),
 * enrichi du libellé FR (texts.FR) et de l'icône (abilitieid.IconExportName)
 * de chaque TID — pour visualiser d'un coup d'œil qui est quoi et éviter les
 * confusions entre talents/capacités qui se ressemblent (ex: TALENT_1 vs TALENT_4).
 *
 * Le Talent 3 (générique, partagé par tous les officiers : TID_GENERIC_OFC_SKILLED
 * ou _CYBER) est affiché mais marqué non éditable ici pour éviter d'écraser par
 * erreur le libellé/l'icône commun à tous les autres officiers.
 */
function admin_get_officer_abilities(PDO $pdo, $tid) {
    $stmt = $pdo->prepare("SELECT * FROM officer_talents WHERE TID = ? LIMIT 1");
    $stmt->execute([$tid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return [];

    $slots = [
        ['key' => 'TalentTID1',    'slot' => 'Talent 1',             'editable' => true],
        ['key' => 'TalentTID2',    'slot' => 'Talent 2',             'editable' => true],
        ['key' => 'TalentTID3',    'slot' => 'Talent 3 (générique)', 'editable' => false],
        ['key' => 'TalentTID4',    'slot' => 'Talent 4',             'editable' => true],
        ['key' => 'TalentTID5',    'slot' => 'Talent 5',             'editable' => true],
        ['key' => 'ActiveAbility', 'slot' => 'Capacité active',      'editable' => true],
        ['key' => 'PassiveAbility','slot' => 'Capacité passive',     'editable' => true],
    ];

    $stmt_txt  = $pdo->prepare("SELECT FR FROM texts WHERE TID = ? LIMIT 1");
    $stmt_icon = $pdo->prepare("SELECT IconExportName FROM abilitieid WHERE TID = ? LIMIT 1");

    $result = [];
    foreach ($slots as $s) {
        $abilityTid = trim((string)($row[$s['key']] ?? ''));
        if ($abilityTid === '') continue; // pas de capa (ex: Sergent qui n'a que l'une des deux)

        $stmt_txt->execute([$abilityTid]);
        $label = $stmt_txt->fetchColumn();

        $stmt_icon->execute([$abilityTid]);
        $icon = $stmt_icon->fetchColumn();

        $result[] = [
            'slot'     => $s['slot'],
            'tid'      => $abilityTid,
            'label'    => ($label !== false && $label !== null) ? $label : '',
            'icon'     => ($icon !== false && $icon !== null) ? $icon : '',
            'editable' => $s['editable'],
        ];
    }
    return $result;
}

/**
 * Met à jour le libellé FR (texts.FR) et l'icône (abilitieid.IconExportName)
 * d'un talent/capacité d'officier, identifié par son TID (ex:
 * TID_FLAMER_OFC_DITTO_TALENT_1). Le talent 3 générique est explicitement
 * bloqué : le modifier ici changerait l'affichage pour tous les officiers.
 */
function admin_save_officer_ability(PDO $pdo, $ability_tid, $label, $icon) {
    $ability_tid = trim((string)$ability_tid);
    if ($ability_tid === '') throw new Exception("TID de capacité manquant.");
    if (in_array($ability_tid, ['TID_GENERIC_OFC_SKILLED', 'TID_GENERIC_OFC_SKILLED_CYBER'], true)) {
        throw new Exception("Le talent 3 est générique et partagé entre tous les officiers : il ne peut pas être modifié ici.");
    }

    $label = trim((string)$label);
    $icon  = trim((string)$icon);

    $stmt_txt_exists = $pdo->prepare("SELECT 1 FROM texts WHERE TID = ? LIMIT 1");
    $stmt_txt_exists->execute([$ability_tid]);
    if ($stmt_txt_exists->fetch()) {
        $pdo->prepare("UPDATE texts SET FR = ? WHERE TID = ?")->execute([$label, $ability_tid]);
    } else {
        $pdo->prepare("INSERT INTO texts (TID, FR) VALUES (?, ?)")->execute([$ability_tid, $label]);
    }

    $stmt_icon_exists = $pdo->prepare("SELECT 1 FROM abilitieid WHERE TID = ? LIMIT 1");
    $stmt_icon_exists->execute([$ability_tid]);
    if ($stmt_icon_exists->fetch()) {
        $pdo->prepare("UPDATE abilitieid SET IconExportName = ? WHERE TID = ?")->execute([$icon, $ability_tid]);
    } else {
        // Ne devrait pas arriver : la ligne est créée automatiquement à l'ajout
        // de l'officier (voir admin_add_character). On la crée quand même en
        // secours plutôt que d'échouer silencieusement.
        $pdo->prepare("
            INSERT INTO abilitieid (id, TID, Type, IconExportName, hero, unlock_order)
            VALUES ((SELECT COALESCE(MAX(id), 0) + 1 FROM (SELECT id FROM abilitieid) x), ?, 'OfficerTalent', ?, NULL, NULL)
        ")->execute([$ability_tid, $icon]);
    }
}

// --- Niveaux d'une capacité active/passive (table officer_abilities) ------
// Distinct des "niveaux" de characterid/buildingid/engravingid gérés plus haut :
// ici la clé n'est pas un TID de personnage mais le TID de la capacité elle-même
// (ActiveAbility / PassiveAbility d'officer_talents), et la table cible est
// toujours `officer_abilities` (FK vers abilitieid.TID).
$GLOBALS['OFFICER_ABILITY_FIELDS'] = [
    'Niveau'          => ['label' => 'Niveau',              'type' => 'int', 'required' => true],
    'HeroLevel'       => ['label' => 'Niveau Hero requis',  'type' => 'int'],
    'UpgradeTimeH'    => ['label' => 'Temps (h)',           'type' => 'int'],
    'UpgradeCost'     => ['label' => 'Coût',                'type' => 'int'],
    'UpgradeResource' => ['label' => 'Ressource',           'type' => 'text'],
];

function admin_get_officer_ability_levels(PDO $pdo, $ability_tid) {
    $stmt = $pdo->prepare("SELECT * FROM officer_abilities WHERE TID = ? ORDER BY Niveau ASC");
    $stmt->execute([$ability_tid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Insère ou met à jour une ligne officer_abilities pour une capacité donnée.
 * Même logique que admin_save_level (validation + upsert), mais câblée en dur
 * sur officer_abilities puisque ce n'est pas une entité du menu principal.
 */
function admin_save_officer_ability_level(PDO $pdo, $ability_tid, array $data, $original_level = null) {
    $ability_tid = trim((string)$ability_tid);
    if ($ability_tid === '') throw new Exception("TID de capacité manquant.");

    $fields = $GLOBALS['OFFICER_ABILITY_FIELDS'];
    $clean = [];
    foreach ($fields as $col => $def) {
        $val = $data[$col] ?? null;
        if (($def['required'] ?? false) && ($val === null || $val === '')) {
            throw new Exception("Le champ « {$def['label']} » est obligatoire.");
        }
        if ($def['type'] === 'int') {
            $clean[$col] = ($val === null || $val === '') ? 0 : (int)$val;
        } else {
            $clean[$col] = (string)$val;
        }
    }

    $checkLevel = $original_level !== null ? $original_level : $clean['Niveau'];
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM officer_abilities WHERE TID = ? AND Niveau = ?");
    $stmt_check->execute([$ability_tid, $checkLevel]);
    $exists = (int)$stmt_check->fetchColumn() > 0;

    if ($exists) {
        $setParts = [];
        $params = [];
        foreach ($clean as $col => $val) {
            $setParts[] = "`{$col}` = ?";
            $params[] = $val;
        }
        $params[] = $ability_tid;
        $params[] = $checkLevel;
        $sql = "UPDATE officer_abilities SET " . implode(', ', $setParts) . " WHERE TID = ? AND Niveau = ?";
        $pdo->prepare($sql)->execute($params);
    } else {
        $cols = array_merge(['TID'], array_keys($clean));
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $params = array_merge([$ability_tid], array_values($clean));
        $sql = "INSERT INTO officer_abilities (`" . implode('`, `', $cols) . "`) VALUES ({$placeholders})";
        $pdo->prepare($sql)->execute($params);
    }
}

function admin_delete_officer_ability_level(PDO $pdo, $ability_tid, $level) {
    $stmt = $pdo->prepare("DELETE FROM officer_abilities WHERE TID = ? AND Niveau = ?");
    $stmt->execute([$ability_tid, $level]);
}