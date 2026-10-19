# Feature Specification: 007 — Demander un congé

**Feature Branch**: `feat/007-demander-conge`

**Created**: 2026-10-19

**Status**: Validée (2026-10-19)

**Input**: Chaque personne doit pouvoir demander un congé sur une période donnée, et son responsable direct doit pouvoir l'accepter ou la refuser. Pour que chacun sache qui est absent, tout le monde voit les congés acceptés de tout le monde. L'admin peut annuler une demande en attente ou un congé accepté ; une fois accepté, un congé ne peut plus être remis en cause que par lui. Le solde de congés (008) et le calendrier des absences (009) viendront ensuite.

## Clarifications

### Session 2026-10-19

- Q: Une demande passe-t-elle par un brouillon ? → A: Non, elle est en attente dès son envoi (C1).
- Q: Demi-journées ? → A: Non, journées entières seulement ; les demi-journées pourront venir avec le solde (008).
- Q: Type de congé (congés payés, RTT, sans solde) ? → A: Un seul type en 007 ; les types viennent avec le solde (008).
- Q: Limites sur les dates ? → A: Début pas antérieur à aujourd'hui (heure de Paris), fin pas antérieure au début, pas de durée maximale (C2).
- Q: Deux demandes qui se chevauchent ? → A: Refus (409) si la période chevauche, même d'un jour, une autre demande du même demandeur en attente ou acceptée ; les demandes refusées, retirées ou annulées ne comptent pas (C4).
- Q: Refus motivé, et après ? → A: Motif obligatoire de 5 caractères au moins ; un refus est définitif, le demandeur fait une nouvelle demande (C8, C9).
- Q: Le demandeur peut-il retirer sa demande ? → A: Oui, tant qu'elle est en attente ; ensuite seul l'admin peut annuler un congé accepté (C11, C12).
- Q: Conditions de l'annulation par l'admin ? → A: Motif obligatoire de 5 caractères au moins, possible quelle que soit la date du congé (C9, C13).
- Q: « Tout le monde voit les congés de tout le monde » : quoi exactement ? → A: Chacun voit toutes ses propres demandes ; des autres, il ne voit que les congés acceptés. Seule exception : le responsable voit les demandes en attente de son équipe, pour les traiter (C6, C15, C16, C17).
- Q: Et l'admin, qui n'a pas de responsable ? → A: Il ne peut pas demander de congé (409), comme il ne peut pas soumettre de note (C5).
- Q: Ordre de la liste à traiter du manager ? → A: La date de début la plus proche d'abord (C6).

### Session 2026-10-19 (relecture du plan)

- Q: La liste des congés acceptés de tous montre-t-elle les congés passés ? → A: Pas en 007 : seulement les congés en cours ou à venir ; les congés passés seront visibles plus tard, avec le calendrier (009) (C15).
- Q: L'admin peut-il annuler une demande qui n'est pas acceptée ? → A: Oui, une demande en attente ; une demande refusée, retirée ou annulée reste définitive (C13, C14).
- Q: Pour annuler une demande en attente, l'admin doit la voir : faut-il élargir la visibilité ? → A: L'admin voit les demandes en attente de tout le monde ; les demandes refusées, retirées ou annulées restent au seul demandeur (C17, C18).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Demander un congé et suivre sa demande (Priority: P1)

Un salarié ou un manager pose une période de congé ; la demande part en attente chez son responsable direct. Il retrouve ses demandes et leur état, et peut retirer une demande tant qu'elle n'est pas décidée.

**Why this priority**: Sans demande, il n'y a rien à accepter ni à afficher.

**Independent Test**: Se connecter en salarié, demander un congé, le voir en attente, le retirer ; tenter une période invalide et une période qui en chevauche une autre.

**Critères couverts**: C1, C2, C3, C4, C5, C11, C12, C16

**Acceptance Scenarios**:

