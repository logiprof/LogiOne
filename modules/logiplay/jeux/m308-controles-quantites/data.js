/* M308 – Les contrôles de quantités (logiprof, leçons 8.0 à 8.3).
   La leçon « Objectifs » reprend c1.7 et c2.2 (déjà travaillés en M304, M306, M307) et c2.10 (contrôle détaillé).
   Les leçons portent sur compter, mesurer et peser : le jeu les rattache à c2.10, découpé en trois parties.
   a[0] = bonne réponse. */
window.OBJ = [
/* ============ 1 · c2.10a UNITÉS ET COMPTAGE ============ */
{code:"c2.10a", t:"Unités et comptage", g:"c2.10 Je décris les travaux effectués dans le cadre du contrôle détaillé et explique les différences avec l’identification globale. (C2) — partie 1 : unités de mesure, histoire du mètre, comptage par quantités partielles", col:"teal", ic:"count",
ch:[
{k:"multi",q:"Quelles sont les trois fonctions essentielles du contrôle des quantités ?",good:["Compter","Mesurer","Peser"],bad:["Vendre","Facturer"],ex:"Leçon 8.0.",ref:"Leçon 8.0"},
{k:"match",q:"Associez chaque unité à sa valeur.",p:[["Baril (USA)","158,8 L"],["Gallon (USA)","3,79 L"],["Gallon (GB)","4,55 L"],["Pint (GB)","0,568 L"]],ex:"Unités de la leçon 8.0.",ref:"Leçon 8.0"},
{k:"match",q:"Associez chaque unité de comptage ou de mesure à sa valeur.",p:[["Douzaine","12 unités"],["Grosse","144 unités"],["Inch (pouce)","2,54 cm"],["Carat","0,2 g"]],ex:"Leçon 8.0.",ref:"Leçon 8.0"},
{k:"num",q:"Combien d’unités contient une grosse ?",n:144,tol:0,u:"unités",ex:"12 douzaines.",ref:"Leçon 8.0"},
{k:"case",s:"Un transitaire vous parle de « 4 TEU » pour une expédition maritime.",q:"Que désigne l’unité TEU ?",a:["Un conteneur de 20 pieds","Une tonne de marchandise","Un camion de 40 tonnes","Une palette de 1,2 m"],ex:"Twenty-foot Equivalent Unit.",ref:"Leçon 8.0"},
{k:"case",s:"Un navire porte-conteneurs est annoncé avec une capacité de 2000 TEU.",q:"Que signifie ce chiffre ?",a:["Il peut transporter 2000 conteneurs de 20 pieds","Il pèse exactement 2000 tonnes à vide au port","Il mesure 2000 mètres de long d’un bout à l’autre","Il transporte 2000 barils de pétrole brut"],ex:"Exemple de la leçon 8.0.",ref:"Leçon 8.0"},
{k:"num",q:"Un pétrolier transporte 10 000 barils. Combien de litres (1 baril = 158,8 L) ?",n:1588000,tol:0,u:"L",ex:"10 000 × 158,8 = 1 588 000 L.",ref:"Leçon 8.0"},
{k:"case",s:"Un client commande des écrans de 24 pouces.",q:"Combien mesure environ la diagonale en cm (1 pouce = 2,54 cm) ?",a:["Environ 61 cm","Environ 24 cm","Environ 94 cm","Environ 10 cm"],ex:"24 × 2,54 = 60,96 cm.",ref:"Leçon 8.0"},
{k:"qcm",q:"Quel système de mesure domine en Europe ?",a:["Le système décimal","Le système des pouces","Le système des barils","Le système des douzaines"],ex:"Certaines unités comme la douzaine restent utilisées.",ref:"Leçon 8.0"},
{k:"case",s:"Un plan ancien indique une longueur de 10 pieds.",q:"Combien mesure un pied (unité historique) ?",a:["0,3048 m","2,54 cm","1,132 m","0,568 m"],ex:"Leçon 8.1.",ref:"Leçon 8.1"},
{k:"order",q:"Remettez dans l’ordre l’histoire du mètre.",a:["Moyen Âge : unités différentes selon les régions","1791 : système uniforme décidé en France","1799 : mètre étalon en platine et iridium","1875 : Convention internationale du mètre"],ex:"Leçon 8.1.",ref:"Leçon 8.1"},
{k:"qcm",q:"Sur quoi le mètre a-t-il été défini à l’origine ?",a:["La distance entre le pôle Nord et l’équateur","La longueur du pied du roi de France","La hauteur de la tour Eiffel de Paris","La largeur d’une palette européenne"],ex:"Décision de l’Assemblée nationale française en 1791.",ref:"Leçon 8.1"},
{k:"num",q:"Combien de pays ont signé la Convention du mètre en 1875 ?",n:17,tol:0,u:"pays",ex:"Dont la Suisse.",ref:"Leçon 8.1"},
{k:"tf",q:"La Suisse a signé la Convention internationale du mètre en 1875.",v:true,ex:"Elle fait partie des 17 pays signataires.",ref:"Leçon 8.1"},
{k:"case",s:"Vous devez contrôler un envoi de 1536 pièces réparties en cartons de 12.",q:"Quelle méthode est la plus efficace ?",a:["Compter les 128 cartons","Compter chaque pièce une à une","Peser une seule pièce","Estimer à l’œil sans compter"],ex:"Utilisation de quantités partielles.",ref:"Leçon 8.2"},
{k:"num",q:"1536 pièces en cartons de 12 : combien de cartons doit-on compter ?",n:128,tol:0,u:"cartons",ex:"1536 ÷ 12 = 128.",ref:"Leçon 8.2"},
{k:"multi",q:"Quels sont les trois niveaux du comptage organisé ?",good:["Pièces par carton","Cartons par couche","Nombre de couches"],bad:["Couleur des cartons","Nom du fournisseur"],ex:"Leçon 8.2.",ref:"Leçon 8.2"},
{k:"num",q:"Une palette a 4 couches de 8 cartons. Combien de cartons au total ?",n:32,tol:0,u:"cartons",ex:"8 × 4 = 32.",ref:"Leçon 8.2"},
{k:"num",q:"(2 couches × 12 pièces) + (1 couche × 27 pièces) + (3 couches × 4 emballages × 21 pièces) = ? pièces",n:303,tol:0,u:"pièces",ex:"24 + 27 + 252 = 303.",ref:"Leçon 8.2"},
{k:"case",s:"Une palette compte 5 couches de 6 cartons de 24 bouteilles.",q:"Combien de bouteilles contient-elle ?",a:["720 bouteilles","144 bouteilles","30 bouteilles","600 bouteilles"],ex:"5 × 6 × 24 = 720.",ref:"Leçon 8.2"},
{k:"case",s:"À l’entrée d’un salon professionnel, vous devez compter les visiteurs toute la journée.",q:"Quel outil utilisez-vous ?",a:["Un compteur manuel","Un pied à coulisse","Une balance compteuse","Un débitmètre"],ex:"Un clic = une unité.",ref:"Leçon 8.2.1"},
{k:"sort",q:"Contrôle détaillé ou identification globale ?",c:["Identification globale","Contrôle détaillé"],it:[["Vérifier l’adresse du destinataire",0],["Compter les pièces par carton",1],["Contrôler le nombre de palettes",0],["Peser les articles en vrac",1],["Mesurer les dimensions d’un article",1]],ex:"Le contrôle détaillé vérifie quantités et qualité article par article (voir M304).",ref:"Objectif c2.10"},
{k:"odd",q:"Laquelle n’est pas une unité de comptage ?",a:["Le gallon","La douzaine","La grosse","La paire"],ex:"Le gallon mesure un volume.",ref:"Leçon 8.0"},
{k:"fill",q:"Une paire correspond à ___ unités.",a:["2","12","144","10"],ex:"Chaussures, textiles.",ref:"Leçon 8.0"},
{k:"case",s:"Un carton contient 3 douzaines d’œufs.",q:"Combien d’œufs contient-il ?",a:["36 œufs","30 œufs","12 œufs","144 œufs"],ex:"3 × 12 = 36.",ref:"Leçon 8.0"}
]},
/* ============ 2 · c2.10b MESURER LONGUEURS ET VOLUMES ============ */
{code:"c2.10b", t:"Mesurer longueurs et volumes", g:"c2.10 Je décris les travaux effectués dans le cadre du contrôle détaillé et explique les différences avec l’identification globale. (C2) — partie 2 : outils de mesure des longueurs et des volumes", col:"indigo", ic:"ruler",
ch:[
{k:"qcm",q:"À quoi sert un compteur métrique ?",a:["Mesurer et diviser fils, cordages et câbles","Compter les visiteurs d’un entrepôt chaque jour","Peser des palettes de marchandises au quai","Mesurer le débit d’un liquide dans un tuyau"],ex:"Souvent remplacé par la technologie laser.",ref:"Leçon 8.2.2"},
{k:"case",s:"Un client commande 35 m de câble électrique sur une grande bobine.",q:"Quel outil utilisez-vous pour mesurer et couper ?",a:["Une roue de mesure reliée à un compteur","Un micromètre numérique de haute précision","Une balance romaine à contrepoids mobile","Un gabarit postal pour lettres et vis"],ex:"Affiche la longueur en cm ou en m.",ref:"Leçon 8.2.2"},
{k:"tf",q:"Les mètres pliants ou à ruban peuvent présenter des écarts de plusieurs millimètres.",v:true,ex:"D’où l’intérêt d’outils certifiés.",ref:"Leçon 8.2.3"},
{k:"qcm",q:"Selon quelle norme le cours recommande-t-il des outils de mesure certifiés ?",a:["DIN 866","ISO 780","EPAL 1","CMR 6"],ex:"DIN = Deutsches Institut für Normung.",ref:"Leçon 8.2.3"},
{k:"multi",q:"Que faire pour vérifier la précision d’un outil de mesure avant de l’utiliser ?",good:["Vérifier son état (usure, déformation)","Le comparer à un instrument de référence","Le tester sur une longueur connue"],bad:["Le peindre en jaune","Le laisser au soleil"],ex:"Leçon 8.2.3.",ref:"Leçon 8.2.3"},
{k:"qcm",q:"Que mesure un pied à coulisse ?",a:["Épaisseur, diamètre et profondeur","Poids, volume et température","Débit, pression et vitesse","Nombre de pièces dans un lot"],ex:"Aussi appelé calibre.",ref:"Leçon 8.2.3"},
{k:"case",s:"Votre chef demande la précision du pied à coulisse standard de l’atelier.",q:"Quelle est la précision habituelle d’un pied à coulisse ?",a:["1/10 mm","1 cm","1 m","1/1000 mm"],ex:"Certains modèles sont plus fins.",ref:"Leçon 8.2.3"},
{k:"match",q:"Associez chaque partie du pied à coulisse à son usage.",p:[["Mâchoires extérieures","Diamètre ou largeur"],["Mâchoires intérieures","Espace ou diamètre interne"],["Tige de profondeur","Profondeur d’un trou"]],ex:"Consignes de la leçon 8.2.3.",ref:"Leçon 8.2.3"},
{k:"case",s:"Vous devez vérifier le diamètre de tiges métalliques avant expédition.",q:"Quel outil utilisez-vous ?",a:["Un pied à coulisse","Un compteur manuel","Un débitmètre","Une balance de table"],ex:"Exemple de la leçon 8.2.3.",ref:"Leçon 8.2.3"},
{k:"multi",q:"Quelles erreurs éviter avec un pied à coulisse ?",good:["L’incliner pendant la mesure","Forcer la fermeture des mâchoires","Oublier de vérifier le zéro"],bad:["Le nettoyer avant usage","Mesurer plusieurs fois en cas de doute"],ex:"Consignes de la leçon 8.2.3.",ref:"Leçon 8.2.3"},
{k:"order",q:"Remettez dans l’ordre une mesure au pied à coulisse.",a:["Vérifier que l’outil est propre et à zéro","Placer l’objet dans les bonnes mâchoires","Serrer légèrement sans déformer","Lire la valeur sur le vernier ou l’écran"],ex:"Consignes de la leçon 8.2.3.",ref:"Leçon 8.2.3"},
{k:"qcm",q:"Quel ancien nom donne-t-on au micromètre ?",a:["Palmer","Vernier","Chablon","Calibre"],ex:"Précision de 0,01 mm à 1/1000 mm.",ref:"Leçon 8.2.3"},
{k:"case",s:"Une imprimerie doit contrôler l’épaisseur de feuilles de papier au centième de millimètre.",q:"Quel instrument convient ?",a:["Un micromètre","Un mètre pliant","Un pied à coulisse simple","Un compteur métrique"],ex:"Mesure très fine.",ref:"Leçon 8.2.3"},
{k:"case",s:"Une pièce métallique sort d’une machine et est encore chaude.",q:"Pourquoi ne faut-il pas la mesurer tout de suite au micromètre ?",a:["La dilatation thermique fausse la mesure","Le micromètre fondrait immédiatement","La pièce deviendrait trop lourde","Ce n’est pas un problème du tout"],ex:"Consigne de la leçon 8.2.3.",ref:"Leçon 8.2.3"},
{k:"fill",q:"Avec un micromètre, l’objet est placé entre l’enclume et la ___.",a:["broche","mâchoire","tige","roue"],ex:"Consignes du micromètre.",ref:"Leçon 8.2.3"},
{k:"case",s:"Au guichet postal, l’employé glisse une enveloppe dans une plaque à fente.",q:"À quoi sert ce gabarit (chablon) à la poste ?",a:["Contrôler la taille des lettres","Peser les colis lourds","Mesurer l’épaisseur du papier","Compter les timbres vendus"],ex:"Il sert aussi pour mesurer des vis.",ref:"Leçon 8.2.3"},
{k:"qcm",q:"Comment fonctionne une pompe à piston ?",a:["Aspiration, refoulement et compteur de mouvements","Pesée du liquide sur une balance romaine du marché","Mesure laser de la surface du liquide dans la cuve","Comptage manuel des gouttes une par une à l’œil"],ex:"Leçon 8.2.4.",ref:"Leçon 8.2.4"},
{k:"qcm",q:"Que mesure un débitmètre ?",a:["La quantité de fluide écoulée par unité de temps","Le poids d’une palette sur un pont basculant","L’épaisseur d’une feuille de métal très fine","La diagonale d’un écran mesurée en pouces"],ex:"Des modèles modernes utilisent les ultrasons.",ref:"Leçon 8.2.4"},
{k:"multi",q:"À quoi sert un scanner volumétrique en entrepôt ?",good:["Vérifier les dimensions à la réception","Choisir l’emplacement de stockage adapté","Choisir le carton d’expédition optimal"],bad:["Peser les camions","Compter les visiteurs"],ex:"Utilisé par exemple chez Amazon.",ref:"Leçon 8.2.4"},
{k:"case",s:"Un centre logistique reçoit 100 boîtes de tailles différentes.",q:"Quel outil mesure automatiquement leurs dimensions ?",a:["Un scanner volumétrique","Un gobelet gradué","Une balance à bras égaux","Un compteur manuel"],ex:"Il optimise stockage et expédition.",ref:"Leçon 8.2.4"},
{k:"sort",q:"Mesure de longueur ou de volume ?",c:["Longueur","Volume"],it:[["Pied à coulisse",0],["Débitmètre",1],["Micromètre",0],["Gobelet gradué",1],["Compteur métrique",0],["Pompe à piston",1]],ex:"Leçons 8.2.2 à 8.2.4.",ref:"Leçon 8.2"},
{k:"odd",q:"Lequel n’est pas un outil de mesure des longueurs ?",a:["Le débitmètre","Le pied à coulisse","Le micromètre","Le mètre à ruban"],ex:"Le débitmètre mesure un débit de fluide.",ref:"Leçon 8.2"},
{k:"tf",q:"Les compteurs métriques analogiques n’existent plus aujourd’hui.",v:false,ex:"Ils existent toujours et fonctionnent manuellement.",ref:"Leçon 8.2.2"},
{k:"case",s:"Votre mètre à ruban affiche 2 mm de plus que l’instrument de référence sur 1 m.",q:"Que faites-vous ?",a:["Je ne l’utilise plus pour les mesures précises","Je l’utilise, 2 mm ne changent jamais rien","Je corrige moi-même le ruban au feutre noir","Je le prête simplement à un collègue pressé"],ex:"Utiliser un outil adapté à la précision requise.",ref:"Leçon 8.2.3"},
{k:"case",s:"Vous devez mesurer la profondeur d’un trou de fixation dans une pièce.",q:"Quelle partie du pied à coulisse utilisez-vous ?",a:["La tige de profondeur","Les mâchoires extérieures","Les mâchoires intérieures","Le vernier seul"],ex:"Consignes de la leçon 8.2.3.",ref:"Leçon 8.2.3"}
]},
/* ============ 3 · c2.10c PESER ============ */
{code:"c2.10c", t:"Peser", g:"c2.10 Je décris les travaux effectués dans le cadre du contrôle détaillé et explique les différences avec l’identification globale. (C2) — partie 3 : balances, tare, poids net et brut, vérification des instruments", col:"violet", ic:"scale",
ch:[
{k:"qcm",q:"En quelle unité se mesure le poids, au sens physique ?",a:["Le newton (N)","Le litre (L)","Le pouce (in)","Le carat (ct)"],ex:"Le poids résulte de l’attraction terrestre.",ref:"Leçon 8.2.5"},
{k:"sort",q:"Balance par comparaison de masses ou dynamomètre ?",c:["Comparaison de masses","Dynamomètre"],it:[["Balance à bras égaux",0],["Balance à ressort",1],["Balance romaine",0],["Dynamomètre numérique sous une grue",1]],ex:"Deux principes de mesure du poids.",ref:"Leçon 8.2.5"},
{k:"case",s:"Sur un marché, le vendeur déplace un contrepoids le long d’un long bras gradué.",q:"Comment fonctionne cette balance romaine ?",a:["Bras de levier inégal et contrepoids mobile","Deux plateaux égaux et des poids normalisés","Un ressort hélicoïdal relié à une aiguille","Un capteur électronique relié à un écran"],ex:"Encore utilisée sur certains marchés.",ref:"Leçon 8.2.5"},
{k:"tf",q:"La précision d’une balance à ressort peut varier selon la localisation géographique.",v:true,ex:"Leçon 8.2.5.",ref:"Leçon 8.2.5"},
{k:"num",q:"Jusqu’à combien de kilogrammes un dynamomètre numérique relié à une grue peut-il peser selon le cours ?",n:12500,tol:0,u:"kg",ex:"Jusqu’à 12,5 t, soit 12 500 kg.",ref:"Leçon 8.2.5"},
{k:"case",s:"Vous devez compter 500 écrous pour une commande.",q:"Quel outil est le plus rapide ?",a:["Une balance compteuse","Un compteur manuel","Un pied à coulisse","Un pont basculant"],ex:"Elle calcule le nombre à partir d’un poids de référence.",ref:"Leçon 8.2.6"},
{k:"order",q:"Remettez dans l’ordre l’utilisation d’une balance compteuse.",a:["Peser une petite quantité de référence (ex. 10 pièces)","La balance enregistre le poids unitaire","Poser le lot entier","Lire le nombre total de pièces"],ex:"Principe de fonctionnement.",ref:"Leçon 8.2.6"},
{k:"num",q:"10 vis pèsent 50 g. Un lot pèse 2500 g. Combien de vis contient-il ?",n:500,tol:0,u:"vis",ex:"Poids unitaire 5 g ; 2500 ÷ 5 = 500.",ref:"Leçon 8.2.6"},
{k:"match",q:"Associez chaque balance à son usage.",p:[["Balance de table","Colis légers avant expédition"],["Pèse-palettes","Palette avant envoi"],["Pont basculant","Camions et wagons"],["Balance compteuse","Petites pièces en grande quantité"]],ex:"Leçon 8.2.6.",ref:"Leçon 8.2.6"},
{k:"case",s:"Un camion chargé pèse 32 t sur le pont basculant ; à vide, il pèse 14 t.",q:"Quel est le poids du chargement ?",a:["18 t","46 t","14 t","32 t"],ex:"Poids total − poids à vide.",ref:"Leçon 8.2.6"},
{k:"case",s:"Après avoir déposé une palette sur la plate-forme de pesage, le cariste reste avec son chariot sur la balance.",q:"Quel est le problème ?",a:["Le poids affiché inclut le chariot","La balance pèse plus juste ainsi","Le chariot recharge sa batterie","Il n’y a aucun problème"],ex:"Ne jamais rester sur la balance, retirer le véhicule.",ref:"Leçon 8.2.6"},
{k:"case",s:"Le cariste lit le poids de la palette sur l’écran de son transpalette.",q:"Quel avantage offre ce transpalette avec pesage intégré ?",a:["Peser sans passer par une balance fixe","Soulever des charges de 40 tonnes","Compter les cartons automatiquement","Mesurer la longueur des palettes"],ex:"Leçon 8.2.6.",ref:"Leçon 8.2.6"},
{k:"multi",q:"Quelles règles respecter avec une balance ?",good:["La garder propre","La faire entretenir par un spécialiste","Ne pas dépasser sa capacité maximale","Respecter la charge minimale"],bad:["La réparer soi-même","Y faire rouler n’importe quel chariot"],ex:"Leçon 8.3.",ref:"Leçon 8.3"},
{k:"tf",q:"Une balance électronique doit être éteinte entre chaque pesée pour rester précise.",v:false,ex:"Elle doit rester allumée toute la journée après préchauffage.",ref:"Leçon 8.3"},
{k:"case",s:"À vide, la balance de l’entrepôt affiche 0,2 kg.",q:"Que faites-vous ?",a:["Je la calibre : remise à zéro","Je soustrais 0,2 kg à la main","Je la secoue jusqu’à 0","Je l’utilise telle quelle"],ex:"Calibrer = remettre à zéro à vide.",ref:"Leçon 8.3.1"},
{k:"case",s:"Vous devez peser 5 kg de clous dans un bac de 500 g.",q:"Comment procédez-vous ?",a:["Poser le bac vide, faire la tare, puis ajouter les clous","Peser les clous sur la main puis les mettre dans le bac","Ajouter simplement 500 g de clous de plus dans le bac","Peser le bac après les clous et additionner les deux"],ex:"Tarer = soustraire le poids de l’emballage.",ref:"Leçon 8.3.1"},
{k:"match",q:"Associez chaque notion à sa définition.",p:[["Calibrer","Remettre à zéro à vide"],["Tarer","Soustraire le poids de l’emballage"],["Poids net","Produit sans emballage"],["Poids brut","Produit avec emballage"]],ex:"Leçon 8.3.1.",ref:"Leçon 8.3.1"},
{k:"num",q:"Un sac de riz pèse 12 kg au total, dont 500 g d’emballage. Quel est le poids net en grammes ?",n:11500,tol:0,u:"g",ex:"12 000 − 500 = 11 500 g.",ref:"Leçon 8.3.1"},
{k:"num",q:"20 cartons de 10 kg de produit + 2 kg d’emballage chacun : quel est le poids brut total en kg ?",n:240,tol:0,u:"kg",ex:"20 × 12 = 240 kg.",ref:"Leçon 8.3.1"},
{k:"qcm",q:"Pourquoi le poids brut est-il essentiel en transport ?",a:["Pour respecter les charges autorisées","Pour calculer la TVA du produit","Pour choisir la couleur du carton","Pour fixer le prix de vente"],ex:"Leçon 8.3.1.",ref:"Leçon 8.3.1"},
{k:"qcm",q:"Quel article de la Constitution fédérale confie la métrologie à la Confédération ?",a:["L’article 125","L’article 184","L’article 440","L’article 29"],ex:"Leçon 8.3.2.",ref:"Leçon 8.3.2"},
{k:"multi",q:"Quels instruments sont soumis à la vérification officielle ?",good:["Instruments de mesure pour les douanes","Pompes à essence pour la vente de carburant"],bad:["Ravitaillement interne en carburant","Instruments de production de mélanges industriels"],ex:"Leçon 8.3.2.",ref:"Leçon 8.3.2"},
{k:"case",s:"Le plomb de contrôle d’une balance de vente est cassé.",q:"Que faites-vous ?",a:["Je le signale à l’inspecteur et mets la balance hors service","Je recolle le plomb avec du ruban adhésif transparent","Je continue à peser comme d’habitude toute la journée","Je change simplement la balance de place dans le magasin"],ex:"Toute altération doit être signalée immédiatement.",ref:"Leçon 8.3.2"},
{k:"odd",q:"Lequel n’est pas une balance ou un dispositif de pesée du cours ?",a:["Le micromètre","Le pèse-palettes","Le pont basculant","La balance compteuse"],ex:"Le micromètre mesure des épaisseurs.",ref:"Leçon 8.2.6"},
{k:"fill",q:"Une tolérance d’erreur de 0,5 % est admise pour des volumes supérieurs à ___ litres.",a:["2","20","200","0,5"],ex:"Exemple de la leçon 8.3.2.",ref:"Leçon 8.3.2"}
]}
];

