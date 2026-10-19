# Implementation Plan: 004 — Politique de dépenses

**Branch**: `feat/004-politique-depenses` | **Date**: 2026-10-12 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/004-politique-depenses/spec.md`

## Summary

Politique de dépenses portée par chaque catégorie (plafond par note, justificatif obligatoire), réglée par l'admin ; signalement « hors politique » calculé par le serveur et figé à la validation, alerte immédiate dans le formulaire.

## Technical Context

**Language/Version**: PHP 8.4+ (Symfony 8.1) ; TypeScript strict (React 19, Vite)

**Primary Dependencies**: Symfony Validator, Security, Workflow, Doctrine ORM ; Zustand, Zod

**Storage**: SQLite (Doctrine + Migrations)

**Testing**: PHPUnit ; Vitest + Testing Library

**Target Platform**: navigateur + API HTTP sous `/api`

**Project Type**: application web (`backend/` + `frontend/`, sans code partagé)

## Constitution Check

Constitution Spec Kit pas encore rédigée : les règles de `CLAUDE.md` et de `docs/methode.md` s'appliquent (back puis front, tests du back écrits avant le code, types du front générés depuis OpenAPI).

## Project Structure

### Documentation (this feature)

```text
specs/004-politique-depenses/
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

- **Back** : la politique est portée par `Category` (`maxAmountCents` nullable, `receiptRequired`) ; `Expense.limitAtApprovalCents` fige le plafond à la validation. Service `PolicyChecker` (plafond applicable, écart, justificatif obligatoire ; égal = conforme). `ExpenseView.policy` et `CategoryView` avec la politique. `PUT /api/policies/{slug}` (admin, `PolicyInput`). Démo : politiques de C10 et notes hors plafond.
- **Front** : `checkPolicy.ts` (seule version front du dépassement) ; alerte en direct et aide « justificatif obligatoire » dans le formulaire ; `PolicyBadge` dans les listes, alerte sur le détail ; page Politique éditable par ligne.
