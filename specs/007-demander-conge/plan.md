# Implementation Plan: 007 — Demander un congé

**Branch**: `feat/007-demander-conge` | **Date**: 2026-10-19 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/007-demander-conge/spec.md`

## Summary

Nouvelle entité `LeaveRequest` avec son propre Workflow (`pending` → `accepted` / `refused` /
`withdrawn` / `cancelled`, `accepted` → `cancelled`), calqué sur le cycle de décision des notes (003) : le
responsable direct accepte ou refuse avec motif, le demandeur retire, l'admin annule une demande en attente ou un congé accepté. Quatre listes aux
règles de visibilité distinctes (congés acceptés en cours ou à venir de tous, ses demandes, demandes
à traiter du manager, demandes en attente de tous pour l'admin).
Côté front, une page `/conges` pour tous les rôles. Aucune nouvelle dépendance.

## Technical Context

**Language/Version**: PHP 8.4+ (Symfony 8.1) ; TypeScript strict (React 19, Vite)

**Primary Dependencies**: Symfony Workflow, Validator, Security (Voter), Doctrine ORM, Clock,
NelmioApiDocBundle ; React Router, Zustand, Zod, Tailwind CSS 4 — tous déjà installés

**Storage**: SQLite (Doctrine + Migrations), nouvelle table `leave_request`

**Testing**: PHPUnit (`WebTestCase` via `tests/Support/ApiTestCase`, écrits avant le code par
`testeur-spec`) ; Vitest + Testing Library ; Playwright (`frontend/e2e/conges.spec.ts`)

**Target Platform**: navigateur + API HTTP sous `/api`

**Project Type**: application web (`backend/` + `frontend/`, sans code partagé)

**Performance Goals**: aucun objectif propre (POC, 7 comptes de démo)

**Constraints**: dates en journées entières, « aujourd'hui » en Europe/Paris côté serveur ;
instants stockés en UTC

**Scale/Scope**: 18 critères, 8 routes, 1 page

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principe | Vérification | État |
|---|---|---|
| I. Spec validée avant tout code | Spec 007 validée et commitée (2d7c477) ; identifiants C1…C18 repris dans le contrat ; C13, C14, C15, C17 précisés et C18 ajouté à la relecture du plan | ✅ |
| II. Back source de vérité | Validator (`LeaveRequestInput`, `NotInPast`), Voter (`LeaveRequestVoter`), Workflow (`leave_request`) ; le front ne duplique que les deux règles listées dans la spec (dates, longueur du motif) | ✅ |
| III. Tests du back d'abord | Tâches de tests `testeur-spec` avant toute implémentation ; parcours e2e prévu | ✅ |
| IV. Contrat OpenAPI unique | Vues annotées, `ApiDocTest` étendu, `make api-types` avant le front | ✅ |
| V. Centimes et typage strict | Pas de montant ; union discriminée Zod sur le statut, aucun `any` | ✅ |
| VI. Sobriété des dépendances | Aucune ajoutée : `Callback` plutôt qu'`Expression` (D4), pas de `symfony/lock` (D6), date native (D10) | ✅ |

Re-check après la phase 1 : inchangé. L'absence de verrou (D6) a été validée le 19/10.

## Project Structure

### Documentation (this feature)

```text
specs/007-demander-conge/
├── spec.md
├── plan.md              # ce fichier
├── research.md          # décisions D1 à D10
├── data-model.md        # entité, Workflow, DTO, vue, démo
├── quickstart.md        # vérification de bout en bout
├── contracts/
│   └── api.md           # routes, codes, corps
└── tasks.md             # /speckit-tasks (pas encore créé)
```

### Source Code (repository root)

```text
backend/
├── config/packages/workflow.yaml          # + workflow leave_request
├── migrations/                             # + table leave_request
├── src/
│   ├── Controller/LeaveController.php      # 8 routes
│   ├── DataFixtures/AppFixtures.php        # + congés de démo
│   ├── Dto/LeaveRequestInput.php, LeaveRequestView.php ; RejectionInput.php (docblock)
│   ├── Entity/LeaveRequest.php
│   ├── Enum/LeaveStatus.php
│   ├── Repository/LeaveRequestRepository.php
│   ├── Security/Voter/LeaveRequestVoter.php
│   ├── Service/LeaveManager.php
│   ├── Validator/NotInPast.php, NotInPastValidator.php
│   └── Workflow/LeaveDecidedListener.php, LeaveCancelledListener.php
└── tests/
    ├── Controller/Leave/                   # un fichier par route
    ├── Controller/ApiDocTest.php           # + LeaveRequestView
    └── DataFixtures/                       # + données de démo des congés

frontend/
├── e2e/conges.spec.ts
└── src/
    ├── api/schema.d.ts, openapi.json       # régénérés
    ├── app/AppRoutes.tsx, AppLayout.tsx    # route /conges, lien « Congés »
    └── features/leaves/
        ├── leavesStore.ts (+ test)
        ├── leaveFormValidation.ts (+ test)
        ├── LeaveStatusBadge.tsx
        └── LeavesPage.tsx (+ test)
```

**Structure Decision**: structure web existante, une feature front de plus (`features/leaves/`),
backend organisé par type de classe comme le reste de `src/`.

## Approche

Ordre `backend/` puis `frontend/`, les tests du back écrits d'abord à partir de la spec :

- **Back** : `LeaveStatus`, `LeaveRequest`, migration ; workflow `leave_request` et listeners (D1) ;
  `LeaveRequestInput` et `NotInPast` (D4) ; `LeaveRequestRepository` (chevauchement, quatre listes,
  D6, D8) ; `LeaveRequestVoter` (D7) ; `LeaveManager` (création avec l'ordre des refus de D5,
  accept / refuse / withdraw / cancel, 409 si `can()` est faux) ; `LeaveController` et
  `LeaveRequestView` (D9) ; données de démo.
- **Front** : types régénérés ; `leavesStore` ; `leaveFormValidation` ; page `/conges` et lien de
  menu (D10) ; parcours e2e : un salarié demande, son manager accepte, un salarié d'une autre équipe
  voit le congé, l'admin l'annule (SC-002).
- **Docs** : `docs/modele-donnees.md` (entité, cycle de vie), `docs/api.md` (8 routes),
  `docs/produit.md` (feature 007, écran `/conges`), `CLAUDE.md` (état actuel).

## Complexity Tracking

Aucune violation de la constitution à justifier.
