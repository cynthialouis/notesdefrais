# Constitution — Notes de frais (POC)

Ce projet est un POC qui sert à expérimenter des fonctionnalités. Les principes ci-dessous
reprennent les règles de `CLAUDE.md`, `backend/AGENTS.md` et `docs/methode.md` ; en cas
d'écart, ces fichiers font foi et la constitution est amendée.

## Core Principles

### I. Spec validée avant tout code

- Toute feature commence par une spec `specs/NNN-nom/spec.md`, validée par l'humain avant
  tout code ; le plan (`plan.md`) et les tâches (`tasks.md`) sont validés à leur tour.
- Les critères d'acceptation sont numérotés `C1`, `C2`… au format EARS, chacun testable ; ces
  identifiants ne sont jamais renumérotés, car les tests et le code s'y réfèrent.
- Les questions ouvertes sont listées avec une proposition et tranchées par l'humain, jamais
  devinées.
- Si le code s'écarte de la spec, l'écart est signalé : on corrige l'un ou l'autre consciemment.

**Rationale** : l'humain valide le quoi ; l'agent ne décide pas seul du comportement.

### II. Le back est la source de vérité

- Validation (Validator), autorisation (Voter) et transitions d'état (Workflow) sont toujours
  faites dans `backend/`, même si le front les fait aussi.
- Une règle métier recalculée côté front pour l'affichage est une duplication volontaire : listée
  dans la spec (« Règles recalculées côté front ») et testée des deux côtés.
- Les deux projets ne partagent aucun code et ne communiquent que par HTTP via `/api`.

**Rationale** : le front n'est qu'un confort ; aucune donnée ne doit dépendre de l'état du
navigateur.

### III. Tests du back d'abord, jamais affaiblis

- Les tests du back sont écrits à partir de la spec, avant l'implémentation, nommés d'après les
  critères.
- Une tâche du plan à la fois, uniquement pour faire passer les tests existants.
- Un test n'est jamais modifié pour le faire passer : s'il paraît faux, on s'arrête et on explique.
- Une feature n'est fusionnée qu'après `make test-e2e` réussi.

**Rationale** : les tests traduisent la spec validée ; les adapter au code inverse la relation.

### IV. Contrat OpenAPI unique

- Les types de l'API côté front sont générés depuis la spec OpenAPI (`make api-types`), jamais
  réécrits à la main.
- Tout changement de contrat est suivi de la régénération des types.

**Rationale** : un seul contrat évite que le front et le back divergent en silence.

### V. Montants en centimes et typage strict

- Les montants sont des centimes entiers, des deux côtés.
- TypeScript strict : jamais de `any` (`unknown` + validation Zod), `import type` pour les types,
  ni `enum`, ni `namespace`, ni parameter properties.

**Rationale** : pas d'erreur d'arrondi ; les données venues de l'extérieur sont validées avant
usage.

### VI. Sobriété des dépendances

- Aucune dépendance n'est ajoutée sans être signalée et justifiée.
- Côté Symfony, on utilise d'abord ce que le framework fournit (composants, recettes Flex,
  attributs, autowiring) avant d'écrire de l'infrastructure ou d'ajouter une bibliothèque tierce.

**Rationale** : chaque dépendance est un coût de maintenance ; le framework couvre déjà la
plupart des besoins.

## Contraintes techniques

- Front : React 19, TypeScript strict, Vite, React Router, Zustand, Zod, Tailwind CSS 4 ;
  composants de `frontend/src/ui/` réutilisés avant d'écrire des classes ; styles uniquement en
  classes Tailwind ; tout élément interactif garde un focus visible ; pas de `useEffect` pour une
  valeur dérivée.
- Back : Symfony 8, Doctrine ORM + Migrations (SQLite) ; tout changement de schéma passe par une
  migration ; DTO reçus avec `#[MapRequestPayload]`.
- Secrets jamais committés.
- Toute la documentation (specs, docs, commentaires, journal) est rédigée en français.

## Cycle d'une feature

- Ordre d'implémentation : `backend/` puis `frontend/`.
- Étapes F1 à F8 de `docs/methode.md` : branche `feat/NNN-nom`, spec, plan, tests, implémentation
  tâche par tâche, vérification à la main, revues (`/revue-api`, `/revue-react`), relecture à
  froid (`relecteur`), fusion.
- Après chaque modification : front → `npx tsc -b`, `npm run lint`, `npm run test` ;
  back → `make test-back`, `php bin/console lint:container`.
- Commits conventionnels, petits et atomiques.

## Governance

- Cette constitution résume les règles du projet ; `CLAUDE.md`, `backend/AGENTS.md` et
  `docs/methode.md` restent la référence détaillée et sont modifiés en même temps qu'elle.
- Un amendement est proposé par l'agent et validé par l'humain ; il est noté dans `JOURNAL-IA.md`.
- Versionnement sémantique : MAJOR si un principe est retiré ou redéfini, MINOR si un principe
  ou une section est ajouté, PATCH pour une reformulation.
- Chaque plan vérifie sa conformité dans sa section « Constitution Check » ; un écart y est
  justifié ou corrigé.

**Version**: 1.0.0 | **Ratified**: 2026-10-19 | **Last Amended**: 2026-10-19
