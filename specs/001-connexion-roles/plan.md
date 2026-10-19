# Implementation Plan: 001 — Connexion par rôle

**Branch**: `feat/001-connexion-roles` | **Date**: 2026-10-11 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/001-connexion-roles/spec.md`

## Summary

Connexion sans mot de passe à un compte de démo, session côté serveur, hiérarchie des rôles admin > manager > salarié appliquée par Symfony Security, pages du front protégées par rôle à partir des rôles renvoyés par l'API.

## Technical Context

**Language/Version**: PHP 8.4+ (Symfony 8.1) ; TypeScript strict (React 19, Vite)

**Primary Dependencies**: Symfony Security, Doctrine ORM, NelmioApiDocBundle ; React Router, Zustand, Zod, `openapi-typescript`

**Storage**: SQLite (Doctrine + Migrations)

**Testing**: PHPUnit avec `dama/doctrine-test-bundle` ; Vitest + Testing Library

**Target Platform**: navigateur + API HTTP sous `/api`

**Project Type**: application web (`backend/` + `frontend/`, sans code partagé)

## Constitution Check

Constitution Spec Kit pas encore rédigée : les règles de `CLAUDE.md` et de `docs/methode.md` s'appliquent (back puis front, tests du back écrits avant le code, types du front générés depuis OpenAPI).

## Project Structure

### Documentation (this feature)

```text
specs/001-connexion-roles/
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

- **Back** : entité `User` (id, nom, email unique, rôle en enum PHP, manager optionnel) + migration + fixtures (1 admin, 2 managers, 4 salariés). Sécurité Symfony : provider `entity`, `role_hierarchy` admin → manager → salarié, authenticator `DemoLoginAuthenticator` sur `POST /api/auth/login` (DTO `LoginInput` validé, 400 / 401 JSON, sert aussi d'entry point 401), logout sur `POST /api/auth/logout` (204), `access_control` (routes publiques : health, doc, login, demo-users ; reste de `/api` : `IS_AUTHENTICATED`). Listener d'exceptions pour le format `{ error: { code, message, fields? } }`. Routes `GET /api/auth/demo-users` et `GET /api/me` (rôles accessibles calculés par `RoleHierarchyInterface`), sorties typées pour OpenAPI.
- **Front** : `react-router`, `zustand`, `zod`, Vitest + Testing Library, `openapi-typescript` (types générés depuis `nelmio:apidoc:dump`). Client `/api` avec `ApiError`. Store de session en union discriminée (`loading` / `anonymous` / `authenticated`), `fetchMe` appelé une fois dans `main.tsx`. `LoginPage` (`useActionState`), `RequireRole`, layout avec menu filtré et déconnexion, pages vides protégées.
- **Tests du back** : `dama/doctrine-test-bundle` (rollback par test), base de test migrée par `make test`.
