# Implementation Plan: 003 — Valider / rejeter

**Branch**: `feat/003-valider-rejeter` | **Date**: 2026-10-12 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/003-valider-rejeter/spec.md`

## Summary

Décision du responsable direct sur les notes soumises (validation, rejet motivé) par des transitions du Workflow, resoumission d'une note rejetée, historique des actions ; liste à traiter et détail partagé côté front.

## Technical Context

**Language/Version**: PHP 8.4+ (Symfony 8.1) ; TypeScript strict (React 19, Vite)

**Primary Dependencies**: Symfony Workflow, Validator, Security (Voter), Doctrine ORM ; Zustand, Zod

**Storage**: SQLite (Doctrine + Migrations)

**Testing**: PHPUnit ; Vitest + Testing Library

**Target Platform**: navigateur + API HTTP sous `/api`

**Project Type**: application web (`backend/` + `frontend/`, sans code partagé)

## Constitution Check

Constitution Spec Kit pas encore rédigée : les règles de `CLAUDE.md` et de `docs/methode.md` s'appliquent (back puis front, tests du back écrits avant le code, types du front générés depuis OpenAPI).

## Project Structure

### Documentation (this feature)

```text
specs/003-valider-rejeter/
├── spec.md
├── plan.md
└── tasks.md
```

### Source Code (repository root)

```text
backend/    # API Symfony
frontend/   # React + TypeScript
```

## Approche

- **Back** : `Expense` gagne `decidedAt`, `decidedBy`, `rejectionReason` ; `ExpenseStatus::isEditable()` inclut `Rejected` ; entité `ExpenseEvent` (type created / submitted / approved / rejected, auteur, commentaire, date). Workflow : `submit` depuis `draft` ou `rejected`, `approve`, `reject` ; listeners pour la décision, l'effacement du motif à la resoumission et l'écriture de l'historique. `ExpenseVoter` : `EXPENSE_VIEW` (propriétaire, ou son responsable hors brouillon), `EXPENSE_DECIDE` (responsable). `ExpenseManager::approve/reject`, refus de soumettre sans responsable. Routes `GET /api/team/expenses`, `POST /api/expenses/{id}/approve`, `POST /api/expenses/{id}/reject` (`RejectionInput`), `GET /api/expenses/{id}/events`. `ExpenseView` construite pour un lecteur (`owner`, décision, motif, `editable`, `decidable`). Démo : managers rattachés à l'admin.
- **Front** : store étendu (liste à traiter, `approve`, `reject`, historique) ; composant `Textarea` ; page Équipe ; détail partagé `/notes/:id` et `/equipe/:id` avec carte Décision, motif d'un rejet, carte Historique.
