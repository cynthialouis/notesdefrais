# Méthode : cycle d'une feature (spec-driven)

Chaque feature = une branche, un commit de spec, un commit de tests, puis les commits d'implémentation.

Specs prévues dans `specs/` : `001-connexion-roles`, `002-soumettre-note`, `003-valider-rejeter`, `004-politique-depenses`, `005-justificatifs`, `006-tableau-de-bord`.

## Étapes

| Étape | Action |
|---|---|
| F1. Branche | `git switch -c feat/00X-nom-court` |
| F2. Spec | `/ecrire-spec` avec l'idée de départ. Relecture avec la grille ci-dessous, questions ouvertes tranchées par l'humain. Commit `docs(spec): 00X nom` |
| F3. Plan | Plan mode, prompt P-Plan. Relecture avec la grille plan, puis validation |
| F4a. Tests (à partir de 002) | Subagent `testeur-spec` sur la spec. Tests nommés d'après les critères, qui échouent pour la bonne raison ; `git diff --stat` ne montre que des fichiers de test. Commit `test(00X): tests depuis la spec` |
| F4b. Implémentation | Une tâche du plan à la fois (P-Impl, ou P-Tâche pour 001), relecture du diff, tests du projet touché (`php bin/phpunit` dans `backend/`, `npm run test` dans `frontend/`), commit `feat(00X): tâche N – description` |
| F5. Vérification à la main | `symfony serve` dans `backend/` et `npm run dev` dans `frontend/`, chaque critère d'acceptation déroulé dans le navigateur avec le bon compte de démo |
| F6. Revues | `/revue-api` si `backend/` a changé, `/revue-react` si `frontend/` a changé. Chaque remarque acceptée ou refusée avec justification |
| F7. Relecture à froid (à partir de 003) | Subagent `relecteur` sur la spec |
| F8. Fusion | `git switch main && git merge --no-ff feat/00X-nom-court`, puis les tests des deux projets sur main |

Si une API est incertaine à n'importe quelle étape : subagent `verificateur-docs`.

## Format d'une spec

Une page maximum, sections dans cet ordre :

- Besoin (qui, quoi, pourquoi, sans technologie)
- Critères d'acceptation au format EARS (QUAND… LE SYSTÈME DOIT… / SI… ALORS… / TANT QUE…), chacun testable
- Autorisations (qui peut faire quoi)
- Cas limites et erreurs
- Hors périmètre
- Questions ouvertes (listées, jamais devinées)
- Plan (DTO et contraintes, entités et migration, routes et Voters, schémas Zod et composants du front)
- Tâches numérotées, chacune reliée à un ou plusieurs critères

## Grille de relecture d'une spec

- Chaque critère est-il testable ?
- Les autorisations sont-elles explicites (qui peut, qui ne peut pas) ?
- Les cas limites sont-ils là (vide, zéro, déjà traité, pas le droit) ?
- Le hors périmètre est-il écrit ?
- Les questions ouvertes sont-elles tranchées par l'humain ?
- Une page maximum ?

## Grille de relecture d'un plan

- L'ordre est-il `backend/` (DTO, entités, routes) → `frontend/` (types générés, composants) ?
- Chaque tâche est-elle reliée à un critère de la spec ?
- Les tests de `backend/` sont-ils prévus avant l'implémentation (à partir de 002) ?
- Une nouvelle dépendance est-elle ajoutée ? Si oui, est-elle justifiée ?
- Une règle métier est-elle recalculée côté front ? Si oui, la duplication est-elle listée dans la spec et testée des deux côtés ?

## Prompts

**P-Plan**
> Lis specs/00X-nom.md, CLAUDE.md, frontend/CLAUDE.md et backend/CLAUDE.md. Propose le plan d'implémentation en tâches numérotées et petites, chacune reliée aux critères d'acceptation, dans l'ordre backend/ → frontend/. Les tests de backend/ seront écrits avant par le subagent testeur-spec. Ne code rien.

**P-Tests** (à partir de 002)
> Utilise le subagent testeur-spec sur specs/00X-nom.md.

**P-Impl** (à partir de 002)
> Implémente uniquement la tâche N du plan. Les tests correspondants existent déjà : lance-les pour me montrer qu'ils échouent, puis écris le code minimal pour les faire passer. Ne modifie pas les tests ; si un test te paraît faux, arrête-toi et explique pourquoi. Pour le front, écris toi-même les tests Testing Library de la tâche. Résume ensuite ce que tu as modifié.

**P-Tâche** (feature 001 uniquement)
> Implémente uniquement la tâche N du plan. Commence par le test, lance-le pour me montrer qu'il échoue, puis écris le code minimal pour le faire passer. Arrête-toi ensuite et résume ce que tu as modifié.

## Journal

Chaque intervention humaine (proposition refusée, API inventée, version obsolète, spec mal interprétée) donne une ligne dans `JOURNAL-IA.md`, avec des entrées réelles uniquement :

| Date | Étape | Ce que l'agent a proposé | Ce que j'ai fait | Pourquoi |
|---|---|---|---|---|
