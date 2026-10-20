# Produit

Application « Espace salarié » pour trois rôles (salarié, manager, admin) : notes de frais, puis congés.

## Features (dans l'ordre de construction)

| N° | Feature | Rôle | Résumé |
|---|---|---|---|
| 001 | Connexion par rôle | tous | Mode démo : choix d'un compte de démo → session. Pas de mot de passe. |
| 002 | Soumettre une note | salarié | Brouillon → soumission ; libellé, montant, date, catégorie. |
| 003 | Valider / rejeter | manager | Uniquement les notes soumises de son équipe ; rejet avec motif obligatoire ; historique des décisions. |
| 004 | Politique de dépenses | admin | Plafond par catégorie, justificatif obligatoire ou non. Une note hors politique est signalée au salarié et au manager. |
| 005 | Justificatifs | salarié | Upload d'une image ou d'un PDF sur une note (type et taille validés). |
| 006 | Tableau de bord | salarié, manager | Salarié : ses totaux par statut. Manager : totaux de l'équipe par catégorie et par mois, export CSV des notes validées. |
| 007 | Demander un congé | tous | Demande d'une période (sans brouillon), acceptée ou refusée avec motif par le responsable direct, retirée tant qu'elle est en attente ; congés acceptés visibles de tous ; annulation motivée par l'admin. |

Chaque feature a sa spec dans `specs/NNN-nom/spec.md` (voir [methode.md](methode.md)).

## Hors périmètre

Vraie authentification (mot de passe, OAuth), remboursement / paiement, multi-devises, OCR des justificatifs, notifications email, déploiement en production. Congés (voir la spec 007) : solde, décompte des jours et types de congé (008), calendrier et congés passés des autres (009), demi-journées, jours ouvrés et jours fériés, modification d'une demande, délégation.

## Rôle de chaque techno

| Techno | Rôle |
|---|---|
### Front (`frontend/`)

| Techno | Rôle |
|---|---|
| React 19 | `useActionState` pour tous les formulaires, `useOptimistic` sur valider/rejeter (bonus), balises `<title>` / `<meta>` natives. |
| TypeScript | Types générés depuis OpenAPI, union discriminée sur le statut, `switch` exhaustif avec `never`, zéro `any`, montants en centimes. |
| Zod | Validation des formulaires pour l'UX ; types déduits avec `z.infer`, jamais réécrits. |
| Zustand | Session courante (utilisateur, rôle) et cache des notes ; sélecteurs fins ; actions async qui appellent l'API. |
| Idiomes React | Totaux, listes filtrées et alerte « hors politique » calculés directement pendant le rendu, sans `useEffect`. |

### Back (`backend/`)

| Techno | Rôle |
|---|---|
| Symfony 8 | API REST : contrôleurs fins, DTO reçus avec `#[MapRequestPayload]`. |
| Validator | Validation de toute entrée : source de vérité, le front n'est qu'un confort. |
| Security + Voters | Session de démo et autorisation sur chaque ressource. |
| Workflow | Machines à états d'une note et d'une demande de congé ; transition impossible refusée (409). |
| Doctrine + SQLite | Entités, migrations versionnées, base locale sans installation. |
| NelmioApiDocBundle | Spec OpenAPI d'où le front génère ses types. |

## Écrans

| Route | Rôle | Contenu |
|---|---|---|
| `/login` | tous | Choix du compte de démo |
| `/` | tous | Tableau de bord : ses totaux par état ; pour un manager, notes validées de l'équipe par catégorie et par mois sur 12 mois, export CSV |
| `/notes` | salarié et plus | Mes notes, filtre par statut (state local), bouton « Nouvelle note » |
| `/notes/nouvelle`, `/notes/:id` | salarié | Formulaire (`useActionState` + `expenseInputSchema.safeParse`), justificatif, alerte hors politique calculée en direct |
| `/equipe` | manager et plus | Notes soumises de l'équipe, valider / rejeter (motif), historique |
| `/politique` | admin | Plafonds par catégorie |
| `/conges` | tous | Demander un congé et Mes demandes (retrait si en attente), sauf pour l'admin, qui n'a pas de responsable, Congés à venir de tous ; pour un manager, Demandes à traiter (accepter / refuser avec motif) ; pour l'admin, Demandes en attente (toutes équipes) et annulation motivée d'une demande en attente ou d'un congé accepté |

Menu du header par domaine : « Tableau de bord », « Notes de frais ▾ » (groupe dépliant : Mes notes, Équipe, Politique, chaque lien filtré par rôle), « Congés » (`app/NavMenu.tsx`). Rôles hiérarchisés (admin > manager > salarié) : un rôle accède aux pages des rôles inférieurs. Routes protégées par rôle via un composant `RequireRole`. Badge de statut = `switch` exhaustif avec `never`.
