# Notes de frais

Gestion de notes de frais pour trois rôles (salarié, manager, admin). 
Un dépôt, deux projets qui ne partagent aucun code et ne communiquent que par HTTP via `/api` :
- `frontend/` : React 19 + TypeScript (Vite)
- `backend/` : API Symfony 8 (PHP 8.4+), Doctrine + SQLite. Voir aussi `backend/CLAUDE.md`.

État actuel : 
- squelettes en place
- route `GET /api/health` branchée de bout en bout
- Aucune feature métier.

## Documentation

À lire selon la tâche, sans la recopier ici :
- [docs/produit.md](docs/produit.md) : features, hors périmètre, technos, écrans
- [docs/architecture.md](docs/architecture.md) : structure, proxy, contrat OpenAPI, contraintes
- [docs/modele-donnees.md](docs/modele-donnees.md) : entités, cycle de vie d'une note, DTO, types front
- [docs/api.md](docs/api.md) : routes, autorisations, conventions d'erreur, tests
- [docs/methode.md](docs/methode.md) : cycle d'une feature, format des specs, grilles de relecture

## Stack

- **Front** : React 19, TypeScript strict, Vite 8, oxlint. Prévus : Zustand, Zod, React Router, Tailwind, Vitest + Testing Library, génération des types depuis OpenAPI.
- **Back** : Symfony 8.1, Doctrine ORM + Migrations (SQLite), Validator, Serializer, Security, Workflow, NelmioApiDocBundle, PHPUnit.

## Commandes

Depuis la racine (`Makefile`) : 
- `make install`
- `make dev` (API en arrière-plan + front)
- `make stop` (arrête l'API)
- `make test`
- `make` (aide)

Dans chaque projet :

| | `frontend/` | `backend/` |
|---|---|---|
| Lancer | `npm run dev` (port 5173, proxy `/api` → 8000) | `symfony serve -d` (port 8000) |
| Tests | à venir (`npm run test`) | `php bin/phpunit` |
| Types / lint | `npx tsc -b` · `npm run lint` | `php bin/console lint:container` |
| Build | `npm run build` | — |
| Base | — | `php bin/console make:migration` · `php bin/console doctrine:migrations:migrate` |

Spec OpenAPI : `GET /api/doc.json`.

## Méthode (spec-driven)

- Toute feature commence par une spec `specs/NNN-nom.md`, validée par moi avant tout code.
- Ordre d'implémentation : `backend/` puis `frontend/`.
- Les tests du back sont écrits à partir de la spec, avant l'implémentation.
- Une tâche du plan à la fois, uniquement pour faire passer les tests existants. Ne jamais modifier un test pour le faire passer : s'il paraît faux, s'arrêter et expliquer.
- Si le code s'écarte de la spec, le signaler : on corrige l'un ou l'autre consciemment.

## Règles transverses

- Le back est la source de vérité : validation (Validator), autorisation (Voter) et transitions (Workflow) y sont toujours faites, même si le front les fait aussi.
- Une règle métier recalculée côté front (pour l'affichage) est une duplication volontaire : listée dans la spec, testée des deux côtés.
- Types de l'API côté front générés depuis OpenAPI, jamais réécrits à la main.
- Montants en centimes entiers, des deux côtés.
- TypeScript : jamais de `any` (utiliser `unknown` + validation Zod), `import type` pour les types, pas d'`enum`, de `namespace` ni de parameter properties (`erasableSyntaxOnly`).
- React : pas de `useEffect` pour une valeur dérivée, calcul direct pendant le rendu.
- Symfony : suivre `backend/AGENTS.md` (attributs, autowiring, `#[MapRequestPayload]`, recettes Flex plutôt que configuration à la main).
- Ne pas ajouter de dépendance sans le signaler et le justifier.
- Si la syntaxe ou le comportement d'une API n'est pas certain : ne pas deviner. Utiliser le subagent `verificateur-docs` pour les librairies du front, Nelmio et la configuration de Claude Code (hooks, permissions, skills, subagents). Pour le cœur de Symfony, interroger d'abord le projet avec `bin/console` (voir `backend/AGENTS.md`).
- Secrets : jamais committés. Back : `.env` ne contient que des valeurs par défaut, secrets dans `.env.local` ou `secrets:set`.
- Commits conventionnels (`feat:`, `fix:`, `test:`, `refactor:`, `docs:`, `chore:`, `chore(harness):`), petits et atomiques.
- Toute la documentation (README, commentaires, docs, ce fichier) est rédigée en français.

## Façon de travailler avec moi

- Toujours lire les fichiers concernés avant de proposer une modification.
- Proposer un plan avant toute modification qui touche plus de deux fichiers.
- Ne jamais modifier de fichier si je demande une analyse, une explication ou une review.
- Après chaque modification, vérifier le projet touché : front → `npx tsc -b` et `npm run lint` ; back → `php bin/phpunit` et `php bin/console lint:container`.
