# 006 — Tableau de bord

## Besoin

En arrivant dans l'application, chacun doit voir d'un coup d'œil où en sont ses dépenses : un salarié, combien il a en brouillon, en attente, validé ou rejeté ; un manager, ce que coûte son équipe, par catégorie et par mois, pour suivre son budget. Le manager doit aussi pouvoir récupérer les notes validées de son équipe dans un tableur, pour la comptabilité.

## Critères d'acceptation

- **C1** QUAND un utilisateur ouvre le tableau de bord, LE SYSTÈME DOIT afficher, pour chaque état (brouillon, soumise, validée, rejetée), le nombre de ses propres notes et leur montant total.
- **C2** LE SYSTÈME DOIT afficher chaque état, même sans note (0 note, 0,00 €).
- **C3** QUAND un manager ouvre le tableau de bord, LE SYSTÈME DOIT afficher en plus le total des notes validées des personnes dont il est le responsable direct, par catégorie, sur la période.
- **C4** QUAND un manager ouvre le tableau de bord, LE SYSTÈME DOIT afficher le total des notes validées de son équipe pour chaque mois de la période, y compris les mois sans note (0,00 €).
- **C5** LE SYSTÈME DOIT calculer tous les totaux côté serveur, en centimes entiers ; le front ne fait que les afficher.
- **C6** QUAND un manager demande l'export, LE SYSTÈME DOIT fournir un fichier CSV des notes validées de son équipe sur la période, une ligne par note.
- **C7** LE SYSTÈME DOIT produire un CSV lisible directement par un tableur français : séparateur point-virgule, encodage UTF-8 avec BOM, montants en euros avec virgule décimale, dates au format JJ/MM/AAAA.
- **C8** LE SYSTÈME DOIT exclure des totaux d'équipe et de l'export les notes du manager lui-même et celles des personnes qu'il n'encadre pas directement.
- **C9** SI l'équipe n'a aucune note validée sur la période, ALORS LE SYSTÈME DOIT l'indiquer par un message au lieu de tableaux vides, et l'export DOIT contenir seulement la ligne d'en-tête.

Période (C3, C4, C6) : les 12 derniers mois civils, mois en cours compris, d'après la date de la dépense (et non la date de validation), en heure de Paris.

Colonnes de l'export (C6) : date de la dépense, salarié, libellé, catégorie, montant, date de validation, validée par.

## Autorisations

| Action | Qui peut | Sinon |
|---|---|---|
| Ses propres totaux par état | connecté | 401 |
| Totaux de l'équipe, export CSV | manager et plus (sur ses subordonnés directs) | 401 non connecté ; 403 salarié |

## Cas limites et erreurs

- Utilisateur sans aucune note : tous les états à 0.
- Note validée datée d'il y a 12 mois pile (premier jour du mois le plus ancien de la période) : incluse ; datée du mois précédent : exclue.
- Libellé contenant un point-virgule, un guillemet ou un retour à la ligne : échappé dans le CSV, la ligne reste une seule note.
- Libellé commençant par `=`, `+`, `-` ou `@` : neutralisé dans le CSV (injection de formule dans le tableur).
- Admin : responsable des managers, il voit leurs totaux d'équipe comme un manager voit ceux de ses salariés.

## Règles recalculées côté front

Aucune règle métier. Les totaux, la période et le contenu du CSV viennent du serveur ; le front formate les montants en euros et calcule seulement l'échelle des barres (plus grand total de la série), décorative et toujours accompagnée de la valeur écrite. *Précisé le 13/10 après la relecture à froid.*

## Hors périmètre

Graphiques interactifs et bibliothèque de graphiques (barres dessinées en CSS dans un tableau), choix de la période, filtres, totaux de l'équipe hors validées (en attente, rejetées), statistiques hors politique ou justificatifs, équipe indirecte (subordonnés des subordonnés), export d'autres formats (Excel, PDF), envoi automatique à la comptabilité.

## Questions ouvertes

Aucune. Tranchées le 13/10 : période fixe des 12 derniers mois civils, mois en cours compris (C3, C4, C6) ; mois de la date de la dépense ; tableau accessible avec barres en CSS, sans bibliothèque de graphiques ; totaux d'équipe sur les seules notes validées ; CSV au format de C7.

## Écarts avec les docs

- `docs/api.md` : `GET /stats` « salarié / manager » et `GET /exports/expenses.csv` « manager » ; à préciser au plan (une ou deux routes de statistiques, admin inclus comme manager).
- `docs/produit.md` : « totaux de l'équipe » ne précisait ni la période ni les états comptés : 12 derniers mois civils, notes validées.

## Plan

- **Back** : `StatsPeriod` (12 mois civils depuis l'horloge, Europe/Paris) ; `ExpenseRepository` : totaux par état d'un propriétaire, notes validées des subordonnés directs sur la période ; service `DashboardStats` (états, mois et catégories toujours présents, à 0 si besoin). Routes `GET /api/stats/me` (connecté), `GET /api/stats/team` et `GET /api/exports/team-expenses.csv` (manager et plus, 403 sinon). Service `TeamExpensesCsv` (BOM, `;`, échappement, neutralisation des formules). Démo : notes validées sur plusieurs mois, une hors période. Aucune dépendance.
- **Front** : `statsApi.ts` (Zod, contrôle contre le contrat) ; composant `ui/Bar` (barre décorative, valeur en texte) ; tableau de bord : cartes par état pour tous, partie équipe pour les managers (par catégorie, par mois, export).

## Tâches

1. `StatsPeriod` et requêtes d'agrégats (C3, C4, C8)
2. `DashboardStats` (C1 à C5, C8)
3. Routes `stats/me` et `stats/team`, vues (C1 à C5, autorisations)
4. Export CSV (C6 à C9)
5. Données de démo (C3, C4)
6. Types, `statsApi.ts`
7. Composant `ui/Bar`
8. Tableau de bord : partie personnelle (C1, C2)
9. Tableau de bord : partie équipe et export (C3, C4, C6, C9)
