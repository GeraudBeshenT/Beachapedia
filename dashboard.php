<?php
// 1. Démarrage de la session en tout premier
session_start();

// 👇 NOUVEAU : Gestion de la langue AVANT d'inclure queries.php
if (isset($_GET['lang'])) {
    $allowed_langs_get = ['EN', 'DE', 'ES', 'FR', 'IT', 'JP', 'PT', 'ZH-HANS', 'NL', 'NO', 'TR', 'KR', 'RU', 'ZH-HANT', 'AR', 'ID', 'MS', 'VI', 'TH', 'FI'];
    $lang_upper = strtoupper($_GET['lang']);
    if (in_array($lang_upper, $allowed_langs_get)) {
        $_SESSION['lang'] = $lang_upper;
        setcookie('lang', $lang_upper, time() + (86400 * 30), "/"); // 30 jours
    }
}

// 2. Vérification de la sécurité
if (!isset($_SESSION['player_id'])) {
    header("Location: /");
    exit();
}

// 3. Ensuite, on inclut les requêtes
require_once 'queries.php';
require_once 'functions.php';
require_once 'admin_config.php';

// On récupère les coûts de tous les bâtiments en base de données
$stmt_prix = $pdo->query("SELECT * FROM buildings"); 
$tous_les_prix = $stmt_prix->fetchAll(PDO::FETCH_ASSOC);
?>

<script>
    // On injecte les données proprement
    window.PRIX_BATIMENTS = <?php echo json_encode($tous_les_prix); ?>;
    console.log("Données chargées :", window.PRIX_BATIMENTS);

    // Options de bonus par emplacement de statue (onglet Profil > Statue),
    // utilisées pour peupler dynamiquement le menu déroulant "Bonus" en JS.
    window.STATUE_OPTIONS_BY_TID = <?php echo json_encode($statue_options_by_tid); ?>;
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
// 👇 Codes alignés sur les 14 colonnes réellement disponibles dans la table `texts`
// (voir $allowed_langs dans queries.php). N'ajoute une entrée ici que si la colonne
// correspondante existe bien dans `texts`, sinon la sélection retombera sur FR.
$bb_languages = [
    ['code' => 'EN', 'flag' => '🇬🇧', 'label' => 'English'],
    ['code' => 'DE', 'flag' => '🇩🇪', 'label' => 'Deutsch'],
    ['code' => 'ES', 'flag' => '🇪🇸', 'label' => 'Español'],
    ['code' => 'FR', 'flag' => '🇫🇷', 'label' => 'Français'],
    ['code' => 'IT', 'flag' => '🇮🇹', 'label' => 'Italiano'],
    ['code' => 'JP', 'flag' => '🇯🇵', 'label' => '日本語'],
    ['code' => 'ZH-HANS', 'flag' => '🇨🇳', 'label' => '简体中文'],
    ['code' => 'ZH-HANT', 'flag' => '🇹🇼', 'label' => '繁體中文'],
    ['code' => 'KR', 'flag' => '🇰🇷', 'label' => '한국어'],
    ['code' => 'NL', 'flag' => '🇳🇱', 'label' => 'Nederlands'],
    ['code' => 'NO', 'flag' => '🇳🇴', 'label' => 'Norsk'],
    ['code' => 'PT', 'flag' => '🇵🇹', 'label' => 'Português'],
    ['code' => 'RU', 'flag' => '🇷🇺', 'label' => 'Русский'],
    ['code' => 'TR', 'flag' => '🇹🇷', 'label' => 'Türkçe'],
    ['code' => 'AR', 'flag' => '🇸🇦', 'label' => 'العربية'],
    ['code' => 'MS', 'flag' => '🇲🇾', 'label' => 'Bahasa Melayu'],
    ['code' => 'ID', 'flag' => '🇮🇩', 'label' => 'Bahasa Indonesia'],
    ['code' => 'VI', 'flag' => '🇻🇳', 'label' => 'Tiếng Việt'],
    ['code' => 'TH', 'flag' => '🇹🇭', 'label' => 'ไทย'],
    ['code' => 'FI', 'flag' => '🇫🇮', 'label' => 'Suomi'],
];
?>

