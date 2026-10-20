# Contrat API : 007 — Demander un congé

Routes sous `/api`, session obligatoire (401 sinon), erreurs au format unique
`{ error: { code, message, fields? } }`. Le contrat qui fait foi reste la spec OpenAPI générée
(`/api/doc.json`) ; les types du front en sont régénérés par `make api-types`.

Corps de réponse : `LeaveRequestView` (voir [data-model.md](../data-model.md)) ou un tableau de vues.

| Méthode | Route | Qui | Succès | Erreurs |
|---|---|---|---|---|
| GET | `/leaves` | connecté | 200, congés acceptés en cours ou à venir de tous, date de début croissante (C15) | 401 |
| GET | `/leaves/pending` | admin | 200, demandes en attente de tous, date de début croissante (C18) | 401 ; 403 non-admin |
| GET | `/me/leaves` | connecté | 200, toutes ses demandes, date de début décroissante (C16) | 401 |
| GET | `/team/leaves` | manager et plus | 200, demandes en attente des subordonnés directs, date de début croissante (C6) | 401 ; 403 salarié |
| POST | `/leaves` | connecté | 201, demande `pending` (C1) | 400 `fields.startDate` / `fields.endDate` (C2) ; 409 sans responsable (C5) ; 409 chevauchement (C4) |
| POST | `/leaves/{id}/accept` | responsable du demandeur | 200, demande `accepted` (C7) | 403 demandeur ; 404 tout autre ; 409 pas en attente (C10) |
| POST | `/leaves/{id}/refuse` | responsable du demandeur | 200, demande `refused` (C8) | 400 `fields.reason` (C9) ; 403 / 404 / 409 comme ci-dessus |
| POST | `/leaves/{id}/withdraw` | demandeur | 200, demande `withdrawn` (C11) | 404 tout autre ; 409 pas en attente (C12) |
| POST | `/leaves/{id}/cancel` | admin | 200, demande `cancelled` (C13) | 400 `fields.reason` (C9) ; 403 non-admin ; 409 ni en attente ni acceptée (C14) |

Corps d'entrée :
- `POST /leaves` : `{ "startDate": "2026-11-02", "endDate": "2026-11-06" }`
- `POST /leaves/{id}/refuse`, `POST /leaves/{id}/cancel` : `{ "reason": "Période de clôture" }`

Ordre des contrôles à la création : 400, puis 409 sans responsable, puis 409 chevauchement.
Une demande inexistante répond 404 sur toutes les routes `{id}`.
