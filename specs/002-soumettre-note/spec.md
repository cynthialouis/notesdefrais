# Feature Specification: 002 — Soumettre une note

**Feature Branch**: `feat/002-soumettre-note`

**Created**: 2026-10-11

**Status**: Implémentée (fusionnée le 2026-10-11)

**Input**: Un salarié avance des frais (repas, transport, hébergement, matériel, autre) et doit les déclarer pour être remboursé. Il prépare une note en brouillon, la corrige autant que nécessaire, puis la soumet quand elle est prête. Il retrouve ensuite toutes ses notes et leur état, sans jamais voir celles des autres.

## Clarifications

### Session 2026-10-11

- Q: Qui peut gérer des notes ? → A: Tout utilisateur connecté gère ses propres notes (C13) ; le valideur des notes d'un manager ou d'un admin est reporté à 003.
- Q: Un brouillon peut-il être supprimé ? → A: Oui (C11, C12).
- Q: Y a-t-il une limite d'ancienneté de la date ? → A: Non.
- Q: L'historique des actions fait-il partie de 002 ? → A: Non, reporté à 003.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Créer une note en brouillon (Priority: P1)

Un salarié saisit libellé, montant, date et catégorie ; la note est enregistrée en brouillon à son nom, au centime près. Les erreurs de saisie sont signalées sous chaque champ avant l'envoi, et le serveur les refuse aussi.

**Why this priority**: Sans création de note, aucune autre étape (soumission, validation) n'existe.

**Independent Test**: Créer une note valide puis tenter une note invalide, avec n'importe quel compte de démo.

**Critères couverts**: C1, C2, C3, C4, C10, C13

**Acceptance Scenarios**:

1. **Given** un utilisateur connecté, **When** il enregistre une note avec un libellé, un montant, une date et une catégorie valides, **Then** la note est créée en brouillon, à son nom (C1, C13).
2. **Given** un montant saisi de 12,50 €, **When** la note est relue, **Then** elle affiche 12,50 €, sans arrondi (C2).
3. **Given** un champ invalide, **When** la note est envoyée à l'API, **Then** elle est refusée (400) avec chaque champ en cause (C3).
4. **Given** un champ invalide dans le formulaire, **When** l'utilisateur tente d'enregistrer, **Then** le message s'affiche sous ce champ avant tout envoi (C4).
5. **Given** le formulaire, **When** l'utilisateur choisit la catégorie, **Then** il voit les catégories connues du serveur avec leur libellé (C10).

---

### User Story 2 - Modifier puis soumettre un brouillon (Priority: P1)

Tant que la note est en brouillon, son propriétaire la corrige librement ; quand elle est prête, il la soumet, et elle n'est plus modifiable.

**Why this priority**: La soumission est le but de la feature : c'est elle qui envoie la note en validation.

**Independent Test**: Modifier un brouillon, le soumettre, puis tenter de le modifier et de le soumettre à nouveau.

**Critères couverts**: C5, C6, C7

**Acceptance Scenarios**:

1. **Given** une note en brouillon, **When** son propriétaire en modifie les champs, **Then** tous les champs sont modifiables (C5).
2. **Given** un brouillon, **When** son propriétaire le soumet, **Then** il passe à l'état « soumise » avec la date de soumission (C6).
3. **Given** une note qui n'est plus en brouillon, **When** on tente de la modifier ou de la soumettre, **Then** c'est refusé (409) (C7).

---

### User Story 3 - Retrouver ses notes et leur état (Priority: P2)

L'utilisateur voit la liste de ses notes, les plus récentes d'abord, et peut la filtrer par état ; il ne voit jamais les notes des autres.

**Why this priority**: Suivre ses notes est utile dès qu'il en a plusieurs, mais vient après leur création.

**Independent Test**: Créer plusieurs notes dans différents états, puis filtrer la liste.

**Critères couverts**: C8, C9

**Acceptance Scenarios**:

1. **Given** plusieurs notes, **When** l'utilisateur ouvre « Mes notes », **Then** il voit ses notes les plus récentes d'abord, avec libellé, montant en euros, date, catégorie et état (C8).
2. **Given** la liste, **When** il choisit un état dans le filtre, **Then** seules les notes dans cet état s'affichent ; « Tous » les affiche toutes (C9).

