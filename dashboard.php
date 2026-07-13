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
                    <img src="images/icons/gacha_info_icon.png" style="width: 25px;"/>
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
                            <li><button class="nav-button" onclick="showTab('Building-Ressource')">Économiques</button></li>
                            <li><button class="nav-button" onclick="showTab('Building-Defense')">Défensifs</button></li>
                            <li><button class="nav-button" onclick="showTab('Building-Army')">Support</button></li>
                            <li><button class="nav-button" onclick="showTab('Building-Trap')">Pièges</button></li>
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
                            <li><button class="nav-button" onclick="showTab('Character-Spell')">Capacité de canonnière</button></li>
                        </ul>
                    </div>

                    <div class="menu-group">
                        <?php if ($tab_tribus_unlocked): ?>
                        <div class="menu-header dashboard-btn" onclick="showTab('Tribes')" data-tooltip="Tribus">
                        <?php else: ?>
                        <div class="menu-header dashboard-btn locked" data-tooltip="Débloqué avec le Radar niveau 18 (actuel : <?php echo (int)$radar_level; ?>)">
                        <?php endif; ?>
                            <span class="menu-icon"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="7" cy="6.5" r="2.3" stroke="currentColor" stroke-width="1.6"/><circle cx="14" cy="7.5" r="1.9" stroke="currentColor" stroke-width="1.6"/><path d="M2.5 16.2c.5-2.8 2.3-4.3 4.5-4.3s4 1.5 4.5 4.3M11.8 12.3c1.9.1 3.4 1.5 3.8 3.9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
                            <span class="menu-label">Tribus</span>
                            <?php if (!$tab_tribus_unlocked): ?><span class="menu-lock">🔒</span><?php endif; ?>
                        </div>
                    </div>

                    <div class="menu-group">
                        <div class="menu-header dashboard-btn" onclick="showTab('Archipel')" data-tooltip="Archipel">
                            <span class="menu-icon"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.5 13c1.5-2 3-2 4.5-.8 1.7 1.3 3.3 1.3 5-.2 1.3-1.2 3-1.2 5.5.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M9 3l1.6 3.4L14 8l-3.4 1.6L9 13l-1.6-3.4L4 8l3.4-1.6L9 3z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></span>
                            <span class="menu-label">Archipel</span>
                        </div>
                    </div>

                    <div class="menu-group">
                        <?php if ($tab_monument_unlocked): ?>
                        <div class="menu-header dashboard-btn" onclick="showTab('Monument')" data-tooltip="Monument mystique">
                        <?php else: ?>
                        <div class="menu-header dashboard-btn locked" data-tooltip="Débloqué une fois le Monument mystique construit">
                        <?php endif; ?>
                            <span class="menu-icon"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8.5 2.5h3l1.5 10h-6l1.5-10z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M6 17.5h8M6.8 15h6.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
                            <span class="menu-label">Monument mystique</span>
                            <?php if (!$tab_monument_unlocked): ?><span class="menu-lock">🔒</span><?php endif; ?>
                        </div>
                    </div>

                    <div class="menu-group">
                        <div class="menu-header dashboard-btn" onclick="showTab('BoomPass')" data-tooltip="Boom Pass">
                            <span class="menu-icon"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.5 8.3a1.7 1.7 0 000 3.4v3.3c0 .7.6 1.2 1.2 1.2h12.6c.7 0 1.2-.5 1.2-1.2v-3.3a1.7 1.7 0 010-3.4V5c0-.7-.5-1.2-1.2-1.2H3.7c-.6 0-1.2.5-1.2 1.2v3.3z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M9 4v12" stroke="currentColor" stroke-width="1.5" stroke-dasharray="1.6 2" stroke-linecap="round"/></svg></span>
                            <span class="menu-label">Boom Pass</span>
                        </div>
                    </div>

                    <div class="menu-group">
                        <?php if ($tab_gravures_unlocked): ?>
                        <div class="menu-header" onclick="openCategoryTab(this, 'Engraving-Overview')" data-tooltip="Gravures">
                        <?php else: ?>
                        <div class="menu-header locked" data-tooltip="Débloqué une fois le Graveur construit">
                        <?php endif; ?>
                            <span class="menu-icon"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 3.2c-4.8.3-9 3.6-10.8 8.1L3.2 16l4.7-1.5c4.5-1.8 7.8-6 8.1-10.8l-.5-.5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M7 13l6-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                            <span class="menu-label">Gravures</span>
                            <?php if ($tab_gravures_unlocked): ?>
                            <span class="menu-caret">▾</span>
                            <?php else: ?>
                            <span class="menu-lock">🔒</span>
                            <?php endif; ?>
                        </div>
                        <ul class="submenu" style="display: none;">
                            <li>
                                <?php if ($tab_gravures_off_unlocked): ?>
                                <button class="nav-button" onclick="showTab('Engraving-Offensive')">Offensive</button>
                                <?php else: ?>
                                <button class="nav-button locked" disabled title="Débloqué une fois le Graveur construit">Offensive 🔒</button>
                                <?php endif; ?>
                            </li>
                            <li>
                                <?php if ($tab_gravures_def_unlocked): ?>
                                <button class="nav-button" onclick="showTab('Engraving-Defensive')">Defensive</button>
                                <?php else: ?>
                                <button class="nav-button locked" disabled title="Débloqué avec le Graveur niveau 2 (actuel : <?php echo (int)$graveur_level; ?>)">Defensive 🔒</button>
                                <?php endif; ?>
                            </li>
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
                            <a href="#" onclick="showTab('MassUpgrade'); return false;">🚀 Mass Upgrade</a>
                            <a href="deconnexion.php">🚪 Déconnexion</a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </nav>

    <div class="main-content">

        <?php include 'modal_qg.php'; ?>

        <?php
            // On pré-calcule les stats pour les passer à la sidebar
            $stats_res = getCategoryStats($buildings_display['Ressource'] ?? []);
            $stats_def = getCategoryStats($buildings_display['Defense'] ?? []);
            $stats_army = getCategoryStats($buildings_display['Army'] ?? []);
            $stats_trap = getCategoryStats($buildings_display['Trap'] ?? []);

            // --- Stats globales pour le Tableau de Bord principal ---
            $all_buildings_flat = array_merge(
                $buildings_display['Ressource'] ?? [],
                $buildings_display['Defense'] ?? [],
                $buildings_display['Army'] ?? [],
                $buildings_display['Trap'] ?? []
            );
            $stats_buildings_global = getCategoryStats($all_buildings_flat);

            $chefs_debloques = 0;
            foreach ($officers_list as $o) {
                if ((int)($o['Debloque'] ?? 0) === 1) $chefs_debloques++;
            }
            $chefs_total = count($officers_list);

            // --- Armée : Troupes + Proto-troupes combinées, Héros, et capacités des Chefs (hors talents) ---
            $stats_troupes_proto  = getUnitsStats(array_merge($troupes_list ?? [], $proto_list ?? []));
            $stats_heros          = getUnitsStats($heros_list ?? []);
            $stats_officiers_capa = getOfficersCapaciteStats($pdo, $id_player, $officers_list ?? []);
            $stats_capacanon      = getUnitsStats($capacanon_list ?? []);

            // --- Tribus : maintenant suivies en base (voir queries.php, bloc 13) ---
            $tribus_current    = 0;
            $tribus_max        = 0;
            $tribus_debloquees = 0;
            foreach ($tribus_list as $trb) {
                $tribus_current += (int)($trb['niveau_actuel'] ?? 0);
                $tribus_max     += (int)($trb['niveau_max'] ?? 0);
                if (!empty($trb['debloque'])) $tribus_debloquees++;
            }
            $tribus_total_tribus = count($tribus_list);
            $stats_tribus = [
                'current'    => $tribus_current,
                'max'        => $tribus_max,
                'percent'    => ($tribus_max > 0) ? round(($tribus_current / $tribus_max) * 100, 1) : 0,
                'debloquees' => $tribus_debloquees,
                'total'      => $tribus_total_tribus,
            ];

            // --- Monument mystique (niveau du monument + nombre de bonus obtenus) ---
            $monument_max_level  = 700;
            $monument_bonus_total    = count($cc_bonuses);
            $monument_bonus_obtenus  = count(array_filter($player_bonuses, fn($nb) => $nb > 0));
            $stats_monument = [
                'level'         => $monument_level,
                'max_level'     => $monument_max_level,
                'percent'       => ($monument_max_level > 0) ? round(($monument_level / $monument_max_level) * 100, 1) : 0,
                'bonus_obtenus' => $monument_bonus_obtenus,
                'bonus_total'   => $monument_bonus_total,
            ];

            // --- Gravures : global (offensif + défensif), puis chaque catégorie séparément ---
            // Calcul basé sur les NIVEAUX et non sur le nombre de gravures débloquées
            $engravings_all_flat  = array_merge($engravings_offensive, $engravings_defensive);

            // Somme de tous les niveaux actuels et max
            $total_current_level = array_sum(array_column($engravings_all_flat, 'niveau_actuel'));
            $total_max_level = array_sum(array_column($engravings_all_flat, 'niveau_max'));

            $stats_gravures = [
                'current' => $total_current_level,
                'max'     => $total_max_level,
                'percent' => ($total_max_level > 0) ? round(($total_current_level / $total_max_level) * 100, 1) : 0,
            ];

            // Stats pour les gravures offensives
            $off_current = array_sum(array_column($engravings_offensive, 'niveau_actuel'));
            $off_max = array_sum(array_column($engravings_offensive, 'niveau_max'));
            $stats_gravures_off = [
                'current' => $off_current,
                'max'     => $off_max,
                'percent' => ($off_max > 0) ? round(($off_current / $off_max) * 100, 1) : 0,
            ];

            // Stats pour les gravures défensives
            $def_current = array_sum(array_column($engravings_defensive, 'niveau_actuel'));
            $def_max = array_sum(array_column($engravings_defensive, 'niveau_max'));
            $stats_gravures_def = [
                'current' => $def_current,
                'max'     => $def_max,
                'percent' => ($def_max > 0) ? round(($def_current / $def_max) * 100, 1) : 0,
            ];
        ?>

        <div id="Dashboard" class="tab-content">
            <?php renderMainDashboard(
                $stats_buildings_global, $stats_res, $stats_def, $stats_army, $stats_trap,
                $stats_troupes_proto, $stats_heros, $stats_officiers_capa, $chefs_debloques, $chefs_total,
                $stats_capacanon,
                $stats_gravures, $stats_gravures_off, $stats_gravures_def,
                $stats_tribus, $stats_monument
            ); ?>
        </div>

        <div id="Building-Overview" class="tab-content">
            <?php renderCategoryNav('Bâtiments', [
                ['label' => 'Bâtiments économiques', 'sub' => round($stats_res['percent']) . '% complété', 'tab' => 'Building-Ressource', 'icon' => '🏦'],
                ['label' => 'Bâtiments défensifs',  'sub' => round($stats_def['percent']) . '% complété',  'tab' => 'Building-Defense', 'icon' => '🛡️'],
                ['label' => 'Bâtiments de support',  'sub' => round($stats_army['percent']) . '% complété', 'tab' => 'Building-Army',    'icon' => '🏰'],
                ['label' => 'Pièges',  'sub' => round($stats_trap['percent']) . '% complété', 'tab' => 'Building-Trap',    'icon' => '💣'],
            ]); ?>
        </div>

        <div id="Army-Overview" class="tab-content">
            <?php renderCategoryNav('Armée', [
                ['label' => 'Troupes',            'tab' => 'Character-Troop',  'icon' => '<img src="images/icons/Fusilier_badge.webp" style="width: 50px;"/>'],
                ['label' => 'Héros',               'tab' => 'Character-Hero',   'icon' => '<img src="images/icons/buildingbutton_heroes.png" style="width: 50px;"/>'],
                ['label' => 'Proto-troupes',       'tab' => 'Character-Proto',  'icon' => '<img src="images/icons/buildingbutton_prototroops.png" style="width: 50px;"/>'],
                ['label' => 'Chef de bataillon',   'tab' => 'Character-Leader', 'icon' => '<img src="images/icons/OfficerIcon.webp" style="width: 50px;"/>'],
                ['label' => 'Capacité de canonnière', 'tab' => 'Character-Spell', 'icon' => '🚤'],
            ]); ?>
        </div>

        <div id="Engraving-Overview" class="tab-content">
            <?php renderCategoryNav('Gravures', [
                ['label' => 'Offensive', 'tab' => 'Engraving-Offensive', 'icon' => '<img src="images/icons/Icon_engravings_offence.webp" style="width: 50px;"/>'],
                ['label' => 'Defensive', 'tab' => 'Engraving-Defensive', 'icon' => '<img src="images/icons/Icon_engravings_defence.webp" style="width: 50px;"/>'],
            ]); ?>
        </div>

        <div id="Building-Ressource" class="tab-content">

            <h2>Bâtiments Économiques</h2>
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
            
        <h2>Bâtiments Défensifs</h2>
        <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
            <div style="flex: 3;">
                <?php renderBuildingsTable(['Bâtiments défensifs' => $buildings_display['Defense'] ?? []]); ?>
            </div>
            <div style="flex: 1;">
                <?php renderStatsSidebar('Bâtiments défensifs', $buildings_display['Defense'] ?? [], $stats_def); ?>
            </div>
            </div>
        </div>
        <div id="Building-Army" class="tab-content">
        <h2>Bâtiments de support</h2>
            <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 3;">
                    <?php renderBuildingsTable(['Army' => $buildings_display['Army'] ?? []]); ?>
                </div>
                <div style="flex: 1;">
                    <?php renderStatsSidebar('Bâtiments de support', $buildings_display['Army'] ?? [], $stats_army); ?>
                </div>
            </div>
        </div>

        <div id="Building-Trap" class="tab-content">
        <h2>Pièges</h2>
            <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 3;">
                    <?php renderBuildingsTable(['Trap' => $buildings_display['Trap'] ?? []]); ?>
                </div>
                <div style="flex: 1;">
                    <?php renderStatsSidebar('Pièges', $buildings_display['Trap'] ?? [], $stats_trap); ?>
                </div>
            </div>
        </div>

        <div id="Character-Troop" class="tab-content">
        <h2>Troupes</h2>
            <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 3;">
                    <?php renderUnitsTable($troupes_list, $character_progress, $house_levels); ?>
                </div>
                <div style="flex: 1;">
                    <?php renderUnitsStatsSidebar('Troupes', $troupes_list); ?>
                </div>
            </div>
        </div>
        <div id="Character-Hero" class="tab-content">
            <h2>Héros</h2>
            <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 3;">
                    <?php renderUnitsTable($heros_list); ?>
                </div>
                <div style="flex: 1;">
                    <?php renderHeroesStatsSidebar($heros_list); ?>
                </div>
            </div>
        </div>
        <div id="Character-Proto" class="tab-content">
        <h2>Proto-troupes</h2>
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
        <h2>Chef de bataillon</h2>
            <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 3;">
                    <?php renderUnitsTable($officers_list, $progress, $house_levels, $pdo, $_SESSION['player_id']); ?>
                </div>
                
                <div style="flex: 1;">
                    <?php renderLeadersStatsSidebar($pdo, $id_player, $officers_list); ?>
                </div>
            </div>
        </div>
        <div id="Character-Spell" class="tab-content">
        <h2>Capacité de canonnière</h2>
            <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 3;">
                    <?php renderUnitsTable($capacanon_list, $character_progress, $house_levels); ?>
                </div>
                <div style="flex: 1;">
                    <?php renderUnitsStatsSidebar('Capacité de canonnière', $capacanon_list); ?>
                </div>
            </div>
        </div>

        <div id="Tribes" class="tab-content">
            <h2>Tribus</h2>
            <p style="margin: 10px 0 20px; color: #bdc3c7;">
                Radar actuel : niveau <?php echo (int)$radar_level; ?> — chaque tribu se débloque à partir d'un certain niveau de Radar.
            </p>
            <?php renderTribusTable($tribus_list); ?>
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

        <div id="Engraving-Offensive" class="tab-content">
            <h2>Gravures Offensives</h2>
            <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 3;">
                    <?php renderEngravingsTable($engravings_offensive); ?>
                </div>
                <div style="flex: 1;">
                    <?php renderEngravingsStatsSidebar('Gravures Offensives', $engravings_offensive, $stats_gravures_off); ?>
                </div>
            </div>
        </div>

        <div id="Engraving-Defensive" class="tab-content">
            <h2>Gravures Défensives</h2>
            <div class="dashboard-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 3;">
                    <?php renderEngravingsTable($engravings_defensive); ?>
                </div>
                <div style="flex: 1;">
                    <?php renderEngravingsStatsSidebar('Gravures Défensives', $engravings_defensive, $stats_gravures_def); ?>
                </div>
            </div>
        </div>

        <?php include 'mass_upgrade.php'; ?>

        

    </div> <?php include 'footer.php'; ?>