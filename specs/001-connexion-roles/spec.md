# Feature Specification: 001 — Connexion par rôle

**Feature Branch**: `feat/001-connexion-roles`

**Created**: 2026-10-11

**Status**: Implémentée (fusionnée le 2026-10-11)

**Input**: Chaque personne qui utilise l'application est salarié, manager ou admin. Pour la démo, elle se connecte sans mot de passe en choisissant un compte parmi des comptes de démo (1 admin, 2 managers, 4 salariés rattachés chacun à un manager). Une fois connectée, elle ne voit et n'utilise que ce que son rôle permet. Toutes les autres features s'appuient sur cette identification.

## Clarifications

### Session 2026-10-11

- Q: Que se passe-t-il quand un utilisateur déjà connecté se connecte avec un autre compte ? → A: La nouvelle connexion remplace la session ouverte (C12).
- Q: Où va un utilisateur connecté qui ouvre la page de connexion ? → A: Au tableau de bord (C13).
- Q: Quelle durée de session ? → A: La durée par défaut du serveur (paramétrage hors périmètre).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Se connecter avec un compte de démo (Priority: P1)

Un visiteur choisit un compte de démo sur la page de connexion, sans mot de passe, et arrive sur le tableau de bord. Il reste connecté après un rechargement et peut se déconnecter.

**Why this priority**: Toutes les autres features s'appuient sur l'identification de l'utilisateur.

**Independent Test**: Ouvrir la page de connexion, choisir un compte, recharger la page, se déconnecter.

**Critères couverts**: C1, C2, C3, C4, C12, C13

**Acceptance Scenarios**:

1. **Given** un visiteur non connecté, **When** il ouvre la page de connexion, **Then** il voit les comptes de démo avec leur nom et leur rôle (C1).
2. **Given** la page de connexion, **When** le visiteur choisit un compte, **Then** une session s'ouvre pour ce compte et il arrive au tableau de bord (C2).
3. **Given** une session ouverte, **When** l'utilisateur recharge la page, **Then** il est toujours reconnu (C3).
4. **Given** une session ouverte, **When** l'utilisateur se déconnecte, **Then** la session est fermée et il arrive à la page de connexion (C4).
5. **Given** un utilisateur connecté, **When** il se connecte avec un autre compte, **Then** la session est remplacée par celle du nouveau compte (C12).
6. **Given** un utilisateur connecté, **When** il ouvre la page de connexion, **Then** il arrive au tableau de bord (C13).

---

### User Story 2 - Ne voir et n'utiliser que ce que son rôle permet (Priority: P1)

Une fois connecté, l'utilisateur ne voit dans le menu que les pages de son rôle et des rôles inférieurs ; une page réservée à un rôle supérieur affiche « Accès refusé ».

**Why this priority**: Sans contrôle d'accès par rôle, les features suivantes (équipe, politique) exposeraient des données à tort.

**Independent Test**: Se connecter successivement en salarié, manager et admin, comparer le menu et ouvrir chaque page par son adresse.

**Critères couverts**: C7, C8, C9

**Acceptance Scenarios**:

1. **Given** un salarié connecté, **When** il ouvre la page « Équipe » ou « Politique », **Then** il voit « Accès refusé » (C7).
2. **Given** un utilisateur connecté, **When** il regarde le menu, **Then** il n'y voit que les pages accessibles à son rôle (C8).
3. **Given** un admin connecté, **When** il ouvre une page de manager ou de salarié, **Then** il y accède (hiérarchie admin > manager > salarié, C9).

---

### User Story 3 - Protéger l'API et les pages des personnes non connectées (Priority: P2)

Une personne non connectée ne peut ni appeler une fonction protégée de l'API ni ouvrir une page protégée ; une demande de connexion mal formée ou visant un compte inexistant est refusée sans rien révéler.

**Why this priority**: Garantit que la protection vient du serveur, quel que soit l'état du navigateur.

**Independent Test**: Appeler l'API sans session, ouvrir une page protégée sans session, envoyer des demandes de connexion invalides.

**Critères couverts**: C5, C6, C10, C11

**Acceptance Scenarios**:

1. **Given** une personne non connectée, **When** elle appelle une fonction protégée de l'API, **Then** elle reçoit un 401 au format d'erreur unique (C5).
2. **Given** une personne non connectée, **When** elle ouvre une page protégée, **Then** elle arrive à la page de connexion (C6).
3. **Given** une demande de connexion sans identifiant ou avec un identifiant mal formé, **When** elle est envoyée, **Then** elle est refusée (400) en indiquant le champ en cause (C10).
4. **Given** une demande de connexion pour un compte inexistant, **When** elle est envoyée, **Then** elle est refusée (401) sans révéler si le compte existe (C11).

---

### Edge Cases

