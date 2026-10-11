---
name: revue-react
description: Relit le code React modifié dans frontend/src/ selon les conventions du projet (useEffect, état, Zustand, Zod, typage, accessibilité, contrat avec l'API). À utiliser après chaque feature front, avant commit (étape F6 de docs/methode.md), ou sur tout frontend/src/ pour une revue globale.
---

# Revue React

## 1. Périmètre

- Par défaut : ce qui a changé sur la branche par rapport à `main`, y compris le travail non commité et les fichiers nouveaux.
  - `git diff main...HEAD --name-only -- frontend/src`
  - `git diff HEAD --name-only -- frontend/src`
  - `git ls-files --others --exclude-standard frontend/src` (les fichiers nouveaux n'apparaissent pas dans `git diff`)
- Si la demande dit « tout le front » : tous les fichiers de `frontend/src/`.
- Lis chaque fichier en entier, pas seulement le diff : un `useEffect` ou un state se juge avec son contexte.
- Lis la spec de la feature dans `specs/` si elle existe (section « Règles recalculées côté front »).

## 2. D'abord les outils

Lance `npx tsc -b` et `npm run lint` dans `frontend/`. Rapporte leurs erreurs telles quelles. Ne les reformule pas comme des remarques de revue : la revue porte sur ce que les outils ne voient pas.

## 3. Vérifier, dans cet ordre

1. **useEffect injustifié** : valeur dérivée (total, liste filtrée, alerte) ou réaction à un événement placée dans un effet. Corriger par un calcul pendant le rendu ou un gestionnaire d'événement. Un effet n'est légitime que pour synchroniser avec l'extérieur de React (abonnement, API du navigateur, chargement initial de la session).
2. **État mal placé** : state dupliqué ou dérivable d'un autre ; donnée mise dans Zustand alors qu'un seul composant s'en sert (un filtre de liste reste local).
3. **Immutabilité** : mutation d'un tableau ou d'un objet du state ou du store (`push`, `sort`, affectation de propriété).
4. **Zustand** : store entier sélectionné ; objet ou tableau sélectionné sans `useShallow` ; donnée partagée (session, cache des notes) chargée hors des actions du store. Une donnée lue par un seul composant peut être chargée localement (règle 2) : ce n'est pas une remarque.
5. **Formulaires** : soumission gérée à la main (`onSubmit` + `useState` pour l'état d'envoi et les erreurs) au lieu de `useActionState` ; saisie validée avec `parse` au lieu de `safeParse` ; type de formulaire écrit à la main au lieu de `z.infer`.
6. **Contrat avec l'API** : type de réponse écrit à la main au lieu du type généré depuis OpenAPI ; URL de serveur en dur au lieu de `/api/...`.
7. **Règles métier** : règle recalculée côté front qui n'est pas listée dans la spec, ou sans test. Montants manipulés autrement qu'en centimes entiers (conversion en flottant, arrondi) ; affichage en euros qui n'utilise pas `Intl.NumberFormat`.
8. **Typage** : `any` ; `as` sans justification ; `switch` sur un statut sans branche `default` exhaustive avec `never`.
9. **Accessibilité** : élément cliquable qui n'est pas un `button` ou un `a` ; champ sans `label` ; erreur non reliée au champ par `aria-describedby` ; listes avec l'index comme `key`.
10. **Tests** : comportement nouveau sans test Testing Library ; requêtes par classe CSS ou par `data-testid` alors qu'un rôle ou un texte suffit.

Ne signale que ce que tu peux montrer dans le code : chaque remarque cite le fichier et la ligne. Pas de remarque de style ni de préférence personnelle.

## 4. Rendre la revue

Une liste par fichier, chaque remarque avec :
- `fichier:ligne`, le numéro de la règle ci-dessus ;
- la gravité : **bloquant** (bug, faille, règle du projet violée), **à corriger** (dette réelle), **suggestion** ;
- la correction proposée, en une ou deux lignes.

Termine par le résultat de `tsc` et du lint, et par « Aucune remarque » pour les fichiers relus sans problème.

## 5. Appliquer ou non

- Si la demande est une revue seule : ne modifie aucun fichier.
- Si la demande inclut les corrections (étape F6) : applique les remarques **bloquant** et **à corriger**, relance `npx tsc -b`, `npm run lint` et les tests du front, puis indique les remarques écartées avec leur justification.
