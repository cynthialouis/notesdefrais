# Tasks: 004 — Politique de dépenses

**Input**: Design documents from `/specs/004-politique-depenses/`

**Prerequisites**: plan.md, spec.md

**Organization**: tâches dans l'ordre du projet (back puis front, `docs/methode.md`) plutôt que par user story ; les critères couverts sont indiqués entre parenthèses. Toutes sont terminées.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Back

- [X] T001 Modèle : politique sur `Category`, plafond figé sur `Expense` (C2, C9)
- [X] T002 `PolicyChecker` et figement à la validation (C5, C7, C9)
- [X] T003 Vues : `policy` dans `ExpenseView`, politique dans `CategoryView` (C6, C8)
- [X] T004 `PUT /api/policies/{slug}` et `PolicyInput` (C2, C3)
- [X] T005 Données de démo (C10)

## Phase 2: Front

- [X] T006 Types, `checkPolicy.ts`, store (C4, C5)
- [X] T007 Formulaire : alerte en direct, justificatif obligatoire (C4, C8)
- [X] T008 Signalement dans les listes et sur le détail (C6)
- [X] T009 Page Politique (C1, C2, C3)
