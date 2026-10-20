# API

API Symfony dans `backend/`. Toutes les routes sont préfixées par `/api`. La spec OpenAPI est servie sur `/api/doc.json`.

| Méthode | Route | Rôle | Effet |
|---|---|---|---|
| GET | `/health` | public | `{ ok: true }` |
| GET | `/auth/demo-users` | public | Comptes de démo proposés à la connexion : `id`, `name`, `role` (sans email) |
| POST | `/auth/login` | public | `{ userId }` → ouvre (ou remplace) la session et renvoie l'utilisateur courant ; 400 si le corps est invalide, 401 si le compte n'existe pas, 415 si le corps n'est pas du JSON |
| POST | `/auth/logout` | connecté | Ferme la session (204) ; GET refusé (405) |
| GET | `/me` | connecté | Utilisateur courant : `id`, `name`, `email`, `role`, `accessibleRoles` (rôles atteints par la hiérarchie) |
| GET | `/categories` | connecté | Catégories et leur politique : `{ slug, label, maxAmountCents (null = aucun plafond), receiptRequired }` |
| GET | `/expenses` | connecté : ses propres notes | Liste filtrable (`?status=`, état inconnu → 400), triée par date décroissante puis id décroissant |
| POST | `/expenses` | connecté (tous rôles) | Crée un brouillon → 201 |
| GET | `/expenses/{id}` | propriétaire, ou son responsable hors brouillon | Lit une note ; la vue dit ce que le lecteur peut faire (`editable`, `decidable`) |
| PATCH | `/expenses/{id}` | propriétaire | Remplace tous les champs d'une note en brouillon ou rejetée (corps complet, comme `POST`) ; 409 sinon |
| DELETE | `/expenses/{id}` | propriétaire | Supprime une note en brouillon ou rejetée → 204 ; 409 sinon |
| POST | `/expenses/{id}/submit` | propriétaire | `draft` ou `rejected` → `submitted` ; 409 sinon, si le propriétaire n'a pas de responsable (l'admin), ou si un justificatif obligatoire manque (dans cet ordre) |
| GET | `/team/expenses` | manager et plus (403 sinon) | Notes soumises des personnes dont on est le responsable direct, les plus anciennes soumissions d'abord |
| POST | `/expenses/{id}/approve` | responsable du propriétaire | `submitted` → `approved` ; 403 pour le propriétaire, 404 pour tout autre ; 409 si pas soumise |
| POST | `/expenses/{id}/reject` | responsable du propriétaire | `submitted` → `rejected`, motif de 5 à 1000 caractères (`RejectionInput`) ; mêmes 403 / 404 / 409 |
| POST | `/expenses/{id}/receipt` | propriétaire (404 sinon) | Joint ou remplace le justificatif (multipart, champ `file`) : JPEG, PNG ou PDF d'après le contenu, 1 octet à 5 000 000 octets ; 400 sinon (`fields.file`) ; 409 si la note n'est pas modifiable → note mise à jour |
| GET | `/expenses/{id}/receipt` | propriétaire, ou son responsable hors brouillon | Le fichier, `inline`, type stocké, `X-Content-Type-Options: nosniff` ; 404 sans justificatif |
| DELETE | `/expenses/{id}/receipt` | propriétaire (404 sinon) | Retire le justificatif ; 404 s'il n'y en a pas ; 409 si la note n'est pas modifiable → note mise à jour |
| GET | `/expenses/{id}/events` | propriétaire, ou son responsable hors brouillon | Historique (type, auteur, commentaire, date), du plus ancien au plus récent |
| PUT | `/policies/{slug}` | admin (403 sinon) | `{ maxAmountCents: int de 1 à 1 000 000 ou null, receiptRequired: bool }` → catégorie mise à jour ; 404 si la catégorie est inconnue |
| GET | `/stats/me` | connecté | Ses totaux par état : `{ byStatus: [{ status, count, totalCents }] }`, les quatre états toujours présents |
| GET | `/stats/team` | manager et plus (403 sinon) | Notes validées des subordonnés directs sur les 12 derniers mois civils (date de la dépense, heure de Paris) : `{ from, to, count, totalCents, byCategory, byMonth }`, catégories et mois toujours présents |
| GET | `/leaves` | connecté | Congés acceptés en cours ou à venir de tous (date de fin pas antérieure à aujourd'hui, heure de Paris), date de début croissante |
| GET | `/leaves/pending` | admin (403 sinon) | Demandes de congé en attente de tous, date de début croissante |
| GET | `/me/leaves` | connecté | Toutes ses demandes de congé, quel que soit leur état, date de début décroissante |
| GET | `/team/leaves` | manager et plus (403 sinon) | Demandes en attente des subordonnés directs, date de début croissante |
| POST | `/leaves` | connecté (tous rôles) | `{ startDate, endDate }` (`Y-m-d`, bornes comprises) → demande `pending`, 201 ; 400 si une date est invalide, si le début est passé (heure de Paris) ou si la fin précède le début ; 409 sans responsable (l'admin), puis 409 si la période chevauche une de ses demandes en attente ou acceptée (dans cet ordre) |
| POST | `/leaves/{id}/accept` | responsable du demandeur | `pending` → `accepted` ; 403 pour le demandeur, 404 pour tout autre ; 409 si pas en attente |
| POST | `/leaves/{id}/refuse` | responsable du demandeur | `pending` → `refused`, motif de 5 à 1000 caractères (`RejectionInput`) ; mêmes 403 / 404 / 409 |
| POST | `/leaves/{id}/withdraw` | demandeur (404 sinon) | `pending` → `withdrawn` ; 409 si pas en attente |
| POST | `/leaves/{id}/cancel` | admin (403 sinon) | `pending` ou `accepted` → `cancelled`, motif (`RejectionInput`), quelle que soit la date du congé ; 409 sinon |
| GET | `/exports/team-expenses.csv` | manager et plus (403 sinon) | Les mêmes notes en CSV pour tableur français : BOM UTF-8, `;`, CRLF, montants à virgule, dates JJ/MM/AAAA ; échappement RFC 4180, formules neutralisées (`'` devant `=`, `+`, `-`, `@`, une tabulation ou un retour chariot) |

Toute demande de congé renvoyée par l'API est une `LeaveRequestView` construite pour le lecteur : décision et annulation (auteur, date, motif) toujours présentes, à `null` si besoin, et `decidable`, `withdrawable`, `cancellable`, ce que le lecteur peut en faire. Visibilité (`LeaveRequestVoter`) : une demande acceptée est visible de tous ; en attente, de son demandeur, de son responsable et de l'admin ; refusée, retirée ou annulée, de son seul demandeur.

Toute note renvoyée par l'API porte `policy` : `{ limitCents, overLimitByCents, receiptRequired, receiptMissing }`, calculé par `PolicyChecker` (hors politique quand `overLimitByCents` n'est pas null ; soumission refusée quand `receiptMissing` est vrai, ce qui n'arrive que pour une note modifiable), et `receipt` : `{ name, mimeType, sizeBytes }` ou null.

## Conventions

- Contrôleurs fins (`AbstractController`, attributs `#[Route]`) qui délèguent aux services.
- Toute entrée passe par un DTO validé, reçu avec `#[MapRequestPayload]` ou `#[MapQueryString]` en précisant `validationFailedStatusCode: 400` (Symfony renvoie 422 par défaut). Jamais de `json_decode()` à la main. Seule exception : `POST /auth/login`, traité par l'authenticator avant tout contrôleur, désérialise et valide `LoginInput` lui-même avec Serializer et Validator.
- Rôles : `ROLE_EMPLOYEE`, `ROLE_MANAGER`, `ROLE_ADMIN`, hiérarchisés admin > manager > salarié (`security.yaml`). Toute route de `/api` exige une session, sauf `health`, `doc.json`, `auth/login` et `auth/demo-users`.
- Autorisation par Voter, sur la ressource précise et pas seulement sur le rôle : un manager ne décide que pour les salariés de son équipe. Pour une ressource d'un autre utilisateur, le refus est un 404 (`#[IsGranted(..., statusCode: 404)]`), indiscernable d'une ressource inexistante.
- Transitions de statut uniquement via le Workflow (`expense`, `leave_request`) ; transition impossible → 409.
- Erreurs au format unique : `{ error: { code, message, fields? } }`, construit uniquement par `App\Http\ApiError` (utilisé par le listener d'exceptions et par la sécurité).
- Réponses : un champ nullable est toujours présent (valeur `null`), jamais omis. Nelmio ne le déclare pas obligatoire de lui-même : chaque vue renvoyée le précise avec `#[OA\Schema(required: [...])]`, et `ApiDocTest` vérifie que tous les champs des vues sont obligatoires dans `/api/doc.json` (une nouvelle vue s'ajoute à sa liste).
- Codes HTTP : 400 validation, 401 non connecté, 403 interdit, 404 introuvable, 405 méthode non autorisée (ex. déconnexion en GET), 409 transition impossible, 415 corps non JSON (ex. connexion, contre le CSRF de connexion).
- Schéma de base modifié uniquement par migration (`make:migration` puis `doctrine:migrations:migrate`).
- Configuration : `.env` committé ne contient que des valeurs par défaut ; les secrets vont dans `.env.local` (ignoré) ou dans le coffre Symfony (`secrets:set`).
- Justificatifs : type et taille validés côté serveur ; nom de fichier stocké généré par le serveur ; stockage hors de `public/`, dans un dossier ignoré par git.

## Tests

PHPUnit : `WebTestCase` pour les routes (requête HTTP simulée, sans lancer de serveur), `KernelTestCase` pour les services. La base SQLite de test est migrée par `make test` et remise à zéro entre les tests par `dama/doctrine-test-bundle` (rollback de transaction) ; chaque test crée ses données via `tests/Support/ApiTestCase`. Chaque route a au moins un test nominal, un test 403 et un test de validation.

Tests de bout en bout (`make test-e2e`, `frontend/e2e/`) : quelques parcours entre plusieurs rôles, joués dans un vrai navigateur contre l'API lancée dans l'environnement `e2e` (voir `docs/architecture.md`). Ils vérifient ce que PHPUnit et Vitest ne voient pas : proxy, cookie de session, envoi multipart réel, téléchargement du CSV. Ils ne remplacent pas les tests par critère.
