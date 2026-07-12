window.showTab = function(tabId) {
    console.log("Tentative d'affichage de : " + tabId); // Affiche dans la console si ça fonctionne

    const targetTab = document.getElementById(tabId);
    
    // Si l'ID est introuvable, on affiche une erreur propre sans tout bloquer
    if (!targetTab) {
        console.error("ATTENTION : L'élément avec l'ID '" + tabId + "' n'existe pas dans la page !");
        return; 
    }

    // Masquer tout
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'none';
        tab.classList.remove('active');
    });

    // Afficher la cible
    targetTab.style.display = 'block';
    targetTab.classList.add('active');

    // Met à jour l'URL (dashboard.php#Categorie-SousCategorie) : c'est ce qui rend
    // chaque onglet partageable en lien direct et navigable au bouton précédent/suivant.
    if (window.location.hash.substring(1) !== tabId) {
        window.location.hash = tabId;
    }

    // Mémorise aussi l'onglet actif en localStorage, en filet de sécurité si jamais
    // la page est rouverte sans hash dans l'URL (ex: lien direct vers dashboard.php seul).
    try {
        localStorage.setItem('activeTab', tabId);
    } catch (e) {
        console.warn("localStorage indisponible :", e);
    }

    // Ouvre automatiquement le sous-menu (Bâtiments, Armée, Gravures...) contenant cet onglet
    const trigger = document.querySelector(`.nav-button[onclick*="showTab('${tabId}')"]`);
    if (trigger) {
        const submenu = trigger.closest('.submenu');
        if (submenu) submenu.style.display = 'block';
        const group = trigger.closest('.menu-group');
        if (group) group.classList.add('open');
    }

    // --- Mise à jour de l'état visuel "actif" dans la sidebar ---
    document.querySelectorAll('.menu-header').forEach(el => {
        el.classList.remove('active');
        el.classList.remove('active-parent');
    });
    document.querySelectorAll('.nav-button').forEach(el => el.classList.remove('active-tab'));

    if (trigger) {
        trigger.classList.add('active-tab');
        const parentHeader = trigger.closest('.menu-group')?.querySelector('.menu-header');
        if (parentHeader) parentHeader.classList.add('active-parent');
    }
    const directHeader = document.querySelector(`.menu-header.dashboard-btn[onclick*="showTab('${tabId}')"]`);
    if (directHeader) directHeader.classList.add('active');
    const overviewHeader = document.querySelector(`.menu-header[onclick*="openCategoryTab(this, '${tabId}')"]`);
    if (overviewHeader) overviewHeader.classList.add('active-parent');
};

// Navigation clavier/souris précédent-suivant du navigateur, ou édition manuelle de l'URL :
// on réagit à tout changement de hash pour rester synchronisé avec l'onglet affiché.
window.addEventListener('hashchange', function() {
    const tabFromHash = window.location.hash.substring(1);
    if (tabFromHash && document.getElementById(tabFromHash)) {
        showTab(tabFromHash);
    }
});

// Fonction pour le changement visuel des bâtiments et calculs associés
window.calculateRemaining = function(element) {
    const row = element.closest('tr');
    if (!row) return;

    // Récupération sécurisée du TID (au cas où il soit sur un autre champ)
    let tid = element.getAttribute('data-tid');
    if (!tid) {
        const tempInput = row.querySelector('[data-tid]');
        if (tempInput) tid = tempInput.getAttribute('data-tid');
    }

    // 1. LE FIX EST ICI : On cherche les menus déroulants ET les champs numériques
    const inputs = row.querySelectorAll('select, input[type="number"]');
    let currentLvl = 0;
    let targetLvl = 0;

    if (inputs.length >= 2) {
        currentLvl = parseInt(inputs[0].value) || 0;
        targetLvl = parseInt(inputs[1].value) || 0;
    } else if (inputs.length === 1) {
        currentLvl = parseInt(inputs[0].value) || 0;
        targetLvl = currentLvl + 1;
    } else {
        currentLvl = parseInt(element.value) || 0;
        targetLvl = currentLvl + 1; 
    }

    // 2. Récupération robuste du niveau Max
    let maxLvlAttr = element.getAttribute('data-max');
    if (!maxLvlAttr && inputs.length > 0) {
        maxLvlAttr = inputs[0].getAttribute('data-max');
    }
    
    let maxLvl = parseInt(maxLvlAttr);
    if (isNaN(maxLvl) || maxLvl <= 0) maxLvl = 99;

    // 3. Ciblage du conteneur de résultat
    let resultDiv = row.querySelector('.cost-container');
    if (!resultDiv) resultDiv = row.cells[row.cells.length - 1];
    if (!resultDiv) return;

    // 4. Vérifications logiques
    if (currentLvl >= maxLvl && maxLvl !== 99) {
        resultDiv.innerHTML = "<span style='color:#f39c12; font-weight:bold;'>Bâtiment déjà au maximum autorisé.</span>";
        return;
    }

    if (targetLvl <= currentLvl) {
        resultDiv.innerHTML = "<span style='color:#7f8c8d; font-size: 0.9em;'>Sélectionnez un niveau supérieur pour voir le coût.</span>";
        return;
    }
    
    // Nouvelle sécurité : Si le joueur vise un niveau bloqué par le QG
    if (targetLvl > maxLvl && maxLvl !== 99) {
        resultDiv.innerHTML = `<span style='color:#e67e22; font-size: 0.9em;'>Niveau ${targetLvl} impossible (Max autorisé : ${maxLvl})</span>`;
        return;
    }

    // 5. Calcul des coûts
    if (window.PRIX_BATIMENTS && window.PRIX_BATIMENTS.length > 0) {
        let totalBois = 0, totalPierre = 0, totalFer = 0;
        let totalSecs = 0;
        let missingData = false;

        for (let lvl = currentLvl + 1; lvl <= targetLvl; lvl++) {
            const prix = window.PRIX_BATIMENTS.find(b => 
                String(b.TID) === String(tid) && 
                parseInt(b.Niveau || b.niveau || b.Level || b.level) === lvl
            );

            if (prix) {
                totalBois += parseInt(prix.Bois || prix.bois || prix.BuildCostWood || 0);
                totalPierre += parseInt(prix.Pierre || prix.pierre || prix.BuildCostStone || 0);
                totalFer += parseInt(prix.Fer || prix.fer || prix.BuildCostIron || 0);
                
                const d = parseInt(prix.BuildTimeD || prix.buildTimeD || 0);
                const h = parseInt(prix.BuildTimeH || prix.buildTimeH || 0);
                const m = parseInt(prix.BuildTimeM || prix.buildTimeM || 0);
                totalSecs += (d * 86400) + (h * 3600) + (m * 60);
            } else {
                missingData = true;
            }
        }

        if (missingData && totalBois === 0) {
            resultDiv.innerHTML = `<span style="color:#e74c3c;">Prix Niv.${targetLvl} introuvable BDD</span>`;
            return;
        }

        let timeStr = '';
        if (totalSecs > 0) {
            const jours = Math.floor(totalSecs / 86400);
            const heures = Math.floor((totalSecs % 86400) / 3600);
            const minutes = Math.floor((totalSecs % 3600) / 60);
            if (jours > 0) timeStr += jours + 'j ';
            if (heures > 0) timeStr += heures + 'h ';
            if (minutes > 0) timeStr += minutes + 'm ';
        } else {
            timeStr = 'Instant';
        }

        resultDiv.innerHTML = `
            <div style='display:flex; gap:12px; font-size: 0.85em; align-items:center;'>
                <span style='min-width: 50px;'>🪵 ${totalBois.toLocaleString('fr-FR')}</span>
                <span style='min-width: 50px;'>🪨 ${totalPierre.toLocaleString('fr-FR')}</span>
                <span style='min-width: 50px;'>⛓️ ${totalFer.toLocaleString('fr-FR')}</span>
                <span style='color:#bdc3c7; border-left: 1px solid #7f8c8d; padding-left: 10px;'>⏱ ${timeStr}</span>
            </div>
        `;
    } else {
        resultDiv.innerHTML = "<span style='color:#e74c3c;'>Données introuvables</span>";
    }
};

