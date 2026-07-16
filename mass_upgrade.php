<?php
/**
 * mass_upgrade.php
 * Contenu de l'onglet "Mass Upgrade", à inclure dans dashboard.php (main-content).
 * Suppose que $pdo, $id_player et $qg (QG actuel du joueur) sont déjà disponibles
 * (c'est le cas dans dashboard.php, qui inclut déjà queries.php avant ce fichier).
 */

require_once 'mass_upgrade_helpers.php';
require_once 'mass_upgrade_render.php';

$mu_max_qg = muGetMaxQG($pdo);
$mu_default_qg = isset($qg) ? (int)$qg : 1;
if ($mu_default_qg < 1) $mu_default_qg = 1;
if ($mu_default_qg > $mu_max_qg) $mu_default_qg = $mu_max_qg;

$mu_initial_data = muBuildData($pdo, $id_player, $mu_default_qg);
?>
<div id="MassUpgrade" class="tab-content">
    <div class="mu-wrapper">

        <div class="mu-header">
            <div>
                <h2>🚀 Mass Upgrade</h2>
                <p class="mu-subtitle">
                    Renseignez en une fois le niveau de vos bâtiments, troupes, héros, chefs de bataillon,
                    leurs capacités et vos gravures pour votre QG actuel. Idéal pour importer d'un coup un compte déjà avancé
                    sans devoir cliquer sur « Améliorer » des milliers de fois.
                </p>
            </div>
        </div>

        <div class="mu-toolbar">
            <label for="muQgSelect">Niveau de QG</label>
            <select id="muQgSelect">
                <?php for ($i = 1; $i <= $mu_max_qg; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo $i === $mu_default_qg ? 'selected' : ''; ?>>
                        QG <?php echo $i; ?>
                    </option>
                <?php endfor; ?>
            </select>
            <button type="button" class="mu-btn mu-btn-secondary" onclick="muLoadForm()">🔄 Charger</button>
            <span id="muLoadingIndicator" class="mu-loading" style="display:none;">Chargement…</span>
        </div>

        <div id="muFormContainer">
            <?php echo muRenderForm($mu_initial_data); ?>
        </div>

        <div class="mu-footer">
            <span id="muStatusMsg" class="mu-status"></span>
            <button type="button" class="mu-btn mu-btn-primary" onclick="muSaveAll()">💾 Enregistrer tout</button>
        </div>

    </div>
</div>

<style>
/* .mu-wrapper { padding: 20px; color: #ecf0f1; } */
.mu-header h2 { margin: 0 0 6px 0; }
.mu-subtitle { color: #bdc3c7; max-width: 800px; padding-left: 10px; font-size: 0.95em; line-height: 1.4; }

.mu-toolbar {
    display: flex; align-items: center; gap: 12px; margin: 18px 0; padding-left: 10px;
        background: radial-gradient(circle, rgba(55, 88, 95, 1) 0%, rgba(40, 57, 64, 1) 100%); padding: 12px 16px; border-radius: 8px; flex-wrap: wrap;
}
.mu-toolbar label { font-weight: bold; }
.mu-toolbar select {
    background: #2c3e50; color: white; border: 1px solid rgba(255,255,255,0.2);
    border-radius: 4px; padding: 6px 10px;
}
.mu-loading { color: #f1c40f; font-size: 0.9em; }

.mu-btn {
    border: none; border-radius: 4px; padding: 8px 16px; font-weight: bold;
    cursor: pointer; color: white; font-size: 0.95em;
}
.mu-btn-secondary { background: #34495e; }
.mu-btn-primary { background: #2ecc71; }
.mu-btn-primary:hover { background: #27ae60; }

.mu-section { margin-bottom: 28px; }
.mu-section-header, .mu-subcategory-header {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
    border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 6px; margin-bottom: 12px;
}
.mu-section-header h3 { margin: 0; color: #3498db; }
.mu-subcategory-header h4 { margin: 14px 0 0 0; color: #1abc9c; }

.mu-fill-row { display: flex; align-items: center; gap: 6px; }
.mu-fill-label { font-size: 0.85em; color: #95a5a6; }
.mu-fill-input {
    width: 60px; background: #1a252f; border: 1px solid rgba(255,255,255,0.2);
    border-radius: 4px; color: white; padding: 4px 6px;
}
.mu-fill-btn {
    background: #3498db; color: white; border: none; border-radius: 4px;
    padding: 5px 10px; cursor: pointer; font-size: 0.85em;
}

.mu-grid {
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}
.mu-item {
    background: #1a252f; border: 1px solid rgba(255,255,255,0.08); border-radius: 6px;
    padding: 10px 12px;
}
.mu-item-name { font-size: 0.9em; margin-bottom: 6px; }
.mu-item-max { color: #7f8c8d; font-size: 0.85em; }
.mu-input {
    width: 100%; background: #2c3e50; border: 1px solid rgba(255,255,255,0.2);
    border-radius: 4px; color: white; padding: 6px 8px; box-sizing: border-box;
}
.mu-instances { display: flex; flex-wrap: wrap; gap: 10px; }
.mu-instance {
    display: flex; flex-direction: column; font-size: 0.75em; color: #95a5a6; gap: 4px;
    flex: 1 1 140px;
}

/* 🔥 Slider + champ numérique côte à côte, façon Clash Ninja */
.mu-level-control {
    display: flex; align-items: center; gap: 8px;
}
.mu-level-control .mu-input { width: 55px; flex: 0 0 auto; }
.mu-slider {
    flex: 1 1 auto;
    accent-color: #1abc9c;
    cursor: pointer;
}

/* 🔥 Boutons de niveau rapide (1 à niveau_max), appliqués à toute la famille (carte entière) */
.mu-quick-levels {
    display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 8px;
}
.mu-quick-btn {
    background: #2c3e50; color: #bdc3c7; border: 1px solid rgba(255,255,255,0.15);
    border-radius: 4px; min-width: 26px; padding: 3px 6px; font-size: 0.78em;
    cursor: pointer; transition: all 0.15s;
}
.mu-quick-btn:hover {
    background: #1abc9c; color: white; border-color: #1abc9c;
}

.mu-abilities { margin-top: 10px; border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 8px; }
.mu-ability-row {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    font-size: 0.82em; margin-bottom: 5px; color: #bdc3c7;
}
.mu-ability-row .mu-input { width: 55px; }

.mu-empty { color: #7f8c8d; font-style: italic; }

.mu-footer {
    display: flex; align-items: center; justify-content: flex-end; gap: 16px;
    position: sticky; bottom: 0;     background: radial-gradient(circle, rgba(55, 88, 95, 1) 0%, rgba(40, 57, 64, 1) 100%); padding: 14px; margin-top: 10px;
}
.mu-status { font-size: 0.9em; }
.mu-status.ok { color: #2ecc71; }
.mu-status.err { color: #e74c3c; }

/* NOUVEAUX STYLES POUR LA NOUVELLE INTERFACE */
.mu-tabs {
    display: flex;
    flex-direction: column;
    gap: 15px;
    padding: 10px;
}

.mu-tab-buttons {
    display: flex;
    gap: 10px;
    border-bottom: 2px solid #1abc9c;
    margin-bottom: 15px;
}

.mu-tab-btn {
    padding: 10px 20px;
    background: #2c3e50;
    color: #bdc3c7;
    border: none;
    border-radius: 6px 6px 0 0;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.2s;
}

.mu-tab-btn:hover {
    background: #34495e;
    color: #fff;
}

.mu-tab-btn.active {
    background: #1abc9c;
    color: white;
}

.mu-tab-content {
    display: none;
}

.mu-tab-content.active {
    display: block;
}

.mu-item-icon {
    width: 75px;
    height: 75px;
    object-fit: contain;
    margin-right: 10px;
    vertical-align: middle;
}

.mu-item-header {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}

.mu-item-name-wrapper {
    display: flex;
    flex-direction: column;
}

.mu-talents-container {
    margin-top: 10px;
    border-top: 1px dashed rgba(255,255,255,0.1);
    padding-top: 8px;
}

.mu-talent-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 5px;
    font-size: 0.85em;
    color: #bdc3c7;
}

.mu-talent-checkbox {
    margin: 0;
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.mu-officer-item {
    min-height: 120px;
}

.mu-capacities-container {
    margin-top: 8px;
    border-top: 1px dashed rgba(255,255,255,0.1);
    padding-top: 8px;
}

/* Responsive mobile : 1 carte par ligne, comme sur le reste du site */
@media (max-width: 768px) {
    .mu-grid { grid-template-columns: 1fr; }
    .mu-toolbar { flex-direction: column; align-items: stretch; }
    .mu-footer { flex-direction: column; align-items: stretch; }
}
</style>

<script>
// --- Mass Upgrade : chargement dynamique du formulaire pour un QG donné ---
function muLoadForm() {
    const qg = document.getElementById('muQgSelect').value;
    const indicator = document.getElementById('muLoadingIndicator');
    indicator.style.display = 'inline';

    fetch('mass_upgrade_load.php?qg=' + encodeURIComponent(qg))
        .then(r => r.text())
        .then(html => {
            document.getElementById('muFormContainer').innerHTML = html;
        })
        .catch(err => {
            console.error('Erreur chargement Mass Upgrade :', err);
            muSetStatus('Erreur lors du chargement.', false);
        })
        .finally(() => { indicator.style.display = 'none'; });
}

// Remplit tous les .mu-input à l'intérieur du plus proche conteneur .mu-fillable
// avec la valeur saisie dans l'input voisin (bouton "Appliquer").
function muFillGroup(button) {
    const row = button.closest('.mu-fill-row');
    const value = row.querySelector('.mu-fill-input').value;
    if (value === '') return;

    // Le groupe ciblé est le conteneur .mu-fillable qui suit immédiatement ce header
    // (ou l'ancêtre .mu-fillable si le bouton est dans un sous-groupe imbriqué).
    let container = row.closest('.mu-section, .mu-subcategory')?.querySelector(':scope > .mu-grid.mu-fillable')
                    || row.closest('.mu-fillable');

    if (!container) return;

    container.querySelectorAll('.mu-input').forEach(input => {
        const max = input.getAttribute('max');
        let v = parseInt(value, 10);
        if (max !== null && v > parseInt(max, 10)) v = parseInt(max, 10);
        input.value = v;
        // Si ce champ a un slider jumeau (.mu-level-control), on le resynchronise aussi
        const wrapper = input.closest('.mu-level-control');
        const range = wrapper ? wrapper.querySelector('.mu-slider') : null;
        if (range) range.value = v;
    });
}

// Synchronise le champ numérique et le slider d'un même niveau (les deux partagent le
// conteneur .mu-level-control), quel que soit celui des deux qu'on vient de modifier.
function muSyncLevelControl(el) {
    const wrapper = el.closest('.mu-level-control');
    if (!wrapper) return;
    const num = wrapper.querySelector('input[type="number"]');
    const range = wrapper.querySelector('input[type="range"]');
    if (!num || !range) return;

    if (el.type === 'range') {
        num.value = el.value;
    } else {
        const max = parseInt(range.getAttribute('max'), 10);
        let v = parseInt(el.value, 10) || 0;
        if (!isNaN(max) && v > max) v = max;
        el.value = v;
        range.value = v;
    }
}

// Bouton de niveau rapide ("comme Clash Ninja") : applique le niveau cliqué à TOUTES les
// instances de la carte (= même famille de bâtiment), champs numériques ET sliders.
function muSetFamilyLevel(button, level) {
    const card = button.closest('.mu-item');
    if (!card) return;

    card.querySelectorAll('.mu-level-control').forEach(wrapper => {
        const num = wrapper.querySelector('input[type="number"]');
        const range = wrapper.querySelector('input[type="range"]');
        if (!num) return;
        const max = parseInt(num.getAttribute('max'), 10);
        const v = (!isNaN(max) && level > max) ? max : level;
        num.value = v;
        if (range) range.value = v;
    });
}

function muSetStatus(msg, ok) {
    const el = document.getElementById('muStatusMsg');
    el.textContent = msg;
    el.className = 'mu-status ' + (ok ? 'ok' : 'err');
}

// --- Collecte de tous les champs saisis et envoi en un seul POST ---
function muSaveAll() {
    const qg = document.getElementById('muQgSelect').value;

    const buildings = [];
    document.querySelectorAll('.mu-input.mu-building').forEach(input => {
        buildings.push({
            id_building: parseInt(input.dataset.building, 10),
            id_instance: parseInt(input.dataset.instance, 10),
            niveau: parseInt(input.value || '0', 10)
        });
    });

    const characters = [];
    document.querySelectorAll('.mu-input.mu-character').forEach(input => {
        characters.push({
            id_character: parseInt(input.dataset.character, 10),
            niveau: parseInt(input.value || '0', 10)
        });
    });

    const abilities = [];
    document.querySelectorAll('.mu-input.mu-ability').forEach(input => {
        abilities.push({
            id_character: parseInt(input.dataset.character, 10),
            id_ability: parseInt(input.dataset.ability, 10),
            niveau: parseInt(input.value || '0', 10)
        });
    });

    // Talents (cases à cocher) : on les fusionne dans le même tableau "abilities",
    // le backend upsert dans progress_ability aussi bien pour les capacités que les talents.
    document.querySelectorAll('.mu-talent-checkbox').forEach(checkbox => {
        const isChecked = checkbox.checked;
        abilities.push({
            id_character: parseInt(checkbox.dataset.char, 10),
            id_ability: parseInt(checkbox.dataset.talent, 10),
            niveau: isChecked ? 1 : 0,
            debloque: isChecked ? 1 : 0
        });
    });

    const engravings = [];
    document.querySelectorAll('.mu-input.mu-engraving').forEach(input => {
        engravings.push({
            id_engraving: parseInt(input.dataset.engraving, 10),
            niveau: parseInt(input.value || '0', 10)
        });
    });

    muSetStatus('Enregistrement en cours…', true);

    fetch('mass_upgrade_save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ qg: parseInt(qg, 10), buildings, characters, abilities, engravings })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            muSetStatus(
                `✅ Enregistré : ${data.nb_buildings} bâtiments, ${data.nb_characters} personnages, ${data.nb_abilities} capacités, ${data.nb_engravings} gravures.`,
                true
            );
            // Petit délai pour laisser le message de confirmation s'afficher avant le
            // rechargement complet de la page (sinon il n'a pas le temps d'être vu).
            setTimeout(() => window.location.reload(), 800);
        } else {
            muSetStatus('❌ ' + (data.message || 'Erreur inconnue.'), false);
        }
    })
    .catch(err => {
        console.error('Erreur enregistrement Mass Upgrade :', err);
        muSetStatus('❌ Erreur réseau lors de l\'enregistrement.', false);
    });
}
// Gestion des onglets
function muSwitchTab(tabName) {
    document.querySelectorAll('.mu-tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.mu-tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    document.getElementById('mu-tab-' + tabName).classList.add('active');
    event.target.classList.add('active');
}

// Gestion de la visibilité des talents
function muUpdateTalentVisibility(checkbox) {
    const talentNum = parseInt(checkbox.getAttribute('data-talent-num'));
    const charId = checkbox.getAttribute('data-char');
    const isChecked = checkbox.checked;

    // Trouver tous les talents de ce personnage
    document.querySelectorAll(`.mu-talent-row[data-char="${charId}"]`).forEach(talentRow => {
        const rowTalentNum = parseInt(talentRow.getAttribute('data-talent-num'));

        // Le talent 1 est toujours visible
        if (rowTalentNum === 1) return;

        // Un talent n'est visible que si tous les précédents sont cochés
        let allPreviousChecked = true;
        for (let i = 1; i < rowTalentNum; i++) {
            const prevCheckbox = document.querySelector(`.mu-talent-row[data-char="${charId}"][data-talent-num="${i}"] .mu-talent-checkbox`);
            if (!prevCheckbox || !prevCheckbox.checked) {
                allPreviousChecked = false;
                break;
            }
        }

        // Mettre à jour la visibilité
        if (allPreviousChecked) {
            talentRow.style.display = 'flex';
            // Activer la checkbox
            const rowCheckbox = talentRow.querySelector('.mu-talent-checkbox');
            if (rowCheckbox) rowCheckbox.disabled = false;
        } else {
            talentRow.style.display = 'none';
            // Désactiver et décocher la checkbox
            const rowCheckbox = talentRow.querySelector('.mu-talent-checkbox');
            if (rowCheckbox) {
                rowCheckbox.disabled = true;
                rowCheckbox.checked = false;
            }
        }
    });
}

// Limiter les champs numériques au max
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.mu-input').forEach(input => {
        const max = parseInt(input.getAttribute('max')) || 999;
        input.addEventListener('change', function() {
            let value = parseInt(this.value) || 0;
            if (value > max) {
                this.value = max;
            }
        });
        input.addEventListener('input', function() {
            let value = parseInt(this.value) || 0;
            if (value > max) {
                this.value = max;
            }
        });
    });
});
</script>