1. **Given** un salarié connecté, **When** il demande un congé avec une date de début et une date de fin valides, **Then** la demande est enregistrée à son nom, en attente (C1).
2. **Given** une fin antérieure au début, un début antérieur à aujourd'hui ou une date absente, **When** la demande est envoyée, **Then** elle est refusée (400) sur le champ en cause (C2), et le formulaire l'a signalé avant l'envoi (C3).
3. **Given** une demande en attente ou acceptée du 10 au 14, **When** le même salarié demande du 14 au 18, **Then** c'est refusé (409) avec un message explicite (C4).
4. **Given** l'admin, **When** il demande un congé, **Then** c'est refusé (409) avec un message explicite (C5).
5. **Given** sa demande en attente, **When** le demandeur la retire, **Then** elle passe à l'état « retirée » (C11) ; une fois décidée, le retrait est refusé (409) (C12).
6. **Given** un utilisateur connecté, **When** il ouvre ses congés, **Then** il voit toutes ses demandes, quel que soit leur état, avec le motif d'un refus ou d'une annulation (C16).

---

### User Story 2 - Accepter ou refuser les demandes de son équipe (Priority: P1)

Le manager voit les demandes en attente des personnes qu'il encadre directement et décide : il accepte, ou il refuse avec un motif.

**Why this priority**: Une demande sans décision ne sert à rien ; c'est le pendant de 003 pour les congés.

**Independent Test**: Se connecter en manager, accepter une demande de son équipe, en refuser une autre avec un motif.

**Critères couverts**: C6, C7, C8, C9, C10

**Acceptance Scenarios**:

1. **Given** un manager connecté, **When** il ouvre les demandes à traiter, **Then** il voit les demandes en attente des personnes qu'il encadre directement, et seulement celles-là, la date de début la plus proche d'abord (C6).
2. **Given** une demande en attente de son équipe, **When** il l'accepte, **Then** elle passe à « acceptée » avec qui a décidé et quand (C7).
3. **Given** une demande en attente de son équipe, **When** il la refuse avec un motif d'au moins 5 caractères, **Then** elle passe à « refusée » avec le motif, qui a décidé et quand (C8) ; un motif plus court est signalé avant l'envoi et refusé (400) (C9).
4. **Given** une demande qui n'est pas en attente, **When** on tente de l'accepter ou de la refuser, **Then** c'est refusé (409) (C10).

---

### User Story 3 - Voir qui est en congé (Priority: P2)

Tout utilisateur connecté voit les congés acceptés de tout le monde, pour savoir qui est absent quand ; les autres demandes ne regardent que leur demandeur.

**Why this priority**: Utile pour s'organiser, mais vient après la demande et la décision ; le calendrier (009) en fera une vue plus lisible.

**Independent Test**: Avec deux salariés d'équipes différentes, vérifier que chacun voit le congé accepté de l'autre, mais pas ses demandes en attente, refusées, retirées ou annulées.

**Critères couverts**: C15, C17

**Acceptance Scenarios**:

1. **Given** des congés acceptés de plusieurs personnes, passés et à venir, **When** un utilisateur connecté ouvre la liste des congés, **Then** il voit ceux en cours ou à venir, avec la personne et les dates, la date de début la plus proche d'abord, et pas les congés terminés (C15).
2. **Given** une demande en attente, refusée, retirée ou annulée, **When** une autre personne que le demandeur consulte les congés, **Then** elle ne la voit pas, sauf une demande en attente pour le responsable du demandeur et pour l'admin (C17).

---

### User Story 4 - Annuler une demande ou un congé (Priority: P3)

L'admin voit les demandes en attente de tout le monde et annule, avec un motif, une demande en attente ou un congé déjà accepté.

**Why this priority**: Cas de correction, rare.

**Independent Test**: Se connecter en admin, annuler une demande en attente d'un salarié et un congé accepté, avec un motif ; tenter d'annuler une demande refusée.

**Critères couverts**: C9, C13, C14, C18

**Acceptance Scenarios**:

