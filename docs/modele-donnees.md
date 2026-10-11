# Modèle de données

Entités Doctrine dans `backend/src/Entity/`, schéma versionné par Doctrine Migrations.

## Entités

| Entité | Champs principaux | Remarques |
|---|---|---|
| `User` | id, name, email, role (`employee` / `manager` / `admin`), manager | Comptes de démo créés par les fixtures |
| `Category` | id, slug, label | repas, transport, hébergement, matériel, autre ; données de référence **insérées par la migration** (présentes dans tous les environnements, jamais purgées par les fixtures : `make db`) |
| `Policy` | id, category, maxAmountCents, receiptRequired | Une politique par catégorie |
| `Expense` | id, owner, category, label, amountCents, date, status (enum `ExpenseStatus`), createdAt, submittedAt ; puis decidedAt, decidedBy, rejectionReason (003) | Montant en centimes entiers, jamais de flottant. `ExpenseStatus::isEditable()` est le seul endroit qui dit quels états se modifient et se suppriment |
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
- La date de soumission est posée par un listener du Workflow à l'entrée dans `submitted`.
- Chaque transition écrira un `ExpenseEvent` à partir de la feature 003 (via les événements du Workflow).

## Entrées (back)

Un DTO `readonly` par entrée, validé par des attributs `#[Assert\...]` et reçu avec `#[MapRequestPayload]` :

| DTO | Contraintes |
|---|---|
| `ExpenseInput` | label : 2 à 80 caractères (après trim) ; amountCents : entier, > 0, ≤ 1 000 000 (10 000 €), reçu en `mixed` et contrôlé par `Assert\Type` pour qu'un flottant soit refusé et jamais tronqué ; date : `Y-m-d`, pas postérieure à aujourd'hui en Europe/Paris (`NotInFuture`) ; categorySlug : catégorie existante (`ExistingCategory`) |
| `RejectionInput` | reason : au moins 5 caractères utiles (« Le motif doit être explicite ») |
| `PolicyInput` | maxAmountCents : entier > 0 ; receiptRequired : booléen |

La vérification de politique est un service PHP (`PolicyChecker`) qui renvoie `ok` ou la liste des violations (`over_limit`, `missing_receipt`).

## Types côté front

Les types des réponses sont générés depuis OpenAPI. Une note renvoyée par l'API porte `editable` (calculé par `ExpenseStatus::isEditable()`) : le front ne recalcule pas cette règle. Le front ajoute ce qu'OpenAPI n'exprime pas, sous forme de schéma Zod validé à la réception (`features/expenses/expensesStore.ts`) :

```ts
// Union discriminée sur le statut : une note rejetée sans motif est impossible à représenter
export type Expense =
  | (ExpenseBase & { status: 'draft' })
  | (ExpenseBase & { status: 'submitted'; submittedAt: string })
  | (ExpenseBase & { status: 'approved'; submittedAt: string; decidedAt: string; decidedBy: string })
  | (ExpenseBase & { status: 'rejected'; submittedAt: string; decidedAt: string; decidedBy: string; rejectionReason: string })
```

Les schémas Zod des formulaires reprennent les contraintes des DTO ci-dessus. L'alerte « hors politique » du formulaire a sa propre version de la règle de politique, testée, alimentée par les politiques renvoyées par `GET /api/categories`.