---

### User Story 4 - Supprimer un brouillon (Priority: P3)

Le propriétaire peut supprimer définitivement un brouillon dont il n'a plus besoin, après confirmation.

**Why this priority**: Confort : un brouillon inutile peut aussi rester en brouillon.

**Independent Test**: Supprimer un brouillon en confirmant, puis tenter de supprimer une note soumise.

**Critères couverts**: C11, C12

**Acceptance Scenarios**:

1. **Given** un brouillon, **When** son propriétaire le supprime et confirme, **Then** il est supprimé définitivement (C11).
2. **Given** une note qui n'est plus en brouillon, **When** on tente de la supprimer, **Then** c'est refusé (409) (C12).

---

### Edge Cases

- 10 000,00 € accepté ; 10 000,01 € et 0 € refusés ; 12,505 € refusé (trois décimales).
- Date du jour acceptée, date de demain refusée.
- Libellé entouré d'espaces : enregistré sans eux ; libellé fait uniquement d'espaces : refusé.
- Soumettre deux fois la même note : la seconde fois 409, la date de soumission ne change pas.
- Supprimer deux fois le même brouillon : la seconde fois 404.
- Abandonner la confirmation de suppression : rien n'est supprimé.
- Aucune note, ou aucune note dans l'état filtré : message explicite au lieu d'une liste vide.
- Identifiant de note inexistant ou d'un autre utilisateur : 404, dans les deux cas.
- Modification d'une note qui n'est plus en brouillon avec des données invalides : refusée en 400 (le contenu est vérifié avant l'état), sans modification. *Précisé le 11/10 après la relecture à froid.*

## Requirements *(mandatory)*

### Functional Requirements

Les identifiants **C1…C13** sont conservés tels quels : les tests et le code y font référence (`testC4…`, « spec 002 (C7) »).

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

### Autorisations

| Action | Qui peut | Sinon |
|---|---|---|
| Lister ses notes, créer une note | connecté | 401 |
| Lire, modifier, soumettre, supprimer une note | son propriétaire | 401 non connecté ; 404 pour la note d'un autre (son existence n'est pas révélée) |
| Lister les catégories | connecté | 401 |

### Règles recalculées côté front

- Validation du formulaire (mêmes règles que le serveur, pour l'affichage immédiat de C4) : testée des deux côtés, celle du serveur fait foi.
- « Date pas postérieure à aujourd'hui » : le front compare à la date locale du navigateur, le serveur à la date du jour en Europe/Paris ; un écart est possible autour de minuit, le serveur tranche.
- Le libellé est nettoyé des blancs (Unicode compris) et sa longueur comptée en caractères de la même façon des deux côtés.

Non recalculé : savoir si une note est modifiable. Le serveur l'indique pour chaque note ; le front l'affiche sans recalculer la règle. *Précisé le 11/10 après la relecture à froid.*

### Key Entities

- **Catégorie** : repas, transport, hébergement, matériel, autre ; données de référence, avec un libellé.
- **Note de frais** : propriétaire, catégorie, libellé, montant en centimes, date de la dépense, état (brouillon ou soumise dans cette feature), date de création, date de soumission.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Chaque critère C1 à C13 est couvert par au moins un test du back ou du front, et `make test` passe.
- **SC-002**: Le parcours e2e `soumission` passe (`make test-e2e`).

## Assumptions

- **Hors périmètre** : validation et rejet par le manager, historique des actions, modification d'une note rejetée et choix du valideur des notes d'un manager ou d'un admin (feature 003) ; politique de dépenses, plafonds et limite d'ancienneté de la date (004) ; justificatifs (005) ; devises autres que l'euro.
- **Écarts avec les docs** :
  - `docs/api.md` : `GET /categories` renvoie « catégories + politique associée » ; en 002, les catégories seules (les politiques arrivent en 004).
  - `docs/modele-donnees.md` : historique des transitions reporté à 003.
  - `docs/api.md` : nouvelle route de suppression d'un brouillon.
