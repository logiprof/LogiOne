<?php
/** Données des 15 exercices PowerPoint (clés 201 à 215 = leçons PowerPoint 1 à 15). */
if ( ! defined( 'LIR_STANDALONE' ) && ! defined( 'ABSPATH' ) ) { exit; }
return array(
  201 => array( 'titre' => 'Découvrir PowerPoint et ses affichages', 'exercice' => 'La visite de l\'entrepôt', 'fichiers' => 1, 'type' => 'pptx', 'formation' => 'powerpoint', 'num' => 1, 'mission' => 'Pour l\'accueil des nouveaux apprentis, un collègue a préparé la présentation de la visite de l\'entrepôt… mais les diapositives sont dans le désordre, l\'une est en double et une diapositive interne ne doit pas être montrée. Remettez la présentation en ordre sans retaper le contenu.', 'consignes' => '1. Ouvrir et enregistrer
   - Ouvrez L01_donnees_brutes.pptx et enregistrez-le sous L01_Prénom_Nom.pptx.
   - Repérez les zones de l\'écran : combien de diapositives contient la présentation ?
2. Supprimer le doublon
   - Passez en Trieuse de diapositives.
   - La diapositive « 2. Le stockage » est en double : supprimez la deuxième.
3. Remettre dans l\'ordre
   - Faites glisser les diapositives pour obtenir cet ordre :
   - - Visite de l\'entrepôt de Bussigny
      - Programme de la visite
      - Sécurité pendant la visite
      - 1. La réception
      - 2. Le stockage
      - 3. La préparation
      - 4. L\'expédition
      - Questions ?
      - Horaires internes
4. Dupliquer et masquer
   - Revenez en affichage Normal. Dupliquez la diapositive « Programme de la visite » et placez la copie juste avant « Questions ? ».
   - Dans la copie, remplacez le titre par Récapitulatif.
   - Masquez la diapositive « Horaires internes » (elle doit rester la dernière).
5. Tester le diaporama
   - Lancez le diaporama depuis le début (F5), avancez jusqu\'à la fin : la diapositive masquée ne doit pas apparaître.
   - Sélectionnez la diapositive 4 et lancez le diaporama depuis la diapositive actuelle (Maj+F5). Quittez avec Échap.
   - Essayez aussi le Mode Lecture.
6. Enregistrer et comparer
   - Enregistrez et comparez avec L01_resultat_attendu.pdf (la diapositive masquée n\'y figure pas).', 'grille' => '[["Doublon supprimé (10 diapositives au total)", "2"], ["Ordre des diapositives", "4"], ["Diapositive « Récapitulatif » à la bonne place", "2"], ["Diapositive « Horaires internes » masquée", "2"]]' ),
  202 => array( 'titre' => 'Créer une présentation à partir d\'un plan', 'exercice' => 'Le service Réception', 'fichiers' => 1, 'type' => 'pptx', 'formation' => 'powerpoint', 'num' => 2, 'mission' => 'Le responsable du service Réception doit présenter son service aux nouveaux apprentis. Il n\'a écrit que la diapositive de titre et son plan, dans les notes de cette diapositive. Construisez la présentation à partir de ce plan, avec les bonnes dispositions et trois sections.', 'consignes' => '1. Ouvrir et enregistrer
   - Ouvrez L02_donnees_brutes.pptx et enregistrez-le sous L02_Prénom_Nom.pptx.
   - Lisez les notes de la diapositive 1 : elles contiennent le plan et le texte de la diapositive de comparaison.
2. Construire le plan
   - Dans les notes, copiez le plan (de « Nos missions » à « Étiquette palette avec code-barres »).
   - Passez en Mode Plan, cliquez à la fin du sous-titre de la diapositive 1, Entrée, Maj+Tab pour revenir au niveau titre, puis collez (Ctrl+V).
   - Avec Tab et Maj+Tab, mettez chaque ligne au bon niveau : 3 titres de diapositives (Nos missions, Les étapes de la réception, Les documents), leurs puces, et 2 sous-puces sous « Contrôle des marchandises ».
3. La diapositive de comparaison
   - Revenez en affichage Normal. Après « Les étapes de la réception », ajoutez une diapositive avec la disposition Comparaison.
   - Titre : Contrôle quantitatif et qualitatif. Remplissez les deux colonnes avec le texte des notes.
4. La diapositive de fin
   - À la fin, ajoutez une diapositive En-tête de section : titre Questions ?, texte Service Réception · Colibri Logistique SA.
5. Les sections
   - Créez 3 sections : Introduction (diapositives 1 et 2), Le travail au quai (3 à 5) et Conclusion (6).
   - Réduisez puis développez les sections pour vérifier.
6. Enregistrer et comparer
   - Contrôlez l\'ensemble en Trieuse de diapositives, enregistrez et comparez avec L02_resultat_attendu.pdf.', 'grille' => '[["6 diapositives dans le bon ordre", "3"], ["Bonnes dispositions (titre, comparaison, en-tête de section)", "3"], ["Niveaux de texte (2 sous-puces)", "2"], ["3 sections bien nommées", "2"]]' ),
  203 => array( 'titre' => 'Mettre en forme le texte et les listes', 'exercice' => 'Les règles de sécurité de l\'entrepôt', 'fichiers' => 1, 'type' => 'pptx', 'formation' => 'powerpoint', 'num' => 3, 'mission' => 'Le responsable sécurité a écrit sa présentation… comme un document Word : un long paragraphe illisible, une liste sans niveaux et un message important oublié. Rendez-la lisible à distance en appliquant la règle des 6 × 6. Les textes à utiliser se trouvent dans les notes des diapositives.', 'consignes' => '1. Ouvrir et enregistrer
   - Ouvrez L03_donnees_brutes.pptx et enregistrez-le sous L03_Prénom_Nom.pptx.
2. Raccourcir le texte (diapositive 2)
   - Lisez le long paragraphe : combien de lignes et de mots ? Est-il lisible à 5 mètres ?
   - Remplacez-le par les 6 règles courtes des notes de la diapositive (copier-coller).
   - Transformez la liste en liste numérotée « 1. 2. 3. » et réglez la taille à 28 pt si besoin.
3. Créer les niveaux (diapositive 3)
   - Les lignes « Obligatoire dans tout l\'entrepôt », « Classe S3… », « Pour les palettes… » et « Zone de stockage… » sont des précisions : abaissez-les d\'un niveau avec Tab.
4. Ajouter une zone de texte (diapositive 4)
   - Sous la liste, ajoutez une zone de texte avec le message des notes : Respectez les zones piétonnes !
   - Mettez-la en gras, 24 pt, centrée, avec un remplissage jaune (couleur du thème).
   - Ajustez sa largeur à celle de la liste.
5. Harmoniser
   - Vérifiez qu\'aucun texte n\'est plus petit que 20 pt.
   - Utilisez le pinceau si vous voulez recopier une mise en forme d\'un texte à l\'autre.
6. Enregistrer et comparer
   - Lancez le diaporama pour juger la lisibilité, enregistrez et comparez avec L03_resultat_attendu.pdf.', 'grille' => '[["Paragraphe remplacé par 6 règles courtes", "3"], ["Liste numérotée", "2"], ["Niveaux de la diapositive 3", "2"], ["Zone de texte mise en valeur", "3"]]' ),
  204 => array( 'titre' => 'Thèmes, couleurs et arrière-plans', 'exercice' => 'La présentation à la clientèle', 'fichiers' => 1, 'type' => 'pptx', 'formation' => 'powerpoint', 'num' => 4, 'mission' => 'Le service commercial utilise encore une présentation de 2015 : format 4:3, thème Office par défaut. Mettez-la aux couleurs de Colibri Logistique SA : vert #067030 et jaune #F5C400, titres en Arial, texte en Calibri, et une photo de l\'entrepôt en fond de la diapositive de titre.', 'consignes' => '1. Ouvrir et enregistrer
   - Décompressez le fichier ZIP, ouvrez L04_donnees_brutes.pptx et enregistrez-le sous L04_Prénom_Nom.pptx.
2. Passer en 16:9
   - Création › Taille des diapositives › Grand écran (16:9), puis Ajuster.
   - Parcourez les 5 diapositives : rien ne doit dépasser.
3. Les couleurs de l\'entreprise
   - Création › Variantes ▾ › Couleurs › Personnaliser les couleurs.
   - Accentuation 1 : Autres couleurs › Personnalisées › Hex #067030 ; Accentuation 2 : #F5C400 ; Texte/arrière-plan Foncé 2 : #0B4D22.
   - Nom : Colibri, puis Enregistrer.
4. Les polices
   - Création › Variantes ▾ › Polices › Personnaliser les polices : titre Arial, corps Calibri, nom Colibri.
5. L\'arrière-plan de la diapositive de titre
   - Sélectionnez la diapositive 1 puis Création › Format de l\'arrière-plan.
   - Remplissage avec image › Insérer… › À partir d\'un fichier : choisissez P-ENTREPOT.jpg.
   - Transparence : 60 %. Ne cliquez pas sur Appliquer partout.
6. Enregistrer et comparer
   - Lancez le diaporama (F5), enregistrez et comparez avec L04_resultat_attendu.pdf.', 'grille' => '[["Format 16:9", "2"], ["Couleurs du thème personnalisées « Colibri »", "4"], ["Polices Arial / Calibri", "2"], ["Image en arrière-plan de la diapositive 1", "2"]]' ),
  205 => array( 'titre' => 'Insérer et retoucher des images et des icônes', 'exercice' => 'Le nouveau quai de chargement', 'fichiers' => 1, 'type' => 'pptx', 'formation' => 'powerpoint', 'num' => 5, 'mission' => 'Le chef d\'exploitation présente le nouveau quai de chargement à l\'équipe. Sa présentation n\'a que du texte : illustrez-la avec les photos et icônes fournies, sans déformer les images, et rendez-la accessible.', 'consignes' => '1. Ouvrir et enregistrer
   - Décompressez le ZIP, ouvrez L05_donnees_brutes.pptx et enregistrez-le sous L05_Prénom_Nom.pptx.
2. Diapositive de titre
   - Insérez P-QUAI.jpg, placez-la sur la moitié droite de la diapositive, sur toute la hauteur.
   - Rognez-la si besoin pour qu\'elle ne déborde pas de la diapositive.
3. Diapositive 2 : style d\'image
   - Cliquez sur l\'icône Images de l\'espace réservé de droite et insérez P-PALETTE.jpg.
   - Appliquez le style Rectangle à coins arrondis avec ombre.
4. Diapositive 3 : photo centrée
   - Insérez P-CAMION.jpg, agrandissez-la par un angle (largeur ≈ 20 cm) et appliquez le style Cadre simple, blanc.
   - Centrez-la : Organiser › Aligner › Centrer.
5. Diapositive 4 : icônes
   - Au-dessus de chaque chiffre clé, insérez une icône : un camion, un colis, une horloge (Insertion › Icônes, ou les fichiers ICO fournis).
   - Donnez-leur la même taille (≈ 4 cm) et alignez-les en haut.
6. Textes de remplacement
   - Pour chaque image et icône : clic droit › Afficher le texte de remplacement et décrivez-la en une phrase.
   - Contrôlez avec Révision › Vérifier l\'accessibilité : aucune image ne doit être signalée.
7. Enregistrer et comparer
   - Enregistrez et comparez avec L05_resultat_attendu.pdf.', 'grille' => '[["Images insérées sur les diapositives 1, 2 et 3", "3"], ["Images non déformées, styles appliqués", "2"], ["3 icônes alignées sur la diapositive 4", "2"], ["Textes de remplacement complets", "3"]]' ),
  206 => array( 'titre' => 'Formes et graphiques SmartArt', 'exercice' => 'L\'organisation de l\'entrepôt', 'fichiers' => 1, 'type' => 'pptx', 'formation' => 'powerpoint', 'num' => 6, 'mission' => 'Cette présentation sert à l\'accueil des nouveaux collaborateurs. L\'organigramme et le flux des marchandises sont de simples listes : transformez-les en schémas clairs, et ajoutez une bulle de sécurité.', 'consignes' => '1. Ouvrir et enregistrer
   - Ouvrez L06_donnees_brutes.pptx et enregistrez-le sous L06_Prénom_Nom.pptx.
2. L\'organigramme (diapositive 2)
   - Cliquez dans la liste, puis Accueil › Convertir en graphique SmartArt › Autres graphiques SmartArt › Hiérarchie › Organigramme.
   - Vérifiez que la Direction est en haut et les 3 services en dessous.
   - Appliquez un style SmartArt et les couleurs Couleur 1 (verts).
3. Le flux des marchandises (diapositive 3)
   - Supprimez la liste et changez la disposition en Titre seul.
   - Dessinez un Pentagone (flèche) puis 4 Chevrons de même taille, et écrivez une étape dans chacun : Réception, Contrôle, Stockage, Préparation, Expédition.
   - Sélectionnez-les : Aligner au milieu, Distribuer horizontalement puis Grouper.
   - Donnez-leur des couleurs du thème différentes.
4. Une bulle (diapositive 4)
   - Insérez une forme Bulle : rectangle à coins arrondis sous la photo, avec le texte Klaxonnez à chaque croisement !
   - Remplissage jaune (Accentuation 2), texte noir en gras. Déplacez la poignée jaune pour que la pointe désigne la photo.
5. Enregistrer et comparer
   - Enregistrez et comparez avec L06_resultat_attendu.pdf. Votre organigramme est un SmartArt : son aspect peut être un peu différent, c\'est normal.', 'grille' => '[["Organigramme (SmartArt) correct", "4"], ["Flux en 5 formes alignées et distribuées", "4"], ["Bulle de sécurité", "2"]]' ),
  207 => array( 'titre' => 'Présenter des données dans un tableau', 'exercice' => 'Les tarifs de transport', 'fichiers' => 1, 'type' => 'pptx', 'formation' => 'powerpoint', 'num' => 7, 'mission' => 'Le service Transport prépare sa présentation des tarifs 2027. Les tarifs sont dans une simple zone de texte et le tableau des volumes est brut. Mettez les deux au propre.', 'consignes' => '1. Ouvrir et enregistrer
   - Ouvrez L07_donnees_brutes.pptx et enregistrez-le sous L07_Prénom_Nom.pptx.
2. Le tableau des tarifs (diapositive 2)
   - Changez la disposition en Titre seul (le texte reste dans une zone à part : vous la supprimerez à la fin).
   - Insérez un tableau de 4 colonnes × 5 lignes et recopiez les tarifs (une ligne de texte = une ligne du tableau).
   - Supprimez ensuite la zone de texte d\'origine.
3. Mettre en forme les tarifs
   - Taille du texte : 20 pt. Élargissez la colonne Région pour qu\'elle tienne sur une ligne.
   - Alignez la colonne Prix par palette à droite.
4. Le tableau des volumes (diapositive 3)
   - Appliquez un style vert de la galerie, cochez Ligne d\'en-tête, Lignes à bandes et Ligne Total.
   - Ajoutez une colonne à droite Trimestre : 425, 299, 634 et 1 358.
   - Alignez tous les nombres à droite.
5. Enregistrer et comparer
   - Enregistrez et comparez avec L07_resultat_attendu.pdf.', 'grille' => '[["Tableau des tarifs 4 × 5 complet", "4"], ["Mise en forme et alignements", "2"], ["Style, ligne Total et colonne Trimestre", "4"]]' ),
  208 => array( 'titre' => 'Illustrer des chiffres avec des graphiques', 'exercice' => 'Les chiffres de l\'entrepôt', 'fichiers' => 1, 'type' => 'pptx', 'formation' => 'powerpoint', 'num' => 8, 'mission' => 'La direction présente les chiffres du semestre. Les données sont là, mais sous forme de tableau, de liste ou dans le mauvais graphique. Transformez-les en trois graphiques clairs.', 'consignes' => '1. Ouvrir et enregistrer
   - Ouvrez L08_donnees_brutes.pptx et enregistrez-le sous L08_Prénom_Nom.pptx.
2. Les commandes (diapositive 2)
   - Insérez un histogramme groupé. Dans la feuille Excel, saisissez les 6 mois et le nombre de commandes du tableau (supprimez les séries 2 et 3 d\'exemple).
   - Fermez la feuille, supprimez le tableau, agrandissez le graphique sur toute la diapositive.
   - Ajoutez les étiquettes de données, supprimez le titre du graphique et la légende.
3. Les emplacements (diapositive 3)
   - Passez la diapositive en Titre seul, insérez un graphique en secteurs avec les 3 catégories de la liste (62, 23 et 15) et supprimez la liste.
   - Affichez les étiquettes en pourcentage et la légende à droite.
4. Le taux de service (diapositive 4)
   - Le taux de service évolue dans le temps : Modifier le type de graphique › Courbes avec marques.
   - Ajoutez les étiquettes de données au-dessus des points.
5. Enregistrer et comparer
   - Vérifiez que tous les textes des graphiques font au moins 16 pt, enregistrez et comparez avec L08_resultat_attendu.pdf.', 'grille' => '[["Histogramme des commandes avec étiquettes", "4"], ["Secteurs des emplacements en %", "3"], ["Courbe du taux de service", "3"]]' ),
  209 => array( 'titre' => 'Chronologies, cycles et schémas reliés', 'exercice' => 'Le projet du nouvel entrepôt', 'fichiers' => 1, 'type' => 'pptx', 'formation' => 'powerpoint', 'num' => 9, 'mission' => 'Colibri construit un nouvel entrepôt à Écublens. Le chef de projet a préparé trois listes : transformez-les en une chronologie, un cycle et un schéma relié par des connecteurs.', 'consignes' => '1. Ouvrir et enregistrer
   - Ouvrez L09_donnees_brutes.pptx et enregistrez-le sous L09_Prénom_Nom.pptx.
2. La chronologie (diapositive 2)
   - Transformez la liste en chronologie : Convertir en graphique SmartArt › Chronologie de base, ou dessinez-la avec des formes.
   - Chaque jalon affiche la date en gras et l\'étape. Mettez la mise en service en jaune.
3. Le cycle (diapositive 3)
   - Convertissez la liste en SmartArt Cycle de base.
   - Choisissez des couleurs variées (Modifier les couleurs › Coloré) et un style SmartArt.
4. Le schéma relié (diapositive 4)
   - Passez en Titre seul. Dessinez 4 rectangles arrondis de même taille avec les 4 étapes de la liste, alignés et distribués.
   - Reliez-les par des connecteurs droits avec flèche (épaisseur 3 pt). Déplacez une forme : le connecteur doit suivre.
   - Ajoutez dessous : « Délai moyen : 4 h entre la commande et le départ du camion ».
5. Volet Sélection
   - Ouvrez le Volet Sélection sur la diapositive 4 et renommez les 4 formes avec le nom de leur étape.
6. Enregistrer et comparer
   - Enregistrez et comparez avec L09_resultat_attendu.pdf.', 'grille' => '[["Chronologie des 5 jalons", "4"], ["Cycle PDCA", "3"], ["4 formes reliées par des connecteurs", "3"]]' ),
  210 => array( 'titre' => 'Le masque des diapositives : logo, pieds de page et numéros', 'exercice' => 'La présentation aux couleurs de Colibri', 'fichiers' => 1, 'type' => 'pptx', 'formation' => 'powerpoint', 'num' => 10, 'mission' => 'Les ressources humaines utilisent cette présentation pour l\'accueil des apprentis. Ajoutez le logo de l\'entreprise sur toutes les diapositives, mettez les titres en vert foncé et numérotez les diapositives, sans toucher les diapositives une par une.', 'consignes' => '1. Ouvrir et enregistrer
   - Décompressez le ZIP, ouvrez L10_donnees_brutes.pptx et enregistrez-le sous L10_Prénom_Nom.pptx.
2. Le logo sur le masque
   - Affichage › Masque des diapositives, cliquez sur la grande miniature du haut.
   - Insertion › Images › Cet appareil › LOGO.png. Réduisez-le à 4,6 cm de large et placez-le en haut à droite.
3. La couleur des titres
   - Toujours sur le masque, sélectionnez le cadre du titre : couleur Vert foncé, Texte 2 et gras.
4. Fermer le masque et vérifier
   - Fermer le mode Masque. Parcourez les diapositives : le logo et les titres verts apparaissent partout.
   - Si le logo chevauche un titre, retournez dans le masque et réduisez la largeur du cadre de titre.
5. Numéros et pied de page
   - Insertion › En-tête/Pied : cochez Numéro de diapositive et Pied de page « Colibri Logistique SA · Formation interne ».
   - Cochez Ne pas afficher sur la diapositive de titre, puis Appliquer partout.
6. Enregistrer et comparer
   - Enregistrez et comparez avec L10_resultat_attendu.pdf.', 'grille' => '[["Logo sur le masque", "3"], ["Titres en vert foncé via le masque", "2"], ["Numéros de diapositive", "2"], ["Pied de page, absent de la diapositive de titre", "3"]]' ),
  211 => array( 'titre' => 'Transitions et animations', 'exercice' => 'La sécurité au quai de chargement', 'fichiers' => 1, 'type' => 'pptx', 'formation' => 'powerpoint', 'num' => 11, 'mission' => 'Le chef d\'équipe Expédition présente chaque lundi les règles de sécurité. Rendez sa présentation vivante mais sobre : une même transition partout, les règles qui apparaissent une à une et les 3 réflexes qui s\'enchaînent tout seuls.', 'consignes' => '1. Ouvrir et enregistrer
   - Ouvrez L11_donnees_brutes.pptx et enregistrez-le sous L11_Prénom_Nom.pptx.
2. Une transition pour toutes les diapositives
   - Onglet Transitions : Fondu, durée 00,70, puis Appliquer partout.
   - Vérifiez qu\'une étoile apparaît à côté de chaque miniature.
3. Les règles une à une (diapositive 2)
   - Sélectionnez le cadre de la liste : animation d\'entrée Fondu, Options d\'effet › Par paragraphe.
   - Lancez le diaporama depuis cette diapositive (Maj+F5) : une règle par clic.
4. Les 3 réflexes (diapositive 3)
   - Animez les 3 icônes avec Fondu, dans l\'ordre bouclier, camion, colis.
   - Ouvrez le Volet Animation. Camion et colis : Démarrer › Après la précédente, délai 00,50.
5. Contrôler
   - Lancez tout le diaporama (F5) et vérifiez l\'enchaînement : aucune animation sur les titres, aucun effet tape-à-l\'œil.
6. Enregistrer et comparer
   - Enregistrez et comparez avec L11_resultat_attendu.pdf (le PDF ne montre pas les animations : comparez surtout le contenu).', 'grille' => '[["Même transition sur toutes les diapositives", "3"], ["Liste animée par paragraphe", "4"], ["3 icônes animées après la précédente", "3"]]' ),
  212 => array( 'titre' => 'Liens, boutons d\'action et vidéo', 'exercice' => 'Le guide de l\'apprenti', 'fichiers' => 1, 'type' => 'pptx', 'formation' => 'powerpoint', 'num' => 12, 'mission' => 'Ce guide est consulté par les apprentis sur un écran de la salle de pause : ils doivent pouvoir y naviguer seuls. Créez un sommaire cliquable, des boutons de retour, un lien vers le site de la Suva et ajoutez la vidéo du quai.', 'consignes' => '1. Ouvrir et enregistrer
   - Décompressez le ZIP, ouvrez L12_donnees_brutes.pptx et enregistrez-le sous L12_Prénom_Nom.pptx.
2. Le sommaire (diapositive 2)
   - Sélectionnez la forme « 1. Mon premier jour » › Insertion › Lien › Emplacement dans ce document › 3. Mon premier jour.
   - Faites de même pour « 2. La sécurité » (diapositive 4) et « 3. Le quai en vidéo » (diapositive 5).
   - Mettez les 3 formes en vert (Accentuation 1).
3. Les boutons de retour
   - Sur la diapositive 3, dessinez un bouton d\'action Accueil en bas à droite (1,8 × 1,8 cm) : lien vers la diapositive 2. Sommaire. Remplissage jaune.
   - Copiez-le (Ctrl+C) et collez-le sur les diapositives 4 et 5 : il garde son lien.
4. Le lien vers la Suva (diapositive 4)
   - Sélectionnez le texte www.suva.ch › Insertion › Lien › adresse https://www.suva.ch.
5. La vidéo (diapositive 5)
   - Insertion › Vidéo › Cet appareil › CLIP-QUAI.mp4. Agrandissez-la (20 cm de large) et centrez-la.
   - Lecture › Début : Automatiquement.
6. Tester et enregistrer
   - Lancez le diaporama (F5) et testez chaque lien et chaque bouton. Enregistrez et comparez avec L12_resultat_attendu.pdf.', 'grille' => '[["Sommaire : 3 liens vers les bonnes diapositives", "3"], ["Boutons de retour au sommaire", "2"], ["Lien vers le site de la Suva", "2"], ["Vidéo insérée, démarrage automatique", "3"]]' ),
  213 => array( 'titre' => 'Préparer son oral : notes, mode Présentateur et minutage', 'exercice' => 'L\'exposé sur l\'inventaire annuel', 'fichiers' => 1, 'type' => 'pptx', 'formation' => 'powerpoint', 'num' => 13, 'mission' => 'Vous présenterez l\'organisation de l\'inventaire à l\'équipe. Les diapositives sont prêtes, mais rien n\'est préparé pour l\'oral : ajoutez vos notes, masquez l\'annexe et répétez avec le minutage.', 'consignes' => '1. Ouvrir et enregistrer
   - Ouvrez L13_donnees_brutes.pptx et enregistrez-le sous L13_Prénom_Nom.pptx.
2. Écrire les notes
   - Ouvrez le volet Notes. Pour chacune des diapositives 2 à 5, écrivez au moins une phrase de notes : ce que vous direz, un exemple concret.
   - Exemple pour la diapositive 3 : « Le 30 décembre, l\'entrepôt est fermé aux livraisons. Montrer le planning affiché à la réception. »
3. Masquer l\'annexe
   - Masquez la diapositive 6 « Annexe : répartition des binômes » (Diaporama › Masquer la diapositive).
4. Essayer le mode Présentateur
   - Lancez Alt+F5 : repérez vos notes, la diapositive suivante et le chronomètre.
   - Pendant le diaporama, tapez 6 puis Entrée pour afficher l\'annexe masquée, puis B pour l\'écran noir.
5. Répéter avec le minutage
   - Diaporama › Vérification du minutage : présentez à voix haute. À la fin, conservez les minutages. Votre exposé doit durer entre 4 et 6 minutes.
6. Enregistrer
   - Dans Diaporama › Configurer le diaporama, choisissez Manuellement pour que les minutages n\'avancent pas tout seuls le jour de l\'exposé. Enregistrez.', 'grille' => '[["Notes sur les diapositives 2 à 5", "6"], ["Annexe masquée", "2"], ["Contenu des diapositives conservé", "2"]]' ),
  214 => array( 'titre' => 'Finaliser, imprimer, exporter et partager', 'exercice' => 'L\'offre de service pour un client', 'fichiers' => 1, 'type' => 'pptx', 'formation' => 'powerpoint', 'num' => 14, 'mission' => 'Le commercial doit envoyer aujourd\'hui une offre à la Laiterie du Jorat. La présentation contient encore des commentaires internes, une photo de 3,4 Mo sans texte de remplacement et aucune propriété. Préparez-la pour l\'envoi.', 'consignes' => '1. Ouvrir et enregistrer
   - Ouvrez L14_donnees_brutes.pptx et enregistrez-le sous L14_Prénom_Nom.pptx. Notez sa taille dans l\'Explorateur.
2. Lire puis supprimer les commentaires
   - Révision › Afficher les commentaires : lisez-les. La température indiquée est-elle à corriger ? (Non : 2 à 6 °C est correct.)
   - Révision › Supprimer › Supprimer tous les commentaires de cette présentation.
3. Orthographe et accessibilité
   - Lancez Révision › Orthographe (F7).
   - Lancez Vérifier l\'accessibilité et ajoutez le texte de remplacement manquant de la photo du quai.
4. Compresser les images
   - Sélectionnez la photo de la diapositive 3 › Format de l\'image › Compresser les images : toutes les images, Web (150 ppp).
   - Enregistrez et comparez la taille du fichier avec celle du début.
5. Propriétés
   - Fichier › Informations : Titre Offre de service Colibri – Laiterie du Jorat, Mots clés logistique; offre; stockage frigorifique.
   - Inspecter le document pour vérifier qu\'il ne reste rien de caché.
6. Exporter en PDF
   - Fichier › Exporter › Créer un document PDF/XPS : enregistrez L14_Prénom_Nom.pdf et ouvrez-le pour le contrôler.
   - Enregistrez la présentation et remettez le fichier .pptx.', 'grille' => '[["Commentaires supprimés", "2"], ["Images compressées (fichier allégé)", "3"], ["Texte de remplacement complété", "2"], ["Titre et mots clés renseignés", "3"]]' ),
  215 => array( 'titre' => 'Projet final : la présentation de l\'inauguration', 'exercice' => 'La présentation de l\'inauguration', 'fichiers' => 1, 'type' => 'pptx', 'formation' => 'powerpoint', 'num' => 15, 'mission' => 'Colibri Logistique SA inaugure son nouvel entrepôt d\'Écublens le 4 février 2028. La direction vous confie la présentation de 5 minutes devant les invités. Toutes les informations sont dans les notes de la première diapositive du fichier de départ. À vous de jouer, en appliquant tout ce que vous avez appris.', 'consignes' => '1. Lire le dossier et faire le plan
   - Décompressez le ZIP, ouvrez L15_donnees_brutes.pptx, enregistrez-le sous L15_Prénom_Nom.pptx et lisez les notes de la diapositive 1.
   - Écrivez sur papier le titre de chacune de vos 8 à 12 diapositives.
2. Le cadre
   - Supprimez la diapositive « Votre mission » quand vous n\'en avez plus besoin.
   - Couleurs du thème Colibri (vert #067030, jaune #F5C400), logo LOGO.png sur le masque.
   - Numéros de diapositive et pied de page, sauf sur la diapositive de titre.
3. Le contenu
   - Une diapositive de titre et une diapositive de conclusion.
   - Au moins 3 images (avec texte de remplacement), 1 tableau (le projet en chiffres), 1 graphique (la capacité de stockage) et 1 schéma (la chronologie).
   - Règle des 6 × 6, texte ≥ 20 pt.
4. L\'oral
   - Une même transition sobre sur toutes les diapositives.
   - Des notes d\'orateur sur chaque diapositive.
   - Répétez avec Vérification du minutage : entre 4 et 6 minutes.
5. Relire et remettre
   - Passez la liste de contrôle, lancez Orthographe et Vérifier l\'accessibilité, renseignez le titre dans les propriétés.
   - Remettez L15_Prénom_Nom.pptx. Le corrigé L15_resultat_attendu.pdf n\'est qu\'un exemple : votre présentation peut être différente.', 'grille' => '[["Structure : 8 à 12 diapositives, titre et conclusion", "2"], ["Cadre : thème, logo sur le masque, numéros", "3"], ["Contenu : images accessibles, tableau, graphique, schéma", "6"], ["Oral : transitions et notes", "3"], ["Qualité générale (évaluée par l\'enseignant)", "6"]]' ),
);
