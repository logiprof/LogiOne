<?php
/** Données des 15 exercices Excel (clés 101 à 115 = leçons Excel 1 à 15). */
if ( ! defined( 'LIR_STANDALONE' ) && ! defined( 'ABSPATH' ) ) { exit; }
return array(
  101 => array( 'titre' => 'Découvrir Excel et organiser un classeur', 'exercice' => 'Remettre en ordre le classeur des articles', 'fichiers' => 1, 'type' => 'xlsx', 'formation' => 'excel', 'num' => 1, 'mission' => 'Le classeur des articles de l\'entrepôt de Bussigny a été préparé à la va-vite : feuilles mal nommées, colonne inutile, articles supprimés encore présents, colonnes trop étroites. Remettez-le en ordre sans retaper aucune donnée, puis utilisez la barre d\'état pour répondre à trois questions.', 'consignes' => '1. Ouvrir et enregistrer
   - Ouvrez L01_donnees_brutes.xlsx.
   - Appuyez sur F12 et enregistrez-le sous L01_Prénom_Nom.xlsx.
2. Organiser les feuilles
   - Renommez Feuil1 en Articles et Feuil2 en Fournisseurs.
   - Donnez la couleur d\'onglet Vert à Articles et Jaune à Fournisseurs (couleurs standard).
   - Déplacez l\'onglet Articles pour qu\'il soit le premier.
   - Supprimez la feuille vide Feuil3.
3. Nettoyer la feuille Articles
   - Supprimez la colonne B (« Ancien code ») entière.
   - Insérez une ligne vide au-dessus de la ligne des titres de colonnes (le titre reste en ligne 1, la ligne 2 devient vide).
   - Repérez les 3 articles dont la désignation se termine par (supprimé) et supprimez leurs lignes entières. Astuce : Ctrl + clic sur les numéros de lignes pour les sélectionner ensemble.
4. Ajuster les colonnes
   - Sélectionnez les colonnes A à H et double-cliquez sur une limite d\'en-tête : toutes s\'ajustent au contenu.
   - Donnez ensuite à la colonne B (Désignation) une largeur exacte de 30.
   - Ajustez aussi les colonnes de la feuille Fournisseurs.
5. Questions avec la barre d\'état
   - Dans la feuille Articles, les cellules jaunes (à droite du tableau) attendent trois réponses. Utilisez uniquement la barre d\'état et tapez le nombre lu :
   - Nombre d\'articles : sélectionnez les codes de la colonne A (sans le titre) et lisez Nombre. Utilisez Ctrl + Maj + ↓.
   - Stock total : sélectionnez les stocks de la colonne H et lisez Somme.
   - Prix le plus élevé : clic droit sur la barre d\'état › cochez Maximum, puis sélectionnez les prix de la colonne G.
6. Enregistrer et comparer
   - Revenez en A1 (Ctrl + Début), enregistrez (Ctrl + S) et comparez avec L01_resultat_attendu.pdf.', 'grille' => '[["Feuilles renommées, colorées, dans le bon ordre, Feuil3 supprimée", "4"], ["Colonne « Ancien code » supprimée, ligne vide insérée", "3"], ["Les 3 articles supprimés ont disparu (lignes entières)", "3"], ["Largeurs de colonnes ajustées, Désignation = 30", "2"], ["Les 3 réponses sont justes", "3"]]' ),
  102 => array( 'titre' => 'Saisir, recopier et déplacer efficacement', 'exercice' => 'Le planning de l\'inventaire tournant', 'fichiers' => 1, 'type' => 'xlsx', 'formation' => 'excel', 'num' => 2, 'mission' => 'Chaque jour ouvré de mars, un emplacement de l\'entrepôt est recompté. Le planning est à moitié vide : complétez-le en laissant Excel travailler pour vous (poignée de recopie, remplissage instantané, copier-coller). Seuls quelques exemples sont à taper ; tout le reste se remplit automatiquement.', 'consignes' => '1. Préparer le fichier
   - Ouvrez L02_donnees_brutes.xlsx et enregistrez-le sous L02_Prénom_Nom.xlsx.
2. Numéroter les contrôles
   - La cellule A4 contient 1. Recopiez-la jusqu\'en A23 pour obtenir les numéros 1 à 20 (pensez à Ctrl ou au bouton d\'options).
3. Dates et jours ouvrés
   - Recopiez la date de B4 jusqu\'en B23, puis choisissez Incrémenter les jours ouvrés : la dernière date doit être le 27.03.2026.
   - Recopiez « lundi » de C4 jusqu\'en C23 et choisissez l\'option qui saute le samedi et le dimanche.
4. Découper les emplacements
   - En E4, tapez l\'allée de l\'emplacement de D4 (la lettre), puis Ctrl + E.
   - Faites de même pour le Niveau : en F4, tapez le dernier chiffre de l\'emplacement, puis Ctrl + E.
   - Vérifiez quelques lignes : « B-03-2 » doit donner B et 2.
5. Répartir les responsables
   - Dans la feuille Équipe, copiez les 4 noms (A2:A5) et collez-les en G4 de la feuille Planning.
   - Sélectionnez G4:G7 et recopiez jusqu\'en G23 : les 4 noms se répètent dans l\'ordre.
6. Déplacer la remarque
   - La remarque a été saisie trop loin, en L30. Déplacez-la en A26 (Ctrl + X puis Ctrl + V, ou en glissant la bordure de la cellule).
7. Collage spécial : transposer
   - Dans la feuille Équipe, copiez A1:A5 et collez-les en C1 avec l\'option Transposer : la liste devient horizontale (C1:G1).
8. Ajuster et enregistrer
   - Ajustez les largeurs de colonnes des deux feuilles, enregistrez et comparez avec L02_resultat_attendu.pdf.', 'grille' => '[["Numéros 1 à 20", "2"], ["Dates : 20 jours ouvrés du 02.03 au 27.03.2026", "3"], ["Jours sans samedi ni dimanche", "2"], ["Allée et niveau correctement extraits", "3"], ["Responsables répétés dans l\'ordre", "2"], ["Remarque déplacée en A26, liste transposée", "3"]]' ),
  103 => array( 'titre' => 'Mettre en forme les cellules', 'exercice' => 'Le bon de livraison', 'fichiers' => 1, 'type' => 'xlsx', 'formation' => 'excel', 'num' => 3, 'mission' => 'Le bon de livraison pour Pharma Léman SA est prêt, mais il est brut et sans aucune mise en forme. Rendez-le professionnel avant impression, en respectant exactement les réglages demandés.', 'consignes' => '1. Préparer le fichier
   - Ouvrez L03_donnees_brutes.xlsx et enregistrez-le sous L03_Prénom_Nom.xlsx.
2. Le titre
   - Sélectionnez A1:F1 et cliquez sur Fusionner et centrer.
   - Titre : taille 20, gras, couleur anthracite #313A45 (Couleur de police › Autres couleurs › Personnalisées › code hexadécimal).
   - Hauteur de la ligne 1 : 36.
3. Le bloc d\'informations
   - Mettez les libellés B3:B6 (N° de bon, Date, Client, Transporteur) en gras.
   - Mettez le numéro de bon (C3) en gras et en couleur standard Rouge foncé.
4. L\'en-tête du tableau
   - Ligne 8 (A8:F8) : remplissage vert LogiProf #067030 (Couleur de remplissage › Autres couleurs › Personnalisées › code hexadécimal), texte blanc et gras.
   - Centrez horizontalement et verticalement, activez Renvoyer à la ligne, hauteur de ligne 32.
5. Les lignes du bon
   - Appliquez Toutes les bordures à A8:F18, puis une Bordure épaisse en encadré sur la même plage.
   - Centrez les colonnes Pos., Unité et Quantité livrée (A9:A18, D9:E18).
   - Remplissage gris très clair (Blanc, Arrière-plan 1, plus sombre 5 %) pour une ligne sur deux : mettez en forme la ligne 10, puis utilisez le pinceau (double-clic) pour les lignes 12, 14, 16 et 18.
6. Le total et la note
   - Ligne 19 : libellé « Nombre total de colis » et le total en gras, libellé aligné à droite, bordure double en bas sur D19:E19.
   - Note de la ligne 21 : fusionnez A21:F21, renvoyer à la ligne, italique, taille 9, hauteur de ligne 30, alignement vertical haut.
7. Finitions
   - Largeurs : A = 6, B = 14, C = 30, D = 9, E = 11, F = 16.
   - Enregistrez et comparez avec L03_resultat_attendu.pdf.', 'grille' => '[["Titre fusionné, 20, gras, anthracite", "2"], ["Bloc d\'informations en gras, n° de bon rouge foncé", "2"], ["En-tête : couleurs, centrage, retour à la ligne, hauteur", "3"], ["Bordures du tableau et encadré épais", "3"], ["Lignes alternées grisées (pinceau)", "2"], ["Total et note correctement mis en forme", "3"]]' ),
  104 => array( 'titre' => 'Les formats de nombres, de dates et de pourcentages', 'exercice' => 'La fiche de stock valorisée', 'fichiers' => 1, 'type' => 'xlsx', 'formation' => 'excel', 'num' => 4, 'mission' => 'L\'export du logiciel de gestion de stock arrive sans aucun format : prix à 5 décimales, dates affichées comme des nombres, codes EAN illisibles, pourcentages en décimales. Appliquez les bons formats sans modifier aucune valeur.', 'consignes' => '1. Préparer le fichier
   - Ouvrez L04_donnees_brutes.xlsx et enregistrez-le sous L04_Prénom_Nom.xlsx.
2. Dates
   - Date d\'édition (B2) : format Date longue.
   - Dernière entrée (D5:D19) : format Date courte.
3. Codes EAN et stock
   - Code EAN (C5:C19) : catégorie Nombre, 0 décimale, sans séparateur de milliers.
   - Stock (E5:E19) : Nombre, 0 décimale, avec séparateur de milliers.
4. Prix et valeurs
   - Prix unitaire (F5:F19) : Nombre, 2 décimales, séparateur de milliers.
   - Valeur du stock (G5:G19) : Monétaire, symbole CHF, 2 décimales.
   - Contrôlez dans la barre de formule que la valeur de F5 a gardé ses 5 décimales.
5. Poids et taux d\'occupation
   - Poids total (H5:H19) : format Personnalisée #\'##0.00 "kg".
   - Taux d\'occupation (I5:I19) : Pourcentage, 1 décimale.
6. Présentation
   - En-têtes ligne 4 : gras, remplissage vert #067030 (Autres couleurs, vu en leçon 3), texte blanc, centrés, renvoyer à la ligne.
   - Bordures : Toutes les bordures sur A4:I19.
   - Ajustez les largeurs de colonnes pour que tout soit lisible (aucun ##### !).
7. Enregistrer et comparer
   - Enregistrez et comparez avec L04_resultat_attendu.pdf.', 'grille' => '[["Dates (longue et courte)", "2"], ["EAN entiers lisibles, stock avec milliers", "3"], ["Prix 2 décimales, valeur en CHF", "3"], ["Poids en kg (format personnalisé), taux en %", "4"], ["Mise en forme et largeurs, aucun #####", "3"]]' ),
  105 => array( 'titre' => 'Premières formules : additionner, soustraire, multiplier', 'exercice' => 'Le bon de commande fournisseur', 'fichiers' => 1, 'type' => 'xlsx', 'formation' => 'excel', 'num' => 5, 'mission' => 'Le stock d\'emballages est bas. Préparez le bon de commande pour Alpaemball SA : calculez les quantités à commander pour revenir au stock maximum, les montants, les poids et les totaux avec la TVA. Une seule formule par colonne, recopiée vers le bas.', 'consignes' => '1. Préparer le fichier
   - Ouvrez L05_donnees_brutes.xlsx et enregistrez-le sous L05_Prénom_Nom.xlsx.
2. Quantité à commander
   - En E5 : stock max − stock actuel (=D5-C5).
   - Recopiez jusqu\'en E12.
3. Montants et poids
   - En G5 : quantité à commander × prix unitaire. Recopiez jusqu\'en G12.
   - En I5 : quantité à commander × poids unitaire. Recopiez jusqu\'en I12.
4. Les totaux
   - G14 (Sous-total) : additionnez les 8 montants avec des + (=G5+G6+…+G12).
   - G15 contient déjà les frais de transport.
   - G16 (Total HT) : sous-total + frais de transport.
   - G17 (TVA) : total HT × le taux de la cellule F17.
   - G18 (Total TTC) : total HT + TVA.
   - I14 : poids total de la commande (additionnez I5 à I12).
5. Contrôler
   - Affichez les formules (Formules › Afficher les formules) et vérifiez que chaque colonne contient la même formule adaptée à sa ligne, puis revenez à l\'affichage normal.
   - Testez : changez le stock actuel de C5 en 50 : la quantité, le montant, les totaux et le poids se recalculent. Remettez 32.
6. Formats
   - Montants (G5:G18) : Nombre, 2 décimales, séparateur de milliers ; Total TTC en gras avec une bordure double en bas.
   - Poids (I5:I14) : 2 décimales.
   - Taux F17 : pourcentage avec 1 décimale.
7. Enregistrer et comparer
   - Enregistrez et comparez avec L05_resultat_attendu.pdf.', 'grille' => '[["Quantités à commander justes et recopiées", "3"], ["Montants et poids justes", "3"], ["Sous-total, total HT, TVA et TTC justes", "5"], ["Aucune valeur tapée à la place d\'une référence", "2"], ["Formats demandés", "2"]]' ),
  106 => array( 'titre' => 'Les fonctions de base : SOMME, MOYENNE, MIN, MAX, NB', 'exercice' => 'Le bilan des réceptions', 'fichiers' => 1, 'type' => 'xlsx', 'formation' => 'excel', 'num' => 6, 'mission' => 'Le chef d\'équipe veut un bilan des 25 réceptions de marchandises de la période. Complétez le tableau de synthèse à droite de la liste uniquement avec des fonctions, ajoutez une ligne de totaux sous la liste et arrondissez les moyennes.', 'consignes' => '1. Préparer le fichier
   - Ouvrez L06_donnees_brutes.xlsx et enregistrez-le sous L06_Prénom_Nom.xlsx.
2. Ligne de totaux
   - En D30, utilisez la somme automatique pour totaliser les palettes.
   - Recopiez la formule vers la droite jusqu\'en G30 (colis, poids, valeur).
   - En H30 : la moyenne des durées de déchargement (menu de la somme automatique).
3. Le tableau de synthèse
   - Complétez les cellules jaunes L5:L15 avec la fonction qui convient à chaque libellé de la colonne K : SOMME, MOYENNE, MAX, MIN, NB ou NBVAL.
   - Pour le nombre de réceptions, comptez les nombres de la colonne Palettes ; pour les réceptions avec remarque, comptez les cellules non vides de la colonne Remarque.
4. Arrondir
   - La durée moyenne (L9) doit être arrondie à 1 décimale : imbriquez votre MOYENNE dans ARRONDI.
   - La valeur moyenne par réception (L15) : arrondie à 0 décimale.
   - Essayez au moins une fois l\'assistant fx.
5. Formats
   - Poids : séparateur de milliers et 1 décimale ; valeurs : 2 décimales et séparateur de milliers.
   - Ligne 30 en gras avec une bordure supérieure ; synthèse K4:L15 avec toutes les bordures.
6. Contrôler et enregistrer
   - Contrôlez deux résultats avec la barre d\'état (sélectionnez la colonne et lisez Somme, Moyenne, Max).
   - Enregistrez et comparez avec L06_resultat_attendu.pdf.', 'grille' => '[["Ligne de totaux (somme automatique + moyenne)", "3"], ["SOMME, MOYENNE, MAX, MIN justes", "4"], ["NB et NBVAL utilisés à bon escient", "3"], ["ARRONDI imbriqué correctement", "3"], ["Formats et bordures", "2"]]' ),
  107 => array( 'titre' => 'Références absolues ($) et calculs de pourcentages', 'exercice' => 'Valoriser le stock et analyser les sorties', 'fichiers' => 1, 'type' => 'xlsx', 'formation' => 'excel', 'num' => 7, 'mission' => 'Le fournisseur annonce une hausse générale de 3.5 %. Calculez la valeur du stock, la part de chaque article, puis l\'impact de la hausse. Dans une deuxième feuille, analysez l\'évolution des sorties par catégorie entre 2025 et 2026. Chaque formule doit pouvoir être recopiée sans erreur.', 'consignes' => '1. Préparer le fichier
   - Ouvrez L07_donnees_brutes.xlsx et enregistrez-le sous L07_Prénom_Nom.xlsx.
2. Valeur du stock
   - Feuille Valorisation, en F5 : stock × prix ; recopiez jusqu\'en F22.
   - En F23 : le total des valeurs (SOMME).
3. Part de chaque article
   - En G5 : valeur de l\'article ÷ total de F23, en bloquant le total avec F4.
   - Recopiez jusqu\'en G22, puis en G23 faites la somme des parts : vous devez obtenir 100 %.
4. Impact de la hausse
   - En H5 : nouveau prix = prix × (1 + hausse de la cellule paramètre C3). Recopiez.
   - En I5 : nouvelle valeur = stock × nouveau prix. Recopiez et totalisez en I23.
   - En H25 : surcoût total = nouvelle valeur totale − valeur totale ; en H26 : surcoût en % de la valeur totale.
   - Testez : mettez 4% en C3, observez, puis remettez 3.5%.
5. Évolution des sorties
   - Feuille Évolution : en D4, l\'écart en CHF (2026 − 2025) ; en E4, l\'évolution en % = écart ÷ 2025. Recopiez jusqu\'à la ligne 10.
   - Ligne 11 : totaux de B, C et D, puis l\'évolution totale en E11 (recopiez E10 vers le bas).
   - En F4 : part de la catégorie dans les sorties 2026 (C4 ÷ total C11 bloqué). Recopiez jusqu\'en F11.
6. Formats
   - Montants : 2 décimales avec séparateur de milliers ; parts, hausse et évolutions : pourcentage, 1 décimale.
   - Mettez en gras les lignes de totaux.
7. Enregistrer et comparer
   - Enregistrez et comparez avec L07_resultat_attendu.pdf.', 'grille' => '[["Valeurs et total justes", "2"], ["Parts justes avec $ (somme = 100 %)", "4"], ["Nouveaux prix et valeurs avec la cellule paramètre", "4"], ["Évolutions et parts de la feuille Évolution", "3"], ["Formats en %", "2"]]' ),
  108 => array( 'titre' => 'Trier, filtrer et figer les volets', 'exercice' => 'Enquête sur les sorties de stock', 'fichiers' => 1, 'type' => 'xlsx', 'formation' => 'excel', 'num' => 8, 'mission' => 'La direction pose cinq questions sur les 200 sorties de stock de l\'année. Répondez-y en triant et en filtrant la liste, et en lisant les résultats dans la barre d\'état. Notez chaque réponse dans la feuille Réponses.', 'consignes' => '1. Préparer le fichier
   - Ouvrez L08_donnees_brutes.xlsx et enregistrez-le sous L08_Prénom_Nom.xlsx.
2. Figer les titres
   - Feuille Sorties : figez les lignes 1 à 3 (cellule A4 › Figer les volets). Descendez jusqu\'en bas pour vérifier.
3. Question 3 : la plus grosse sortie
   - Triez la colonne Valeur CHF du plus grand au plus petit.
   - Notez le n° de bon de la première ligne dans la feuille Réponses (C5).
4. Questions 1, 2 et 4 : filtres simples
   - Activez le filtre automatique.
   - Q1 : filtrez le client Pharma Léman ; lisez le nombre d\'enregistrements trouvés. Effacez le filtre.
   - Q2 : filtrez la catégorie EPI ; sélectionnez les valeurs visibles et lisez la Somme dans la barre d\'état.
   - Q4 : effacez, puis filtrez la colonne Date sur juillet 2026. Effacez ensuite ce filtre.
5. Question 5 : filtres combinés
   - Filtrez la catégorie EPI et la valeur supérieure ou égale à 500 (Filtres numériques). Notez le nombre de sorties.
   - Laissez ce filtre actif : il fait partie du résultat attendu.
6. Tri final sur deux niveaux
   - Avec Données › Trier, triez la liste par Client (A→Z), puis par Valeur CHF (du plus grand au plus petit).
7. Enregistrer et comparer
   - Enregistrez et comparez vos réponses et votre liste avec L08_resultat_attendu.pdf.', 'grille' => '[["Volets figés en A4", "1"], ["Q1 à Q5 justes (2 pts chacune)", "10"], ["Filtre EPI ≥ 500 actif", "2"], ["Tri client / valeur décroissante", "2"]]' ),
  109 => array( 'titre' => 'Les tableaux Excel et l\'impression', 'exercice' => 'L\'état du stock à imprimer', 'fichiers' => 1, 'type' => 'xlsx', 'formation' => 'excel', 'num' => 9, 'mission' => 'Le responsable d\'entrepôt veut un état du stock complet, valorisé et imprimable, qui se mettra à jour facilement quand de nouveaux articles seront ajoutés. Transformez la liste en tableau Excel, ajoutez la valeur et les totaux, puis préparez une impression soignée.', 'consignes' => '1. Préparer le fichier
   - Ouvrez L09_donnees_brutes.xlsx et enregistrez-le sous L09_Prénom_Nom.xlsx.
2. Créer le tableau
   - Cliquez dans la liste et créez un tableau (Ctrl + L) avec en-têtes.
   - Style : le style vert de la section Moyen (Style de tableau moyen 4).
   - Nommez le tableau tblStock.
3. Colonne calculée
   - En I3, tapez le titre Valeur CHF : la colonne rejoint le tableau.
   - En I4, calculez stock × prix en cliquant sur les cellules (références structurées) : la formule se recopie seule.
   - Format de la colonne : 2 décimales avec séparateur de milliers.
4. Ligne de total
   - Cochez Ligne Total.
   - Sous Valeur CHF : Somme ; sous Code : Nombre (remplacez « Total ») ; sous Stock : Somme.
   - Testez : filtrez la catégorie Emballage et regardez les totaux changer, puis effacez le filtre.
5. Mise en page
   - Orientation Paysage, ajusté à 1 page en largeur, hauteur automatique.
   - Lignes à répéter en haut : $3:$3.
   - Pied de page : à gauche le nom de la feuille, à droite Page x sur y.
   - Contrôlez dans l\'aperçu (Ctrl + P) : les titres doivent apparaître en haut de la page 2.
6. Enregistrer et comparer
   - Enregistrez et comparez avec L09_resultat_attendu.pdf.', 'grille' => '[["Tableau créé, style vert et nom tblStock", "3"], ["Colonne calculée Valeur juste et formatée", "3"], ["Ligne de total : nombre, somme stock, somme valeur", "3"], ["Paysage, 1 page en largeur, titres répétés", "4"], ["Pied de page demandé", "2"]]' ),
  110 => array( 'titre' => 'Les fonctions conditionnelles : SI, NB.SI, SOMME.SI', 'exercice' => 'Contrôle des stocks et proposition de commande', 'fichiers' => 1, 'type' => 'xlsx', 'formation' => 'excel', 'num' => 10, 'mission' => 'Chaque lundi, le magasinier contrôle les 74 articles. Automatisez ce contrôle : Excel doit signaler les articles à commander, calculer les quantités et les montants, puis résumer la commande par catégorie dans la feuille Synthèse.', 'consignes' => '1. Préparer le fichier
   - Ouvrez L10_donnees_brutes.xlsx et enregistrez-le sous L10_Prénom_Nom.xlsx.
2. Le statut
   - Feuille Stock, en I4 : avec SI, affichez À commander si le stock est inférieur ou égal au stock min, sinon OK.
   - Recopiez jusqu\'à la dernière ligne (double-clic sur la poignée).
3. Le niveau
   - En J4, avec SI.CONDITIONS : Critique si stock ≤ stock min ; Faible si stock ≤ 2 × stock min ; sinon Correct. Recopiez.
4. Quantité et montant
   - En K4 : si le statut est « À commander », stock max − stock, sinon 0. Recopiez.
   - En L4 : quantité à commander × prix. Recopiez.
   - Format de L : 2 décimales, séparateur de milliers.
5. La synthèse par catégorie
   - Feuille Synthèse, ligne 5 (Emballage), avec des données de la feuille Stock et des plages bloquées :
   - B5 : nombre d\'articles de la catégorie (NB.SI) ;
   - C5 : nombre d\'articles de la catégorie à commander (NB.SI.ENS) ;
   - D5 : montant à commander pour la catégorie (SOMME.SI sur la colonne Montant) ;
   - E5 : nombre d\'articles de niveau Critique de la catégorie (NB.SI.ENS).
   - Recopiez B5:E5 jusqu\'à la ligne 11, puis faites les totaux en ligne 12 (SOMME).
6. Contrôle croisé
   - En B14 : nombre total d\'articles « À commander » de la feuille Stock (NB.SI). Il doit être égal à C12.
   - En B15 : montant total de la commande (SOMME de la colonne L). Il doit être égal à D12.
7. Formats et enregistrement
   - Montants en CHF avec 2 décimales ; totaux en gras.
   - Enregistrez et comparez avec L10_resultat_attendu.pdf.', 'grille' => '[["Statut avec SI juste", "3"], ["Niveau avec SI.CONDITIONS juste", "3"], ["Quantités et montants justes", "3"], ["Synthèse NB.SI / NB.SI.ENS / SOMME.SI justes et recopiées", "4"], ["Contrôle croisé cohérent", "2"]]' ),
  111 => array( 'titre' => 'La mise en forme conditionnelle', 'exercice' => 'Le tableau de bord du stock', 'fichiers' => 1, 'type' => 'xlsx', 'formation' => 'excel', 'num' => 11, 'mission' => 'Le tableau de contrôle des stocks est complet, mais tout se ressemble. Ajoutez des mises en forme conditionnelles pour que le chef d\'équipe voie en un coup d\'œil les articles à commander, les plus gros montants et les emplacements presque vides. Aucune formule à écrire dans les cellules : tout est déjà calculé.', 'consignes' => '1. Préparer le fichier
   - Ouvrez L11_donnees_brutes.xlsx et enregistrez-le sous L11_Prénom_Nom.xlsx.
2. Les articles à commander
   - Sélectionnez I4:I77 (Statut).
   - Règle Texte qui contient « À commander », format Remplissage rouge clair avec texte rouge foncé.
3. Les stocks bas
   - Sélectionnez F4:F77 (Stock) : règle Inférieur à 20, format Texte rouge.
4. Les montants
   - Sélectionnez L4:L77 : barres de données, dégradé vert.
   - Sur la même plage, Règles des valeurs plus/moins élevées › 10 valeurs les plus élevées : réglez sur 5, format Remplissage vert avec texte vert foncé.
5. Le taux de remplissage
   - Sélectionnez M4:M77 : jeu d\'icônes « 3 flèches (couleur) ».
   - Par défaut, Excel découpe en tiers entre la plus petite et la plus grande valeur. Modifiez la règle (Gérer les règles › Modifier la règle) : type Nombre, flèche verte si ≥ 0.67, jaune si ≥ 0.33.
6. Les lignes critiques
   - Sélectionnez A4:M77 puis Nouvelle règle › Utiliser une formule : =$J4="Critique", remplissage orange très clair (Orange, Accentuation 2, plus clair 80 %).
7. Vérifier les règles
   - Ouvrez Gérer les règles (Cette feuille de calcul) : vous devez voir 6 règles. Supprimez les éventuels doublons.
   - Testez : mettez le stock de l\'article EM-1001 (F4) à 15 : la ligne doit changer d\'apparence. Remettez 290.
8. Enregistrer et comparer
   - Enregistrez et comparez avec L11_resultat_attendu.pdf.', 'grille' => '[["« À commander » en rouge (texte qui contient)", "2"], ["Stock < 20 en texte rouge", "2"], ["Barres de données + top 5 sur les montants", "3"], ["Jeu d\'icônes 3 flèches", "2"], ["Lignes critiques par formule", "4"], ["Aucune règle en double", "2"]]' ),
  112 => array( 'titre' => 'Rechercher des informations : RECHERCHEV et listes déroulantes', 'exercice' => 'Le bon de préparation de commande', 'fichiers' => 1, 'type' => 'xlsx', 'formation' => 'excel', 'num' => 12, 'mission' => 'Le préparateur ne doit saisir que le code et la quantité : toutes les autres informations du bon de préparation doivent venir automatiquement du catalogue (feuille Articles). Les codes de la commande CMD-26-0871 sont déjà saisis ; 5 lignes restent libres pour compléter la commande.', 'consignes' => '1. Préparer le fichier
   - Ouvrez L12_donnees_brutes.xlsx et enregistrez-le sous L12_Prénom_Nom.xlsx.
2. La désignation
   - Feuille Préparation, en C6 : =RECHERCHEV($B6;Articles!$A$4:$I$77;2;FAUX) (sélectionnez la plage à la souris dans la feuille Articles, puis F4).
   - Recopiez jusqu\'en C20 : les lignes vides affichent #N/A.
3. Les autres colonnes
   - Emplacement (D6, colonne 5), Unité (E6, colonne 6) et Prix (G6, colonne 7) : même formule en changeant seulement le numéro de colonne.
   - Entourez chaque formule de SIERREUR(…;"") pour masquer les #N/A, puis recopiez jusqu\'à la ligne 20.
4. Montant et poids
   - H6 : montant = quantité × prix, avec =SI(B6="";"";…) pour les lignes vides.
   - I6 : poids = quantité × poids unitaire (colonne 9 du catalogue), même principe.
   - Ligne 21 : totaux du montant et du poids (SOMME).
5. Les listes déroulantes
   - Sélectionnez B6:B20 : validation Liste, source =Articles!$A$4:$A$77.
   - Sélectionnez F6:F20 : validation Nombre entier supérieur à 0, alerte d\'erreur « La quantité doit être un nombre entier positif ».
6. Compléter la commande
   - Le client ajoute deux articles : en B16, choisissez EP-1005 (quantité 8) et en B17, BU-1006 (quantité 12). Toutes les informations doivent apparaître seules.
   - Essayez de taper 2.5 en F18 : la saisie doit être refusée. Laissez F18 vide.
7. Formats et enregistrement
   - Prix et montants : 2 décimales et séparateur de milliers ; poids : 2 décimales.
   - Enregistrez et comparez avec L12_resultat_attendu.pdf.', 'grille' => '[["RECHERCHEV juste (FAUX, plage bloquée)", "4"], ["Toutes les colonnes remplies automatiquement", "3"], ["SIERREUR / SI pour les lignes vides", "3"], ["Validation liste et nombre entier", "3"], ["Commande complétée, totaux justes", "2"]]' ),
  113 => array( 'titre' => 'Créer des graphiques parlants', 'exercice' => 'Le tableau de bord des sorties 2026', 'fichiers' => 1, 'type' => 'xlsx', 'formation' => 'excel', 'num' => 13, 'mission' => 'La direction souhaite une page de synthèse visuelle des sorties de l\'année. À partir des données mensuelles, par catégorie et par client, créez quatre graphiques soignés et rassemblez-les sur la feuille Tableau de bord, prête à imprimer.', 'consignes' => '1. Préparer le fichier
   - Ouvrez L13_donnees_brutes.xlsx et enregistrez-le sous L13_Prénom_Nom.xlsx.
2. Histogramme des valeurs mensuelles
   - Feuille Données : sélectionnez A3:A15 puis, avec Ctrl, C3:C15.
   - Insérez un histogramme groupé. Titre : Valeur des sorties par mois (CHF). Supprimez la légende.
3. Courbe du nombre de bons
   - Sélectionnez A3:B15 et insérez une courbe avec marqueurs.
   - Titre : Nombre de bons de sortie par mois ; titre de l\'axe vertical : Bons ; pas de légende.
4. Secteurs par catégorie
   - Sélectionnez E3:F10 et insérez un graphique en secteurs.
   - Titre : Répartition de la valeur par catégorie ; étiquettes de données en pourcentage ; légende à droite.
5. Barres par client
   - Sélectionnez H3:I9 (déjà trié) et insérez des barres groupées.
   - Titre : Valeur des sorties par client (CHF) ; étiquettes de données ; pas de légende.
6. Le tableau de bord
   - Déplacez les 4 graphiques dans la feuille Tableau de bord (Déplacer le graphique › Objet dans).
   - Disposez-les en grille 2 × 2 sous le titre, de taille identique (onglet Format du graphique : hauteur 7.5 cm, largeur 12 cm).
   - Harmonisez les couleurs en vert : pour chaque graphique, Création de graphique › Modifier les couleurs › Palette monochrome 3 (les verts).
   - Mise en page : paysage, ajusté sur 1 page.
7. Tester et enregistrer
   - Feuille Données : changez la valeur de juillet en 30000 et observez l\'histogramme. Annulez (Ctrl + Z).
   - Enregistrez et comparez avec L13_resultat_attendu.pdf.', 'grille' => '[["Histogramme mensuel juste et titré", "3"], ["Courbe avec marqueurs et titre d\'axe", "3"], ["Secteurs avec % et légende", "3"], ["Barres par client avec étiquettes", "3"], ["Tableau de bord disposé, couleurs vertes, imprimable", "3"]]' ),
  114 => array( 'titre' => 'Les tableaux croisés dynamiques', 'exercice' => 'Analyser les sorties de l\'année', 'fichiers' => 1, 'type' => 'xlsx', 'formation' => 'excel', 'num' => 14, 'mission' => 'Les 200 sorties de stock de 2026 sont dans un tableau (tblSorties). La direction veut trois analyses : la valeur par catégorie avec sa part en %, la valeur par mois et par client, et le nombre de bons par client. Construisez-les avec des tableaux croisés dynamiques, sans aucune formule.', 'consignes' => '1. Préparer le fichier
   - Ouvrez L14_donnees_brutes.xlsx et enregistrez-le sous L14_Prénom_Nom.xlsx.
2. TCD 1 : valeur par catégorie
   - Cliquez dans le tableau, Insertion › Tableau croisé dynamique, nouvelle feuille. Renommez la feuille TCD Catégories.
   - Lignes : Catégorie ; Valeurs : Valeur CHF (Somme).
   - Triez les catégories de la plus grande à la plus petite valeur.
   - Format de nombre du champ : 2 décimales avec séparateur de milliers.
3. Part en % du total
   - Glissez une seconde fois Valeur CHF dans Valeurs.
   - Paramètres : affichez-la en % du total général et nommez-la % de Valeur CHF.
4. TCD 2 : valeur par mois et par client
   - Nouveau TCD sur une feuille TCD Mois : Lignes = Date groupée par Mois ; Colonnes = Client ; Valeurs = Somme de Valeur CHF.
   - Format : séparateur de milliers, 0 décimale.
5. TCD 3 : nombre de bons par client
   - Nouveau TCD sur une feuille TCD Clients : Lignes = Client ; Valeurs = N° bon (calcul Nombre).
   - Ajoutez Catégorie dans la zone Filtres et testez le filtre sur EPI, puis revenez à (Tous).
6. Graphique et actualisation
   - Depuis le TCD 1, insérez un graphique croisé dynamique en histogramme (série Somme de Valeur CHF).
   - Test : dans la feuille Sorties, changez la quantité de la première ligne en 1000, actualisez et observez. Annulez ensuite le changement et actualisez à nouveau.
7. Enregistrer et comparer
   - Enregistrez et comparez vos trois TCD avec L14_resultat_attendu.pdf.', 'grille' => '[["TCD 1 : catégories triées, somme formatée", "4"], ["% du total général juste", "3"], ["TCD 2 : mois × clients", "3"], ["TCD 3 : nombre de bons + filtre catégorie", "3"], ["Graphique croisé dynamique", "2"]]' ),
  115 => array( 'titre' => 'L\'analyse ABC du stock', 'exercice' => 'L\'analyse ABC de l\'entrepôt de Bussigny', 'fichiers' => 1, 'type' => 'xlsx', 'formation' => 'excel', 'num' => 15, 'mission' => 'Le responsable veut revoir la gestion des 40 articles de consommables les plus utilisés. Réalisez l\'analyse ABC complète de leur valeur de consommation 2026 : classement, synthèse, couleurs et diagramme de Pareto. Vous mobilisez ici presque toutes les compétences du cours : les étapes sont volontairement moins guidées.', 'consignes' => '1. Préparer le fichier
   - Ouvrez L15_donnees_brutes.xlsx et enregistrez-le sous L15_Prénom_Nom.xlsx.
2. Valeur annuelle
   - En F7:F46 : consommation annuelle × prix unitaire.
   - En F47 : le total.
3. Trier
   - Triez la liste A6:F46 par Valeur annuelle CHF, du plus grand au plus petit (sans inclure la ligne de total !).
4. % de la valeur et % cumulé
   - En G7:G46 : part de chaque article dans le total (référence absolue).
   - En H7 : =G7 ; en H8 : cumul précédent + % de la ligne, recopié jusqu\'en H46. Vérifiez : H46 = 100 %.
   - Formats : pourcentage avec 1 décimale.
5. Classe A, B ou C
   - En I7:I46 : la classe avec deux SI imbriqués (ou SI.CONDITIONS) et les seuils des cellules C3 et C4 bloqués.
   - Centrez la colonne.
6. Synthèse
   - Tableau L6:P10 : pour chaque classe, le nombre d\'articles (NB.SI), le % des articles, la valeur (SOMME.SI) et le % de la valeur. Ligne 10 : totaux (100 %).
7. Couleurs
   - Sur I7:I46 et L7:L9 : MFC Égal à « A » remplissage vert, « B » jaune, « C » rouge clair.
8. Diagramme de Pareto
   - Sélectionnez A6:A46, F6:F46 et H6:H46 (avec Ctrl).
   - Graphique combiné : valeur en histogramme groupé, % cumulé en courbe sur l\'axe secondaire (max 100 %).
   - Couleurs (double-clic sur une série › Remplissage / Trait › Autres couleurs) : barres vert #067030, courbe anthracite #313A45.
   - Titre : Diagramme de Pareto – analyse ABC 2026. Déplacez-le sur la feuille Pareto.
9. Conclusions
   - Dans la feuille ABC, en L13, choisissez dans la liste déroulante la classe qui doit être inventoriée le plus souvent ; en L14, la classe dont les commandes peuvent être groupées.
   - Testez : passez le seuil A à 70 %, observez la synthèse, puis remettez 80 %.
   - Mise en page : paysage, 1 page en largeur, titres répétés (ligne 6). Enregistrez et comparez avec L15_resultat_attendu.pdf.', 'grille' => '[["Valeur annuelle et total justes", "2"], ["Tri décroissant correct", "2"], ["% et % cumulé justes (100 % en fin)", "3"], ["Classes justes avec seuils paramétrables", "3"], ["Synthèse NB.SI / SOMME.SI juste", "3"], ["MFC par classe", "2"], ["Diagramme de Pareto combiné", "3"], ["Conclusions et mise en page", "2"]]' ),
);
