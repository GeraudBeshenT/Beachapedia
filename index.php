<?php 
session_start();
if (isset($_SESSION['player_id'])) { header("Location: dashboard.php"); exit(); }
include 'connexion.php'; 
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Boom Beach Tracker - Accueil</title>
    <style>
        .tabs { display: flex; cursor: pointer; margin-bottom: 20px; }
        .tab { padding: 10px 20px; background: #34495e; color: white; }
        .tab.active { background: #1abc9c; }
        .form-container { display: none; }
        .form-container.active { display: block; }
    </style>
</head>
<body>
    <div class="tabs">
        <div class="tab active" onclick="showForm('login')">Connexion</div>
        <div class="tab" onclick="showForm('register')">Inscription</div>
    </div>

    <div id="login-form" class="form-container active">
        <form method="POST" action="">
            <input type="hidden" name="action_auth" value="login">
            <input type="text" name="pseudo" placeholder="Pseudo" required>
            <input type="password" name="password" placeholder="Mot de passe" required>
            <button type="submit">Se connecter</button>
        </form>
    </div>

    <div id="register-form" class="form-container">
        <form method="POST" action="">
            <input type="hidden" name="action_auth" value="register">
            <input type="text" name="pseudo" placeholder="Pseudo" required>
            <input type="text" name="id_player" placeholder="ID de jeu" required>
            <input type="password" name="password" placeholder="Mot de passe" required>
            <button type="submit">S'inscrire</button>
        </form>
    </div>

    <script>
        function showForm(type) {
            document.querySelectorAll('.form-container').forEach(f => f.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.getElementById(type + '-form').classList.add('active');
            event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>