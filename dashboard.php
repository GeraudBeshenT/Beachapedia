<?php
// 1. Démarrage de la session en tout premier
session_start();

// 2. Vérification de la sécurité
if (!isset($_SESSION['player_id'])) {
    header("Location: index.php");
    exit();
}

// 3. Ensuite, on inclut les requêtes qui dépendent de la session
require_once 'queries.php';
require_once 'functions.php';

// On récupère les coûts de tous les bâtiments en base de données
$stmt_prix = $pdo->query("SELECT * FROM buildings"); 
$tous_les_prix = $stmt_prix->fetchAll(PDO::FETCH_ASSOC);
?>

<script>
    // On injecte les données proprement
    window.PRIX_BATIMENTS = <?php echo json_encode($tous_les_prix); ?>;
    console.log("Données chargées :", window.PRIX_BATIMENTS);
</script>

<?php

include 'header.php';
include 'modal_qg.php';
include 'connexion.php';
?>

<script>
// Au chargement de la page, on restaure l'onglet où l'utilisateur se trouvait.
// L'URL (dashboard.php#Categorie-SousCategorie) est désormais la source de vérité :
// c'est ce qui rend chaque onglet partageable / navigable au bouton précédent-suivant.
// localStorage ne sert plus que de filet de sécurité (ex: lien direct vers dashboard.php sans hash).
window.onload = function() {
    const hashTab = window.location.hash.substring(1);
    let savedTab = null;
    try {
        savedTab = localStorage.getItem('activeTab');
    } catch (e) {
        console.warn("localStorage indisponible :", e);
    }
    let tabToShow = hashTab || savedTab || 'Dashboard';

    // Sécurité : si la valeur (hash ou localStorage, ancienne version, id renommé, etc.)
    // ne correspond à aucun onglet existant, on retombe sur l'onglet par défaut
    // au lieu de laisser la page vide.
    if (!document.getElementById(tabToShow)) {
        console.warn("Onglet '" + tabToShow + "' introuvable, retour à l'onglet par défaut.");
        tabToShow = 'Dashboard';
        try { localStorage.removeItem('activeTab'); } catch (e) {}
        if (window.location.hash) {
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }
    }

    showTab(tabToShow);
};
</script>

