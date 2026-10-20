# Feature Specification: 005 — Justificatifs

**Feature Branch**: `feat/005-justificatifs`

**Created**: 2026-10-13

**Status**: Implémentée (fusionnée le 2026-10-13)

**Input**: Pour être remboursé, un salarié doit souvent prouver sa dépense : il joint à sa note la photo ou le PDF de son reçu. Quand la politique de l'entreprise exige un justificatif pour une catégorie, une note sans justificatif ne doit pas pouvoir partir en validation. Le manager doit pouvoir consulter le justificatif avant de décider.

## Clarifications

### Session 2026-10-13

- Q: Combien de justificatifs par note ? → A: Un seul, remplaçable (C1, C6).
- Q: Où se fait le dépôt ? → A: Sur le détail de la note, ouvert après la création (C13).
- Q: Comment refuser la soumission sans justificatif obligatoire ? → A: En 409 (C8).
- Q: Comment le justificatif est-il consulté ? → A: Ouvert dans un nouvel onglet, sans aperçu intégré (C5).
- Q: Les dépôts apparaissent-ils dans l'historique de la note ? → A: Non.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Joindre un justificatif à sa note (Priority: P1)

Après avoir créé sa note, le salarié arrive sur son détail et y joint la photo ou le PDF de son reçu ; il peut le remplacer ou le retirer tant que la note est modifiable. Les fichiers invalides sont refusés, dès le formulaire et par le serveur.

**Why this priority**: Sans dépôt, ni le blocage de soumission ni la consultation n'ont d'objet.

**Independent Test**: Créer une note, joindre un PDF, le remplacer par une image, le retirer, tenter un fichier invalide.

**Critères couverts**: C1, C2, C3, C4, C6, C7, C11, C12, C13

**Acceptance Scenarios**:

1. **Given** un salarié qui vient de créer une note, **When** l'enregistrement aboutit, **Then** le détail de la note s'ouvre, où il peut joindre le justificatif (C13).
2. **Given** une note modifiable (brouillon ou rejetée), **When** son propriétaire joint un JPEG, PNG ou PDF d'au plus 5 Mo, **Then** il devient le justificatif de la note (un seul par note), avec son nom d'origine, son type et sa taille (C1).
3. **Given** un fichier qui n'est ni JPEG, ni PNG, ni PDF d'après son contenu, **When** il est envoyé, **Then** il est refusé (400) avec un message qui cite les formats acceptés (C2).
4. **Given** un fichier de plus de 5 Mo ou vide, **When** il est envoyé, **Then** il est refusé (400) avec un message explicite (C3).
5. **Given** un fichier de type ou de taille invalide dans le formulaire de dépôt, **When** l'utilisateur le choisit, **Then** l'erreur s'affiche avant tout envoi (C4).
6. **Given** une note modifiable, **When** son propriétaire remplace ou retire le justificatif, **Then** c'est fait (C6) ; **Given** une note soumise ou validée, **Then** tout dépôt, remplacement ou retrait est refusé (409) (C7).
7. **Given** une note avec justificatif, **When** elle est supprimée, **Then** son justificatif l'est aussi (C11).
8. **Given** un justificatif déposé, **When** il est stocké, **Then** c'est hors de la partie publique du serveur, sous un nom généré par le serveur (C12).

---

### User Story 2 - Ne pas soumettre sans justificatif obligatoire (Priority: P1)

Quand la catégorie exige un justificatif et que la note n'en a pas, le détail le signale, le bouton de soumission est désactivé avec une explication, et le serveur refuse la soumission.

**Why this priority**: C'est la règle de l'entreprise que la feature doit faire respecter.

**Independent Test**: Créer une note d'hébergement sans justificatif, constater le blocage, joindre un justificatif, soumettre.

**Critères couverts**: C8, C9, C10

**Acceptance Scenarios**:

1. **Given** une note dont la catégorie exige un justificatif et qui n'en a pas, **When** on tente de la soumettre, **Then** c'est refusé (409) avec un message explicite (C8).
2. **Given** une note modifiable (brouillon ou rejetée), **When** son détail s'affiche, **Then** il indique si un justificatif obligatoire lui manque ; une note soumise ou validée n'est jamais signalée (C9).
3. **Given** un justificatif obligatoire manquant, **When** le détail s'affiche, **Then** le bouton de soumission est désactivé et la raison est expliquée à côté (C10).

---

### User Story 3 - Consulter le justificatif avant de décider (Priority: P2)

Le propriétaire et son responsable voient sur la note le justificatif joint et l'ouvrent dans un nouvel onglet.

**Why this priority**: Aide la décision du manager, une fois le dépôt possible.

**Independent Test**: En manager, ouvrir le justificatif d'une note soumise de son équipe.

**Critères couverts**: C5

**Acceptance Scenarios**:

1. **Given** une note avec justificatif, **When** son propriétaire ou son responsable l'ouvre, **Then** il voit le nom et la taille du justificatif et peut l'ouvrir dans un nouvel onglet, affiché par le navigateur (C5).

---

### Edge Cases