// Correction de la boucle infinie (Ancienne ligne 48)
window.calculateUnitCost = function(selectElement) {
    const tid = selectElement.getAttribute('data-tid');
    const currentLvl = parseInt(selectElement.value);

    if (!window.DATA_UNITES) return;

    // On récupère uniquement les lignes qui concernent CETTE troupe/héros
    const unitRows = window.DATA_UNITES.filter(u => u.TID === tid);
    if (unitRows.length === 0) return;

    // Le niveau max possible pour cette unité est le niveau max trouvé dans ses données
    const maxLvlAvailable = Math.max(...unitRows.map(u => parseInt(u.Niveau)));

    let totalGold = 0;
    let totalTime = 0;

    // On boucle proprement du niveau actuel + 1 jusqu'au niveau max
    for (let nextLvl = currentLvl + 1; nextLvl <= maxLvlAvailable; nextLvl++) {
        const dataLvl = unitRows.find(u => parseInt(u.Niveau) === nextLvl);
        if (dataLvl) {
            totalGold += parseInt(dataLvl.UpgradeCost) || 0;
            totalTime += parseInt(dataLvl.UpgradeTime) || 0;
        }
    }

    console.log(`Troupe ${tid} - Or total restant : ${totalGold}, Temps total : ${totalTime}`);
}

// --- Dans script.js ---
window.ameliorerBatiment = function(tid, instanceId, targetLevel) {
    if (!confirm("Confirmer le passage au niveau " + targetLevel + " ?")) return;

    const formData = new FormData();
    formData.append('tid', tid);
    formData.append('id_instance', instanceId);
    formData.append('target_level', targetLevel);

    fetch('upgrade_building.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // L'onglet actif est déjà mémorisé (localStorage) via showTab, le reload le restaurera.
            location.reload(); 
        } else {
            alert("Erreur : " + data.message);
        }
    })
    .catch(error => console.error('Erreur:', error));
};

window.updateOfficerAbilities = function(selectElement) {
    const card = selectElement.closest('.unit-card');
    if (!card) return;

    const selectedHeroLevel = parseInt(selectElement.value) || 0;
    const officerTid = selectElement.getAttribute('data-tid');
    
    const tablesContainers = card.querySelectorAll('.officer-abilities-container');
    
    if (selectedHeroLevel === 0) {
        tablesContainers.forEach(container => {
            container.style.setProperty('display', 'none', 'important');
        });
        return;
    } else {
        tablesContainers.forEach(container => {
            container.style.setProperty('display', 'block', 'important');
        });
    }

    tablesContainers.forEach(container => {
        // On détermine le bon dictionnaire de données à utiliser pour ce conteneur précis
        const isPassiveTable = container.classList.contains('type-passive');
        const dataSource = isPassiveTable ? window.DATA_PROG_PASSIVES : window.DATA_PROG_ACTIVES;

        const rows = container.querySelectorAll('.ability-row');

        rows.forEach(row => {
            const abilityLvl = parseInt(row.cells[0].textContent);
            
            if (abilityLvl === 1) {
                row.style.display = "table-row";
                row.style.opacity = "1";
                row.style.background = "rgba(46, 204, 113, 0.08)";
                row.cells[0].style.color = "#ffcc00";
                return;
            }

            const dbTargetLvl = abilityLvl - 1;
            let lvlData = null;
            
            // Plus de "else if" global : on pioche STRICTEMENT dans la bonne source isolée
            if (dataSource && dataSource[officerTid] && dataSource[officerTid][dbTargetLvl]) {
                lvlData = dataSource[officerTid][dbTargetLvl];
            }

            if (lvlData) {
                const requiredLvl = parseInt(lvlData.req_hero_lvl);

                if (selectedHeroLevel >= requiredLvl) {
                    row.style.display = "table-row";
                    row.style.opacity = "1";
                    row.style.background = "rgba(46, 204, 113, 0.08)";
                    row.cells[0].style.color = "#ffcc00";
                } else {
                    row.style.display = "none";
                }
            } else {
                row.style.display = "none";
            }
        });
    });
}

// --- OPTIONNEL : Forcer l'initialisation automatique au chargement de la page ---
document.addEventListener("DOMContentLoaded", () => {
    // Sélectionne tous les menus déroulants de niveau dans les cartes d'unités
    document.querySelectorAll('.unit-card .lvl-select').forEach(select => {
        window.updateOfficerAbilities(select);
        
        select.addEventListener('change', function() {
            window.updateOfficerAbilities(this);
        });
    });
});


