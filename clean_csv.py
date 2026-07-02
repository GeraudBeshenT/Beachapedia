import csv

input_file = 'texts.csv'          # Fichier d'entrée (ton CSV original)
output_file = 'texts_cleaned.csv'  # Fichier de sortie (CSV nettoyé)

with open(input_file, 'r', encoding='utf-8') as infile, \
     open(output_file, 'w', encoding='utf-8', newline='') as outfile:
    reader = csv.reader(infile)
    writer = csv.writer(outfile, quoting=csv.QUOTE_ALL)  # Ajoute des guillemets autour de chaque champ
    for row in reader:
        # Remplace les \n par des espaces dans chaque champ
        cleaned_row = [field.replace('\n', ' ').replace('\r', ' ') for field in row]
        writer.writerow(cleaned_row)

print(f"Fichier nettoyé enregistré sous : {output_file}")