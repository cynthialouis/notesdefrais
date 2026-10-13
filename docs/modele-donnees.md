# Modèle de données

Entités Doctrine dans `backend/src/Entity/`, schéma versionné par Doctrine Migrations.

## Entités

| Entité | Champs principaux | Remarques |
|---|---|---|
| `User` | id, name, email, role (`employee` / `manager` / `admin`), manager | Comptes de démo créés par les fixtures. Le `manager` est le responsable direct : les salariés ont leur manager, les managers l'admin, l'admin personne |
| `Category` | id, slug, label, maxAmountCents (null = aucun plafond), receiptRequired | repas, transport, hébergement, matériel, autre ; données de référence **insérées par la migration** (présentes dans tous les environnements, jamais purgées par les fixtures : `make db`) |
| `Expense` | id, owner, category, label, amountCents, date, status (enum `ExpenseStatus`), createdAt, submittedAt ; puis decidedAt, decidedBy, rejectionReason (003) | Montant en centimes entiers, jamais de flottant. `ExpenseStatus::isEditable()` est le seul endroit qui dit quels états se modifient et se suppriment |
| `Receipt` | id, expense, filename, mimeType, sizeBytes, storagePath | Fichier sur disque (hors `public/`, ignoré par git), métadonnées en base |
| `ExpenseEvent` | id, expense, actor, type (enum `ExpenseEventType`), comment, createdAt | Historique : créée (écrit par `ExpenseManager::create()`), soumise, validée, rejetée (écrits par un listener `completed` du Workflow), motif d'un rejet en commentaire |

## Cycle de vie d'une note

```
draft ──submit──▶ submitted ──approve──▶ approved
                       │
                       └──reject (motif)──▶ rejected ──submit (après correction)──▶ submitted
```

- Déclaré comme machine à états dans `backend/config/packages/workflow.yaml` (composant Workflow). Une transition invalide est refusée par le Workflow et renvoyée en 409.
- Seul le propriétaire modifie un brouillon ou une note rejetée (`ExpenseStatus::isEditable()`).
- Seul le responsable du propriétaire décide d'une note soumise (`ExpenseVoter`, attribut `DECIDE`) ; une personne sans responsable ne peut pas soumettre.
- Les instants (`createdAt`, `submittedAt`, `decidedAt`) sont stockés en UTC : les setters des entités convertissent, car Doctrine perd le décalage horaire.
- Une note `approved` est figée.
- La date de soumission est posée par un listener du Workflow à l'entrée dans `submitted`.
- Chaque transition écrit un `ExpenseEvent` ; la décision (auteur, date, motif) est posée à l'entrée dans `approved` ou `rejected`, et effacée à la resoumission (le motif reste dans l'historique).

## Entrées (back)

Un DTO `readonly` par entrée, validé par des attributs `#[Assert\...]` et reçu avec `#[MapRequestPayload]` :

| DTO | Contraintes |
|---|---|
| `ExpenseInput` | label : 2 à 80 caractères (après trim) ; amountCents : entier, > 0, ≤ 1 000 000 (10 000 €), reçu en `mixed` et contrôlé par `Assert\Type` pour qu'un flottant soit refusé et jamais tronqué ; date : `Y-m-d`, pas postérieure à aujourd'hui en Europe/Paris (`NotInFuture`) ; categorySlug : catégorie existante (`ExistingCategory`) |
| `RejectionInput` | reason : au moins 5 caractères utiles (« Le motif doit être explicite ») |
| `PolicyInput` | maxAmountCents : entier > 0 ; receiptRequired : booléen |

La politique d'une catégorie (plafond, justificatif obligatoire) est portée par `Category`. `PolicyChecker` est le seul endroit côté serveur qui contrôle une note : plafond applicable (celui figé dans `Expense.limitAtApprovalCents` à la validation, sinon celui de la catégorie), écart en centimes (égal au plafond = conforme), justificatif obligatoire. Le front n'en duplique que le calcul du dépassement (`features/policies/checkPolicy.ts`), pour l'alerte à la saisie. Le refus de soumettre une note sans justificatif obligatoire arrive avec la feature 005.

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

Les catégories et leur politique (`GET /api/categories`) sont aussi validées par Zod à la réception (`features/expenses/categories.ts`), avec le même contrôle à la compilation contre le type généré. Les champs nullables y sont exigés (`.nullable()` sans `.optional()`) : le serveur les envoie toujours.

Les schémas Zod des formulaires reprennent les contraintes des DTO ci-dessus. L'alerte « hors politique » du formulaire a sa propre version de la règle de politique, testée, alimentée par les politiques renvoyées par `GET /api/categories`.