document.addEventListener("DOMContentLoaded", function() {
    setTimeout(function() {
        if (!window.PRIX_BATIMENTS) {
            console.error("Erreur : window.PRIX_BATIMENTS n'est pas chargé.");
            return;
        }

        // 1. Initialiser et peupler tous les volets d'accordéon (UNIQUEMENT les niveaux autorisés)
        document.querySelectorAll('.building-accordion tbody').forEach(tbody => {
            const tid = tbody.getAttribute('data-tid');
            const maxLvl = parseInt(tbody.getAttribute('data-max')) || 0;

            // CRUCIAL : On filtre par TID ET on bride strictement au niveau maximum autorisé par le QG actuel !
            let levelsData = window.PRIX_BATIMENTS.filter(d => d.TID === tid && parseInt(d.Niveau) <= maxLvl);
            
            // On trie par niveau croissant (0, 1, 2, 3...)
            levelsData.sort((a, b) => parseInt(a.Niveau) - parseInt(b.Niveau));

            let html = "";
            levelsData.forEach(lvl => {
                // Gestion souple des majuscules/minuscules sur les colonnes SQL
                let d = parseInt(lvl.BuildTimeD || lvl.buildtimed) || 0;
                let h = parseInt(lvl.BuildTimeH || lvl.buildtimeh) || 0;
                let m = parseInt(lvl.BuildTimeM || lvl.buildtimem) || 0;
                let s = parseInt(lvl.BuildTimeS || lvl.buildtimes) || 0;

                let timeParts = [];
                if (d > 0) timeParts.push(d + "j");
                if (h > 0) timeParts.push(h + "h");
                if (m > 0) timeParts.push(m + "m");
                if (s > 0 && d === 0) timeParts.push(s + "s");
                let duration = timeParts.length > 0 ? timeParts.join(' ') : "Immédiat";

                let wood = parseInt(lvl.Bois || lvl.bois) ? parseInt(lvl.Bois || lvl.bois).toLocaleString('fr-FR') : '0';
                let stone = parseInt(lvl.Pierre || lvl.pierre) ? parseInt(lvl.Pierre || lvl.pierre).toLocaleString('fr-FR') : '0';
                let iron = parseInt(lvl.Fer || lvl.fer) ? parseInt(lvl.Fer || lvl.fer).toLocaleString('fr-FR') : '0';

                html += `
                <tr class="accordion-row-item" data-lvl="${lvl.Niveau}">
                    <td><span class="lvl-indicator">Niveau ${lvl.Niveau}</span></td>
                    <td class="wood-text">${wood}</td>
                    <td class="stone-text">${stone}</td>
                    <td class="iron-text">${iron}</td>
                    <td class="time-text">🕒 ${duration}</td>
                </tr>`;
            });
            tbody.innerHTML = html;
        });

        // 2. Forcer l'affichage des valeurs réelles sur la ligne principale au chargement
        document.querySelectorAll('.lvl-select').forEach(select => {
            const safeId = select.getAttribute('data-safeid');
            if (safeId) {
                window.updateMainRow(select, safeId);
            }
        });
    }, 100); // 100ms suffisent à stabiliser le DOM
});

// Fonction pour ouvrir / fermer le volet au clic
window.toggleAccordion = function(safeId) {
    const accordion = document.getElementById(`accordion-${safeId}`);
    const arrow = document.getElementById(`arrow-${safeId}`);
    if (!accordion) return;
    
    if (accordion.style.display === "none" || accordion.style.display === "") {
        accordion.style.display = "block";
        if (arrow) arrow.style.transform = "rotate(180deg)";
    } else {
        accordion.style.display = "none";
        if (arrow) arrow.style.transform = "rotate(0deg)";
    }
}

// Fonction qui lie window.PRIX_BATIMENTS à la ligne principale HTML
window.updateMainRow = function(selectElement, safeId) {
    const tid = selectElement.getAttribute('data-tid');
    const currentLvl = parseInt(selectElement.value);
    
    // Si niveau 0 (Non construit), on affiche le coût d'achat (Niveau 0 ou Niveau 1 selon ta structure de table)
    let lvlTarget = currentLvl;

    if (window.PRIX_BATIMENTS) {
        let bInfo = window.PRIX_BATIMENTS.find(d => d.TID === tid && parseInt(d.Niveau) === lvlTarget);
        
        // Sécurité si la BDD commence au niveau 0 pour la construction ou au niveau 1
        if (!bInfo && currentLvl === 0) {
            bInfo = window.PRIX_BATIMENTS.find(d => d.TID === tid && (parseInt(d.Niveau) === 0 || parseInt(d.Niveau) === 1));
        }

        if (bInfo) {
            const card = document.getElementById(`card-${safeId}`);
            if (!card) return;

            // Image dynamique
            const imgTag = document.getElementById(`img-${safeId}`);
            if (imgTag && bInfo.ExportName) {
                imgTag.src = `images/v_all_unlocks/${bInfo.ExportName}.png`;
            }

            // Mise à jour des coûts
            let woodVal = card.querySelector('.val-wood');
            let stoneVal = card.querySelector('.val-stone');
            let ironVal = card.querySelector('.val-iron');

            if (woodVal) woodVal.innerText = parseInt(bInfo.Bois || bInfo.bois) ? parseInt(bInfo.Bois || bInfo.bois).toLocaleString('fr-FR') : '0';
            if (stoneVal) stoneVal.innerText = parseInt(bInfo.Pierre || bInfo.pierre) ? parseInt(bInfo.Pierre || bInfo.pierre).toLocaleString('fr-FR') : '0';
            if (ironVal) ironVal.innerText = parseInt(bInfo.Fer || bInfo.fer) ? parseInt(bInfo.Fer || bInfo.fer).toLocaleString('fr-FR') : '0';

            // Mise à jour du temps
            let d = parseInt(bInfo.BuildTimeD || bInfo.buildtimed) || 0;
            let h = parseInt(bInfo.BuildTimeH || bInfo.buildtimeh) || 0;
            let m = parseInt(bInfo.BuildTimeM || bInfo.buildtimem) || 0;
            let s = parseInt(bInfo.BuildTimeS || bInfo.buildtimes) || 0;

            let timeParts = [];
            if (d > 0) timeParts.push(d + "j");
            if (h > 0) timeParts.push(h + "h");
            if (m > 0) timeParts.push(m + "m");
            if (s > 0 && d === 0) timeParts.push(s + "s");
            let duration = timeParts.length > 0 ? timeParts.join(' ') : "Immédiat";
            
            let timeContainer = card.querySelector('.building-stat-item.time .stat-value');
            if (timeContainer) timeContainer.innerText = duration;
        }
    }
}



