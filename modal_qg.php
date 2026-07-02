<?php
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

// 3. Infos amélioration QG
$next_lvl = $qg + 1;
$stmt_next = $pdo->prepare("
    SELECT b.BuildCostWood, b.BuildCostStone, b.BuildCostIron, 
           b.BuildTimeD, b.BuildTimeH, b.BuildTimeM, b.BuildTimeS, t.XP 
    FROM buildings b
    INNER JOIN townhall_levels t ON b.Niveau = t.TownHallLevel
    WHERE b.TID = 'TID_BUILDING_PALACE' AND b.Niveau = ?
");
$stmt_next->execute([$next_lvl]);
$next_info = $stmt_next->fetch(PDO::FETCH_ASSOC);

// 4. Niveau max du Palais
$stmt_max = $pdo->prepare("SELECT MAX(Niveau) FROM buildings WHERE TID = 'TID_BUILDING_PALACE'");
$stmt_max->execute();
$niveau_max_qg = (int)$stmt_max->fetchColumn();
?>

<div class="qg-card">
    <div class="qg-header">
        <img src="images/<?php echo $qg_image_name; ?>.png" width="80">
        <div>
            <h3 style="margin:0">
                Palais Niveau <?php echo $qg; ?> 
                <span style="font-size: 0.8em; color: #3498db;">(Niv. Joueur: <?php echo $niveau_joueur; ?>)</span>
            </h3>
            <p style="margin:5px 0 0 0; font-size: 0.9em; color: #7f8c8d;">XP Total : <?php echo $xp_actuelle; ?></p>
        </div>
    </div>

    <?php if ($next_info): ?>
        <div class="qg-cost-grid">
            <div class="cost-item"><img src="images/icons/Wood.png" width="50"> <p class="qg-cost"><?php echo $next_info['BuildCostWood']; ?></p></div>
            <div class="cost-item"><img src="images/icons/Stone.png" width="50"> <p class="qg-cost"><?php echo $next_info['BuildCostStone']; ?></p></div>
            <div class="cost-item"><img src="images/icons/Iron.png" width="50"> <p class="qg-cost"><?php echo $next_info['BuildCostIron']; ?></p></div>
        </div>
        
        <div style="font-size: 0.9em; margin-bottom: 15px;">
            <p>⏱ <strong>Temps :</strong> <?php echo $next_info['BuildTimeD'].'j '.$next_info['BuildTimeH'].'h '.$next_info['BuildTimeM'].'m'; ?></p>
            <p>⭐ <strong>XP requis :</strong> <?php echo $next_info['XP']; ?></p>
        </div>

        <button id="btn-ameliorer-qg" onclick="ameliorerQG()" data-qg="<?php echo $qg; ?>">Améliorer</button>
            Passer au niveau <?php echo $next_lvl; ?>
        </button>
    <?php else: ?>
        <p style="text-align:center; color: #27ae60;">Palais au niveau maximum (<?php echo $niveau_max_qg; ?>)</p>
    <?php endif; ?>
</div>