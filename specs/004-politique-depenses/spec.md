# Feature Specification: 004 — Politique de dépenses

**Feature Branch**: `feat/004-politique-depenses`

**Created**: 2026-10-12

**Status**: Implémentée (fusionnée le 2026-10-13)

**Input**: L'entreprise fixe des règles de dépense par catégorie : un plafond par note, et l'obligation ou non de joindre un justificatif. Les dépenses hors règles doivent être repérées avant validation, sans être interdites : le salarié est prévenu dès la saisie, et le manager voit le dépassement au moment de décider. Seul l'admin règle la politique.

## Clarifications

### Session 2026-10-12

- Q: L'obligation de justificatif bloque-t-elle déjà la soumission ? → A: Non : elle se règle et s'affiche en 004, le blocage de soumission arrive avec 005.
- Q: Le signalement d'une note change-t-il quand la politique change ? → A: Il est recalculé tant que la note n'est pas validée, et figé à la validation (C9).
- Q: Quelles politiques de départ ? → A: Celles de C10.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Régler la politique de chaque catégorie (Priority: P1)

L'admin consulte et modifie, pour chaque catégorie, le plafond par note et l'obligation de justificatif ; les contrôles suivants appliquent aussitôt la nouvelle politique.

**Why this priority**: Sans politique réglée, il n'y a rien à signaler.

**Independent Test**: Se connecter en admin, modifier le plafond d'une catégorie, vérifier qu'il s'applique aux notes.

**Critères couverts**: C1, C2, C3, C10

**Acceptance Scenarios**:

1. **Given** l'admin connecté, **When** il ouvre la page Politique, **Then** il voit pour chaque catégorie son plafond (ou « aucun ») et si un justificatif est obligatoire (C1).
2. **Given** la page Politique, **When** l'admin enregistre le plafond et l'obligation de justificatif d'une catégorie, **Then** ils s'appliquent à tous les contrôles suivants (C2).
3. **Given** un plafond qui n'est pas un montant strictement positif d'au plus 10 000,00 €, **When** l'admin l'enregistre, **Then** il est refusé (400) sur le champ ; un plafond vide signifie « aucun plafond » (C3).
4. **Given** les données de démo, **When** on ouvre la page Politique, **Then** on voit les politiques de départ de C10.

---

### User Story 2 - Être prévenu dès la saisie (Priority: P1)

En remplissant le formulaire, le salarié voit immédiatement si son montant dépasse le plafond et si la catégorie exige un justificatif, sans être empêché d'enregistrer ni de soumettre.

**Why this priority**: Prévenir avant la soumission évite des rejets inutiles.

**Independent Test**: Saisir un montant au-dessus, puis égal au plafond, dans une catégorie plafonnée.

**Critères couverts**: C4, C5, C8

**Acceptance Scenarios**:

1. **Given** le formulaire de note, **When** le salarié saisit un montant supérieur au plafond de la catégorie, **Then** une alerte « hors politique » s'affiche immédiatement, sans bloquer l'enregistrement ni la soumission (C4).
2. **Given** un montant égal au plafond, **When** il est saisi, **Then** la note est conforme (C5).
3. **Given** une catégorie qui exige un justificatif, **When** le salarié la choisit, **Then** le formulaire l'indique (C8).

---

### User Story 3 - Voir le dépassement au moment de décider (Priority: P2)

Le propriétaire et son responsable voient, dans les listes et sur le détail, qu'une note est hors politique et de combien ; une note validée garde le signalement qu'elle avait à sa validation.

**Why this priority**: Aide la décision du manager, une fois la politique réglée et la saisie signalée.

**Independent Test**: Baisser un plafond en admin, puis voir en manager les notes qui le dépassent.

**Critères couverts**: C6, C7, C9

**Acceptance Scenarios**:

1. **Given** une note qui dépasse le plafond de sa catégorie, **When** son propriétaire ou son responsable la voit dans une liste ou sur le détail, **Then** elle est signalée « hors politique » avec la raison (plafond dépassé, et de combien) (C6).
2. **Given** une note, **When** son signalement est affiché, **Then** il a été calculé par le serveur, indépendamment de l'alerte du formulaire (C7).
3. **Given** une note non validée, **When** la politique change, **Then** son signalement suit la politique en vigueur ; une note validée garde celui de sa validation (C9).