1. **Given** des demandes en attente dans plusieurs équipes, **When** l'admin ouvre les demandes en attente, **Then** il les voit toutes, la date de début la plus proche d'abord (C18).
2. **Given** une demande en attente ou un congé accepté, **When** l'admin l'annule avec un motif d'au moins 5 caractères, **Then** la demande passe à « annulée » avec le motif, qui a annulé et quand (C13) ; un motif plus court est refusé (400) (C9).
3. **Given** une demande refusée, retirée ou déjà annulée, **When** l'admin tente de l'annuler, **Then** c'est refusé (409) (C14).

---

### Edge Cases

- Début égal à la fin (un seul jour) : accepté. Début aujourd'hui : accepté ; hier : refusé (400).
- Chevauchement d'un seul jour (fin de l'une = début de l'autre) : refusé (409). Chevauchement avec une demande refusée, retirée ou annulée : accepté.
- Décider deux fois la même demande (double clic, deux onglets) : la seconde décision est refusée (409), la première est conservée.
- Retirer une demande déjà décidée : 409. Retirer la demande d'un autre : 404.
- Annuler une demande refusée, retirée ou déjà annulée : 409.
- Congé accepté terminé hier : absent de la liste des congés de tous ; terminé aujourd'hui : présent.
- Un manager ne décide jamais de ses propres demandes : c'est l'admin, son responsable, qui le fait.
- Aucune demande à traiter, aucun congé à afficher : message explicite au lieu d'une liste vide.
- Motif fait d'espaces : refusé ; motif conservé sans les espaces autour.

## Requirements *(mandatory)*

### Functional Requirements

