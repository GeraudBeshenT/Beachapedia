<?php
// upgrade_engraving.php
// Gère à la fois l'amélioration ($action='upgrade', par défaut/implicite) et la
// rétrogradation ($action='downgrade') d'une gravure, dans le même fichier — même
// convention que upgrade_building.php (dispatch sur $action).

// 1. Démarrage de la session et sécurité
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$id_player = $_SESSION['player_id'] ?? null;
if (!$id_player) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté.']);
    exit();
}

require_once 'config.php'; // Contient ta connexion $pdo

// 2. Récupération des données envoyées en POST (ou JSON)
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? ($_POST['action'] ?? 'upgrade');
$id_engraving = isset($input['id_engraving']) ? (int)$input['id_engraving'] : (isset($_POST['id_engraving']) ? (int)$_POST['id_engraving'] : null);

if (!$id_engraving) {
    echo json_encode(['success' => false, 'message' => 'ID de gravure manquant.']);
    exit();
}

try {
    // 3. Ligne de progression existante pour cette gravure (commune aux deux actions)
    $stmt_check = $pdo->prepare("SELECT niveau FROM progress_engraving WHERE id_player = ? AND id_engraving = ?");
    $stmt_check->execute([$id_player, $id_engraving]);
    $current_progress = $stmt_check->fetch(PDO::FETCH_ASSOC);
    $niveau_en_base = $current_progress ? (int)$current_progress['niveau'] : 0;

    if ($action === 'downgrade') {
        // --- RÉTROGRADATION ---
        $current_level = isset($input['current_level']) ? (int)$input['current_level'] : (isset($_POST['current_level']) ? (int)$_POST['current_level'] : null);
        if ($current_level === null) {
            echo json_encode(['success' => false, 'message' => 'Données manquantes.']);
            exit();
        }
        if ($niveau_en_base <= 0) {
            echo json_encode(['success' => false, 'message' => 'Cette gravure est déjà au niveau minimum.']);
            exit();
        }

        // Garde-fou anti-course/anti-triche : le niveau en base doit correspondre à ce que
        // la card affichait au moment du clic (même principe que upgrade_building.php).
        if ($niveau_en_base !== $current_level) {
            echo json_encode(['success' => false, 'message' => 'Le niveau a changé entre-temps, recharge la page et réessaie.']);
            exit();
        }

        $new_level = $current_level - 1;
        $stmt_update = $pdo->prepare("UPDATE progress_engraving SET niveau = ? WHERE id_player = ? AND id_engraving = ?");
        $stmt_update->execute([$new_level, $id_player, $id_engraving]);

        echo json_encode([
            'success' => true,
            'action' => 'downgraded',
            'new_level' => $new_level,
            'id_engraving' => $id_engraving,
            'message' => 'Gravure rétrogradée.'
        ]);
        exit();
    }

    // --- AMÉLIORATION (comportement historique, inchangé) ---
    if ($current_progress === false) {
        // Le joueur n'a jamais amélioré cette gravure -> On l'insère au Niveau 1
        $new_level = 1;
        $stmt_insert = $pdo->prepare("INSERT INTO progress_engraving (id_player, id_engraving, niveau) VALUES (?, ?, ?)");
        $stmt_insert->execute([$id_player, $id_engraving, $new_level]);
        $upgrade_action = 'inserted';
    } else {
        // La gravure existe déjà -> On augmente son niveau de 1
        $new_level = $niveau_en_base + 1;

        // Optionnel : Tu peux ajouter ici une sécurité pour vérifier si le niveau ne dépasse pas le MaxQuality de engravingid

        $stmt_update = $pdo->prepare("UPDATE progress_engraving SET niveau = ? WHERE id_player = ? AND id_engraving = ?");
        $stmt_update->execute([$new_level, $id_player, $id_engraving]);
        $upgrade_action = 'updated';
    }

    // 4. Réponse au format JSON pour ton script JavaScript (AJAX)
    echo json_encode([
        'success' => true,
        'action' => $upgrade_action,
        'new_level' => $new_level,
        'id_engraving' => $id_engraving,
        'message' => 'Gravure améliorée avec succès !'
    ]);

} catch (PDOException $e) {
    // En cas d'erreur de base de données
    error_log("Erreur upgrade_engraving : " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la mise à jour de la base de données.'
    ]);
}