// Gestion de l'ouverture des sous-menus accordéon
window.toggleSubMenu = function(element) {
    const submenu = element.nextElementSibling;
    const group = element.closest('.menu-group');
    const isOpen = submenu.style.display === "block";
    submenu.style.display = isOpen ? "none" : "block";
    if (group) group.classList.toggle('open', !isOpen);
};

// Clic sur un menu-header possédant un sous-menu (Bâtiments, Armée, Gravures) :
// on ouvre son sous-menu ET on affiche directement sa page "mini-onglets"
// (cartes de navigation vers chaque sous-catégorie).
window.openCategoryTab = function(element, tabId) {
    const submenu = element.nextElementSibling;
    if (submenu) submenu.style.display = "block";
    const group = element.closest('.menu-group');
    if (group) group.classList.add('open');
    showTab(tabId);
};

// ==========================================================================
// SIDEBAR RÉTRACTABLE (façon ARCTracker.io)
// ==========================================================================
window.toggleSidebar = function() {
    const layout = document.querySelector('.main-layout');
    if (!layout) return;
    const collapsed = layout.classList.toggle('sidebar-collapsed');
    try {
        localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
    } catch (e) {
        console.warn("localStorage indisponible :", e);
    }
};

document.addEventListener('DOMContentLoaded', function() {
    const layout = document.querySelector('.main-layout');
    if (!layout) return;
    try {
        if (localStorage.getItem('sidebarCollapsed') === '1') {
            layout.classList.add('sidebar-collapsed');
        }
    } catch (e) {
        console.warn("localStorage indisponible :", e);
    }
});

// ==========================================================================
// SÉLECTEUR DE LANGUE (sidebar)
// ==========================================================================
window.toggleLangMenu = function(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('langDropdown');
    if (dropdown) dropdown.classList.toggle('open');
};

window.selectLang = function(element) {
    const flag = element.getAttribute('data-flag');
    const label = element.getAttribute('data-label');
    const code = element.getAttribute('data-lang');

    const flagEl = document.getElementById('currentLangFlag');
    const labelEl = document.getElementById('currentLangLabel');
    if (flagEl) flagEl.textContent = flag;
    if (labelEl) labelEl.textContent = label;

    document.querySelectorAll('.lang-option').forEach(opt => opt.classList.remove('selected'));
    element.classList.add('selected');

    const dropdown = document.getElementById('langDropdown');
    if (dropdown) dropdown.classList.remove('open');

    try {
        localStorage.setItem('siteLang', code);
    } catch (e) {
        console.warn("localStorage indisponible :", e);
    }

    // TODO : brancher ici le vrai système de traduction (texts.csv de Boom Beach)
    // une fois que la structure multilingue du back-end sera prête.
};

// Ferme le menu déroulant des langues si on clique ailleurs sur la page
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('langDropdown');
    if (dropdown && dropdown.classList.contains('open') && !e.target.closest('.lang-select')) {
        dropdown.classList.remove('open');
    }
});

// Ouverture/fermeture du petit menu déroulant du profil (bas de la sidebar)
window.toggleProfileMenu = function(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('profileDropdown');
    if (dropdown) dropdown.classList.toggle('open');
};

// Ferme le menu du profil si on clique ailleurs sur la page
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('profileDropdown');
    if (dropdown && dropdown.classList.contains('open') && !e.target.closest('.profile-btn')) {
        dropdown.classList.remove('open');
    }
});

/**
 * Modifie le niveau du monument et rafraîchit à la volée le statut grisé/actif des bonus
 */
window.updateMonumentLevel = function(val) {
    const level = parseInt(val) || 0;

    // 1. Envoi AJAX instantané
    const formData = new FormData();
    formData.append('type', 'level');
    formData.append('id', 'MYSTIC_MONUMENT');
    formData.append('value', level);

    fetch('upgrade_monument.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (!data.success) alert("Erreur lors de la sauvegarde du niveau : " + data.message);
    })
    .catch(err => console.error("Erreur réseau Monument:", err));

    // 2. Gestion dynamique de l'affichage (grisé / déverrouillé)
    const rows = document.querySelectorAll('.monument-bonus-table .mm-bonus-row');
    rows.forEach(row => {
        const requiredLvl = parseInt(row.getAttribute('data-min-mm-lvl')) || 0;
        const inputField = row.querySelector('.monument-qty-field');
        
        if (level < requiredLvl) {
            row.classList.add('bonus-locked');
            if (inputField) {
                inputField.disabled = true;
                if(inputField.value != 0) {
                    inputField.value = 0;
                    // Si la valeur est remise à 0 d'office, on applique la modif en BDD
                    updateMonumentBonus(parseInt(inputField.getAttribute('onchange').match(/\d+/)[0]), 0);
                }
            }
        } else {
            row.classList.remove('bonus-locked');
            if (inputField) inputField.disabled = false;
        }
    });
};

/**
 * Modifie la quantité d'un bonus du monument à la volée
 */
window.updateMonumentBonus = function(idBonus, value, maxCount) {
    const val = parseInt(value);
    if (val > maxCount) {
        alert("Valeur maximale atteinte : " + maxCount);
        return;
    }

    const formData = new FormData();
    formData.append('type', 'bonus');
    formData.append('id', idBonus);
    formData.append('value', val);

    fetch('upgrade_monument.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) alert("Erreur : " + data.message);
    })
    .catch(err => console.error("Erreur fetch Monument :", err));
};

