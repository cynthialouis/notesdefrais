---
name: revue-api
description: Relit le code de l'API Symfony modifié dans backend/ selon les conventions du projet (validation, autorisation par Voter, Workflow, erreurs, Doctrine, contrat OpenAPI, tests). À utiliser après chaque feature back, avant commit (étape F6 de docs/methode.md), ou sur tout backend/ pour une revue globale.
---

# Revue API

## 1. Périmètre

- Par défaut : ce qui a changé sur la branche par rapport à `main`, y compris le travail non commité et les fichiers nouveaux.
  - `git diff main...HEAD --name-only -- backend`
  - `git diff HEAD --name-only -- backend`
  - `git ls-files --others --exclude-standard backend` (les fichiers nouveaux n'apparaissent pas dans `git diff`)
- Si la demande dit « tout le back » : `backend/src/`, `backend/config/`, `backend/migrations/` et `backend/tests/`.
- Lis chaque fichier en entier. Pour une route, suis le chemin complet : contrôleur → DTO → Voter → service → entité.
- Lis la spec de la feature dans `specs/` et les conventions de `docs/api.md`. Les conventions Symfony générales (attributs, autowiring, Flex) sont dans `backend/AGENTS.md` : ne les recopie pas, vérifie qu'elles sont respectées.

## 2. D'abord les outils

Dans `backend/`, lance et rapporte tels quels :
- `php bin/phpunit`
- `php bin/console lint:container`
- `php bin/console lint:yaml config/`
- `php bin/console doctrine:schema:validate` (mapping cohérent avec les migrations)
- `php bin/console debug:router` (liste des routes, pour repérer une route nouvelle ou modifiée)

La revue porte sur ce que ces outils ne voient pas.

## 3. Vérifier, dans cet ordre

1. **Autorisation** : chaque route protégée vérifie le droit sur **la ressource précise** via un Voter (`#[IsGranted('ATTRIBUT', 'sujet')]` ou `denyAccessUnlessGranted`), pas seulement le rôle. Un manager ne décide que pour les salariés de son équipe ; un salarié ne lit ni ne modifie la note d'un autre. Le code renvoyé (403, ou 404 pour ne pas révéler la ressource) est celui de la spec.
2. **Validation** : toute entrée (body, query, paramètres) passe par un DTO `readonly` avec ses contraintes `#[Assert\...]`, reçu par `#[MapRequestPayload]` ou `#[MapQueryString]`. Pas de `json_decode()`, ni de `$request->get()` lu sans validation. Pas d'entité Doctrine remplie directement depuis la requête.
3. **Transitions de statut** : uniquement via le Workflow (`can()` puis `apply()`), jamais par un setter de statut. Transition impossible → 409.
4. **Erreurs** : format unique `{ error: { code, message, fields? } }` produit par le listener d'exceptions ; codes HTTP de `docs/api.md` ; aucune fuite de détail interne (message d'exception, SQL, chemin de fichier).
5. **Données** : montants en centimes entiers (`int`), jamais en `float` ; requêtes Doctrine avec paramètres liés, jamais de concaténation de chaînes ; tout changement de schéma passe par une **nouvelle** migration, aucune migration existante n'est modifiée.
6. **Fichiers** (justificatifs) : type MIME et taille vérifiés côté serveur, nom de fichier stocké généré par le serveur, stockage hors de `public/`.
7. **Contrat OpenAPI** : chaque route nouvelle ou modifiée apparaît correctement dans `/api/doc.json` (réponses décrites avec `#[OA\Response]` quand Nelmio ne peut pas les déduire). Signale tout changement de contrat qui casse le front (champ renommé ou supprimé, type changé).
8. **Contrôleurs** : fins, la logique métier est dans un service ; une règle de la spec n'est implémentée qu'à un seul endroit du back.
9. **Tests** : pour chaque route, au moins un test nominal, un test d'autorisation (403 ou 404) et un test de validation (400), qui vérifient un vrai comportement (réponse et état en base), pas seulement le code HTTP.
10. **Écart avec la spec** : critère non implémenté, ou comportement implémenté qui n'est pas dans la spec.

Ne signale que ce que tu peux montrer dans le code : chaque remarque cite le fichier et la ligne. Pas de remarque de style ni de préférence personnelle.

## 4. Rendre la revue

Une liste par fichier, chaque remarque avec :
- `fichier:ligne`, le numéro de la règle ci-dessus ;
- la gravité : **bloquant** (faille d'autorisation, donnée non validée, bug, règle du projet violée), **à corriger** (dette réelle), **suggestion** ;
- la correction proposée, en une ou deux lignes.

Termine par le résultat des outils et par « Aucune remarque » pour les fichiers relus sans problème.

## 5. Appliquer ou non

- Si la demande est une revue seule : ne modifie aucun fichier.
- Si la demande inclut les corrections (étape F6) : applique les remarques **bloquant** et **à corriger**, en ajoutant le test qui prouve chaque faille corrigée, relance `php bin/phpunit` et `php bin/console lint:container`, puis indique les remarques écartées avec leur justification.
