---
name: ecrire-spec
description: Transforme une idée de fonctionnalité en spec au format du projet (Spec Kit) dans specs/NNN-nom/spec.md (étape F2 de docs/methode.md). À utiliser avant tout code d'une nouvelle feature ou d'une évolution fonctionnelle ; pas pour un bug ni un refactor.
---

# Écrire une spec

Tu écris le **quoi**, pas le comment. Tu ne codes rien, tu ne crées pas de branche, tu ne commites pas.

## 1. Comprendre l'existant

- Lis `docs/methode.md` (format d'une spec et grille de relecture) et `docs/produit.md`.
- Lis les docs utiles à la feature : `docs/modele-donnees.md` (entités, cycle de vie), `docs/api.md` (routes, codes d'erreur).
- Lis les specs existantes (`specs/*/spec.md`) et le code réellement en place (`backend/src/Entity/`, `backend/src/Dto/`, `backend/config/packages/workflow.yaml`, `frontend/src/features/`). Les docs décrivent la cible, le code dit ce qui existe déjà.

## 2. Nommer le dossier

- Prends le numéro libre suivant parmi les dossiers de `specs/` et un nom court en kebab-case : la spec va dans `specs/NNN-nom/spec.md`.
- N'appelle pas `/speckit-specify` ni ses scripts (ils créent une branche nommée `NNN-nom` au lieu de `feat/NNN-nom`, et une checklist) : écris le fichier toi-même. Les deux partent du même modèle, la spec a donc le même format.

## 3. Rédiger

Pars du modèle `.specify/templates/overrides/spec-template.md` (titres de Spec Kit en anglais, contenu en français) et suis le format de `docs/methode.md`, avec ces exigences :
- **Input** : le besoin, qui, quoi, pourquoi, sans aucune technologie.
- **User Scenarios** : une user story par parcours d'un rôle, avec priorité, test indépendant, critères couverts et scénarios Given/When/Then qui renvoient aux critères.
- **Functional Requirements** : critères numérotés `C1`, `C2`… (jamais `FR-001`) au format EARS, chacun vérifiable par un seul test (les tests et la relecture s'y réfèrent par leur numéro).
- **Autorisations** : pour chaque action, qui peut et qui ne peut pas, avec le code attendu (401 non connecté, 403 interdit, 404 si la ressource d'un autre ne doit pas être révélée).
- **Edge Cases** : vide, zéro, valeur maximale, action déjà faite (409), ressource d'un autre.
- **Règles recalculées côté front** : liste chaque règle métier que le front recalcule pour l'affichage (duplication volontaire, à tester des deux côtés), ou écris « aucune ».
- **Success Criteria** : vérifiables (critères couverts par des tests, parcours e2e), sans chiffre inventé.
- **Assumptions** : hors périmètre explicite, écarts avec les docs.
- **Clarifications** : tout ce que l'idée ne tranche pas, en `[NEEDS CLARIFICATION: …]` avec une proposition par défaut motivée. Ne tranche jamais à la place de l'humain.

Pas de `plan.md` ni de `tasks.md` : ils sont produits à l'étape F3.

## 4. Se relire avant de présenter

- Applique la grille de relecture de `docs/methode.md` et corrige toi-même ce qui ne passe pas.
- Vérifie qu'aucun critère ne contredit `docs/api.md` ou `docs/modele-donnees.md`. Si la feature impose un écart, signale-le comme question ouverte au lieu de modifier les docs.
- Pas de remplissage : les user stories et les scénarios reprennent les critères sans en ajouter.

## 5. Présenter

Écris le fichier, puis rends dans la conversation :
- le chemin du fichier ;
- les clarifications, numérotées, avec ta proposition pour chacune ;
- les écarts avec les docs, s'il y en a.

Attends que je tranche les questions et valide la spec. Intègre mes réponses dans la spec (section Clarifications en `### Session AAAA-MM-JJ` / `- Q: … → A: …`, et la réponse reportée dans les critères ou les hypothèses) avant tout commit.
