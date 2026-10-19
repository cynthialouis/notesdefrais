# Tasks: 007 — Demander un congé

**Input**: Design documents from `/specs/007-demander-conge/`

**Prerequisites**: plan.md, spec.md, research.md (D1 à D10), data-model.md, contracts/api.md, quickstart.md

**Tests**: obligatoires (constitution III). Tous les tests du back sont écrits en T001 par le subagent
`testeur-spec`, avant toute implémentation, et ne sont plus modifiés ensuite ; chaque tâche
d'implémentation du back les fait passer. Les tests Vitest + Testing Library du front sont écrits dans
la tâche front qui les concerne.

**Organization**: écart assumé avec le modèle de Spec Kit, qui fait un incrément complet (back + front)
par user story : la constitution impose `backend/` puis `frontend/`. Les tâches sont donc groupées par
user story **à l'intérieur** de la partie back, puis de la partie front. Une tâche = un commit
`feat(007): tâche N – description` (`docs(007)`, `test(007)` selon le cas).

## Format: `[ID] [P?] [Story] Description`

- **[P]** : peut être faite dans n'importe quel ordre par rapport aux autres [P] de la même phase (fichiers distincts, pas de dépendance)
- **[Story]** : user story de la spec (US1 demander, US2 décider, US3 voir les congés, US4 annuler)
- Les critères couverts sont indiqués entre parenthèses.

## Path Conventions

`backend/src/`, `backend/tests/`, `backend/config/`, `backend/migrations/` ; `frontend/src/`, `frontend/e2e/`.

---

## Phase 1: Tests du back (étape F4a)

**Purpose**: traduire les 18 critères en tests PHPUnit qui échouent, avant tout code.

- [X] T001 Lancer le subagent `testeur-spec` sur `specs/007-demander-conge/` : un fichier de test par route dans `backend/tests/Controller/Leave/` (`CreateLeaveTest`, `MyLeavesTest`, `WithdrawLeaveTest`, `TeamLeavesTest`, `AcceptLeaveTest`, `RefuseLeaveTest`, `AcceptedLeavesTest`, `PendingLeavesTest`, `CancelLeaveTest`), `LeaveRequestView` ajoutée à la liste de `backend/tests/Controller/ApiDocTest.php`, test des données de démo dans `backend/tests/DataFixtures/LeaveFixturesTest.php`, aides de création de demandes dans `backend/tests/Support/ApiTestCase.php` ; tests nommés d'après les critères (`testC4…`) ; vérifier qu'ils échouent pour la bonne raison et que `git diff --stat` ne montre que `backend/tests/` ; commit `test(007): tests depuis la spec` (C1 à C18)

**Checkpoint**: `make test-back ARGS="--filter Leave"` échoue sur des classes ou routes absentes, pas sur une erreur des tests.

---

## Phase 2: Fondations du back (bloquant pour toutes les user stories)

**Purpose**: modèle, cycle de vie, autorisations et vue, communs aux quatre user stories.

