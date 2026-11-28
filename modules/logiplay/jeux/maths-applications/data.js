/* Mathématiques – Applications professionnelles (modules 4 à 8). a[0] = bonne réponse. Généré par build_data.py. */
window.OBJ = [
{
"code": "Mod. 4",
"t": "Proportionnalité et règle de trois",
"g": "Prévoir une quantité, un temps ou un coût à partir d’une situation connue",
"col": "teal",
"ic": "ratio",
"ch": [
{
"k": "qcm",
"q": "Quand deux grandeurs sont-elles directement proportionnelles ?",
"a": [
"Quand l’une double, l’autre double aussi",
"Quand l’une double, l’autre est divisée par deux",
"Quand leur somme reste toujours la même",
"Quand leur différence reste toujours la même"
],
"ex": "Même sens, même rapport : si on multiplie l’une par un nombre, l’autre est multipliée par le même nombre.",
"ref": "Leçon 4.1"
},
{
"k": "tf",
"q": "Un transport facturé avec un forfait fixe plus un prix au kilomètre est directement proportionnel à la distance.",
"v": false,
"ex": "Non : le forfait n’est payé qu’une fois, 200 km ne coûtent donc pas le double de 100 km.",
"ref": "Leçon 4.1"
},
{
"k": "num",
"q": "5 cartons identiques pèsent 40 kg. Combien pèsent 8 cartons ?",
"n": 64,
"tol": 0,
"u": "kg",
"ex": "1 carton : 40 ÷ 5 = 8 kg. 8 cartons : 8 × 8 = 64 kg.",
"ref": "Leçon 4.1"
},
{
"k": "num",
"q": "15 cartons identiques pèsent ensemble 180 kg. Quel est le poids de 22 cartons ?",
"n": 264,
"tol": 0,
"u": "kg",
"ex": "1 carton : 180 ÷ 15 = 12 kg. 22 cartons : 22 × 12 = 264 kg.",
"ref": "Leçon 4.1"
},
{
"k": "num",
"q": "9 palettes contiennent ensemble 432 cartons. Combien de cartons contiennent 15 palettes identiques ?",
"n": 720,
"tol": 0,
"u": "cartons",
"ex": "1 palette : 432 ÷ 9 = 48 cartons. 15 palettes : 15 × 48 = 720 cartons.",
"ref": "Leçon 4.1"
},
{
"k": "case",
"s": "Le chef d’équipe de la plateforme Logimax planifie la journée de demain. Aujourd’hui, 6 préparateurs ont traité 420 commandes. Demain, 8 préparateurs travailleront au même rythme.",
"q": "Combien de commandes l’équipe peut-elle traiter demain ?",
"a": [
"560 commandes",
"490 commandes",
"600 commandes",
"630 commandes"
],
"ex": "1 préparateur : 420 ÷ 6 = 70 commandes. 8 préparateurs : 8 × 70 = 560 commandes.",
"ref": "Leçon 4.1"
},
{
"k": "num",
"q": "2 magasiniers rangent une livraison en 6 heures. Combien d’heures faut-il à 3 magasiniers travaillant au même rythme ?",
"n": 4,
"tol": 0,
"u": "heures",
"ex": "Travail total : 2 × 6 = 12 heures de travail. Avec 3 magasiniers : 12 ÷ 3 = 4 heures.",
"ref": "Leçon 4.2"
},
{
"k": "num",
"q": "6 collaborateurs terminent un inventaire en 8 heures. Combien d’heures faut-il à 12 collaborateurs au même rythme ?",
"n": 4,
"tol": 0,
"u": "heures",
"ex": "Travail total : 6 × 8 = 48 heures de travail. 48 ÷ 12 = 4 heures.",
"ref": "Leçon 4.2"
},
{
"k": "case",
"s": "Au quai de Transvalor SA, 4 chariots élévateurs déchargent une grosse livraison en 6 heures. Pour libérer le quai plus vite, le responsable engage 8 chariots identiques.",
"q": "Combien de temps dure le déchargement avec 8 chariots ?",
"a": [
"3 heures",
"12 heures",
"4 heures",
"2 heures"
],
"ex": "Proportionnalité inverse : 4 × 6 = 24 heures-chariot. 24 ÷ 8 = 3 heures. Plus de chariots, donc moins de temps.",
"ref": "Leçon 4.2"
},
{
"k": "num",
"q": "5 chariots déchargent un camion en 50 minutes. Combien de minutes faut-il avec 10 chariots identiques ?",
"n": 25,
"tol": 0,
"u": "minutes",
"ex": "5 × 50 = 250 minutes-chariot. 250 ÷ 10 = 25 minutes.",
"ref": "Leçon 4.2"
},
{
"k": "sort",
"q": "Classez chaque situation : directement ou inversement proportionnelle ?",
"c": [
"Directe",
"Inverse"
],
"it": [
[
"Nombre de cartons identiques et poids total",
0
],
[
"Nombre de personnes et durée d’un inventaire",
1
],
[
"Vitesse et temps de trajet sur une distance fixe",
1
],
[
"Nombre d’étiquettes et prix total",
0
],
[
"Nombre de palettes et surface au sol",
0
],
[
"Nombre de chariots et temps de déchargement",
1
]
],
"ex": "Directe : même sens. Inverse : sens contraire (plus de monde ou plus de vitesse, moins de temps).",
"ref": "Leçon 4.2"
},
{
"k": "tf",
"q": "En proportionnalité inverse, le produit des deux grandeurs reste constant.",
"v": true,
"ex": "Oui : 2 magasiniers × 6 h = 3 magasiniers × 4 h = 12 heures de travail.",
"ref": "Leçon 4.2"
},
{
"k": "fill",
"q": "En proportionnalité directe, c’est le ___ des deux grandeurs qui reste constant.",
"a": [
"rapport",
"produit",
"total",
"écart"
],
"ex": "Directe : le rapport est constant (le poids d’un carton, le prix d’un article). Inverse : c’est le produit.",
"ref": "Leçon 4.2"
},
{
"k": "num",
"q": "Un camion consomme 30 L pour 100 km. Combien de litres consomme-t-il pour 250 km ?",
"n": 75,
"tol": 0,
"u": "L",
"ex": "1 km : 30 ÷ 100 = 0.3 L. 250 km : 0.3 × 250 = 75 L.",
"ref": "Leçon 4.3"
},
{
"k": "case",
"s": "Un chauffeur de TransLéman doit livrer à 365 km du dépôt. Son camion consomme 28 L de diesel pour 100 km. Il veut savoir combien de carburant prévoir pour l’aller.",
"q": "Quelle quantité de carburant faut-il prévoir ?",
"a": [
"102.2 L",
"93.8 L",
"112.0 L",
"130.4 L"
],
"ex": "1 km : 28 ÷ 100 = 0.28 L. 365 km : 0.28 × 365 = 102.2 L.",
"ref": "Leçon 4.3"
},
{
"k": "num",
"q": "Un véhicule roule à 60 km/h. Combien de minutes lui faut-il pour parcourir 150 km ?",
"n": 150,
"tol": 0,
"u": "minutes",
"ex": "60 km en 60 min, donc 1 km par minute. 150 km : 150 min, soit 2 h 30 min.",
"ref": "Leçon 4.3"
},
{
"k": "qcm",
"q": "Une durée de 5.5 h correspond à…",
"a": [
"5 h 30 min",
"5 h 50 min",
"5 h 05 min",
"5 h 55 min"
],
"ex": "1 heure = 60 minutes, pas 100 : 0.5 h = 30 min. 5.5 h = 5 h 30 min.",
"ref": "Leçon 4.3"
},
{
"k": "case",
"s": "Un camion de livraison a parcouru 240 km en 4 heures à vitesse constante. Il lui reste une tournée de 330 km à la même vitesse et le planificateur veut l’heure d’arrivée.",
"q": "Combien de temps faut-il pour 330 km ?",
"a": [
"5 h 30 min",
"4 h 50 min",
"5 h 15 min",
"6 h 00 min"
],
"ex": "240 km en 240 min, donc 1 km par minute. 330 km : 330 min = 5 h 30 min.",
"ref": "Leçon 4.3"
},
{
"k": "num",
"q": "750 étiquettes coûtent CHF 45.00. Combien de francs coûtent 1’200 étiquettes au même prix unitaire ?",
"n": 72,
"tol": 0,
"u": "CHF",
"ex": "1 étiquette : 45 ÷ 750 = CHF 0.06. 1’200 étiquettes : 1’200 × 0.06 = CHF 72.00.",
"ref": "Leçon 4.3"
},
{
"k": "case",
"s": "Le magasinier de Logipack commande des films d’emballage. Le dernier lot de 400 films a coûté CHF 68.00. Il en faut cette fois 650, au même prix unitaire.",
"q": "Quel sera le montant de la commande ?",
"a": [
"CHF 110.50",
"CHF 102.00",
"CHF 115.60",
"CHF 119.00"
],
"ex": "1 film : 68 ÷ 400 = CHF 0.17. 650 films : 650 × 0.17 = CHF 110.50. On garde le prix unitaire exact et on arrondit seulement à la fin.",
"ref": "Leçon 4.3"
},
{
"k": "order",
"q": "Remettez dans l’ordre la méthode du retour à l’unité.",
"a": [
"Repérer la grandeur connue et la grandeur cherchée",
"Diviser pour trouver la valeur pour 1 unité",
"Multiplier par le nombre d’unités demandé",
"Contrôler que le résultat va dans le bon sens"
],
"ex": "On divise pour trouver l’unité, on multiplie pour trouver le résultat, puis on vérifie le sens.",
"ref": "Leçon 4.1"
},
{
"k": "num",
"q": "2 préparateurs traitent 120 colis en 3 heures. Combien de colis 4 préparateurs traitent-ils en 2 heures au même rythme ?",
"n": 160,
"tol": 0,
"u": "colis",
"ex": "Cadence : 120 ÷ 2 ÷ 3 = 20 colis par personne et par heure. 20 × 4 × 2 = 160 colis.",
"ref": "Leçon 4.4"
},
{
"k": "case",
"s": "Chez Distrivaud, 4 préparateurs ont traité 560 colis en 7 heures. Demain, l’équipe comptera 6 préparateurs, mais seulement pendant 5 heures.",
"q": "Combien de colis l’équipe peut-elle traiter demain ?",
"a": [
"600 colis",
"560 colis",
"630 colis",
"720 colis"
],
"ex": "Cadence : 560 ÷ 4 ÷ 7 = 20 colis par personne et par heure. 20 × 6 × 5 = 600 colis.",
"ref": "Leçon 4.4"
},
{
"k": "case",
"s": "Hier, 6 collaborateurs ont préparé 900 colis en 5 heures. Aujourd’hui, 1’440 colis doivent partir et il ne reste que 4 heures avant le passage du transporteur.",
"q": "Combien de collaborateurs faut-il engager ?",
"a": [
"12 collaborateurs",
"10 collaborateurs",
"14 collaborateurs",
"16 collaborateurs"
],
"ex": "Cadence : 900 ÷ 6 ÷ 5 = 30 colis par personne et par heure. 1’440 ÷ (30 × 4) = 12 collaborateurs.",
"ref": "Leçon 4.4"
},
{
"k": "case",
"s": "Un préparateur traite 25 colis par heure. Une commande urgente de 1’000 colis doit être prête en 6 heures. Le chef d’équipe calcule le personnel nécessaire.",
"q": "Combien de préparateurs faut-il prévoir ?",
"a": [
"7 préparateurs",
"6 préparateurs",
"8 préparateurs",
"5 préparateurs"
],
"ex": "1’000 ÷ (25 × 6) = 6.67. On arrondit vers le haut : on ne peut pas engager une fraction de personne, donc 7 préparateurs.",
"ref": "Leçon 4.4"
},
{
"k": "num",
"q": "Un camion consomme 24 L pour 100 km. Il effectue 3 trajets de 175 km. Combien de litres consomme-t-il au total ?",
"n": 126,
"tol": 0,
"u": "L",
"ex": "Distance totale : 3 × 175 = 525 km. 0.24 × 525 = 126 L.",
"ref": "Leçon 4.4"
},
{
"k": "odd",
"q": "Trouvez l’intrus : laquelle de ces situations n’est PAS directement proportionnelle ?",
"a": [
"Nombre de caristes et durée du déchargement",
"Nombre de palettes et surface au sol",
"Temps de fonctionnement et pièces produites",
"Nombre d’étiquettes et prix total"
],
"ex": "Plus de caristes, moins de temps : c’est une proportionnalité inverse.",
"ref": "Leçon 4.2"
},
{
"k": "match",
"q": "Associez chaque donnée du métier à sa valeur pour 1 unité.",
"p": [
[
"30 L/100 km",
"0.3 L par km"
],
[
"60 km/h",
"1 km par minute"
],
[
"120 colis en 8 min",
"15 colis par minute"
],
[
"CHF 45.00 les 750 étiquettes",
"CHF 0.06 par étiquette"
]
],
"ex": "On divise pour revenir à 1 unité : 30 ÷ 100, 60 ÷ 60, 120 ÷ 8, 45 ÷ 750.",
"ref": "Leçon 4.3"
},
{
"k": "multi",
"q": "Quelles situations sont inversement proportionnelles ?",
"good": [
"Nombre de personnes et durée d’un rangement",
"Nombre de chariots et temps de déchargement",
"Vitesse et temps de trajet sur 200 km"
],
"bad": [
"Nombre de cartons et poids total",
"Nombre d’étiquettes et prix total"
],
"ex": "Inverse : quand l’une augmente, l’autre diminue (le produit reste constant).",
"ref": "Leçon 4.2"
},
{
"k": "case",
"s": "Deux magasiniers ont rangé une livraison en 6 heures. Pour la prochaine, identique, ils seront trois. Un collègue annonce : « Ça prendra 9 heures. »",
"q": "Que lui répondez-vous ?",
"a": [
"Non : 12 heures de travail ÷ 3 = 4 heures",
"Oui : 6 ÷ 2 × 3 = 9 heures de travail",
"Non : 6 − 1 = 5 heures, une personne de plus",
"Oui : avec plus de monde, le travail dure plus"
],
"ex": "Erreur classique : appliquer la proportionnalité directe. Avec plus de monde, le travail ne peut pas durer plus longtemps. 2 × 6 = 12 ; 12 ÷ 3 = 4 h.",
"ref": "Leçon 4.2"
},
{
"k": "case",
"s": "Une bande transporteuse du centre de tri déplace 840 colis en 35 minutes. Le responsable veut savoir ce qu’elle traite en 50 minutes, au même rythme.",
"q": "Combien de colis passent en 50 minutes ?",
"a": [
"1’200 colis",
"1’050 colis",
"1’260 colis",
"1’400 colis"
],
"ex": "1 minute : 840 ÷ 35 = 24 colis. 50 minutes : 24 × 50 = 1’200 colis.",
"ref": "Leçon 4.3"
}
]
},
{
"code": "Mod. 5",
"t": "Pourcentages avancés",
"g": "Lire et calculer des taux, des remises, des hausses et des baisses",
"col": "indigo",
"ic": "percent",
"ch": [
{
"k": "qcm",
"q": "Que signifie « pour cent » ?",
"a": [
"Sur 100",
"Fois 100",
"Divisé par 10",
"Sur 1’000"
],
"ex": "Un pourcentage indique combien de parts on aurait si le total était ramené à 100.",
"ref": "Leçon 5.1"
},
{
"k": "num",
"q": "Sur 200 colis reçus, 6 sont abîmés. Quel est le taux de casse en % ?",
"n": 3,
"tol": 0,
"u": "%",
"ex": "6 ÷ 200 = 0.03 ; 0.03 × 100 = 3 %.",
"ref": "Leçon 5.1"
},
{
"k": "case",
"s": "À la réception de Logiplus, le contrôleur a compté 12 colis endommagés sur 300 colis reçus ce matin. La responsable qualité lui demande le taux de casse.",
"q": "Quel est le taux de casse ?",
"a": [
"4 %",
"4.2 %",
"12 %",
"0.04 %"
],
"ex": "Taux de casse = colis endommagés ÷ colis reçus × 100 = 12 ÷ 300 × 100 = 4 %. Le total de référence est celui des colis reçus, pas des colis intacts.",
"ref": "Leçon 5.1"
},
{
"k": "match",
"q": "Associez chaque indicateur à son calcul (partie ÷ total).",
"p": [
[
"Taux de casse",
"Colis endommagés ÷ colis reçus"
],
[
"Taux d’erreur de préparation",
"Commandes avec erreur ÷ commandes préparées"
],
[
"Taux de service",
"Articles livrés tout de suite ÷ articles commandés"
],
[
"Taux de retour",
"Colis retournés ÷ colis expédiés"
]
],
"ex": "Même formule partout : partie ÷ total × 100. Seuls la partie et le total changent.",
"ref": "Leçon 5.1"
},
{
"k": "match",
"q": "Associez chaque fraction à son pourcentage.",
"p": [
[
"1/2",
"50 %"
],
[
"1/4",
"25 %"
],
[
"1/10",
"10 %"
],
[
"1/100",
"1 %"
]
],
"ex": "Fraction → nombre décimal (0.5 ; 0.25 ; 0.1 ; 0.01) → × 100.",
"ref": "Leçon 5.1"
},
{
"k": "tf",
"q": "Le résultat 0.03 correspond à 0.03 %.",
"v": false,
"ex": "Il faut multiplier par 100 : 0.03 = 3 %.",
"ref": "Leçon 5.1"
},
{
"k": "case",
"s": "Inventaire chez Stockalp : le logiciel annonce 4’850 pièces d’un article, mais le comptage physique en trouve 4’753. Le chef de dépôt veut l’écart d’inventaire en %.",
"q": "Quel est l’écart d’inventaire ?",
"a": [
"2 %",
"2.04 %",
"97 %",
"0.2 %"
],
"ex": "Écart : 4’850 − 4’753 = 97. Référence : le stock informatique. 97 ÷ 4’850 × 100 = 2 %.",
"ref": "Leçon 5.1"
},
{
"k": "num",
"q": "Combien font 15 % de 400 articles ?",
"n": 60,
"tol": 0,
"u": "articles",
"ex": "10 % = 40, 5 % = 20 ; 15 % = 60. Ou : 400 × 0.15 = 60.",
"ref": "Leçon 5.2"
},
{
"k": "num",
"q": "Un stock compte 400 articles, dont 15 % de catégorie A. Combien d’articles ne sont PAS en catégorie A ?",
"n": 340,
"tol": 0,
"u": "articles",
"ex": "400 × 0.15 = 60 articles A ; 400 − 60 = 340. Ou directement 400 × 0.85 = 340.",
"ref": "Leçon 5.2"
},
{
"k": "case",
"s": "Le rayonnage du dépôt Morges compte 250 emplacements. Le logiciel indique que 64 % sont occupés. Le cariste veut savoir combien d’emplacements libres il lui reste pour la réception.",
"q": "Combien d’emplacements sont libres ?",
"a": [
"90 emplacements",
"160 emplacements",
"64 emplacements",
"36 emplacements"
],
"ex": "Libres : 100 % − 64 % = 36 %. 250 × 0.36 = 90 emplacements. Piège : 64 % de 250 donne les emplacements occupés (160).",
"ref": "Leçon 5.2"
},
{
"k": "qcm",
"q": "Comment s’écrit 7.5 % en nombre décimal ?",
"a": [
"0.075",
"0.75",
"7.5",
"0.0075"
],
"ex": "On divise par 100 : le point se déplace de deux rangs vers la gauche.",
"ref": "Leçon 5.2"
},
{
"k": "num",
"q": "On doit contrôler 2.5 % d’un lot de 1’200 cartons. Combien de cartons faut-il contrôler ?",
"n": 30,
"tol": 0,
"u": "cartons",
"ex": "1’200 × 0.025 = 30 cartons.",
"ref": "Leçon 5.2"
},
{
"k": "fill",
"q": "Pour une hausse de 8 %, le coefficient multiplicateur est ___.",
"a": [
"1.08",
"1.8",
"0.92",
"0.08"
],
"ex": "Hausse de p % : × (1 + p ÷ 100) = × 1.08. Piège : écrire 1.8.",
"ref": "Leçon 5.3"
},
{
"k": "num",
"q": "Un rouleau de film coûte CHF 20.00. Le fournisseur accorde une remise de 5 %. Quel est le nouveau prix en CHF ?",
"n": 19,
"tol": 0,
"u": "CHF",
"ex": "Coefficient : 1 − 0.05 = 0.95. 20 × 0.95 = CHF 19.00.",
"ref": "Leçon 5.3"
},
{
"k": "case",
"s": "Une prestation de transport est facturée CHF 50.00 hors taxe. La comptable de LogiSuisse doit ajouter la TVA de 8.1 % pour obtenir le montant TTC.",
"q": "Quel est le montant TTC ?",
"a": [
"CHF 54.05",
"CHF 50.81",
"CHF 58.10",
"CHF 45.95"
],
"ex": "Coefficient : 1 + 0.081 = 1.081. 50 × 1.081 = CHF 54.05.",
"ref": "Leçon 5.3"
},
{
"k": "num",
"q": "Le prix d’un transport passe de CHF 50.00 à CHF 55.00. De combien de % a-t-il augmenté ?",
"n": 10,
"tol": 0,
"u": "%",
"ex": "Écart : 55 − 50 = 5. 5 ÷ 50 × 100 = 10 %. On compare toujours à l’ancienne valeur.",
"ref": "Leçon 5.3"
},
{
"k": "case",
"s": "Le prix d’une palette de sangles passe de CHF 80.00 à CHF 68.00 grâce à une nouvelle offre. L’acheteur veut connaître la variation en % pour son rapport.",
"q": "Quelle est la variation du prix ?",
"a": [
"Baisse de 15 %",
"Baisse de 17.6 %",
"Baisse de 12 %",
"Hausse de 15 %"
],
"ex": "(68 − 80) ÷ 80 × 100 = −15 % : baisse de 15 %. Piège : diviser par la nouvelle valeur (12 ÷ 68 = 17.6 %).",
"ref": "Leçon 5.3"
},
{
"k": "qcm",
"q": "Pour calculer une variation en %, on divise l’écart par…",
"a": [
"L’ancienne valeur",
"La nouvelle valeur",
"La somme des deux valeurs",
"100"
],
"ex": "La valeur de départ est toujours la référence.",
"ref": "Leçon 5.3"
},
{
"k": "sort",
"q": "Classez chaque coefficient multiplicateur : hausse ou baisse ?",
"c": [
"Hausse",
"Baisse"
],
"it": [
[
"× 1.081 (TVA)",
0
],
[
"× 0.95",
1
],
[
"× 1.10",
0
],
[
"× 0.90",
1
],
[
"× 0.855",
1
],
[
"× 1.025",
0
]
],
"ex": "Coefficient supérieur à 1 : hausse. Inférieur à 1 : baisse.",
"ref": "Leçon 5.3"
},
{
"k": "case",
"s": "Un fournisseur de cartons accorde à Logimax une remise de 10 % sur un lot à CHF 200.00, puis une remise de fidélité de 5 % sur le prix déjà réduit.",
"q": "Quel est le prix final du lot ?",
"a": [
"CHF 171.00",
"CHF 170.00",
"CHF 172.50",
"CHF 180.00"
],
"ex": "200 × 0.90 × 0.95 = CHF 171.00. Les remises ne s’additionnent pas : la remise totale est de 14.5 %, pas de 15 %.",
"ref": "Leçon 5.4"
},
{
"k": "tf",
"q": "Une remise de 10 % suivie d’une remise de 5 % donne une remise totale de 15 %.",
"v": false,
"ex": "On multiplie les coefficients : 0.90 × 0.95 = 0.855, soit une remise de 14.5 %.",
"ref": "Leçon 5.4"
},
{
"k": "num",
"q": "Un stock de 1’000 pièces augmente de 10 %, puis diminue de 10 %. Combien de pièces reste-t-il ?",
"n": 990,
"tol": 0,
"u": "pièces",
"ex": "1’000 × 1.10 = 1’100 ; 1’100 × 0.90 = 990. La baisse porte sur 1’100 : on ne revient pas à 1’000.",
"ref": "Leçon 5.4"
},
{
"k": "case",
"s": "Grâce à un nouveau scanner, le taux d’erreur de préparation de l’entrepôt est passé de 5 % à 3.5 %. La direction demande de combien il a baissé, en valeur relative.",
"q": "De combien le taux d’erreur a-t-il baissé, en % ?",
"a": [
"30 %",
"1.5 %",
"3.5 %",
"15 %"
],
"ex": "Écart : 1.5 point. Valeur relative : 1.5 ÷ 5 × 100 = 30 %.",
"ref": "Leçon 5.4"
},
{
"k": "qcm",
"q": "Un taux d’erreur passe de 5 % à 3.5 %. De combien de points de pourcentage a-t-il baissé ?",
"a": [
"1.5 point",
"30 points",
"3.5 points",
"8.5 points"
],
"ex": "5 − 3.5 = 1.5 point. En valeur relative, c’est une baisse de 30 %.",
"ref": "Leçon 5.4"
},
{
"k": "case",
"s": "Un lot de 2’000 articles arrive au contrôle qualité. 8 % sont non conformes. Parmi les articles conformes, 20 % partent en express, le reste en envoi standard.",
"q": "Combien d’articles partent en express ?",
"a": [
"368 articles",
"400 articles",
"320 articles",
"1’472 articles"
],
"ex": "Conformes : 2’000 × 0.92 = 1’840. Express : 1’840 × 0.20 = 368. Les 20 % s’appliquent aux conformes, pas au total.",
"ref": "Leçon 5.4"
},
{
"k": "order",
"q": "Remettez dans l’ordre la méthode pour enchaîner plusieurs pourcentages.",
"a": [
"Découper le problème en étapes, dans l’ordre de l’énoncé",
"Se demander à quelle quantité s’applique le pourcentage",
"Calculer le résultat de l’étape",
"Utiliser ce résultat comme base de l’étape suivante"
],
"ex": "Chaque pourcentage s’applique au résultat de l’étape précédente.",
"ref": "Leçon 5.4"
},
{
"k": "odd",
"q": "Trouvez l’intrus parmi ces coefficients.",
"a": [
"× 1.05",
"× 0.90",
"× 0.95",
"× 0.75"
],
"ex": "× 1.05 est une hausse de 5 % ; les autres sont des baisses (10 %, 5 %, 25 %).",
"ref": "Leçon 5.3"
},
{
"k": "multi",
"q": "Quelles affirmations sont justes ?",
"good": [
"Le taux de casse se calcule sur les colis reçus",
"Une variation se compare à la valeur de départ",
"On multiplie les coefficients de pourcentages successifs"
],
"bad": [
"Des pourcentages successifs s’additionnent",
"On divise l’écart par la nouvelle valeur"
],
"ex": "Toujours se demander « sur combien ? » et ne jamais additionner des pourcentages successifs.",
"ref": "Leçon 5.4"
},
{
"k": "case",
"s": "En octobre, le site de vente en ligne de Logiplus a expédié 1’200 colis. 36 colis ont été retournés par les clients. Le service client veut le taux de retour du mois.",
"q": "Quel est le taux de retour ?",
"a": [
"3 %",
"3.6 %",
"36 %",
"0.3 %"
],
"ex": "Colis retournés ÷ colis expédiés × 100 = 36 ÷ 1’200 × 100 = 3 %.",
"ref": "Leçon 5.1"
},
{
"k": "num",
"q": "Une facture s’élève à CHF 1’000.00 hors taxe. Quel est le montant TTC avec une TVA de 8.1 %, en CHF ?",
"n": 1081,
"tol": 0,
"u": "CHF",
"ex": "1’000 × 1.081 = CHF 1’081.00.",
"ref": "Leçon 5.3"
},
{
"k": "case",
"s": "Un client a commandé 480 articles à Distrivaud. 456 ont pu être livrés immédiatement, les autres seront livrés plus tard. La responsable calcule le taux de service.",
"q": "Quel est le taux de service ?",
"a": [
"95 %",
"5 %",
"105.3 %",
"4.8 %"
],
"ex": "Articles livrés immédiatement ÷ articles commandés × 100 = 456 ÷ 480 × 100 = 95 %.",
"ref": "Leçon 5.1"
}
]
},
{
"code": "Mod. 6",
"t": "Taux de remplissage et capacité",
"g": "Mesurer l’occupation d’un stock ou d’un véhicule et la place restante",
"col": "violet",
"ic": "fill",
"ch": [
{
"k": "num",
"q": "Un rayonnage compte 200 emplacements, dont 150 sont occupés. Quel est le taux d’occupation en % ?",
"n": 75,
"tol": 0,
"u": "%",
"ex": "150 ÷ 200 × 100 = 75 %.",
"ref": "Leçon 6.1"
},
{
"k": "tf",
"q": "Le taux d’occupation et le taux libre font toujours 100 % ensemble.",
"v": true,
"ex": "Taux libre = 100 % − taux d’occupation.",
"ref": "Leçon 6.1"
},
{
"k": "case",
"s": "Le rayonnage à palettes de Stockalp compte 320 emplacements. Ce matin, 248 sont occupés. Le chef de dépôt prépare son rapport hebdomadaire.",
"q": "Quel est le taux d’occupation ?",
"a": [
"77.5 %",
"22.5 %",
"72.0 %",
"129.0 %"
],
"ex": "248 ÷ 320 × 100 = 77.5 %. Les 22.5 % correspondent au taux libre.",
"ref": "Leçon 6.1"
},
{
"k": "qcm",
"q": "À partir de quel taux d’occupation beaucoup d’entreprises fixent-elles un seuil d’alerte ?",
"a": [
"Environ 85 à 90 %",
"Environ 50 %",
"Exactement 100 %",
"Environ 25 à 30 %"
],
"ex": "Un entrepôt presque plein manque de souplesse : le seuil d’alerte se situe souvent autour de 85 à 90 %.",
"ref": "Leçon 6.1"
},
{
"k": "case",
"s": "Le directeur de Logimax est fier : son entrepôt est occupé à 98 %. Pourtant, les préparateurs se plaignent et les erreurs augmentent depuis quelques semaines.",
"q": "Quel est le problème d’un taux aussi élevé ?",
"a": [
"Plus de place pour une réception imprévue",
"Le loyer de l’entrepôt devient plus élevé",
"Les palettes deviennent plus lourdes à lever",
"Le taux libre dépasse alors les 100 %"
],
"ex": "Un entrepôt presque plein manque de souplesse : réceptions imprévues difficiles, déplacements et erreurs en hausse.",
"ref": "Leçon 6.1"
},
{
"k": "tf",
"q": "Un taux d’occupation très bas signale de la surface payée mais inutilisée.",
"v": true,
"ex": "C’est l’autre extrême : on paie de la place pour rien.",
"ref": "Leçon 6.1"
},
{
"k": "num",
"q": "Une camionnette a une charge utile de 1’000 kg. Elle transporte 800 kg. Quel est son taux de chargement en % ?",
"n": 80,
"tol": 0,
"u": "%",
"ex": "800 ÷ 1’000 × 100 = 80 %.",
"ref": "Leçon 6.2"
},
{
"k": "num",
"q": "Un camion a une charge utile de 15 t. Il transporte 12 t. Quel est son taux de chargement en % ?",
"n": 80,
"tol": 0,
"u": "%",
"ex": "12 ÷ 15 × 100 = 80 %. Les deux valeurs sont déjà en tonnes.",
"ref": "Leçon 6.2"
},
{
"k": "case",
"s": "Une camionnette a une charge utile de 3.5 t. Le livreur a chargé 2’800 kg de marchandises et veut contrôler son taux de chargement.",
"q": "Quel est le taux de chargement ?",
"a": [
"80 %",
"0.8 %",
"125 %",
"8 %"
],
"ex": "On convertit d’abord : 3.5 t = 3’500 kg. 2’800 ÷ 3’500 × 100 = 80 %.",
"ref": "Leçon 6.2"
},
{
"k": "sort",
"q": "Classez chaque capacité selon sa mesure.",
"c": [
"Poids",
"Volume",
"Nombre d’unités"
],
"it": [
[
"Charge utile d’un camion",
0
],
[
"Charge admissible d’une palette",
0
],
[
"Volume utile d’un conteneur",
1
],
[
"Contenance d’une cuve",
1
],
[
"Places palettes d’une semi-remorque",
2
],
[
"Cases d’un magasin automatique",
2
]
],
"ex": "Poids : kg, t. Volume : m³, L. Unités : palettes, bacs, cases.",
"ref": "Leçon 6.2"
},
{
"k": "case",
"s": "Un camion de Transvalor charge des cartons d’emballage vides, très volumineux mais légers. Le chauffeur se demande quelle limite il atteindra en premier.",
"q": "Quelle limite sera atteinte en premier ?",
"a": [
"Le volume, bien avant la charge utile",
"La charge utile, bien avant le volume",
"Les deux limites exactement en même temps",
"Aucune, le camion ne sera jamais plein"
],
"ex": "Des marchandises légères et volumineuses remplissent l’espace bien avant d’atteindre la charge utile.",
"ref": "Leçon 6.2"
},
{
"k": "qcm",
"q": "Un calcul donne 22 ÷ 26 × 100 = 84.615… %. Quel est le résultat arrondi au dixième ?",
"a": [
"84.6 %",
"84.7 %",
"84.0 %",
"85.0 %"
],
"ex": "Chiffre des centièmes : 1, donc on garde le dixième tel quel : 84.6 %.",
"ref": "Leçon 6.2"
},
{
"k": "num",
"q": "Un rayonnage de 320 emplacements en compte 248 occupés. Combien d’emplacements sont disponibles ?",
"n": 72,
"tol": 0,
"u": "emplacements",
"ex": "320 − 248 = 72 emplacements.",
"ref": "Leçon 6.3"
},
{
"k": "case",
"s": "Le dépôt de Logipack dispose de 400 emplacements palettes, occupés à 72 %. Une livraison importante est annoncée pour demain matin.",
"q": "Combien d’emplacements sont encore disponibles ?",
"a": [
"112 emplacements",
"288 emplacements",
"128 emplacements",
"28 emplacements"
],
"ex": "Part libre : 100 % − 72 % = 28 %. 400 × 0.28 = 112 emplacements. Contrôle : 288 occupés + 112 = 400.",
"ref": "Leçon 6.3"
},
{
"k": "num",
"q": "Une cuve de 2’000 L est remplie à 70 %. Combien de litres reste-t-il disponibles ?",
"n": 600,
"tol": 0,
"u": "L",
"ex": "Part libre : 30 %. 2’000 × 0.30 = 600 L. Contrôle : 1’400 + 600 = 2’000 L.",
"ref": "Leçon 6.3"
},
{
"k": "case",
"s": "Un conteneur a un volume utile de 67.7 m³. Les marchandises déjà chargées occupent 54.16 m³. Le transitaire veut savoir quel volume il peut encore proposer.",
"q": "Quel volume reste disponible ?",
"a": [
"13.54 m³",
"13.61 m³",
"12.54 m³",
"121.86 m³"
],
"ex": "67.70 − 54.16 = 13.54 m³. Avec des décimales, on aligne les chiffres après le point.",
"ref": "Leçon 6.3"
},
{
"k": "tf",
"q": "Quantité occupée + capacité disponible = capacité totale.",
"v": true,
"ex": "C’est le contrôle à faire à chaque calcul de capacité disponible.",
"ref": "Leçon 6.3"
},
{
"k": "num",
"q": "Un rayonnage de 100 emplacements est occupé à 60 %. On range 15 palettes de plus. Quel est le nouveau taux d’occupation en % ?",
"n": 75,
"tol": 0,
"u": "%",
"ex": "60 occupés + 15 = 75. 75 ÷ 100 × 100 = 75 %.",
"ref": "Leçon 6.4"
},
{
"k": "num",
"q": "Un dépôt de 100 emplacements contient 75 palettes. On l’agrandit de 50 emplacements. Quel est le nouveau taux d’occupation en % ?",
"n": 50,
"tol": 0,
"u": "%",
"ex": "Toujours 75 palettes, mais 150 emplacements : 75 ÷ 150 × 100 = 50 %.",
"ref": "Leçon 6.4"
},
{
"k": "case",
"s": "Le dépôt de Distrivaud compte 500 emplacements, occupés à 80 %. En fin de journée, 160 palettes partent en expédition et libèrent leurs emplacements.",
"q": "Quel est le nouveau taux d’occupation ?",
"a": [
"48 %",
"64 %",
"32 %",
"52 %"
],
"ex": "Départ : 500 × 0.80 = 400. Après expédition : 400 − 160 = 240. 240 ÷ 500 × 100 = 48 %.",
"ref": "Leçon 6.4"
},
{
"k": "case",
"s": "Un rayonnage de 240 emplacements est occupé à 75 %. Dans la matinée, on range 30 palettes reçues et 12 palettes partent vers la production.",
"q": "Quel est le taux d’occupation à midi ?",
"a": [
"82.5 %",
"75.0 %",
"93.0 %",
"67.5 %"
],
"ex": "Départ : 240 × 0.75 = 180. 180 + 30 − 12 = 198. 198 ÷ 240 × 100 = 82.5 %.",
"ref": "Leçon 6.4"
},
{
"k": "qcm",
"q": "Un collègue écrit « 75 % + 15 palettes = 90 % ». Quelle est son erreur ?",
"a": [
"Il ajoute un nombre de palettes à un pourcentage",
"Il oublie de multiplier le résultat final par 100",
"Il aurait dû soustraire les 15 palettes du total",
"Il aurait dû diviser le taux par 15 palettes"
],
"ex": "On revient aux quantités réelles, on met à jour, puis on recalcule le taux.",
"ref": "Leçon 6.4"
},
{
"k": "order",
"q": "Remettez dans l’ordre le calcul du taux après un mouvement de stock.",
"a": [
"Trouver la quantité occupée au départ",
"Ajouter les entrées et soustraire les sorties",
"Diviser par la capacité totale",
"Multiplier par 100"
],
"ex": "Revenir aux quantités, mettre à jour, recalculer le taux.",
"ref": "Leçon 6.4"
},
{
"k": "match",
"q": "Associez chaque grandeur à son calcul.",
"p": [
[
"Taux d’occupation",
"Occupé ÷ capacité totale × 100"
],
[
"Taux libre",
"100 % − taux d’occupation"
],
[
"Capacité disponible",
"Capacité totale − occupé"
],
[
"Taux de chargement",
"Quantité chargée ÷ capacité maximale × 100"
]
],
"ex": "Taux : en %. Capacité disponible : en quantité réelle (emplacements, kg, m³, L).",
"ref": "Leçon 6.3"
},
{
"k": "odd",
"q": "Trouvez l’intrus : lequel de ces événements fait BAISSER le taux d’occupation sans qu’aucune palette ne sorte ?",
"a": [
"Extension du dépôt de 50 emplacements",
"Réception de 20 palettes",
"Retour client remis en stock",
"Rangement d’une livraison"
],
"ex": "Une extension change le total (le dénominateur), pas le stock. Les trois autres font monter le taux.",
"ref": "Leçon 6.4"
},
{
"k": "case",
"s": "Une semi-remorque offre 33 places palettes. Le cariste en a chargé 27 et le dispatcher lui demande le taux de chargement, arrondi au dixième.",
"q": "Quel est le taux de chargement ?",
"a": [
"81.8 %",
"18.2 %",
"82.0 %",
"122.2 %"
],
"ex": "27 ÷ 33 × 100 = 81.81… → 81.8 %.",
"ref": "Leçon 6.2"
},
{
"k": "multi",
"q": "On agrandit un dépôt sans déplacer de palettes. Qu’est-ce qui est vrai ?",
"good": [
"La capacité totale augmente",
"La quantité stockée ne change pas",
"Le taux d’occupation baisse"
],
"bad": [
"Des palettes sortent du stock",
"Le taux libre baisse"
],
"ex": "Une extension change le dénominateur, pas le stock : le taux d’occupation baisse et le taux libre monte.",
"ref": "Leçon 6.4"
},
{
"k": "fill",
"q": "Une extension du dépôt change le ___, pas la quantité stockée.",
"a": [
"total des emplacements",
"nombre de palettes",
"poids stocké",
"taux de service"
],
"ex": "Le dénominateur (capacité totale) change ; le stock reste le même.",
"ref": "Leçon 6.4"
},
{
"k": "num",
"q": "Une zone de stockage au sol mesure 1’200 m². 900 m² sont occupés. Quel est le taux d’occupation en % ?",
"n": 75,
"tol": 0,
"u": "%",
"ex": "900 ÷ 1’200 × 100 = 75 %.",
"ref": "Leçon 6.1"
},
{
"k": "case",
"s": "Une réception de 40 palettes est annoncée. Le rayonnage de 300 emplacements est occupé à 90 %. Le magasinier doit dire au transporteur si toute la livraison entre.",
"q": "Que répond-il ?",
"a": [
"Non : 30 places libres, il en manque 10",
"Oui : 30 places libres, il en reste 20",
"Non : 270 places libres, il en manque 230",
"Oui : 40 places libres, juste assez"
],
"ex": "Libres : 300 × 0.10 = 30 emplacements. 40 − 30 = 10 palettes ne trouvent pas de place.",
"ref": "Leçon 6.3"
}
]
},
{
"code": "Mod. 7",
"t": "Palettisation et optimisation",
"g": "Calculer combien de colis tiennent sur une palette sans dépasser hauteur et poids",
"col": "coral",
"ic": "pallet",
"ch": [
{
"k": "qcm",
"q": "Quelles sont les dimensions d’une palette EUR ?",
"a": [
"1’200 × 800 mm",
"1’200 × 1’000 mm",
"1’000 × 800 mm",
"1’200 × 1’200 mm"
],
"ex": "Palette EUR : 1’200 × 800 mm. Palette industrielle : 1’200 × 1’000 mm.",
"ref": "Leçon 7.1"
},
{
"k": "qcm",
"q": "Quelles sont les dimensions d’une palette industrielle ?",
"a": [
"1’200 × 1’000 mm",
"1’200 × 800 mm",
"1’000 × 600 mm",
"800 × 600 mm"
],
"ex": "Palette industrielle : 1’200 × 1’000 mm.",
"ref": "Leçon 7.1"
},
{
"k": "num",
"q": "Combien de cartons de 400 × 200 mm tiennent par couche sur une palette EUR ?",
"n": 12,
"tol": 0,
"u": "cartons",
"ex": "1’200 ÷ 400 = 3 et 800 ÷ 200 = 4 : 3 × 4 = 12. Autre sens : 6 × 2 = 12. Contrôle : 960’000 ÷ 80’000 = 12, couche complète.",
"ref": "Leçon 7.1"
},
{
"k": "case",
"s": "Le préparateur de Logipack doit palettiser des cartons de 300 × 400 mm sur une palette EUR. Il hésite sur le sens à donner aux cartons.",
"q": "Combien de cartons par couche au maximum ?",
"a": [
"8 cartons",
"6 cartons",
"7 cartons",
"10 cartons"
],
"ex": "300 sur la longueur : 4 × 2 = 8. 400 sur la longueur : 3 × 2 = 6. On garde la meilleure orientation : 8 cartons.",
"ref": "Leçon 7.1"
},
{
"k": "num",
"q": "Combien de cartons de 600 × 400 mm tiennent par couche sur une palette EUR ?",
"n": 4,
"tol": 0,
"u": "cartons",
"ex": "600 sur la longueur : 2 × 2 = 4. 400 sur la longueur : 3 × 1 = 3. Maximum : 4 cartons.",
"ref": "Leçon 7.1"
},
{
"k": "case",
"s": "Chez Distrivaud, on charge des cartons de 400 × 300 mm sur des palettes industrielles de 1’200 × 1’000 mm. L’apprentie calcule la meilleure couche.",
"q": "Combien de cartons par couche au maximum ?",
"a": [
"9 cartons",
"10 cartons",
"8 cartons",
"12 cartons"
],
"ex": "400 sur la longueur : 3 × 3 = 9 (1’000 ÷ 300 = 3.33 → 3). 300 sur la longueur : 4 × 2 = 8. Maximum : 9. Les 10 cartons de la surface sont un maximum théorique impossible à atteindre ici.",
"ref": "Leçon 7.1"
},
{
"k": "tf",
"q": "Ce plan montre une couche complète : la palette EUR est entièrement couverte par 12 cartons de 400 × 200 mm.",
"v": true,
"img": "couche12",
"ex": "3 × 4 = 12 cartons ; 12 × 80’000 mm² = 960’000 mm², soit toute la surface de la palette.",
"ref": "Leçon 7.1"
},
{
"k": "tf",
"q": "Si 800 ÷ 300 = 2.66, on arrondit à 3 cartons sur la largeur.",
"v": false,
"ex": "On arrondit toujours vers le bas : un morceau de carton ne compte pas, donc 2.",
"ref": "Leçon 7.1"
},
{
"k": "tf",
"q": "La surface de la palette divisée par la surface d’un carton donne un maximum théorique.",
"v": true,
"ex": "On ne peut jamais faire mieux. Si on l’atteint, la couche est parfaitement remplie.",
"ref": "Leçon 7.1"
},
{
"k": "pickimg",
"q": "Quel plan de couche montre la meilleure orientation de cartons de 300 × 400 mm sur une palette EUR ?",
"a": [
"couche8",
"couche6",
"couche7"
],
"ex": "300 sur la longueur : 4 × 2 = 8 cartons. L’autre sens ne donne que 6 cartons.",
"ref": "Leçon 7.1"
},
{
"k": "order",
"q": "Remettez dans l’ordre le calcul des cartons par couche.",
"a": [
"Mettre toutes les dimensions en mm",
"Diviser longueur et largeur de la palette par celles du carton",
"Arrondir chaque résultat vers le bas",
"Multiplier les deux nombres",
"Tourner le carton et garder la meilleure orientation"
],
"ex": "Longueur par longueur, largeur par largeur, arrondi vers le bas, puis multiplier. Tester les deux sens.",
"ref": "Leçon 7.1"
},
{
"k": "num",
"q": "Hauteur maximale : 1’350 mm. Palette vide : 150 mm. Cartons : 300 mm de haut. Combien de couches peut-on poser ?",
"n": 4,
"tol": 0,
"u": "couches",
"img": "hauteur",
"ex": "1’350 − 150 = 1’200 mm. 1’200 ÷ 300 = 4 couches.",
"ref": "Leçon 7.2"
},
{
"k": "case",
"s": "L’alvéole du rayonnage de Stockalp accepte des charges de 1’800 mm de haut au maximum, palette EUR (144 mm) comprise. Les cartons mesurent 250 mm de haut.",
"q": "Combien de couches peut-on poser ?",
"a": [
"6 couches",
"7 couches",
"8 couches",
"5 couches"
],
"ex": "1’800 − 144 = 1’656 mm. 1’656 ÷ 250 = 6.62 → 6 couches (arrondi vers le bas).",
"ref": "Leçon 7.2"
},
{
"k": "case",
"s": "Hauteur maximale 1’600 mm, palette vide 150 mm, cartons de 200 mm. Un collègue annonce « 1’600 ÷ 200 = 8 couches ». Vous contrôlez son calcul.",
"q": "Combien de couches peut-on réellement poser ?",
"a": [
"7 couches",
"8 couches",
"7.25 couches",
"6 couches"
],
"ex": "Il a oublié la palette vide : 1’600 − 150 = 1’450 mm ; 1’450 ÷ 200 = 7.25 → 7 couches.",
"ref": "Leçon 7.2"
},
{
"k": "num",
"q": "Une palette reçoit 12 cartons par couche sur 6 couches. Combien de cartons porte-t-elle ?",
"n": 72,
"tol": 0,
"u": "cartons",
"ex": "12 × 6 = 72 cartons.",
"ref": "Leçon 7.2"
},
{
"k": "tf",
"q": "Si le calcul donne 6.9 couches, on pose 7 couches.",
"v": false,
"ex": "On garde uniquement les couches complètes : 6 couches. La septième dépasserait la limite.",
"ref": "Leçon 7.2"
},
{
"k": "num",
"q": "Une palette reçoit 10 cartons par couche sur 4 couches. Quelle est sa capacité ?",
"n": 40,
"tol": 0,
"u": "cartons",
"ex": "10 × 4 = 40 cartons.",
"ref": "Leçon 7.3"
},
{
"k": "case",
"s": "Une commande de 130 cartons doit partir ce soir. Chaque palette reçoit 10 cartons par couche sur 4 couches. Le préparateur doit réserver des palettes vides.",
"q": "Combien de palettes faut-il préparer ?",
"a": [
"4 palettes",
"3 palettes",
"5 palettes",
"13 palettes"
],
"ex": "Capacité : 40 cartons. 130 ÷ 40 = 3.25 → 4 palettes nécessaires (arrondi vers le haut : la dernière, même incomplète, doit partir).",
"ref": "Leçon 7.3"
},
{
"k": "num",
"q": "130 cartons, 40 cartons par palette : combien de palettes seront complètes ?",
"n": 3,
"tol": 0,
"u": "palettes",
"ex": "130 ÷ 40 = 3.25 → 3 palettes complètes (arrondi vers le bas).",
"ref": "Leçon 7.3"
},
{
"k": "num",
"q": "130 cartons, 40 cartons par palette : combien de cartons iront sur la dernière palette ?",
"n": 10,
"tol": 0,
"u": "cartons",
"ex": "130 − 3 × 40 = 10 cartons.",
"ref": "Leçon 7.3"
},
{
"k": "case",
"s": "Logimax doit expédier 500 cartons. Une palette reçoit 12 cartons par couche sur 5 couches. Le chef d’équipe réserve les palettes et la place dans le camion.",
"q": "Combien de palettes sont nécessaires ?",
"a": [
"9 palettes",
"8 palettes",
"10 palettes",
"7 palettes"
],
"ex": "Capacité : 12 × 5 = 60. 500 ÷ 60 = 8.33 → 9 palettes nécessaires.",
"ref": "Leçon 7.3"
},
{
"k": "case",
"s": "Même commande de 500 cartons, palettes de 60 cartons (12 par couche sur 5 couches). Le préparateur veut savoir ce qu’il mettra sur la dernière palette.",
"q": "Combien de cartons iront sur la dernière palette ?",
"a": [
"20 cartons",
"40 cartons",
"60 cartons",
"8 cartons"
],
"ex": "8 palettes complètes : 8 × 60 = 480. 500 − 480 = 20 cartons.",
"ref": "Leçon 7.3"
},
{
"k": "sort",
"q": "Dans quel sens faut-il arrondir ?",
"c": [
"Vers le haut",
"Vers le bas"
],
"it": [
[
"Palettes nécessaires pour une commande",
0
],
[
"Palettes complètes",
1
],
[
"Couches sous une hauteur maximale",
1
],
[
"Cartons sur la longueur de la palette",
1
],
[
"Cartons chargeables selon le poids",
1
],
[
"Préparateurs à engager pour une commande",
0
]
],
"ex": "Vers le haut quand tout doit partir ou être traité. Vers le bas quand une limite ne doit pas être dépassée.",
"ref": "Leçon 7.3"
},
{
"k": "num",
"q": "Masse totale maximale (palette comprise) : 500 kg. Palette vide : 20 kg. Carton : 10 kg. L’espace permet 50 cartons. Combien de cartons peut-on charger ?",
"n": 48,
"tol": 0,
"u": "cartons",
"ex": "500 − 20 = 480 kg ; 480 ÷ 10 = 48. L’espace permet 50 : on garde le plus petit, 48.",
"ref": "Leçon 7.4"
},
{
"k": "case",
"s": "La palette de Logipack peut accueillir 60 cartons selon l’espace. La masse totale ne doit pas dépasser 800 kg, palette comprise. Palette vide : 25 kg ; carton : 14 kg.",
"q": "Combien de cartons peut-on réellement charger ?",
"a": [
"55 cartons",
"60 cartons",
"57 cartons",
"56 cartons"
],
"ex": "800 − 25 = 775 kg ; 775 ÷ 14 = 55.36 → 55. Espace : 60. On garde le plus petit : 55 cartons.",
"ref": "Leçon 7.4"
},
{
"k": "case",
"s": "Le rayonnage accepte 1’000 kg de charge posée sur la palette (palette non comprise). Les cartons pèsent 18 kg et l’espace permet 64 cartons.",
"q": "Combien de cartons peut-on charger ?",
"a": [
"55 cartons",
"56 cartons",
"54 cartons",
"64 cartons"
],
"ex": "La limite ne comprend pas la palette : 1’000 ÷ 18 = 55.56 → 55 cartons (moins que les 64 de l’espace).",
"ref": "Leçon 7.4"
},
{
"k": "qcm",
"q": "L’espace permet 50 cartons et le poids en permet 48. Combien de cartons charge-t-on ?",
"a": [
"48 cartons",
"50 cartons",
"49 cartons",
"98 cartons"
],
"ex": "Deux limites : c’est la plus petite qui décide.",
"ref": "Leçon 7.4"
},
{
"k": "match",
"q": "Associez chaque grandeur à sa formule.",
"p": [
[
"Nombre de couches",
"(Hauteur max − palette vide) ÷ hauteur d’un carton"
],
[
"Capacité d’une palette",
"Cartons par couche × nombre de couches"
],
[
"Masse totale",
"Palette vide + cartons × masse d’un carton"
],
[
"Dernière palette",
"Total − palettes complètes × capacité"
]
],
"ex": "Formules des leçons 7.2 à 7.4.",
"ref": "Leçon 7.4"
},
{
"k": "odd",
"q": "Trouvez l’intrus : lequel de ces éléments ne limite PAS la hauteur d’une palette chargée ?",
"a": [
"Le poids d’un carton",
"L’alvéole du rayonnage",
"La hauteur intérieure du camion",
"Les consignes du client"
],
"ex": "Le poids limite la charge, pas la hauteur.",
"ref": "Leçon 7.2"
},
{
"k": "multi",
"q": "Quelles sont des erreurs fréquentes dans le calcul des couches ?",
"good": [
"Oublier la hauteur de la palette vide",
"Arrondir vers le haut",
"Confondre hauteur et longueur du carton"
],
"bad": [
"Arrondir vers le bas le nombre de couches",
"Retirer la palette vide de la hauteur maximale"
],
"ex": "Retirer la palette, diviser par la hauteur d’un carton, arrondir vers le bas.",
"ref": "Leçon 7.2"
},
{
"k": "case",
"s": "Cartons de 400 × 300 × 250 mm (h), 12 kg chacun, sur palette EUR de 144 mm et 25 kg. Hauteur max 1’400 mm, masse totale max 1’000 kg palette comprise.",
"q": "Combien de cartons peut-on charger ?",
"a": [
"40 cartons",
"81 cartons",
"48 cartons",
"45 cartons"
],
"ex": "Couche : 8. Couches : (1’400 − 144) ÷ 250 = 5.02 → 5. Espace : 40. Poids : 975 ÷ 12 → 81. Le plus petit : 40 cartons.",
"ref": "Leçon 7.4"
}
]
},
{
"code": "Mod. 8",
"t": "Charge utile et répartition",
"g": "Vérifier qu’un chargement respecte la charge utile du véhicule",
"col": "amber",
"ic": "truckw",
"ch": [
{
"k": "qcm",
"q": "Comment calcule-t-on la charge utile d’un véhicule ?",
"a": [
"Poids total autorisé − poids à vide",
"Poids à vide − poids total autorisé",
"Poids total autorisé + poids à vide",
"Poids à vide − charge transportée"
],
"ex": "Charge utile = poids total autorisé − poids à vide.",
"ref": "Leçon 8.1"
},
{
"k": "num",
"q": "Un camion a un poids total autorisé de 18 t et un poids à vide de 8 t. Quelle est sa charge utile en tonnes ?",
"n": 10,
"tol": 0,
"u": "t",
"ex": "18 − 8 = 10 t.",
"ref": "Leçon 8.1"
},
{
"k": "num",
"q": "Charge utile : 10’000 kg. Le camion transporte 8 palettes de 500 kg. Quelle charge reste disponible en kg ?",
"n": 6000,
"tol": 0,
"u": "kg",
"ex": "8 × 500 = 4’000 kg ; 10’000 − 4’000 = 6’000 kg.",
"ref": "Leçon 8.1"
},
{
"k": "case",
"s": "La charge utile du camion est de 10’000 kg et il porte déjà 4’000 kg. Un collègue retire encore le poids à vide du camion (8’000 kg) et conclut qu’il ne reste rien.",
"q": "Que lui répondez-vous ?",
"a": [
"Faux : la charge utile déduit déjà le poids à vide",
"Juste : le poids du camion se retire à chaque calcul",
"Faux : il faut plutôt ajouter le poids à vide",
"Juste : il reste alors exactement 2’000 kg"
],
"ex": "Le poids du véhicule n’entre jamais dans le calcul de chargement : il est déjà déduit de la charge utile. Il reste 6’000 kg.",
"ref": "Leçon 8.1"
},
{
"k": "tf",
"q": "Le poids du véhicule à vide entre dans le calcul de la charge disponible.",
"v": false,
"ex": "Il est déjà déduit dans la charge utile.",
"ref": "Leçon 8.1"
},
{
"k": "num",
"q": "Une camionnette a une charge utile de 3.5 t. Elle transporte 2’150 kg. Quelle charge reste disponible en kg ?",
"n": 1350,
"tol": 0,
"u": "kg",
"ex": "3.5 t = 3’500 kg. 3’500 − 2’150 = 1’350 kg.",
"ref": "Leçon 8.1"
},
{
"k": "num",
"q": "Un camion a une charge utile de 12’000 kg. Il transporte 9’000 kg. Quel est son taux de chargement en % ?",
"n": 75,
"tol": 0,
"u": "%",
"ex": "9’000 ÷ 12’000 × 100 = 75 %.",
"ref": "Leçon 8.2"
},
{
"k": "num",
"q": "Un camion a une charge utile de 20’000 kg. Il est chargé à 85 %. Quelle masse transporte-t-il en kg ?",
"n": 17000,
"tol": 0,
"u": "kg",
"ex": "20’000 × 85 ÷ 100 = 17’000 kg.",
"ref": "Leçon 8.2"
},
{
"k": "case",
"s": "Le camion de TransLéman a une charge utile de 24’000 kg. Ce soir, il part avec 17’000 kg. Le dispatcher note le taux de chargement, arrondi au dixième.",
"q": "Quel est le taux de chargement ?",
"a": [
"70.8 %",
"70.9 %",
"141.2 %",
"29.2 %"
],
"ex": "17’000 ÷ 24’000 × 100 = 70.83… → 70.8 %.",
"ref": "Leçon 8.2"
},
{
"k": "qcm",
"q": "Un camion est chargé à 75 % de sa charge utile. Quelle part reste libre ?",
"a": [
"25 %",
"75 %",
"33 %",
"125 %"
],
"ex": "100 % − 75 % = 25 %.",
"ref": "Leçon 8.2"
},
{
"k": "tf",
"q": "Si on divise la charge utile par la charge transportée, on obtient un résultat supérieur à 100 %.",
"v": true,
"ex": "C’est le signe d’une division inversée : on divise la charge transportée par la charge utile.",
"ref": "Leçon 8.2"
},
{
"k": "num",
"q": "Il reste 2’000 kg de charge disponible. Chaque palette pèse 450 kg. Combien de palettes peut-on encore charger ?",
"n": 4,
"tol": 0,
"u": "palettes",
"ex": "2’000 ÷ 450 = 4.44 → 4 palettes. Contrôle : 4 × 450 = 1’800 kg ; 5 × 450 = 2’250 kg (surcharge).",
"ref": "Leçon 8.3"
},
{
"k": "case",
"s": "Un camion de messagerie a encore 3’000 kg de charge disponible. Les colis à charger pèsent 35 kg chacun. Le chauffeur veut en prendre le maximum.",
"q": "Combien de colis peut-il encore charger ?",
"a": [
"85 colis",
"86 colis",
"84 colis",
"87 colis"
],
"ex": "3’000 ÷ 35 = 85.71 → 85 colis. Arrondir à 86 ferait dépasser la charge utile (86 × 35 = 3’010 kg).",
"ref": "Leçon 8.3"
},
{
"k": "tf",
"q": "Si le calcul donne 4.6 palettes, on peut encore charger 5 palettes.",
"v": false,
"ex": "Toujours vers le bas : 4 palettes. Une surcharge n’est jamais tolérée.",
"ref": "Leçon 8.3"
},
{
"k": "qcm",
"q": "Pourquoi arrondit-on toujours vers le bas le nombre d’unités transportables ?",
"a": [
"Une unité de plus ferait dépasser la charge utile",
"Les calculateurs ne gèrent pas les décimales",
"Le client paie seulement les palettes entières",
"C’est la règle mathématique de tout arrondi"
],
"ex": "On cherche ce qui peut encore monter sans dépasser une limite.",
"ref": "Leçon 8.3"
},
{
"k": "sort",
"q": "Dans quel sens faut-il arrondir ?",
"c": [
"Vers le haut",
"Vers le bas"
],
"it": [
[
"Palettes pouvant encore monter dans le camion",
1
],
[
"Colis supplémentaires dans la camionnette",
1
],
[
"Palettes nécessaires pour expédier une commande",
0
],
[
"Trajets nécessaires pour livrer 50 t avec un camion de 12 t",
0
],
[
"Cartons chargeables sous une limite de poids",
1
]
],
"ex": "Limite à respecter : vers le bas. Tout doit partir : vers le haut.",
"ref": "Leçon 8.3"
},
{
"k": "num",
"q": "Un camion transporte 6 palettes de 800 kg et 4 palettes de 600 kg. Quelle est la charge transportée en kg ?",
"n": 7200,
"tol": 0,
"u": "kg",
"ex": "6 × 800 = 4’800 ; 4 × 600 = 2’400 ; 4’800 + 2’400 = 7’200 kg.",
"ref": "Leçon 8.4"
},
{
"k": "case",
"s": "Le camion de Logimax a une charge utile de 12’000 kg. Il transporte déjà 6 palettes de 800 kg et 4 palettes de 600 kg. On veut ajouter des palettes de 900 kg.",
"q": "Combien de palettes de 900 kg peut-il encore charger ?",
"a": [
"5 palettes",
"6 palettes",
"13 palettes",
"3 palettes"
],
"ex": "Transporté : 7’200 kg. Disponible : 4’800 kg. 4’800 ÷ 900 = 5.33 → 5 palettes.",
"ref": "Leçon 8.4"
},
{
"k": "case",
"s": "Une semi-remorque de Transvalor (charge utile 18’000 kg) porte 10 palettes de 750 kg et 8 palettes de 520 kg. Le dispatcher contrôle le taux de chargement.",
"q": "Quel est le taux de chargement, arrondi au dixième ?",
"a": [
"64.8 %",
"35.2 %",
"63.0 %",
"154.4 %"
],
"ex": "7’500 + 4’160 = 11’660 kg. 11’660 ÷ 18’000 × 100 = 64.8 %.",
"ref": "Leçon 8.4"
},
{
"k": "case",
"s": "Une camionnette (charge utile 1’200 kg) transporte 15 colis de 32 kg et 6 colis de 48 kg. Le livreur veut ajouter des colis de 25 kg pour un dernier client.",
"q": "Combien de colis de 25 kg peut-il encore charger ?",
"a": [
"17 colis",
"18 colis",
"48 colis",
"30 colis"
],
"ex": "Transporté : 480 + 288 = 768 kg. Disponible : 432 kg. 432 ÷ 25 = 17.28 → 17 colis.",
"ref": "Leçon 8.4"
},
{
"k": "order",
"q": "Remettez dans l’ordre la méthode pour un chargement mixte.",
"a": [
"Calculer le poids de chaque groupe de palettes",
"Additionner les groupes",
"Soustraire le total de la charge utile",
"Diviser par le poids d’une palette et arrondir vers le bas"
],
"ex": "Groupe par groupe, additionner, soustraire de la charge utile, puis diviser et arrondir vers le bas.",
"ref": "Leçon 8.4"
},
{
"k": "match",
"q": "Associez chaque notion à sa définition.",
"p": [
[
"Poids total autorisé",
"Poids maximal du véhicule chargé"
],
[
"Poids à vide",
"Poids du véhicule sans chargement"
],
[
"Charge utile",
"Poids maximal de marchandises transportables"
],
[
"Charge disponible",
"Charge utile − charge déjà transportée"
]
],
"ex": "Charge utile = poids total autorisé − poids à vide ; charge disponible = charge utile − charge transportée.",
"ref": "Leçon 8.1"
},
{
"k": "multi",
"q": "Quelles sont les bonnes pratiques de répartition des charges ?",
"good": [
"Placer les palettes lourdes en bas",
"Placer les palettes lourdes vers le centre du plateau",
"Équilibrer le poids entre l’avant et l’arrière",
"Équilibrer le poids entre la gauche et la droite"
],
"bad": [
"Mettre les palettes lourdes à l’arrière pour décharger vite",
"Poser les palettes légères sous les lourdes"
],
"ex": "Respecter la charge utile ne suffit pas : la répartition fait partie d’un chargement sûr.",
"ref": "Leçon 8.4"
},
{
"k": "odd",
"q": "Trouvez l’intrus : lequel n’est PAS une erreur ?",
"a": [
"Contrôler en ajoutant une unité de plus",
"Oublier un groupe de palettes dans l’addition",
"Multiplier toutes les palettes par un seul poids",
"Arrondir vers le haut les palettes en plus"
],
"ex": "Ajouter une unité de plus pour vérifier qu’elle dépasse la charge disponible est le bon contrôle.",
"ref": "Leçon 8.4"
},
{
"k": "case",
"s": "Une camionnette a une charge utile de 7.5 t. Elle transporte déjà 5’600 kg de matériel. Le magasinier veut savoir quelle charge il peut encore ajouter.",
"q": "Quelle charge reste disponible ?",
"a": [
"1’900 kg",
"2’900 kg",
"190 kg",
"13’100 kg"
],
"ex": "7.5 t = 7’500 kg. 7’500 − 5’600 = 1’900 kg. Piège : mélanger kg et tonnes.",
"ref": "Leçon 8.1"
},
{
"k": "fill",
"q": "Charge utile = poids total autorisé − ___.",
"a": [
"poids à vide",
"charge disponible",
"charge transportée",
"poids des palettes"
],
"ex": "La charge utile se déduit des documents du véhicule.",
"ref": "Leçon 8.1"
},
{
"k": "case",
"s": "Un camion de 10 t de charge utile doit partir avec 14 palettes de 680 kg chacune. Le chef de quai contrôle le chargement avant le départ.",
"q": "Le chargement est-il conforme ?",
"a": [
"Oui : 9’520 kg, il reste 480 kg",
"Non : 9’520 kg, il dépasse de 480 kg",
"Oui : 8’400 kg, il reste 1’600 kg",
"Non : 14 palettes dépassent toujours 10 t"
],
"ex": "14 × 680 = 9’520 kg ≤ 10’000 kg : conforme (95.2 %). Il reste 480 kg.",
"ref": "Leçon 8.2"
},
{
"k": "num",
"q": "Un camion a un poids total autorisé de 26 t et un poids à vide de 11.2 t. Quelle est sa charge utile en kg ?",
"n": 14800,
"tol": 0,
"u": "kg",
"img": "charge",
"ex": "26 − 11.2 = 14.8 t = 14’800 kg.",
"ref": "Leçon 8.1"
},
{
"k": "case",
"s": "La camionnette d’un livreur (charge utile 1’000 kg) porte déjà 620 kg. Chez le dernier client, on lui demande de reprendre 9 colis de 45 kg.",
"q": "Que peut-il faire ?",
"a": [
"Prendre 8 colis : 1 de plus dépasserait de 25 kg",
"Prendre les 9 colis : la charge reste sous 1’000 kg",
"Prendre 7 colis seulement, par sécurité",
"Ne rien prendre : la camionnette est déjà pleine"
],
"ex": "Disponible : 1’000 − 620 = 380 kg. 380 ÷ 45 = 8.44 → 8 colis. Avec 9 colis : 620 + 405 = 1’025 kg, surcharge de 25 kg.",
"ref": "Leçon 8.3"
}
]
}
];
window.LEVELS = [[0, "Stagiaire"], [150, "Apprenti·e logisticien·ne"], [400, "Logisticien·ne en formation"], [800, "Logisticien·ne CFC"], [1300, "Référent·e calcul professionnel"], [2000, "Expert·e chargement et capacité"], [3000, "Formateur·rice logistique"]];
window.META = {
 "title": "Mathématiques – Applications professionnelles",
 "eyebrow": "Logisticien·ne CFC · Mathématiques, modules 4 à 8",
 "key": "atelier-maths-applications-v1",
 "intro": "Entraînez-vous aux calculs du métier : règle de trois, pourcentages, taux d’occupation et de chargement, palettisation et charge utile.",
 "goal": 20,
 "exam": {
  "n": 25,
  "min": 35
 },
 "cover": {
  "src": "img/cover.jpg",
  "alt": "Image de titre du cours Mathématiques – Applications professionnelles"
 },
 "imgs": {
  "couche12": {
   "src": "img/couche12.png",
   "alt": "Palette EUR vue de dessus : 3 rangées de 4 cartons de 400 × 200 mm"
  },
  "couche8": {
   "src": "img/couche8.png",
   "alt": "Palette EUR vue de dessus : 4 cartons sur la longueur et 2 sur la largeur, 300 mm sur la longueur"
  },
  "couche6": {
   "src": "img/couche6.png",
   "alt": "Palette EUR vue de dessus : 3 cartons sur la longueur et 2 sur la largeur, 400 mm sur la longueur, bande vide"
  },
  "couche7": {
   "src": "img/couche7.png",
   "alt": "Palette EUR vue de dessus : cartons de 300 × 400 mm, 4 sur la longueur et 2 sur la largeur, mais un emplacement laissé vide (7 cartons)"
  },
  "hauteur": {
   "src": "img/hauteur.png",
   "alt": "Palette vue de côté : hauteur maximale 1’350 mm du sol au sommet, palette vide 150 mm, carton de 300 mm de haut, nombre de couches à trouver"
  },
  "charge": {
   "src": "img/charge.png",
   "alt": "Schéma d’un camion : poids total autorisé = poids à vide + charge utile"
  }
 },
 "icons": {
  "ratio": "<path d=\"M4 7h7M4 17h7M13 7h7M13 17h7\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\"/><path d=\"M12 3v18\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-dasharray=\"2 2.5\"/><path d=\"M17 10l-2 4M7 10l2 4\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\"/>",
  "percent": "<path d=\"M19 5L5 19\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\"/><circle cx=\"7\" cy=\"7\" r=\"2.5\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"/><circle cx=\"17\" cy=\"17\" r=\"2.5\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"/>",
  "fill": "<rect x=\"4\" y=\"3\" width=\"16\" height=\"18\" rx=\"1.5\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"/><path d=\"M4 9h16M4 15h16\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"/><path d=\"M7 18h4M7 12h7\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\"/>",
  "pallet": "<rect x=\"5\" y=\"4\" width=\"6\" height=\"5\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"/><rect x=\"13\" y=\"4\" width=\"6\" height=\"5\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"/><rect x=\"5\" y=\"11\" width=\"14\" height=\"5\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"/><path d=\"M3 18h18M5 18v2M12 18v2M19 18v2\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\"/>",
  "truckw": "<path d=\"M2 7h11v9H2zM13 10h4l3 3v3h-7z\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linejoin=\"round\"/><circle cx=\"6\" cy=\"18\" r=\"2\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"/><circle cx=\"16\" cy=\"18\" r=\"2\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"/><path d=\"M5 11h5\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\"/>"
 },
 "note": {
  "text": "Jeu construit d’après le cours « Mathématiques | Applications professionnelles » (modules 4 à 8, leçons 4.1 à 8.4). Taux de TVA normal en Suisse : 8.1 %. Cours complet sur",
  "url": "https://logiprof.fontes-oliveira.ch",
  "link": "logiprof"
 }
};
window.META.langs = ["fr","de","it"];
