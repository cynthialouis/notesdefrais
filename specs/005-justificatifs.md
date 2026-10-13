# 005 — Justificatifs

## Besoin

Pour être remboursé, un salarié doit souvent prouver sa dépense : il joint à sa note la photo ou le PDF de son reçu. Quand la politique de l'entreprise exige un justificatif pour une catégorie, une note sans justificatif ne doit pas pouvoir partir en validation. Le manager doit pouvoir consulter le justificatif avant de décider.

## Critères d'acceptation

- **C1** QUAND le propriétaire d'une note modifiable (brouillon ou rejetée) joint un fichier JPEG, PNG ou PDF d'au plus 5 Mo, LE SYSTÈME DOIT l'enregistrer comme justificatif de la note (un seul par note), avec son nom d'origine, son type et sa taille.
- **C2** SI le fichier n'est ni un JPEG, ni un PNG, ni un PDF (d'après son contenu, pas son nom), ALORS LE SYSTÈME DOIT le refuser (400) avec un message qui cite les formats acceptés.
- **C3** SI le fichier dépasse 5 Mo ou est vide, ALORS LE SYSTÈME DOIT le refuser (400) avec un message explicite.
- **C4** QUAND le formulaire de dépôt contient un fichier de type ou de taille invalide, LE SYSTÈME DOIT afficher l'erreur avant tout envoi.
- **C5** LE SYSTÈME DOIT afficher sur la note le justificatif joint (nom, taille) et permettre à son propriétaire et à son responsable de l'ouvrir dans un nouvel onglet, affiché par le navigateur.
- **C6** TANT QU'une note est modifiable, LE SYSTÈME DOIT permettre à son propriétaire de remplacer ou de retirer son justificatif.
- **C7** SI la note n'est pas modifiable (soumise ou validée), ALORS LE SYSTÈME DOIT refuser tout dépôt, remplacement ou retrait de justificatif (409).
- **C8** SI la catégorie d'une note exige un justificatif et que la note n'en a pas, ALORS LE SYSTÈME DOIT refuser sa soumission (409) avec un message explicite.
- **C9** LE SYSTÈME DOIT indiquer, pour chaque note, si un justificatif obligatoire lui manque.
- **C10** TANT QU'un justificatif obligatoire manque, LE SYSTÈME DOIT l'expliquer à côté du bouton de soumission et désactiver ce bouton.
- **C11** QUAND une note est supprimée, LE SYSTÈME DOIT supprimer aussi son justificatif.
- **C12** LE SYSTÈME DOIT conserver les fichiers hors de la partie publique du serveur, sous un nom qu'il génère lui-même.
- **C13** QUAND un salarié crée une note, LE SYSTÈME DOIT ouvrir son détail, où il peut joindre le justificatif.

## Autorisations

| Action | Qui peut | Sinon |
|---|---|---|
| Déposer, remplacer, retirer un justificatif | le propriétaire de la note | 401 non connecté ; 404 pour tout autre (existence non révélée) |
| Ouvrir un justificatif | ceux qui peuvent lire la note (propriétaire, son responsable hors brouillon) | 401 ; 404 |

## Cas limites et erreurs

- Fichier de 5 Mo exactement accepté ; 5 Mo + 1 octet refusé ; fichier vide refusé.
- Fichier `.pdf` dont le contenu est une image PNG : accepté comme PNG ; fichier `.jpg` dont le contenu est du texte : refusé.
- Aucun fichier dans la requête : 400.
- Ouvrir le justificatif d'une note qui n'en a pas : 404.
- Nom d'origine avec des caractères spéciaux ou un chemin (`../`) : conservé pour l'affichage seulement, jamais utilisé pour stocker le fichier.
- Note rejetée puis corrigée : le justificatif peut être remplacé avant la resoumission, et C8 s'applique à la resoumission.
- La politique change après la soumission : les notes déjà soumises ne sont pas affectées ; C8 s'applique à la soumission suivante.

## Règles recalculées côté front

- Type et taille du fichier (C4), pour l'erreur immédiate : testés des deux côtés, le serveur fait foi (le front se fie au type annoncé par le navigateur, le serveur au contenu).

Non recalculé : « justificatif obligatoire manquant » (C9, C10). Le serveur l'indique pour chaque note.

## Hors périmètre

OCR et lecture automatique du montant, aperçu intégré des PDF, compression ou redimensionnement des images, formats HEIC et autres, antivirus, stockage externe (S3), justificatif sur une note validée, historique des dépôts dans l'historique de la note.

## Questions ouvertes

Aucune. Tranchées le 13/10 : un seul justificatif par note, remplaçable (C1, C6) ; dépôt sur le détail, ouvert après la création (C13) ; refus de soumission en 409 (C8) ; ouverture dans un nouvel onglet, sans aperçu intégré (C5) ; dépôts absents de l'historique.

## Écarts avec les docs

- `docs/api.md` : seule la route de dépôt est prévue ; il faut aussi l'ouverture et le retrait (routes à préciser au plan), et le refus de soumission de C8 (409).
- `docs/modele-donnees.md` : `Receipt` est prévu sans dire combien par note ; ce sera un seul.
- Feature 002 (C1) : après la création, retour à la liste ; remplacé par l'ouverture du détail (C13), test du front de 002 à adapter.

## Plan

- **Back** : entité `Receipt` (un-à-un avec `Expense`, nom d'origine, type, taille, nom de stockage généré) ; service `ReceiptStorage` (`var/receipts/<env>`, hors `public/`) ; `PolicyCheck.receiptMissing` (C9) ; `ExpenseManager` : dépôt, remplacement, retrait (409 si non modifiable), refus de soumission sans justificatif obligatoire (409, après les refus d'état et de responsable), effacement du fichier à la suppression. Routes `POST`, `GET`, `DELETE /api/expenses/{id}/receipt` (dépôt multipart validé par `MapUploadedFile` et `Assert\File`, 400 ; ouverture en `inline` avec `nosniff`). `ExpenseView.receipt`. Nouvelle dépendance : `symfony/mime` (type détecté d'après le contenu, C2). Démo : justificatifs générés, un brouillon d'hébergement sans justificatif.
- **Front** : store (`receipt`, `receiptMissing`, `uploadReceipt`, `removeReceipt`) ; `apiFetch` accepte un `FormData` ; `receiptValidation.ts` (seule version front du contrôle de type et de taille) ; carte Justificatif sur le détail ; bouton de soumission désactivé et expliqué ; ouverture du détail après la création.

## Tâches

1. Modèle : `Receipt`, migration (C1, C12)
2. `ReceiptStorage` (C11, C12)
3. `ExpenseManager` et `PolicyChecker` : dépôt, remplacement, retrait, refus de soumission (C6 à C9, C11)
4. Routes et vues : dépôt, ouverture, retrait, `ExpenseView.receipt` (C1 à C3, C5 à C7)
5. Données de démo (C9, C10)
6. Types, store, `apiFetch` multipart
7. `receiptValidation.ts` (C4)
8. Carte Justificatif sur le détail (C4 à C6)
9. Soumission désactivée et expliquée (C10)
10. Ouverture du détail après la création (C13)
