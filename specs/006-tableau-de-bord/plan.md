# Implementation Plan: 006 — Tableau de bord

**Branch**: `feat/006-tableau-de-bord` | **Date**: 2026-10-13 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/006-tableau-de-bord/spec.md`

## Summary

Totaux calculés par le serveur en centimes : par état pour chacun, et pour les managers, notes validées de leurs subordonnés directs par catégorie et par mois sur les 12 derniers mois civils ; export CSV pour un tableur français ; barres en CSS côté front.

## Technical Context

**Language/Version**: PHP 8.4+ (Symfony 8.1) ; TypeScript strict (React 19, Vite)

**Primary Dependencies**: Doctrine ORM, Symfony Security, Clock ; Zod

**Storage**: SQLite (Doctrine + Migrations)

**Testing**: PHPUnit ; Vitest + Testing Library

**Target Platform**: navigateur + API HTTP sous `/api`

**Project Type**: application web (`backend/` + `frontend/`, sans code partagé)

## Constitution Check

Constitution Spec Kit pas encore rédigée : les règles de `CLAUDE.md` et de `docs/methode.md` s'appliquent (back puis front, tests du back écrits avant le code, types du front générés depuis OpenAPI).

## Project Structure

### Documentation (this feature)

```text
specs/006-tableau-de-bord/
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

- **Back** : `StatsPeriod` (12 mois civils depuis l'horloge, Europe/Paris) ; `ExpenseRepository` : totaux par état d'un propriétaire, notes validées des subordonnés directs sur la période ; service `DashboardStats` (états, mois et catégories toujours présents, à 0 si besoin). Routes `GET /api/stats/me` (connecté), `GET /api/stats/team` et `GET /api/exports/team-expenses.csv` (manager et plus, 403 sinon). Service `TeamExpensesCsv` (BOM, `;`, échappement, neutralisation des formules). Démo : notes validées sur plusieurs mois, une hors période. Aucune dépendance.
- **Front** : `statsApi.ts` (Zod, contrôle contre le contrat) ; composant `ui/Bar` (barre décorative, valeur en texte) ; tableau de bord : cartes par état pour tous, partie équipe pour les managers (par catégorie, par mois, export).
