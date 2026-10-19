# Tasks: 005 — Justificatifs

**Input**: Design documents from `/specs/005-justificatifs/`

**Prerequisites**: plan.md, spec.md

**Organization**: tâches dans l'ordre du projet (back puis front, `docs/methode.md`) plutôt que par user story ; les critères couverts sont indiqués entre parenthèses. Toutes sont terminées.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Back

- [X] T001 Modèle : `Receipt`, migration (C1, C12)
- [X] T002 `ReceiptStorage` (C11, C12)
- [X] T003 `ExpenseManager` et `PolicyChecker` : dépôt, remplacement, retrait, refus de soumission (C6 à C9, C11)
- [X] T004 Routes et vues : dépôt, ouverture, retrait, `ExpenseView.receipt` (C1 à C3, C5 à C7)
- [X] T005 Données de démo (C9, C10)

## Phase 2: Front

- [X] T006 Types, store, `apiFetch` multipart
- [X] T007 `receiptValidation.ts` (C4)
- [X] T008 Carte Justificatif sur le détail (C4 à C6)
- [X] T009 Soumission désactivée et expliquée (C10)
- [X] T010 Ouverture du détail après la création (C13)