- [X] T002 Enum `LeaveStatus` (`pending`, `accepted`, `refused`, `withdrawn`, `cancelled`, méthode `blocksPeriod()` vraie pour `pending` et `accepted`) dans `backend/src/Enum/LeaveStatus.php` ; entité `LeaveRequest` dans `backend/src/Entity/LeaveRequest.php` avec `requester` (ManyToOne `User`, non null), `startDate` et `endDate` (`date_immutable`), `status`, `createdAt`, `decidedAt` / `decidedBy` / `refusalReason` (string ≤ 1000, null), `cancelledAt` / `cancelledBy` / `cancellationReason` (string ≤ 1000, null), instants stockés en UTC comme `Expense`, `isRequestedBy(User)` et `isDecidedBy(User)` (responsable direct du demandeur) ; `LeaveRequestRepository` vide ; migration générée par `php bin/console make:migration` dans `backend/migrations/`, index `(requester_id, status)` et `(status, start_date)` (C1)
- [X] T003 Workflow `leave_request` (`state_machine`, `marking_store: method`, propriété `status`, `initial_marking: pending`, transitions `accept` pending→accepted, `refuse` pending→refused, `withdraw` pending→withdrawn, `cancel` [pending, accepted]→cancelled) dans `backend/config/packages/workflow.yaml` ; listeners `backend/src/Workflow/LeaveDecidedListener.php` (entrée dans `accepted` / `refused` : `decidedAt`, `decidedBy` via `CurrentActor`, `refusalReason` depuis le contexte `reason`) et `backend/src/Workflow/LeaveCancelledListener.php` (entrée dans `cancelled` : `cancelledAt`, `cancelledBy`, `cancellationReason`) (C7, C8, C13)
- [X] T004 [P] `LeaveRequestVoter` dans `backend/src/Security/Voter/LeaveRequestVoter.php` : `LEAVE_VIEW` (demandeur ; tous si `accepted` ; responsable du demandeur ou admin si `pending`), `LEAVE_PARTY` (demandeur ou son responsable), `LEAVE_DECIDE` (responsable du demandeur), `LEAVE_REQUESTER` (demandeur) (C17, autorisations)
- [X] T005 [P] Vue `LeaveRequestView` dans `backend/src/Dto/LeaveRequestView.php`, construite pour un lecteur : `id`, `requester` (`PersonRef`), `startDate`, `endDate` (`Y-m-d`), `status`, `createdAt`, `decidedAt`, `decidedBy`, `refusalReason`, `cancelledAt`, `cancelledBy`, `cancellationReason` (nullables toujours présents, déclarés dans `#[OA\Schema(required: [...])]`), `decidable`, `withdrawable`, `cancellable` (vrai pour l'admin si `pending` ou `accepted`) ; `LeaveManager` vide dans `backend/src/Service/LeaveManager.php` (workflow `leave_request` injecté) (C16, C17)

**Checkpoint**: `php bin/console lint:container` passe ; `ApiDocTest` passe pour `LeaveRequestView` dès qu'une route l'expose (T009).

---

## Phase 3: User Story 1 — Demander un congé et suivre sa demande (Priority: P1), back 🎯 MVP

**Goal**: un salarié ou un manager crée une demande, la voit, la retire tant qu'elle est en attente.

**Independent Test**: `make test-back ARGS="--filter 'CreateLeave|MyLeaves|WithdrawLeave'"`.

- [X] T006 [P] [US1] Contrainte `NotInPast` (date `Y-m-d` pas antérieure à aujourd'hui en Europe/Paris, via `ClockInterface`, symétrique de `NotInFuture`) dans `backend/src/Validator/NotInPast.php` et `NotInPastValidator.php` ; DTO `LeaveRequestInput` dans `backend/src/Dto/LeaveRequestInput.php` : `startDate` (`NotBlank`, `Date`, `NotInPast`), `endDate` (`NotBlank`, `Date`), `#[Assert\Callback]` « la fin ne peut pas être antérieure au début » posé sur `endDate` (C2)
- [X] T007 [P] [US1] `LeaveRequestRepository::hasOverlap(User, start, end)` (même demandeur, `status IN (pending, accepted)`, `startDate <= :end AND endDate >= :start`) et `findForRequester(User)` (date de début décroissante) dans `backend/src/Repository/LeaveRequestRepository.php` (C4, C16)
- [X] T008 [US1] `LeaveManager::request(User, LeaveRequestInput)` (refus 409 sans responsable, puis 409 si chevauchement, messages explicites via `PublicHttpException::conflict`) et `withdraw(LeaveRequest)` (409 si `can()` est faux) dans `backend/src/Service/LeaveManager.php` (C1, C4, C5, C11, C12)
- [X] T009 [US1] `LeaveController` dans `backend/src/Controller/LeaveController.php` : `POST /api/leaves` (`#[MapRequestPayload(validationFailedStatusCode: 400)]`, 201), `GET /api/me/leaves`, `POST /api/leaves/{id}/withdraw` (`LEAVE_REQUESTER`, 404 sinon), réponses annotées pour OpenAPI (C1, C2, C4, C5, C11, C12, C16)

**Checkpoint**: tests US1 verts ; `make test-back` vert sur l'existant.

---

## Phase 4: User Story 2 — Accepter ou refuser les demandes de son équipe (Priority: P1), back

**Goal**: le responsable direct liste et décide les demandes en attente de son équipe.

**Independent Test**: `make test-back ARGS="--filter 'TeamLeaves|AcceptLeave|RefuseLeave'"`.

- [ ] T010 [US2] `LeaveRequestRepository::findPendingForResponsible(User)` (date de début croissante) ; `LeaveManager::accept()` et `refuse(LeaveRequest, string $reason)` (409 si `can()` est faux) ; docblock de `backend/src/Dto/RejectionInput.php` élargi aux motifs de refus et d'annulation de congé (C6, C7, C8, C10)
- [ ] T011 [US2] Routes `GET /api/team/leaves` (`ROLE_MANAGER`, 403 sinon), `POST /api/leaves/{id}/accept` et `POST /api/leaves/{id}/refuse` (`RejectionInput`, `LEAVE_PARTY` en 404 puis `LEAVE_DECIDE` en 403) dans `backend/src/Controller/LeaveController.php` (C6 à C10)

**Checkpoint**: tests US1 et US2 verts.

---

## Phase 5: User Story 3 — Voir qui est en congé (Priority: P2), back

**Goal**: tout utilisateur connecté voit les congés acceptés en cours ou à venir de tous.

**Independent Test**: `make test-back ARGS="--filter AcceptedLeaves"`.

- [ ] T012 [US3] `LeaveRequestRepository::findAcceptedEndingFrom(\DateTimeImmutable $today)` (`status = accepted`, `endDate >= :today`, date de début croissante, « aujourd'hui » en Europe/Paris via `ClockInterface`) et route `GET /api/leaves` dans `backend/src/Controller/LeaveController.php` (C15, C17)

**Checkpoint**: tests US1 à US3 verts.

---

## Phase 6: User Story 4 — Annuler une demande ou un congé (Priority: P3), back

**Goal**: l'admin voit les demandes en attente de tous et annule une demande en attente ou un congé accepté.

**Independent Test**: `make test-back ARGS="--filter 'PendingLeaves|CancelLeave'"`.

- [ ] T013 [US4] `LeaveRequestRepository::findAllPending()` (date de début croissante) ; `LeaveManager::cancel(LeaveRequest, string $reason)` (409 si ni en attente ni acceptée) ; routes `GET /api/leaves/pending` et `POST /api/leaves/{id}/cancel` (`RejectionInput`), `ROLE_ADMIN` sinon 403, dans `backend/src/Controller/LeaveController.php` (C9, C13, C14, C18)

**Checkpoint**: tous les tests `Leave` verts.

---

## Phase 7: Transverse du back

- [ ] T014 Données de démo des congés dans `backend/src/DataFixtures/AppFixtures.php`, relatives à la date du jour : au moins une demande en attente par équipe, des congés acceptés à venir dans les deux équipes, une refusée, une retirée, une annulée, une demande en attente d'un manager (à traiter par l'admin), un congé accepté terminé hier ; `make db` puis `make test-back` (tous les tests, dont `LeaveFixturesTest`) ; `php bin/console lint:container` (C6, C15, C18)
- [ ] T015 Régénérer les types du front : `make api-types` (`frontend/src/api/openapi.json`, `frontend/src/api/schema.d.ts`), puis `npx tsc -b` dans `frontend/`

**Checkpoint**: back terminé, contrat publié.

---

## Phase 8: Fondations du front

- [ ] T016 [P] `frontend/src/features/leaves/leavesStore.ts` (Zustand) : réponses validées par une union discriminée Zod sur `status` (`refused` exige `refusalReason`, `cancelled` exige `cancellationReason`), contrôlée à la compilation contre le type généré ; actions `fetchMine`, `fetchAccepted`, `fetchTeam`, `fetchAllPending`, `request`, `withdraw`, `accept`, `refuse`, `cancel` ; tests dans `leavesStore.test.ts` (C1, C6, C15, C16, C18)
- [ ] T017 [P] `frontend/src/features/leaves/leaveFormValidation.ts` : schéma Zod des dates (fin pas antérieure au début, début pas antérieur à `todayIso()` de `features/expenses/expenseFormValidation.ts`), seule copie front de la règle ; tests dans `leaveFormValidation.test.ts`, mêmes cas limites que la spec (début aujourd'hui accepté, hier refusé, un seul jour accepté) (C3)
- [ ] T018 `LeaveStatusBadge` (`switch` exhaustif avec `never`) dans `frontend/src/features/leaves/LeaveStatusBadge.tsx` ; route `/conges` pour tous les rôles dans `frontend/src/app/AppRoutes.tsx`, lien « Congés » dans `frontend/src/app/AppLayout.tsx` ; page `LeavesPage.tsx` vide ; test de route dans `frontend/src/app/AppRoutes.test.tsx`

---

## Phase 9: User Story 1 — front

- [ ] T019 [US1] Dans `frontend/src/features/leaves/LeavesPage.tsx` : formulaire « Demander un congé » (`useActionState`, `Field` avec `<input type="date">`, erreurs par champ avant envoi, erreurs 409 du serveur en `Alert`), section « Mes demandes » (`Table`, badge, motif d'un refus ou d'une annulation, bouton « Retirer » si `withdrawable`), `EmptyState` sans demande ; tests dans `LeavesPage.test.tsx` (C1, C3, C4, C5, C11, C12, C16)

---

## Phase 10: User Story 2 — front

- [ ] T020 [US2] Section « Demandes à traiter » pour les managers (rôle lu dans la session) dans `frontend/src/features/leaves/LeavesPage.tsx` : boutons « Accepter » et « Refuser » si `decidable`, motif validé par `rejectionSchema` de `features/expenses/rejectionValidation.ts` avant envoi, `EmptyState` si vide ; tests dans `LeavesPage.test.tsx` (C6 à C10)

---

## Phase 11: User Story 3 — front

- [ ] T021 [US3] Section « Congés à venir » (personne, dates, date de début croissante) dans `frontend/src/features/leaves/LeavesPage.tsx`, `EmptyState` si vide ; tests dans `LeavesPage.test.tsx` (C15, C17)

---

## Phase 12: User Story 4 — front

- [ ] T022 [US4] Pour l'admin : section « Demandes en attente (toutes équipes) » et bouton « Annuler » avec motif (`rejectionSchema`) sur les demandes en attente et les congés acceptés quand `cancellable`, dans `frontend/src/features/leaves/LeavesPage.tsx` ; tests dans `LeavesPage.test.tsx` (C9, C13, C14, C18)

**Checkpoint**: `npx tsc -b`, `npm run lint`, `npm run test` verts dans `frontend/`.

---

## Phase 13: Finition et étapes F5 à F8

- [ ] T023 Parcours e2e `frontend/e2e/conges.spec.ts` : un salarié demande un congé, son manager l'accepte, un salarié de l'autre équipe le voit dans « Congés à venir », l'admin l'annule et il disparaît ; `make test-e2e ARGS="conges"` (SC-002)
- [ ] T024 [P] Docs : `docs/modele-donnees.md` (entité `LeaveRequest`, cycle de vie, `LeaveRequestInput`), `docs/api.md` (8 routes de `contracts/api.md`), `docs/produit.md` (feature 007, écran `/conges`), `CLAUDE.md` (état actuel : feature 007) ; commit `docs(007): …`
- [ ] T025 F5 — Vérification à la main par Cynthia, en suivant `specs/007-demander-conge/quickstart.md` (aucun commit avant son retour)
- [ ] T026 F6 — `/revue-api` et `/revue-react` sur la branche ; appliquer les remarques retenues, justifier les autres, ligne dans `JOURNAL-IA.md` pour chaque écart
- [ ] T027 F7 — Subagent `relecteur` sur `specs/007-demander-conge/` ; corriger les problèmes signalés
- [ ] T028 F8 — `make test-e2e` (tous les parcours), puis fusion `git switch main && git merge --no-ff feat/007-demander-conge` après accord de Cynthia, puis `make test` sur `main`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (tests)** : aucune ; bloque tout le reste.
- **Phase 2 (fondations du back)** : après T001 ; bloque les phases 3 à 6.
- **Phases 3 à 6 (back par user story)** : dans l'ordre de priorité ; US2, US3 et US4 réutilisent `LeaveController` et `LeaveManager` créés en US1, donc séquentielles.
- **Phase 7** : après les phases 3 à 6 (les données de démo couvrent tous les états ; les types exigent toutes les routes).
- **Phase 8 (fondations du front)** : après T015.
- **Phases 9 à 12 (front par user story)** : après la phase 8, séquentielles (même fichier `LeavesPage.tsx`).
- **Phase 13** : T023 et T024 après la phase 12 ; T025 → T026 → T027 → T028 dans cet ordre.

### Within Each User Story

- Tests du back déjà écrits (T001) : chaque tâche commence par les lancer pour montrer qu'ils échouent.
- Repository et DTO avant service, service avant contrôleur.
- Front : store et validation avant les sections de la page.

### Parallel Opportunities

- T004 et T005 (voter, vue) : fichiers distincts.
- T006 et T007 (validation, repository).
- T016 et T017 (store, validation du formulaire).
- T024 (docs) en parallèle de T023 (e2e).

En pratique, un seul agent travaille une tâche à la fois (constitution III) : [P] indique seulement
que l'ordre entre ces tâches est libre.

---

## Parallel Example: User Story 1

```bash
# Ordre libre entre ces deux tâches :
Task: "T006 NotInPast et LeaveRequestInput dans backend/src/Validator/ et backend/src/Dto/"
Task: "T007 hasOverlap et findForRequester dans backend/src/Repository/LeaveRequestRepository.php"
```

---

## Implementation Strategy

### MVP (User Stories 1 et 2)

1. Phases 1 et 2, puis US1 et US2 côté back (T001 à T011).
2. T014 et T015, puis front des US1 et US2 (T016 à T020).
3. **Point d'arrêt possible** : une demande peut être posée, acceptée ou refusée de bout en bout.

### Livraison incrémentale

US3 (voir les congés) puis US4 (annulation par l'admin) s'ajoutent sans toucher aux précédentes ;
la fusion n'a lieu qu'une fois les 18 critères couverts et `make test-e2e` vert (constitution III).

---

## Notes

- Ne jamais modifier un test du back pour le faire passer : s'il paraît faux, s'arrêter et expliquer.
- Après chaque tâche : back → `make test-back` et `php bin/console lint:container` ; front → `npx tsc -b`, `npm run lint`, `npm run test`.
- Tout écart entre le code et la spec est signalé avant d'aller plus loin.
