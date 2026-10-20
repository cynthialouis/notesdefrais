# Quickstart: 007 — Demander un congé

Guide de vérification de bout en bout. Contrat : [contracts/api.md](contracts/api.md) ;
modèle : [data-model.md](data-model.md).

## Prérequis

```sh
make install
make db          # migre la base de dev et recharge les données de démo (congés compris)
make dev         # API sur 8000, front sur 5173
```

## Tests automatiques

```sh
make test-back ARGS="--filter Leave"   # tests PHPUnit de la feature (écrits avant le code)
make test                               # PHPUnit + Vitest
make test-e2e ARGS="conges"             # parcours de bout en bout
```

Attendu : tout passe ; chaque critère C1 à C18 a au moins un test nommé d'après lui.

## Vérification à la main (étape F5)

Page `/conges`, avec les comptes de démo :

1. **Salarié** : demander un congé de demain à après-demain → il apparaît « En attente » dans
   « Mes demandes » (C1, C16). Fin avant début, ou début hier → message sous le champ, sans envoi
   (C3). Redemander une période qui chevauche d'un jour → message de refus (C4). Retirer la demande
   → « Retirée » (C11), plus de bouton « Retirer » (C12).
2. **Manager de ce salarié** : « Demandes à traiter » liste ses demandes en attente, début le plus
   proche d'abord (C6). Accepter l'une (C7) ; refuser une autre avec un motif de 4 caractères →
   erreur immédiate (C9), puis avec un motif valide → « Refusée » (C8).
3. **Salarié d'une autre équipe** : « Congés à venir » montre le congé accepté (C15), pas un congé terminé hier, pas la
   demande refusée ni son motif (C17).
4. **Admin** : « Demandes en attente » liste celles de toutes les équipes (C18). Annuler l'une d'elles,
   puis un congé accepté, avec un motif valide → « Annulée » chez le demandeur, avec le motif (C13,
   C16) ; le congé disparaît des congés à venir. Pas de bouton « Annuler » sur une demande refusée
   (C14). Demander un congé en admin →
   message de refus explicite (C5).