---

### Edge Cases

- Plafond de 0 ou négatif : refusé (400) ; plafond de 10 000,00 € : accepté ; catégorie sans plafond : aucune alerte.
- Catégorie inconnue : 404.
- Plafond abaissé après coup : les notes non validées sont signalées selon le nouveau plafond (C9), les notes validées ne changent pas.

## Requirements *(mandatory)*

### Functional Requirements

Les identifiants **C1…C10** sont conservés tels quels : les tests et le code y font référence (`testC4…`, « spec 004 (C9) »).

- **C1** QUAND l'admin ouvre la page Politique, LE SYSTÈME DOIT afficher pour chaque catégorie son plafond (ou « aucun ») et si un justificatif est obligatoire.
- **C2** QUAND l'admin enregistre le plafond et l'obligation de justificatif d'une catégorie, LE SYSTÈME DOIT les appliquer à tous les contrôles suivants.
- **C3** SI le plafond saisi n'est pas un montant strictement positif d'au plus 10 000,00 €, ALORS LE SYSTÈME DOIT le refuser (400) en le signalant sur le champ ; un plafond vide signifie « aucun plafond ».
- **C4** QUAND un salarié saisit dans le formulaire un montant supérieur au plafond de la catégorie choisie, LE SYSTÈME DOIT afficher une alerte « hors politique » immédiatement, sans bloquer l'enregistrement ni la soumission.
- **C5** LE SYSTÈME DOIT considérer un montant égal au plafond comme conforme.
- **C6** QUAND une note dépasse le plafond de sa catégorie, LE SYSTÈME DOIT la signaler « hors politique » avec la raison (plafond dépassé, et de combien) à son propriétaire et à son responsable, dans les listes et sur le détail.
- **C7** LE SYSTÈME DOIT calculer ce signalement côté serveur, indépendamment de l'alerte affichée par le formulaire.
- **C8** QUAND une catégorie exige un justificatif, LE SYSTÈME DOIT l'indiquer dans le formulaire de note.
- **C9** TANT QU'une note n'est pas validée, LE SYSTÈME DOIT calculer son signalement avec la politique en vigueur ; une note validée DOIT garder le signalement qu'elle avait au moment de sa validation.
- **C10** LE SYSTÈME DOIT fournir des politiques de départ dans les données de démo : repas 50 €, hébergement 150 € avec justificatif, transport 300 €, matériel 500 € avec justificatif, « autre » sans plafond.

### Autorisations

| Action | Qui peut | Sinon |
|---|---|---|
| Lire les politiques (avec les catégories) | connecté | 401 |
| Modifier la politique d'une catégorie | admin | 401 non connecté ; 403 manager ou salarié |
| Voir le signalement d'une note | ceux qui peuvent lire la note (propriétaire, son responsable) | selon les règles de 003 |

### Règles recalculées côté front

- Dépassement de plafond (montant > plafond de la catégorie) : pour l'alerte immédiate de C4 ; testé des deux côtés, le serveur fait foi (C7).

### Key Entities

- **Catégorie** (complétée) : plafond par note (optionnel, en centimes), justificatif obligatoire ou non.
- **Note de frais** (complétée) : plafond figé au moment de la validation.
- **Signalement** (calculé, non stocké) : hors politique ou non, dépassement en centimes, justificatif obligatoire.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Chaque critère C1 à C10 est couvert par au moins un test du back ou du front, et `make test` passe.
- **SC-002**: Le parcours e2e `politique` passe (`make test-e2e`).

## Assumptions

- **Hors périmètre** : plafonds par personne ou par équipe, plafonds cumulés (par mois), historique des politiques, refus automatique d'une note hors plafond, dépôt des justificatifs et refus de soumettre une note sans justificatif obligatoire (feature 005).
- **Écarts avec les docs** :
  - `docs/api.md` : `GET /categories` renvoie désormais la politique associée (prévu, reporté depuis 002) ; route de modification `PUT /policies/{categoryId}` à préciser au plan.
