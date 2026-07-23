<?php
// update_boosts.php
// Enregistre les 2 bonus de vitesse du Profil (onglet Boost) : joueurs.BuildingBoost /
// joueurs.ArmoryBoost. Purement des valeurs d'AFFICHAGE (voir formatUnitsTime /
// formatSecondsToText dans functions.php) : n'ont aucun effet sur le jeu réel, seulement
// sur le temps de construction/amélioration affiché côté site.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

$id_player = $_SESSION['player_id'] ?? null;
if (!$id_player) {
    echo json_encode(['success' => false, 'message' => 'Non connecté.']);
    exit;
}

// Bornage 0-99% (les mêmes bornes que côté affichage, voir getPlayerBoosts()).
$building_boost = isset($_POST['building_boost']) ? (int)$_POST['building_boost'] : 0;
$armory_boost   = isset($_POST['armory_boost'])   ? (int)$_POST['armory_boost']   : 0;
$building_boost = max(0, min(99, $building_boost));
$armory_boost   = max(0, min(99, $armory_boost));

try {
    $stmt = $pdo->prepare("UPDATE joueurs SET BuildingBoost = ?, ArmoryBoost = ? WHERE id_player = ?");
    $stmt->execute([$building_boost, $armory_boost, $id_player]);

    echo json_encode([
        'success'         => true,
        'building_boost'  => $building_boost,
        'armory_boost'    => $armory_boost,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}