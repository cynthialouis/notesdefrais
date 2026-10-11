# Méthode : cycle d'une feature (spec-driven)

Chaque feature = une branche, un commit de spec, un commit de tests, puis les commits d'implémentation.

## Rôles

- **Claude** rédige tout (specs, plans, tests, code, harness, documentation, journal), se critique avec les grilles ci-dessous et applique lui-même les corrections pertinentes. Il justifie ce qu'il écarte.
- **L'humain** exprime le besoin, tranche les questions ouvertes, valide la spec et le plan, lit les diffs pour les comprendre, teste à la main dans le navigateur et prépare la démo.

Specs prévues dans `specs/` : `001-connexion-roles`, `002-soumettre-note`, `003-valider-rejeter`, `004-politique-depenses`, `005-justificatifs`, `006-tableau-de-bord`.

## Étapes

| Étape | Action |
|---|---|
| F1. Branche | `git switch -c feat/00X-nom-court` |
| F2. Spec | `/ecrire-spec` avec l'idée de départ. Claude relit la spec avec la grille ci-dessous et la corrige ; l'humain tranche les questions ouvertes et valide. Commit `docs(spec): 00X nom` |
| F3. Plan | Plan mode, prompt P-Plan. Claude vérifie le plan avec la grille et l'ajuste ; l'humain valide. Le plan et les tâches sont ajoutés à la spec. Commit `docs(spec): 00X plan` |
| F4a. Tests | Subagent `testeur-spec` sur la spec. Claude vérifie : tests nommés d'après les critères, qui échouent pour la bonne raison ; `git diff --stat` ne montre que des fichiers de test. Commit `test(00X): tests depuis la spec` |
| F4b. Implémentation | Une tâche du plan à la fois (P-Impl), lecture du diff par l'humain pour le comprendre, tests du projet touché (`php bin/phpunit` dans `backend/`, `npm run test` dans `frontend/`), commit `feat(00X): tâche N – description` |
| F5. Vérification à la main | `symfony serve` dans `backend/` et `npm run dev` dans `frontend/`, chaque critère d'acceptation déroulé dans le navigateur avec le bon compte de démo |
| F6. Revues | `/revue-api` si `backend/` a changé, `/revue-react` si `frontend/` a changé. Claude applique les remarques pertinentes et justifie celles qu'il écarte |
| F7. Relecture à froid | Subagent `relecteur` sur la spec ; Claude corrige les problèmes signalés |
| F8. Fusion | `git switch main && git merge --no-ff feat/00X-nom-court`, puis les tests des deux projets sur main |

Si une API est incertaine à n'importe quelle étape : subagent `verificateur-docs`.

## Format d'une spec

Une page maximum, sections dans cet ordre :

- Besoin (qui, quoi, pourquoi, sans technologie)
- Critères d'acceptation numérotés `C1`, `C2`… au format EARS (QUAND… LE SYSTÈME DOIT… / SI… ALORS… / TANT QUE…), chacun testable
- Autorisations (qui peut faire quoi, avec le code attendu : 401, 403, 404)
- Cas limites et erreurs
- Règles recalculées côté front (duplications volontaires, ou « aucune »)
- Hors périmètre
- Questions ouvertes (listées avec une proposition, jamais devinées)

Rédigée à l'étape F2 par le skill `ecrire-spec`. Les sections suivantes sont ajoutées à l'étape F3, une fois le plan validé :

- Plan (DTO et contraintes, entités et migration, routes et Voters, schémas Zod et composants du front)
- Tâches numérotées, chacune reliée à un ou plusieurs critères

## Grille de relecture d'une spec (appliquée par Claude)

- Chaque critère est-il testable ?
- Les autorisations sont-elles explicites (qui peut, qui ne peut pas) ?
- Les cas limites sont-ils là (vide, zéro, déjà traité, pas le droit) ?
- Le hors périmètre est-il écrit ?
- Les questions ouvertes sont-elles listées pour que l'humain les tranche, et non devinées ?
- Une page maximum ?

## Grille de relecture d'un plan (appliquée par Claude)

- L'ordre est-il `backend/` (DTO, entités, routes) → `frontend/` (types générés, composants) ?
- Chaque tâche est-elle reliée à un critère de la spec ?
- Les tests de `backend/` sont-ils prévus avant l'implémentation ?
- Une nouvelle dépendance est-elle ajoutée ? Si oui, est-elle justifiée ?
- Une règle métier est-elle recalculée côté front ? Si oui, la duplication est-elle listée dans la spec et testée des deux côtés ?

## Prompts

**P-Plan**
> Lis specs/00X-nom.md, CLAUDE.md, frontend/CLAUDE.md et backend/CLAUDE.md. Propose le plan d'implémentation en tâches numérotées et petites, chacune reliée aux critères d'acceptation, dans l'ordre backend/ → frontend/. Les tests de backend/ seront écrits avant par le subagent testeur-spec. Ne code rien.

**P-Tests**
> Utilise le subagent testeur-spec sur specs/00X-nom.md.

**P-Impl**
> Implémente uniquement la tâche N du plan. Les tests correspondants existent déjà : lance-les pour me montrer qu'ils échouent, puis écris le code minimal pour les faire passer. Ne modifie pas les tests ; si un test te paraît faux, arrête-toi et explique pourquoi. Pour le front, écris toi-même les tests Testing Library de la tâche. Résume ensuite ce que tu as modifié.

## Journal

Chaque écart détecté (test du harness, remarque de revue retenue ou écartée, API inventée, version obsolète, spec mal interprétée) donne une ligne dans `JOURNAL-IA.md`, tenue par Claude, avec des entrées réelles uniquement :

| Date | Étape | Ce que l'agent a proposé | Ce que j'ai fait | Pourquoi |
|---|---|---|---|---|
