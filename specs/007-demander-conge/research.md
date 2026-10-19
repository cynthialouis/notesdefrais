# Research: 007 — Demander un congé

Phase 0 de `/speckit-plan`. Pas d'inconnue technique : toutes les briques existent déjà dans le projet
(feature 003 pour le cycle de décision). Les décisions ci-dessous fixent comment les réutiliser, et
signalent les points où la spec laisse une marge d'interprétation.

## D1. Cycle de vie : un second Workflow, séparé de celui des notes

- **Decision** : nouveau workflow `leave_request` (`type: state_machine`, `marking_store: method`,
  propriété `status`) dans `config/packages/workflow.yaml`, à côté de `expense`. Places : `pending`
  (place initiale), `accepted`, `refused`, `withdrawn`, `cancelled`. Transitions : `accept`
  (pending → accepted), `refuse` (pending → refused), `withdraw` (pending → withdrawn), `cancel`
  (pending ou accepted → cancelled, C13).
- **Rationale** : la constitution (II) impose les transitions par le Workflow ; une transition
  impossible donne `can() === false`, traduite en 409 par le service, comme pour les notes (C10, C12, C14).
- **Alternatives considered** : généraliser `Expense` en « demande » commune — refusé : deux cycles
  différents (pas de brouillon, retrait, annulation) et un couplage entre deux domaines sans gain ;
  un simple champ `status` modifié à la main — refusé par la constitution.

## D2. Pas de place `draft`

- **Decision** : `initial_marking: pending` ; la création pose directement l'état « en attente ».
- **Rationale** : clarification de la spec (pas de brouillon, C1).
- **Alternatives considered** : aucune.

## D3. Motifs de refus et d'annulation : réutiliser `RejectionInput`

- **Decision** : `POST /refuse` et `POST /cancel` reçoivent `RejectionInput` (`reason`, 5 à 1000
  caractères après `UnicodeTrim`), dont le docblock est élargi. Côté front, `rejectionSchema`
  (`features/expenses/rejectionValidation.ts`) est réutilisé tel quel.
- **Rationale** : même règle que le rejet d'une note (C9 reprend 003 C5) ; un seul endroit par côté,
  donc aucune divergence possible entre les deux motifs.
- **Alternatives considered** : un `LeaveReasonInput` dédié — refusé : duplication de contraintes
  identiques ; renommer `RejectionInput` en `ReasonInput` — refusé ici : toucherait la feature 003
  sans nécessité.

## D4. Validation des dates

- **Decision** : `LeaveRequestInput` (`startDate`, `endDate`, chaînes `Y-m-d`) : `NotBlank` + `Date`
  sur chaque champ ; nouvelle contrainte `NotInPast` sur `startDate` (symétrique de `NotInFuture`,
  date du jour en Europe/Paris via `ClockInterface`) ; `#[Assert\Callback]` dans le DTO pour « fin
  pas antérieure au début », violation posée sur `endDate`. DTO reçu avec
  `#[MapRequestPayload(validationFailedStatusCode: 400)]` (C2).
- **Rationale** : même schéma que `ExpenseInput` / `NotInFuture` ; deux dates `Y-m-d` se comparent
  comme des chaînes.
- **Alternatives considered** : `#[Assert\Expression]` — refusé : exige `symfony/expression-language`,
  non installé (constitution VI) ; `GreaterThanOrEqual(propertyPath:)` — refusé : compare des chaînes
  sans garantir qu'elles sont des dates valides.

## D5. Ordre des refus à la création

- **Decision** : 400 (validation du DTO) → 409 sans responsable (C5) → 409 chevauchement (C4).
- **Rationale** : même ordre que la soumission d'une note (validation, puis état, puis responsable) ;
  le contenu est vérifié avant tout le reste.

## D6. Chevauchement (C4) : requête, sans verrou

- **Decision** : `LeaveRequestRepository::hasOverlap(User, start, end)` : existe-t-il une demande du
  même demandeur, `status IN (pending, accepted)`, avec `startDate <= :end AND endDate >= :start`
  (bornes comprises, donc un jour commun suffit).
