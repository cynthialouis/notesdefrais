# API

API Symfony dans `backend/`. Toutes les routes sont préfixées par `/api`. La spec OpenAPI est servie sur `/api/doc.json`.

| Méthode | Route | Rôle | Effet |
|---|---|---|---|
| GET | `/health` | public | `{ ok: true }` |
| GET | `/auth/demo-users` | public | Comptes de démo proposés à la connexion : `id`, `name`, `role` (sans email) |
| POST | `/auth/login` | public | `{ userId }` → ouvre (ou remplace) la session et renvoie l'utilisateur courant ; 400 si le corps est invalide, 401 si le compte n'existe pas, 415 si le corps n'est pas du JSON |
| POST | `/auth/logout` | connecté | Ferme la session (204) ; GET refusé (405) |
| GET | `/me` | connecté | Utilisateur courant : `id`, `name`, `email`, `role`, `accessibleRoles` (rôles atteints par la hiérarchie) |
| GET | `/categories` | connecté | Catégories `{ slug, label }` (la politique associée arrive avec la feature 004) |
| GET | `/expenses` | connecté : ses propres notes | Liste filtrable (`?status=`, état inconnu → 400), triée par date décroissante puis id décroissant |
| POST | `/expenses` | connecté (tous rôles) | Crée un brouillon → 201 |
| GET | `/expenses/{id}` | propriétaire, ou son responsable hors brouillon | Lit une note ; la vue dit ce que le lecteur peut faire (`editable`, `decidable`) |
| PATCH | `/expenses/{id}` | propriétaire | Remplace tous les champs d'un brouillon (corps complet, comme `POST`) ; 409 hors brouillon |
| DELETE | `/expenses/{id}` | propriétaire | Supprime un brouillon → 204 ; 409 hors brouillon |
| POST | `/expenses/{id}/submit` | propriétaire | `draft` ou `rejected` → `submitted` ; 409 sinon, ou si le propriétaire n'a pas de responsable (l'admin) |
| GET | `/team/expenses` | manager et plus (403 sinon) | Notes soumises des personnes dont on est le responsable direct, les plus anciennes soumissions d'abord |
| POST | `/expenses/{id}/approve` | responsable du propriétaire | `submitted` → `approved` ; 403 pour le propriétaire, 404 pour tout autre ; 409 si pas soumise |
| POST | `/expenses/{id}/reject` | responsable du propriétaire | `submitted` → `rejected`, motif de 5 à 1000 caractères (`RejectionInput`) ; mêmes 403 / 404 / 409 |
| POST | `/expenses/{id}/receipts` | propriétaire | Upload (≤ 5 Mo, jpg / png / pdf) |
| GET | `/expenses/{id}/events` | propriétaire, ou son responsable hors brouillon | Historique (type, auteur, commentaire, date), du plus ancien au plus récent |
| PUT | `/policies/{categoryId}` | admin | Modifie plafond / justificatif obligatoire |
| GET | `/stats` | salarié / manager | Agrégats pour le tableau de bord, calculés côté serveur |
| GET | `/exports/expenses.csv` | manager | Export des notes validées (`text/csv`) |

## Conventions

- Contrôleurs fins (`AbstractController`, attributs `#[Route]`) qui délèguent aux services.
- Toute entrée passe par un DTO validé, reçu avec `#[MapRequestPayload]` ou `#[MapQueryString]` en précisant `validationFailedStatusCode: 400` (Symfony renvoie 422 par défaut). Jamais de `json_decode()` à la main. Seule exception : `POST /auth/login`, traité par l'authenticator avant tout contrôleur, désérialise et valide `LoginInput` lui-même avec Serializer et Validator.
- Rôles : `ROLE_EMPLOYEE`, `ROLE_MANAGER`, `ROLE_ADMIN`, hiérarchisés admin > manager > salarié (`security.yaml`). Toute route de `/api` exige une session, sauf `health`, `doc.json`, `auth/login` et `auth/demo-users`.
- Autorisation par Voter, sur la ressource précise et pas seulement sur le rôle : un manager ne décide que pour les salariés de son équipe. Pour une ressource d'un autre utilisateur, le refus est un 404 (`#[IsGranted(..., statusCode: 404)]`), indiscernable d'une ressource inexistante.
- Transitions de statut uniquement via le Workflow ; transition impossible → 409.
- Erreurs au format unique : `{ error: { code, message, fields? } }`, construit uniquement par `App\Http\ApiError` (utilisé par le listener d'exceptions et par la sécurité).
- Codes HTTP : 400 validation, 401 non connecté, 403 interdit, 404 introuvable, 405 méthode non autorisée (ex. déconnexion en GET), 409 transition impossible, 415 corps non JSON (ex. connexion, contre le CSRF de connexion).
- Schéma de base modifié uniquement par migration (`make:migration` puis `doctrine:migrations:migrate`).
- Configuration : `.env` committé ne contient que des valeurs par défaut ; les secrets vont dans `.env.local` (ignoré) ou dans le coffre Symfony (`secrets:set`).
- Justificatifs : type et taille validés côté serveur ; nom de fichier stocké généré par le serveur ; stockage hors de `public/`, dans un dossier ignoré par git.

## Tests

PHPUnit : `WebTestCase` pour les routes (requête HTTP simulée, sans lancer de serveur), `KernelTestCase` pour les services. La base SQLite de test est migrée par `make test` et remise à zéro entre les tests par `dama/doctrine-test-bundle` (rollback de transaction) ; chaque test crée ses données via `tests/Support/ApiTestCase`. Chaque route a au moins un test nominal, un test 403 et un test de validation.