- Fichier de 5 Mo exactement accepté ; 5 Mo + 1 octet refusé ; fichier vide refusé.
- Fichier `.pdf` dont le contenu est une image PNG : accepté comme PNG ; fichier `.jpg` dont le contenu est du texte : refusé.
- Aucun fichier dans la requête : 400.
- Ouvrir le justificatif d'une note qui n'en a pas : 404.
- Nom d'origine avec des caractères spéciaux ou un chemin (`../`) : réduit à son nom de fichier, sans le chemin, et conservé pour l'affichage seulement, jamais utilisé pour stocker le fichier. *Précisé le 13/10 : le framework retire le chemin à la réception.*
- Note rejetée puis corrigée : le justificatif peut être remplacé avant la resoumission, et C8 s'applique à la resoumission.
- La politique change après la soumission : les notes déjà soumises ne sont pas affectées ; C8 s'applique à la soumission suivante.

## Requirements *(mandatory)*

### Functional Requirements

Les identifiants **C1…C13** sont conservés tels quels : les tests et le code y font référence (`testC4…`, « spec 005 (C8) »).

- **C1** QUAND le propriétaire d'une note modifiable (brouillon ou rejetée) joint un fichier JPEG, PNG ou PDF d'au plus 5 Mo, LE SYSTÈME DOIT l'enregistrer comme justificatif de la note (un seul par note), avec son nom d'origine, son type et sa taille.
- **C2** SI le fichier n'est ni un JPEG, ni un PNG, ni un PDF (d'après son contenu, pas son nom), ALORS LE SYSTÈME DOIT le refuser (400) avec un message qui cite les formats acceptés.
- **C3** SI le fichier dépasse 5 Mo ou est vide, ALORS LE SYSTÈME DOIT le refuser (400) avec un message explicite.
- **C4** QUAND le formulaire de dépôt contient un fichier de type ou de taille invalide, LE SYSTÈME DOIT afficher l'erreur avant tout envoi.
- **C5** LE SYSTÈME DOIT afficher sur la note le justificatif joint (nom, taille) et permettre à son propriétaire et à son responsable de l'ouvrir dans un nouvel onglet, affiché par le navigateur.
- **C6** TANT QU'une note est modifiable, LE SYSTÈME DOIT permettre à son propriétaire de remplacer ou de retirer son justificatif.
- **C7** SI la note n'est pas modifiable (soumise ou validée), ALORS LE SYSTÈME DOIT refuser tout dépôt, remplacement ou retrait de justificatif (409).
- **C8** SI la catégorie d'une note exige un justificatif et que la note n'en a pas, ALORS LE SYSTÈME DOIT refuser sa soumission (409) avec un message explicite.
- **C9** LE SYSTÈME DOIT indiquer, pour chaque note modifiable (brouillon ou rejetée), si un justificatif obligatoire lui manque ; une note soumise ou validée n'est jamais signalée, et l'indication n'apparaît que sur le détail de la note. *Précisé le 13/10 après la relecture à froid.*
- **C10** TANT QU'un justificatif obligatoire manque, LE SYSTÈME DOIT l'expliquer à côté du bouton de soumission et désactiver ce bouton.
- **C11** QUAND une note est supprimée, LE SYSTÈME DOIT supprimer aussi son justificatif.
- **C12** LE SYSTÈME DOIT conserver les fichiers hors de la partie publique du serveur, sous un nom qu'il génère lui-même.
- **C13** QUAND un salarié crée une note, LE SYSTÈME DOIT ouvrir son détail, où il peut joindre le justificatif.

### Autorisations

| Action | Qui peut | Sinon |
|---|---|---|
| Déposer, remplacer, retirer un justificatif | le propriétaire de la note | 401 non connecté ; 404 pour tout autre (existence non révélée) |
| Ouvrir un justificatif | ceux qui peuvent lire la note (propriétaire, son responsable hors brouillon) | 401 ; 404 |

### Règles recalculées côté front

- Type et taille du fichier (C4), pour l'erreur immédiate : testés des deux côtés, le serveur fait foi (le front se fie au type annoncé par le navigateur, le serveur au contenu).

Non recalculé : « justificatif obligatoire manquant » (C9, C10). Le serveur l'indique pour chaque note.

### Key Entities

- **Justificatif** : un seul par note ; nom d'origine (affichage seulement), type détecté d'après le contenu, taille, nom de stockage généré par le serveur.
- **Signalement** (complété) : justificatif obligatoire manquant, pour les notes modifiables.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Chaque critère C1 à C13 est couvert par au moins un test du back ou du front, et `make test` passe.
- **SC-002**: Le parcours e2e `soumission` (note d'hébergement soumise après dépôt du justificatif) passe (`make test-e2e`).

## Assumptions

- **Hors périmètre** : OCR et lecture automatique du montant, aperçu intégré des PDF, compression ou redimensionnement des images, formats HEIC et autres, antivirus, stockage externe (S3), justificatif sur une note validée, historique des dépôts dans l'historique de la note.
- **Écarts avec les docs** :
  - `docs/api.md` : seule la route de dépôt est prévue ; il faut aussi l'ouverture et le retrait (routes à préciser au plan), et le refus de soumission de C8 (409).
  - `docs/modele-donnees.md` : `Receipt` est prévu sans dire combien par note ; ce sera un seul.
  - Feature 002 (C1) : après la création, retour à la liste ; remplacé par l'ouverture du détail (C13), test du front de 002 à adapter.
