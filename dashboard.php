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
// Au chargement de la page, on restaure l'onglet où l'utilisateur se trouvait avant le refresh.
// Ordre de priorité : dernier onglet mémorisé (localStorage) > hash de l'URL > onglet par défaut.
window.onload = function() {
    let savedTab = null;
    try {
        savedTab = localStorage.getItem('activeTab');
    } catch (e) {
        console.warn("localStorage indisponible :", e);
    }
    const hashTab = window.location.hash.substring(1);
    let tabToShow = savedTab || hashTab || 'tab-resource';

    // Sécurité : si la valeur mémorisée (ancienne version du script, id renommé, etc.)
    // ne correspond à aucun onglet existant, on retombe sur l'onglet par défaut
    // au lieu de laisser la page vide.
    if (!document.getElementById(tabToShow)) {
        console.warn("Onglet '" + tabToShow + "' introuvable, retour à l'onglet par défaut.");
        tabToShow = 'tab-resource';
        try { localStorage.removeItem('activeTab'); } catch (e) {}
        if (window.location.hash) {
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }
    }

    showTab(tabToShow);
};
</script>

<div class="user-bar" style="background: #2c3e50; padding: 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #1abc9c;">
    <div class="welcome-msg" style="font-size: 1.2em; font-weight: bold; color: #fff;">
        Bienvenue, <span style="color: #1abc9c;"><?php echo htmlspecialchars($_SESSION['player_nom']); ?></span> !
    </div>
    <div class="logout-btn">
        <a href="deconnexion.php" style="background: #e74c3c; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-weight: bold;">
            Déconnexion
        </a>
    </div>
</div>
<div class="main-layout">
    <nav class="sidebar">
        <div class="sidebar-title">MENU</div>
        
        <div class="menu-group">
            <div class="menu-header dashboard-btn" onclick="showTab('tab-dashboard')">Dashboard</div>
        </div>

        <div class="menu-group">
            <div class="menu-header" onclick="toggleSubMenu(this)">Bâtiments ▼</div>
            <ul class="submenu" style="display: none;">
                <li><button class="nav-button" onclick="showTab('tab-resource')">Économie</button></li>
                <li><button class="nav-button" onclick="showTab('tab-defense')">Défense</button></li>
                <li><button class="nav-button" onclick="showTab('tab-army')">Renfort</button></li>
            </ul>
        </div>

        <div class="menu-group">
            <div class="menu-header" onclick="toggleSubMenu(this)">Armée ▼</div>
            <ul class="submenu" style="display: none;">
                <li><button class="nav-button" onclick="showTab('tab-units')">Troupes</button></li>
                <li><button class="nav-button" onclick="showTab('tab-heroes')">Héros</button></li>
                <li><button class="nav-button" onclick="showTab('tab-proto')">Proto-troupes</button></li>
                <li><button class="nav-button" onclick="showTab('tab-leaders')">Chef de bataillon</button></li>
            </ul>
        </div>

        <div class="menu-group">
            <div class="menu-header dashboard-btn" onclick="showTab('tab-tribes')">Tribus</div>
        </div>
        <div class="menu-group">
            <div class="menu-header dashboard-btn" onclick="showTab('tab-Archipel')">Archipel</div>
        </div>
        <div class="menu-group">
            <div class="menu-header dashboard-btn" onclick="showTab('tab-monument')">Monument mystique</div>
        </div>
        <div class="menu-group">
            <div class="menu-header dashboard-btn" onclick="showTab('tab-boompass')">Boom Pass</div>
        </div>
        <div class="menu-group">
            <div class="menu-header" onclick="toggleSubMenu(this)">Gravures ▼</div>
            <ul class="submenu" style="display: none;">
                <li><button class="nav-button" onclick="showTab('subtab-off')">Offensive</button></li>
                <li><button class="nav-button" onclick="showTab('subtab-def')">Defensive</button></li>
            </ul>
        </div>

    </nav>

    <div class="main-content">

        <?php
            // On pré-calcule les stats pour les passer à la sidebar
            $stats_res = getCategoryStats($buildings_display['Ressource'] ?? []);
            $stats_def = getCategoryStats($buildings_display['Defense'] ?? []);
            $stats_army = getCategoryStats($buildings_display['Army'] ?? []);
        ?>

        <div id="tab-resource" class="tab-content">

            <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 3;">
                    <?php renderBuildingsTable(['Ressource' => $buildings_display['Ressource'] ?? []]); ?>
                </div>
                <div style="flex: 1;">
                    <?php renderStatsSidebar('Bâtiments Économiques', $buildings_display['Ressource'] ?? [], $stats_res); ?>
                </div>
            </div>
        </div>

        <div id="tab-defense" class="tab-content">
            
        <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
            <div style="flex: 3;">
                <?php renderBuildingsTable(['Defense' => $buildings_display['Defense'] ?? []]); ?>
            </div>
            <div style="flex: 1;">
                <?php renderStatsSidebar('Défense', $buildings_display['Defense'] ?? [], $stats_def); ?>
            </div>
            </div>
        </div>
        <div id="tab-army" class="tab-content">
            <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 3;">
                    <?php renderBuildingsTable(['Army' => $buildings_display['Army'] ?? []]); ?>
                </div>
                <div style="flex: 1;">
                    <?php renderStatsSidebar('Armée', $buildings_display['Army'] ?? [], $stats_army); ?>
                </div>
            </div>
        </div>

        <div id="tab-units" class="tab-content">
            <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 3;">
                    <?php renderUnitsTable($troupes_list, $character_progress, $house_levels); ?>
                </div>
                <div style="flex: 1;">
                    <?php renderUnitsStatsSidebar('Troupes', $troupes_list); ?>
                </div>
            </div>
        </div>
        <div id="tab-heroes" class="tab-content"><?php renderUnitsTable($heros_list); ?></div>
        <div id="tab-proto" class="tab-content">
            <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 3;">
                    <?php renderUnitsTable($proto_list, $character_progress, $house_levels); ?>
                </div>
                <div style="flex: 1;">
                    <?php renderUnitsStatsSidebar('Proto-troupes', $proto_list); ?>
                </div>
            </div>
        </div>
        <div id="tab-leaders" class="tab-content">
            <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 3;">
                    <?php renderUnitsTable($officers_list); ?>
                </div>
                
                <div style="flex: 1;">
                    <?php renderLeadersStatsSidebar($pdo, $id_player, $officers_list); ?>
                </div>
            </div>
        </div>

        <div id="tab-tribes" class="tab-content">
            <h2>Régions et Bonus des Tribus</h2>
            </div>
        <div id="tab-archipelago" class="tab-content">
            <h2>Exploration de l'Archipel</h2>
        </div>
        <div id="tab-monument" class="tab-content" style="display:none;">
            <?php renderMysticMonument($monument_level, $cc_bonuses, $player_bonuses); ?>
        </div>
        <div id="tab-monument" class="tab-content">
            <h2>Améliorations du Monument Mystique</h2>
        </div>
        <div id="tab-boompass" class="tab-content">
            <h2>Récompenses du Boom Pass</h2>
        </div>

        <div id="subtab-off" class="tab-content"><?php renderEngravingsTable($engravings_offensive); ?></div>
        <div id="subtab-def" class="tab-content"><?php renderEngravingsTable($engravings_defensive); ?></div>
        

    </div> <?php include 'footer.php'; ?>