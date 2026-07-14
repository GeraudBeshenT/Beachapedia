<?php
session_start();
require_once 'connexion.php'; // gère $_POST['action_auth'] (login / register) et prépare $auth_message
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Beachapedia — Connexion</title>
<link rel="stylesheet" href="style.css">
<style>
    /* ---------- Page de connexion / inscription — thème Boom Beach ---------- */

    .auth-page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 30px 16px;
        background:
            radial-gradient(circle at 20% 20%, rgba(255,255,255,0.06), transparent 40%),
            linear-gradient(180deg, #2a6478 0%, #3C7891 55%, #f0d9a0 100%);
        box-sizing: border-box;
    }

    .auth-card {
        width: 100%;
        max-width: 440px;
        background: #1a252f;
        border: 2px solid #1abc9c;
        border-radius: 20px;
        box-shadow: 0 15px 40px rgba(0,0,0,0.45);
        overflow: hidden;
        position: relative;
    }

    .auth-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 6px;
        background: linear-gradient(90deg, #1abc9c, #EE893E, #1abc9c);
    }

    .auth-header {
        text-align: center;
        padding: 28px 24px 14px;
    }

    .auth-logo {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: #111a24;
        border: 3px solid #EE893E;
        font-size: 30px;
        margin-bottom: 10px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.4);
    }

    .auth-title {
        font-family: 'Bangers', cursive;
        letter-spacing: 1px;
        font-size: 2.1em;
        color: #ecf0f1;
        margin: 4px 0 2px;
        text-shadow: 0 2px 0 rgba(0,0,0,0.35);
    }

    .auth-subtitle {
        font-size: 0.85em;
        color: #8aa0b0;
        margin: 0;
    }

    /* Onglets Connexion / Inscription */
    .auth-tabs {
        display: flex;
        margin: 18px 24px 0;
        background: #111a24;
        border-radius: 10px;
        padding: 4px;
        gap: 4px;
    }

    .auth-tab {
        flex: 1;
        text-align: center;
        padding: 10px 0;
        font-family: 'Bangers', cursive;
        font-size: 1.1em;
        letter-spacing: 0.5px;
        color: #8aa0b0;
        border-radius: 8px;
        cursor: pointer;
        user-select: none;
        transition: background 0.2s ease, color 0.2s ease;
    }

    .auth-tab.active {
        background: #1abc9c;
        color: #0e1b22;
    }

    .auth-body {
        padding: 22px 24px 28px;
    }

    .auth-form { display: none; flex-direction: column; gap: 14px; }
    .auth-form.active { display: flex; }

    .auth-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .auth-field label {
        font-size: 0.8em;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #8aa0b0;
    }

    .auth-field input {
        background: #111a24;
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 8px;
        padding: 11px 14px;
        color: #ecf0f1;
        font-size: 0.95em;
        outline: none;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .auth-field input::placeholder { color: #556877; }

    .auth-field input:focus {
        border-color: #1abc9c;
        box-shadow: 0 0 0 3px rgba(26, 188, 156, 0.18);
    }

    .auth-submit {
        margin-top: 6px;
        padding: 12px 0;
        border: none;
        border-radius: 10px;
        background: #EE893E;
        color: #1a1208;
        font-family: 'Bangers', cursive;
        font-size: 1.25em;
        letter-spacing: 1px;
        cursor: pointer;
        box-shadow: 0 4px 0 #b9631f;
        transition: transform 0.1s ease, box-shadow 0.1s ease, background 0.15s ease;
    }

    .auth-submit:hover { background: #f39c4f; }

    .auth-submit:active {
        transform: translateY(3px);
        box-shadow: 0 1px 0 #b9631f;
    }

    .auth-message {
        margin: 0 24px 18px;
        padding: 10px 14px;
        border-radius: 8px;
        font-size: 0.85em;
        font-weight: 600;
        text-align: center;
    }

    .auth-message.success {
        background: rgba(26, 188, 156, 0.15);
        border: 1px solid rgba(26, 188, 156, 0.4);
        color: #1abc9c;
    }

    .auth-message.error {
        background: rgba(231, 76, 60, 0.15);
        border: 1px solid rgba(231, 76, 60, 0.4);
        color: #e74c3c;
    }

    .auth-hint {
        text-align: center;
        font-size: 0.75em;
        color: #556877;
        margin-top: 4px;
    }

    @media (max-width: 480px) {
        .auth-title { font-size: 1.7em; }
        .auth-header, .auth-body { padding-left: 18px; padding-right: 18px; }
        .auth-tabs { margin-left: 18px; margin-right: 18px; }
        .auth-message { margin-left: 18px; margin-right: 18px; }
    }
</style>
</head>
<body>

<div class="auth-page">
    <div class="auth-card">

        <div class="auth-header">
            <div class="auth-logo">🏝️</div>
            <h1 class="auth-title">BEACHAPEDIA</h1>
            <p class="auth-subtitle">Ton tracker de progression Boom Beach</p>
        </div>

        <div class="auth-tabs">
            <div class="auth-tab active" data-target="login">Connexion</div>
            <div class="auth-tab" data-target="register">Inscription</div>
        </div>

        <?php if (!empty($auth_message)): ?>
            <div class="auth-message <?= strpos($auth_message, '✅') !== false ? 'success' : 'error' ?>">
                <?= htmlspecialchars($auth_message) ?>
            </div>
        <?php endif; ?>

        <div class="auth-body">

            <!-- Formulaire de connexion -->
            <form class="auth-form active" id="form-login" method="POST" action="index.php">
                <input type="hidden" name="action_auth" value="login">

                <div class="auth-field">
                    <label for="login-pseudo">Pseudo</label>
                    <input type="text" id="login-pseudo" name="pseudo" placeholder="Ton pseudo" required>
                </div>

                <div class="auth-field">
                    <label for="login-password">Mot de passe</label>
                    <input type="password" id="login-password" name="password" placeholder="••••••••" required>
                </div>

                <button type="submit" class="auth-submit">Se connecter</button>
                <p class="auth-hint">Pas encore de compte ? Clique sur « Inscription » ci-dessus.</p>
            </form>

            <!-- Formulaire d'inscription -->
            <form class="auth-form" id="form-register" method="POST" action="index.php">
                <input type="hidden" name="action_auth" value="register">

                <div class="auth-field">
                    <label for="register-pseudo">Pseudo</label>
                    <input type="text" id="register-pseudo" name="pseudo" placeholder="Choisis un pseudo" required>
                </div>

                <div class="auth-field">
                    <label for="register-id">ID joueur (en jeu)</label>
                    <input type="text" id="register-id" name="id_player" placeholder="Ton ID Boom Beach" required>
                </div>

                <div class="auth-field">
                    <label for="register-password">Mot de passe</label>
                    <input type="password" id="register-password" name="password" placeholder="••••••••" required minlength="6">
                </div>

                <button type="submit" class="auth-submit">Créer mon compte</button>
                <p class="auth-hint">Déjà inscrit ? Clique sur « Connexion » ci-dessus.</p>
            </form>

        </div>
    </div>
</div>

<script>
    const tabs = document.querySelectorAll('.auth-tab');
    const forms = {
        login: document.getElementById('form-login'),
        register: document.getElementById('form-register')
    };

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            Object.values(forms).forEach(f => f.classList.remove('active'));
            forms[tab.dataset.target].classList.add('active');
        });
    });

    // Si une erreur d'inscription est renvoyée, on rouvre automatiquement l'onglet Inscription
    <?php if (!empty($auth_message) && isset($_POST['action_auth']) && $_POST['action_auth'] === 'register'): ?>
        document.querySelector('.auth-tab[data-target="register"]').click();
    <?php endif; ?>
</script>

</body>
</html>