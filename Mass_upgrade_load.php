<?php
// mass_upgrade_load.php
// Endpoint AJAX (GET) : renvoie le fragment HTML du formulaire Mass Upgrade
// pour le niveau de QG demandé, pré-rempli avec la progression actuelle du joueur.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'mass_upgrade_helpers.php';
require_once 'mass_upgrade_render.php';

header('Content-Type: text/html; charset=utf-8');

$id_player = $_SESSION['player_id'] ?? null;
if (!$id_player) {
    http_response_code(401);
    echo "<p class='mu-empty'>Session expirée, merci de vous reconnecter.</p>";
    exit;
}

$qg = isset($_GET['qg']) ? (int)$_GET['qg'] : 1;
$max_qg = muGetMaxQG($pdo);
if ($qg < 1) $qg = 1;
if ($qg > $max_qg) $qg = $max_qg;

$data = muBuildData($pdo, $id_player, $qg);
echo muRenderForm($data);