# Guide utilisateur - Gestion des classes d'apprentis

## Installation

1. Installer le plugin WordPress depuis le fichier ZIP.
2. Activer le plugin dans l'administration WordPress.
3. Accéder au menu `LogiClass` dans l'administration WordPress.
4. Optionnel : créer une page WordPress protégée et y placer le shortcode `[gestion_apprentis]` pour un accès front-end.
5. Vérifier que l'utilisateur administrateur dispose du droit `gca_manage`.
6. Configurer un plugin SMTP fiable pour l'envoi des e-mails.

## Accès au plugin

Deux accès sont disponibles :

- Depuis l'administration WordPress : menu `LogiClass`, puis `À traiter`, `Classes`, `Apprentis`, `Formateurs`, `Actions exécutées`, `Communication`, `Messages types`, `Imports` ou `Paramètres`.
- Depuis une page du site : shortcode `[gestion_apprentis]`.

Le menu d'administration et le shortcode utilisent la même interface et les mêmes données.

Dans les tableaux et historiques, le nom d'un apprenti ouvre sa fiche dans une nouvelle page. Le nom ou le numéro d'une classe ouvre directement la liste des apprentis de cette classe.

## Format des dates

Toutes les dates visibles et saisies dans l'interface utilisent le format :

```text
JJ.MM.AAAA
```

Exemple : `21.08.2026`.

La date de convocation insérée dans l'e-mail et dans le PDF utilise aussi ce format.

## Import Lagap-EP

1. Se connecter à Lagap-EP.
2. Ouvrir `Recherche d'élèves`.
3. Sélectionner les classes souhaitées.
4. Cliquer sur `Tout charger` si Lagap ne charge pas toutes les lignes.
5. Ouvrir le menu de grille, puis `Exporter` et `Export CSV`.
6. Dans le plugin, ouvrir l'onglet `Imports`.
7. Utiliser le bloc `Import Lagap-EP`.
8. Vérifier le mapping proposé.
9. Cocher `Sauvegarder ce mapping pour mes prochains imports de ce type` si ce réglage doit être réutilisé.
10. Valider l'import.

L'import Lagap-EP remplace les compteurs existants par les valeurs officielles de l'export, sauf si un compteur a été corrigé ou protégé manuellement dans une fiche. Les autres champs corrigés ou validés manuellement dans `Apprentis` ou `Formateurs` restent aussi prioritaires et ne sont pas écrasés par les imports suivants.

Chaque fichier préparé puis validé est rattaché au compte prof connecté. L'historique affiche le prof, le fichier, le résultat de l'import et une empreinte SHA-256 du fichier source.

## Import des photos

1. Importer d'abord les élèves depuis Lagap ou un CSV afin que les fiches existent.
2. Ouvrir l'onglet `Imports`.
3. Dans le bloc `Photos de classe`, sélectionner le PDF de liste de classe avec photos.
4. Cliquer sur `Analyser les photos`.
5. Vérifier chaque vignette, le nom détecté dans le PDF et l'élève cible proposé.
6. Si nécessaire, attribuer la photo à un autre élève dans la liste déroulante groupée par classe.
7. Cliquer sur `Enregistrer les attributions`.

Le plugin lit les noms alternatifs des photos dans le PDF, extrait les images, puis prépare une page de contrôle. Une fiche est proposée automatiquement uniquement si le `Prénom Nom` du PDF correspond exactement à une seule fiche apprenti. La proposition affiche aussi la classe de la fiche trouvée. En cas de doute, d'erreur ou d'homonyme entre classes, il faut choisir manuellement la bonne fiche avant validation. Si le PDF contient un emplacement `NO IMAGE`, aucune photo n'est importée pour cette ligne et les photos suivantes restent alignées avec leurs propres élèves.

## Import initial generique

1. Ouvrir l'onglet `Imports`.
2. Choisir `Liste des apprentis`.
3. Importer le CSV ou XLSX.
4. Vérifier le mapping proposé.
5. Cocher `Sauvegarder ce mapping pour mes prochains imports de ce type` si ce réglage doit être réutilisé.
6. Choisir si les doublons doivent être mis à jour ou ignorés.
7. Valider l'import.

