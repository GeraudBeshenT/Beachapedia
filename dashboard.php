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

<?php
// Liste des langues disponibles (basée sur les 20 locales du texts.csv de Boom Beach).
// Codes/drapeaux à ajuster une fois le vrai fichier de traduction branché.
$bb_languages = [
    ['code' => 'fr',    'flag' => '🇫🇷', 'label' => 'Français'],
    ['code' => 'en',    'flag' => '🇬🇧', 'label' => 'English'],
    ['code' => 'de',    'flag' => '🇩🇪', 'label' => 'Deutsch'],
    ['code' => 'es',    'flag' => '🇪🇸', 'label' => 'Español'],
    ['code' => 'es-mx', 'flag' => '🇲🇽', 'label' => 'Español (Latam)'],
    ['code' => 'it',    'flag' => '🇮🇹', 'label' => 'Italiano'],
    ['code' => 'pt-br', 'flag' => '🇧🇷', 'label' => 'Português (Brasil)'],
    ['code' => 'nl',    'flag' => '🇳🇱', 'label' => 'Nederlands'],
    ['code' => 'pl',    'flag' => '🇵🇱', 'label' => 'Polski'],
    ['code' => 'ru',    'flag' => '🇷🇺', 'label' => 'Русский'],
    ['code' => 'tr',    'flag' => '🇹🇷', 'label' => 'Türkçe'],
    ['code' => 'ar',    'flag' => '🇸🇦', 'label' => 'العربية'],
    ['code' => 'zh-cn', 'flag' => '🇨🇳', 'label' => '简体中文'],
    ['code' => 'zh-tw', 'flag' => '🇹🇼', 'label' => '繁體中文'],
    ['code' => 'ja',    'flag' => '🇯🇵', 'label' => '日本語'],
    ['code' => 'ko',    'flag' => '🇰🇷', 'label' => '한국어'],
    ['code' => 'th',    'flag' => '🇹🇭', 'label' => 'ไทย'],
    ['code' => 'vi',    'flag' => '🇻🇳', 'label' => 'Tiếng Việt'],
    ['code' => 'no',    'flag' => '🇳🇴', 'label' => 'Norsk'],
    ['code' => 'fi',    'flag' => '🇫🇮', 'label' => 'Suomi'],
];
?>

