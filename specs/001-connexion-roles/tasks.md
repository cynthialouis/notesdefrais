# Tasks: 001 — Connexion par rôle

**Input**: Design documents from `/specs/001-connexion-roles/`

**Prerequisites**: plan.md, spec.md

**Organization**: tâches dans l'ordre du projet (back puis front, `docs/methode.md`) plutôt que par user story ; les critères couverts sont indiqués entre parenthèses. Toutes sont terminées.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Back

- [X] T001 Entité `User`, migration, fixtures (C1, C9)
- [X] T002 Listener d'erreurs JSON (C5, C10, C11)
- [X] T003 Sécurité : provider, hiérarchie, authenticator, logout, access_control (C2, C3, C4, C5, C10, C11, C12)
- [X] T004 Routes `demo-users` et `me` (C1, C3, C9)

## Phase 2: Front

- [X] T005 Dépendances front, scripts `test` / `gen:api`, cible `make api-types`
- [X] T006 Client API et `ApiError` (C5)
- [X] T007 Store de session (C2, C3, C4, C12)
- [X] T008 Écrans : connexion, `RequireRole`, layout, menu, pages vides (C1, C2, C4, C6, C7, C8, C13)
