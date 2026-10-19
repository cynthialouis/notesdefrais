# Implementation Plan: 005 — Justificatifs

**Branch**: `feat/005-justificatifs` | **Date**: 2026-10-13 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/005-justificatifs/spec.md`

## Summary

Un justificatif (JPEG, PNG ou PDF, 5 Mo au plus, type détecté d'après le contenu) par note, stocké hors `public/` ; dépôt, remplacement et retrait tant que la note est modifiable, soumission refusée si le justificatif obligatoire manque, ouverture dans un nouvel onglet.

## Technical Context

**Language/Version**: PHP 8.4+ (Symfony 8.1) ; TypeScript strict (React 19, Vite)

**Primary Dependencies**: Symfony Validator, Security, Workflow, `symfony/mime` (nouvelle dépendance), Doctrine ORM ; Zustand, Zod

**Storage**: SQLite (Doctrine + Migrations)

**Testing**: PHPUnit ; Vitest + Testing Library

**Target Platform**: navigateur + API HTTP sous `/api`

**Project Type**: application web (`backend/` + `frontend/`, sans code partagé)

## Constitution Check

Constitution Spec Kit pas encore rédigée : les règles de `CLAUDE.md` et de `docs/methode.md` s'appliquent (back puis front, tests du back écrits avant le code, types du front générés depuis OpenAPI).

## Project Structure

### Documentation (this feature)

```text
specs/005-justificatifs/
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

- **Back** : entité `Receipt` (un-à-un avec `Expense`, nom d'origine, type, taille, nom de stockage généré) ; service `ReceiptStorage` (`var/receipts/<env>`, hors `public/`) ; `PolicyCheck.receiptMissing` (C9) ; `ExpenseManager` : dépôt, remplacement, retrait (409 si non modifiable), refus de soumission sans justificatif obligatoire (409, après les refus d'état et de responsable), effacement du fichier à la suppression. Routes `POST`, `GET`, `DELETE /api/expenses/{id}/receipt` (dépôt multipart validé par `MapUploadedFile` et `Assert\File`, 400 ; ouverture en `inline` avec `nosniff`). `ExpenseView.receipt`. Nouvelle dépendance : `symfony/mime` (type détecté d'après le contenu, C2). Démo : justificatifs générés, un brouillon d'hébergement sans justificatif.
- **Front** : store (`receipt`, `receiptMissing`, `uploadReceipt`, `removeReceipt`) ; `apiFetch` accepte un `FormData` ; `receiptValidation.ts` (seule version front du contrôle de type et de taille) ; carte Justificatif sur le détail ; bouton de soumission désactivé et expliqué ; ouverture du détail après la création.
