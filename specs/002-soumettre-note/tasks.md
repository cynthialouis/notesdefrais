# Tasks: 002 — Soumettre une note

**Input**: Design documents from `/specs/002-soumettre-note/`

**Prerequisites**: plan.md, spec.md

**Organization**: tâches dans l'ordre du projet (back puis front, `docs/methode.md`) plutôt que par user story ; les critères couverts sont indiqués entre parenthèses. Toutes sont terminées.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Back

- [X] T001 `Category`, migration avec les 5 catégories, `GET /api/categories` (C10)
- [X] T002 `Expense`, `ExpenseStatus`, migration, fixtures de démo (C1, C2)
- [X] T003 Workflow `expense` et date de soumission (C6, C7)
- [X] T004 `ExpenseInput`, `ExpenseListQuery`, contraintes `NotInFuture` et `ExistingCategory` (C3)
- [X] T005 `ExpenseVoter` (C13, autorisations 404)
- [X] T006 `ExpenseController` et `ExpenseManager` : liste, création, lecture, modification, soumission, suppression (C1, C2, C5 à C9, C11 à C13)

## Phase 2: Front

- [X] T007 Types régénérés, `money.ts` (C2)
- [X] T008 Store `expensesStore` (C1, C5, C6, C8, C11)
- [X] T009 Écrans : liste et filtre, formulaire, soumission, suppression (C4, C8, C9, C10, C11)