window.triggerUpgradeCharacter = function(tid, safeId, maxLvl) {
    // 1. Récupérer l'élément d'affichage
    const displayElement = document.getElementById('lvl-' + safeId);
    if (!displayElement) return;
    
    // 2. Extraire le niveau actuel
    const textContent = displayElement.innerText;
    let currentLvl = 0;
    if (textContent.includes("Niveau")) {
        currentLvl = parseInt(textContent.replace('Niveau ', '')) || 0;
    }
    
    let newLvl = currentLvl + 1;
    
    // 3. Vérifier la limite
    if (newLvl > maxLvl) {
        alert("Niveau maximum atteint ! (" + maxLvl + ")");
        return;
    }

    // 4. Appel AJAX unifié
    const formData = new FormData();
    formData.append('tid', tid);
    formData.append('target_level', newLvl);

    fetch('upgrade_character.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // L'onglet actif est déjà mémorisé (localStorage) via showTab, le reload le restaurera.
            // On recharge pour refléter le nouveau niveau ET les nouveaux coûts/temps du niveau suivant
            // (le format "Niveau X / Y" et les coûts affichés dépendent tous deux de la BDD).
            location.reload();
        } else {
            alert("Erreur serveur : " + data.message);
        }
    })
    .catch(error => {
        if (error.name === 'AbortError') return; // reload() a coupé la requête : rien d'anormal

        console.error("Erreur réseau :", error);
        alert("Une erreur est survenue lors de la communication avec le serveur.");
    });
};

// Débloquage d'un talent d'officier
window.unlockTalent = function(button) {
    const idCharacter = button.getAttribute('data-character');
    const idAbility = button.getAttribute('data-ability');
    const talentTid = button.getAttribute('data-tid') || 'ce talent';

    if (!confirm("Débloquer le talent " + talentTid + " ?")) return;

    button.disabled = true;
    button.innerHTML = "⏳";

    fetch('upgrade_ability.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'unlock_talent',
            id_character: idCharacter,
            id_ability: idAbility
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert("Erreur : " + data.message);
            button.disabled = false;
            button.innerHTML = "Débloquer";
        }
    })
    .catch(error => {
        console.error("Erreur:", error);
        alert("Une erreur est survenue.");
        button.disabled = false;
        button.innerHTML = "Débloquer";
    });
};

// Fonction pour améliorer une capacité d'officier via AJAX
window.triggerUpgradeAbility = function(button, ab_id, safe_ab_id) {
    const idCharacter = button.getAttribute("data-character");
    const idAbility = button.getAttribute("data-ability");
    const abilityTid = button.getAttribute("data-tid") || "cette capacité";
    const nextLevel = button.getAttribute("data-next-level") || "?";

    if (!idCharacter || !idAbility) {
        console.error("IDs manquants sur le bouton !");
        return;
    }

    // 1. Pop-up de validation
    if (!confirm("Améliorer la capacité " + abilityTid + " au niveau " + nextLevel + " ?")) {
        return; // L'utilisateur a annulé
    }

    // 2. Feedback visuel de chargement
    button.disabled = true;
    const originalText = button.innerHTML;
    button.innerHTML = "⏳";

    // 3. Appel AJAX
    fetch("upgrade_ability.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id_character: idCharacter, id_ability: idAbility })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Affiche l'alerte de succès avec les noms dynamiques
            alert(data.talent_nom + " pour " + data.troupe_nom + " débloqué !");

            // 4. Rechargement de la page pour rafraîchir tous les compteurs et calculs PHP
            window.location.reload();

        } else {
            alert("Erreur : " + data.message);
            button.disabled = false;
            button.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error("Erreur AJAX:", error);
        alert("Une erreur technique est survenue.");
        button.disabled = false;
        button.innerHTML = originalText;
    });
};

window.triggerUpgradeTalent = function(button) {
    const charId = button.getAttribute('data-character');
    const abilityId = button.getAttribute('data-ability');

    if (!abilityId || abilityId === "0") {
        alert("Aucun talent disponible à améliorer.");
        return;
    }

    // 1. Pop-up de confirmation native
    if (!confirm("Êtes-vous sûr de vouloir améliorer ce talent ?")) {
        return; 
    }

    // 2. Appel au serveur pour mettre à jour la BDD
    fetch('upgrade_ability.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id_character: charId,
            id_ability: abilityId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("Amélioration réussie !");
            window.location.reload(); // Rafraîchit pour voir le changement
        } else {
            alert("Erreur : " + data.message);
        }
    })
    .catch(error => {
        console.error("Erreur:", error);
        alert("Une erreur technique est survenue.");
    });
};

window.unlockOfficer = function(idCharacter, tid) {
    const officerTid = tid || "ce chef de bataillon";
    if (!confirm("Êtes-vous sûr d'avoir débloqué le " + officerTid + " ?")) return;

    let formData = new FormData();
    formData.append('action', 'unlock_officer');
    // Assure-toi que cette clé correspond au $_POST['id_character'] de ton PHP
    formData.append('id_character', idCharacter); 

    fetch('upgrade_character.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error("Erreur:", error);
        alert("Une erreur technique est survenue.");
    });
};

function triggerUpgradeBuilding(tid, idInstance, niveauActuel, niveauMax, safeId) {
    const targetLevel = niveauActuel + 1;
    if (targetLevel > niveauMax) return; // déjà au max, sécurité
 
    const btn = document.querySelector(`#card-${safeId} .btn-upgrade`);
    if (btn) btn.disabled = true;
 
    const formData = new FormData();
    formData.append('tid', tid);
    formData.append('id_instance', idInstance);
    formData.append('target_level', targetLevel);
 
    fetch('upgrade_building.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Recharge la portion concernée pour refléter le nouveau niveau,
            // le nouveau libellé de bouton (Construire -> Améliorer) et les nouveaux coûts.
            // Le plus simple et le plus fiable : recharger l'onglet courant.
            location.reload();
        } else {
            alert(data.message || "Erreur lors de la mise à jour du bâtiment.");
            if (btn) btn.disabled = false;
        }
    })
    .catch(err => {
        // location.reload() (juste au-dessus, en cas de succès) coupe net toutes les requêtes
        // encore en vol au moment du rechargement. Le navigateur remonte ça comme un "AbortError",
        // ce n'est PAS une vraie erreur réseau : on l'ignore silencieusement.
        if (err.name === 'AbortError') return;
        console.error('Erreur triggerUpgradeBuilding:', err);
        alert("Erreur réseau, réessaie.");
        if (btn) btn.disabled = false;
    });
}


