/* M603 – L’arrimage des marchandises. Objectif unique C3.2 découpé en trois parties. a[0] = bonne réponse. */
const G32 = "C3.2 Je décris les techniques et les moyens auxiliaires utilisés pour sécuriser les marchandises sur les supports de charge. (C2)";
window.OBJ = [
/* ============ 1 · FILMAGE, RÉTRACTION, CERCLAGE ============ */
{code:"C3.2a", t:"Filmage, rétraction, cerclage", g:G32+" — partie : filmage, rétraction et cerclage (leçon 3.1)", col:"teal", ic:"wrap",
ch:[
{k:"multi",q:"Quelles sont les trois méthodes principales pour sécuriser un chargement de palettes ?",good:["Le filmage (stretching)","La rétraction","Le cerclage"],bad:["Le collage des cartons"],ex:"Leçon 3.1.",ref:"Leçon 3.1"},
{k:"qcm",q:"En quoi consiste le filmage ?",a:["Envelopper la charge avec un film étirable","Chauffer une housse pour qu’elle épouse la charge","Serrer la charge avec un feuillard métallique","Coller les cartons entre eux avec de la colle"],ex:"Leçon 3.1.1.",ref:"Leçon 3.1.1"},
{k:"qcm",q:"Pour quelles charges le filmage est-il surtout adapté ?",a:["Les charges homogènes","Les charges à angles vifs","Les charges très lourdes","Les charges stockées dehors"],ex:"Leçon 3.1.1.",ref:"Leçon 3.1.1"},
{k:"multi",q:"Quels points sont importants lors du filmage ?",good:["Le film doit être étiré pendant l’enveloppement","La palette doit être incluse dans le filmage","Compléter par un cerclage les charges à angles saillants"],bad:["Laisser le film détendu"],ex:"Leçon 3.1.1.",ref:"Leçon 3.1.1"},
{k:"case",s:"Un préparateur filme une palette sans inclure la palette en bois dans le film.",q:"Quel est le risque ?",a:["La charge peut glisser de la palette","Le film devient non recyclable","La palette devient trop lourde","Aucun, c’est la méthode conseillée"],ex:"La palette doit être incluse, sauf pour les unités de stock automatisées.",ref:"Leçon 3.1.1"},
{k:"case",s:"Une palette porte des profilés métalliques lourds aux arêtes vives.",q:"Que faut-il ajouter au filmage ?",a:["Un cerclage plastique","Une deuxième palette dessous","Une housse en papier kraft","Rien, le film suffit"],ex:"Les angles saillants peuvent percer le film.",ref:"Leçon 3.1.1"},
{k:"tf",q:"Pour les unités de stock automatisées, la palette n’est pas forcément incluse dans le filmage.",v:true,ex:"Exception de la leçon 3.1.1.",ref:"Leçon 3.1.1"},
{k:"qcm",q:"Comment fonctionne la rétraction ?",a:["Une housse chauffée épouse la forme de la charge","Un film est étiré tout autour de la palette en bois","Un feuillard est soudé par vibration autour des colis","Une boucle textile serre les cartons entre eux"],ex:"Leçon 3.1.2.",ref:"Leçon 3.1.2"},
{k:"multi",q:"Comment chauffe-t-on la housse thermorétractable selon le cours ?",good:["Au foehn","À la flamme nue"],bad:["Au micro-ondes","À l’eau chaude"],ex:"Leçon 3.1.2.",ref:"Leçon 3.1.2"},
{k:"sort",q:"Avantage ou inconvénient de la rétraction ?",c:["Avantage","Inconvénient"],it:[["Protection contre les intempéries",0],["Protection contre le vol",0],["Adaptée aux grandes quantités",0],["Non étirable",1],["Impact écologique du PVC",1]],ex:"Leçon 3.1.2.",ref:"Leçon 3.1.2"},
{k:"case",s:"Des appareils électroménagers doivent être stockés plusieurs semaines à l’extérieur, sous un auvent.",q:"Quelle méthode de sécurisation recommandez-vous ?",a:["La rétraction avec une housse","Un simple filmage sans palette","Un cerclage horizontal seul","Aucune protection"],ex:"Exemple de la leçon 3.1.2.",ref:"Leçon 3.1.2"},
{k:"case",s:"Une housse rétractée a été déchirée par une fourche pendant le chargement.",q:"Quelle conséquence le cours indique-t-il ?",a:["Elle ne protège plus efficacement","Elle se referme seule en refroidissant","Elle devient plus résistante","Aucune, la housse est étirable"],ex:"Non étirable : une fois abîmée, elle ne protège plus.",ref:"Leçon 3.1.2"},
{k:"match",q:"Associez chaque type de cerclage à son usage.",p:[["Vertical","Relier la charge à la palette"],["Horizontal","Certains produits comme les fûts"],["Combiné","Piles très instables"]],ex:"Leçon 3.1.3.",ref:"Leçon 3.1.3"},
{k:"qcm",q:"Quel type de cerclage est le plus courant ?",a:["Le cerclage vertical","Le cerclage horizontal","Le cerclage combiné","Le cerclage diagonal"],ex:"Leçon 3.1.3.",ref:"Leçon 3.1.3"},
{k:"case",s:"Des bidons lourds risquent de basculer pendant le transport en camion.",q:"Quel cerclage le cours propose-t-il ?",a:["Un cerclage vertical","Un cerclage horizontal seul","Aucun cerclage, un film suffit","Une simple ficelle"],ex:"Exemple de la leçon 3.1.3.",ref:"Leçon 3.1.3"},
{k:"case",s:"Une pile de cartons de tailles différentes est très instable.",q:"Quelle solution est la plus sûre ?",a:["Un cerclage combiné, avec filmage si nécessaire","Un cerclage horizontal unique placé tout en bas","Une housse rétractée qui n’a pas été chauffée","Un filmage rapide sans étirer le film du tout"],ex:"Combiné pour les piles très instables ; conseil : combiner cerclage et filmage.",ref:"Leçon 3.1.3"},
{k:"tf",q:"Le cours conseille de combiner parfois cerclage et filmage.",v:true,ex:"Leçon 3.1.3.",ref:"Leçon 3.1.3"},
{k:"tf",q:"Le cerclage est surtout utilisé pour de très petites unités légères.",v:false,ex:"Surtout pour des unités volumineuses.",ref:"Leçon 3.1.3"},
{k:"sort",q:"Filmage, rétraction ou cerclage ?",c:["Filmage","Rétraction","Cerclage"],it:[["Rapide, économique, simple",0],["Charges homogènes",0],["Charges exposées à l’extérieur",1],["Housse en PVC épais",1],["Charges volumineuses ou instables",2],["Feuillard plastique ou métallique",2]],ex:"Comparatif de la leçon 3.1.",ref:"Leçon 3.1"},
{k:"order",q:"Remettez dans l’ordre un filmage correct.",a:["Fixer le film à la base de la palette","Envelopper en étirant le film","Monter jusqu’au sommet de la charge","Couper et fixer la fin du film"],ex:"Film étiré, palette incluse.",ref:"Leçon 3.1.1"},
{k:"odd",q:"Lequel n’est pas un inconvénient cité pour une des trois méthodes ?",a:["Rapide et économique","Peu résistant aux arêtes","Non étirable","Moins efficace seul pour petites unités"],ex:"« Rapide et économique » est un avantage du filmage.",ref:"Leçon 3.1"},
{k:"fill",q:"La housse de rétraction est souvent en ___ épais.",a:["PVC","acier","carton","coton"],ex:"Leçon 3.1.2.",ref:"Leçon 3.1.2"},
{k:"case",s:"Une palette de cartons identiques de lessive doit partir dans un camion bâché.",q:"Quelle méthode simple suffit généralement ?",a:["Le filmage avec film étirable","Le cerclage acier combiné","La rétraction à la flamme","La soudure par ultrasons"],ex:"Charges homogènes.",ref:"Leçon 3.1.1"},
{k:"qcm",q:"Avec quel matériel réalise-t-on le filmage ?",a:["Un dérouleur manuel ou une filmeuse automatique","Un foehn industriel ou une flamme nue au gaz","Une cercleuse manuelle et des chapes métalliques","Un poinçon de cerclage et des boucles textiles"],ex:"Leçon 3.1.1.",ref:"Leçon 3.1.1"},
{k:"case",s:"Votre chef veut protéger des palettes de téléviseurs contre le vol pendant un stockage temporaire sur un parking.",q:"Quelle méthode offre la meilleure protection ?",a:["La rétraction","Le filmage transparent seul","Le cerclage horizontal","Aucune méthode n’aide"],ex:"Bonne protection contre les intempéries et le vol.",ref:"Leçon 3.1.2"}
]},
/* ============ 2 · CHOISIR LE FEUILLARD ============ */
{code:"C3.2b", t:"Choisir le bon feuillard", g:G32+" — partie : types de feuillards, résistance à la traction, allongement, capacité de rétraction (leçon 3.2)", col:"indigo", ic:"strap",
ch:[
{k:"multi",q:"Quels critères guident le choix d’un feuillard ?",good:["Résistance à la traction","Allongement","Capacité de rétraction","Coût et impact environnemental"],bad:["Couleur du feuillard"],ex:"Leçon 3.2.",ref:"Leçon 3.2"},
{k:"match",q:"Associez chaque feuillard à sa résistance à la traction.",p:[["Plastique (PP ou PET)","330 – 400 N/mm²"],["Textile / polyester tissé","470 – 570 N/mm²"],["Acier","700 – 1100 N/mm²"]],ex:"Comparatif de la leçon 3.2.",ref:"Leçon 3.2"},
{k:"match",q:"Associez chaque feuillard à son allongement.",p:[["Plastique (PP)","16 – 22 %"],["Textile","11 – 16 %"],["Acier","6 – 12 %"]],ex:"Leçon 3.2.2.",ref:"Leçon 3.2.2"},
{k:"sort",q:"Capacité de rétraction des feuillards.",c:["Très grande ou bonne","Faible"],it:[["Plastique PET",0],["Plastique PP",0],["Textile",0],["Acier",1]],ex:"Leçon 3.2.3.",ref:"Leçon 3.2.3"},
{k:"case",s:"Un fournisseur propose trois feuillards pour des charges lourdes de barres métalliques.",q:"Lequel est le plus résistant à la traction ?",a:["L’acier","Le textile","Le plastique PP","Le plastique PET"],ex:"700 – 1100 N/mm².",ref:"Leçon 3.2"},
{k:"case",s:"Vous cherchez un feuillard capable de s’étirer beaucoup avant de se rompre.",q:"Lequel est le plus extensible ?",a:["Le plastique (PP)","Le textile","L’acier","Ils sont tous identiques"],ex:"16 – 22 %.",ref:"Leçon 3.2.2"},
{k:"sort",q:"Recyclable ou non selon le cours ?",c:["Recyclable","Non recyclable"],it:[["Feuillard PET",0],["Feuillard acier",0],["Feuillard textile",1]],ex:"Comparatif de la leçon 3.2.",ref:"Leçon 3.2"},
{k:"qcm",q:"Que mesure la résistance à la traction (N/mm²) ?",a:["La force nécessaire pour rompre 1 mm² de feuillard","La longueur du feuillard juste avant sa rupture","La capacité à retrouver sa tension après étirement","Le poids d’un mètre de feuillard sur la bobine"],ex:"Leçon 3.2.1.",ref:"Leçon 3.2.1"},
{k:"fill",q:"Force de rupture = largeur × épaisseur × ___.",a:["résistance à la traction","allongement","capacité de rappel","prix au mètre"],ex:"Formule de la leçon 3.2.1.",ref:"Leçon 3.2.1"},
{k:"num",q:"Un feuillard mesure 25 mm de large et 0,5 mm d’épaisseur. Quelle est sa section, en dixièmes de mm² (12,5 mm² = 125) ?",n:125,tol:0,u:"dixièmes de mm²",ex:"25 × 0,5 = 12,5 mm².",ref:"Leçon 3.2.1"},
{k:"num",q:"Section 12,5 mm², résistance 400 N/mm². Quelle est la force de rupture ?",n:5000,tol:0,u:"N",ex:"12,5 × 400 = 5000 N.",ref:"Leçon 3.2.1"},
{k:"num",q:"Feuillard acier : 16 mm × 0,5 mm, résistance 800 N/mm². Force de rupture ?",n:6400,tol:0,u:"N",ex:"16 × 0,5 = 8 mm² ; 8 × 800 = 6400 N.",ref:"Leçon 3.2.1"},
{k:"num",q:"Feuillard textile : 20 mm × 1 mm, résistance 500 N/mm². Force de rupture ?",n:10000,tol:0,u:"N",ex:"20 × 1 × 500 = 10 000 N.",ref:"Leçon 3.2.1"},
{k:"case",s:"Deux feuillards ont la même résistance à la traction, mais l’un est deux fois plus large.",q:"Lequel a la plus grande force de rupture ?",a:["Le plus large, car sa section est plus grande","Le plus étroit, car il est bien plus souple","Ils ont exactement la même force de rupture","On ne peut rien calculer avec ces données"],ex:"Force = section × résistance.",ref:"Leçon 3.2.1"},
{k:"case",s:"Une palette de 600 kg (environ 6000 N) doit être cerclée avec un seul feuillard PP de 5000 N.",q:"Que concluez-vous pour la sécurité ?",a:["Un seul feuillard est insuffisant : en prévoir plusieurs ou un plus résistant","Le feuillard suffit largement, car la force de rupture n’est jamais atteinte","La force de rupture n’a aucun lien avec la sécurité de la palette chargée","Il faut supprimer complètement le cerclage et utiliser seulement du film"],ex:"Lien entre force de rupture et sécurité.",ref:"Leçon 3.2.1"},
{k:"qcm",q:"Qu’est-ce que l’allongement d’un feuillard ?",a:["Sa capacité à s’étirer avant de se rompre","Sa capacité à retrouver sa tension initiale","Sa résistance mesurée sur 1 mm² de section","Sa longueur totale enroulée sur la bobine"],ex:"Leçon 3.2.2.",ref:"Leçon 3.2.2"},
{k:"case",s:"Des palettes subissent de fortes secousses dans un camion sur une route de montagne.",q:"Quel feuillard absorbe le mieux les mouvements ?",a:["Le feuillard plastique","Le feuillard acier","Une ficelle de chanvre","Aucun feuillard"],ex:"Exemple de la leçon 3.2.2.",ref:"Leçon 3.2.2"},
{k:"qcm",q:"Comment appelle-t-on aussi la capacité de rétraction ?",a:["La capacité de rappel","La force de rupture","L’allongement","La résistance à la traction"],ex:"Leçon 3.2.3.",ref:"Leçon 3.2.3"},
{k:"case",s:"Un lot de boîtes encore chaudes est cerclé ; en refroidissant, les boîtes se rétractent.",q:"Quel feuillard compense ce relâchement ?",a:["Un feuillard plastique","Un feuillard acier","Une boucle sans feuillard","Un film rétractable"],ex:"Exemple de la leçon 3.2.3.",ref:"Leçon 3.2.3"},
{k:"case",s:"Du bois fraîchement scié va perdre de l’humidité pendant le stockage.",q:"Quelle propriété du feuillard est cruciale ?",a:["La capacité de rétraction (rappel)","La couleur exacte du feuillard","Le poids du feuillard au mètre","La largeur seule du feuillard"],ex:"Charge qui se rétracte en perdant de l’humidité.",ref:"Leçon 3.2.3"},
{k:"tf",q:"Le feuillard acier n’a quasiment aucune capacité de rappel.",v:true,ex:"Leçon 3.2.3.",ref:"Leçon 3.2.3"},
{k:"tf",q:"Le feuillard textile est le plus économique des trois.",v:false,ex:"Prix élevé ; le plastique est économique.",ref:"Leçon 3.2"},
{k:"order",q:"Classez ces feuillards du moins cher au plus cher.",a:["Plastique","Textile","Acier"],ex:"Économique, élevé, très élevé.",ref:"Leçon 3.2"},
{k:"odd",q:"Lequel n’est pas un critère technique de choix du feuillard ?",a:["La couleur","La résistance à la traction","L’allongement","La capacité de rétraction"],ex:"Leçon 3.2.",ref:"Leçon 3.2"},
{k:"case",s:"Une charge très lourde de tôles ne bouge pas en volume et ne subit pas de variation de température.",q:"Quel feuillard est le plus adapté ?",a:["L’acier, très résistant","Le PP, très extensible","Le textile non recyclable","Une simple ficelle"],ex:"Résistance élevée, charge stable.",ref:"Leçon 3.2"}
]},
/* ============ 3 · TYPES DE FERMETURE ============ */
{code:"C3.2c", t:"Types de fermeture", g:G32+" — partie : fermetures de feuillards (boucle, chape, sans chape) (leçon 3.3)", col:"violet", ic:"buckle",
ch:[
{k:"qcm",q:"Pourquoi le système de fermeture est-il important ?",a:["Il garantit le maintien de la tension du feuillard","Il rend le feuillard beaucoup plus léger à porter","Il remplace complètement le choix du feuillard","Il sert seulement à décorer la palette chargée"],ex:"Leçon 3.3.",ref:"Leçon 3.3"},
{k:"multi",q:"Quelles méthodes de fermeture le cours cite-t-il ?",good:["Avec boucle","Avec chape","Sans chape (soudure, encliquetage)","Avec ficelle"],bad:["Avec agrafes de bureau"],ex:"Leçon 3.3.",ref:"Leçon 3.3"},
{k:"multi",q:"Quels sont les atouts de la fermeture avec boucle ?",good:["Simple","Économique","Réutilisable","Facile à retendre ou rouvrir"],bad:["Soudée définitivement"],ex:"Leçon 3.3.1.",ref:"Leçon 3.3.1"},
{k:"case",s:"Dans un entrepôt, des lots de planches sont attachés pour un transport interne et seront rouverts le jour même.",q:"Quelle fermeture convient le mieux ?",a:["La boucle","La chape poinçonnée","La soudure par ultrasons","L’encliquetage"],ex:"Fixation temporaire, transport interne, industrie du bois.",ref:"Leçon 3.3.1"},
{k:"multi",q:"Avec quels feuillards utilise-t-on souvent la boucle ?",good:["Textiles","Polyester tissé","Plastique PP"],bad:["Acier"],ex:"Leçon 3.3.1.",ref:"Leçon 3.3.1"},
{k:"qcm",q:"Qu’est-ce qu’une chape ?",a:["Une pièce qui serre les extrémités du feuillard après tension","Une boucle textile réutilisable pour fermer le feuillard","Une soudure réalisée par vibration entre les deux extrémités","Un film plastique qui recouvre toute la palette chargée"],ex:"Leçon 3.3.2.",ref:"Leçon 3.3.2"},
{k:"order",q:"Remettez dans l’ordre une fermeture avec chape.",a:["Tendre le feuillard avec un appareil","Placer la chape sur les extrémités","Écraser ou poinçonner la chape"],ex:"Leçon 3.3.2.",ref:"Leçon 3.3.2"},
{k:"tf",q:"La chape convient aussi bien aux feuillards plastiques qu’aux feuillards métalliques.",v:true,ex:"Leçon 3.3.2.",ref:"Leçon 3.3.2"},
{k:"case",s:"Un cerclage de feuillard acier doit tenir pendant un long transport international.",q:"Quelle fermeture choisissez-vous parmi celles du cours ?",a:["Une chape écrasée ou poinçonnée","Une boucle facile à rouvrir","Un simple nœud de ficelle","Un encliquetage sans outil"],ex:"La chape convient au métal.",ref:"Leçon 3.3.2"},
{k:"case",s:"Un responsable veut automatiser complètement la fermeture des feuillards sur sa ligne d’emballage.",q:"Quelle fermeture est la plus rapide et la plus automatisable ?",a:["La fermeture sans chape","La fermeture avec boucle","La fermeture avec chape","La fermeture avec ficelle"],ex:"Leçon 3.3.3.",ref:"Leçon 3.3.3"},
{k:"match",q:"Associez chaque méthode sans chape à sa description.",p:[["Soudage par vibration","Chaleur produite par frottement"],["Soudage par ultrasons","Machines automatiques haute performance"],["Encliquetage","Extrémités clipsées sans chaleur"]],ex:"Leçon 3.3.3.",ref:"Leçon 3.3.3"},
{k:"num",q:"Selon le cours, quelle est la fréquence maximale du soudage par vibration, en Hz ?",n:300,tol:0,u:"Hz",ex:"200 à 300 Hz.",ref:"Leçon 3.3.3"},
{k:"case",s:"Une ligne de production cercle 600 palettes par jour et veut éviter d’acheter des chapes.",q:"Quelle fermeture recommandez-vous ?",a:["Sans chape, par soudure automatique","Avec boucle, fermée à la main","Avec chape, palette par palette","Avec ficelle nouée par l’opérateur"],ex:"Gain de temps en série, pas de pièces supplémentaires.",ref:"Leçon 3.3.3"},
{k:"multi",q:"Quels sont les avantages de la fermeture sans chape ?",good:["Pas de pièces supplémentaires","Gain de temps","Efficacité en production en série"],bad:["Facile à rouvrir et réutiliser"],ex:"Leçon 3.3.3.",ref:"Leçon 3.3.3"},
{k:"tf",q:"Le soudage par ultrasons est la méthode la plus courante dans les petits entrepôts.",v:false,ex:"Il est rare, réservé aux machines automatiques haute performance.",ref:"Leçon 3.3.3"},
{k:"sort",q:"Avec ou sans chape ?",c:["Avec chape","Sans chape"],it:[["Pièce écrasée sur les extrémités",0],["Pièce poinçonnée",0],["Soudage par vibration",1],["Encliquetage",1],["Soudage par ultrasons",1]],ex:"Leçons 3.3.2 et 3.3.3.",ref:"Leçons 3.3.2 et 3.3.3"},
{k:"case",s:"Un appareil réalise la tension, la pose et la soudure de la chape en une seule opération.",q:"Quel avantage apporte-t-il ?",a:["Un gain de temps au cerclage","Un feuillard plus extensible","Une boucle réutilisable","Une protection contre la pluie"],ex:"Équipements associés de la leçon 3.3.2.",ref:"Leçon 3.3.2"},
{k:"fill",q:"Dans la fermeture sans chape, les extrémités du feuillard sont imbriquées et ___ entre elles.",a:["soudées","nouées","collées","agrafées"],ex:"Leçon 3.3.3.",ref:"Leçon 3.3.3"},
{k:"odd",q:"Lequel n’est pas une méthode de fermeture sans chape ?",a:["La boucle","Le soudage par vibration","Le soudage par ultrasons","L’encliquetage"],ex:"La boucle est une fermeture à part.",ref:"Leçon 3.3"},
{k:"case",s:"Un collègue a cerclé une palette mais la tension du feuillard s’est relâchée au premier virage.",q:"Quelle est la cause la plus probable ?",a:["Une fermeture mal adaptée ou mal serrée","Un feuillard beaucoup trop résistant","Une palette en bois trop légère","Un film étirable trop fortement étiré"],ex:"La fermeture garantit le maintien de la tension.",ref:"Leçon 3.3"},
{k:"case",s:"Dans l’industrie du meuble, des panneaux sont regroupés en paquets qui seront souvent rouverts et refermés.",q:"Quelle fermeture est la plus pratique ?",a:["La boucle","La soudure par ultrasons","La chape poinçonnée","L’encliquetage définitif"],ex:"Domaines d’usage de la boucle.",ref:"Leçon 3.3.1"},
{k:"case",s:"Une entreprise veut fermer des feuillards sans chaleur et sans chape.",q:"Comment l’encliquetage ferme-t-il le feuillard ?",a:["Les extrémités sont clipsées ensemble sans chaleur","Les extrémités sont chauffées par frottement rapide","Une chape métallique est poinçonnée sur le feuillard","Une boucle textile est nouée autour des extrémités"],ex:"Leçon 3.3.3.",ref:"Leçon 3.3.3"},
{k:"order",q:"Classez ces fermetures de la plus manuelle à la plus automatisée.",a:["Boucle","Chape","Soudage par vibration","Soudage par ultrasons"],ex:"Leçon 3.3.",ref:"Leçon 3.3"},
{k:"match",q:"Associez chaque fermeture à son domaine d’usage.",p:[["Boucle","Transport interne et industrie du bois"],["Chape","Feuillards plastiques et métalliques"],["Sans chape","Lignes à haut rendement"]],ex:"Leçon 3.3.",ref:"Leçon 3.3"},
{k:"tf",q:"La boucle est pratique pour les fixations temporaires.",v:true,ex:"Leçon 3.3.1.",ref:"Leçon 3.3.1"}
]}
];

