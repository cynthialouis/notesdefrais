# Tasks: 006 — Tableau de bord

**Input**: Design documents from `/specs/006-tableau-de-bord/`

**Prerequisites**: plan.md, spec.md

**Organization**: tâches dans l'ordre du projet (back puis front, `docs/methode.md`) plutôt que par user story ; les critères couverts sont indiqués entre parenthèses. Toutes sont terminées.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Back

- [X] T001 `StatsPeriod` et requêtes d'agrégats (C3, C4, C8)
- [X] T002 `DashboardStats` (C1 à C5, C8)
- [X] T003 Routes `stats/me` et `stats/team`, vues (C1 à C5, autorisations)
- [X] T004 Export CSV (C6 à C9)
- [X] T005 Données de démo (C3, C4)

## Phase 2: Front

- [X] T006 Types, `statsApi.ts`
- [X] T007 Composant `ui/Bar`
- [X] T008 Tableau de bord : partie personnelle (C1, C2)
- [X] T009 Tableau de bord : partie équipe et export (C3, C4, C6, C9)
