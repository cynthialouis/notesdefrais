# Feature Specification: [NNN] — [NOM DE LA FEATURE]

**Feature Branch**: `feat/[NNN-nom-court]`

**Created**: [DATE]

**Status**: Brouillon

**Input**: [Besoin : qui, quoi, pourquoi, sans aucune technologie]

<!--
  Format du projet (docs/methode.md) : titres de Spec Kit en anglais, contenu en français.
  Les critères gardent les identifiants C1, C2… (et non FR-001) : les tests s'y réfèrent par leur nom (testC4…).
  Une page de spec utile : pas de remplissage, supprimer les sections facultatives inutiles.
-->

## Clarifications

<!--
  Questions ouvertes, chacune avec une proposition par défaut motivée, tant que l'humain ne les a pas tranchées.
  Une fois tranchées : `### Session AAAA-MM-JJ` puis `- Q: … → A: …`, et la réponse reportée dans les critères ou les hypothèses.
-->

- [NEEDS CLARIFICATION: question] — proposition : [réponse proposée, et pourquoi]

## User Scenarios & Testing *(mandatory)*

### User Story 1 - [Titre court] (Priority: P1)

[Parcours décrit simplement, du point de vue d'un rôle : salarié, manager ou admin]

**Why this priority**: [Valeur et raison de la priorité]

**Independent Test**: [Comment le vérifier seul, avec quel compte de démo]

**Critères couverts**: [C1, C2…]

**Acceptance Scenarios**:

1. **Given** [état initial], **When** [action], **Then** [résultat attendu] (C1).

---

### Edge Cases

<!-- Vide, zéro, valeur maximale, action déjà faite (409), ressource d'un autre. -->

- [Cas limite et comportement attendu]

## Requirements *(mandatory)*

### Functional Requirements

<!--
  Format EARS, chacun vérifiable par un seul test :
  QUAND… LE SYSTÈME DOIT… / SI… ALORS LE SYSTÈME DOIT… / TANT QUE… LE SYSTÈME DOIT…
-->

- **C1** QUAND [déclencheur], LE SYSTÈME DOIT [comportement].
- **C2** SI [condition d'erreur], ALORS LE SYSTÈME DOIT [refus et code attendu].

### Autorisations

| Action | Qui peut | Sinon |
|---|---|---|
| [Action] | [rôle ou propriétaire] | [401 non connecté ; 403 interdit ; 404 si la ressource d'un autre ne doit pas être révélée] |

### Règles recalculées côté front

<!-- Chaque règle métier que le front recalcule pour l'affichage (duplication volontaire, testée des deux côtés), ou « Aucune ». -->

- [Règle] : [pourquoi le front la recalcule] ; testée des deux côtés, le serveur fait foi.

### Key Entities *(si la feature touche aux données)*

- **[Entité]** : [ce qu'elle représente, attributs clés, sans implémentation]

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Chaque critère C1 à Cn est couvert par au moins un test du back ou du front, et `make test` passe.
- **SC-002**: [Parcours e2e de `frontend/e2e/` qui passe avec `make test-e2e`, si la feature traverse plusieurs rôles]

## Assumptions

- **Hors périmètre** : [ce qu'on ne fait pas, explicitement]
- **Écarts avec les docs** : [écart avec `docs/api.md` ou `docs/modele-donnees.md`, à trancher ; ou « aucun »]
