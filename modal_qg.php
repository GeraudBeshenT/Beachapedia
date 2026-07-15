<?php
global $selected_lang;

// 1. Récupérer le niveau du QG (via la variable globale $qg si dispo, sinon requête)
if (!isset($qg)) {
    $stmt_qg = $pdo->prepare("SELECT qg FROM joueurs WHERE id_player = ?");
    $stmt_qg->execute([$id_player]);
    $qg = (int)$stmt_qg->fetchColumn();
}

// 2. Récupérer l'XP du joueur pour calculer son niveau d'expérience global
$stmt_xp = $pdo->prepare("SELECT experience FROM joueurs WHERE id_player = ?");
$stmt_xp->execute([$id_player]);
$xp_actuelle = (int)$stmt_xp->fetchColumn();

$stmt_lvl = $pdo->prepare("SELECT Level FROM experience_levels WHERE xp_total <= ? ORDER BY Level DESC LIMIT 1");
$stmt_lvl->execute([$xp_actuelle]);
$niveau_joueur = (int)($stmt_lvl->fetchColumn() ?? 1);

// 3. Nom du QG traduit dans la langue sélectionnée (même TID que celui utilisé pour les coûts plus bas)
$stmt_nom_qg = $pdo->prepare("SELECT $selected_lang FROM texts WHERE TID = 'TID_BUILDING_PALACE'");
$stmt_nom_qg->execute();
$nom_qg = $stmt_nom_qg->fetchColumn() ?: 'Quartier Général';

