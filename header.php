<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Beachapedia - QG <?php echo $qg; ?></title>
    <link rel="icon" type="image/png" href="images/BBT.webp">
    <link rel="stylesheet" href="style.css">
    <script>window.PRIX_BATIMENTS = <?php echo htmlspecialchars($_SESSION['player_nom']); ?>;</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="script.js"></script>
    <script src="script.js?v=<?php echo time(); ?>"></script>
</head>
<body>
    <h1>Beachapedia : Base de <?php echo htmlspecialchars($_SESSION['player_nom']); ?></h1>