// Fonction pour basculer les sous-onglets de gravures
window.showEngravingSubTab = function(subTabId, buttonElement) {
    document.querySelectorAll('.engraving-sub-content').forEach(content => {
        content.style.display = 'none';
    });

    const target = document.getElementById(subTabId);
    if (target) {
        target.style.display = 'block';
    }

    document.querySelectorAll('.engravings-sub-tabs .sub-tab-btn').forEach(btn => {
        btn.style.background = '#2c3e50'; 
    });

    if (buttonElement) {
        buttonElement.style.background = '#e74c3c';
    }
};

// Affiche/masque le tableau détaillé des coûts d'une gravure, sous sa carte
window.toggleCostTable = function(safeId) {
    const table = document.getElementById('table-cost-' + safeId);
    const chevron = document.getElementById('chevron-' + safeId);
    if (!table) return;

    const isOpen = table.classList.toggle('open');
    if (chevron) {
        const icon = chevron.querySelector('.chevron-icon');
        if (icon) icon.textContent = isOpen ? '🔼' : '🔽';
    }
};

window.triggerUpgradeTribu = function(idTrib, safeId, maxLvl) {
    const displayElement = document.getElementById('lvl-' + safeId);
    if (!displayElement) return;

    const formData = new FormData();
    formData.append('id_trib', idTrib);

    fetch('upgrade_tribs.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const newLvl = data.new_level;

            // 1. Mettre à jour l'affichage du niveau principal
            displayElement.innerText = "Niveau " + newLvl + " / " + maxLvl;

            // 2. Changer le texte du bouton, et le désactiver si le niveau max est atteint
            const card = document.getElementById('card-' + safeId);
            const btn = card ? card.querySelector('.btn-upgrade') : null;
            const btnText = card ? card.querySelector('.btn-text') : null;
            const isMaxed = newLvl >= maxLvl;

            if (btnText) {
                btnText.innerText = isMaxed ? "Max !" : "Améliorer";
            }
            if (btn && isMaxed) {
                btn.disabled = true;
            }

            // 3. Si le niveau max est atteint, on masque le bloc coûts/temps du prochain palier
            if (isMaxed) {
                const costsBlock = card ? card.querySelector('.building-card-costs') : null;
                const timeBlock = card ? card.querySelector('.building-card-time') : null;
                if (costsBlock) costsBlock.style.display = 'none';
                if (timeBlock) timeBlock.style.display = 'none';
            }

            console.log("Succès :", data.message);
        } else {
            alert("Erreur serveur : " + data.message);
        }
    })
    .catch(error => {
        console.error("Erreur réseau :", error);
        alert("Une erreur est survenue lors de la communication avec le serveur.");
    });
};

window.triggerUpgradeEngraving = function(idEngraving, safeId, maxLvl) {
    const displayElement = document.getElementById('lvl-' + safeId);
    if (!displayElement) return;

    const formData = new FormData();
    formData.append('id_engraving', idEngraving);

    fetch('upgrade_engraving.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const newLvl = data.new_level;

            // 1. Mettre à jour l'affichage du niveau principal
            displayElement.innerText = "Niveau " + newLvl + " / " + maxLvl;

            // 2. Changer le texte du bouton, et le désactiver si le niveau max est atteint
            const card = document.getElementById('card-' + safeId);
            const btn = card ? card.querySelector('.btn-upgrade') : null;
            const btnText = card ? card.querySelector('.btn-text') : null;
            const isMaxed = newLvl >= maxLvl;

            if (btnText) {
                btnText.innerText = isMaxed ? "Max !" : "Améliorer";
            }
            if (btn && isMaxed) {
                btn.disabled = true;
            }

            // 3. Mettre à jour l'affichage du coût du PROCHAIN niveau
            const costElement = document.getElementById('cost-' + safeId);
            if (costElement) {
                const costs = JSON.parse(costElement.getAttribute('data-costs') || '{}');
                const nextLvl = newLvl + 1;

                if (costs[nextLvl] !== undefined) {
                    costElement.innerText = costs[nextLvl];
                } else {
                    costElement.innerText = "Max";
                    const costsRow = costElement.closest('.troop-card-costs');
                    if (costsRow) costsRow.style.display = 'none';
                }
            }

            // 4. Mettre à jour dynamiquement le tableau déroulant des coûts
            const tableContainer = document.getElementById('table-cost-' + safeId);
            if (tableContainer) {
                const rows = tableContainer.querySelectorAll('tbody tr');
                rows.forEach((row, index) => {
                    const rowLvl = index + 1; // Les niveaux commencent à 1
                    const statusCell = row.querySelector('.status-cell');

                    row.classList.remove('cost-row-done', 'cost-row-next');
                    if (rowLvl <= newLvl) {
                        row.classList.add('cost-row-done');
                        if (statusCell) statusCell.innerText = "✅ Acquis";
                    } else if (rowLvl === newLvl + 1) {
                        row.classList.add('cost-row-next');
                        if (statusCell) statusCell.innerText = "⏳ Suivant";
                    } else {
                        if (statusCell) statusCell.innerText = "🔒 Bloqué";
                    }
                });
            }

            console.log("Succès :", data.message);
        } else {
            alert("Erreur serveur : " + data.message);
        }
    })
    .catch(error => {
        console.error("Erreur réseau :", error);
        alert("Une erreur est survenue lors de la communication avec le serveur.");
    });
};

// La fonction reçoit maintenant 'currentQG' directement dans les parenthèses
// Assure-toi qu'il n'y a pas d'accolade manquante juste avant cette ligne !
window.ameliorerQG = function() {
    const btn = document.getElementById('btn-ameliorer-qg');
    if (!btn) {
        console.error("Bouton QG introuvable");
        return;
    }
    
    const currentQG = parseInt(btn.getAttribute('data-qg'));
    const nextQG = currentQG + 1;
    
    if (!confirm("Passer le QG au niveau " + nextQG + " ?")) return;

    const formData = new FormData();
    formData.append('qg_level', nextQG);

    fetch('update_qg.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert("Erreur : " + data.message);
        }
    })
    .catch(err => console.error("Erreur fetch QG :", err));
};