- Session expirée ou cookie altéré : traité comme non connecté (401 côté API, page de connexion côté front).
- Déconnexion sans session ouverte : refusée (401).
- Déconnexion demandée autrement que par une action explicite (lien, image : requête GET) : refusée (405), la session reste ouverte. *Ajouté le 11/10 après la revue API.*
- Demande de connexion dont le corps n'est pas du JSON (ce qu'enverrait un formulaire d'un autre site pour connecter un visiteur à son insu) : refusée (415). *Ajouté le 11/10 après la revue API.*
- Le rôle et les pages accessibles viennent toujours du serveur ; modifier l'état du navigateur ne donne accès à aucune donnée.

## Requirements *(mandatory)*

### Functional Requirements

Les identifiants **C1…C13** sont conservés tels quels : les tests et le code y font référence (`testC4…`, « spec 001 (C5) »).

- **C1** QUAND un visiteur ouvre la page de connexion, LE SYSTÈME DOIT afficher les comptes de démo avec leur nom et leur rôle.
- **C2** QUAND un visiteur choisit un compte, LE SYSTÈME DOIT ouvrir une session pour ce compte et l'amener au tableau de bord.
- **C3** TANT QUE la session est ouverte, LE SYSTÈME DOIT reconnaître l'utilisateur après un rechargement de la page.
- **C4** QUAND l'utilisateur se déconnecte, LE SYSTÈME DOIT fermer la session et l'amener à la page de connexion.
- **C5** SI une personne non connectée appelle une fonction protégée de l'API, ALORS LE SYSTÈME DOIT la refuser (401) avec le format d'erreur unique.
- **C6** SI une personne non connectée ouvre une page protégée, ALORS LE SYSTÈME DOIT l'amener à la page de connexion.
- **C7** SI un utilisateur ouvre une page réservée à un rôle supérieur au sien, ALORS LE SYSTÈME DOIT afficher « Accès refusé ».
- **C8** LE SYSTÈME DOIT n'afficher dans le menu que les pages accessibles au rôle de l'utilisateur.
- **C9** LE SYSTÈME DOIT appliquer la hiérarchie admin > manager > salarié : un rôle accède aussi aux pages des rôles inférieurs.
- **C10** SI la demande de connexion ne désigne pas un compte (identifiant absent ou mal formé), ALORS LE SYSTÈME DOIT la refuser (400) en indiquant le champ en cause.
- **C11** SI la demande de connexion désigne un compte inexistant, ALORS LE SYSTÈME DOIT la refuser (401) sans révéler si le compte existe.
- **C12** QUAND un utilisateur déjà connecté se connecte avec un autre compte, LE SYSTÈME DOIT remplacer la session par celle du nouveau compte.
- **C13** QUAND un utilisateur déjà connecté ouvre la page de connexion, LE SYSTÈME DOIT l'amener au tableau de bord.

### Autorisations

| Action | Qui peut | Sinon |
|---|---|---|
| Voir la liste des comptes de démo | tout le monde | — |
| Se connecter | tout le monde | — |
| Connaître l'utilisateur courant, se déconnecter | connecté | 401 |
| Tableau de bord | connecté | page de connexion |
| Page « Mes notes » | salarié, manager, admin | page de connexion / « Accès refusé » |
| Page « Équipe » | manager, admin | idem |
| Page « Politique » | admin | idem |

Les pages « Mes notes », « Équipe » et « Politique » sont vides dans cette feature (« à venir »), mais déjà protégées.

### Règles recalculées côté front

Aucune. Le serveur renvoie, pour l'utilisateur courant, la liste des rôles auxquels il a accès ; le front ne recalcule pas la hiérarchie.

### Key Entities

- **Utilisateur** : nom, email unique, rôle (salarié, manager ou admin), responsable optionnel. Les comptes de démo sont 1 admin, 2 managers et 4 salariés rattachés chacun à un manager.
- **Session** : ouverte par la connexion, reconnue après rechargement, fermée par la déconnexion ; durée par défaut du serveur.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Chaque critère C1 à C13 est couvert par au moins un test du back ou du front, et `make test` passe.
- **SC-002**: Les parcours e2e `connexion` et `acces` passent (`make test-e2e`).

## Assumptions

- **Hors périmètre** : mot de passe, inscription, OAuth, mot de passe oublié, durée de session paramétrable (la durée par défaut du serveur s'applique), gestion des comptes par l'admin, données métier des pages protégées.
- **Écarts avec les docs (déjà tranchés, docs à mettre à jour)** :
  - `docs/api.md` : nouvelle route publique de liste des comptes de démo ; l'utilisateur courant expose ses rôles accessibles.
  - `docs/produit.md` (écrans) : avec la hiérarchie, « Mes notes » et « Équipe » ne sont plus réservées à un seul rôle.