<div class="main-layout">
    <nav class="sidebar">
        <div class="sidebar-scroll">
            <div class="sidebar-title">MENU</div>

            <div class="menu-group">
                <div class="menu-header dashboard-btn" onclick="showTab('Dashboard')">Dashboard</div>
            </div>

            <div class="menu-group">
                <div class="menu-header" onclick="openCategoryTab(this, 'Building-Overview')">Bâtiments ▼</div>
                <ul class="submenu" style="display: none;">
                    <li><button class="nav-button" onclick="showTab('Building-Ressource')">Économie</button></li>
                    <li><button class="nav-button" onclick="showTab('Building-Defense')">Défense</button></li>
                    <li><button class="nav-button" onclick="showTab('Building-Army')">Renfort</button></li>
                </ul>
            </div>

            <div class="menu-group">
                <div class="menu-header" onclick="openCategoryTab(this, 'Army-Overview')">Armée ▼</div>
                <ul class="submenu" style="display: none;">
                    <li><button class="nav-button" onclick="showTab('Character-Troop')">Troupes</button></li>
                    <li><button class="nav-button" onclick="showTab('Character-Hero')">Héros</button></li>
                    <li><button class="nav-button" onclick="showTab('Character-Proto')">Proto-troupes</button></li>
                    <li><button class="nav-button" onclick="showTab('Character-Leader')">Chef de bataillon</button></li>
                </ul>
            </div>

            <div class="menu-group">
                <div class="menu-header dashboard-btn" onclick="showTab('Tribes')">Tribus</div>
            </div>
            <div class="menu-group">
                <div class="menu-header dashboard-btn" onclick="showTab('Archipel')">Archipel</div>
            </div>
            <div class="menu-group">
                <div class="menu-header dashboard-btn" onclick="showTab('Monument')">Monument mystique</div>
            </div>
            <div class="menu-group">
                <div class="menu-header dashboard-btn" onclick="showTab('BoomPass')">Boom Pass</div>
            </div>
            <div class="menu-group">
                <div class="menu-header" onclick="openCategoryTab(this, 'Engraving-Overview')">Gravures ▼</div>
                <ul class="submenu" style="display: none;">
                    <li><button class="nav-button" onclick="showTab('Engraving-Offensive')">Offensive</button></li>
                    <li><button class="nav-button" onclick="showTab('Engraving-Defensive')">Defensive</button></li>
                </ul>
            </div>
        </div>

        <div class="sidebar-profile">
            <div class="profile-btn" onclick="toggleProfileMenu(event)">
                <div class="profile-avatar">
                    <?php echo htmlspecialchars(strtoupper(substr($_SESSION['player_nom'], 0, 1))); ?>
                    <img src="images/icons/gacha_info_icon.png" class="badge-icon" alt="" onerror="this.style.display='none'">
                </div>
                <span class="profile-name"><?php echo htmlspecialchars($_SESSION['player_nom']); ?></span>

                <div class="profile-dropdown" id="profileDropdown">
                    <a href="deconnexion.php">🚪 Déconnexion</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="main-content">

        <?php
            // On pré-calcule les stats pour les passer à la sidebar
            $stats_res = getCategoryStats($buildings_display['Ressource'] ?? []);
            $stats_def = getCategoryStats($buildings_display['Defense'] ?? []);
            $stats_army = getCategoryStats($buildings_display['Army'] ?? []);

            // --- Stats globales pour le Tableau de Bord principal ---
            $all_buildings_flat = array_merge(
                $buildings_display['Ressource'] ?? [],
                $buildings_display['Defense'] ?? [],
                $buildings_display['Army'] ?? []
            );
            $stats_buildings_global = getCategoryStats($all_buildings_flat);

            $stats_troupes_global = ['current' => 0, 'max' => 0, 'percent' => 0];
            if (!empty($troupes_list)) {
                $t_current = 0;
                $t_max = 0;
                foreach ($troupes_list as $t) {
                    $t_current += (int)($t['niveau_joueur'] ?? 0);
                    $t_max     += (int)($t['niveau_autorise'] ?? 0);
                }
                $stats_troupes_global = [
                    'current' => $t_current,
                    'max'     => $t_max,
                    'percent' => ($t_max > 0) ? round(($t_current / $t_max) * 100, 1) : 0
                ];
            }

            $chefs_debloques = 0;
            foreach ($officers_list as $o) {
                if ((int)($o['Debloque'] ?? 0) === 1) $chefs_debloques++;
            }
            $chefs_total = count($officers_list);
        ?>

        <div id="Dashboard" class="tab-content">
            <?php renderMainDashboard($stats_buildings_global, $stats_troupes_global, $chefs_debloques, $chefs_total); ?>
        </div>

        <div id="Building-Overview" class="tab-content">
            <?php renderCategoryNav('Bâtiments', [
                ['label' => 'Économie', 'sub' => round($stats_res['percent']) . '% complété', 'tab' => 'Building-Ressource', 'icon' => '🏦'],
                ['label' => 'Défense',  'sub' => round($stats_def['percent']) . '% complété',  'tab' => 'Building-Defense', 'icon' => '🛡️'],
                ['label' => 'Renfort',  'sub' => round($stats_army['percent']) . '% complété', 'tab' => 'Building-Army',    'icon' => '🏰'],
            ]); ?>
        </div>

        <div id="Army-Overview" class="tab-content">
            <?php renderCategoryNav('Armée', [
                ['label' => 'Troupes',            'tab' => 'Character-Troop',  'icon' => '<img src="images/icons/Fusilier_badge.webp" style="width: 50px;"/>'],
                ['label' => 'Héros',               'tab' => 'Character-Hero',   'icon' => '<img src="images/icons/buildingbutton_heroes.png" style="width: 50px;"/>'],
                ['label' => 'Proto-troupes',       'tab' => 'Character-Proto',  'icon' => '<img src="images/icons/buildingbutton_prototroops.png" style="width: 50px;"/>'],
                ['label' => 'Chef de bataillon',   'tab' => 'Character-Leader', 'icon' => '<img src="images/icons/OfficerIcon.webp" style="width: 50px;"/>'],
            ]); ?>
        </div>

        <div id="Engraving-Overview" class="tab-content">
            <?php renderCategoryNav('Gravures', [
                ['label' => 'Offensive', 'tab' => 'Engraving-Offensive', 'icon' => '<img src="images/icons/Icon_engravings_offence.webp" style="width: 50px;"/>'],
                ['label' => 'Defensive', 'tab' => 'Engraving-Defensive', 'icon' => '<img src="images/icons/Icon_engravings_defence.webp" style="width: 50px;"/>'],
            ]); ?>
        </div>

        <div id="Building-Ressource" class="tab-content">

            <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 3;">
                    <?php renderBuildingsTable(['Ressource' => $buildings_display['Ressource'] ?? []]); ?>
                </div>
                <div style="flex: 1;">
                    <?php renderStatsSidebar('Bâtiments Économiques', $buildings_display['Ressource'] ?? [], $stats_res); ?>
                </div>
            </div>
        </div>

        <div id="Building-Defense" class="tab-content">
            
        <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
            <div style="flex: 3;">
                <?php renderBuildingsTable(['Defense' => $buildings_display['Defense'] ?? []]); ?>
            </div>
            <div style="flex: 1;">
                <?php renderStatsSidebar('Défense', $buildings_display['Defense'] ?? [], $stats_def); ?>
            </div>
            </div>
        </div>
        <div id="Building-Army" class="tab-content">
            <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 3;">
                    <?php renderBuildingsTable(['Army' => $buildings_display['Army'] ?? []]); ?>
                </div>
                <div style="flex: 1;">
                    <?php renderStatsSidebar('Armée', $buildings_display['Army'] ?? [], $stats_army); ?>
                </div>
            </div>
        </div>

        <div id="Character-Troop" class="tab-content">
            <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 3;">
                    <?php renderUnitsTable($troupes_list, $character_progress, $house_levels); ?>
                </div>
                <div style="flex: 1;">
                    <?php renderUnitsStatsSidebar('Troupes', $troupes_list); ?>
                </div>
            </div>
        </div>
        <div id="Character-Hero" class="tab-content"><?php renderUnitsTable($heros_list); ?></div>
        <div id="Character-Proto" class="tab-content">
            <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 3;">
                    <?php renderUnitsTable($proto_list, $character_progress, $house_levels); ?>
                </div>
                <div style="flex: 1;">
                    <?php renderUnitsStatsSidebar('Proto-troupes', $proto_list); ?>
                </div>
            </div>
        </div>
        <div id="Character-Leader" class="tab-content">
            <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 3;">
                    <?php renderUnitsTable($officers_list); ?>
                </div>
                
                <div style="flex: 1;">
                    <?php renderLeadersStatsSidebar($pdo, $id_player, $officers_list); ?>
                </div>
            </div>
        </div>

        <div id="Tribes" class="tab-content">
            <h2>Régions et Bonus des Tribus</h2>
            </div>
        <div id="Archipel" class="tab-content">
            <h2>Exploration de l'Archipel</h2>
        </div>
        <div id="Monument" class="tab-content">
            <?php renderMysticMonument($monument_level, $cc_bonuses, $player_bonuses); ?>
        </div>
        <div id="BoomPass" class="tab-content">
            <h2>Récompenses du Boom Pass</h2>
        </div>

        <div id="Engraving-Offensive" class="tab-content"><?php renderEngravingsTable($engravings_offensive); ?></div>
        <div id="Engraving-Defensive" class="tab-content"><?php renderEngravingsTable($engravings_defensive); ?></div>
        

    </div> <?php include 'footer.php'; ?>