- **Rationale** : une seule requête, testable par critère.
- **Alternatives considered** : `symfony/lock` autour de la création (recommandé par
  `backend/AGENTS.md` pour l'exclusion mutuelle) — **écarté pour ce POC** : il faudrait
  deux envois strictement simultanés du même utilisateur ; la feature 003 a fait le même choix pour
  les décisions simultanées (hors périmètre), et ce serait une nouvelle dépendance. **Validé le 19/10.**

## D7. Autorisations : Voter `LeaveRequestVoter`

- **Decision** : trois attributs sur une `LeaveRequest` :
  - `LEAVE_VIEW` : le demandeur ; tout le monde si la demande est acceptée ; le responsable du
    demandeur et l'admin si elle est en attente (C15, C17, C6, C18) ;
  - `LEAVE_PARTY` : le demandeur ou son responsable ;
  - `LEAVE_DECIDE` : le responsable du demandeur.

  Routes : accepter / refuser → `LEAVE_PARTY` en 404 puis `LEAVE_DECIDE` en 403 (403 pour le
  demandeur, 404 pour tout autre, comme 003) ; retirer → demandeur, 404 sinon ; annuler →
  `ROLE_ADMIN`, 403 sinon, puis 409 si la demande n'est ni en attente ni acceptée.
- **Rationale** : `LEAVE_VIEW` seul ne suffit pas pour accepter / refuser : un congé accepté est
  visible de tous, un tiers aurait alors 403 au lieu du 404 prévu par la spec.
- **Tranché le 19/10** : l'admin annule une demande en attente ou acceptée, et voit les demandes en
  attente de tous. Sur une demande refusée, retirée ou annulée d'un autre, il reçoit 409 (C14) et non
  404 : il en apprend l'existence, pas le contenu, ce qui est admis.

## D8. Listes

- **Decision** : quatre routes de liste plutôt qu'une route filtrée :
  - `GET /api/leaves` : congés acceptés en cours ou à venir de tous (`endDate >= aujourd'hui`, heure
    de Paris), date de début croissante (C15) ;
  - `GET /api/me/leaves` : toutes ses demandes (C16), début le plus récent d'abord (comme « Mes notes ») ;
  - `GET /api/team/leaves` : demandes en attente des subordonnés directs, `ROLE_MANAGER` sinon 403 (C6),
    date de début croissante ;
  - `GET /api/leaves/pending` : demandes en attente de tous, `ROLE_ADMIN` sinon 403 (C18), date de
    début croissante.
- **Rationale** : chaque route a une seule règle de visibilité, testable sans paramètre ; même schéma
  que `/api/expenses` et `/api/team/expenses`.
- **Tranché le 19/10** : les congés passés des autres ne sont pas montrés en 007 ; ils le seront avec
  le calendrier (009). C15 a été précisé dans la spec.

## D9. Vue renvoyée : ce que le lecteur peut faire

- **Decision** : `LeaveRequestView` construite pour un lecteur (comme `ExpenseView`) : `requester`,
  dates, `status`, `createdAt`, décision (`decidedAt`, `decidedBy`, `refusalReason`), annulation
  (`cancelledAt`, `cancelledBy`, `cancellationReason`), et `decidable`, `withdrawable`, `cancellable` (vrai pour l'admin sur une demande en attente ou acceptée).
  Champs nullables toujours présents, déclarés dans `#[OA\Schema(required: [...])]` et ajoutés à
  `ApiDocTest`.
- **Rationale** : la spec exclut de recalculer côté front ce que le lecteur peut faire.

## D10. Front

- **Decision** : `features/leaves/` : `leavesStore.ts` (Zustand, réponses validées par Zod et contrôlées
  à la compilation contre les types générés), `leaveFormValidation.ts` (seule copie front de la règle
  des dates, `todayIso` réutilisé de `expenseFormValidation.ts`), `LeaveStatusBadge` (`switch`
  exhaustif avec `never`), page `/conges` pour tous les rôles : formulaire de demande, « Mes demandes »,
  « Congés à venir », pour un manager « Demandes à traiter », pour l'admin « Demandes en attente
  (toutes équipes) » ; bouton « Annuler » de l'admin sur les demandes en attente et les congés acceptés. Champs de date en `<input type="date">` natif via `Field`.
- **Rationale** : aucune dépendance (constitution VI) ; une page unique suffit tant que le calendrier
  (009) n'existe pas.
- **Alternatives considered** : sélecteur de dates d'une librairie — refusé (dépendance, accessibilité
  du natif suffisante) ; demandes à traiter sur la page Équipe — refusé : mélangerait notes et congés
  dans une page déjà dense.
