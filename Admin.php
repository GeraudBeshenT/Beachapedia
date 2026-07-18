<?php
// admin.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'admin_config.php';

// Accès strictement réservé au compte admin (id_player = ADMIN_PLAYER_ID).
// Un joueur non connecté est renvoyé vers le login ; un joueur connecté mais
// non autorisé reçoit un simple message d'erreur (pas de redirection silencieuse
// qui laisserait penser à un bug).
if (!isset($_SESSION['player_id'])) {
    header("Location: index.php");
    exit();
}
if (!admin_check_access()) {
    http_response_code(403);
    echo "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><title>Accès refusé</title></head>
    <body style='font-family:sans-serif; background:#0f1720; color:#eee; display:flex; align-items:center; justify-content:center; height:100vh; margin:0;'>
        <div style='text-align:center;'>
            <h1>🔒 Accès refusé</h1>
            <p>Cette page est réservée à l'administrateur.</p>
            <p><a href='dashboard.php' style='color:#1abc9c;'>← Retour au dashboard</a></p>
        </div>
    </body></html>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Beachapedia</title>
<style>
    :root {
        --bg-dark-blue: #105771;
        --bg-mid-blue: #01AFC1;
        --bg-light-blue: #00B0BF;
        --accent-teal: #1abc9c;
        --admin-bg: #0f1720;
        --admin-card: #1b2634;
        --admin-border: #2b3a4a;
        --admin-text: #e7edf3;
        --admin-text-dim: #93a3b3;
        --admin-danger: #e74c3c;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: 'Segoe UI', Roboto, Arial, sans-serif;
        background: var(--admin-bg);
        color: var(--admin-text);
    }
    .admin-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 24px;
        background: linear-gradient(180deg, var(--bg-dark-blue) 0%, var(--bg-light-blue) 100%);
    }
    .admin-topbar h1 { font-size: 18px; margin: 0; }
    .admin-topbar a {
        color: #fff;
        text-decoration: none;
        background: rgba(0,0,0,0.25);
        padding: 8px 14px;
        border-radius: 6px;
        font-size: 14px;
    }
    .admin-topbar a:hover { background: rgba(0,0,0,0.4); }

    .admin-container { max-width: 1200px; margin: 0 auto; padding: 24px; }

    .admin-toolbar {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
        align-items: flex-end;
        background: var(--admin-card);
        border: 1px solid var(--admin-border);
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 20px;
    }
    .admin-toolbar label { display: flex; flex-direction: column; gap: 6px; font-size: 13px; color: var(--admin-text-dim); }
    .admin-toolbar select, .admin-toolbar input[type="text"] {
        background: #0f1720;
        border: 1px solid var(--admin-border);
        color: var(--admin-text);
        padding: 8px 10px;
        border-radius: 6px;
        min-width: 220px;
        font-size: 14px;
    }

    .admin-card {
        background: var(--admin-card);
        border: 1px solid var(--admin-border);
        border-radius: 10px;
        padding: 18px;
        margin-bottom: 20px;
    }
    .admin-card h3 { margin-top: 0; }
    .admin-card-header-row { display: flex; align-items: center; justify-content: space-between; }

    .admin-fiche-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 12px;
        margin-bottom: 14px;
    }
    .admin-field { display: flex; flex-direction: column; gap: 6px; font-size: 13px; color: var(--admin-text-dim); }
    .admin-field input {
        background: #0f1720;
        border: 1px solid var(--admin-border);
        color: var(--admin-text);
        padding: 8px 10px;
        border-radius: 6px;
        font-size: 14px;
    }

    .admin-actions-row { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }

    .admin-btn {
        border: none;
        border-radius: 6px;
        padding: 9px 14px;
        font-size: 14px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .admin-btn-primary { background: var(--accent-teal); color: #06231d; font-weight: 600; }
    .admin-btn-secondary { background: #2b3a4a; color: var(--admin-text); }
    .admin-btn-mini { background: #2b3a4a; color: var(--admin-text); padding: 6px 9px; font-size: 13px; }
    .admin-btn-danger { background: var(--admin-danger); color: #fff; }
    .admin-btn:hover { filter: brightness(1.1); }
    .admin-upload-inline { display: flex; align-items: center; gap: 10px; }
    .admin-upload-hint { font-size: 12px; color: var(--admin-text-dim); }

    .admin-table-wrapper { overflow-x: auto; }
    .admin-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .admin-table th, .admin-table td { padding: 8px 10px; border-bottom: 1px solid var(--admin-border); text-align: left; white-space: nowrap; }
    .admin-table th { color: var(--admin-text-dim); font-weight: 600; }
    .admin-table input {
        background: #0f1720;
        border: 1px solid var(--admin-border);
        color: var(--admin-text);
        padding: 6px 8px;
        border-radius: 5px;
        width: 100px;
        font-size: 13px;
    }
    .admin-row-actions { display: flex; gap: 6px; }

    .admin-empty-state { color: var(--admin-text-dim); text-align: center; padding: 40px 0; }

    .admin-toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #1b2634;
        border: 1px solid var(--accent-teal);
        color: var(--admin-text);
        padding: 12px 18px;
        border-radius: 8px;
        opacity: 0;
        transform: translateY(10px);
        transition: all 0.25s ease;
        pointer-events: none;
        max-width: 360px;
        z-index: 999;
    }
    .admin-toast.show { opacity: 1; transform: translateY(0); }
    .admin-toast.error { border-color: var(--admin-danger); }

    /* Champs en lecture seule tant que "Modifier" n'a pas été cliqué */
    input:disabled, select:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        background: #10161f;
    }
    .admin-btn-edit { background: #2b6ea3; color: #fff; }
</style>
</head>
<body>

<div class="admin-topbar">
    <h1>🛠️ Administration — Beachapedia</h1>
    <a href="dashboard.php">← Retour au dashboard</a>
</div>

<div class="admin-container">

    <div class="admin-toolbar">
        <label>
            Type de contenu
            <select id="admin-type">
                <?php foreach ($GLOBALS['ADMIN_ENTITIES'] as $key => $def): ?>
                    <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($def['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Classe / Catégorie
            <select id="admin-class">
                <option value="">— Toutes —</option>
            </select>
        </label>
        <label>
            Filtrer
            <input type="text" id="admin-tid-filter" placeholder="Rechercher un TID ou un nom…">
        </label>
        <label>
            TID
            <select id="admin-tid">
                <option value="">— Choisir un type d'abord —</option>
            </select>
        </label>
    </div>

    <div id="admin-add-character" class="admin-card" style="display:none;">
        <h3>➕ Ajouter un personnage</h3>
        <div class="admin-fiche-grid">
            <label class="admin-field">
                <span>TID (ex : TID_MONNOM_OFC_EXEMPLE)</span>
                <input type="text" id="new-char-tid">
            </label>
            <label class="admin-field">
                <span>Classe</span>
                <select id="new-char-class">
                    <option value="Troupe">Troupe</option>
                    <option value="Hero">Hero</option>
                    <option value="Proto">Proto</option>
                    <option value="Officier">Officier</option>
                    <option value="Spell">Spell</option>
                </select>
            </label>
            <label class="admin-field">
                <span>QG requis (HQUnlock)</span>
                <input type="number" id="new-char-hq" value="1">
            </label>
            <label class="admin-field">
                <span>Nom image (IconExportName)</span>
                <input type="text" id="new-char-icon">
            </label>
            <label class="admin-field" id="new-char-officer-troop-wrap" style="display:none;">
                <span>Troupe liée (Officer)</span>
                <select id="new-char-officer-troop">
                    <option value="">— Choisir une troupe —</option>
                </select>
            </label>
            <label class="admin-field" id="new-char-rank-wrap" style="display:none;">
                <span>Type d'officier</span>
                <select id="new-char-rank">
                    <option value="Lieutenant">Lieutenant (2 talents)</option>
                    <option value="Sergent">Sergent (1 seul talent)</option>
                </select>
            </label>
            <label class="admin-field" id="new-char-ability-wrap" style="display:none;">
                <span>Talent du Sergent</span>
                <select id="new-char-ability">
                    <option value="Active">Actif</option>
                    <option value="Passive">Passif</option>
                </select>
            </label>
            <label class="admin-field" id="new-char-active-icon-wrap" style="display:none;">
                <span>Nom image capacité active (IconExportName)</span>
                <input type="text" id="new-char-active-icon" placeholder="ex : icon_fireshot">
            </label>
        </div>
        <div class="admin-actions-row">
            <button type="button" class="admin-btn admin-btn-primary" id="new-char-submit">➕ Insérer</button>
            <span class="admin-upload-hint">Après insertion, la page est rechargée sur ce nouveau TID.</span>
        </div>
    </div>

    <div id="admin-empty-state" class="admin-empty-state">Sélectionne un type puis un TID pour afficher sa fiche et ses niveaux.</div>

    <div id="admin-fiche"></div>
    <div id="admin-levels"></div>
    <div id="admin-officer-abilities"></div>
    <div id="admin-active-ability-levels"></div>
    <div id="admin-passive-ability-levels"></div>

</div>

<div id="admin-toast" class="admin-toast"></div>

<script src="admin.js"></script>
</body>
</html>