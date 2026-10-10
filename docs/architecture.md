# Architecture

Un dépôt git, deux projets indépendants : un front React et une API Symfony.

```
frontend/            front React 19 (Vite, TypeScript), organisé par feature
  src/features/{auth,expenses,approvals,policies,dashboard}/
  vite.config.ts       proxy /api → API Symfony
backend/             API Symfony 8 (PHP 8.4+)
  src/
    Controller/          contrôleurs fins, un par ressource
    Dto/                 objets d'entrée validés (Validator)
    Entity/  Repository/ Doctrine
    Security/            authentification de démo, Voters
    Service/             règles métier (politique de dépenses…)
  config/packages/workflow.yaml   machine à états d'une note
  migrations/          Doctrine Migrations
  tests/               PHPUnit (WebTestCase, KernelTestCase)
specs/               une spec par feature
docs/                documentation du projet, docs/adr/ pour les décisions
```

Dans `frontend/src/features/*`, chaque feature regroupe ses composants, hooks, store et tests.

## Comment les morceaux s'assemblent

- **Deux processus en dev** : `symfony serve` dans `backend/` (port 8000), `npm run dev` dans `frontend/` (port 5173).
- **Proxy Vite** : le front appelle `/api/...`, Vite redirige vers `http://127.0.0.1:8000`. Pas de CORS, pas d'URL de serveur en dur.
- **Base de données** : SQLite via Doctrine, un fichier par environnement (`backend/var/data_dev.db`, `data_test.db`). Aucune installation.
- **Session** : Symfony Security avec la session PHP native (cookie httpOnly). Le mécanisme exact de connexion de démo est tranché dans la spec 001.

## Contrat front / back

- Le **back est la source de vérité** : chaque entrée est validée par Validator, chaque accès par un Voter, chaque transition de statut par le Workflow. On ne fait jamais confiance au client.
- **OpenAPI** : NelmioApiDocBundle publie la spec sur `/api/doc.json`, à partir des routes, des DTO et de leurs contraintes. Le front en **génère ses types TypeScript** (outil choisi à la première feature, par exemple `openapi-typescript`). Un changement de contrat côté back casse donc le typecheck du front.
- **Zod côté front** valide les formulaires pour l'UX (erreur immédiate). Ses contraintes reprennent celles du DTO correspondant.
- **Règles métier dupliquées volontairement** : quand le front doit recalculer une règle pour l'affichage (alerte « hors politique »), il en a sa propre version, testée. Celle du back fait foi. Chaque duplication est listée dans la spec de la feature.

## Contraintes

- Le front et le back ne partagent aucun code : ils ne communiquent que par HTTP via `/api`.
- Données serveur dans Zustand côté front : choix de périmètre. TanStack Query serait l'outil idiomatique en production (voir `docs/adr/`).

## Versions

Symfony 8.1, PHP 8.4+, React 19, Vite 8, TypeScript ~6.0. Zod, Zustand, React Router et Vitest évoluent vite (par exemple, Zod 3 → 4 : `z.string().email()` devient `z.email()`). Vérifier la syntaxe dans la doc de la version installée (`composer.json`, `package.json`) plutôt que de la deviner.
