# 004 — Politique de dépenses

## Besoin

L'entreprise fixe des règles de dépense par catégorie : un plafond par note, et l'obligation ou non de joindre un justificatif. Les dépenses hors règles doivent être repérées avant validation, sans être interdites : le salarié est prévenu dès la saisie, et le manager voit le dépassement au moment de décider. Seul l'admin règle la politique.

## Critères d'acceptation

- **C1** QUAND l'admin ouvre la page Politique, LE SYSTÈME DOIT afficher pour chaque catégorie son plafond (ou « aucun ») et si un justificatif est obligatoire.
- **C2** QUAND l'admin enregistre le plafond et l'obligation de justificatif d'une catégorie, LE SYSTÈME DOIT les appliquer à tous les contrôles suivants.
- **C3** SI le plafond saisi n'est pas un montant strictement positif d'au plus 10 000,00 €, ALORS LE SYSTÈME DOIT le refuser (400) en le signalant sur le champ ; un plafond vide signifie « aucun plafond ».
- **C4** QUAND un salarié saisit dans le formulaire un montant supérieur au plafond de la catégorie choisie, LE SYSTÈME DOIT afficher une alerte « hors politique » immédiatement, sans bloquer l'enregistrement ni la soumission.
- **C5** LE SYSTÈME DOIT considérer un montant égal au plafond comme conforme.
- **C6** QUAND une note dépasse le plafond de sa catégorie, LE SYSTÈME DOIT la signaler « hors politique » avec la raison (plafond dépassé, et de combien) à son propriétaire et à son responsable, dans les listes et sur le détail.
- **C7** LE SYSTÈME DOIT calculer ce signalement côté serveur, indépendamment de l'alerte affichée par le formulaire.
- **C8** QUAND une catégorie exige un justificatif, LE SYSTÈME DOIT l'indiquer dans le formulaire de note.
- **C9** TANT QU'une note n'est pas validée, LE SYSTÈME DOIT calculer son signalement avec la politique en vigueur ; une note validée DOIT garder le signalement qu'elle avait au moment de sa validation.
- **C10** LE SYSTÈME DOIT fournir des politiques de départ dans les données de démo : repas 50 €, hébergement 150 € avec justificatif, transport 300 €, matériel 500 € avec justificatif, « autre » sans plafond.

## Autorisations

| Action | Qui peut | Sinon |
|---|---|---|
| Lire les politiques (avec les catégories) | connecté | 401 |
| Modifier la politique d'une catégorie | admin | 401 non connecté ; 403 manager ou salarié |
| Voir le signalement d'une note | ceux qui peuvent lire la note (propriétaire, son responsable) | selon les règles de 003 |

## Cas limites et erreurs

- Plafond de 0 ou négatif : refusé (400) ; plafond de 10 000,00 € : accepté ; catégorie sans plafond : aucune alerte.
- Catégorie inconnue : 404.
- Plafond abaissé après coup : les notes non validées sont signalées selon le nouveau plafond (C9), les notes validées ne changent pas.

## Règles recalculées côté front

- Dépassement de plafond (montant > plafond de la catégorie) : pour l'alerte immédiate de C4 ; testé des deux côtés, le serveur fait foi (C7).

## Hors périmètre

Plafonds par personne ou par équipe, plafonds cumulés (par mois), historique des politiques, refus automatique d'une note hors plafond, dépôt des justificatifs et refus de soumettre une note sans justificatif obligatoire (feature 005).

## Questions ouvertes

Aucune. Tranchées le 12/10 : l'obligation de justificatif se règle et s'affiche en 004, le blocage de soumission arrive avec 005 ; signalement recalculé tant que la note n'est pas validée, figé à la validation (C9) ; politiques de départ (C10).

## Écarts avec les docs

- `docs/api.md` : `GET /categories` renvoie désormais la politique associée (prévu, reporté depuis 002) ; route de modification `PUT /policies/{categoryId}` à préciser au plan.

## Plan

- **Back** : la politique est portée par `Category` (`maxAmountCents` nullable, `receiptRequired`) ; `Expense.limitAtApprovalCents` fige le plafond à la validation. Service `PolicyChecker` (plafond applicable, écart, justificatif obligatoire ; égal = conforme). `ExpenseView.policy` et `CategoryView` avec la politique. `PUT /api/policies/{slug}` (admin, `PolicyInput`). Démo : politiques de C10 et notes hors plafond.
- **Front** : `checkPolicy.ts` (seule version front du dépassement) ; alerte en direct et aide « justificatif obligatoire » dans le formulaire ; `PolicyBadge` dans les listes, alerte sur le détail ; page Politique éditable par ligne.

## Tâches

1. Modèle : politique sur `Category`, plafond figé sur `Expense` (C2, C9)
2. `PolicyChecker` et figement à la validation (C5, C7, C9)
3. Vues : `policy` dans `ExpenseView`, politique dans `CategoryView` (C6, C8)
4. `PUT /api/policies/{slug}` et `PolicyInput` (C2, C3)
5. Données de démo (C10)
6. Types, `checkPolicy.ts`, store (C4, C5)
7. Formulaire : alerte en direct, justificatif obligatoire (C4, C8)
8. Signalement dans les listes et sur le détail (C6)
9. Page Politique (C1, C2, C3)