L'export Lagap fourni contient déjà les colonnes suivantes : nom, prénom, classe, retards annuels, taux d'absence annuel, nombre de mises à la porte annuel, téléphone du responsable, `Formateur responsable`, `Entreprise formatrice`, `Entreprise formatrice Adresse`, `Entreprise formatrice NPA`, `Entreprise formatrice Ville` et `Entreprise formatrice Email`. Si l'export contient aussi `E-mail principal de l’élève`, il est utilisé comme destinataire principal des convocations. `Formateur responsable` alimente le nom du responsable affiché dans le plugin. `Entreprise formatrice Email` alimente l'e-mail du responsable de formation utilisé pour les communications.

Un mapping sauvegardé est lié au profil prof connecté. Lors du prochain import du même type, il est repris automatiquement uniquement si les colonnes du fichier correspondent aux colonnes sauvegardées.

## Import de notes

1. Ouvrir l'onglet `Imports`.
2. Utiliser le bloc `Import de notes`.
3. Importer l'export CSV ou XLSX de l'interface dédiée aux notes.
4. Vérifier le mapping des colonnes : `Classe`, `Nom`, `Prénom`, `Évaluation` et `Note` sont obligatoires.
5. Sauvegarder le mapping si la structure du fichier sera réutilisée.
6. Valider l'import.

Les notes sont rattachées uniquement aux fiches apprentis existantes. Pour éviter toute confusion, la classe doit déjà exister dans le plugin et le nom/prénom de l'apprenti doivent correspondre à une fiche de cette classe. Ces trois colonnes servent seulement à retrouver l'apprenti ; les seules données de notes récupérées sont l'évaluation et la note obtenue. Les lignes non reconnues sont signalées dans le détail de l'import et ne sont pas attribuées automatiquement à un autre élève.

Lors d'une convocation, la copie cachée contient toujours `carlos.fontes@eduvaud.ch`, même si le champ est vide avant l'envoi. Cela permet de recevoir le PDF joint pour impression.

La date de convocation est obligatoire uniquement pour les e-mails de convocation. Cliquer dans le champ `Date de convocation` ouvre un calendrier ; la date sélectionnée est ensuite utilisée dans l'e-mail et dans le PDF au format `jour.mois.année`.

Pour une convocation de retards, cocher `L'apprenti ne s'est pas présenté à une retenue précédente` enregistre cette information dans l'historique. Elle est ensuite reprise automatiquement dans les prochaines convocations via la variable `{detention_no_show_note}`.

Dans les paramètres, `Nom d'expéditeur` et `E-mail d'expédition` définissent l'adresse visible d'envoi. Le plugin force ces valeurs au moment de l'envoi ; la configuration SMTP doit donc autoriser cette adresse pour éviter un rejet du message. Le champ `E-mail de réponse` est optionnel et permet de recevoir les réponses sur une autre adresse. L'e-mail du responsable de formation est mis en copie dans les convocations. Si l'apprenti est mineur, les représentants légaux renseignés sont aussi ajoutés en copie. En rupture ou candidat libre, aucun e-mail formateur n'est repris.

## Suivi périodique

L'import de suivi peut remplacer les compteurs existants ou additionner les valeurs importées. Par défaut, utiliser `Remplacer les compteurs existants`, car l'export de l'école est présumé cumulatif.

Après chaque import, le tableau `À traiter` est recalculé automatiquement.

Le tableau `À traiter` affiche aussi les dernières actions déjà entreprises pour chaque apprenti concerné : convocations envoyées, e-mails au Doyen, mises à la porte marquées comme traitées, avertissements et exclusions.

Si plusieurs événements concernent le même apprenti, ils sont affichés séparément afin de pouvoir les traiter individuellement. Par exemple, plusieurs mises à la porte cumulées créent une action distincte pour chaque mise à la porte.