// 4. Infos amélioration QG (coûts + temps du niveau suivant)
$next_lvl = $qg + 1;
$stmt_next = $pdo->prepare("
    SELECT BuildCostWood, BuildCostStone, BuildCostIron,
           BuildTimeD, BuildTimeH, BuildTimeM, BuildTimeS
    FROM buildings
    WHERE TID = 'TID_BUILDING_PALACE' AND Niveau = ?
");
$stmt_next->execute([$next_lvl]);
$next_info = $stmt_next->fetch(PDO::FETCH_ASSOC);

// Le niveau joueur requis pour DÉCLENCHER cette amélioration se lit sur la ligne
// du QG ACTUEL (pas celle du niveau suivant) : townhall_levels.TownHallLevel = $qg
// donne le palier à atteindre pour pouvoir passer de $qg à $next_lvl.
if ($next_info) {
    $stmt_xp_req = $pdo->prepare("SELECT XP FROM townhall_levels WHERE TownHallLevel = ?");
    $stmt_xp_req->execute([$qg]);
    $next_info['XP'] = (int)($stmt_xp_req->fetchColumn() ?? 0);
}

// 5. Niveau max du Palais
$stmt_max = $pdo->prepare("SELECT MAX(Niveau) FROM buildings WHERE TID = 'TID_BUILDING_PALACE'");
$stmt_max->execute();
$niveau_max_qg = (int)$stmt_max->fetchColumn();

// 6. Déblocages obtenus en passant au niveau suivant (bâtiments + troupes/officiers)
//    Même logique que update_qg.php : townhall_levels.TID_xxx = nombre CUMULÉ d'instances
//    débloquées à ce niveau. On compare la ligne du QG actuel à celle du QG suivant pour
//    savoir quels bâtiments sont "Nouveau" (0 -> N) ou juste augmentés en quantité (+N).
$unlocks_buildings = [];
$unlocks_troops = [];

if ($next_info) {
    $stmt_th_cur = $pdo->prepare("SELECT * FROM townhall_levels WHERE TownHallLevel = ?");
    $stmt_th_cur->execute([$qg]);
    $qg_row_cur = $stmt_th_cur->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt_th_next = $pdo->prepare("SELECT * FROM townhall_levels WHERE TownHallLevel = ?");
    $stmt_th_next->execute([$next_lvl]);
    $qg_row_next = $stmt_th_next->fetch(PDO::FETCH_ASSOC) ?: [];

    $colonnes_ignorees = [
        'TownHallLevel', 'XP', 'RequiredBuilding',
        'RequiredBuildingLevel', 'RequiredTroopLevel', 'MaterialSlots'
    ];

    // Nom + visuel (niveau 1) de chaque bâtiment, pour affichage des nouveaux déblocages
    $stmt_bld_info = $pdo->query("
        SELECT bi.TID, t.$selected_lang AS nom, b1.ExportName
        FROM buildingid bi
        JOIN texts t ON t.TID = bi.TID
        LEFT JOIN buildings b1 ON b1.TID = bi.TID AND b1.Niveau = 1
    ");
    $buildings_info = [];
    foreach ($stmt_bld_info->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $buildings_info[$row['TID']] = $row;
    }

    foreach ($qg_row_next as $colonne => $valeur_next) {
        if (in_array($colonne, $colonnes_ignorees, true)) continue;
        if (!isset($buildings_info[$colonne])) continue; // ex: pièges (Arsenal, pas QG)

        $valeur_cur  = (int)($qg_row_cur[$colonne] ?? 0);
        $valeur_next = (int)$valeur_next;
        $diff = $valeur_next - $valeur_cur;

        if ($diff > 0) {
            $info = $buildings_info[$colonne];
            $unlocks_buildings[] = [
                'nom'     => $info['nom'] ?? $colonne,
                'img_src' => 'images/' . ($info['ExportName'] ?: 'default-building') . '.webp',
                'label'   => ($valeur_cur === 0) ? 'Nouveau' : ('x' . $diff),
            ];
        }
    }

    // Troupes / officiers débloqués pile à ce niveau de QG
    $stmt_troops = $pdo->prepare("
        SELECT ci.Class, ci.IconExportName, t.$selected_lang AS nom
        FROM characterid ci
        INNER JOIN texts t ON t.TID = ci.TID
        WHERE ci.HQUnlock = ?
    ");
    $stmt_troops->execute([$next_lvl]);
    foreach ($stmt_troops->fetchAll(PDO::FETCH_ASSOC) as $tr) {
        $unlocks_troops[] = [
            'nom'     => $tr['nom'] ?? '???',
            'img_src' => 'images/characters/' . ($tr['Class'] ?? 'default') . '/' . ($tr['IconExportName'] ?? 'default') . '.png',
            'label'   => 'Nouveau',
        ];
    }
}
?>

<div class="qg-panel">
    <div class="qg-panel-header">
        <h2><?php echo htmlspecialchars($nom_qg); ?> — Niveau <?php echo $qg; ?></h2>
        <span class="qg-panel-header-sub">Niv. Joueur : <?php echo $niveau_joueur; ?></span>
    </div>

    <?php if ($next_info): ?>
        <div class="qg-panel-body">

            <div class="qg-panel-visual">
                <img src="images/<?php echo htmlspecialchars($qg_image_name); ?>.png"
                     alt="<?php echo htmlspecialchars($nom_qg); ?> niveau <?php echo $qg; ?>"
                     onerror="this.src='images/default-building.png'">
            </div>

            <div class="qg-panel-unlocks">
                <h4 class="qg-panel-subtitle">Débloque au niveau <?php echo $next_lvl; ?></h4>

                <?php if (empty($unlocks_buildings) && empty($unlocks_troops)): ?>
                    <p class="qg-unlocks-empty">Aucun nouveau déblocage à ce niveau.</p>
                <?php else: ?>

                    <div class="qg-unlocks-section">
                        <h5 class="qg-unlocks-section-title">Bâtiments</h5>
                        <?php if (!empty($unlocks_buildings)): ?>
                            <div class="qg-unlocks-grid">
                                <?php foreach ($unlocks_buildings as $u): ?>
                                <div class="qg-unlock-item">
                                    <div class="qg-unlock-icon-wrap">
                                        <img src="<?php echo htmlspecialchars($u['img_src']); ?>"
                                             alt="<?php echo htmlspecialchars($u['nom']); ?>"
                                             onerror="this.src='images/default-building.png'">
                                        <span class="qg-unlock-badge"><?php echo htmlspecialchars($u['label']); ?></span>
                                    </div>
                                    <span class="qg-unlock-name"><?php echo htmlspecialchars($u['nom']); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="qg-unlocks-empty">Aucun.</p>
                        <?php endif; ?>
                    </div>

                    <div class="qg-unlocks-section">
                        <h5 class="qg-unlocks-section-title">Armée</h5>
                        <?php if (!empty($unlocks_troops)): ?>
                            <div class="qg-unlocks-grid">
                                <?php foreach ($unlocks_troops as $u): ?>
                                <div class="qg-unlock-item">
                                    <div class="qg-unlock-icon-wrap">
                                        <img src="<?php echo htmlspecialchars($u['img_src']); ?>"
                                             alt="<?php echo htmlspecialchars($u['nom']); ?>"
                                             onerror="this.src='images/default-building.png'">
                                        <span class="qg-unlock-badge"><?php echo htmlspecialchars($u['label']); ?></span>
                                    </div>
                                    <span class="qg-unlock-name"><?php echo htmlspecialchars($u['nom']); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="qg-unlocks-empty">Aucune.</p>
                        <?php endif; ?>
                    </div>

                <?php endif; ?>
            </div>

            <div class="qg-panel-side">
                <div class="qg-cost-list">
                    <div class="qg-cost-row">
                        <img src="images/icons/Wood.png" alt="Bois">
                        <span><?php echo number_format($next_info['BuildCostWood'], 0, ',', ' '); ?></span>
                    </div>
                    <div class="qg-cost-row">
                        <img src="images/icons/Stone.png" alt="Pierre">
                        <span><?php echo number_format($next_info['BuildCostStone'], 0, ',', ' '); ?></span>
                    </div>
                    <div class="qg-cost-row">
                        <img src="images/icons/Iron.png" alt="Fer">
                        <span><?php echo number_format($next_info['BuildCostIron'], 0, ',', ' '); ?></span>
                    </div>
                </div>

                <div class="qg-info-row">
                    <img src="images/icons/Time Icon.png" alt="Temps" onerror="this.style.display='none'">
                    <span><?php echo trim($next_info['BuildTimeD'] . 'j ' . $next_info['BuildTimeH'] . 'h ' . $next_info['BuildTimeM'] . 'm'); ?></span>
                </div>
                <div class="qg-info-row">
                    ⭐ <span><?php echo $next_info['XP']; ?> XP requis</span>
                </div>

                <button id="btn-ameliorer-qg" class="qg-btn-upgrade" onclick="ameliorerQG()" data-qg="<?php echo $qg; ?>">
                    Améliorer
                    <span class="qg-btn-upgrade-sub">Passer au niveau <?php echo $next_lvl; ?></span>
                </button>
            </div>

        </div>
    <?php else: ?>
        <div class="qg-panel-body qg-panel-maxed">
            <div class="qg-panel-visual">
                <img src="images/<?php echo htmlspecialchars($qg_image_name); ?>.png"
                     alt="<?php echo htmlspecialchars($nom_qg); ?> niveau <?php echo $qg; ?>"
                     onerror="this.src='images/default-building.png'">
            </div>
            <p class="qg-maxed-text">Quartier général au niveau maximum (<?php echo $niveau_max_qg; ?>)</p>
        </div>
    <?php endif; ?>
</div>