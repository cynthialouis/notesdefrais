# Tasks: 003 — Valider / rejeter

**Input**: Design documents from `/specs/003-valider-rejeter/`

**Prerequisites**: plan.md, spec.md

**Organization**: tâches dans l'ordre du projet (back puis front, `docs/methode.md`) plutôt que par user story ; les critères couverts sont indiqués entre parenthèses. Toutes sont terminées.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Back

- [X] T001 Modèle : champs de décision, `ExpenseEvent`, `isEditable()` (C8, C9, C10)
- [X] T002 Workflow : transitions et listeners, historique (C3, C4, C8, C10)
- [X] T003 `ExpenseVoter` : lecture et décision (C12, autorisations)
- [X] T004 `ExpenseManager` et `RejectionInput` : décisions, refus sans responsable (C3 à C6, C13)
- [X] T005 Routes et vues : liste à traiter, décisions, historique, `ExpenseView` par lecteur (C1, C2, C7, C11, C12)
- [X] T006 Données de démo et `AppFixturesTest` (C13)

## Phase 2: Front

- [X] T007 Types régénérés et store (C1, C3, C4, C11)
- [X] T008 Écrans : Équipe, décision, motif, historique (C1, C2, C5, C7, C8, C11)
