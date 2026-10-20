# Notes de frais

Application de gestion de notes de frais et de congés (affichée sous le nom « Espace salarié ») pour trois rôles : le salarié saisit et soumet ses notes et demande ses congés, le manager valide ou rejette les notes et accepte ou refuse les congés de son équipe, l'admin fixe la politique de dépenses et peut annuler un congé.

Le dépôt contient deux projets indépendants qui ne communiquent que par HTTP : un front **React 19 + TypeScript** (`frontend/`) et une API **Symfony 8** (`backend/`). L'application fonctionne en local, sans installation de base de données (SQLite), avec des comptes de démo sans mot de passe.

## Fonctionnalités

| Feature | Pour qui | Ce qu'elle permet |
|---|---|---|
| Connexion par rôle | tous | Choisir un compte de démo ; les pages et les routes de l'API sont protégées par rôle (admin > manager > salarié) |
| Soumettre une note | salarié | Créer un brouillon (libellé, montant, date, catégorie), le modifier, le soumettre ou le supprimer ; liste filtrable par état |
| Valider / rejeter | manager | Traiter les notes soumises de son équipe, rejeter avec un motif obligatoire ; le salarié corrige et soumet de nouveau ; historique de chaque note |
| Politique de dépenses | admin | Plafond et justificatif obligatoire par catégorie ; une note hors politique est signalée sans être bloquée |
| Justificatifs | salarié | Joindre une image ou un PDF (5 Mo au plus, type vérifié d'après le contenu) ; soumission bloquée si le justificatif est obligatoire et absent |
| Tableau de bord | salarié, manager | Ses totaux par état ; pour un manager, les notes validées de l'équipe par catégorie et par mois sur 12 mois, et leur export CSV |
| Demander un congé | tous | Demander une période de congé ; le responsable l'accepte ou la refuse avec un motif ; le demandeur la retire tant qu'elle est en attente ; les congés acceptés sont visibles de tous ; l'admin annule avec un motif |

Chaque feature a sa spec dans [`specs/`](specs/). Hors périmètre : vraie authentification, remboursement, multi-devises, notifications, déploiement en production (voir [`docs/produit.md`](docs/produit.md)).

## Stack

- **Front** : React 19, TypeScript strict, Vite 8, React Router 8, Zustand 5, Zod 4, Tailwind CSS 4, types de l'API générés depuis OpenAPI (`openapi-typescript`).
- **Back** : Symfony 8.1 (PHP 8.4+), Doctrine ORM et Migrations sur SQLite, Validator, Security (Voters), Workflow, NelmioApiDocBundle.
- **Tests** : PHPUnit pour l'API, Vitest et Testing Library pour le front, Playwright pour les parcours de bout en bout.

L'API est la source de vérité : validation, autorisation et changements d'état d'une note y sont toujours faits, même quand le front les vérifie aussi pour l'affichage. Les montants sont stockés en centimes entiers.

## Prérequis

- PHP 8.4 ou plus récent, avec les extensions `pdo_sqlite` et `fileinfo`
- [Composer](https://getcomposer.org/)
- [Symfony CLI](https://symfony.com/download) (serveur local de l'API)
- Node.js 22 ou plus récent, avec npm
- `make`

## Installation

```bash
git clone https://github.com/cynthialouis/notesdefrais.git
cd notesdefrais
make install   # dépendances PHP et npm, et Chromium pour les tests de bout en bout
make db        # crée la base de dev et charge les données de démo
```

Aucun secret n'est nécessaire en local : les fichiers `.env` committés ne contiennent que des valeurs par défaut. Pour un secret réel, utiliser `backend/.env.local` (ignoré par git) ou `php bin/console secrets:set`.

## Lancement

```bash
make dev
```

L'API démarre en arrière-plan sur le port 8000, puis le front sur <http://localhost:5173>. Le front appelle `/api`, que Vite redirige vers l'API. `Ctrl+C` arrête le front, `make stop` arrête l'API.

La spec OpenAPI de l'API est servie sur <http://localhost:8000/api/doc.json>.

### Comptes de démo

La page de connexion propose ces comptes, sans mot de passe :

| Compte | Rôle | Responsable |
|---|---|---|
| Alice Martin | admin | — |
| Claire Dubois | manager | Alice Martin |
| Hugo Lefèvre | manager | Alice Martin |
| Léa Bernard, Tom Petit | salarié | Claire Dubois |
| Inès Moreau, Noah Laurent | salarié | Hugo Lefèvre |

Les données de démo contiennent des notes dans chaque état, dont certaines hors politique ou avec justificatif. `make db` les recharge à tout moment.

## Tests

| Commande | Ce qu'elle lance |
|---|---|
| `make test` | Tests de l'API (PHPUnit) puis du front (Vitest) |
| `make test-back` | Tests de l'API seuls ; filtre : `make test-back ARGS="--filter Nom"` |
| `make test-e2e` | Parcours de bout en bout (Playwright) dans un vrai navigateur ; filtre : `make test-e2e ARGS="decision"` |

Les tests de bout en bout démarrent leur propre API (port 8001, base `backend/var/data_e2e.db`) et leur propre front (port 5174) : ils peuvent tourner pendant `make dev` sans toucher à la base de dev. Le journal de cette API est écrit dans `backend/var/log/e2e-api.log`.

Avant un commit, vérifier aussi les types et le lint : `npx tsc -b` et `npm run lint` dans `frontend/`, `php bin/console lint:container` dans `backend/`.

## Commandes utiles

| Commande | Effet |
|---|---|
| `make` | Liste toutes les commandes |
| `make api-types` | Régénère les types TypeScript du front depuis la spec OpenAPI, après un changement de contrat de l'API |
| `make db` | Migre la base de dev et recharge les données de démo |
| `make e2e-db` | Remet la base des tests de bout en bout dans l'état des données de démo |

## Structure

```
frontend/            front React (Vite), organisé par feature
  src/features/        auth, expenses, approvals, policies, dashboard
  src/ui/              composants d'interface réutilisables
  src/api/             client HTTP et types générés depuis OpenAPI
  e2e/                 parcours de bout en bout (Playwright)
backend/             API Symfony
  src/                 contrôleurs, DTO, entités, Voters, services
  config/packages/workflow.yaml   cycle de vie d'une note
  migrations/          migrations Doctrine
  tests/               tests PHPUnit
specs/               un dossier par feature (spec.md, plan.md, tasks.md)
docs/                documentation du projet
```

## Documentation

- [`docs/produit.md`](docs/produit.md) : features, hors périmètre, rôle de chaque techno, écrans
- [`docs/architecture.md`](docs/architecture.md) : structure, proxy, contrat OpenAPI, tests de bout en bout, contraintes
- [`docs/modele-donnees.md`](docs/modele-donnees.md) : entités, cycle de vie d'une note, DTO, types du front
- [`docs/api.md`](docs/api.md) : routes, autorisations, conventions d'erreur, tests
- [`docs/methode.md`](docs/methode.md) : cycle de développement d'une feature

## Méthode de développement

Le projet est développé avec un agent de code (Claude Code), selon une méthode guidée par les specs : chaque feature commence par une spec validée, les tests de l'API sont écrits à partir de la spec avant l'implémentation, puis le code est relu et vérifié à la main avant la fusion. Le détail est dans [`docs/methode.md`](docs/methode.md), les consignes de l'agent dans [`CLAUDE.md`](CLAUDE.md), et chaque écart relevé en cours de route dans [`JOURNAL-IA.md`](JOURNAL-IA.md).