document.addEventListener('DOMContentLoaded', () => {
    console.log("Script chargé avec succès.");
});

// Fonction pour basculer l'affichage
window.toggleDetails = function(event, tid, instance_id) {
    // 1. Cibler la div de détails que tu as préparée en PHP
    const containerId = 'details-' + tid + '-' + instance_id;
    const container = document.getElementById(containerId);
    
    if (!container) {
        console.error("Conteneur introuvable :", containerId);
        return;
    }

    // 2. Basculer l'affichage (Si c'est ouvert, on ferme et on arrête là)
    if (container.style.display === 'block') {
        container.style.display = 'none';
        // Optionnel : faire tourner la flèche
        event.currentTarget.style.transform = "rotate(0deg)";
        return;
    }

    // 3. Vérification de la présence des données
    if (typeof window.DATA_PROGRESSION === 'undefined') {
        container.innerHTML = "<p style='color:red;'>Erreur : Données non chargées.</p>";
        container.style.display = 'block';
        return;
    }

    // 4. Filtrer les niveaux restants
    const filteredData = window.DATA_PROGRESSION.filter(item => 
        String(item.TID) === String(tid) && String(item.id_instance) === String(instance_id)
    );

    if (filteredData.length === 0) {
        container.innerHTML = "<p>Bâtiment déjà au maximum autorisé.</p>";
        container.style.display = 'block';
        return;
    }

    // 5. Générer le tableau HTML
    let totalBois = 0, totalPierre = 0, totalFer = 0, totalMinutes = 0;

    let html = `
    <table class='progression-table' style='width:100%; font-size: 0.9em; margin-top: 10px; background: rgba(0,0,0,0.2); border-radius: 5px;'>
        <thead>
            <tr>
                <th>Niv.</th>
                <th><img src='images/icons/Wood.png' width='20'></th>
                <th><img src='images/icons/Stone.png' width='20'></th>
                <th><img src='images/icons/Iron.png' width='20'></th>
                <th><img src='images/icons/Time Icon.png' width='20'></th>
            </tr>
        </thead>
        <tbody>`;

    filteredData.forEach(p => {
        let mins = (parseInt(p.BuildTimeD || 0) * 1440) + (parseInt(p.BuildTimeH || 0) * 60) + parseInt(p.BuildTimeM || 0);
        totalBois += parseInt(p.BuildCostWood || 0);
        totalPierre += parseInt(p.BuildCostStone || 0);
        totalFer += parseInt(p.BuildCostIron || 0);
        totalMinutes += mins;

        html += `
            <tr>
                <td>${p.Niveau}</td>
                <td>${parseInt(p.BuildCostWood || 0).toLocaleString('fr-FR')}</td>
                <td>${parseInt(p.BuildCostStone || 0).toLocaleString('fr-FR')}</td>
                <td>${parseInt(p.BuildCostIron || 0).toLocaleString('fr-FR')}</td>
                <td>${p.BuildTimeD}j ${p.BuildTimeH}h ${p.BuildTimeM}m</td>
            </tr>`;
    });

    // Ligne Total
    const tDays = Math.floor(totalMinutes / 1440);
    const tHours = Math.floor((totalMinutes % 1440) / 60);
    const tMins = totalMinutes % 60;

    html += `
            <tr style='font-weight: bold; color: #f1c40f; border-top: 1px solid #444;'>
                <td>Total</td>
                <td>${totalBois.toLocaleString('fr-FR')}</td>
                <td>${totalPierre.toLocaleString('fr-FR')}</td>
                <td>${totalFer.toLocaleString('fr-FR')}</td>
                <td>${tDays}j ${tHours}h ${tMins}m</td>
            </tr>
        </tbody>
    </table>`;

    // 6. Insérer et afficher
    container.innerHTML = html;
    container.style.display = 'block';
    event.currentTarget.style.transform = "rotate(180deg)"; // Fait tourner le chevron vers le haut
};

// Remplace UNIQUEMENT le bloc DOMContentLoaded par celui-ci :
document.addEventListener('DOMContentLoaded', () => {
    
    const tenterAffichage = (tentative = 0) => {
        const containers = document.querySelectorAll('.cost-container');
        
        if (typeof window.PRIX_BATIMENTS === 'undefined' || window.PRIX_BATIMENTS.length === 0) {
            if (tentative < 5) {
                setTimeout(() => tenterAffichage(tentative + 1), 200);
                return;
            }
            containers.forEach(c => c.innerHTML = "Données introuvables");
            return;
        }

        containers.forEach(container => {
            const tid = container.getAttribute('data-tid');
            const nextLevelAttr = container.getAttribute('data-next-lvl');
            
            // Si le PHP nous dit explicitement que c'est au niveau max
            if (nextLevelAttr === 'max') {
                container.innerHTML = "<span style='color:#f39c12; font-weight:bold;'>Max</span>";
                return;
            }

            const nextLevel = parseInt(nextLevelAttr);
            
            // Recherche ultra-tolérante pour le Niveau (Niveau, niveau, Level, level)
            const prix = window.PRIX_BATIMENTS.find(b => 
                String(b.TID) === String(tid) && 
                parseInt(b.Niveau || b.niveau || b.Level || b.level) === nextLevel
            );
            
            if (prix) {
                // Recherche ultra-tolérante pour les ressources (FR ou EN)
                const bois = prix.Bois || prix.bois || prix.BuildCostWood || 0;
                const pierre = prix.Pierre || prix.pierre || prix.BuildCostStone || 0;
                const fer = prix.Fer || prix.fer || prix.BuildCostIron || 0;
                
                // Recherche ultra-tolérante pour le temps
                const d = prix.BuildTimeD || prix.buildTimeD || 0;
                const h = prix.BuildTimeH || prix.buildTimeH || 0;
                const m = prix.BuildTimeM || prix.buildTimeM || 0;
                
                let timeStr = '';
                if (d > 0) timeStr += d + 'j ';
                if (h > 0) timeStr += h + 'h ';
                if (m > 0) timeStr += m + 'm ';
                if (timeStr === '') timeStr = 'Instant';
                
                // Affichage final avec ressources ET temps
                container.innerHTML = `
                    <div style='display:flex; gap:12px; font-size: 0.85em; align-items:center;'>
                        <span style='min-width: 50px;'>🪵 ${parseInt(bois).toLocaleString()}</span>
                        <span style='min-width: 50px;'>🪨 ${parseInt(pierre).toLocaleString()}</span>
                        <span style='min-width: 50px;'>⛓️ ${parseInt(fer).toLocaleString()}</span>
                        <span style='color:#bdc3c7; border-left: 1px solid #7f8c8d; padding-left: 10px;'>⏱ ${timeStr}</span>
                    </div>`;
            } else {
                // S'il ne trouve pas le coût dans la BDD, on met un message d'erreur clair au lieu de dire "Max"
                container.innerHTML = `<span style="color:#e74c3c;">Prix Niv.${nextLevel} introuvable BDD</span>`;
            }
        });
    };

    tenterAffichage();
});

