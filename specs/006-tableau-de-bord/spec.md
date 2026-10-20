# Feature Specification: 006 — Tableau de bord

**Feature Branch**: `feat/006-tableau-de-bord`

**Created**: 2026-10-13

**Status**: Implémentée (fusionnée le 2026-10-13)

**Input**: En arrivant dans l'application, chacun doit voir d'un coup d'œil où en sont ses dépenses : un salarié, combien il a en brouillon, en attente, validé ou rejeté ; un manager, ce que coûte son équipe, par catégorie et par mois, pour suivre son budget. Le manager doit aussi pouvoir récupérer les notes validées de son équipe dans un tableur, pour la comptabilité.

## Clarifications

### Session 2026-10-13

- Q: Sur quelle période ? → A: Période fixe des 12 derniers mois civils, mois en cours compris (C3, C4, C6).
- Q: Quel mois compte pour une note ? → A: Le mois de la date de la dépense.
- Q: Quelle forme pour les graphiques ? → A: Tableau accessible avec barres en CSS, sans bibliothèque de graphiques.
- Q: Quelles notes comptent dans les totaux d'équipe ? → A: Les seules notes validées.
- Q: Quel format de CSV ? → A: Celui de C7.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Voir où en sont ses propres notes (Priority: P1)

Tout utilisateur voit en arrivant, pour chaque état (brouillon, soumise, validée, rejetée), le nombre de ses notes et leur montant total.

**Why this priority**: Concerne tous les utilisateurs, dès la connexion.

**Independent Test**: Se connecter avec un compte qui a des notes dans plusieurs états, puis avec un compte sans note.

**Critères couverts**: C1, C2, C5

**Acceptance Scenarios**:

1. **Given** un utilisateur connecté, **When** il ouvre le tableau de bord, **Then** il voit pour chaque état le nombre de ses propres notes et leur montant total (C1).
2. **Given** un état sans note, **When** le tableau de bord s'affiche, **Then** cet état apparaît quand même (0 note, 0,00 €) (C2).
3. **Given** des totaux affichés, **When** on les compare aux notes, **Then** ils ont été calculés par le serveur en centimes entiers (C5).

---

### User Story 2 - Suivre ce que coûte son équipe (Priority: P1)

Le manager voit en plus le total des notes validées des personnes qu'il encadre directement, par catégorie et par mois, sur les 12 derniers mois civils.

**Why this priority**: C'est le besoin de suivi budgétaire du manager.

**Independent Test**: Se connecter en manager et comparer les totaux d'équipe aux notes validées de ses subordonnés directs.

**Critères couverts**: C3, C4, C5, C8, C9

**Acceptance Scenarios**:

1. **Given** un manager connecté, **When** il ouvre le tableau de bord, **Then** il voit le total des notes validées de ses subordonnés directs par catégorie, sur la période (C3).
2. **Given** un manager connecté, **When** il ouvre le tableau de bord, **Then** il voit ce total pour chaque mois de la période, y compris les mois sans note (0,00 €) (C4).
3. **Given** les notes du manager lui-même et celles de personnes qu'il n'encadre pas directement, **When** les totaux d'équipe sont calculés, **Then** elles en sont exclues (C8).
4. **Given** une équipe sans note validée sur la période, **When** le manager ouvre le tableau de bord, **Then** un message le dit au lieu de tableaux vides (C9).

---

### User Story 3 - Exporter les notes validées de l'équipe (Priority: P2)

Le manager télécharge un fichier CSV des notes validées de son équipe sur la période, lisible directement par un tableur français.

**Why this priority**: Utile pour la comptabilité, mais vient après la consultation.

**Independent Test**: En manager, télécharger l'export et l'ouvrir dans un tableur.

**Critères couverts**: C6, C7, C8, C9

**Acceptance Scenarios**:

1. **Given** un manager connecté, **When** il demande l'export, **Then** il reçoit un CSV des notes validées de son équipe sur la période, une ligne par note (C6).
2. **Given** l'export, **When** il est ouvert dans un tableur français, **Then** il est lisible directement : point-virgule, UTF-8 avec BOM, montants en euros avec virgule décimale, dates JJ/MM/AAAA (C7).
3. **Given** les notes du manager et celles de personnes qu'il n'encadre pas directement, **When** l'export est produit, **Then** elles en sont exclues (C8).
4. **Given** une équipe sans note validée sur la période, **When** l'export est produit, **Then** il ne contient que la ligne d'en-tête (C9).

---

### Edge Cases

