# Modèle de données

Entités Doctrine dans `backend/src/Entity/`, schéma versionné par Doctrine Migrations.

## Entités

| Entité | Champs principaux | Remarques |
|---|---|---|
| `User` | id, name, email, role (`employee` / `manager` / `admin`), manager | Comptes de démo créés par les fixtures |
| `Category` | id, slug, label | repas, transport, hébergement, matériel, autre |
| `Policy` | id, category, maxAmountCents, receiptRequired | Une politique par catégorie |
| `Expense` | id, owner, category, label, amountCents, date, status, submittedAt, decidedAt, decidedBy, rejectionReason | Montant en centimes entiers, jamais de flottant |
| `Receipt` | id, expense, filename, mimeType, sizeBytes, storagePath | Fichier sur disque (hors `public/`, ignoré par git), métadonnées en base |
| `ExpenseEvent` | id, expense, actor, type, comment, createdAt | Historique : créée, soumise, validée, rejetée |

## Cycle de vie d'une note

```
draft ──submit──▶ submitted ──approve──▶ approved
                       │
                       └──reject (motif)──▶ rejected ──submit (après correction)──▶ submitted
```

- Déclaré comme machine à états dans `backend/config/packages/workflow.yaml` (composant Workflow). Une transition invalide est refusée par le Workflow et renvoyée en 409.
- Seul le propriétaire modifie un brouillon ou une note rejetée.
- Seul le manager du propriétaire décide sur une note soumise (Voter).
- Une note `approved` est figée.
- Chaque transition écrit un `ExpenseEvent` (via les événements du Workflow).

## Entrées (back)

Un DTO `readonly` par entrée, validé par des attributs `#[Assert\...]` et reçu avec `#[MapRequestPayload]` :

| DTO | Contraintes |
|---|---|
| `ExpenseInput` | label : 2 à 80 caractères (après trim) ; amountCents : entier, > 0, ≤ 1 000 000 (10 000 €) ; date : date ISO, pas dans le futur ; categorySlug : une des 5 catégories |
| `RejectionInput` | reason : au moins 5 caractères utiles (« Le motif doit être explicite ») |
| `PolicyInput` | maxAmountCents : entier > 0 ; receiptRequired : booléen |

La vérification de politique est un service PHP (`PolicyChecker`) qui renvoie `ok` ou la liste des violations (`over_limit`, `missing_receipt`).

## Types côté front

Les types des réponses sont générés depuis OpenAPI. Le front y ajoute ce qu'OpenAPI n'exprime pas :

```ts
// Union discriminée sur le statut : une note rejetée sans motif est impossible à représenter
export type Expense =
  | (ExpenseBase & { status: 'draft' })
  | (ExpenseBase & { status: 'submitted'; submittedAt: string })
  | (ExpenseBase & { status: 'approved'; submittedAt: string; decidedAt: string; decidedBy: string })
  | (ExpenseBase & { status: 'rejected'; submittedAt: string; decidedAt: string; decidedBy: string; rejectionReason: string })
```

Les schémas Zod des formulaires reprennent les contraintes des DTO ci-dessus. L'alerte « hors politique » du formulaire a sa propre version de la règle de politique, testée, alimentée par les politiques renvoyées par `GET /api/categories`.
