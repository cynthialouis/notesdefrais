# Feature Specification: 003 — Valider / rejeter

**Feature Branch**: `feat/003-valider-rejeter`

**Created**: 2026-10-12

**Status**: Implémentée (fusionnée le 2026-10-12)

**Input**: Un manager doit traiter les notes de frais que lui soumettent les personnes de son équipe : les valider pour remboursement, ou les rejeter en expliquant pourquoi, pour que la personne corrige et resoumette. Chacun doit pouvoir retrouver ce qui s'est passé sur une note (qui a fait quoi, quand, et pourquoi en cas de rejet).

## Clarifications

### Session 2026-10-12

- Q: Qui décide des notes d'un manager, et d'un admin ? → A: Les managers sont rattachés à l'admin ; l'admin, sans responsable, ne soumet pas (C13).
- Q: Dans quel ordre afficher la liste à traiter ? → A: Les plus anciennes soumissions d'abord (C1).
- Q: Faut-il reconstituer l'historique des notes antérieures ? → A: Non.
- Q: Comment empêcher deux décisions simultanées ? → A: Par le contrôle d'état (C6), sans verrou en base.
- Q: Que devient le motif à la resoumission ? → A: Il est retiré de la note et conservé dans l'historique.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Valider ou rejeter les notes de son équipe (Priority: P1)

Le manager ouvre la page Équipe, voit les notes soumises par les personnes qu'il encadre directement, consulte le détail et décide : il valide, ou il rejette avec un motif.

**Why this priority**: C'est le cœur de la feature : sans décision, une note soumise reste bloquée.

**Independent Test**: Se connecter en manager, ouvrir une note soumise de son équipe, la valider ; en rejeter une autre avec un motif.

**Critères couverts**: C1, C2, C3, C4, C5, C6, C7, C12

**Acceptance Scenarios**:

1. **Given** un manager connecté, **When** il ouvre la page Équipe, **Then** il voit les notes soumises des personnes qu'il encadre directement, et seulement celles-là, les plus anciennes soumissions d'abord (C1).
2. **Given** la liste à traiter, **When** il la parcourt, **Then** chaque note montre son auteur, son libellé, sa date, sa catégorie, son montant et sa date de soumission (C2).
3. **Given** une note soumise de son équipe, **When** il en ouvre le détail, **Then** il peut la consulter pour décider (C12).
4. **Given** une note soumise de son équipe, **When** il la valide, **Then** elle passe à « validée » avec qui a décidé et quand (C3).
5. **Given** une note soumise de son équipe, **When** il la rejette avec un motif, **Then** elle passe à « rejetée » avec le motif, qui a décidé et quand (C4).
6. **Given** un motif de moins de 5 caractères (espaces autour exclus), **When** il tente de rejeter, **Then** l'écran le signale avant l'envoi et l'API refuse (400) sur le champ motif (C5).
7. **Given** une note qui n'est pas « soumise », **When** on tente de la valider ou de la rejeter, **Then** c'est refusé (409) (C6).
8. **Given** une note décidée, **When** le manager revient à la liste, **Then** elle n'y figure plus (C7).

---

### User Story 2 - Corriger et resoumettre une note rejetée (Priority: P1)

Le propriétaire d'une note rejetée voit le motif, la corrige et la resoumet, ou la supprime s'il y renonce. Une note validée, elle, ne bouge plus.

**Why this priority**: Le rejet n'a de sens que si la personne peut corriger ; sinon la note est perdue.

**Independent Test**: Rejeter une note en manager, puis, en salarié, lire le motif, corriger et resoumettre.

**Critères couverts**: C8, C9, C13

**Acceptance Scenarios**:

