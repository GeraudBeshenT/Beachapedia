<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Beachapedia - QG <?php echo $qg; ?></title>
    <link rel="icon" type="image/png" href="images/BBT.webp">
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Bangers&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <script>window.PRIX_BATIMENTS = <?php echo htmlspecialchars($_SESSION['player_nom']); ?>;</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="script.js?v=<?php echo time(); ?>"></script>
</head>
<body>