Les alertes d'absence utilisent des seuils séparés. Le plugin crée une action à traiter si l'apprenti dépasse le seuil d'absences non justifiées, fixé par défaut à 21 jours/périodes, ou si son taux d'absences injustifiées atteint le seuil critique, fixé par défaut à 30 %. Le délai de démarrage après le début des cours et le nombre minimal de périodes d'absence restent appliqués afin d'éviter qu'une absence en tout début d'année déclenche immédiatement une alerte à 100 %.

Pour ces actions d'absence, vous pouvez soit justifier l'absence sans envoyer de message, soit préparer un e-mail au responsable de formation avec l'apprenti en copie, soit préparer un e-mail au Doyen. Un justificatif d’absence peut aussi être ajouté depuis l’onglet `À traiter` pour un apprenti qui n’apparaît pas encore dans la liste des situations à traiter, avec le jour concerné et le nombre de périodes excusées.

Quand une alerte d'absence est justifiée, supprimée, ou qu'un message est envoyé, seule la valeur actuelle est considérée comme traitée. Si le nombre de périodes ou le taux d'absence augmente lors d'un prochain import, une nouvelle action réapparaît dans `À traiter`.

## Classes

Depuis le menu `Classes`, ouvrir une classe permet de modifier son nom, son année scolaire et son statut. Les apprentis déjà liés restent dans la classe après modification.

Dans la liste d'une classe, le bouton `Modifier` ouvre la fiche de l'apprenti. Cette fiche permet de corriger ses données et, si nécessaire, de le déplacer vers une autre classe.

Le nom de l'apprenti est aussi cliquable et ouvre la fiche dans une nouvelle page afin de garder la liste de classe disponible.

## Apprentis

L'onglet `Apprentis` affiche toutes les fiches actives dans une seule table. Il permet de filtrer par classe, par genre, par statut de vérification ou par recherche libre.

Depuis cette vue, il est possible de modifier rapidement le genre, l'e-mail principal de l'élève, le téléphone de l'élève, le formateur responsable rattaché et le statut `Vérifié`. La colonne `Responsable` propose les formateurs déjà enregistrés dans le plugin. Le bouton `Mettre à jour les fiches élèves` applique directement ces corrections dans les fiches des élèves. Le plugin ne sauvegarde que les fiches réellement modifiées, et chaque modification rapide est ajoutée à l'historique de la fiche concernée. Quand une entreprise est renseignée et que l'apprenti a un employeur actif, elle apparaît à côté du nom de l'apprenti pour éviter toute confusion.

Les cases de sélection permettent aussi d'appliquer une action groupée aux apprentis affichés : marquer plusieurs fiches comme `Vérifiées`, les remettre `À vérifier`, ou définir leur genre en une seule opération.

Pour les informations plus complètes, le nom de l'apprenti ou le bouton `Ouvrir` permettent d'accéder à la fiche détaillée dans une nouvelle page.

Le bouton `Exporter les apprentis` télécharge un CSV complet de toutes les fiches apprentis, avec les coordonnées, les compteurs, la rupture de contrat éventuelle, les notes récupérées, les décisions disciplinaires, les exclusions, les actions, les communications et les champs protégés contre les imports.

Une fiche relue peut être cochée comme `Données vérifiées` dans la fiche détaillée ou directement depuis la vue `Apprentis`. Le badge affiche ensuite `Vérifié`; les fiches restantes affichent `À vérifier`. Le filtre de vérification permet d'afficher uniquement les fiches déjà contrôlées ou celles qui restent à contrôler.

Lorsqu'une fiche est marquée comme vérifiée, ses champs modifiables sont protégés contre les imports suivants. Une modification rapide protège aussi les champs corrigés, même si toute la fiche n'est pas encore marquée comme vérifiée.

Dans une fiche apprenti, le bloc `Actions déjà entreprises` reprend l'historique détaillé : type d'action, date, objet d'e-mail, destinataires, copie, copie cachée et lien de téléchargement du PDF lorsque le message a généré une convocation.

