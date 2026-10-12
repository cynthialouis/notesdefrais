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

## Interface (`frontend/src/ui/`)

Tailwind CSS 4, configuré dans `frontend/src/index.css` (`@import "tailwindcss"`, `@theme`) : la couleur d'accent est l'alias `brand-*` (indigo), seul endroit à changer pour l'identité visuelle. Aucun autre fichier CSS.

`src/ui/` contient les composants de présentation réutilisables, sans logique métier :

| Composant | Rôle |
|---|---|
| `Button`, `ButtonLink` | Boutons `primary` / `secondary` / `danger`, état `pending` ; lien avec l'apparence d'un bouton |
| `Card`, `PageHeader` | Conteneur blanc avec en-tête optionnel ; titre de page, description, actions |
| `Field`, `Input`, `Select` | Champ de formulaire : `Field` génère l'`id` et les liaisons `aria-describedby` / `aria-invalid` (aide, erreur) |
| `Badge`, `Alert`, `EmptyState`, `LoadingState` | Statut (le texte porte l'information), messages (`role="alert"` / `"status"`), états vides et de chargement |
| `Table`, `Th`, `Td` | Tableau de données, défilement horizontal sur mobile, montants alignés en chiffres tabulaires |

Les styles de variantes passent par `cx()` et `buttonStyles.ts`, sans dépendance supplémentaire.

## Comment les morceaux s'assemblent

- **Deux processus en dev** : `symfony serve` dans `backend/` (port 8000), `npm run dev` dans `frontend/` (port 5173).
- **Proxy Vite** : le front appelle `/api/...`, Vite redirige vers `http://127.0.0.1:8000`. Pas de CORS, pas d'URL de serveur en dur.
- **Base de données** : SQLite via Doctrine, un fichier par environnement (`backend/var/data_dev.db`, `data_test.db`). Aucune installation.
- **Session** : Symfony Security avec la session PHP native (cookie `httponly`, `samesite=lax`). Connexion de démo par l'authenticator `DemoLoginAuthenticator` (spec 001). Côté front, la session vit dans un store Zustand (`features/auth/sessionStore.ts`), lue une fois au démarrage dans `main.tsx`.

## Contrat front / back

- Le **back est la source de vérité** : chaque entrée est validée par Validator, chaque accès par un Voter, chaque transition de statut par le Workflow. On ne fait jamais confiance au client.
- **OpenAPI** : NelmioApiDocBundle publie la spec sur `/api/doc.json`, à partir des routes, des DTO et de leurs contraintes. Le front en **génère ses types TypeScript** avec `openapi-typescript` : `make api-types` exporte la spec (`nelmio:apidoc:dump`) dans `frontend/src/api/openapi.json` puis génère `frontend/src/api/schema.d.ts`, tous deux commités pour que les changements de contrat soient visibles dans les diffs. Un changement de contrat côté back casse donc le typecheck du front. (`openapi-typescript` 7 déclare TypeScript 5 en peer dependency : une `override` dans `package.json` lui fait utiliser le TypeScript 6 du projet, génération vérifiée.)
- **Zod côté front** valide les formulaires pour l'UX (erreur immédiate). Ses contraintes reprennent celles du DTO correspondant.
- **Règles métier dupliquées volontairement** : quand le front doit recalculer une règle pour l'affichage (alerte « hors politique »), il en a sa propre version, testée. Celle du back fait foi. Chaque duplication est listée dans la spec de la feature.

## Contraintes

- Le front et le back ne partagent aucun code : ils ne communiquent que par HTTP via `/api`.
- Données serveur dans Zustand côté front : choix de périmètre. TanStack Query serait l'outil idiomatique en production (voir `docs/adr/`).

## Versions

Symfony 8.1, PHP 8.4+, React 19, Vite 8, TypeScript ~6.0, React Router 8 (tout s'importe de `react-router`, mode déclaratif), Zustand 5, Zod 4, Vitest 5. Ces librairies évoluent vite (par exemple, Zod 3 → 4 : `z.string().email()` devient `z.email()`). Vérifier la syntaxe dans la doc de la version installée (`composer.json`, `package.json`) plutôt que de la deviner.
