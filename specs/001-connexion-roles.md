# 001 — Connexion par rôle

## Besoin

Chaque personne qui utilise l'application est salarié, manager ou admin. Pour la démo, elle se connecte sans mot de passe en choisissant un compte parmi des comptes de démo (1 admin, 2 managers, 4 salariés rattachés chacun à un manager). Une fois connectée, elle ne voit et n'utilise que ce que son rôle permet. Toutes les autres features s'appuient sur cette identification.

## Critères d'acceptation

- **C1** QUAND un visiteur ouvre la page de connexion, LE SYSTÈME DOIT afficher les comptes de démo avec leur nom et leur rôle.
- **C2** QUAND un visiteur choisit un compte, LE SYSTÈME DOIT ouvrir une session pour ce compte et l'amener au tableau de bord.
- **C3** TANT QUE la session est ouverte, LE SYSTÈME DOIT reconnaître l'utilisateur après un rechargement de la page.
- **C4** QUAND l'utilisateur se déconnecte, LE SYSTÈME DOIT fermer la session et l'amener à la page de connexion.
- **C5** SI une personne non connectée appelle une fonction protégée de l'API, ALORS LE SYSTÈME DOIT la refuser (401) avec le format d'erreur unique.
- **C6** SI une personne non connectée ouvre une page protégée, ALORS LE SYSTÈME DOIT l'amener à la page de connexion.
- **C7** SI un utilisateur ouvre une page réservée à un rôle supérieur au sien, ALORS LE SYSTÈME DOIT afficher « Accès refusé ».
- **C8** LE SYSTÈME DOIT n'afficher dans le menu que les pages accessibles au rôle de l'utilisateur.
- **C9** LE SYSTÈME DOIT appliquer la hiérarchie admin > manager > salarié : un rôle accède aussi aux pages des rôles inférieurs.
- **C10** SI la demande de connexion ne désigne pas un compte (identifiant absent ou mal formé), ALORS LE SYSTÈME DOIT la refuser (400) en indiquant le champ en cause.
- **C11** SI la demande de connexion désigne un compte inexistant, ALORS LE SYSTÈME DOIT la refuser (401) sans révéler si le compte existe.
- **C12** QUAND un utilisateur déjà connecté se connecte avec un autre compte, LE SYSTÈME DOIT remplacer la session par celle du nouveau compte.
- **C13** QUAND un utilisateur déjà connecté ouvre la page de connexion, LE SYSTÈME DOIT l'amener au tableau de bord.

## Autorisations

| Action | Qui peut | Sinon |
|---|---|---|
| Voir la liste des comptes de démo | tout le monde | — |
| Se connecter | tout le monde | — |
| Connaître l'utilisateur courant, se déconnecter | connecté | 401 |
| Tableau de bord | connecté | page de connexion |
| Page « Mes notes » | salarié, manager, admin | page de connexion / « Accès refusé » |
| Page « Équipe » | manager, admin | idem |
| Page « Politique » | admin | idem |

Les pages « Mes notes », « Équipe » et « Politique » sont vides dans cette feature (« à venir »), mais déjà protégées.

## Cas limites et erreurs

- Session expirée ou cookie altéré : traité comme non connecté (401 côté API, page de connexion côté front).
- Déconnexion sans session ouverte : refusée (401).
- Déconnexion demandée autrement que par une action explicite (lien, image : requête GET) : refusée (405), la session reste ouverte. *Ajouté le 11/10 après la revue API.*
- Demande de connexion dont le corps n'est pas du JSON (ce qu'enverrait un formulaire d'un autre site pour connecter un visiteur à son insu) : refusée (415). *Ajouté le 11/10 après la revue API.*
- Le rôle et les pages accessibles viennent toujours du serveur ; modifier l'état du navigateur ne donne accès à aucune donnée.

## Règles recalculées côté front

Aucune. Le serveur renvoie, pour l'utilisateur courant, la liste des rôles auxquels il a accès ; le front ne recalcule pas la hiérarchie.

## Hors périmètre

Mot de passe, inscription, OAuth, mot de passe oublié, durée de session paramétrable (la durée par défaut du serveur s'applique), gestion des comptes par l'admin, données métier des pages protégées.

## Questions ouvertes

Aucune. Tranchées le 11/10 : une connexion remplace la session ouverte (C12) ; un utilisateur connecté qui ouvre la page de connexion va au tableau de bord (C13) ; durée de session par défaut (hors périmètre).

## Écarts avec les docs (déjà tranchés, docs à mettre à jour)

- `docs/api.md` : nouvelle route publique de liste des comptes de démo ; l'utilisateur courant expose ses rôles accessibles.
- `docs/produit.md` (écrans) : avec la hiérarchie, « Mes notes » et « Équipe » ne sont plus réservées à un seul rôle.

## Plan

- **Back** : entité `User` (id, nom, email unique, rôle en enum PHP, manager optionnel) + migration + fixtures (1 admin, 2 managers, 4 salariés). Sécurité Symfony : provider `entity`, `role_hierarchy` admin → manager → salarié, authenticator `DemoLoginAuthenticator` sur `POST /api/auth/login` (DTO `LoginInput` validé, 400 / 401 JSON, sert aussi d'entry point 401), logout sur `POST /api/auth/logout` (204), `access_control` (routes publiques : health, doc, login, demo-users ; reste de `/api` : `IS_AUTHENTICATED`). Listener d'exceptions pour le format `{ error: { code, message, fields? } }`. Routes `GET /api/auth/demo-users` et `GET /api/me` (rôles accessibles calculés par `RoleHierarchyInterface`), sorties typées pour OpenAPI.
- **Front** : `react-router`, `zustand`, `zod`, Vitest + Testing Library, `openapi-typescript` (types générés depuis `nelmio:apidoc:dump`). Client `/api` avec `ApiError`. Store de session en union discriminée (`loading` / `anonymous` / `authenticated`), `fetchMe` appelé une fois dans `main.tsx`. `LoginPage` (`useActionState`), `RequireRole`, layout avec menu filtré et déconnexion, pages vides protégées.
- **Tests du back** : `dama/doctrine-test-bundle` (rollback par test), base de test migrée par `make test`.

## Tâches

1. Entité `User`, migration, fixtures (C1, C9)
2. Listener d'erreurs JSON (C5, C10, C11)
3. Sécurité : provider, hiérarchie, authenticator, logout, access_control (C2, C3, C4, C5, C10, C11, C12)
4. Routes `demo-users` et `me` (C1, C3, C9)
5. Dépendances front, scripts `test` / `gen:api`, cible `make api-types`
6. Client API et `ApiError` (C5)
7. Store de session (C2, C3, C4, C12)
8. Écrans : connexion, `RequireRole`, layout, menu, pages vides (C1, C2, C4, C6, C7, C8, C13)