Dans la fiche apprenti, le bloc `Contrat de formation` permet d'indiquer une rupture de contrat avec sa date. À partir de cette date, le plugin affiche la fin de la période de suivi école de 3 mois et la date d'alerte fixée à 3 mois + 1 semaine. Quand cette échéance est atteinte, une action `Rupture de contrat` apparaît dans `À traiter` afin de vérifier si une nouvelle entreprise formatrice a été trouvée ou si l'apprenti doit arrêter de se présenter aux cours.

Le même bloc permet aussi d'indiquer qu'un apprenti est `Candidat libre`. Dans ce cas, comme pour une rupture, les données du formateur et de l'entreprise ne sont pas utilisées pour les communications, mais les situations de l'apprenti restent visibles dans `À traiter`.

Pendant une rupture de contrat, aucune adresse formateur de la fiche élève n'est utilisée pour les envois. La liste `À traiter` et les formulaires affichent une alerte de rupture, puis retirent automatiquement les e-mails formateurs des destinataires, même s'ils ont été saisis manuellement.

Le bloc `Notes récupérées` affiche uniquement les notes obtenues dans les différentes évaluations : évaluation et note. Ces notes sont aussi incluses dans l'export individuel de la fiche.

## Formateurs

L'onglet `Formateurs` centralise les fiches des formateurs responsables ajoutées ou confirmées manuellement. La liste utilise toute la largeur disponible ; le formulaire d'ajout s'ouvre uniquement avec le bouton `Ajouter un formateur responsable`.

La liste affiche le nom du formateur responsable avec l'entreprise formatrice, l'adresse de l'entreprise, l'e-mail du responsable, le badge de vérification et les apprentis rattachés. Cela permet de contrôler rapidement à quelle adresse partiront les communications et quelles fiches formateurs restent à relire.

Une fiche formateur permet de modifier le nom du responsable, l'entreprise, l'adresse complète de l'entreprise, l'e-mail du responsable, le téléphone, la formule d'appel et le statut `Données vérifiées`. Lors de l'enregistrement, ces données sont recopiées automatiquement dans toutes les fiches des apprentis rattachés à ce responsable.

Le bouton `Exporter les formateurs` télécharge un CSV complet des fiches formateurs, avec l'entreprise, l'adresse, l'e-mail d'envoi, le téléphone, la formule d'appel, le statut de vérification et la liste des apprentis rattachés.

Les champs corrigés ou vérifiés dans une fiche formateur restent prioritaires sur les données importées.

Dans la fiche formateur, le bloc `Apprentis rattachés` affiche les élèves liés. Il est possible de retirer un élève du responsable ou d'attribuer d'autres apprentis actifs depuis une liste de sélection. Ces changements sont aussi ajoutés à l'historique des fiches élèves concernées.

Le menu `Actions exécutées` affiche les mêmes informations sous forme globale, regroupées par classe. Il permet de relire rapidement les convocations envoyées, e-mails au Doyen, retenues manquées, avertissements, exclusions et actions marquées comme traitées. Les actions peuvent être modifiées ou supprimées depuis ce menu.

Dans la fiche d'un apprenti, le bloc `Suivi disciplinaire` permet de signaler manuellement un avertissement reçu avec sa date et une convocation à un conseil de savoir-être avec sa date. Le bloc `Exclusions` permet d'ajouter plusieurs exclusions pour le même apprenti, chacune avec une date de début, une date de fin, une durée calculée, une note, puis de modifier ou supprimer chaque ligne. Chaque modification est ajoutée à l'historique de la fiche.

## Communication

Depuis l'onglet `Communication`, sélectionner une classe pour afficher tous ses apprentis.

- `PDF` génère et télécharge le rapport complet d'un élève.
- `E-mail` prépare l'envoi du rapport au formateur.
- `Télécharger les rapports de la classe` génère un fichier ZIP contenant un PDF individuel par élève.

Le rapport reprend la photo, l'identité, le genre, les coordonnées, les compteurs, les notes récupérées, les décisions disciplinaires renseignées, les exclusions avec leurs périodes, les situations en attente et les actions déjà entreprises. Le genre est importé si une colonne existe ou estimé d'après le prénom, puis il peut être corrigé manuellement dans la fiche élève.

## Journal et sauvegarde

