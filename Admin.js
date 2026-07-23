// admin.js — page d'administration (admin.php)
(function () {
    const API = 'admin_api.php';

    const typeSelect   = document.getElementById('admin-type');
    const classSelect  = document.getElementById('admin-class');
    const tidFilter    = document.getElementById('admin-tid-filter');
    const tidSelect    = document.getElementById('admin-tid');
    const ficheBox     = document.getElementById('admin-fiche');
    const levelsBox    = document.getElementById('admin-levels');
    const officerAbilitiesBox = document.getElementById('admin-officer-abilities');
    const activeAbilityLevelsBox  = document.getElementById('admin-active-ability-levels');
    const passiveAbilityLevelsBox = document.getElementById('admin-passive-ability-levels');
    const emptyState   = document.getElementById('admin-empty-state');
    const toast        = document.getElementById('admin-toast');
    const addCharPanel = document.getElementById('admin-add-character');

    let currentEntity = null; // config renvoyée par l'API (fields, id_fields, image...)
    let currentIdRow  = null; // dernière fiche chargée (pour "Annuler")
    let currentTidLabel = null; // nom FR courant (pour "Annuler")
    let allTids = [];
    let troopsLoaded = false;

    function showToast(message, isError) {
        toast.textContent = message;
        toast.className = 'admin-toast show' + (isError ? ' error' : '');
        clearTimeout(showToast._t);
        showToast._t = setTimeout(() => { toast.className = 'admin-toast'; }, 3500);
    }

    async function api(action, opts = {}) {
        const { method = 'GET', params = {}, body = null } = opts;
        let url = `${API}?action=${encodeURIComponent(action)}`;
        if (method === 'GET') {
            for (const k in params) url += `&${k}=${encodeURIComponent(params[k])}`;
        }
        const fetchOpts = { method };
        if (body) fetchOpts.body = body;
        const res = await fetch(url, fetchOpts);
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Erreur inconnue.');
        return data;
    }

    // =========================================================================
    // Filtres en cascade : Type -> Classe/Catégorie -> TID
    // =========================================================================

    async function loadClasses(preselect) {
        classSelect.innerHTML = '<option value="">— Toutes —</option>';
        try {
            const data = await api('list_classes', { params: { type: typeSelect.value } });
            data.classes.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c;
                opt.textContent = c;
                classSelect.appendChild(opt);
            });
            if (preselect) classSelect.value = preselect;
        } catch (e) {
            showToast(e.message, true);
        }
    }

    async function loadTids(preselectTid) {
        tidSelect.innerHTML = '<option value="">Chargement…</option>';
        ficheBox.innerHTML = '';
        levelsBox.innerHTML = '';
        emptyState.style.display = 'block';
        try {
            const data = await api('list_tids', { params: { type: typeSelect.value, class: classSelect.value } });
            allTids = data.tids;
            renderTidOptions(allTids);
            if (preselectTid) {
                tidSelect.value = preselectTid;
                if (tidSelect.value) loadEntityData(preselectTid);
            }
        } catch (e) {
            showToast(e.message, true);
        }
    }

    function renderTidOptions(list) {
        tidSelect.innerHTML = '<option value="">— Choisir un TID —</option>' +
            list.map(t => `<option value="${escapeAttr(t.tid)}">${escapeHtml(t.label)} (${escapeHtml(t.tid)})</option>`).join('');
    }

    tidFilter.addEventListener('input', () => {
        const q = tidFilter.value.trim().toLowerCase();
        const filtered = !q ? allTids : allTids.filter(t =>
            t.tid.toLowerCase().includes(q) || (t.label || '').toLowerCase().includes(q));
        renderTidOptions(filtered);
    });

    typeSelect.addEventListener('change', () => {
        toggleAddCharacterPanel();
        loadClasses().then(() => loadTids());
    });
    classSelect.addEventListener('change', () => loadTids());
    tidSelect.addEventListener('change', () => {
        if (tidSelect.value) loadEntityData(tidSelect.value);
        else { ficheBox.innerHTML = ''; levelsBox.innerHTML = ''; emptyState.style.display = 'block'; }
    });

    function toggleAddCharacterPanel() {
        const isCharacter = typeSelect.value === 'character';
        addCharPanel.style.display = isCharacter ? 'block' : 'none';
        if (isCharacter && !troopsLoaded) loadTroopsForAddForm();
    }

    // =========================================================================
    // Chargement fiche + niveaux pour un TID
    // =========================================================================

    async function loadEntityData(tid) {
        try {
            const data = await api('get_data', { params: { type: typeSelect.value, tid } });
            currentEntity = data.entity;
            currentIdRow  = data.id_row;
            currentTidLabel = data.tid_label;
            emptyState.style.display = 'none';
            renderFiche(tid, data.id_row, data.tid_label);
            renderLevels(tid, data.levels);

            const isOfficer = typeSelect.value === 'character' && data.id_row && data.id_row.Class === 'Officier';
            if (isOfficer) loadOfficerAbilities(tid);
            else {
                officerAbilitiesBox.innerHTML = '';
                activeAbilityLevelsBox.innerHTML = '';
                passiveAbilityLevelsBox.innerHTML = '';
            }
        } catch (e) {
            showToast(e.message, true);
        }
    }

    // =========================================================================
    // Fiche (table de définition) — lecture seule tant qu'on n'a pas cliqué "Modifier"
    // =========================================================================

    function renderFiche(tid, idRow, tidLabel) {
        const fields = currentEntity.id_fields || {};
        const img = currentEntity.image;
        const label = tidLabel || tid;

        let html = `<div class="admin-card">
            <div class="admin-card-header-row">
                <h3>Fiche — ${escapeHtml(label)} <span style="font-weight:400; opacity:.6; font-size:13px;">(<code>${escapeHtml(tid)}</code>)</span></h3>
                <span>
                    <button type="button" class="admin-btn admin-btn-edit" id="admin-fiche-edit-btn">✏️ Modifier</button>
                    <button type="button" class="admin-btn admin-btn-primary" id="admin-fiche-save-btn" style="display:none;">💾 Enregistrer</button>
                    <button type="button" class="admin-btn admin-btn-secondary" id="admin-fiche-cancel-btn" style="display:none;">✖ Annuler</button>
                </span>
            </div>
            <div class="admin-fiche-grid">`;

        for (const col in fields) {
            const def = fields[col];
            const val = idRow ? (idRow[col] ?? '') : '';
            html += `<label class="admin-field">
                <span>${escapeHtml(def.label)}</span>
                <input type="${def.type === 'int' ? 'number' : 'text'}" data-col="${escapeAttr(col)}" value="${escapeAttr(val)}" disabled>
            </label>`;
        }

        html += `</div>`;

        if (img.scope === 'id') {
            html += `<div class="admin-actions-row">
                <span class="admin-upload-inline">
                    <label class="admin-btn admin-btn-secondary">
                        🖼️ Uploader l'image
                        <input type="file" accept="image/*" id="admin-fiche-image" style="display:none;">
                    </label>
                    <span class="admin-upload-hint">Chemin attendu : ${escapeHtml(img.dir)}/${img.subdir_from ? '{' + img.subdir_from + '}/' : ''}{${img.name_field}}.${img.ext}</span>
                </span>
            </div>`;
        }

        html += `</div>`;
        ficheBox.innerHTML = html;

        const editBtn   = document.getElementById('admin-fiche-edit-btn');
        const saveBtn   = document.getElementById('admin-fiche-save-btn');
        const cancelBtn = document.getElementById('admin-fiche-cancel-btn');
        const inputs    = () => ficheBox.querySelectorAll('[data-col]');

        editBtn.addEventListener('click', () => {
            inputs().forEach(i => i.disabled = false);
            editBtn.style.display = 'none';
            saveBtn.style.display = '';
            cancelBtn.style.display = '';
        });
        cancelBtn.addEventListener('click', () => renderFiche(tid, currentIdRow, currentTidLabel));
        saveBtn.addEventListener('click', () => saveFiche(tid));

        if (img.scope === 'id') {
            document.getElementById('admin-fiche-image').addEventListener('change', (e) => {
                if (e.target.files[0]) uploadImage(tid, null, e.target.files[0]);
            });
        }
    }

    async function saveFiche(tid) {
        const data = {};
        ficheBox.querySelectorAll('[data-col]').forEach(input => {
            data[input.dataset.col] = input.value;
        });
        try {
            await api('save_id_row', {
                method: 'POST',
                body: buildForm({ type: typeSelect.value, tid, data: JSON.stringify(data) }),
            });
            showToast('Fiche enregistrée.');
            loadEntityData(tid);
        } catch (e) {
            showToast(e.message, true);
        }
    }

    // =========================================================================
    // Tableau des niveaux — chaque ligne en lecture seule tant qu'on n'a pas
    // cliqué sur "✏️" (les nouvelles lignes ajoutées démarrent en édition).
    // =========================================================================

    function renderLevels(tid, levels) {
        const fields = currentEntity.fields;
        const levelCol = currentEntity.level_col;
        const img = currentEntity.image;
        const cols = Object.keys(fields);

        let html = `<div class="admin-card">
            <div class="admin-card-header-row">
                <h3>Niveaux</h3>
                <button type="button" class="admin-btn admin-btn-primary" id="admin-add-level">➕ Ajouter un niveau</button>
            </div>
            <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead><tr>`;
        cols.forEach(c => html += `<th>${escapeHtml(fields[c].label)}</th>`);
        if (img.scope === 'level') html += `<th>Image</th>`;
        html += `<th>Actions</th></tr></thead><tbody id="admin-levels-tbody">`;

        levels.forEach(row => {
            html += renderLevelRow(row, cols, fields, img, row[levelCol]);
        });

        html += `</tbody></table></div></div>`;
        levelsBox.innerHTML = html;

        document.getElementById('admin-add-level').addEventListener('click', () => {
            const tbody = document.getElementById('admin-levels-tbody');
            const tr = document.createElement('tr');
            tr.innerHTML = renderLevelRowInner({}, cols, fields, img, null, true);
            tbody.appendChild(tr);
            bindRowEvents(tr, tid, cols, img, null, true, true);
            tr.querySelector('input:not(:disabled)')?.focus();
        });

        levelsBox.querySelectorAll('tr[data-level]').forEach(tr => {
            bindRowEvents(tr, tid, cols, img, tr.dataset.level, false, false);
        });
    }

    function renderLevelRow(row, cols, fields, img, levelValue) {
        return `<tr data-level="${escapeAttr(levelValue)}">${renderLevelRowInner(row, cols, fields, img, levelValue, false)}</tr>`;
    }

    function renderLevelRowInner(row, cols, fields, img, levelValue, isNew) {
        let html = '';
        cols.forEach(c => {
            const def = fields[c];
            const val = row[c] ?? '';
            html += `<td><input type="${def.type === 'int' ? 'number' : 'text'}" data-col="${escapeAttr(c)}" value="${escapeAttr(val)}" ${isNew ? '' : 'disabled'}></td>`;
        });
        if (img.scope === 'level') {
            html += `<td>
                <label class="admin-btn admin-btn-mini">🖼️
                    <input type="file" accept="image/*" class="admin-row-image" style="display:none;" ${isNew ? 'disabled title="Enregistre d\'abord le niveau"' : ''}>
                </label>
            </td>`;
        }
        html += `<td class="admin-row-actions">
            ${isNew ? '' : '<button type="button" class="admin-btn admin-btn-mini admin-btn-edit admin-row-edit">✏️</button>'}
            <button type="button" class="admin-btn admin-btn-mini admin-row-save" ${isNew ? '' : 'style="display:none;"'}>💾</button>
            ${isNew
                ? ''
                : '<button type="button" class="admin-btn admin-btn-mini admin-btn-secondary admin-row-cancel" style="display:none;">✖</button><button type="button" class="admin-btn admin-btn-mini admin-btn-danger admin-row-delete">🗑️</button>'}
        </td>`;
        return html;
    }

    function bindRowEvents(tr, tid, cols, img, originalLevel, isNew, startInEdit) {
        const editBtn   = tr.querySelector('.admin-row-edit');
        const saveBtn   = tr.querySelector('.admin-row-save');
        const cancelBtn = tr.querySelector('.admin-row-cancel');
        const delBtn    = tr.querySelector('.admin-row-delete');
        const rowInputs = () => tr.querySelectorAll('[data-col]');

        if (editBtn) {
            editBtn.addEventListener('click', () => {
                rowInputs().forEach(i => i.disabled = false);
                editBtn.style.display = 'none';
                saveBtn.style.display = '';
                if (cancelBtn) cancelBtn.style.display = '';
                rowInputs()[0]?.focus();
            });
        }
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => loadEntityData(tid)); // simple : on recharge tout
        }
        saveBtn.addEventListener('click', () => saveRow(tr, tid, cols, originalLevel, isNew));
        if (delBtn) delBtn.addEventListener('click', () => deleteRow(tr, tid, originalLevel));

        const imgInput = tr.querySelector('.admin-row-image');
        if (imgInput) {
            imgInput.addEventListener('change', (e) => {
                if (e.target.files[0]) uploadImage(tid, tr.dataset.level, e.target.files[0]);
            });
        }
    }

    async function saveRow(tr, tid, cols, originalLevel, isNew) {
        const data = {};
        tr.querySelectorAll('[data-col]').forEach(input => { data[input.dataset.col] = input.value; });
        try {
            await api('save_level', {
                method: 'POST',
                body: buildForm({
                    type: typeSelect.value,
                    tid,
                    data: JSON.stringify(data),
                    original_level: isNew ? '' : originalLevel,
                }),
            });
            showToast('Niveau enregistré.');
            loadEntityData(tid); // on recharge pour re-trier / rafraîchir proprement
        } catch (e) {
            showToast(e.message, true);
        }
    }

    async function deleteRow(tr, tid, level) {
        if (!confirm(`Supprimer le niveau ${level} ? Cette action est irréversible.`)) return;
        try {
            await api('delete_level', {
                method: 'POST',
                body: buildForm({ type: typeSelect.value, tid, level }),
            });
            showToast('Niveau supprimé.');
            tr.remove();
        } catch (e) {
            showToast(e.message, true);
        }
    }

    async function uploadImage(tid, level, file) {
        const form = new FormData();
        form.append('action', 'upload_image');
        form.append('type', typeSelect.value);
        form.append('tid', tid);
        if (level !== null) form.append('level', level);
        form.append('image', file);
        try {
            const res = await fetch(API, { method: 'POST', body: form });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);
            showToast('Image envoyée : ' + data.path);
        } catch (e) {
            showToast(e.message, true);
        }
    }

    // =========================================================================
    // Tableau des capacités/talents d'un officier (TID + nom FR + icône) —
    // pour visualiser d'un coup d'œil qui est actif/passif/talent N et éviter
    // de confondre les capacités qui se ressemblent. Le Talent 3 (générique,
    // partagé par tous les officiers) est affiché en lecture seule.
    // =========================================================================

    async function loadOfficerAbilities(tid) {
        officerAbilitiesBox.innerHTML = '<div class="admin-card">Chargement des capacités…</div>';
        try {
            const data = await api('get_officer_abilities', { params: { tid } });
            renderOfficerAbilities(data.abilities);

            const activeEntry  = data.abilities.find(a => a.slot === 'Capacité active');
            const passiveEntry = data.abilities.find(a => a.slot === 'Capacité passive');
            loadAbilityLevelsTable(activeAbilityLevelsBox, '⚡ Niveaux — Capacité active', activeEntry ? activeEntry.tid : null);
            loadAbilityLevelsTable(passiveAbilityLevelsBox, '🛡️ Niveaux — Capacité passive', passiveEntry ? passiveEntry.tid : null);
        } catch (e) {
            officerAbilitiesBox.innerHTML = '';
            activeAbilityLevelsBox.innerHTML = '';
            passiveAbilityLevelsBox.innerHTML = '';
            showToast(e.message, true);
        }
    }

    function renderOfficerAbilities(abilities) {
        if (!abilities.length) {
            officerAbilitiesBox.innerHTML = '';
            return;
        }

        let html = `<div class="admin-card">
            <h3>🎖️ Talents &amp; capacités</h3>
            <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead><tr><th>Emplacement</th><th>TID</th><th>Nom (FR)</th><th>Icône (IconExportName)</th><th>Actions</th></tr></thead>
                <tbody>`;

        abilities.forEach(a => {
            const locked = !a.editable;
            html += `<tr data-tid="${escapeAttr(a.tid)}">
                <td>${escapeHtml(a.slot)}</td>
                <td><code>${escapeHtml(a.tid)}</code></td>
                <td><input type="text" data-col="label" value="${escapeAttr(a.label)}" disabled></td>
                <td><input type="text" data-col="icon" value="${escapeAttr(a.icon)}" disabled></td>
                <td class="admin-row-actions">
                    ${locked
                        ? '<span class="admin-upload-hint" title="Générique et partagé entre tous les officiers : non modifiable ici.">🔒 partagé</span>'
                        : `<button type="button" class="admin-btn admin-btn-mini admin-btn-edit admin-row-edit">✏️</button>
                           <button type="button" class="admin-btn admin-btn-mini admin-row-save" style="display:none;">💾</button>
                           <button type="button" class="admin-btn admin-btn-mini admin-btn-secondary admin-row-cancel" style="display:none;">✖</button>`
                    }
                </td>
            </tr>`;
        });

        html += `</tbody></table></div></div>`;
        officerAbilitiesBox.innerHTML = html;

        officerAbilitiesBox.querySelectorAll('tr[data-tid]').forEach(tr => {
            const editBtn   = tr.querySelector('.admin-row-edit');
            const saveBtn   = tr.querySelector('.admin-row-save');
            const cancelBtn = tr.querySelector('.admin-row-cancel');
            if (!editBtn) return; // ligne verrouillée (talent 3 générique)

            const rowInputs = () => tr.querySelectorAll('[data-col]');
            editBtn.addEventListener('click', () => {
                rowInputs().forEach(i => i.disabled = false);
                editBtn.style.display = 'none';
                saveBtn.style.display = '';
                cancelBtn.style.display = '';
                rowInputs()[0]?.focus();
            });
            cancelBtn.addEventListener('click', () => loadOfficerAbilities(tidSelect.value));
            saveBtn.addEventListener('click', () => saveOfficerAbility(tr));
        });
    }

    async function saveOfficerAbility(tr) {
        const ability_tid = tr.dataset.tid;
        const label = tr.querySelector('[data-col="label"]').value;
        const icon  = tr.querySelector('[data-col="icon"]').value;
        try {
            await api('save_officer_ability', {
                method: 'POST',
                body: buildForm({ ability_tid, label, icon }),
            });
            showToast('Capacité enregistrée.');
            loadOfficerAbilities(tidSelect.value);
        } catch (e) {
            showToast(e.message, true);
        }
    }

    // =========================================================================
    // Niveaux d'une capacité active ou passive (table officer_abilities) —
    // ajout / modif / suppression, sur le même principe que le tableau
    // "Niveaux" générique, mais câblé sur le TID de la capacité elle-même
    // (et non celui de l'officier).
    // =========================================================================

    async function loadAbilityLevelsTable(box, title, abilityTid) {
        if (!abilityTid) { box.innerHTML = ''; return; }
        box.innerHTML = '<div class="admin-card">Chargement…</div>';
        try {
            const data = await api('get_officer_ability_levels', { params: { ability_tid: abilityTid } });
            renderAbilityLevelsTable(box, title, abilityTid, data.fields, data.levels);
        } catch (e) {
            box.innerHTML = '';
            showToast(e.message, true);
        }
    }

    function renderAbilityLevelsTable(box, title, abilityTid, fields, levels) {
        const cols = Object.keys(fields);

        let html = `<div class="admin-card">
            <div class="admin-card-header-row">
                <h3>${escapeHtml(title)} <span style="font-weight:400; opacity:.6; font-size:13px;">(<code>${escapeHtml(abilityTid)}</code>)</span></h3>
                <button type="button" class="admin-btn admin-btn-primary admin-ability-add-level">➕ Ajouter un niveau</button>
            </div>
            <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead><tr>`;
        cols.forEach(c => html += `<th>${escapeHtml(fields[c].label)}</th>`);
        html += `<th>Actions</th></tr></thead><tbody class="admin-ability-levels-tbody">`;

        levels.forEach(row => {
            html += renderAbilityLevelRow(row, cols, fields, row['Niveau']);
        });

        html += `</tbody></table></div></div>`;
        box.innerHTML = html;

        const tbody = box.querySelector('.admin-ability-levels-tbody');
        box.querySelector('.admin-ability-add-level').addEventListener('click', () => {
            const tr = document.createElement('tr');
            tr.innerHTML = renderAbilityLevelRowInner({}, cols, fields, true);
            tbody.appendChild(tr);
            bindAbilityRowEvents(tr, abilityTid, null, true, box, title);
            tr.querySelector('input:not(:disabled)')?.focus();
        });

        box.querySelectorAll('tr[data-level]').forEach(tr => {
            bindAbilityRowEvents(tr, abilityTid, tr.dataset.level, false, box, title);
        });
    }

    function renderAbilityLevelRow(row, cols, fields, levelValue) {
        return `<tr data-level="${escapeAttr(levelValue)}">${renderAbilityLevelRowInner(row, cols, fields, false)}</tr>`;
    }

    function renderAbilityLevelRowInner(row, cols, fields, isNew) {
        let html = '';
        cols.forEach(c => {
            const def = fields[c];
            const val = row[c] ?? '';
            html += `<td><input type="${def.type === 'int' ? 'number' : 'text'}" data-col="${escapeAttr(c)}" value="${escapeAttr(val)}" ${isNew ? '' : 'disabled'}></td>`;
        });
        html += `<td class="admin-row-actions">
            ${isNew ? '' : '<button type="button" class="admin-btn admin-btn-mini admin-btn-edit admin-row-edit">✏️</button>'}
            <button type="button" class="admin-btn admin-btn-mini admin-row-save" ${isNew ? '' : 'style="display:none;"'}>💾</button>
            ${isNew
                ? ''
                : '<button type="button" class="admin-btn admin-btn-mini admin-btn-secondary admin-row-cancel" style="display:none;">✖</button><button type="button" class="admin-btn admin-btn-mini admin-btn-danger admin-row-delete">🗑️</button>'}
        </td>`;
        return html;
    }

    function bindAbilityRowEvents(tr, abilityTid, originalLevel, isNew, box, title) {
        const editBtn   = tr.querySelector('.admin-row-edit');
        const saveBtn   = tr.querySelector('.admin-row-save');
        const cancelBtn = tr.querySelector('.admin-row-cancel');
        const delBtn    = tr.querySelector('.admin-row-delete');
        const rowInputs = () => tr.querySelectorAll('[data-col]');

        if (editBtn) {
            editBtn.addEventListener('click', () => {
                rowInputs().forEach(i => i.disabled = false);
                editBtn.style.display = 'none';
                saveBtn.style.display = '';
                if (cancelBtn) cancelBtn.style.display = '';
                rowInputs()[0]?.focus();
            });
        }
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => loadAbilityLevelsTable(box, title, abilityTid));
        }
        saveBtn.addEventListener('click', () => saveAbilityLevelRow(tr, abilityTid, originalLevel, isNew, box, title));
        if (delBtn) delBtn.addEventListener('click', () => deleteAbilityLevelRow(tr, abilityTid, originalLevel, box, title));
    }

    async function saveAbilityLevelRow(tr, abilityTid, originalLevel, isNew, box, title) {
        const data = {};
        tr.querySelectorAll('[data-col]').forEach(input => { data[input.dataset.col] = input.value; });
        try {
            await api('save_officer_ability_level', {
                method: 'POST',
                body: buildForm({
                    ability_tid: abilityTid,
                    data: JSON.stringify(data),
                    original_level: isNew ? '' : originalLevel,
                }),
            });
            showToast('Niveau enregistré.');
            loadAbilityLevelsTable(box, title, abilityTid);
        } catch (e) {
            showToast(e.message, true);
        }
    }

    async function deleteAbilityLevelRow(tr, abilityTid, level, box, title) {
        if (!confirm(`Supprimer le niveau ${level} ? Cette action est irréversible.`)) return;
        try {
            await api('delete_officer_ability_level', {
                method: 'POST',
                body: buildForm({ ability_tid: abilityTid, level }),
            });
            showToast('Niveau supprimé.');
            tr.remove();
        } catch (e) {
            showToast(e.message, true);
        }
    }

    // =========================================================================
    // Ajout d'un nouveau personnage (characterid [+ officer_talents])
    // =========================================================================

    const newCharClass         = document.getElementById('new-char-class');
    const newCharHq            = document.getElementById('new-char-hq');
    const newCharOfficerWrap   = document.getElementById('new-char-officer-troop-wrap');
    const newCharOfficerSelect = document.getElementById('new-char-officer-troop');
    const newCharRankWrap      = document.getElementById('new-char-rank-wrap');
    const newCharRankSelect    = document.getElementById('new-char-rank');
    const newCharAbilityWrap   = document.getElementById('new-char-ability-wrap');
    const newCharAbilitySelect = document.getElementById('new-char-ability');
    const newCharTidInput      = document.getElementById('new-char-tid');
    const newCharActiveIconWrap = document.getElementById('new-char-active-icon-wrap');

    async function loadTroopsForAddForm() {
        try {
            const data = await api('list_troops');
            newCharOfficerSelect.innerHTML = '<option value="">— Choisir une troupe —</option>' +
                data.troops.map(t => `<option value="${escapeAttr(t.tid)}">${escapeHtml(t.label)}</option>`).join('');
            troopsLoaded = true;
        } catch (e) {
            showToast(e.message, true);
        }
    }

    function refreshAddCharacterFieldsVisibility() {
        const isOfficer = newCharClass.value === 'Officier';
        newCharOfficerWrap.style.display = isOfficer ? '' : 'none';
        newCharRankWrap.style.display    = isOfficer ? '' : 'none';
        newCharAbilityWrap.style.display = (isOfficer && newCharRankSelect.value === 'Sergent') ? '' : 'none';

        // Règle métier : un officier se débloque toujours au QG 7.
        newCharHq.disabled = isOfficer;
        if (isOfficer) newCharHq.value = 7;

        // La capacité active a besoin d'un IconExportName saisi à la main, SAUF
        // pour les officiers CYBER (icône générique icon_squad_command insérée
        // automatiquement côté serveur). Lieutenant a toujours une capa active ;
        // Sergent seulement si "Actif" est sélectionné.
        const isCyber = /CYBER/i.test(newCharTidInput.value.trim());
        const hasActive = isOfficer && (
            newCharRankSelect.value === 'Lieutenant' ||
            (newCharRankSelect.value === 'Sergent' && newCharAbilitySelect.value === 'Active')
        );
        newCharActiveIconWrap.style.display = (hasActive && !isCyber) ? '' : 'none';
    }

    newCharClass.addEventListener('change', refreshAddCharacterFieldsVisibility);
    newCharRankSelect.addEventListener('change', refreshAddCharacterFieldsVisibility);
    newCharAbilitySelect.addEventListener('change', refreshAddCharacterFieldsVisibility);
    newCharTidInput.addEventListener('input', refreshAddCharacterFieldsVisibility);

    document.getElementById('new-char-submit').addEventListener('click', async () => {
        const payload = {
            tid: document.getElementById('new-char-tid').value.trim(),
            class: newCharClass.value,
            hq_unlock: newCharHq.value,
            icon: document.getElementById('new-char-icon').value.trim(),
            officer_troop: newCharOfficerSelect.value,
            officer_rank: newCharRankSelect.value,
            officer_ability: newCharAbilitySelect.value,
            active_icon: document.getElementById('new-char-active-icon').value.trim(),
        };
        try {
            const data = await api('add_character', { method: 'POST', body: buildForm(payload) });
            // On reste sur la page admin, mais avec un vrai rechargement — et le
            // nouveau TID pré-sélectionné pour confirmer visuellement l'ajout.
            const params = new URLSearchParams({ type: 'character', class: payload.class, tid: data.tid });
            window.location.href = '/admin?' + params.toString();
        } catch (e) {
            showToast(e.message, true);
        }
    });

    // =========================================================================
    // Événements — carte "admin-events" (dropdown + calendrier + datatable)
    // =========================================================================

    // IDs spéciaux (voir demande) : 5 = GBA (sort TempSpell), 12 = Troopmania (2 troupes).
    const EVENT_ID_GBA        = '5';
    const EVENT_ID_TROOPMANIA = '12';

    const eventSelect    = document.getElementById('event-select');
    const eventDebut     = document.getElementById('event-debut');
    const eventEnd       = document.getElementById('event-end');
    const eventSubmit    = document.getElementById('event-submit');
    const eventTableBody = document.getElementById('event-table-body');

    const eventGbaWrap    = document.getElementById('event-gba-wrap');
    const eventGbaSelect  = document.getElementById('event-gba');
    const eventTroop1Wrap   = document.getElementById('event-troop1-wrap');
    const eventTroop1Select = document.getElementById('event-troop1');
    const eventTroop2Wrap   = document.getElementById('event-troop2-wrap');
    const eventTroop2Select = document.getElementById('event-troop2');

    // Caches pour ne charger les listes de personnages qu'une seule fois.
    let tempSpellOptionsCache = null; // characterid.Class = 'TempSpell'
    let troopOptionsCache     = null; // characterid.Class = 'Troupe'

    async function getCharacterOptions(cssClass) {
        if (cssClass === 'TempSpell') {
            if (!tempSpellOptionsCache) {
                const data = await api('list_tids', { params: { type: 'characterid', class: 'TempSpell' } });
                tempSpellOptionsCache = data.tids;
            }
            return tempSpellOptionsCache;
        }
        if (!troopOptionsCache) {
            const data = await api('list_tids', { params: { type: 'characterid', class: 'Troupe' } });
            troopOptionsCache = data.tids;
        }
        return troopOptionsCache;
    }

    function fillCharacterSelect(selectEl, options, placeholder, selectedTid) {
        selectEl.innerHTML = `<option value="">${placeholder}</option>` +
            options.map(o => `<option value="${escapeAttr(o.tid)}">${escapeHtml(o.label)}</option>`).join('');
        if (selectedTid) selectEl.value = selectedTid;
    }

    // Affiche/cache les champs Bonus (GBA ou Troopmania) selon l'événement choisi
    // dans le formulaire d'ajout, et pré-charge les listes déroulantes correspondantes.
    async function refreshEventBonusFields() {
        const idEvent = eventSelect.value;
        eventGbaWrap.style.display    = 'none';
        eventTroop1Wrap.style.display = 'none';
        eventTroop2Wrap.style.display = 'none';

        if (idEvent === EVENT_ID_GBA) {
            eventGbaWrap.style.display = '';
            try {
                fillCharacterSelect(eventGbaSelect, await getCharacterOptions('TempSpell'), '— Aucun —');
            } catch (e) { showToast(e.message, true); }
        } else if (idEvent === EVENT_ID_TROOPMANIA) {
            eventTroop1Wrap.style.display = '';
            eventTroop2Wrap.style.display = '';
            try {
                const troops = await getCharacterOptions('Troupe');
                fillCharacterSelect(eventTroop1Select, troops, '— Aucune —');
                fillCharacterSelect(eventTroop2Select, troops, '— Aucune —');
            } catch (e) { showToast(e.message, true); }
        }
    }

    eventSelect.addEventListener('change', refreshEventBonusFields);

    // MySQL "YYYY-MM-DD HH:MM:SS" -> valeur attendue par <input type="datetime-local">
    function toDatetimeLocal(mysqlDatetime) {
        if (!mysqlDatetime) return '';
        return mysqlDatetime.replace(' ', 'T').slice(0, 16);
    }

    async function loadEventDefinitions() {
        eventSelect.innerHTML = '<option value="">Chargement…</option>';
        try {
            const data = await api('list_event_definitions');
            eventSelect.innerHTML = '';
            if (!data.events.length) {
                eventSelect.innerHTML = '<option value="">— Aucun événement défini —</option>';
                return;
            }
            eventSelect.innerHTML = '<option value="">— Choisir un événement —</option>';
            data.events.forEach(ev => {
                const opt = document.createElement('option');
                opt.value = ev.id;
                opt.textContent = ev.label;
                eventSelect.appendChild(opt);
            });
        } catch (e) {
            showToast(e.message, true);
        }
    }

    async function loadScheduledEvents() {
        eventTableBody.innerHTML = '<tr><td colspan="6" class="admin-empty-state">Chargement…</td></tr>';
        try {
            const data = await api('list_scheduled_events');
            renderEventTable(data.events);
        } catch (e) {
            eventTableBody.innerHTML = '<tr><td colspan="6" class="admin-empty-state">Erreur de chargement.</td></tr>';
            showToast(e.message, true);
        }
    }

    // Construit le contenu de la cellule "Bonus" pour une ligne du datatable :
    // rien pour les événements normaux, un select TempSpell pour l'event GBA (5),
    // deux select Troupe pour l'event Troopmania (12).
    async function buildBonusCell(td, ev) {
        const idEvent = String(ev.id);
        if (idEvent === EVENT_ID_GBA) {
            td.innerHTML = `<select data-col="gba" class="event-bonus-select"><option value="">— Aucun —</option></select>`;
            const sel = td.querySelector('select');
            try {
                fillCharacterSelect(sel, await getCharacterOptions('TempSpell'), '— Aucun —', ev.gba);
            } catch (e) { showToast(e.message, true); }
        } else if (idEvent === EVENT_ID_TROOPMANIA) {
            td.innerHTML = `
                <select data-col="troop1" class="event-bonus-select" style="margin-bottom:4px;"><option value="">— Troupe 1 —</option></select>
                <select data-col="troop2" class="event-bonus-select"><option value="">— Troupe 2 —</option></select>
            `;
            const [sel1, sel2] = td.querySelectorAll('select');
            try {
                const troops = await getCharacterOptions('Troupe');
                fillCharacterSelect(sel1, troops, '— Troupe 1 —', ev.troop1);
                fillCharacterSelect(sel2, troops, '— Troupe 2 —', ev.troop2);
            } catch (e) { showToast(e.message, true); }
        } else {
            td.innerHTML = '—';
        }
    }

    function renderEventTable(events) {
        eventTableBody.innerHTML = '';
        if (!events.length) {
            eventTableBody.innerHTML = '<tr><td colspan="6" class="admin-empty-state">Aucun événement programmé pour le moment.</td></tr>';
            return;
        }
        events.forEach(ev => {
            const tr = document.createElement('tr');
            tr.dataset.id = ev.id;
            tr.innerHTML = `
                <td><img class="event-thumb" src="images/event/${escapeAttr(ev.ExportName)}.png" onerror="this.style.visibility='hidden'"></td>
                <td>${escapeHtml(ev.label)}</td>
                <td><input type="datetime-local" data-col="debut" value="${toDatetimeLocal(ev.debut)}"></td>
                <td><input type="datetime-local" data-col="end" value="${toDatetimeLocal(ev.end)}" placeholder="Pas de fin"></td>
                <td class="event-bonus-cell">…</td>
                <td class="admin-row-actions">
                    <button type="button" class="admin-btn admin-btn-mini event-row-save">💾</button>
                    <button type="button" class="admin-btn admin-btn-mini admin-btn-danger event-row-delete">🗑️</button>
                </td>
            `;
            tr.querySelector('.event-row-save').addEventListener('click', () => saveEventRow(tr, ev.id));
            tr.querySelector('.event-row-delete').addEventListener('click', () => deleteEventRow(tr, ev.id, ev.label));
            eventTableBody.appendChild(tr);
            buildBonusCell(tr.querySelector('.event-bonus-cell'), ev);
        });
    }

    async function saveEventRow(tr, idEvent) {
        const debut  = tr.querySelector('[data-col="debut"]').value;
        const end    = tr.querySelector('[data-col="end"]').value;
        const gba    = tr.querySelector('[data-col="gba"]')?.value    || '';
        const troop1 = tr.querySelector('[data-col="troop1"]')?.value || '';
        const troop2 = tr.querySelector('[data-col="troop2"]')?.value || '';
        try {
            await api('save_event_schedule', {
                method: 'POST',
                body: buildForm({ id_event: idEvent, debut, end, gba, troop1, troop2 }),
            });
            showToast('Événement mis à jour.');
            loadScheduledEvents();
        } catch (e) {
            showToast(e.message, true);
        }
    }

    async function deleteEventRow(tr, idEvent, label) {
        if (!confirm(`Retirer la programmation de « ${label} » ?`)) return;
        try {
            await api('delete_event_schedule', {
                method: 'POST',
                body: buildForm({ id_event: idEvent }),
            });
            showToast('Programmation supprimée.');
            tr.remove();
        } catch (e) {
            showToast(e.message, true);
        }
    }

    eventSubmit.addEventListener('click', async () => {
        const idEvent = eventSelect.value;
        if (!idEvent) { showToast('Choisis un événement.', true); return; }
        if (!eventDebut.value) { showToast('Renseigne au moins la date de début.', true); return; }
        try {
            await api('save_event_schedule', {
                method: 'POST',
                body: buildForm({
                    id_event: idEvent,
                    debut: eventDebut.value,
                    end: eventEnd.value, // peut être vide : pas de date de fin
                    gba: idEvent === EVENT_ID_GBA ? eventGbaSelect.value : '',
                    troop1: idEvent === EVENT_ID_TROOPMANIA ? eventTroop1Select.value : '',
                    troop2: idEvent === EVENT_ID_TROOPMANIA ? eventTroop2Select.value : '',
                }),
            });
            showToast('Événement programmé.');
            eventSelect.value = '';
            eventDebut.value = '';
            eventEnd.value = '';
            refreshEventBonusFields();
            loadScheduledEvents();
        } catch (e) {
            showToast(e.message, true);
        }
    });

    // =========================================================================
    // Helpers
    // =========================================================================

    function buildForm(obj) {
        const fd = new FormData();
        for (const k in obj) fd.append(k, obj[k]);
        return fd;
    }
    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    }
    function escapeAttr(str) { return escapeHtml(str); }

    // =========================================================================
    // Init — relit d'éventuels paramètres d'URL (?type=&class=&tid=) pour
    // resélectionner automatiquement, notamment après l'ajout d'un personnage.
    // =========================================================================

    async function init() {
        const params = new URLSearchParams(window.location.search);
        const initialType  = params.get('type');
        const initialClass = params.get('class') || '';
        const initialTid   = params.get('tid') || '';

        if (initialType && [...typeSelect.options].some(o => o.value === initialType)) {
            typeSelect.value = initialType;
        }
        toggleAddCharacterPanel();
        await loadClasses(initialClass);
        await loadTids(initialTid);
        refreshAddCharacterFieldsVisibility();

        loadEventDefinitions();
        loadScheduledEvents();
    }

    init();
})();