- Utilisateur sans aucune note : tous les états à 0.
- Note validée datée d'il y a 12 mois pile (premier jour du mois le plus ancien de la période) : incluse ; datée du mois précédent : exclue.
- Libellé contenant un point-virgule, un guillemet ou un retour à la ligne : échappé dans le CSV, la ligne reste une seule note.
- Libellé commençant par `=`, `+`, `-` ou `@` : neutralisé dans le CSV (injection de formule dans le tableur).
- Admin : responsable des managers, il voit leurs totaux d'équipe comme un manager voit ceux de ses salariés.

## Requirements *(mandatory)*

### Functional Requirements

Les identifiants **C1…C9** sont conservés tels quels : les tests et le code y font référence (`testC4…`, « spec 006 (C7) »).

- **C1** QUAND un utilisateur ouvre le tableau de bord, LE SYSTÈME DOIT afficher, pour chaque état (brouillon, soumise, validée, rejetée), le nombre de ses propres notes et leur montant total.
- **C2** LE SYSTÈME DOIT afficher chaque état, même sans note (0 note, 0,00 €).
- **C3** QUAND un manager ouvre le tableau de bord, LE SYSTÈME DOIT afficher en plus le total des notes validées des personnes dont il est le responsable direct, par catégorie, sur la période.
- **C4** QUAND un manager ouvre le tableau de bord, LE SYSTÈME DOIT afficher le total des notes validées de son équipe pour chaque mois de la période, y compris les mois sans note (0,00 €).
- **C5** LE SYSTÈME DOIT calculer tous les totaux côté serveur, en centimes entiers ; le front ne fait que les afficher.
- **C6** QUAND un manager demande l'export, LE SYSTÈME DOIT fournir un fichier CSV des notes validées de son équipe sur la période, une ligne par note.
- **C7** LE SYSTÈME DOIT produire un CSV lisible directement par un tableur français : séparateur point-virgule, encodage UTF-8 avec BOM, montants en euros avec virgule décimale, dates au format JJ/MM/AAAA.
- **C8** LE SYSTÈME DOIT exclure des totaux d'équipe et de l'export les notes du manager lui-même et celles des personnes qu'il n'encadre pas directement.
- **C9** SI l'équipe n'a aucune note validée sur la période, ALORS LE SYSTÈME DOIT l'indiquer par un message au lieu de tableaux vides, et l'export DOIT contenir seulement la ligne d'en-tête.

Période (C3, C4, C6) : les 12 derniers mois civils, mois en cours compris, d'après la date de la dépense (et non la date de validation), en heure de Paris.

Colonnes de l'export (C6) : date de la dépense, salarié, libellé, catégorie, montant, date de validation, validée par.

### Autorisations

| Action | Qui peut | Sinon |
|---|---|---|
| Ses propres totaux par état | connecté | 401 |
| Totaux de l'équipe, export CSV | manager et plus (sur ses subordonnés directs) | 401 non connecté ; 403 salarié |

### Règles recalculées côté front

Aucune règle métier. Les totaux, la période et le contenu du CSV viennent du serveur ; le front formate les montants en euros et calcule seulement l'échelle des barres (plus grand total de la série), décorative et toujours accompagnée de la valeur écrite. *Précisé le 13/10 après la relecture à froid.*

### Key Entities

- **Période** (calculée) : les 12 derniers mois civils, mois en cours compris, en heure de Paris.
- **Totaux** (calculés, non stockés) : par état pour un propriétaire ; par catégorie et par mois pour les notes validées des subordonnés directs.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Chaque critère C1 à C9 est couvert par au moins un test du back ou du front, et `make test` passe.
- **SC-002**: Le parcours e2e `tableau-de-bord` passe (`make test-e2e`).

## Assumptions

- **Hors périmètre** : graphiques interactifs et bibliothèque de graphiques (barres dessinées en CSS dans un tableau), choix de la période, filtres, totaux de l'équipe hors validées (en attente, rejetées), statistiques hors politique ou justificatifs, équipe indirecte (subordonnés des subordonnés), export d'autres formats (Excel, PDF), envoi automatique à la comptabilité.
- **Écarts avec les docs** :
  - `docs/api.md` : `GET /stats` « salarié / manager » et `GET /exports/expenses.csv` « manager » ; à préciser au plan (une ou deux routes de statistiques, admin inclus comme manager).
  - `docs/produit.md` : « totaux de l'équipe » ne précisait ni la période ni les états comptés : 12 derniers mois civils, notes validées.