window.LEVELS=[[0,"Stagiaire"],[150,"Apprenti·e logisticien·ne"],[400,"Logisticien·ne en formation"],[800,"Logisticien·ne CFC"],[1300,"Référent·e arrimage"],[2000,"Expert·e cerclage"],[3000,"Formateur·rice logistique"]];

window.META={
  title:"M603 – L’arrimage des marchandises",
  eyebrow:"Logisticien·ne CFC · Module M603",
  key:"atelier-m603-arrimage-v1",
  intro:"Révisez le filmage, la rétraction, le cerclage, le choix du feuillard (résistance, allongement, rappel) et les types de fermeture.",
  goal:20,
  exam:{n:24,min:30},
  cover:{src:"img/cover.jpg",alt:"Bandeau du module M603"},
  imgs:{},
  icons:{
    wrap:'<rect x="6" y="4" width="12" height="13" rx="1" fill="none" stroke="currentColor" stroke-width="2"/><path d="M4 20h16M6 8l12 3M6 13l12 3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    strap:'<rect x="4" y="5" width="16" height="14" rx="1" fill="none" stroke="currentColor" stroke-width="2"/><path d="M9 5v14M15 5v14" fill="none" stroke="currentColor" stroke-width="3"/>',
    buckle:'<rect x="7" y="7" width="10" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 12h4M17 12h4M12 7v10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
  },
  note:{text:"La fermeture avec ficelle est annoncée dans la leçon 3.3 mais n’y est pas décrite. Cours complet sur",url:"https://logiprof.fontes-oliveira.ch",link:"logiprof"}
};
window.META.langs = ["fr","de","it"];
