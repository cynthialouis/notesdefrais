# Modèle de données

Entités Doctrine dans `backend/src/Entity/`, schéma versionné par Doctrine Migrations.

## Entités

| Entité | Champs principaux | Remarques |
|---|---|---|
| `User` | id, name, email, role (`employee` / `manager` / `admin`), manager | Comptes de démo créés par les fixtures. Le `manager` est le responsable direct : les salariés ont leur manager, les managers l'admin, l'admin personne |
| `Category` | id, slug, label, maxAmountCents (null = aucun plafond), receiptRequired | repas, transport, hébergement, matériel, autre ; données de référence **insérées par la migration** (présentes dans tous les environnements, jamais purgées par les fixtures : `make db`) |
| `Expense` | id, owner, category, label, amountCents, date, status (enum `ExpenseStatus`), createdAt, submittedAt ; puis decidedAt, decidedBy, rejectionReason (003) | Montant en centimes entiers, jamais de flottant. `ExpenseStatus::isEditable()` est le seul endroit qui dit quels états se modifient et se suppriment |
| `Receipt` | id, originalName, mimeType, sizeBytes, storageName, uploadedAt | Un seul par note (`Expense.receipt`, un-à-un ; remplacer ou retirer supprime l'ancien). Fichier sur disque dans `var/receipts/<env>` (hors `public/`, ignoré par git), sous un nom généré par le serveur (`ReceiptStorage`) ; nom d'origine sans chemin, pour l'affichage ; type détecté d'après le contenu |
| `LeaveRequest` | id, requester, startDate, endDate (journées entières, bornes comprises), status (enum `LeaveStatus`), createdAt ; decidedAt, decidedBy, refusalReason ; cancelledAt, cancelledBy, cancellationReason (007) | Le responsable du `requester` décide. `LeaveStatus::blocksPeriod()` (vrai pour `pending` et `accepted`) est le seul endroit qui dit quelles demandes comptent pour le chevauchement. Pas d'historique séparé : décision et annulation sont posées sur la demande |
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

## Cycle de vie d'une demande de congé

```
pending ──accept──▶ accepted ──cancel (admin, motif)──▶ cancelled
   │                                                       ▲
   ├──cancel (admin, motif) ───────────────────────────────┘
   ├──refuse (motif)──▶ refused
   └──withdraw (demandeur)──▶ withdrawn
```

- Machine à états `leave_request` dans `workflow.yaml`, sans brouillon : la création pose `pending`. `refused`, `withdrawn` et `cancelled` sont finales ; une transition impossible est renvoyée en 409.
- `LeaveRequestVoter` : `LEAVE_PARTY` (demandeur ou son responsable), `LEAVE_DECIDE` (responsable du demandeur), `LEAVE_REQUESTER` (retrait). L'annulation est réservée à `ROLE_ADMIN`. La visibilité (voir `docs/api.md`) est réglée par les requêtes des listes.
- Listeners du Workflow : `LeaveDecidedListener` pose decidedAt, decidedBy et le motif d'un refus à l'entrée dans `accepted` ou `refused` ; `LeaveCancelledListener` pose cancelledAt, cancelledBy et le motif à l'entrée dans `cancelled`. Instants stockés en UTC, comme pour les notes.

## Entrées (back)

Un DTO `readonly` par entrée, validé par des attributs `#[Assert\...]` et reçu avec `#[MapRequestPayload]` :

| DTO | Contraintes |
|---|---|
| `ExpenseInput` | label : 2 à 80 caractères (après trim) ; amountCents : entier, > 0, ≤ 1 000 000 (10 000 €), reçu en `mixed` et contrôlé par `Assert\Type` pour qu'un flottant soit refusé et jamais tronqué ; date : `Y-m-d`, pas postérieure à aujourd'hui en Europe/Paris (`NotInFuture`) ; categorySlug : catégorie existante (`ExistingCategory`) |
| `RejectionInput` | reason : 5 à 1000 caractères après `UnicodeTrim` ; motif du rejet d'une note, du refus et de l'annulation d'une demande de congé |
| `LeaveRequestInput` | startDate : `Y-m-d`, pas antérieure à aujourd'hui en Europe/Paris (`NotInPast`) ; endDate : `Y-m-d`, pas antérieure à startDate (`Assert\Callback`, violation sur endDate) |
| `PolicyInput` | maxAmountCents : entier > 0 ; receiptRequired : booléen |

La politique d'une catégorie (plafond, justificatif obligatoire) est portée par `Category`. `PolicyChecker` est le seul endroit côté serveur qui contrôle une note : plafond applicable (celui figé dans `Expense.limitAtApprovalCents` à la validation, sinon celui de la catégorie), écart en centimes (égal au plafond = conforme), justificatif obligatoire. Le front n'en duplique que le calcul du dépassement (`features/policies/checkPolicy.ts`), pour l'alerte à la saisie. Il dit aussi si un justificatif obligatoire manque (`receiptMissing`) : `ExpenseManager::submit()` refuse alors la soumission (409, spec 005). Les fichiers sont effacés après l'écriture en base (remplacement, retrait, suppression de la note).

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

Les demandes de congé suivent le même principe (`features/leaves/leavesStore.ts`) : union discriminée sur `status`, où une demande refusée a toujours son motif et une demande annulée son auteur, sa date et son motif ; `decidable`, `withdrawable` et `cancellable` viennent du serveur. Le front ne duplique que la validation des dates (`leaveFormValidation.ts`) et la longueur du motif (`rejectionSchema`), pour l'erreur immédiate.

Les schémas Zod des formulaires reprennent les contraintes des DTO ci-dessus. L'alerte « hors politique » du formulaire a sa propre version de la règle de politique, testée, alimentée par les politiques renvoyées par `GET /api/categories`.