<div class="main-layout">
    <nav class="sidebar">
        <div class="sidebar-inner">

            <div class="sidebar-header">
                <a href="dashboard.php" class="sidebar-logo">
                    <img src="images/Beachapedia.webp" alt="Beachapedia" class="sidebar-logo-img" onerror="this.style.display='none'">
                    <span class="sidebar-logo-text">Beachapedia</span>
                </a>
                <button type="button" class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()" aria-label="Réduire / agrandir le menu">
                    <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12.5 4l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>

            <div class="sidebar-scroll">
                <div class="sidebar-section">

                    <div class="menu-group">
                        <div class="menu-header dashboard-btn" onclick="showTab('Dashboard')" data-tooltip="Dashboard">
                            <span class="menu-icon"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="2.5" y="2.5" width="6" height="6" rx="1.2" stroke="currentColor" stroke-width="1.6"/><rect x="11.5" y="2.5" width="6" height="6" rx="1.2" stroke="currentColor" stroke-width="1.6"/><rect x="2.5" y="11.5" width="6" height="6" rx="1.2" stroke="currentColor" stroke-width="1.6"/><rect x="11.5" y="11.5" width="6" height="6" rx="1.2" stroke="currentColor" stroke-width="1.6"/></svg></span>
                            <span class="menu-label">Dashboard</span>
                        </div>
                    </div>

                    <div class="menu-group">
                        <div class="menu-header" onclick="openCategoryTab(this, 'Building-Overview')" data-tooltip="Bâtiments">
                            <span class="menu-icon"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 17.5V6l7-3.5 7 3.5v11.5" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M3 17.5h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><rect x="8" y="10.5" width="4" height="7" stroke="currentColor" stroke-width="1.6"/><path d="M6.5 8h.01M13.5 8h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
                            <span class="menu-label">Bâtiments</span>
                            <span class="menu-caret">▾</span>
                        </div>
                        <ul class="submenu" style="display: none;">
                            <li><button class="nav-button" onclick="showTab('Building-Ressource')">Économie</button></li>
                            <li><button class="nav-button" onclick="showTab('Building-Defense')">Défense</button></li>
                            <li><button class="nav-button" onclick="showTab('Building-Army')">Renfort</button></li>
                        </ul>
                    </div>

                    <div class="menu-group">
                        <div class="menu-header" onclick="openCategoryTab(this, 'Army-Overview')" data-tooltip="Armée">
                            <span class="menu-icon"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 2.5l6 2.7v4.3c0 4-2.6 6.9-6 8-3.4-1.1-6-4-6-8V5.2L10 2.5z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M10 6v6.5M7.3 9.2h5.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
                            <span class="menu-label">Armée</span>
                            <span class="menu-caret">▾</span>
                        </div>
                        <ul class="submenu" style="display: none;">
                            <li><button class="nav-button" onclick="showTab('Character-Troop')">Troupes</button></li>
                            <li><button class="nav-button" onclick="showTab('Character-Hero')">Héros</button></li>
                            <li><button class="nav-button" onclick="showTab('Character-Proto')">Proto-troupes</button></li>
                            <li><button class="nav-button" onclick="showTab('Character-Leader')">Chef de bataillon</button></li>
                        </ul>
                    </div>

                    <div class="menu-group">
                        <div class="menu-header dashboard-btn" onclick="showTab('Tribes')" data-tooltip="Tribus">
                            <span class="menu-icon"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="7" cy="6.5" r="2.3" stroke="currentColor" stroke-width="1.6"/><circle cx="14" cy="7.5" r="1.9" stroke="currentColor" stroke-width="1.6"/><path d="M2.5 16.2c.5-2.8 2.3-4.3 4.5-4.3s4 1.5 4.5 4.3M11.8 12.3c1.9.1 3.4 1.5 3.8 3.9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
                            <span class="menu-label">Tribus</span>
                        </div>
                    </div>

                    <div class="menu-group">
                        <div class="menu-header dashboard-btn" onclick="showTab('Archipel')" data-tooltip="Archipel">
                            <span class="menu-icon"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.5 13c1.5-2 3-2 4.5-.8 1.7 1.3 3.3 1.3 5-.2 1.3-1.2 3-1.2 5.5.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M9 3l1.6 3.4L14 8l-3.4 1.6L9 13l-1.6-3.4L4 8l3.4-1.6L9 3z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></span>
                            <span class="menu-label">Archipel</span>
                        </div>
                    </div>

                    <div class="menu-group">
                        <div class="menu-header dashboard-btn" onclick="showTab('Monument')" data-tooltip="Monument mystique">
                            <span class="menu-icon"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8.5 2.5h3l1.5 10h-6l1.5-10z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M6 17.5h8M6.8 15h6.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
                            <span class="menu-label">Monument mystique</span>
                        </div>
                    </div>

                    <div class="menu-group">
                        <div class="menu-header dashboard-btn" onclick="showTab('BoomPass')" data-tooltip="Boom Pass">
                            <span class="menu-icon"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.5 8.3a1.7 1.7 0 000 3.4v3.3c0 .7.6 1.2 1.2 1.2h12.6c.7 0 1.2-.5 1.2-1.2v-3.3a1.7 1.7 0 010-3.4V5c0-.7-.5-1.2-1.2-1.2H3.7c-.6 0-1.2.5-1.2 1.2v3.3z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M9 4v12" stroke="currentColor" stroke-width="1.5" stroke-dasharray="1.6 2" stroke-linecap="round"/></svg></span>
                            <span class="menu-label">Boom Pass</span>
                        </div>
                    </div>

                    <div class="menu-group">
                        <div class="menu-header" onclick="openCategoryTab(this, 'Engraving-Overview')" data-tooltip="Gravures">
                            <span class="menu-icon"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 3.2c-4.8.3-9 3.6-10.8 8.1L3.2 16l4.7-1.5c4.5-1.8 7.8-6 8.1-10.8l-.5-.5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M7 13l6-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                            <span class="menu-label">Gravures</span>
                            <span class="menu-caret">▾</span>
                        </div>
                        <ul class="submenu" style="display: none;">
                            <li><button class="nav-button" onclick="showTab('Engraving-Offensive')">Offensive</button></li>
                            <li><button class="nav-button" onclick="showTab('Engraving-Defensive')">Defensive</button></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="sidebar-footer">
                <div class="lang-select" id="langSelect">
                    <button type="button" class="lang-btn" onclick="toggleLangMenu(event)" data-tooltip="Langue">
                        <span class="lang-flag" id="currentLangFlag">🇫🇷</span>
                        <span class="lang-label" id="currentLangLabel">Français</span>
                        <span class="lang-caret">▾</span>
                    </button>
                    <div class="lang-dropdown" id="langDropdown">
                        <?php foreach ($bb_languages as $lg): ?>
                        <button type="button" class="lang-option<?php echo $lg['code'] === 'fr' ? ' selected' : ''; ?>" data-lang="<?php echo $lg['code']; ?>" data-flag="<?php echo $lg['flag']; ?>" data-label="<?php echo htmlspecialchars($lg['label']); ?>" onclick="selectLang(this)">
                            <span class="lang-flag"><?php echo $lg['flag']; ?></span>
                            <span><?php echo htmlspecialchars($lg['label']); ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="sidebar-profile">
                    <div class="profile-btn" onclick="toggleProfileMenu(event)" data-tooltip="<?php echo htmlspecialchars($_SESSION['player_nom']); ?>">
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