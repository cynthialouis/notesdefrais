# 003 — Valider / rejeter

## Besoin

Un manager doit traiter les notes de frais que lui soumettent les personnes de son équipe : les valider pour remboursement, ou les rejeter en expliquant pourquoi, pour que la personne corrige et resoumette. Chacun doit pouvoir retrouver ce qui s'est passé sur une note (qui a fait quoi, quand, et pourquoi en cas de rejet).

## Critères d'acceptation

- **C1** QUAND un manager ouvre la page Équipe, LE SYSTÈME DOIT lister les notes soumises des personnes qu'il encadre directement, et seulement celles-là, les plus anciennes soumissions d'abord.
- **C2** LE SYSTÈME DOIT afficher pour chaque note à traiter : son auteur, son libellé, sa date, sa catégorie, son montant et sa date de soumission.
- **C3** QUAND le manager valide une note soumise de son équipe, LE SYSTÈME DOIT la passer à l'état « validée » en enregistrant qui a décidé et quand.
- **C4** QUAND le manager rejette une note soumise de son équipe avec un motif, LE SYSTÈME DOIT la passer à l'état « rejetée » en enregistrant le motif, qui a décidé et quand.
- **C5** SI le motif de rejet fait moins de 5 caractères (espaces autour exclus), ALORS LE SYSTÈME DOIT refuser le rejet (400) en le signalant sur le champ motif, avant tout envoi côté écran.
- **C6** SI la note n'est pas à l'état « soumise », ALORS LE SYSTÈME DOIT refuser sa validation et son rejet (409).
- **C7** QUAND une note est décidée, LE SYSTÈME DOIT la retirer de la liste à traiter du manager.
- **C8** TANT QUE sa note est rejetée, LE SYSTÈME DOIT montrer le motif à son propriétaire et lui permettre de la modifier puis de la resoumettre, ou de la supprimer s'il y renonce (*précisé le 12/10 après la relecture à froid*).
- **C9** TANT QU'une note est validée, LE SYSTÈME DOIT refuser sa modification, sa suppression, sa soumission et toute nouvelle décision.
- **C10** QUAND une note est créée, soumise, validée ou rejetée, LE SYSTÈME DOIT l'inscrire dans son historique : action, auteur, date, et motif pour un rejet.
- **C11** LE SYSTÈME DOIT permettre au propriétaire et à son manager de consulter l'historique d'une note, du plus ancien au plus récent.
- **C12** LE SYSTÈME DOIT permettre au manager de consulter le détail d'une note soumise de son équipe pour décider.
- **C13** SI le propriétaire d'une note n'a pas de responsable (l'admin), ALORS LE SYSTÈME DOIT refuser la soumission (409) avec un message explicite.

Le responsable direct d'une personne décide de ses notes : les salariés sont rattachés à leur manager, les managers à l'admin.

## Autorisations

| Action | Qui peut | Sinon |
|---|---|---|
| Lister les notes à traiter | manager et plus | 401 non connecté ; 403 salarié |
| Lire une note, consulter son historique | son propriétaire, le manager de son propriétaire | 404 (existence non révélée) |
| Valider, rejeter | le manager du propriétaire | 403 pour le propriétaire lui-même ; 404 pour tout autre |
| Modifier, resoumettre, supprimer une note rejetée | son propriétaire | 404 |

## Cas limites et erreurs

- Motif de 5 caractères exactement accepté ; motif fait d'espaces refusé ; motif conservé sans les espaces autour.
- Décider deux fois la même note (double clic, deux onglets) : la seconde décision est refusée (409), la première est conservée.
- Une note rejetée puis resoumise redevient « soumise » et revient dans la liste à traiter ; le motif n'est plus affiché sur la note mais l'historique garde le rejet et son motif.
- Un manager ne décide jamais de ses propres notes.
- Liste à traiter vide : message explicite.

## Règles recalculées côté front

- Longueur minimale du motif (5 caractères, espaces autour exclus, comptés en caractères comme pour le libellé) : pour l'erreur immédiate de C5 ; testée des deux côtés, le serveur fait foi.

Non recalculé : ce qui est modifiable ou décidable. Le serveur l'indique pour chaque note.

## Hors périmètre

Notifications (email ou autre), délégation à un remplaçant, reconstitution de l'historique des notes créées avant cette feature (les données de démo sont régénérées), verrou en base contre deux décisions strictement simultanées (le contrôle d'état de C6 suffit), validation par lots, annulation ou modification d'une décision, commentaire sur une validation, plafonds et justificatifs (004, 005).

## Questions ouvertes

Aucune. Tranchées le 12/10 : managers rattachés à l'admin, admin sans responsable ne soumet pas (C13) ; plus anciennes soumissions d'abord (C1) ; pas de reconstitution d'historique ; contrôle d'état contre les décisions simultanées ; motif retiré de la note à la resoumission, conservé dans l'historique.

## Écarts avec les docs

- `docs/api.md` annonce `GET /expenses` filtré « manager : son équipe » ; la feature 002 en a fait la liste des notes personnelles. Les notes à traiter passent par une route dédiée (à décider au plan).
- `docs/modele-donnees.md` : une personne sans responsable ne peut pas soumettre (C13) ; les managers ont l'admin pour responsable.
- Feature 001 : les données de démo et leur test (`AppFixturesTest`) supposaient des managers sans responsable ; à adapter.

## Plan

- **Back** : `Expense` gagne `decidedAt`, `decidedBy`, `rejectionReason` ; `ExpenseStatus::isEditable()` inclut `Rejected` ; entité `ExpenseEvent` (type created / submitted / approved / rejected, auteur, commentaire, date). Workflow : `submit` depuis `draft` ou `rejected`, `approve`, `reject` ; listeners pour la décision, l'effacement du motif à la resoumission et l'écriture de l'historique. `ExpenseVoter` : `EXPENSE_VIEW` (propriétaire, ou son responsable hors brouillon), `EXPENSE_DECIDE` (responsable). `ExpenseManager::approve/reject`, refus de soumettre sans responsable. Routes `GET /api/team/expenses`, `POST /api/expenses/{id}/approve`, `POST /api/expenses/{id}/reject` (`RejectionInput`), `GET /api/expenses/{id}/events`. `ExpenseView` construite pour un lecteur (`owner`, décision, motif, `editable`, `decidable`). Démo : managers rattachés à l'admin.
- **Front** : store étendu (liste à traiter, `approve`, `reject`, historique) ; composant `Textarea` ; page Équipe ; détail partagé `/notes/:id` et `/equipe/:id` avec carte Décision, motif d'un rejet, carte Historique.

## Tâches

1. Modèle : champs de décision, `ExpenseEvent`, `isEditable()` (C8, C9, C10)
2. Workflow : transitions et listeners, historique (C3, C4, C8, C10)
3. `ExpenseVoter` : lecture et décision (C12, autorisations)
4. `ExpenseManager` et `RejectionInput` : décisions, refus sans responsable (C3 à C6, C13)
5. Routes et vues : liste à traiter, décisions, historique, `ExpenseView` par lecteur (C1, C2, C7, C11, C12)
6. Données de démo et `AppFixturesTest` (C13)
7. Types régénérés et store (C1, C3, C4, C11)
8. Écrans : Équipe, décision, motif, historique (C1, C2, C5, C7, C8, C11)