Le journal interne conserve les dernières actions réalisées dans le plugin : imports, modifications de fiches, envois d'e-mails, téléchargements PDF, archivages et exports.

En arrière-plan, le plugin conserve aussi des snapshots techniques dans ses propres tables `gca_*`. Ces snapshots servent de sauvegarde d'audit et restent séparés des contenus WordPress du site.

Les exports complets des listes `Apprentis` et `Formateurs` utilisent un CSV UTF-8 séparé par point-virgule afin d'être lisibles dans Excel avec les accents.

## Règles retards

- 3 retards : première convocation avec PDF.
- 6 retards : deuxième convocation avec PDF.
- 7 retards et plus : notification au Doyen, seulement après les convocations 3 et 6.
- Après avertissement confirmé : tout nouveau retard crée une nouvelle notification Doyen.
- Après exclusion confirmée : tout nouveau retard relance la procédure de notification.

Le PDF joint aux convocations de retards reprend le formulaire CPNV `Retenue` : nom et prénom de l'apprenti, classe, date de convocation et motif sont remplis automatiquement. Pour une 2e convocation, le motif indique simplement la convocation pour 6 arrivées tardives et la remarque reprend la date de la première convocation pour 3 arrivées tardives.

## Paramètres

L'onglet `Paramètres` permet de modifier les seuils, les destinataires Doyen, les seuils d'absence, les règles de démarrage des absences, les modèles d'e-mails et la signature des messages.

Variables disponibles dans les modèles :

`{recipient_name}`, `{full_name}`, `{first_name}`, `{last_name}`, `{class_name}`, `{class_code}`, `{lateness_count}`, `{lateness_total}`, `{lateness_threshold}`, `{lateness_previous_threshold}`, `{lateness_additional_count}`, `{absence_count}`, `{absence_rate}`, `{removal_count}`, `{today}`, `{convocation_date}`, `{first_convocation_date}`, `{detention_no_show_note}`, `{trainer_salutation}`, `{trainer_name}`, `{trainer_company}`, `{trainer_company_address}`, `{trainer_company_zip}`, `{trainer_company_city}`, `{trainer_company_full_address}`, `{trainer_email}`, `{trainer_phone}`, `{responsible_trainer_name}`, `{responsible_trainer_company}`, `{responsible_trainer_company_address}`, `{responsible_trainer_company_zip}`, `{responsible_trainer_company_city}`, `{responsible_trainer_company_full_address}`, `{responsible_trainer_email}`, `{responsible_trainer_phone}`, `{contract_status}`, `{contract_break_date}`, `{contract_follow_until}`, `{contract_review_date}`, `{contract_responsible_email}`, `{student_name}`, `{student_email}`, `{apprentices_list}`, `{apprentices_count}`, `{classes_list}`, `{threshold}`, `{gender_article}`, `{gender_article_cap}`, `{gender_pronoun}`, `{gender_participle}`.

La signature peut contenir un texte formaté et une image. Elle est ajoutée aux convocations, notifications et rapports envoyés depuis le plugin.

La variable `{class_code}` reprend le numéro court de classe pour les objets d'e-mails, par exemple `C1a` ou `C2b`. Si un objet personnalisé ne contient pas ce code, le plugin l'ajoute automatiquement avant l'envoi.

Pour la deuxième convocation à 6 retards, `{first_convocation_date}` reprend la date de la première convocation, `{lateness_total}` affiche le total atteint et `{lateness_additional_count}` affiche les retards supplémentaires depuis la première convocation. `{lateness_count}` reste disponible et correspond aussi au total.

## Messages aux formateurs

Dans `Messages types`, les modèles sont séparés des envois réels :

- `Modèles pour e-mails libres` sert à créer les textes proposés depuis le bouton `Rédiger un e-mail libre`.
- `Modèles pour envois de masse` sert à créer les textes utilisés ensuite dans les envois groupés.

Dans `Communication`, les envois restent séparés :

- `Responsables de formation` regroupe chaque e-mail de responsable une seule fois et affiche le responsable ainsi que l'entreprise pour contrôle.
- `Envois de masse` sert à choisir un message, sélectionner les destinataires, sélectionner les classes concernées et envoyer les e-mails individuels aux formateurs.