window.LEVELS=[[0,"Stagiaire"],[150,"Apprenti·e logisticien·ne"],[400,"Logisticien·ne en formation"],[800,"Logisticien·ne CFC"],[1300,"Référent·e contrôle des quantités"],[2000,"Expert·e métrologie"],[3000,"Formateur·rice logistique"]];

window.META={
  title:"M308 – Les contrôles de quantités",
  eyebrow:"Logisticien·ne CFC · Module M308",
  key:"atelier-m308-quantites-v1",
  intro:"Révisez les unités de mesure, le comptage par quantités partielles, les outils de mesure des longueurs et des volumes, les balances, la tare, le poids net et brut et la vérification des instruments.",
  goal:20,
  exam:{n:22,min:25},
  cover:{src:"img/cover.jpg",alt:"Bandeau du module M308"},
  imgs:{},
  icons:{
    count:'<path d="M4 6h16M4 12h16M4 18h10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M18 16v4M16 18h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    ruler:'<path d="M3 16L16 3l5 5L8 21z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M7 12l2 2M10 9l2 2M13 6l2 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    scale:'<path d="M12 4v16M7 20h10M5 8h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M5 8l-3 6a3 3 0 0 0 6 0zM19 8l-3 6a3 3 0 0 0 6 0z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>'
  },
  note:{text:"La leçon « Objectifs » de M308 reprend c1.7 et c2.2 (déjà travaillés dans M304, M306 et M307) et c2.10. Les leçons portant sur compter, mesurer et peser, le jeu les rattache à c2.10 en trois parties. Voir le cours sur",url:"https://logiprof.fontes-oliveira.ch",link:"logiprof"}
};
window.META.langs = ["fr","de","it"];
