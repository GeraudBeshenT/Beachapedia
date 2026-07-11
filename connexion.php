<?php
require_once 'config.php';

$auth_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_auth'])) {
    
    // INSCRIPTION
    if ($_POST['action_auth'] === 'register') {
        $pseudo = trim($_POST['pseudo']);
        $id_jeu = trim(htmlspecialchars($_POST['id_player']));
        $pass  = password_hash($_POST['password'], PASSWORD_BCRYPT);

        try {
            $stmt = $pdo->prepare("INSERT INTO joueurs (id_player, pseudo, password) VALUES (?, ?, ?)");
            $stmt->execute([$id_jeu, $pseudo, $pass]);

            $starting_buildings = [10, 11, 13, 24];
        
            $stmt_build = $pdo->prepare("
                INSERT INTO progress_building (id_player, id_building, id_instance, niveau, Debloque) 
                VALUES (?, ?, 1, 1, 1)
            ");

            foreach ($starting_buildings as $tid) {
                $stmt_build->execute([$id_jeu, $tid]);
            }

            $starting_characters = [50, 76];

            $stmt_char = $pdo->prepare("
                INSERT INTO progress_character (id_player, id_character, niveau, Debloque) 
                VALUES (?, ?, 1, 1)
            ");

            foreach ($starting_characters as $tid) {
                $stmt_char->execute([$id_jeu, $tid]);
            } $pdo->prepare("
                INSERT INTO progress_character (id_player, id_character, niveau, Debloque) 
                VALUES (?, 50, 1, 1)
            ");
            $stmt_char->execute([$id_jeu]);

            $auth_message = "✅ Inscription réussie !";
            
            // 3. IMPORTANT : Redirection après succès pour éviter le rechargement du POST
            header("Location: index.php?msg=success");
            exit();

        } catch (Exception $e) {
            $auth_message = "❌ Erreur : ID ou Pseudo déjà utilisé.";
        }
    }

    // CONNEXION
    if ($_POST['action_auth'] === 'login') {
        $pseudo = trim($_POST['pseudo']);
        $pass   = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM joueurs WHERE pseudo = ?");
        $stmt->execute([$pseudo]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['player_id']  = $user['id_player'];
            $_SESSION['player_nom'] = $user['pseudo'];
            header("Location: dashboard.php");
            exit();
        } else {
            $auth_message = "❌ Pseudo ou mot de passe incorrect.";
        }
    }
}
?>