<div class="main-layout">
    <nav class="sidebar">
        <div class="sidebar-inner">

            <div class="sidebar-header">
                <a href="/dashboard" class="sidebar-logo">
                    <img src="images/Beachapedia.webp" alt="Beachapedia" class="sidebar-logo-img" onerror="this.style.display='none'">
                    <span class="sidebar-logo-text">Beachapedia</span>
                </a>
                <button type="button" class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()" aria-label="Réduire / agrandir le menu">
                    <img src="images/icons/Menu.png" style="width: 25px;"/>
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
                            <li>
                                <?php if ($tab_heros_unlocked): ?>
                                <button class="nav-button" onclick="showTab('Character-Hero')">Héros</button>
                                <?php else: ?>
                                <button class="nav-button locked" disabled title="Débloqué au QG niveau <?php echo (int)$hq_min_hero; ?> (actuel : <?php echo (int)$qg; ?>)">Héros <img class="troop-card-condition-icon" src="images/icons/Lock.png"></button>
                                <?php endif; ?>
                            </li>
                            <li>
                                <?php if ($tab_proto_unlocked): ?>
                                <button class="nav-button" onclick="showTab('Character-Proto')">Proto-troupes</button>
                                <?php else: ?>
                                <button class="nav-button locked" disabled title="Débloqué au QG niveau <?php echo (int)$hq_min_proto; ?> (actuel : <?php echo (int)$qg; ?>)">Proto-troupes <img class="troop-card-condition-icon" src="images/icons/Lock.png"></button>
                                <?php endif; ?>
                            </li>
                            <li>
                                <?php if ($tab_chefs_unlocked): ?>
                                <button class="nav-button" onclick="showTab('Character-Leader')">Chef de bataillon</button>
                                <?php else: ?>
                                <button class="nav-button locked" disabled title="Débloqué au QG niveau <?php echo (int)$hq_min_officier; ?> (actuel : <?php echo (int)$qg; ?>)">Chef de bataillon <img class="troop-card-condition-icon" src="images/icons/Lock.png"></button>
                                <?php endif; ?>
                            </li>
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
                            <?php if (!$tab_tribus_unlocked): ?><span class="menu-lock"><img class="troop-card-condition-icon" src="images/icons/Lock.png"></span><?php endif; ?>
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
                            <?php if (!$tab_monument_unlocked): ?><span class="menu-lock"><img class="troop-card-condition-icon" src="images/icons/Lock.png"></span><?php endif; ?>
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
                            <span class="menu-lock"><img class="troop-card-condition-icon" src="images/icons/Lock.png"></span>
                            <?php endif; ?>
                        </div>
                        <ul class="submenu" style="display: none;">
                            <li>
                                <?php if ($tab_gravures_off_unlocked): ?>
                                <button class="nav-button" onclick="showTab('Engraving-Offensive')">Offensive</button>
                                <?php else: ?>
                                <button class="nav-button locked" disabled title="Débloqué une fois le Graveur construit">Offensive <img class="troop-card-condition-icon" src="images/icons/Lock.png"></button>
                                <?php endif; ?>
                            </li>
                            <li>
                                <?php if ($tab_gravures_def_unlocked): ?>
                                <button class="nav-button" onclick="showTab('Engraving-Defensive')">Defensive</button>
                                <?php else: ?>
                                <button class="nav-button locked" disabled title="Débloqué avec le Graveur niveau 2 (actuel : <?php echo (int)$graveur_level; ?>)">Defensive <img class="troop-card-condition-icon" src="images/icons/Lock.png"></button>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php
                // Langue actuellement active (celle utilisée par queries.php), pour afficher le bon état initial
                $current_lang_code = $_SESSION['lang'] ?? 'FR';
                $current_lang_info = null;
                foreach ($bb_languages as $lg) {
                    if ($lg['code'] === $current_lang_code) {
                        $current_lang_info = $lg;
                        break;
                    }
                }
                if (!$current_lang_info) {
                    $current_lang_info = $bb_languages[0]; // fallback FR
                }
            ?>
            <div class="sidebar-footer">
                <div class="lang-select" id="langSelect">
                    <button type="button" class="lang-btn" onclick="toggleLangMenu(event)" data-tooltip="Langue">
                        <span class="lang-flag" id="currentLangFlag"><?php echo $current_lang_info['flag']; ?></span>
                        <span class="lang-label" id="currentLangLabel"><?php echo htmlspecialchars($current_lang_info['label']); ?></span>
                        <span class="lang-caret">▾</span>
                    </button>
                    <div class="lang-dropdown" id="langDropdown">
                        <?php foreach ($bb_languages as $lg): ?>
                        <button type="button" class="lang-option<?php echo $lg['code'] === $current_lang_code ? ' selected' : ''; ?>" data-lang="<?php echo $lg['code']; ?>" data-flag="<?php echo $lg['flag']; ?>" data-label="<?php echo htmlspecialchars($lg['label']); ?>" onclick="selectLang(this)">
                            <span class="lang-flag"><?php echo $lg['flag']; ?></span>
                            <span><?php echo htmlspecialchars($lg['label']); ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="sidebar-profile">
                    <div class="profile-btn" onclick="showTab('Profile-Overview')" data-tooltip="<?php echo htmlspecialchars($_SESSION['player_nom']); ?>">
                        <div class="profile-avatar">
                            <?php echo htmlspecialchars(strtoupper(substr($_SESSION['player_nom'], 0, 1))); ?>
                            <img src="images/icons/gacha_info_icon.png" class="badge-icon" alt="" onerror="this.style.display='none'">
                        </div>
                        <span class="profile-name"><?php echo htmlspecialchars($_SESSION['player_nom']); ?></span>
                    </div>
                </div>
            </div>

        </div>
    </nav>

    <div class="main-content">

        <div class="content-body-flex">
        <div class="content-main-col">

        <?php include 'modal_qg.php'; ?>

        <!-- Modale de confirmation déblocage/verrouillage d'un Chef de bataillon
             (ouverte depuis la barre de déblocage rapide, voir renderOfficerQuickBar
             dans functions.php et openOfficerUnlockModal() dans script.js) -->
        <div id="officer-unlock-modal" class="modal">
            <div class="modal-content officer-unlock-modal-content">
                <span class="close-modal" onclick="closeOfficerUnlockModal()">&times;</span>
                <div class="officer-unlock-modal-body">
                    <img id="officer-unlock-modal-img" src="" alt="">
                    <h3 id="officer-unlock-modal-name"></h3>
                    <p id="officer-unlock-modal-status" style="color:#95a5a6;"></p>
                    <div class="officer-unlock-modal-actions">
                        <button type="button" class="officer-unlock-btn officer-unlock-btn-yes" onclick="confirmOfficerUnlock(true)"><img class="troop-card-condition-icon" src="images/icons/Unlock.png" style="width:50px;"> Débloqué</button>
                        <button type="button" class="officer-unlock-btn officer-unlock-btn-no" onclick="confirmOfficerUnlock(false)"><img class="troop-card-condition-icon" src="images/icons/Lock.png" style="width:50px;"> Pas débloqué / Reverrouiller</button>
                    </div>
                </div>
            </div>
        </div>

        <?php
            // On pré-calcule les stats pour les passer à la sidebar
            $stats_res = getCategoryStats($buildings_display['Ressource'] ?? []);
            $stats_def = getCategoryStats($buildings_display['Defense'] ?? []);
            $stats_army = getCategoryStats($buildings_display['Army'] ?? []);
            $stats_trap = getCategoryStats($buildings_display['Trap'] ?? []);

            // Types de bâtiments (TID distincts) débloqués au QG actuel / total existant
            // dans la catégorie (tous QG confondus) — pour le "X / Y bâtiments" du dashboard.
            foreach ([
                'Ressource' => &$stats_res,
                'Defense'   => &$stats_def,
                'Army'      => &$stats_army,
                'Trap'      => &$stats_trap,
            ] as $cat => &$stats_ref) {
                $stats_ref['types_debloques'] = count(array_unique(array_column($buildings_display[$cat] ?? [], 'TID')));
                $stats_ref['types_total']     = $buildings_types_total[$cat] ?? 0;
            }
            unset($stats_ref);

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

            // --- Armée : Troupes, Proto-troupes (désormais séparées), Héros, et capacités des Chefs (hors talents) ---
            $stats_troupes        = getUnitsStats($troupes_list ?? []);
            $stats_proto          = getUnitsStats($proto_list ?? []);
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

            // --- Monument mystique (%age dashboard basé sur le niveau MM déclaré par le
            // joueur -- joueurs.MM, $player_mm_level, voir queries.php -- PAS sur
            // $monument_level qui lui ne sert qu'au déblocage de l'onglet / aux stats de
            // la page Bâtiments > Support) ---
            $monument_max_level  = 800;
            $monument_bonus_total    = count($cc_bonuses);
            $monument_bonus_obtenus  = count(array_filter($player_bonuses, fn($nb) => $nb > 0));
            $stats_monument = [
                'level'         => $player_mm_level,
                'max_level'     => $monument_max_level,
                'percent'       => ($monument_max_level > 0) ? round(($player_mm_level / $monument_max_level) * 100, 1) : 0,
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
            <?php
                renderMainDashboard(
                    $stats_buildings_global, $stats_res, $stats_def, $stats_army, $stats_trap,
                    $stats_troupes, $stats_proto, $stats_heros, $stats_officiers_capa, $chefs_debloques, $chefs_total,
                    $stats_capacanon,
                    $stats_gravures, $stats_gravures_off, $stats_gravures_def,
                    $stats_tribus, $stats_monument,
                    $qg
                );

                // Bandeau horizontal (scroll) listant tous les niveaux de QG, avec une coche
                // (images/icons/check.png) sur les niveaux où bâtiments + troupes + capacités
                // de canonnière + héros débloqués à ce QG sont TOUS au niveau maximum absolu.
                // $all_qg_images vient de queries.php (Niveau + ExportName de TID_BUILDING_PALACE).
                $qg_maxed_map = getQgMaxedMap($pdo, $id_player, $all_qg_images);
                renderQgProgressStrip($all_qg_images, $qg_maxed_map, $qg);
            ?>
        </div>

        <?php
            // Ressources nécessaires pour tout finir au max (jusqu'au niveau max ATTEIGNABLE
            // au QG actuel / à l'Arsenal actuel selon la catégorie) : 1 ligne par
            // sous-catégorie + Total. Calculées une fois ici, affichées sous les cartes
            // de chaque page de navigation (Building-Overview / Army-Overview).
            $resume_batiments = getBuildingsResourceSummary($buildings_display);
            $resume_armee     = getArmyResourceSummary($pdo, $id_player, $troupes_list, $proto_list, $heros_list, $officers_list, $capacanon_list);
        ?>

        <div id="Building-Overview" class="tab-content">
            <?php renderCategoryNav('Bâtiments', [
                ['label' => 'Bâtiments économiques', 'sub' => round($stats_res['percent']) . '% complété', 'tab' => 'Building-Ressource', 'icon' => '🏦'],
                ['label' => 'Bâtiments défensifs',  'sub' => round($stats_def['percent']) . '% complété',  'tab' => 'Building-Defense', 'icon' => '🛡️'],
                ['label' => 'Bâtiments de support',  'sub' => round($stats_army['percent']) . '% complété', 'tab' => 'Building-Army',    'icon' => '🏰'],
                ['label' => 'Pièges',  'sub' => round($stats_trap['percent']) . '% complété', 'tab' => 'Building-Trap',    'icon' => '💣'],
            ]); ?>
            <?php renderBuildingResourceSummaryTable($resume_batiments); ?>
        </div>

        <div id="Army-Overview" class="tab-content">
            <?php renderCategoryNav('Armée', [
                ['label' => 'Troupes',            'tab' => 'Character-Troop',  'icon' => '<img src="images/icons/Fusilier_badge.webp" style="width: 50px;"/>'],
                ['label' => 'Héros',               'tab' => 'Character-Hero',   'icon' => '<img src="images/icons/buildingbutton_heroes.png" style="width: 50px;"/>', 'locked' => !$tab_heros_unlocked, 'lock_tooltip' => "Débloqué au QG niveau {$hq_min_hero} (actuel : {$qg})"],
                ['label' => 'Proto-troupes',       'tab' => 'Character-Proto',  'icon' => '<img src="images/icons/buildingbutton_prototroops.png" style="width: 50px;"/>', 'locked' => !$tab_proto_unlocked, 'lock_tooltip' => "Débloqué au QG niveau {$hq_min_proto} (actuel : {$qg})"],
                ['label' => 'Chef de bataillon',   'tab' => 'Character-Leader', 'icon' => '<img src="images/icons/OfficerIcon.webp" style="width: 50px;"/>', 'locked' => !$tab_chefs_unlocked, 'lock_tooltip' => "Débloqué au QG niveau {$hq_min_officier} (actuel : {$qg})"],
                ['label' => 'Capacité de canonnière', 'tab' => 'Character-Spell', 'icon' => '🚤'],
            ]); ?>
            <?php renderArmyResourceSummaryTable($resume_armee); ?>
        </div>

        <div id="Engraving-Overview" class="tab-content">
            <?php renderCategoryNav('Gravures', [
                ['label' => 'Offensive', 'tab' => 'Engraving-Offensive', 'icon' => '<img src="images/icons/Icon_engravings_offence.webp" style="width: 50px;"/>'],
                ['label' => 'Defensive', 'tab' => 'Engraving-Defensive', 'icon' => '<img src="images/icons/Icon_engravings_defence.webp" style="width: 50px;"/>'],
            ]); ?>
        </div>

        <div id="Building-Ressource" class="tab-content">

            <h2>Bâtiments Économiques</h2>
            <div class="dashboard-wrapper">
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
        <div class="dashboard-wrapper">
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
            <div class="dashboard-wrapper">
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
            <div class="dashboard-wrapper">
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
            <div class="dashboard-wrapper">
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
            <div class="dashboard-wrapper">
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
            <div class="dashboard-wrapper">
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
            <?php renderOfficerQuickBar($officers_list, $officer_ranks_by_id ?? []); ?>
            <?php
                // La barre ci-dessus (renderOfficerQuickBar) affiche TOUS les officiers pour
                // permettre de débloquer/reverrouiller. En revanche, les cartes détaillées
                // ci-dessous (niveau, talents, capacités) n'ont de sens que pour les officiers
                // déjà débloqués -> on filtre sur Debloque = 1 uniquement pour cet affichage.
                $officers_list_debloques = array_values(array_filter($officers_list, function($o) {
                    return (int)($o['Debloque'] ?? 0) === 1;
                }));
            ?>
            <div class="dashboard-wrapper">
                <div style="flex: 3;">
                    <?php renderUnitsTable($officers_list_debloques, $character_progress, $house_levels, $pdo, $_SESSION['player_id']); ?>
                </div>
                
                <div style="flex: 1;">
                    <?php renderLeadersStatsSidebar($pdo, $id_player, $officers_list); ?>
                </div>
            </div>
        </div>
        <div id="Character-Spell" class="tab-content">
        <h2>Capacité de canonnière</h2>
            <div class="dashboard-wrapper">
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
            <?php renderMysticMonument($player_mm_level, $cc_bonuses, $player_bonuses); ?>
        </div>
        <div id="Engraving-Offensive" class="tab-content">
            <h2>Gravures Offensives</h2>
            <div class="dashboard-wrapper">
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
            <div class="dashboard-wrapper">
                <div style="flex: 3;">
                    <?php renderEngravingsTable($engravings_defensive); ?>
                </div>
                <div style="flex: 1;">
                    <?php renderEngravingsStatsSidebar('Gravures Défensives', $engravings_defensive, $stats_gravures_def); ?>
                </div>
            </div>
        </div>

        <?php
            // Bonus de vitesse actuels du joueur (Profil > Boost), pour pré-remplir le formulaire.
            $player_boosts = getPlayerBoosts($pdo, $id_player);
        ?>
        <div id="Profile-Overview" class="tab-content">
            <h2>Profil</h2>

            <div class="profile-page-header">
                <div class="profile-page-avatar">
                    <?php echo htmlspecialchars(strtoupper(substr($_SESSION['player_nom'], 0, 1))); ?>
                </div>
                <div class="profile-page-identity">
                    <span class="profile-page-name"><?php echo htmlspecialchars($_SESSION['player_nom']); ?></span>
                    <span class="profile-page-sub">QG niveau <?php echo (int)$qg; ?></span>
                </div>
            </div>

            <div class="profile-page-actions">
                <button type="button" class="profile-action-btn" onclick="showTab('MassUpgrade')">🚀 Mass Upgrade</button>
                <?php if (($_SESSION['player_id'] ?? null) === ADMIN_PLAYER_ID): ?>
                <a href="/admin" class="profile-action-btn">🛠️ Administration</a>
                <?php endif; ?>
                <a href="deconnexion.php" class="profile-action-btn profile-action-btn-danger">🚪 Déconnexion</a>
            </div>

            <?php renderCategoryNav('Réglages', [
                ['label' => 'Boost', 'sub' => 'Bonus de vitesse de construction', 'tab' => 'Profile-Boost', 'icon' => '⚡'],
                ['label' => 'Statue', 'sub' => 'Gérer mes statues', 'tab' => 'Profile-Statue', 'icon' => '🗿'],
            ]); ?>
        </div>

        <div id="Profile-Boost" class="tab-content">
            <h2>Boost</h2>
            <p style="margin: 10px 0 20px; color: #bdc3c7;">
                Ces bonus réduisent uniquement le temps d'amélioration <strong>affiché sur le site</strong>
                (aucun impact sur les valeurs réelles du jeu). BuildingBoost s'applique aux bâtiments
                améliorés depuis le QG (Économiques / Défensifs / Support) — pas aux Pièges/Mines ni
                aux Troupes, qui dépendent d'ArmoryBoost (Arsenal/Atelier : Pièges, Mines, Troupes,
                Proto-troupes, Héros, Chefs de bataillon, Capacités de canonnière).
            </p>

            <div class="boost-form">
                <div class="boost-field">
                    <label for="boost-building">🏛️ BuildingBoost</label>
                    <div class="boost-input-wrap">
                        <input type="number" id="boost-building" min="0" max="99" step="1" value="<?php echo (int)$player_boosts['building']; ?>">
                        <span class="boost-input-suffix">%</span>
                    </div>
                </div>
                <div class="boost-field">
                    <label for="boost-armory">⚔️ ArmoryBoost</label>
                    <div class="boost-input-wrap">
                        <input type="number" id="boost-armory" min="0" max="99" step="1" value="<?php echo (int)$player_boosts['armory']; ?>">
                        <span class="boost-input-suffix">%</span>
                    </div>
                </div>
                <button type="button" class="boost-save-btn" onclick="saveBoosts()">Enregistrer</button>
                <span class="boost-save-status" id="boost-save-status"></span>
            </div>
        </div>

        <div id="Profile-Statue" class="tab-content">
            <?php renderStatuesTable($artifact_capacity, $statue_emplacements, $player_statues); ?>
        </div>

        <?php include 'mass_upgrade.php'; ?>

        

        </div>
        <?php renderEventPanel(getActiveEvents($pdo)); ?>
        </div>

    </div> <?php include 'footer.php'; ?>