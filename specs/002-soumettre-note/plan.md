# Implementation Plan: 002 — Soumettre une note

**Branch**: `feat/002-soumettre-note` | **Date**: 2026-10-11 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/002-soumettre-note/spec.md`

## Summary

Notes de frais en brouillon créées, modifiées, soumises et supprimées par leur propriétaire ; montants en centimes entiers, transition de soumission portée par le Workflow Symfony, liste filtrable par état côté front.

## Technical Context

**Language/Version**: PHP 8.4+ (Symfony 8.1) ; TypeScript strict (React 19, Vite)

**Primary Dependencies**: Symfony Workflow, Validator, Security (Voter), Doctrine ORM ; Zustand, Zod

**Storage**: SQLite (Doctrine + Migrations)

**Testing**: PHPUnit ; Vitest + Testing Library

**Target Platform**: navigateur + API HTTP sous `/api`

**Project Type**: application web (`backend/` + `frontend/`, sans code partagé)

## Constitution Check

Constitution Spec Kit pas encore rédigée : les règles de `CLAUDE.md` et de `docs/methode.md` s'appliquent (back puis front, tests du back écrits avant le code, montants en centimes, types du front générés depuis OpenAPI).

## Project Structure

### Documentation (this feature)

```text
specs/002-soumettre-note/
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

- **Back** : entité `Category` (5 catégories insérées par la migration, données de référence) et `GET /api/categories`. Entité `Expense` (propriétaire, catégorie, libellé, `amountCents`, date, `status` en enum `ExpenseStatus`, `createdAt`, `submittedAt`) ; `ExpenseStatus::isEditable()` dit seul quels états se modifient. Workflow `expense` (state machine, transition `submit`, date de soumission posée par un listener via `ClockInterface`). DTO `ExpenseInput` (contraintes Validator, `NotInFuture` en Europe/Paris, `ExistingCategory`) et `ExpenseListQuery`, reçus avec `validationFailedStatusCode: 400`. `ExpenseVoter` (`EXPENSE_OWNER`) avec `#[IsGranted(..., statusCode: 404)]`. `ExpenseController` fin + service `ExpenseManager` : liste filtrable triée (date puis id décroissants), création 201, lecture, `PATCH` (tous les champs), soumission, suppression 204 ; 409 hors brouillon. Sortie `ExpenseView`.
- **Front** : types régénérés ; `money.ts` (`parseEurosToCents`, seul point de conversion, et `formatCents` avec `Intl.NumberFormat`) ; store Zustand `expensesStore` (cache des notes, réponses validées en union discriminée Zod) ; pages `/notes` (filtre local, liste calculée au rendu, badge `switch` exhaustif), `/notes/nouvelle` et `/notes/:id` (formulaire commun `useActionState` + `safeParse`, erreurs par champ, soumission, suppression confirmée).
