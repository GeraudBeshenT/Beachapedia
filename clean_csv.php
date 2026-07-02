<?php
$source = 'texts.csv';
$destination = 'texts_fixed.csv';

if (($handle = fopen($source, "r")) !== FALSE) {
    $fileOut = fopen($destination, "w");
    
    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
        // fgetcsv est intelligent : il a déjà séparé les colonnes 
        // en respectant les guillemets.
        // On réécrit maintenant la ligne avec le point-virgule.
        fputcsv($fileOut, $data, ";");
    }
    
    fclose($handle);
    fclose($fileOut);
    echo "Terminé ! Le fichier texts_fixed.csv a été créé avec des points-virgules.";
} else {
    echo "Impossible d'ouvrir le fichier source.";
}
?>