// On lance la fonction quand la page commence à être prête
document.addEventListener("DOMContentLoaded", chargerCoutsApercu);


document.addEventListener("DOMContentLoaded", function() {
    document.body.addEventListener("click", function(e) {
        // On vérifie si l'élément cliqué est bien notre bouton d'amélioration
        if (e.target && e.target.classList.contains("btn-upgrade-ability")) {
            e.preventDefault();
            
            // --- AJOUT : Demande de confirmation ---
            if (!confirm("Confirmez-vous l'amélioration de cette capacité ?")) {
                return;
            }
            // ----------------------------------------

            const button = e.target;
            const idCharacter = button.getAttribute("data-character");
            const idAbility = button.getAttribute("data-ability");
            
            // Blocage immédiat pour éviter le spam de clics
            button.disabled = true;
            button.innerHTML = "⏳...";

            fetch("upgrade_ability.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ id_character: idCharacter, id_ability: idAbility })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 1. Mise à jour texte Niv. X
                    const rowOrCard = button.closest("tr") || button.closest(".talent-row");
                    const levelDisplay = rowOrCard ? rowOrCard.querySelector(".ability-level-text") : null;
                    if (levelDisplay) {
                        levelDisplay.innerHTML = "Niv. " + data.new_level;
                    }

                    // 2. Gestion blocage du bouton (clic infini)
                    if (data.new_level >= 15) {
                        button.disabled = true;
                        button.innerHTML = "MAX";
                        button.style.background = "#7f8c8d";
                    } else {
                        button.disabled = false;
                        button.innerHTML = "⬆"; 
                        button.style.background = "#2ecc71";
                        setTimeout(() => { button.style.background = "#1abc9c"; }, 1000);
                    }

                    // 3. Masquer la ligne acquise dans le tableau des coûts
                    const currentTr = button.closest("tr");
                    if (currentTr) {
                        currentTr.style.transition = "opacity 0.5s";
                        currentTr.style.opacity = "0";
                        setTimeout(() => { currentTr.style.display = "none"; }, 500);
                    }

                    // 4. Mise à jour Sidebar (droite)
                    const sidebarRow = document.querySelector(`[data-sidebar-character="${idCharacter}"]`);
                    if (sidebarRow) {
                        const scoreSpan = sidebarRow.querySelector(".sidebar-chef-score");
                        if (scoreSpan) {
                            let parts = scoreSpan.innerText.split("/");
                            let current = parseInt(parts[0]) + 1;
                            let max = parseInt(parts[1]);
                            scoreSpan.innerText = `${current}/${max}`;
                        }
                    }

                    // 5. Recalcul % global
                    let progSum = 0;
                    let maxSum = 0;
                    document.querySelectorAll(".sidebar-chef-score").forEach(span => {
                        let [c, m] = span.innerText.split("/").map(Number);
                        progSum += (c > 1) ? (c - 1) : 0;
                        maxSum += (m > 1) ? (m - 1) : 0;
                    });

                    const percentDiv = document.querySelector(".sidebar-global-percent");
                    if (percentDiv && maxSum > 0) {
                        percentDiv.innerText = Math.round((progSum / maxSum) * 100) + "%";
                    }

                } else {
                    alert("Erreur : " + data.message);
                    button.disabled = false;
                    button.innerHTML = "<img src='images/icons/gacha_info_icon.png' style='width: 25px;'>";
                }
            })
            .catch(error => {
                console.error("Erreur AJAX:", error);
                button.disabled = false;
                button.innerHTML = "<img src='images/icons/gacha_info_icon.png' style='width: 25px;'>";
            });
        }
    });
});
// ==========================================================================
// MASQUER LES ÉLÉMENTS AU NIVEAU MAX (bâtiments, troupes, tribus, gravures)
// ==========================================================================
// Gestion du bouton "Masquer les éléments au max"
window.toggleHideMaxed = function(btn) {
    const tabContent = btn.closest('.tab-content');
    if (!tabContent) return;

    const isHidden = btn.classList.toggle('active');
    const containers = tabContent.querySelectorAll('.hide-maxed-container');

    containers.forEach(container => {
        container.classList.toggle('maxed-hidden', isHidden);
    });

    // Mise à jour du label
    const label = btn.querySelector('.hide-maxed-label');
    if (label) {
        const currentText = label.textContent;
        const newText = isHidden
            ? currentText.replace('Masquer', 'Afficher')
            : currentText.replace('Afficher', 'Masquer');
        label.textContent = newText;
    }

    // Mémorisation dans localStorage
    try {
        localStorage.setItem('hideMaxed_' + tabContent.id, isHidden ? '1' : '0');
    } catch (e) {
        console.warn("localStorage indisponible :", e);
    }
};

// Restaurer l'état au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.tab-content').forEach(tab => {
        try {
            const isHidden = localStorage.getItem('hideMaxed_' + tab.id) === '1';
            if (isHidden) {
                const btn = tab.querySelector('.btn-hide-maxed');
                if (btn) {
                    btn.classList.add('active');
                    const label = btn.querySelector('.hide-maxed-label');
                    if (label) {
                        label.textContent = label.textContent.replace('Masquer', 'Afficher');
                    }
                }
                tab.querySelectorAll('.hide-maxed-container').forEach(container => {
                    container.classList.add('maxed-hidden');
                });
            }
        } catch (e) {
            console.warn("Erreur restauration hideMaxed :", e);
        }
    });
});

// Log de confirmation de fin de chargement
console.log("Script chargé avec succès et toutes les fonctions sont prêtes.");