1. **Given** une note rejetée, **When** son propriétaire l'ouvre, **Then** il voit le motif et peut la modifier puis la resoumettre, ou la supprimer (C8).
2. **Given** une note validée, **When** on tente de la modifier, de la supprimer, de la soumettre ou de la décider à nouveau, **Then** c'est refusé (C9).
3. **Given** un propriétaire sans responsable (l'admin), **When** il tente de soumettre une note, **Then** c'est refusé (409) avec un message explicite (C13).

---

### User Story 3 - Retrouver l'historique d'une note (Priority: P2)

Le propriétaire et son manager consultent l'historique d'une note : création, soumission, validation, rejet, avec auteur, date et motif d'un rejet.

**Why this priority**: Utile pour comprendre une note après plusieurs allers-retours, mais pas indispensable pour décider.

**Independent Test**: Faire passer une note par création, soumission, rejet, resoumission et validation, puis lire l'historique.

**Critères couverts**: C10, C11

**Acceptance Scenarios**:

1. **Given** une note, **When** elle est créée, soumise, validée ou rejetée, **Then** l'action est inscrite dans son historique avec auteur, date, et motif pour un rejet (C10).
2. **Given** une note, **When** son propriétaire ou son manager consulte l'historique, **Then** il le voit du plus ancien au plus récent (C11).

---

### Edge Cases

- Motif de 5 caractères exactement accepté ; motif fait d'espaces refusé ; motif conservé sans les espaces autour.
- Décider deux fois la même note (double clic, deux onglets) : la seconde décision est refusée (409), la première est conservée.
- Une note rejetée puis resoumise redevient « soumise » et revient dans la liste à traiter ; le motif n'est plus affiché sur la note mais l'historique garde le rejet et son motif.
- Un manager ne décide jamais de ses propres notes.
- Liste à traiter vide : message explicite.

## Requirements *(mandatory)*

### Functional Requirements

Les identifiants **C1…C13** sont conservés tels quels : les tests et le code y font référence (`testC4…`, « spec 003 (C4, C5, C6, C9) »).

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

### Autorisations

| Action | Qui peut | Sinon |
|---|---|---|
| Lister les notes à traiter | manager et plus | 401 non connecté ; 403 salarié |
| Lire une note, consulter son historique | son propriétaire, le manager de son propriétaire | 404 (existence non révélée) |
| Valider, rejeter | le manager du propriétaire | 403 pour le propriétaire lui-même ; 404 pour tout autre |
| Modifier, resoumettre, supprimer une note rejetée | son propriétaire | 404 |

### Règles recalculées côté front

- Longueur minimale du motif (5 caractères, espaces autour exclus, comptés en caractères comme pour le libellé) : pour l'erreur immédiate de C5 ; testée des deux côtés, le serveur fait foi.

Non recalculé : ce qui est modifiable ou décidable. Le serveur l'indique pour chaque note.

### Key Entities

- **Note de frais** (complétée) : date de décision, auteur de la décision, motif de rejet ; états brouillon, soumise, validée, rejetée.
- **Événement d'historique** : note, type (création, soumission, validation, rejet), auteur, commentaire (motif d'un rejet), date.
- **Utilisateur** (complété) : les managers ont l'admin pour responsable.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Chaque critère C1 à C13 est couvert par au moins un test du back ou du front, et `make test` passe.
- **SC-002**: Le parcours e2e `decision` passe (`make test-e2e`).

## Assumptions

- **Hors périmètre** : notifications (email ou autre), délégation à un remplaçant, reconstitution de l'historique des notes créées avant cette feature (les données de démo sont régénérées), verrou en base contre deux décisions strictement simultanées (le contrôle d'état de C6 suffit), validation par lots, annulation ou modification d'une décision, commentaire sur une validation, plafonds et justificatifs (004, 005).
- **Écarts avec les docs** :
  - `docs/api.md` annonce `GET /expenses` filtré « manager : son équipe » ; la feature 002 en a fait la liste des notes personnelles. Les notes à traiter passent par une route dédiée (à décider au plan).
  - `docs/modele-donnees.md` : une personne sans responsable ne peut pas soumettre (C13) ; les managers ont l'admin pour responsable.
  - Feature 001 : les données de démo et leur test (`AppFixturesTest`) supposaient des managers sans responsable ; à adapter.
