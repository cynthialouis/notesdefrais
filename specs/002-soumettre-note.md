# 002 — Soumettre une note

## Besoin

Un salarié avance des frais (repas, transport, hébergement, matériel, autre) et doit les déclarer pour être remboursé. Il prépare une note en brouillon, la corrige autant que nécessaire, puis la soumet quand elle est prête. Il retrouve ensuite toutes ses notes et leur état, sans jamais voir celles des autres.

## Critères d'acceptation

- **C1** QUAND un salarié enregistre une note avec un libellé, un montant, une date et une catégorie valides, LE SYSTÈME DOIT la créer en brouillon, à son nom.
- **C2** LE SYSTÈME DOIT conserver exactement le montant saisi en euros, au centime près (12,50 € saisis → 12,50 € relus, jamais d'arrondi).
- **C3** SI un champ est invalide, ALORS LE SYSTÈME DOIT refuser l'enregistrement (400) en indiquant chaque champ en cause.
- **C4** QUAND le formulaire contient un champ invalide, LE SYSTÈME DOIT afficher le message sous ce champ avant tout envoi.
- **C5** TANT QUE la note est en brouillon, LE SYSTÈME DOIT permettre à son propriétaire d'en modifier tous les champs.
- **C6** QUAND le propriétaire soumet un brouillon, LE SYSTÈME DOIT le passer à l'état « soumise » et dater la soumission.
- **C7** SI une note n'est plus en brouillon, ALORS LE SYSTÈME DOIT refuser sa modification et sa soumission (409).
- **C8** LE SYSTÈME DOIT lister les notes de l'utilisateur, les plus récentes d'abord, avec libellé, montant en euros, date, catégorie et état.
- **C9** QUAND l'utilisateur choisit un état dans le filtre, LE SYSTÈME DOIT n'afficher que les notes dans cet état ; « Tous » les affiche toutes.
- **C10** LE SYSTÈME DOIT proposer les catégories connues du serveur, avec leur libellé.
- **C11** QUAND le propriétaire supprime un brouillon et confirme la suppression, LE SYSTÈME DOIT le supprimer définitivement.
- **C12** SI une note n'est plus en brouillon, ALORS LE SYSTÈME DOIT refuser sa suppression (409).
- **C13** LE SYSTÈME DOIT permettre à tout utilisateur connecté (salarié, manager, admin) de gérer ses propres notes.

Champs valides (C3, C4) : libellé de 2 à 80 caractères après suppression des espaces autour ; montant strictement positif, au plus 10 000,00 €, au plus deux décimales ; date valide, pas postérieure à aujourd'hui ; catégorie parmi celles du serveur.

## Autorisations

| Action | Qui peut | Sinon |
|---|---|---|
| Lister ses notes, créer une note | connecté | 401 |
| Lire, modifier, soumettre, supprimer une note | son propriétaire | 401 non connecté ; 404 pour la note d'un autre (son existence n'est pas révélée) |
| Lister les catégories | connecté | 401 |

## Cas limites et erreurs

- 10 000,00 € accepté ; 10 000,01 € et 0 € refusés ; 12,505 € refusé (trois décimales).
- Date du jour acceptée, date de demain refusée.
- Libellé entouré d'espaces : enregistré sans eux ; libellé fait uniquement d'espaces : refusé.
- Soumettre deux fois la même note : la seconde fois 409, la date de soumission ne change pas.
- Supprimer deux fois le même brouillon : la seconde fois 404.
- Abandonner la confirmation de suppression : rien n'est supprimé.
- Aucune note, ou aucune note dans l'état filtré : message explicite au lieu d'une liste vide.
- Identifiant de note inexistant ou d'un autre utilisateur : 404, dans les deux cas.

## Règles recalculées côté front

- Validation du formulaire (mêmes règles que le serveur, pour l'affichage immédiat de C4) : testée des deux côtés, celle du serveur fait foi.
- « Date pas postérieure à aujourd'hui » : le front compare à la date locale du navigateur, le serveur à la date du jour en Europe/Paris ; un écart est possible autour de minuit, le serveur tranche.

## Hors périmètre

Validation et rejet par le manager, historique des actions, modification d'une note rejetée et choix du valideur des notes d'un manager ou d'un admin (feature 003) ; politique de dépenses, plafonds et limite d'ancienneté de la date (004) ; justificatifs (005) ; devises autres que l'euro.

## Questions ouvertes

Aucune. Tranchées le 11/10 : tout utilisateur connecté gère ses notes (C13), le valideur des notes d'un manager ou d'un admin est reporté à 003 ; suppression d'un brouillon possible (C11, C12) ; aucune limite d'ancienneté de la date ; historique reporté à 003.

## Écarts avec les docs

- `docs/api.md` : `GET /categories` renvoie « catégories + politique associée » ; en 002, les catégories seules (les politiques arrivent en 004).
- `docs/modele-donnees.md` : historique des transitions reporté à 003.
- `docs/api.md` : nouvelle route de suppression d'un brouillon.