Les modèles libres créés dans `Messages types` sont proposés lorsque vous ouvrez une situation avec `Rédiger un e-mail libre`. Choisir un modèle remplit automatiquement l'objet et le message avec les données de l'apprenti concerné.

Il est possible de cocher une ou plusieurs classes destinataires dans la partie d'envoi. Si aucune classe n'est cochée, le plugin utilise toutes les classes actives.

Le menu `Destinataires` permet d'envoyer aux formateurs uniquement ou aux formateurs avec les élèves rattachés en copie. Dans tous les cas, le plugin envoie un e-mail individuel au responsable de formation renseigné dans les fiches apprentis. Un même e-mail de responsable reçoit un seul message, avec la liste des apprentis et leur numéro court de classe. La communication est ensuite sauvegardée dans chaque fiche apprenti concernée.

Les apprentis en rupture de contrat ou candidats libres sont affichés dans une alerte dédiée et exclus des envois formateur. Le bouton d'e-mail de rapport au formateur est aussi bloqué pour eux ; le PDF reste téléchargeable pour le suivi interne.

Le champ `Adresses de test` permet d'envoyer un aperçu à une ou plusieurs adresses avant l'envoi réel. Les adresses peuvent être séparées par une virgule, un point-virgule, un espace ou un retour à la ligne. L'envoi de test ne crée pas d'action exécutée sur les fiches élèves.

Les modèles proposés utilisent l'introduction et la formule d'appel du responsable lorsqu'elles sont renseignées. La variable `{apprentices_list}` ajoute automatiquement les prénoms, noms et numéros courts de classe des apprentis rattachés.

Si un ancien modèle contient une liste à puces avec `{{Prénom Nom}}` et `{{Numéro de classe}}`, le plugin remplace automatiquement cette zone par la vraie liste des apprentis du destinataire. Les noms et classes générés gardent la police normale du message, même si le repère avait été collé dans un style de type code. La variable recommandée reste `{apprentices_list}`, car elle évite de devoir prévoir un nombre fixe de lignes.

Les variables spécifiques sont `{trainer_email}`, `{responsible_trainer_name}`, `{responsible_trainer_email}`, `{responsible_trainer_company}`, `{trainer_company_full_address}`, `{responsible_trainer_company_full_address}`, `{apprentices_list}`, `{apprentices_count}` et `{classes_list}`.

Dans `Messages types`, le menu `Modèle libre à modifier` permet de gérer les modèles proposés dans les e-mails libres. Le menu `Message préformaté à modifier` permet de charger un modèle enregistré pour les envois de masse. Pour créer ou modifier un modèle, adaptez le nom, l'objet et le message, puis utilisez le bouton d'enregistrement correspondant.

Le menu `Message à envoyer` permet de charger un modèle dans la partie envoi. L'objet et le message peuvent encore être adaptés avant l'envoi sans modifier le modèle enregistré.

Les champs de message disposent d'une barre de mise en forme : gras, italique, souligné, listes, liens et retrait de la mise en forme. Le formatage est conservé dans les modèles, les signatures et les e-mails envoyés.

## E-mails et rappels internes

Les convocations, rapports et e-mails individuels aux formateurs partent uniquement après une action d'envoi manuelle depuis le formulaire correspondant.

Le bloc `Rappels internes` permet de recevoir un e-mail de rappel si des actions restent en attente au moins le délai indiqué. Il permet aussi de recevoir un rappel si le dernier import Lagap date de plus de 8 jours, ou du délai personnalisé choisi.

Les ruptures de contrat arrivées à l'échéance `date de rupture + 3 mois + 1 semaine` sont incluses dans ce rappel interne quotidien, même si l'action vient d'être créée le jour du contrôle.

## Confidentialité

Les fichiers importés, les PDF et les photos sont stockés dans un dossier privé et servis uniquement après contrôle d'accès WordPress. Les fiches peuvent être exportées individuellement depuis le détail de l'apprenti.
