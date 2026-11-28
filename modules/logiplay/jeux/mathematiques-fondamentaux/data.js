/* Mathématiques – Fondamentaux (modules 1 à 3). a[0] = bonne réponse. Généré par build_data.py. */
window.OBJ = [
{
"code": "Mod. 1a",
"t": "Longueurs et masses",
"g": "Convertir des longueurs et des masses avec les tableaux d’unités",
"col": "teal",
"ic": "ruler",
"ch": [
{
"k": "qcm",
"img": "longueurs",
"q": "Dans le tableau des longueurs, que fait-on à chaque pas vers une unité plus petite (vers la droite) ?",
"a": [
"On multiplie par 10",
"On multiplie par 100",
"On divise par 10",
"On multiplie par 1’000"
],
"ex": "km → hm → dam → m → dm → cm → mm : chaque pas vers la droite = × 10, chaque pas vers la gauche = ÷ 10.",
"ref": "Module 1 · Conversions de longueurs"
},
{
"k": "num",
"q": "Convertissez 3.5 km en mètres.",
"n": 3500,
"tol": 0,
"u": "m",
"ex": "km → m = 3 pas vers la droite : 3.5 × 10 × 10 × 10 = 3’500 m.",
"ref": "Module 1 · Conversions de longueurs"
},
{
"k": "num",
"q": "Convertissez 2.4 m en centimètres.",
"n": 240,
"tol": 0,
"u": "cm",
"ex": "m → cm = 2 pas vers la droite : 2.4 × 100 = 240 cm.",
"ref": "Module 1 · Conversions de longueurs"
},
{
"k": "num",
"q": "Convertissez 1’250 mm en centimètres.",
"n": 125,
"tol": 0,
"u": "cm",
"ex": "mm → cm = 1 pas vers la gauche : 1’250 ÷ 10 = 125 cm.",
"ref": "Module 1 · Conversions de longueurs"
},
{
"k": "qcm",
"q": "Une palette EUR mesure 1’200 mm de long. Combien cela fait-il en mètres ?",
"a": [
"1.2 m",
"12 m",
"0.12 m",
"120 m"
],
"ex": "mm → m = 3 pas vers la gauche : 1’200 ÷ 1’000 = 1.2 m.",
"ref": "Module 1 · Conversions de longueurs"
},
{
"k": "case",
"s": "Le magasinier de Stockalp SA range des cartons de 35 cm de large côte à côte sur une tablette de rayonnage longue de 4.2 m.",
"q": "Combien de cartons tiennent sur la longueur de la tablette ?",
"a": [
"12 cartons",
"120 cartons",
"14 cartons",
"1 carton"
],
"ex": "On met tout dans la même unité : 4.2 m = 420 cm. 420 ÷ 35 = 12 cartons.",
"ref": "Module 1 · Conversions de longueurs"
},
{
"k": "order",
"q": "Rangez ces unités de longueur de la plus grande à la plus petite.",
"a": [
"km",
"dam",
"m",
"cm",
"mm"
],
"ex": "Ordre du tableau : km, hm, dam, m, dm, cm, mm.",
"ref": "Module 1 · Conversions de longueurs"
},
{
"k": "match",
"q": "Associez chaque unité à son équivalence.",
"p": [
[
"1 km",
"1’000 m"
],
[
"1 m",
"100 cm"
],
[
"1 cm",
"10 mm"
],
[
"1 dam",
"10 m"
]
],
"ex": "Chaque pas du tableau vaut × 10 : 1 m = 10 dm = 100 cm = 1’000 mm.",
"ref": "Module 1 · Conversions de longueurs"
},
{
"k": "tf",
"q": "Pour convertir 5 mm en mètres, on multiplie par 1’000.",
"v": false,
"ex": "Faux : mm → m, c’est vers une unité plus grande, donc on divise : 5 ÷ 1’000 = 0.005 m.",
"ref": "Module 1 · Conversions de longueurs"
},
{
"k": "tf",
"q": "1 hg (hectogramme) vaut 10 g.",
"v": false,
"ex": "Faux : 1 hg = 100 g. C’est 1 dag (décagramme) qui vaut 10 g.",
"ref": "Module 1 · Conversions de masses"
},
{
"k": "qcm",
"q": "Combien de kilogrammes y a-t-il dans 1 tonne ?",
"a": [
"1’000 kg",
"100 kg",
"10 kg",
"10’000 kg"
],
"ex": "1 t = 1’000 kg. Dans le tableau t, q, kg : 1 t = 10 q et 1 q = 100 kg.",
"ref": "Module 1 · Conversions de masses"
},
{
"k": "qcm",
"q": "Que vaut un quintal (q) ?",
"a": [
"100 kg",
"10 kg",
"1’000 kg",
"50 kg"
],
"ex": "Le quintal se place entre la tonne et le kilogramme : 1 q = 100 kg.",
"ref": "Module 1 · Conversions de masses"
},
{
"k": "num",
"q": "Convertissez 2.75 t en kilogrammes.",
"n": 2750,
"tol": 0,
"u": "kg",
"ex": "t → kg : × 1’000. 2.75 × 1’000 = 2’750 kg.",
"ref": "Module 1 · Conversions de masses"
},
{
"k": "num",
"q": "Convertissez 2’500 dag en kilogrammes.",
"n": 25,
"tol": 0,
"u": "kg",
"ex": "dag → kg = 2 pas vers la gauche (dag → hg → kg) : 2’500 ÷ 10 ÷ 10 = 25 kg. Erreur fréquente : répondre 250 kg (un seul pas).",
"ref": "Module 1 · Conversions de masses"
},
{
"k": "case",
"s": "Un camion de TransRhône livre trois lots : 600 kg de vis, 1.4 t de tôles et 2’500 dag d’étiquettes. Le chauffeur doit noter la masse totale sur le bon.",
"q": "Quelle masse totale doit-il inscrire ?",
"a": [
"2’025 kg",
"2’250 kg",
"2’000 kg",
"4’500 kg"
],
"ex": "On convertit tout en kg : 600 kg + 1’400 kg + 25 kg = 2’025 kg. 2’500 dag = 25 kg, pas 250 kg.",
"ref": "Module 1 · Conversions de masses"
},
{
"k": "fill",
"q": "Pour convertir des grammes en kilogrammes, on ___ par 1’000.",
"a": [
"divise",
"multiplie",
"additionne",
"soustrait"
],
"ex": "g → kg : vers une unité plus grande, on divise. 3’400 g = 3.4 kg.",
"ref": "Module 1 · Conversions de masses"
},
{
"k": "sort",
"q": "Classez chaque masse : moins de 1 kg ou 1 kg et plus ?",
"c": [
"Moins de 1 kg",
"1 kg et plus"
],
"it": [
[
"950 g",
0
],
[
"12 hg",
1
],
[
"80 dag",
0
],
[
"0.001 t",
1
],
[
"1’500’000 mg",
1
],
[
"9 hg",
0
]
],
"ex": "12 hg = 1.2 kg ; 80 dag = 0.8 kg ; 0.001 t = 1 kg ; 1’500’000 mg = 1.5 kg ; 9 hg = 0.9 kg.",
"ref": "Module 1 · Conversions de masses"
},
{
"k": "odd",
"q": "Trouvez l’intrus : laquelle de ces longueurs n’est PAS égale à 1.5 m ?",
"a": [
"15 cm",
"150 cm",
"1’500 mm",
"15 dm"
],
"ex": "1.5 m = 15 dm = 150 cm = 1’500 mm. 15 cm ne vaut que 0.15 m.",
"ref": "Module 1 · Conversions de longueurs"
},
{
"k": "case",
"s": "Un chariot élévateur a une capacité de 1.2 t. Le cariste doit lever une palette de 850 kg sur laquelle on a ajouté 3 cartons de 120 kg.",
"q": "Peut-il lever cette charge ?",
"a": [
"Non : 1’210 kg, dépassement de 10 kg",
"Oui : 1’210 kg, il reste 10 kg",
"Oui : 850 kg sont sous 1.2 t",
"Non : 1’210 kg dépassent 12 t"
],
"ex": "1.2 t = 1’200 kg. 850 + 3 × 120 = 1’210 kg > 1’200 kg : dépassement de 10 kg. Il faut retirer un carton.",
"ref": "Module 1 · Conversions de masses"
},
{
"k": "case",
"s": "Au poste d’expédition, un préparateur emballe 18 colis identiques de 750 g pour le même client.",
"q": "Quelle est la masse totale de l’envoi ?",
"a": [
"13.5 kg",
"135 kg",
"1.35 kg",
"13’500 kg"
],
"ex": "18 × 750 g = 13’500 g = 13.5 kg (÷ 1’000).",
"ref": "Module 1 · Conversions de masses"
},
{
"k": "multi",
"q": "Quelles masses valent exactement 2 kg ?",
"good": [
"2’000 g",
"20 hg",
"200 dag",
"2’000’000 mg"
],
"bad": [
"0.2 t",
"20’000 mg"
],
"ex": "2 kg = 20 hg = 200 dag = 2’000 g = 2’000’000 mg. 0.2 t = 200 kg ; 20’000 mg = 20 g.",
"ref": "Module 1 · Conversions de masses"
},
{
"k": "case",
"s": "Pour rejoindre l’entrepôt annexe, le cariste roule 0.6 km sur la route de service, puis encore 450 m dans la cour.",
"q": "Quelle distance parcourt-il ?",
"a": [
"1’050 m",
"1’050 km",
"456 m",
"0.105 km"
],
"ex": "On convertit avant d’additionner : 0.6 km = 600 m. 600 + 450 = 1’050 m.",
"ref": "Module 1 · Conversions de longueurs"
},
{
"k": "case",
"s": "Entre le quai de réception et la zone de stockage, il y a 85 m. Ce matin, le cariste a fait 12 allers-retours avec son chariot.",
"q": "Quelle distance a-t-il parcourue ?",
"a": [
"2.04 km",
"1.02 km",
"20.4 km",
"0.204 km"
],
"ex": "Un aller-retour = 2 × 85 = 170 m. 12 × 170 = 2’040 m = 2.04 km.",
"ref": "Module 1 · Conversions de longueurs"
},
{
"k": "case",
"s": "Un camion doit passer sous la porte d’un entrepôt haute de 3.8 m. Le chargement : 3 palettes empilées de 1’350 mm chacune, posées sur le plateau.",
"q": "Le chargement (sans le camion) dépasse-t-il déjà la hauteur de la porte ?",
"a": [
"Oui : 4.05 m > 3.8 m",
"Non : 4.05 m < 3.8 m",
"Non : 1’350 mm < 3.8 m",
"Oui : 4’050 m > 3.8 m"
],
"ex": "3 × 1’350 = 4’050 mm = 4.05 m. C’est plus que 3.8 m : il faut dépiler.",
"ref": "Module 1 · Conversions de longueurs"
},
{
"k": "case",
"s": "Pour une commande, on a préparé 250 rouleaux de film étirable de 2.4 kg chacun. Le transporteur demande la masse en tonnes.",
"q": "Quelle masse communiquez-vous ?",
"a": [
"0.6 t",
"6 t",
"0.06 t",
"60 t"
],
"ex": "250 × 2.4 = 600 kg. 600 ÷ 1’000 = 0.6 t.",
"ref": "Module 1 · Conversions de masses"
},
{
"k": "case",
"s": "Le bon de livraison indique trois colis : 12.5 kg, 8’400 g et 95 hg. Vous devez contrôler la masse totale.",
"q": "Quelle est la masse totale ?",
"a": [
"30.4 kg",
"125.9 kg",
"3.04 kg",
"304 kg"
],
"ex": "8’400 g = 8.4 kg ; 95 hg = 9.5 kg. 12.5 + 8.4 + 9.5 = 30.4 kg.",
"ref": "Module 1 · Conversions de masses"
},
{
"k": "num",
"q": "Convertissez 4’500 kg en quintaux.",
"n": 45,
"tol": 0,
"u": "q",
"ex": "kg → q : ÷ 100. 4’500 ÷ 100 = 45 q.",
"ref": "Module 1 · Conversions de masses"
},
{
"k": "tf",
"q": "1 dam (décamètre) vaut 10 m.",
"v": true,
"ex": "Vrai : km, hm, dam, m. 1 dam = 10 m ; 1 hm = 100 m.",
"ref": "Module 1 · Conversions de longueurs"
}
]
},
{
"code": "Mod. 1b",
"t": "Force et poids",
"g": "Calculer le poids (force en newtons) d’une charge à partir de sa masse, et l’inverse",
"col": "indigo",
"ic": "weight",
"ch": [
{
"k": "qcm",
"q": "Dans quelle unité exprime-t-on une force, par exemple le poids d’une charge ?",
"a": [
"En newtons (N)",
"En kilogrammes (kg)",
"En joules (J)",
"En pascals (Pa)"
],
"ex": "La masse s’exprime en kg ; le poids est une force et s’exprime en newtons (N).",
"ref": "Module 1 · Force et poids"
},
{
"k": "tf",
"q": "La masse (en kg) et le poids (en N) sont deux grandeurs différentes.",
"v": true,
"ex": "Vrai : la masse est la quantité de matière (kg) ; le poids est la force exercée par cette masse (N).",
"ref": "Module 1 · Force et poids"
},
{
"k": "fill",
"q": "Le poids d’une charge se calcule avec la formule F = m × ___ (en N/kg).",
"a": [
"9.81",
"0.981",
"98.1",
"3.6"
],
"ex": "F (N) = m (kg) × 9.81. En pratique, on arrondit souvent à 10 pour estimer.",
"ref": "Module 1 · Force et poids"
},
{
"k": "num",
"q": "Avec l’arrondi pratique g ≈ 10 N/kg, quel est le poids d’une caisse de 50 kg ?",
"n": 500,
"tol": 0,
"u": "N",
"ex": "50 × 10 = 500 N (calcul exact : 50 × 9.81 = 490.5 N).",
"ref": "Module 1 · Force et poids"
},
{
"k": "qcm",
"q": "Quel est le poids exact d’un carton de 20 kg (g = 9.81 N/kg) ?",
"a": [
"196.2 N",
"1’962 N",
"2.04 N",
"19.62 N"
],
"ex": "20 × 9.81 = 196.2 N.",
"ref": "Module 1 · Force et poids"
},
{
"k": "num",
"q": "Combien de newtons vaut 1 kN ?",
"n": 1000,
"tol": 0,
"u": "N",
"ex": "kilo = 1’000 : 1 kN = 1’000 N.",
"ref": "Module 1 · Force et poids"
},
{
"k": "num",
"q": "Convertissez 2.5 kN en newtons.",
"n": 2500,
"tol": 0,
"u": "N",
"ex": "2.5 × 1’000 = 2’500 N.",
"ref": "Module 1 · Force et poids"
},
{
"k": "qcm",
"q": "Une charge exerce un poids de 981 N. Quelle est sa masse ?",
"a": [
"100 kg",
"98.1 kg",
"9’624 kg",
"10 kg"
],
"ex": "m = F ÷ 9.81 = 981 ÷ 9.81 = 100 kg. Erreur fréquente : diviser par 10 au lieu de 9.81, ce qui donne 98.1 kg.",
"ref": "Module 1 · Force et poids"
},
{
"k": "case",
"s": "Le transpalette manuel de l’atelier a une capacité de 2’000 kg. Le dynamomètre indique que la palette à déplacer exerce une force de 17’658 N.",
"q": "Le transpalette peut-il la déplacer ?",
"a": [
"Oui : 1’800 kg, il reste 200 kg",
"Non : 17’658 kg dépassent 2’000 kg",
"Oui : 176.58 kg seulement",
"Non : 1’800 kg dépassent 2’000 kg"
],
"ex": "m = 17’658 ÷ 9.81 = 1’800 kg ≤ 2’000 kg : c’est possible, avec une marge de 200 kg.",
"ref": "Module 1 · Force et poids"
},
{
"k": "case",
"s": "Une tablette de rayonnage supporte 3 kN au maximum. Un préparateur veut y poser 4 cartons de 80 kg. Il estime le poids avec g ≈ 10 N/kg.",
"q": "Peut-il poser les 4 cartons ?",
"a": [
"Non : 3’200 N dépassent 3’000 N",
"Oui : 320 N restent sous 3 kN",
"Oui : 3’200 N restent sous 3 kN",
"Non : 320 kg dépassent 3 kg"
],
"ex": "4 × 80 = 320 kg → 320 × 10 = 3’200 N = 3.2 kN > 3 kN. Il faut en mettre 3 au maximum.",
"ref": "Module 1 · Force et poids"
},
{
"k": "match",
"q": "Associez chaque masse à son poids approximatif (g ≈ 10 N/kg).",
"p": [
[
"1 kg",
"≈ 10 N"
],
[
"50 kg",
"≈ 500 N"
],
[
"100 kg",
"≈ 1 kN"
],
[
"1 t",
"≈ 10 kN"
]
],
"ex": "Poids ≈ masse × 10 : 1 t = 1’000 kg → ≈ 10’000 N = 10 kN.",
"ref": "Module 1 · Force et poids"
},
{
"k": "order",
"q": "Remettez dans l’ordre le calcul du poids d’une charge donnée en tonnes.",
"a": [
"Convertir la masse en kg",
"Multiplier par 9.81",
"Écrire le résultat en N",
"Convertir en kN si nécessaire (÷ 1’000)"
],
"ex": "Exemple : 1.5 t = 1’500 kg ; 1’500 × 9.81 = 14’715 N = 14.715 kN.",
"ref": "Module 1 · Force et poids"
},
{
"k": "sort",
"q": "Classez chaque valeur : masse ou force (poids) ?",
"c": [
"Masse",
"Force (poids)"
],
"it": [
[
"1.2 t",
0
],
[
"450 kg",
0
],
[
"12 kN",
1
],
[
"981 N",
1
],
[
"750 g",
0
],
[
"4’000 N",
1
]
],
"ex": "t, kg et g sont des unités de masse ; N et kN sont des unités de force.",
"ref": "Module 1 · Force et poids"
},
{
"k": "odd",
"q": "Trouvez l’intrus : laquelle n’est PAS une unité de force ?",
"a": [
"kg",
"N",
"kN",
"daN"
],
"ex": "Le kilogramme est une unité de masse. N, kN et daN (décanewton) mesurent une force.",
"ref": "Module 1 · Force et poids"
},
{
"k": "tf",
"q": "Sur la Lune, la masse d’un colis est plus petite que sur la Terre.",
"v": false,
"ex": "Faux : la masse ne change pas. C’est le poids (la force) qui change, car l’attraction est plus faible.",
"ref": "Module 1 · Force et poids"
},
{
"k": "num",
"q": "Avec g ≈ 10 N/kg, quel est le poids d’une caisse de 1.5 t, en kN ?",
"n": 15,
"tol": 0,
"u": "kN",
"ex": "1.5 t = 1’500 kg → 1’500 × 10 = 15’000 N = 15 kN.",
"ref": "Module 1 · Force et poids"
},
{
"k": "qcm",
"q": "Quel est le poids exact d’une palette de 600 kg ?",
"a": [
"5’886 N",
"6’000 N",
"61.2 N",
"588.6 N"
],
"ex": "600 × 9.81 = 5’886 N. 6’000 N est l’estimation avec g ≈ 10.",
"ref": "Module 1 · Force et poids"
},
{
"k": "case",
"s": "Un palan de l’atelier supporte au maximum 5 kN. Le mécanicien doit soulever un moteur de 450 kg pour le poser sur une palette.",
"q": "Le palan convient-il ?",
"a": [
"Oui : 4’414.5 N < 5’000 N",
"Non : 4’414.5 N > 5’000 N",
"Non : 450 kg > 5 kg",
"Oui : 45.9 N < 5 kN"
],
"ex": "F = 450 × 9.81 = 4’414.5 N, soit 4.41 kN : c’est sous la limite de 5 kN.",
"ref": "Module 1 · Force et poids"
},
{
"k": "case",
"s": "La fiche technique d’un appareil de levage indique une force de levage maximale de 25 kN. Le chef d’équipe veut connaître la masse maximale à lever.",
"q": "Quelle masse maximale peut-on lever ?",
"a": [
"Environ 2’548 kg",
"Environ 245 kg",
"Environ 25 kg",
"Environ 245’250 kg"
],
"ex": "25 kN = 25’000 N. m = 25’000 ÷ 9.81 ≈ 2’548 kg (environ 2.5 t).",
"ref": "Module 1 · Force et poids"
},
{
"k": "case",
"s": "Un collègue calcule le poids d’une palette de 300 kg et écrit sur sa fiche : « 300 ÷ 9.81 = 30.6 N ».",
"q": "Que lui répondez-vous ?",
"a": [
"Faux : il faut multiplier, 2’943 N",
"Juste : le poids vaut 30.6 N",
"Faux : il faut diviser par 10",
"Juste, mais il faut l’écrire en kg"
],
"ex": "Poids = masse × 9.81 = 300 × 9.81 = 2’943 N. On divise seulement pour passer d’une force à une masse.",
"ref": "Module 1 · Force et poids"
},
{
"k": "num",
"q": "Une charge pèse 3’000 N. Avec g ≈ 10 N/kg, quelle est sa masse ?",
"n": 300,
"tol": 0,
"u": "kg",
"ex": "m ≈ 3’000 ÷ 10 = 300 kg (exact : 3’000 ÷ 9.81 ≈ 305.8 kg).",
"ref": "Module 1 · Force et poids"
},
{
"k": "multi",
"q": "Quelles affirmations sont justes ?",
"good": [
"Le poids s’exprime en newtons",
"1 kg pèse environ 10 N",
"1 kN = 1’000 N"
],
"bad": [
"1 N = 1 kg",
"Le poids se calcule en divisant la masse par 9.81"
],
"ex": "Poids (N) = masse (kg) × 9.81 ; 1 kg pèse 9.81 N, pas 1 N.",
"ref": "Module 1 · Force et poids"
},
{
"k": "case",
"s": "Chaque niveau d’un rayonnage supporte 5 kN. On y stocke des cartons de 40 kg. Pour le calcul, l’entreprise utilise g ≈ 10 N/kg.",
"q": "Combien de cartons peut-on poser au maximum par niveau ?",
"a": [
"12 cartons",
"13 cartons",
"125 cartons",
"1 carton"
],
"ex": "1 carton ≈ 40 × 10 = 400 N. 5’000 ÷ 400 = 12.5 → on arrondit vers le bas : 12 cartons.",
"ref": "Module 1 · Force et poids"
},
{
"k": "qcm",
"q": "Une force de 4’905 N correspond à quelle masse ?",
"a": [
"500 kg",
"48’118 kg",
"490.5 kg",
"50 kg"
],
"ex": "m = 4’905 ÷ 9.81 = 500 kg.",
"ref": "Module 1 · Force et poids"
},
{
"k": "case",
"s": "Un pont roulant est autorisé jusqu’à 2 t. Le capteur de la charge suspendue affiche 22 kN.",
"q": "La charge est-elle autorisée ?",
"a": [
"Non : environ 2’243 kg > 2’000 kg",
"Oui : environ 224 kg < 2’000 kg",
"Oui : 22 kN restent sous la limite de 2 t",
"Non : 22 kN correspondent à 22 t"
],
"ex": "22 kN = 22’000 N. m = 22’000 ÷ 9.81 ≈ 2’243 kg : c’est plus que 2’000 kg. On ne lève pas.",
"ref": "Module 1 · Force et poids"
},
{
"k": "case",
"s": "Un conteneur de 1’200 kg est posé sur le plancher d’une plateforme de chargement. Le responsable sécurité veut la force exercée en kN.",
"q": "Quelle force le conteneur exerce-t-il ?",
"a": [
"11.77 kN",
"11’772 kN",
"122.32 kN",
"1.22 kN"
],
"ex": "1’200 × 9.81 = 11’772 N = 11.77 kN.",
"ref": "Module 1 · Force et poids"
}
]
},
{
"code": "Mod. 1c",
"t": "Pourcentages",
"g": "Calculer une part, un taux et une valeur après hausse ou rabais",
"col": "violet",
"ic": "percent",
"ch": [
{
"k": "qcm",
"q": "À quelle fraction correspond 25 % ?",
"a": [
"1/4",
"1/25",
"2/5",
"1/2"
],
"ex": "25 % = 25/100 = 1/4.",
"ref": "Module 1 · Pourcentages"
},
{
"k": "num",
"q": "Calculez 15 % de 400 colis.",
"n": 60,
"tol": 0,
"u": "colis",
"ex": "Part = total × taux ÷ 100 = 400 × 15 ÷ 100 = 60 colis.",
"ref": "Module 1 · Pourcentages"
},
{
"k": "num",
"q": "Calculez 10 % de CHF 2’500.",
"n": 250,
"tol": 0,
"u": "CHF",
"ex": "2’500 × 10 ÷ 100 = CHF 250.",
"ref": "Module 1 · Pourcentages"
},
{
"k": "num",
"q": "Sur 80 commandes, 12 sont livrées en retard. Quel est le pourcentage de retards ?",
"n": 15,
"tol": 0,
"u": "%",
"ex": "Taux = part ÷ total × 100 = 12 ÷ 80 × 100 = 15 %.",
"ref": "Module 1 · Pourcentages"
},
{
"k": "fill",
"q": "Taux (%) = part ÷ ___ × 100.",
"a": [
"total",
"part",
"taux",
"rabais"
],
"ex": "On divise la part par le total, puis on multiplie par 100.",
"ref": "Module 1 · Pourcentages"
},
{
"k": "qcm",
"q": "Comment écrit-on 4.5 % sous forme de nombre décimal ?",
"a": [
"0.045",
"0.45",
"4.5",
"0.0045"
],
"ex": "4.5 % = 4.5 ÷ 100 = 0.045. Dans une réponse, on écrit le taux avec le signe % : 4.5 %.",
"ref": "Module 1 · Pourcentages"
},
{
"k": "match",
"q": "Associez chaque pourcentage à sa valeur.",
"p": [
[
"50 %",
"1/2"
],
[
"25 %",
"1/4"
],
[
"10 %",
"0.1"
],
[
"75 %",
"3/4"
]
],
"ex": "Pourcentage ÷ 100 = nombre décimal.",
"ref": "Module 1 · Pourcentages"
},
{
"k": "case",
"s": "Logibois commande du matériel pour CHF 3’000. Le fournisseur accorde un rabais de 12 %, puis facture CHF 120 de frais de transport.",
"q": "Quel est le total à payer ?",
"a": [
"CHF 2’760.00",
"CHF 2’640.00",
"CHF 2’745.60",
"CHF 3’108.00"
],
"ex": "Rabais d’abord : 3’000 × 0.88 = 2’640. Puis les frais : 2’640 + 120 = CHF 2’760.00.",
"ref": "Module 3 · Problèmes série 2"
},
{
"k": "num",
"q": "Quel est le montant du rabais de 12 % sur une commande de CHF 3’000 ?",
"n": 360,
"tol": 0,
"u": "CHF",
"ex": "3’000 × 12 ÷ 100 = CHF 360.",
"ref": "Module 3 · Problèmes série 2"
},
{
"k": "case",
"s": "L’entrepôt contenait 1’200 cartons d’un article. Pendant la semaine, 35 % du stock a été expédié.",
"q": "Combien de cartons reste-t-il ?",
"a": [
"780 cartons",
"420 cartons",
"1’165 cartons",
"835 cartons"
],
"ex": "Expédié : 1’200 × 0.35 = 420. Reste : 1’200 − 420 = 780 cartons (ou 1’200 × 0.65).",
"ref": "Module 1 · Pourcentages"
},
{
"k": "case",
"s": "Lors de l’inventaire, le magasinier constate 6 écarts sur 240 emplacements contrôlés. Le responsable veut le taux d’erreur.",
"q": "Quel est le taux d’erreur ?",
"a": [
"2.5 %",
"0.025 %",
"40 %",
"6 %"
],
"ex": "6 ÷ 240 × 100 = 2.5 %.",
"ref": "Module 1 · Pourcentages"
},
{
"k": "tf",
"q": "Une hausse de 10 % suivie d’une baisse de 10 % ramène exactement au prix de départ.",
"v": false,
"ex": "Faux : 100 × 1.1 = 110 ; 110 × 0.9 = 99. La baisse se calcule sur 110, pas sur 100.",
"ref": "Module 1 · Pourcentages"
},
{
"k": "order",
"q": "Remettez dans l’ordre le calcul d’une facture avec rabais et frais de transport.",
"a": [
"Calculer le rabais sur le prix initial",
"Soustraire le rabais du prix initial",
"Ajouter les frais de transport",
"Contrôler que le total est plausible"
],
"ex": "Le rabais s’applique au prix de la marchandise, avant d’ajouter les frais.",
"ref": "Module 3 · Problèmes série 2"
},
{
"k": "sort",
"q": "Classez chaque coefficient : hausse ou baisse ?",
"c": [
"Hausse",
"Baisse"
],
"it": [
[
"× 1.08",
0
],
[
"× 0.85",
1
],
[
"× 1.2",
0
],
[
"× 0.97",
1
],
[
"× 1.081",
0
],
[
"× 0.5",
1
]
],
"ex": "Plus grand que 1 : hausse (1.08 = + 8 %). Plus petit que 1 : baisse (0.85 = − 15 %).",
"ref": "Module 1 · Pourcentages"
},
{
"k": "odd",
"q": "Trouvez l’intrus : laquelle de ces valeurs n’est PAS égale à 20 % ?",
"a": [
"0.02",
"0.2",
"1/5",
"20/100"
],
"ex": "20 % = 20/100 = 0.2 = 1/5. 0.02 = 2 %.",
"ref": "Module 1 · Pourcentages"
},
{
"k": "case",
"s": "Un client demande le prix TVA comprise d’un article vendu CHF 200.00 hors taxe. Le taux normal de TVA en Suisse est de 8.1 %.",
"q": "Quel est le prix TTC ?",
"a": [
"CHF 216.20",
"CHF 215.40",
"CHF 208.10",
"CHF 183.80"
],
"ex": "Prix TTC = prix HT × (1 + taux) = 200 × 1.081 = CHF 216.20. Hors support : le cours utilise encore 7.7 %, mais le taux normal est de 8.1 % depuis le 1er janvier 2024.",
"ref": "Module 3 · Problèmes série 3"
},
{
"k": "num",
"q": "Un camion de 24 places palettes est rempli à 75 %. Combien de palettes contient-il ?",
"n": 18,
"tol": 0,
"u": "palettes",
"ex": "24 × 75 ÷ 100 = 18 palettes.",
"ref": "Module 1 · Pourcentages"
},
{
"k": "case",
"s": "Le fournisseur de cartons annonce une hausse de 5 % au 1er janvier. Un lot coûtait jusqu’ici CHF 80.00.",
"q": "Quel sera le nouveau prix du lot ?",
"a": [
"CHF 84.00",
"CHF 85.00",
"CHF 80.05",
"CHF 76.00"
],
"ex": "80 × 1.05 = CHF 84.00.",
"ref": "Module 1 · Pourcentages"
},
{
"k": "qcm",
"q": "Combien font 18 % de 350 ?",
"a": [
"63",
"6.3",
"19.4",
"630"
],
"ex": "350 × 18 ÷ 100 = 63.",
"ref": "Module 1 · Pourcentages"
},
{
"k": "case",
"s": "Une facture de CHF 1’850.00 accorde 2 % d’escompte si elle est payée dans les 10 jours. La comptabilité paie à temps.",
"q": "Quel montant paie-t-elle ?",
"a": [
"CHF 1’813.00",
"CHF 1’848.00",
"CHF 1’480.00",
"CHF 1’887.00"
],
"ex": "1’850 × 0.98 = CHF 1’813.00 (escompte : CHF 37.00).",
"ref": "Module 1 · Pourcentages"
},
{
"k": "num",
"q": "90 palettes représentent 30 % du stock. Combien de palettes compte le stock total ?",
"n": 300,
"tol": 0,
"u": "palettes",
"ex": "Total = part × 100 ÷ taux = 90 × 100 ÷ 30 = 300 palettes.",
"ref": "Module 1 · Pourcentages"
},
{
"k": "multi",
"q": "Quels calculs donnent le prix après un rabais de 20 % ?",
"good": [
"Prix × 0.8",
"Prix − Prix × 0.2",
"Prix × 80 ÷ 100"
],
"bad": [
"Prix × 1.2",
"Prix − 20"
],
"ex": "Rabais de 20 % : il reste 80 % du prix, donc × 0.8.",
"ref": "Module 1 · Pourcentages"
},
{
"k": "case",
"s": "Sur 500 colis livrés le mois passé par le transporteur, 2 % sont arrivés endommagés. Le responsable prépare la réclamation.",
"q": "Combien de colis sont endommagés ?",
"a": [
"10 colis",
"2 colis",
"25 colis",
"100 colis"
],
"ex": "500 × 2 ÷ 100 = 10 colis.",
"ref": "Module 1 · Pourcentages"
},
{
"k": "tf",
"q": "1 % de 1’000 kg vaut 10 kg.",
"v": true,
"ex": "Vrai : 1’000 × 1 ÷ 100 = 10 kg.",
"ref": "Module 1 · Pourcentages"
},
{
"k": "case",
"s": "L’entrepôt compte 800 emplacements palettes. Aujourd’hui, 640 sont occupés. Le directeur veut le taux d’occupation.",
"q": "Quel est le taux d’occupation ?",
"a": [
"80 %",
"0.8 %",
"64 %",
"125 %"
],
"ex": "640 ÷ 800 × 100 = 80 %.",
"ref": "Module 1 · Pourcentages"
},
{
"k": "qcm",
"q": "La calculatrice affiche 0.1 comme résultat d’un taux. Quel pourcentage cela représente-t-il ?",
"a": [
"10 %",
"1 %",
"0.1 %",
"100 %"
],
"ex": "0.1 × 100 = 10 %. Pensez à multiplier par 100 pour écrire le résultat en %.",
"ref": "Module 1 · Pourcentages"
}
]
},
{
"code": "Mod. 2a",
"t": "Surfaces et volumes",
"g": "Calculer et convertir des surfaces et des volumes utiles en entrepôt",
"col": "coral",
"ic": "cube",
"ch": [
{
"k": "qcm",
"q": "Quelle formule donne la surface d’un rectangle ?",
"a": [
"Longueur × largeur",
"Longueur + largeur",
"2 × (longueur + largeur)",
"Longueur ÷ largeur"
],
"ex": "Surface = L × l. 2 × (L + l) donne le périmètre (le tour), pas la surface.",
"ref": "Module 2 · Calculs de surface"
},
{
"k": "num",
"q": "Quelle est la surface d’un entrepôt de 50 m sur 24 m ?",
"n": 1200,
"tol": 0,
"u": "m²",
"ex": "50 × 24 = 1’200 m².",
"ref": "Module 2 · Calculs de surface"
},
{
"k": "qcm",
"q": "Quelle surface au sol occupe une palette EUR de 1.2 m × 0.8 m ?",
"a": [
"0.96 m²",
"9.60 m²",
"2.00 m²",
"4.00 m²"
],
"ex": "1.2 × 0.8 = 0.96 m².",
"ref": "Module 2 · Calculs de surface"
},
{
"k": "case",
"s": "Le responsable de Stockalp SA prépare une zone pour 20 palettes EUR (1.2 m × 0.8 m) posées à plat, sans empiler, sans compter les allées.",
"q": "Quelle surface faut-il au minimum ?",
"a": [
"19.2 m²",
"20 m²",
"40 m²",
"1.92 m²"
],
"ex": "1 palette : 0.96 m². 20 × 0.96 = 19.2 m².",
"ref": "Module 2 · Calculs de surface"
},
{
"k": "num",
"q": "Combien de dm² y a-t-il dans 1 m² ?",
"n": 100,
"tol": 0,
"u": "dm²",
"ex": "Pour les surfaces, chaque pas du tableau vaut × 100 : 1 m² = 100 dm².",
"ref": "Module 2 · Calculs de surface"
},
{
"k": "num",
"q": "Convertissez 3.2 m² en cm².",
"n": 32000,
"tol": 0,
"u": "cm²",
"ex": "m² → cm² = 2 pas : 3.2 × 100 × 100 = 32’000 cm².",
"ref": "Module 2 · Calculs de surface"
},
{
"k": "qcm",
"q": "Convertissez 45’000 mm² en m².",
"a": [
"0.045 m²",
"0.450 m²",
"0.0045 m²",
"4.500 m²"
],
"ex": "mm² → m² = 3 pas vers la gauche : 45’000 ÷ 100 ÷ 100 ÷ 100 = 0.045 m². Attention : l’exemple du cours indique 0.45 m², c’est une erreur.",
"ref": "Module 2 · Calculs de surface"
},
{
"k": "tf",
"q": "Dans le tableau des surfaces, chaque pas d’une unité à la suivante vaut × 100 ou ÷ 100.",
"v": true,
"ex": "Vrai : 1 m² = 100 dm² = 10’000 cm². Pour les longueurs, c’est × 10 ; pour les volumes, × 1’000.",
"ref": "Module 2 · Calculs de surface"
},
{
"k": "tf",
"q": "Dans le tableau des volumes, chaque pas d’une unité à la suivante vaut × 1’000’000’000.",
"v": false,
"ex": "Faux : chaque pas vaut × 1’000 (1 m³ = 1’000 dm³). L’« astuce » du cours qui parle de 1 milliard par pas est une erreur.",
"ref": "Module 2 · Calculs de volumes"
},
{
"k": "num",
"q": "Combien de litres contient 1 m³ ?",
"n": 1000,
"tol": 0,
"u": "L",
"ex": "1 m³ = 1’000 dm³ et 1 dm³ = 1 L, donc 1 m³ = 1’000 L.",
"ref": "Module 2 · Calculs de volumes"
},
{
"k": "num",
"q": "Convertissez 2.5 m³ en litres.",
"n": 2500,
"tol": 0,
"u": "L",
"ex": "2.5 × 1’000 = 2’500 L.",
"ref": "Module 2 · Calculs de volumes"
},
{
"k": "qcm",
"q": "Convertissez 4’800 cm³ en litres.",
"a": [
"4.8 L",
"48 L",
"0.48 L",
"480 L"
],
"ex": "4’800 cm³ ÷ 1’000 = 4.8 dm³ = 4.8 L.",
"ref": "Module 2 · Calculs de volumes"
},
{
"k": "case",
"s": "Le transitaire doit annoncer le volume utile d’un conteneur dont l’intérieur mesure 6 m de long, 2.5 m de large et 2.7 m de haut.",
"q": "Quel volume annonce-t-il ?",
"a": [
"40.5 m³",
"11.2 m³",
"15.0 m³",
"405 m³"
],
"ex": "V = 6 × 2.5 × 2.7 = 40.5 m³.",
"ref": "Module 2 · Calculs de volumes"
},
{
"k": "case",
"s": "Il faut ranger des fûts de 210 L dans un espace de stockage de 2.5 m³. On ne compte que le volume, et on ne coupe pas un fût.",
"q": "Combien de fûts entiers peut-on y mettre au maximum ?",
"a": [
"11 fûts",
"12 fûts",
"84 fûts",
"1 fût"
],
"ex": "2.5 m³ = 2’500 L. 2’500 ÷ 210 = 11.9 → on arrondit vers le bas : 11 fûts.",
"ref": "Module 2 · Calculs de volumes"
},
{
"k": "case",
"img": "zone",
"s": "Une zone de stockage mesure 6 m sur 4 m. On y pose des palettes EUR de 1.2 m × 0.8 m, toutes dans le même sens, sans allée.",
"q": "Combien de palettes peut-on poser au sol ?",
"a": [
"25 palettes",
"24 palettes",
"30 palettes",
"20 palettes"
],
"ex": "6 ÷ 1.2 = 5 palettes sur la longueur ; 4 ÷ 0.8 = 5 sur la largeur. 5 × 5 = 25 palettes (24 m² ÷ 0.96 m² = 25 aussi).",
"ref": "Module 2 · Calculs de surface"
},
{
"k": "num",
"q": "Combien de cartons de 0.05 m³ peut-on stocker dans 15 m³ (en ne comptant que le volume) ?",
"n": 300,
"tol": 0,
"u": "cartons",
"ex": "15 ÷ 0.05 = 300 cartons.",
"ref": "Module 2 · Calculs de volumes"
},
{
"k": "qcm",
"q": "Quel est le volume d’une palette chargée de 1.2 m × 0.8 m × 1.7 m ?",
"a": [
"1.632 m³",
"3.7 m³",
"0.96 m³",
"16.32 m³"
],
"ex": "1.2 × 0.8 × 1.7 = 1.632 m³.",
"ref": "Module 2 · Calculs de volumes"
},
{
"k": "match",
"q": "Associez chaque unité à son équivalence.",
"p": [
[
"1 m²",
"100 dm²"
],
[
"1 m³",
"1’000 L"
],
[
"1 L",
"1 dm³"
],
[
"1 cm²",
"100 mm²"
]
],
"ex": "Surfaces : × 100 par pas. Volumes : × 1’000 par pas. 1 L = 1 dm³.",
"ref": "Module 2 · Calculs de volumes"
},
{
"k": "sort",
"q": "Classez : surface ou volume ?",
"c": [
"Surface",
"Volume"
],
"it": [
[
"m²",
0
],
[
"m³",
1
],
[
"L",
1
],
[
"cm²",
0
],
[
"Place au sol d’une palette",
0
],
[
"Contenance d’une citerne",
1
]
],
"ex": "Surface : 2 dimensions (m², cm²). Volume : 3 dimensions (m³, L).",
"ref": "Module 2 · Calculs de surface"
},
{
"k": "order",
"q": "Rangez ces unités de surface de la plus grande à la plus petite.",
"a": [
"km²",
"dam²",
"m²",
"cm²",
"mm²"
],
"ex": "km², hm², dam², m², dm², cm², mm².",
"ref": "Module 2 · Calculs de surface"
},
{
"k": "odd",
"q": "Trouvez l’intrus : laquelle n’est PAS une unité de volume ?",
"a": [
"m²",
"m³",
"L",
"dm³"
],
"ex": "m² mesure une surface. m³, L et dm³ mesurent un volume.",
"ref": "Module 2 · Calculs de volumes"
},
{
"k": "case",
"s": "Une palette chargée a un volume de 1.44 m³ et occupe 0.96 m² au sol. Le magasinier veut connaître sa hauteur pour choisir un emplacement.",
"q": "Quelle est sa hauteur ?",
"a": [
"1.5 m",
"1.38 m",
"2.4 m",
"0.67 m"
],
"ex": "Hauteur = volume ÷ surface = 1.44 ÷ 0.96 = 1.5 m.",
"ref": "Module 3 · Problèmes série 1"
},
{
"k": "case",
"s": "Un rayonnage à palettes fait 1.2 m de profondeur et 12 m de long. Le responsable veut savoir quelle surface au sol il occupe.",
"q": "Quelle surface occupe-t-il ?",
"a": [
"14.4 m²",
"13.2 m²",
"26.4 m²",
"1.44 m²"
],
"ex": "1.2 × 12 = 14.4 m².",
"ref": "Module 2 · Calculs de surface"
},
{
"k": "case",
"s": "Une zone fait 12 m² au sol et 2.2 m de haut. On veut y stocker 9 palettes de 0.96 m² et de 1.67 m de haut, empilées 2 par 2.",
"q": "Que répondez-vous ?",
"a": [
"Pas empilées, mais les 9 tiennent au sol",
"Oui : 5 emplacements empilés suffisent",
"Non : les 9 palettes n’y tiennent pas",
"Oui, en les empilant plutôt 3 par 3"
],
"ex": "Empilées : 2 × 1.67 = 3.34 m > 2.2 m, impossible. Au sol : 9 × 0.96 = 8.64 m² ≤ 12 m², c’est possible.",
"ref": "Module 3 · Problèmes série 1"
},
{
"k": "num",
"q": "Une surface de 600 m² est divisée en 3 zones égales. Quelle est la surface d’une zone ?",
"n": 200,
"tol": 0,
"u": "m²",
"ex": "600 ÷ 3 = 200 m².",
"ref": "Module 2 · Calculs de surface"
},
{
"k": "multi",
"q": "Quelles valeurs sont égales à 1.5 m³ ?",
"good": [
"1’500 L",
"1’500 dm³",
"1’500’000 cm³"
],
"bad": [
"150 L",
"15’000 cm³"
],
"ex": "1.5 m³ = 1’500 dm³ = 1’500 L = 1’500’000 cm³.",
"ref": "Module 2 · Calculs de volumes"
},
{
"k": "case",
"s": "La remorque d’un semi-remorque mesure à l’intérieur 13.6 m de long, 2.45 m de large et 2.7 m de haut. Le planificateur veut son volume de chargement.",
"q": "Quel est ce volume ?",
"a": [
"89.96 m³",
"33.32 m³",
"18.75 m³",
"899.64 m³"
],
"ex": "13.6 × 2.45 × 2.7 = 89.96 m³ (environ 90 m³).",
"ref": "Module 2 · Calculs de volumes"
},
{
"k": "case",
"s": "Un carton mesure 60 cm × 40 cm × 50 cm. Le transporteur facture au volume et demande la valeur en m³.",
"q": "Quel volume annoncez-vous ?",
"a": [
"0.12 m³",
"120 m³",
"1.2 m³",
"0.012 m³"
],
"ex": "On convertit d’abord en mètres : 0.6 × 0.4 × 0.5 = 0.12 m³ (= 120’000 cm³).",
"ref": "Module 2 · Calculs de volumes"
}
]
},
{
"code": "Mod. 2b",
"t": "Temps, distances et vitesses",
"g": "Calculer des durées, des heures d’arrivée, des distances et des vitesses",
"col": "amber",
"ic": "clock2",
"ch": [
{
"k": "num",
"q": "Combien de minutes font 1 h 30 ?",
"n": 90,
"tol": 0,
"u": "min",
"ex": "1 h = 60 min. 60 + 30 = 90 min.",
"ref": "Module 2 · Temps de travail"
},
{
"k": "qcm",
"q": "Combien d’heures (en nombre décimal) font 90 minutes ?",
"a": [
"1.5 h",
"1.3 h",
"1.9 h",
"0.9 h"
],
"ex": "Minutes → heures : ÷ 60. 90 ÷ 60 = 1.5 h.",
"ref": "Module 2 · Temps de travail"
},
{
"k": "qcm",
"q": "À quelle durée correspondent 2.25 h ?",
"a": [
"2 h 15",
"2 h 25",
"2 h 02",
"2 h 30"
],
"ex": "0.25 h × 60 = 15 min. 2.25 h = 2 h 15.",
"ref": "Module 2 · Temps de travail"
},
{
"k": "num",
"q": "Combien de secondes font 4 min 30 s ?",
"n": 270,
"tol": 0,
"u": "s",
"ex": "4 × 60 + 30 = 270 s.",
"ref": "Module 2 · Temps de travail"
},
{
"k": "case",
"s": "Un chauffeur de Livrex part du dépôt à 08 h 45 pour une tournée prévue de 2 h 35.",
"q": "À quelle heure doit-il être de retour ?",
"a": [
"11 h 20",
"10 h 80",
"11 h 30",
"10 h 20"
],
"ex": "Heures : 8 + 2 = 10 h. Minutes : 45 + 35 = 80 min = 1 h 20. 10 h + 1 h 20 = 11 h 20.",
"ref": "Module 2 · Temps de travail"
},
{
"k": "qcm",
"q": "Quelle durée sépare 14 h 10 de 17 h 40 ?",
"a": [
"3 h 30",
"3 h 50",
"4 h 30",
"3 h 20"
],
"ex": "De 14 h 10 à 17 h 10 : 3 h. Plus 30 min : 3 h 30.",
"ref": "Module 2 · Temps de travail"
},
{
"k": "case",
"s": "Un magasinier travaille de 07 h 30 à 11 h 45, puis de 13 h 15 à 17 h 00. Il doit remplir sa feuille d’heures.",
"q": "Combien d’heures a-t-il travaillé ?",
"a": [
"8 h",
"7 h 45",
"9 h 30",
"8 h 15"
],
"ex": "Matin : 4 h 15. Après-midi : 3 h 45. Total : 8 h.",
"ref": "Module 2 · Temps de travail"
},
{
"k": "case",
"s": "Un préparateur met 12 minutes pour filmer et étiqueter une palette. Il en a 15 à préparer avant le départ du camion.",
"q": "Combien de temps lui faut-il ?",
"a": [
"3 h",
"2 h 30",
"1 h 80",
"2 h 45"
],
"ex": "15 × 12 = 180 min = 3 h.",
"ref": "Module 2 · Temps de travail"
},
{
"k": "num",
"q": "Une opération dure 3 min 20 s. Combien de fois peut-on la faire en 1 heure ?",
"n": 18,
"tol": 0,
"u": "fois",
"ex": "3 min 20 s = 200 s. 1 h = 3’600 s. 3’600 ÷ 200 = 18 fois.",
"ref": "Module 2 · Temps de travail"
},
{
"k": "case",
"s": "Sur une journée de 8 h, un employé prend 40 min de pause et passe 1 h en réunion d’équipe. Le chef veut connaître son temps productif.",
"q": "Quel est son temps productif ?",
"a": [
"6 h 20",
"7 h",
"6 h 40",
"7 h 20"
],
"ex": "8 h − 40 min − 1 h = 6 h 20.",
"ref": "Module 2 · Temps de travail"
},
{
"k": "case",
"s": "Une tournée commence à 06 h 10 et dure 3 h 45. Le chauffeur mange ensuite 40 min, puis repart pour 1 h 30 de livraisons.",
"q": "À quelle heure finit-il ?",
"a": [
"12 h 05",
"11 h 05",
"11 h 25",
"10 h 35"
],
"ex": "06 h 10 + 3 h 45 = 09 h 55 ; + 40 min = 10 h 35 ; + 1 h 30 = 12 h 05. Le quiz du cours indique 11 h 05, c’est une erreur.",
"ref": "Module 2 · Temps de travail"
},
{
"k": "qcm",
"q": "Quelle formule permet de calculer une distance ?",
"a": [
"D = V × T",
"D = V ÷ T",
"D = T ÷ V",
"D = V + T"
],
"ex": "Distance = vitesse × temps. Vitesse = D ÷ T ; temps = D ÷ V.",
"ref": "Module 2 · Distances et vitesses"
},
{
"k": "num",
"q": "Un trajet de 360 km dure 4 h. Quelle est la vitesse moyenne ?",
"n": 90,
"tol": 0,
"u": "km/h",
"ex": "V = 360 ÷ 4 = 90 km/h.",
"ref": "Module 2 · Distances et vitesses"
},
{
"k": "num",
"q": "Un livreur roule à 80 km/h pendant 45 minutes. Quelle distance parcourt-il ?",
"n": 60,
"tol": 0,
"u": "km",
"ex": "45 min = 0.75 h. 80 × 0.75 = 60 km.",
"ref": "Module 2 · Distances et vitesses"
},
{
"k": "num",
"q": "Combien d’heures faut-il pour faire 200 km à 100 km/h ?",
"n": 2,
"tol": 0,
"u": "h",
"ex": "T = 200 ÷ 100 = 2 h.",
"ref": "Module 2 · Distances et vitesses"
},
{
"k": "case",
"s": "Un livreur roule d’abord 60 minutes à 45 km/h en ville, puis 90 minutes à 60 km/h sur la route cantonale.",
"q": "Quelle distance a-t-il parcourue ?",
"a": [
"135 km",
"127.5 km",
"105 km",
"157.5 km"
],
"ex": "45 × 1 = 45 km ; 60 × 1.5 = 90 km. Total : 135 km. Le quiz du cours indique 127.5 km, c’est une erreur.",
"ref": "Module 2 · Distances et vitesses"
},
{
"k": "case",
"s": "Un camion effectue 180 km en 2 h, fait une pause de 30 min, puis roule encore 120 km à 60 km/h.",
"q": "Quelle est la durée totale ?",
"a": [
"4 h 30",
"5 h 30",
"4 h 00",
"5 h 00"
],
"ex": "2 h + 30 min + (120 ÷ 60 = 2 h) = 4 h 30. Le quiz du cours indique 5 h 30, c’est une erreur.",
"ref": "Module 2 · Distances et vitesses"
},
{
"k": "case",
"s": "Un chauffeur part à 07 h 40 pour un trajet de 192 km. Il roule à une vitesse moyenne de 80 km/h, sans pause.",
"q": "À quelle heure arrive-t-il ?",
"a": [
"10 h 04",
"10 h 32",
"09 h 52",
"10 h 24"
],
"ex": "T = 192 ÷ 80 = 2.4 h = 2 h 24 (0.4 × 60 = 24 min). 07 h 40 + 2 h 24 = 10 h 04.",
"ref": "Module 2 · Distances et vitesses"
},
{
"k": "qcm",
"q": "À combien d’heures en nombre décimal correspond 1 h 20 min ?",
"a": [
"1.33 h",
"1.20 h",
"1.50 h",
"1.25 h"
],
"ex": "20 min ÷ 60 = 0.33 h. 1 h 20 = 1.33 h, pas 1.20 h.",
"ref": "Module 2 · Distances et vitesses"
},
{
"k": "qcm",
"q": "Un livreur met 1 h 20 pour faire 96 km. Quelle est sa vitesse moyenne ?",
"a": [
"72 km/h",
"80 km/h",
"76.8 km/h",
"120 km/h"
],
"ex": "1 h 20 = 4/3 h. V = 96 ÷ 4/3 = 96 × 3 ÷ 4 = 72 km/h.",
"ref": "Module 2 · Distances et vitesses"
},
{
"k": "case",
"s": "Dans l’entrepôt, on chronomètre un chariot élévateur : il parcourt 600 m en 3 minutes. Le responsable sécurité veut sa vitesse en km/h.",
"q": "Quelle est la vitesse du chariot ?",
"a": [
"12 km/h",
"200 km/h",
"20 km/h",
"1.8 km/h"
],
"ex": "600 m = 0.6 km ; 3 min = 0.05 h. V = 0.6 ÷ 0.05 = 12 km/h.",
"ref": "Module 2 · Distances et vitesses"
},
{
"k": "match",
"q": "Associez chaque conversion à l’opération à faire.",
"p": [
[
"min → h",
"÷ 60"
],
[
"h → min",
"× 60"
],
[
"m/s → km/h",
"× 3.6"
],
[
"km → m",
"× 1’000"
]
],
"ex": "Tableau des conversions utiles du cours.",
"ref": "Module 2 · Distances et vitesses"
},
{
"k": "order",
"q": "Remettez dans l’ordre la méthode pour additionner deux durées.",
"a": [
"Additionner les heures entre elles",
"Additionner les minutes entre elles",
"Convertir les minutes au-delà de 60 en heures",
"Écrire le résultat en h et min"
],
"ex": "1 h 45 + 2 h 35 = 3 h 80 min = 4 h 20.",
"ref": "Module 2 · Temps de travail"
},
{
"k": "sort",
"q": "Classez : durée ou heure (moment de la journée) ?",
"c": [
"Durée",
"Heure"
],
"it": [
[
"Départ à 07 h 40",
1
],
[
"Trajet de 2 h 24",
0
],
[
"Pause de 30 min",
0
],
[
"Arrivée à 10 h 04",
1
],
[
"Tournée de 5 h 40",
0
],
[
"Fin de service à 17 h 00",
1
]
],
"ex": "Une heure indique un moment ; une durée indique combien de temps passe.",
"ref": "Module 2 · Temps de travail"
},
{
"k": "odd",
"q": "Trouvez l’intrus : laquelle de ces durées n’est PAS égale à 2.5 h ?",
"a": [
"2 h 50",
"2 h 30",
"150 min",
"9’000 s"
],
"ex": "2.5 h = 2 h 30 = 150 min = 9’000 s. 2 h 50 = 2.83 h.",
"ref": "Module 2 · Temps de travail"
},
{
"k": "tf",
"q": "2 h 30 s’écrit 2.30 h en nombre décimal.",
"v": false,
"ex": "Faux : 30 min = 0.5 h, donc 2 h 30 = 2.5 h. Une heure compte 60 minutes, pas 100.",
"ref": "Module 2 · Temps de travail"
},
{
"k": "case",
"s": "Un chariot tracteur roule à 4.5 km/h dans l’entrepôt. Le cariste le conduit pendant 40 minutes au total sur sa tournée d’approvisionnement.",
"q": "Quelle distance parcourt-il ?",
"a": [
"3 km",
"180 km",
"1.8 km",
"4.5 km"
],
"ex": "40 min = 2/3 h. 4.5 × 2/3 = 3 km.",
"ref": "Module 2 · Distances et vitesses"
},
{
"k": "case",
"s": "Un camion parcourt 100 km à 50 km/h, puis 60 km à 80 km/h, puis 90 km à 60 km/h. Le planificateur calcule le temps de conduite.",
"q": "Quel est le temps de conduite total ?",
"a": [
"4 h 15",
"4 h",
"3 h 45",
"4 h 30"
],
"ex": "100 ÷ 50 = 2 h ; 60 ÷ 80 = 45 min ; 90 ÷ 60 = 1 h 30. Total : 4 h 15.",
"ref": "Module 2 · Distances et vitesses"
},
{
"k": "tf",
"q": "Pour convertir une vitesse de m/s en km/h, on multiplie par 3.6.",
"v": true,
"ex": "Vrai : 1 m/s = 3.6 km/h (3’600 s dans 1 h, 1’000 m dans 1 km).",
"ref": "Module 2 · Distances et vitesses"
},
{
"k": "num",
"q": "Convertissez 10 m/s en km/h.",
"n": 36,
"tol": 0,
"u": "km/h",
"ex": "10 × 3.6 = 36 km/h.",
"ref": "Module 2 · Distances et vitesses"
},
{
"k": "case",
"s": "Un livreur doit être chez un client à 14 h 00. Le trajet fait 150 km et il roule en moyenne à 75 km/h, sans pause.",
"q": "À quelle heure doit-il partir au plus tard ?",
"a": [
"12 h 00",
"12 h 30",
"11 h 45",
"13 h 15"
],
"ex": "T = 150 ÷ 75 = 2 h. 14 h 00 − 2 h = 12 h 00.",
"ref": "Module 2 · Distances et vitesses"
}
]
},
{
"code": "Mod. 3",
"t": "Problèmes à plusieurs étapes",
"g": "Résoudre un problème logistique en plusieurs étapes et vérifier que le résultat est réaliste",
"col": "emerald",
"ic": "steps",
"ch": [
{
"k": "order",
"q": "Remettez dans l’ordre la méthode pour résoudre un problème à plusieurs étapes.",
"a": [
"Lire attentivement la consigne",
"Identifier les données et convertir les unités",
"Calculer étape par étape",
"Vérifier la cohérence du résultat"
],
"ex": "Méthode du cours : lire, décomposer, calculer étape par étape, puis vérifier que la réponse est réaliste.",
"ref": "Module 3 · Problèmes série 1"
},
{
"k": "case",
"s": "Neuf palettes doivent être stockées en bloc, empilées 2 par 2. Chaque pile occupe un emplacement au sol.",
"q": "Combien d’emplacements faut-il ?",
"a": [
"5 emplacements",
"4 emplacements",
"4.5 emplacements",
"9 emplacements"
],
"ex": "9 ÷ 2 = 4.5 → on arrondit vers le haut : 4 piles de 2 + 1 palette seule = 5 emplacements.",
"ref": "Module 3 · Problèmes série 1"
},
{
"k": "case",
"s": "Une commande de 180 colis doit être palettisée. On met 24 colis par palette au maximum.",
"q": "Combien de palettes faut-il préparer ?",
"a": [
"8 palettes",
"7 palettes",
"7.5 palettes",
"9 palettes"
],
"ex": "180 ÷ 24 = 7.5 → on arrondit vers le haut : 8 palettes (la dernière n’est pas complète).",
"ref": "Module 3 · Problèmes série 1"
},
{
"k": "case",
"s": "Une camionnette a une charge utile de 1.5 t. On y charge 480 kg de cartons, 0.65 t de bidons et 3’500 hg de sacs.",
"q": "La charge utile est-elle respectée ?",
"a": [
"Oui : 1’480 kg, il reste 20 kg",
"Non : 1’480 kg dépassent 1.5 t",
"Non : 4’630 kg dépassent 1.5 t",
"Oui : 1’130 kg, il reste 370 kg"
],
"ex": "Tout en kg : 480 + 650 + 350 = 1’480 kg ≤ 1’500 kg. Il reste 20 kg.",
"ref": "Module 3 · Problèmes série 2"
},
{
"k": "case",
"s": "Un rayonnage supporte 1’000 kg. On veut y stocker 20 cartons de 18 kg, 15 cartons de 25 kg et 8 cartons de 40 kg.",
"q": "Que constatez-vous ?",
"a": [
"Surcharge de 55 kg",
"Il reste 55 kg de marge",
"Surcharge de 83 kg",
"La charge est pile 1’000 kg"
],
"ex": "360 + 375 + 320 = 1’055 kg > 1’000 kg : surcharge de 55 kg. Il faut déplacer des cartons.",
"ref": "Module 3 · Problèmes série 2"
},
{
"k": "num",
"img": "tournee",
"q": "Une tournée livre 5 clients : 22 km, 13 km, 8 km, 14 km et 18 km entre les arrêts, puis 25 km de retour direct au dépôt. Distance totale ?",
"n": 100,
"tol": 0,
"u": "km",
"ex": "Aller : 22 + 13 + 8 + 14 + 18 = 75 km. Avec le retour : 75 + 25 = 100 km.",
"ref": "Module 3 · Problèmes série 2"
},
{
"k": "case",
"s": "Une commande de rayonnages coûte CHF 4’500.00. Le fournisseur accorde 8 % de rabais et facture CHF 95.00 de livraison.",
"q": "Quel est le montant de la facture ?",
"a": [
"CHF 4’235.00",
"CHF 4’227.40",
"CHF 4’140.00",
"CHF 4’955.00"
],
"ex": "4’500 × 0.92 = 4’140 ; 4’140 + 95 = CHF 4’235.00. Le rabais ne s’applique pas aux frais de livraison.",
"ref": "Module 3 · Problèmes série 2"
},
{
"k": "case",
"s": "Un préparateur doit préparer 5 colis de type A (7 min chacun), 8 de type B (12 min) et 4 de type C (9 min).",
"q": "Combien de temps lui faut-il en tout ?",
"a": [
"2 h 47",
"2 h 30",
"1 h 67",
"3 h 07"
],
"ex": "35 + 96 + 36 = 167 min = 2 h 47.",
"ref": "Module 3 · Problèmes série 3"
},
{
"k": "case",
"s": "Vous facturez 12 pièces à CHF 48.00 hors taxe. La TVA au taux normal (8.1 %) doit être ajoutée.",
"q": "Quel est le total TVA comprise ?",
"a": [
"CHF 622.66",
"CHF 620.35",
"CHF 584.10",
"CHF 529.34"
],
"ex": "12 × 48 = CHF 576.00 HT. 576 × 1.081 = CHF 622.66. Hors support : le cours calcule avec 7.7 %, taux en vigueur jusqu’à fin 2023.",
"ref": "Module 3 · Problèmes série 3"
},
{
"k": "case",
"s": "Une palette de 720 kg doit être déchargée et déplacée par petites charges avec un chariot de manutention qui porte 300 kg au maximum.",
"q": "Combien de trajets faut-il ?",
"a": [
"3 trajets",
"2 trajets",
"2.4 trajets",
"4 trajets"
],
"ex": "720 ÷ 300 = 2.4 → on arrondit vers le haut : 3 trajets.",
"ref": "Module 3 · Problèmes série 4"
},
{
"k": "case",
"s": "Une commande comprend 4 cartons de 5 kg, 6 cartons de 7.5 kg et 6 cartons de 2.8 kg. Elle doit aller sur une tablette qui supporte 100 kg.",
"q": "Peut-on la stocker sur cette tablette ?",
"a": [
"Oui : 81.8 kg, marge de 18.2 kg",
"Non : 81.8 kg dépassent 100 kg",
"Oui : 15.3 kg seulement",
"Non : 109.4 kg dépassent 100 kg"
],
"ex": "20 + 45 + 16.8 = 81.8 kg ≤ 100 kg. Marge : 18.2 kg.",
"ref": "Module 3 · Problèmes série 4"
},
{
"k": "case",
"s": "Un chauffeur dispose de 6 h pour une tournée de 240 km à 80 km/h de moyenne, avec 12 livraisons de 10 min et 3 pauses de 15 min.",
"q": "Respecte-t-il le délai ?",
"a": [
"Oui : 5 h 45, marge de 15 min",
"Non : 6 h 15, retard de 15 min",
"Oui : 3 h de route seulement",
"Non : 6 h 45, retard de 45 min"
],
"ex": "Route : 240 ÷ 80 = 3 h. Livraisons : 2 h. Pauses : 45 min. Total : 5 h 45 ≤ 6 h.",
"ref": "Module 3 · Problèmes série 4"
},
{
"k": "case",
"img": "zone",
"s": "Une zone de 6 m × 4 m et de 2.5 m de haut doit accueillir des palettes EUR de 1.5 m de haut. Le chef demande la capacité de la zone.",
"q": "Combien de palettes la zone peut-elle accueillir ?",
"a": [
"25 palettes, sur 1 seule couche",
"50 palettes, sur 2 couches",
"24 palettes, sur 1 seule couche",
"41 palettes, sur 2 couches"
],
"ex": "Au sol : 5 × 5 = 25 palettes. En hauteur : 2 × 1.5 = 3 m > 2.5 m, donc une seule couche.",
"ref": "Module 3 · Problèmes série 3"
},
{
"k": "tf",
"q": "Pour calculer un nombre de trajets, on arrondit toujours à l’entier supérieur.",
"v": true,
"ex": "Vrai : avec 2.4 trajets, il reste une charge à transporter, il faut donc un 3e trajet.",
"ref": "Module 3 · Problèmes série 4"
},
{
"k": "tf",
"q": "Pour savoir combien de fûts entiers entrent dans un espace, on arrondit à l’entier supérieur.",
"v": false,
"ex": "Faux : le 12e fût ne tiendrait pas. On arrondit vers le bas quand on cherche ce qui « entre ».",
"ref": "Module 2 · Calculs de volumes"
},
{
"k": "sort",
"q": "Faut-il arrondir vers le haut ou vers le bas ?",
"c": [
"Vers le haut",
"Vers le bas"
],
"it": [
[
"Nombre de trajets nécessaires",
0
],
[
"Palettes à préparer pour 180 colis",
0
],
[
"Fûts qui tiennent dans 2.5 m³",
1
],
[
"Colis préparés en 50 min",
1
],
[
"Personnes à engager",
0
],
[
"Cartons sur une tablette limitée en charge",
1
]
],
"ex": "Ce qu’il faut pour tout faire : vers le haut. Ce qui entre ou ce qui est terminé : vers le bas.",
"ref": "Module 3 · Problèmes série 4"
},
{
"k": "match",
"q": "Associez chaque grandeur à sa formule.",
"p": [
[
"Prix après rabais",
"Prix × (1 − taux)"
],
[
"Prix TTC",
"Prix HT × (1 + taux TVA)"
],
[
"Capacité restante",
"Capacité max − masse utilisée"
],
[
"Nombre de trajets",
"Masse totale ÷ capacité"
]
],
"ex": "Formules des séries de problèmes 2, 3 et 4.",
"ref": "Module 3 · Problèmes série 2"
},
{
"k": "odd",
"q": "Trouvez l’intrus : quelle habitude ne fait PAS partie d’une bonne méthode ?",
"a": [
"Arrondir chaque calcul intermédiaire",
"Identifier les données utiles",
"Mettre les valeurs dans la même unité",
"Vérifier si la réponse est réaliste"
],
"ex": "On garde les valeurs exactes pendant le calcul et on arrondit seulement le résultat final.",
"ref": "Module 3 · Problèmes série 1"
},
{
"k": "num",
"q": "Il faut livrer 4’250 kg avec une camionnette de 1’200 kg de charge utile. Combien de trajets faut-il ?",
"n": 4,
"tol": 0,
"u": "trajets",
"ex": "4’250 ÷ 1’200 = 3.54 → on arrondit vers le haut : 4 trajets.",
"ref": "Module 3 · Problèmes série 4"
},
{
"k": "case",
"s": "On veut poser 8 palettes EUR (0.96 m² chacune) à plat dans un coin de quai libre de 7.5 m².",
"q": "Le coin est-il assez grand ?",
"a": [
"Non : il faut 7.68 m²",
"Oui : il faut 7.2 m²",
"Oui : il reste 0.18 m²",
"Non : il faut 9.6 m²"
],
"ex": "8 × 0.96 = 7.68 m² > 7.5 m² : il manque 0.18 m².",
"ref": "Module 3 · Problèmes série 1"
},
{
"k": "case",
"s": "Une palette chargée fait 1.6 m³ pour 0.96 m² au sol. Le magasinier voudrait en empiler deux sous un plafond de 2.2 m.",
"q": "Est-ce possible ?",
"a": [
"Non : 2 × 1.67 m = 3.33 m",
"Oui : 1.67 m < 2.2 m",
"Oui : 1.6 m³ < 2.2 m",
"Non : 2 × 0.96 m = 1.92 m"
],
"ex": "Hauteur = 1.6 ÷ 0.96 ≈ 1.67 m. Deux palettes : environ 3.33 m > 2.2 m.",
"ref": "Module 3 · Problèmes série 1"
},
{
"k": "multi",
"q": "Quels contrôles de cohérence sont utiles à la fin d’un problème ?",
"good": [
"Un nombre de palettes est un nombre entier",
"La masse ne dépasse pas la charge utile",
"Les unités étaient les mêmes avant d’additionner"
],
"bad": [
"Le résultat a le plus de décimales possible",
"Le résultat est plus grand que toutes les données"
],
"ex": "Le résultat doit être réaliste et respecter les contraintes du terrain.",
"ref": "Module 3 · Problèmes série 1"
},
{
"k": "case",
"s": "Un collègue additionne ses temps de préparation : 35 + 96 + 36 = 167 min. Il note « 1 h 67 » sur la feuille de travail.",
"q": "Que corrigez-vous ?",
"a": [
"167 min = 2 h 47",
"167 min = 1.67 h",
"Rien : 1 h 67 est juste",
"167 min = 16 h 7"
],
"ex": "167 ÷ 60 = 2 reste 47 : 2 h 47. Une heure compte 60 minutes.",
"ref": "Module 3 · Problèmes série 3"
},
{
"k": "case",
"s": "Un entrepôt compte 3 zones de 500 m². Les allées et zones de sécurité occupent 20 % de la surface. Le reste sert au stockage de palettes EUR au sol.",
"q": "Combien de palettes au maximum, au sol ?",
"a": [
"1’250 palettes",
"1’562 palettes",
"1’200 palettes",
"1’440 palettes"
],
"ex": "Total : 1’500 m². Stockage : 1’500 × 0.8 = 1’200 m². 1’200 ÷ 0.96 = 1’250 palettes (maximum théorique).",
"ref": "Module 3 · Problèmes série 1"
},
{
"k": "case",
"s": "Vous facturez 150 cartons à CHF 3.20 HT. Le client a droit à 5 % de rabais, puis la TVA de 8.1 % s’ajoute.",
"q": "Quel est le total TVA comprise ?",
"a": [
"CHF 492.94",
"CHF 518.88",
"CHF 456.00",
"CHF 494.88"
],
"ex": "150 × 3.20 = 480 ; rabais : 480 × 0.95 = 456 ; TVA : 456 × 1.081 = CHF 492.94.",
"ref": "Module 3 · Problèmes série 3"
},
{
"k": "num",
"q": "Un employé ne prépare que des colis de type C (9 min chacun). Combien en prépare-t-il en 90 min ?",
"n": 10,
"tol": 0,
"u": "colis",
"ex": "90 ÷ 9 = 10 colis.",
"ref": "Module 3 · Problèmes série 3"
},
{
"k": "case",
"s": "Départ du dépôt à 07 h 30. La tournée compte 2 h 10 de route, 7 livraisons de 12 min et une pause de 20 min.",
"q": "À quelle heure le chauffeur rentre-t-il ?",
"a": [
"11 h 24",
"10 h 04",
"11 h 44",
"10 h 24"
],
"ex": "7 × 12 = 84 min. 130 + 84 + 20 = 234 min = 3 h 54. 07 h 30 + 3 h 54 = 11 h 24.",
"ref": "Module 3 · Problèmes série 4"
},
{
"k": "qcm",
"q": "Une facture comporte un rabais et des frais de transport. Dans quel ordre calcule-t-on ?",
"a": [
"Rabais d’abord, puis les frais",
"Frais d’abord, puis le rabais",
"Rabais sur les frais uniquement",
"L’ordre ne change jamais rien"
],
"ex": "Le rabais porte sur la marchandise. On ajoute ensuite les frais (règle de la série 2 du cours).",
"ref": "Module 3 · Problèmes série 2"
}
]
}
];
window.LEVELS = [[0, "Stagiaire"], [150, "Apprenti·e logisticien·ne"], [400, "Logisticien·ne en formation"], [800, "Logisticien·ne CFC"], [1300, "Référent·e calcul de base"], [2000, "Expert·e mesures et conversions"], [3000, "Formateur·rice logistique"]];
window.META = {
 "title": "Mathématiques – Fondamentaux",
 "eyebrow": "Logisticien·ne CFC · Mathématiques, modules 1 à 3",
 "key": "atelier-maths-fondamentaux-v1",
 "intro": "Consolidez les bases du calcul en logistique : conversions, poids, pourcentages, surfaces et volumes, temps et vitesses, problèmes à plusieurs étapes.",
 "goal": 20,
 "exam": {
  "n": 25,
  "min": 35
 },
 "cover": {
  "src": "img/cover.jpg",
  "alt": "Image de titre du cours Mathématiques – Fondamentaux : une formatrice et trois apprenti·e·s calculent autour d’une table, avec une calculatrice et des formules au mur"
 },
 "imgs": {
  "longueurs": {
   "src": "img/longueurs.png",
   "alt": "Tableau des longueurs km, hm, dam, m, dm, cm, mm : × 10 à chaque pas vers la droite, ÷ 10 vers la gauche"
  },
  "zone": {
   "src": "img/zone.png",
   "alt": "Zone de stockage de 6 m sur 4 m vue de dessus, remplie de 5 × 5 palettes de 1.2 m × 0.8 m"
  },
  "tournee": {
   "src": "img/tournee.png",
   "alt": "Schéma d’une tournée : dépôt, 5 clients reliés par 22, 13, 8, 14 et 18 km, puis retour direct de 25 km"
  }
 },
 "icons": {
  "ruler": "<path d=\"M3 15L15 3l6 6L9 21z\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/><path d=\"M7 11l2 2M10 8l2 2M13 5l2 2M6 14l1 1\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/>",
  "weight": "<path d=\"M6 9h12l2 11H4z\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/><circle cx=\"12\" cy=\"6\" r=\"2.5\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/><path d=\"M10 15h4\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/>",
  "percent": "<path d=\"M19 5L5 19\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/><circle cx=\"7\" cy=\"7\" r=\"2.5\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/><circle cx=\"17\" cy=\"17\" r=\"2.5\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/>",
  "cube": "<path d=\"M12 3l8 4.5v9L12 21l-8-4.5v-9z\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/><path d=\"M4 7.5l8 4.5 8-4.5M12 12v9\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/>",
  "clock2": "<circle cx=\"12\" cy=\"13\" r=\"8\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/><path d=\"M12 9v4l3 2M9 3h6\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/>",
  "steps": "<path d=\"M3 20h5v-5h5v-5h5V5h3\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/><path d=\"M17 3l4 2-4 2\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/>"
 },
 "note": {
  "text": "Jeu construit d’après le cours « Mathématiques | Fondamentaux » (modules 1 à 3). Quelques réponses du quiz du cours ont été corrigées, et la TVA est calculée au taux normal actuel de 8.1 %. Cours complet sur",
  "url": "https://logiprof.fontes-oliveira.ch",
  "link": "logiprof"
 }
};
window.META.langs = ["fr","de","it"];
