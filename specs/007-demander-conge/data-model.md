# Data Model: 007 — Demander un congé

## Entité `LeaveRequest` (nouvelle)

| Champ | Type | Contraintes / remarques |
|---|---|---|
| id | int | clé |
| requester | `User` (ManyToOne, non null) | le demandeur ; son `manager` est le responsable qui décide |
| startDate | date (`date_immutable`) | journée entière, bornes comprises |
| endDate | date (`date_immutable`) | `>= startDate` |
| status | enum `LeaveStatus` | `pending` à la création |
| createdAt | instant UTC | posé à la création |
| decidedAt | instant UTC, null | posé à l'entrée dans `accepted` ou `refused` |
| decidedBy | `User`, null | idem |
| refusalReason | string ≤ 1000, null | seulement si `refused` |
| cancelledAt | instant UTC, null | posé à l'entrée dans `cancelled` |
| cancelledBy | `User`, null | l'admin |
| cancellationReason | string ≤ 1000, null | seulement si `cancelled` |

Instants stockés en UTC (setters qui convertissent, comme `Expense`). Index sur
`(requester_id, status)` pour le chevauchement et sur `(status, start_date)` pour les listes.
Migration générée par `make:migration`.

## Enum `LeaveStatus`

`pending`, `accepted`, `refused`, `withdrawn`, `cancelled` (valeurs = places du Workflow).
`blocksPeriod(): bool` vrai pour `pending` et `accepted` : seul endroit qui dit quelles demandes
comptent pour le chevauchement (C4).

## Cycle de vie (workflow `leave_request`, state_machine)

```
pending ──accept──▶ accepted ──cancel (admin, motif)──▶ cancelled
   │                                                       ▲
   ├──cancel (admin, motif) ───────────────────────────────┘
   │
   ├──refuse (motif)──▶ refused
   │
   └──withdraw (demandeur)──▶ withdrawn
```

- Transition impossible → `can()` faux → 409 (C10, C12, C14).
- Listener `LeaveDecidedListener` : entrée dans `accepted` / `refused` → `decidedAt`, `decidedBy`
  (via `CurrentActor`), `refusalReason` depuis le contexte (clé `reason`).
- Listener `LeaveCancelledListener` : entrée dans `cancelled` → `cancelledAt`, `cancelledBy`,
  `cancellationReason`.
- `refused`, `withdrawn` et `cancelled` sont finales.

## Entrées (DTO)

| DTO | Contraintes |
|---|---|
| `LeaveRequestInput` | `startDate` : `NotBlank`, `Date` (`Y-m-d`), `NotInPast` (Europe/Paris) ; `endDate` : `NotBlank`, `Date` ; `Callback` : `endDate >= startDate`, violation sur `endDate` |
| `RejectionInput` (existant) | `reason` : 5 à 1000 caractères après `UnicodeTrim` ; réutilisé pour refuser et annuler |

## Vue `LeaveRequestView`

`id`, `requester` (`PersonRef`), `startDate`, `endDate` (`Y-m-d`), `status`, `createdAt`,
`decidedAt`, `decidedBy`, `refusalReason`, `cancelledAt`, `cancelledBy`, `cancellationReason`
(nullables toujours présents), `decidable`, `withdrawable`, `cancellable` (booléens calculés pour
le lecteur).

## Types côté front

Générés depuis OpenAPI ; `leavesStore.ts` ajoute une union discriminée Zod sur `status` (un congé
`refused` sans `refusalReason`, ou `cancelled` sans `cancellationReason`, est impossible à
représenter), contrôlée à la compilation contre le type généré.

## Données de démo

Demandes relatives à la date du jour (`ClockInterface`) : pour chaque manager, au moins une demande
en attente de son équipe ; des congés acceptés à venir dans les deux équipes ; une demande refusée,
une retirée, une annulée ; une demande en attente d'un manager, à traiter par l'admin ; un congé accepté terminé hier
(absent de la liste des congés de tous).