- **C1** QUAND un utilisateur qui a un responsable demande un congé avec une date de début et une date de fin valides, LE SYSTÈME DOIT enregistrer la demande à son nom, à l'état « en attente ».
- **C2** SI une date est absente ou invalide, si la fin est antérieure au début, ou si le début est antérieur à aujourd'hui (heure de Paris), ALORS LE SYSTÈME DOIT refuser la demande (400) en indiquant chaque champ en cause.
- **C3** QUAND le formulaire de demande contient une date invalide, LE SYSTÈME DOIT afficher le message sous ce champ avant tout envoi.
- **C4** SI la période chevauche, même d'un jour, une autre demande du même demandeur en attente ou acceptée, ALORS LE SYSTÈME DOIT refuser la demande (409) avec un message explicite.
- **C5** SI le demandeur n'a pas de responsable (l'admin), ALORS LE SYSTÈME DOIT refuser la demande (409) avec un message explicite.
- **C6** QUAND un manager ouvre les demandes à traiter, LE SYSTÈME DOIT lister les demandes en attente des personnes qu'il encadre directement, et seulement celles-là, la date de début la plus proche d'abord.
- **C7** QUAND le responsable du demandeur accepte une demande en attente, LE SYSTÈME DOIT la passer à l'état « acceptée » en enregistrant qui a décidé et quand.
- **C8** QUAND le responsable du demandeur refuse une demande en attente avec un motif, LE SYSTÈME DOIT la passer à l'état « refusée » en enregistrant le motif, qui a décidé et quand.
- **C9** SI le motif d'un refus ou d'une annulation fait moins de 5 caractères (espaces autour exclus), ALORS LE SYSTÈME DOIT le refuser (400) en le signalant sur le champ motif, avant tout envoi côté écran.
- **C10** SI la demande n'est pas en attente, ALORS LE SYSTÈME DOIT refuser son acceptation et son refus (409).
- **C11** TANT QUE sa demande est en attente, LE SYSTÈME DOIT permettre au demandeur de la retirer : elle passe à l'état « retirée ».
- **C12** SI la demande n'est plus en attente, ALORS LE SYSTÈME DOIT refuser son retrait (409).
- **C13** QUAND l'admin annule une demande en attente ou un congé accepté avec un motif, LE SYSTÈME DOIT la passer à l'état « annulée » en enregistrant le motif, qui a annulé et quand, quelle que soit la date du congé.
- **C14** SI la demande n'est ni en attente ni acceptée, ALORS LE SYSTÈME DOIT refuser son annulation (409).
- **C15** LE SYSTÈME DOIT montrer à tout utilisateur connecté les congés acceptés en cours ou à venir de tout le monde (date de fin pas antérieure à aujourd'hui, heure de Paris), avec la personne et les dates, la date de début la plus proche d'abord.
- **C16** LE SYSTÈME DOIT montrer à chacun toutes ses propres demandes, quel que soit leur état, avec le motif d'un refus ou d'une annulation.
- **C17** LE SYSTÈME DOIT réserver à son seul demandeur une demande en attente, refusée, retirée ou annulée, et ses motifs ; seules exceptions, le responsable du demandeur et l'admin voient ses demandes en attente (C6, C18).
- **C18** QUAND l'admin ouvre les demandes en attente, LE SYSTÈME DOIT lister les demandes en attente de tout le monde, la date de début la plus proche d'abord.

### Autorisations

| Action | Qui peut | Sinon |
|---|---|---|
| Demander un congé | connecté, avec un responsable | 401 non connecté ; 409 sans responsable (C5) |
| Voir les congés acceptés de tous | connecté | 401 |
| Voir ses propres demandes, tous états | connecté | 401 |
| Lister les demandes à traiter | manager et plus | 401 non connecté ; 403 salarié |
| Accepter, refuser | le responsable du demandeur | 403 pour le demandeur lui-même ; 404 pour tout autre |
| Retirer une demande | son demandeur | 404 pour tout autre |
| Lister les demandes en attente de tous | admin | 401 non connecté ; 403 manager ou salarié |
| Annuler une demande en attente ou un congé accepté | admin | 401 non connecté ; 403 manager ou salarié |
| Voir une demande non acceptée et ses motifs | son demandeur ; son responsable et l'admin si elle est en attente | 404 (existence non révélée) |

### Règles recalculées côté front

- Validité des dates (fin pas antérieure au début, début pas antérieur à aujourd'hui) : pour l'erreur immédiate de C3 ; le front compare à la date locale du navigateur, le serveur à la date du jour à Paris, le serveur fait foi (comme en 002). Testée des deux côtés.
- Longueur minimale d'un motif de refus ou d'annulation (5 caractères, espaces autour exclus) : pour l'erreur immédiate de C9, comme en 003. Testée des deux côtés.

Non recalculé : le chevauchement (C4), ni ce que le lecteur peut faire d'une demande (accepter, refuser, retirer, annuler) : le serveur l'indique pour chaque demande.

### Key Entities

- **Demande de congé** : demandeur, date de début, date de fin (journées entières, bornes comprises), état (en attente, acceptée, refusée, retirée, annulée), date de la demande ; décision (auteur, date, motif d'un refus) ; annulation (auteur, date, motif).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Chaque critère C1 à C18 est couvert par au moins un test du back ou du front, et `make test` passe.
- **SC-002**: Un parcours e2e passe (`make test-e2e`) : un salarié demande un congé, son manager l'accepte, un salarié d'une autre équipe le voit, l'admin l'annule.

## Assumptions

- **Hors périmètre** : solde de congés, décompte des jours et types de congé (008) ; calendrier des absences et congés passés des autres (009) ; demi-journées ; jours ouvrés, week-ends et jours fériés ; modification d'une demande (on la retire et on en refait une) ; resoumission d'une demande refusée ; notifications ; arrêt maladie et toute donnée de santé ; délégation à un remplaçant ; lien avec les notes de frais ; historique détaillé des actions (la décision et l'annulation sont enregistrées sur la demande).
- **Écarts avec les docs** :
  - `docs/produit.md` : l'application ne gérait que des notes de frais ; les congés sont un nouveau domaine (choix assumé : POC d'expérimentation), avec un nouvel écran.
  - `docs/modele-donnees.md` : nouvelle entité et nouveau cycle de vie, à documenter au plan.
  - `docs/api.md` : nouvelles routes, à préciser au plan.
