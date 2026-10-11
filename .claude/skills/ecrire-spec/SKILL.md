---
name: ecrire-spec
description: Transforme une idée de fonctionnalité en spec au format du projet dans specs/NNN-nom.md (étape F2 de docs/methode.md). À utiliser avant tout code d'une nouvelle feature ou d'une évolution fonctionnelle ; pas pour un bug ni un refactor.
---

# Écrire une spec

Tu écris le **quoi**, pas le comment. Tu ne codes rien, tu ne crées pas de branche, tu ne commites pas.

## 1. Comprendre l'existant

- Lis `docs/methode.md` (format d'une spec et grille de relecture) et `docs/produit.md`.
- Lis les docs utiles à la feature : `docs/modele-donnees.md` (entités, cycle de vie), `docs/api.md` (routes, codes d'erreur).
- Lis les specs existantes dans `specs/` et le code réellement en place (`backend/src/Entity/`, `backend/src/Dto/`, `backend/config/packages/workflow.yaml`, `frontend/src/features/`). Les docs décrivent la cible, le code dit ce qui existe déjà.

## 2. Nommer le fichier

- Si la feature figure dans la liste de `docs/methode.md` (« Specs prévues »), reprends son numéro et son nom.
- Sinon, prends le numéro libre suivant dans `specs/` et un nom court en kebab-case.

## 3. Rédiger

Suis le format de `docs/methode.md`, avec ces exigences :
- **Besoin** : qui, quoi, pourquoi, sans aucune technologie.
- **Critères d'acceptation** : numérotés `C1`, `C2`… au format EARS, chacun vérifiable par un seul test (les tests et la relecture s'y réfèrent par leur numéro).
- **Autorisations** : pour chaque action, qui peut et qui ne peut pas, avec le code attendu (401 non connecté, 403 interdit, 404 si la ressource d'un autre ne doit pas être révélée).
- **Cas limites et erreurs** : vide, zéro, valeur maximale, action déjà faite (409), ressource d'un autre.
- **Règles recalculées côté front** : liste chaque règle métier que le front recalcule pour l'affichage (duplication volontaire, à tester des deux côtés), ou écris « aucune ».
- **Hors périmètre** : ce qu'on ne fait pas, explicitement.
- **Questions ouvertes** : tout ce que l'idée ne tranche pas. Pour chacune, une proposition par défaut motivée. Ne tranche jamais à la place de l'humain.

Pas de section Plan ni Tâches : elles sont produites à l'étape F3 puis ajoutées à la spec.

## 4. Se relire avant de présenter

- Applique la grille de relecture de `docs/methode.md` et corrige toi-même ce qui ne passe pas.
- Vérifie qu'aucun critère ne contredit `docs/api.md` ou `docs/modele-donnees.md`. Si la feature impose un écart, signale-le comme question ouverte au lieu de modifier les docs.
- Une page maximum (environ 60 lignes).

## 5. Présenter

Écris le fichier, puis rends dans la conversation :
- le chemin du fichier ;
- les questions ouvertes, numérotées, avec ta proposition pour chacune ;
- les écarts avec les docs, s'il y en a.

Attends que l'humain tranche les questions et valide la spec. Intègre ses réponses dans la spec (les questions tranchées passent dans les critères ou le hors périmètre) avant tout commit.
