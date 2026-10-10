# AGENTS.md

Ceci est un projet Symfony. Consulter `composer.json` pour connaître la version
exacte de Symfony et de PHP utilisée, et lire `symfony.lock` pour savoir quelles
recettes ont été exécutées. Ne pas supposer que Doctrine, Twig, API Platform,
Messenger ou Lock sont installés tant que l'un de ces fichiers ne l'indique pas.

## Demander avant de générer

Si la tâche ne le précise pas, demander plutôt que deviner :

- Persistance : Doctrine ORM, Doctrine ODM, ou aucune ?
- Interface : rendu serveur (Twig), API (Serializer, éventuellement API Platform), ou les deux ?
- Authentification : SecurityBundle, et avec quel authenticator ?

S'il est impossible de demander (pas de canal interactif), énoncer l'hypothèse
retenue et choisir l'option la plus légère (par exemple, aucune couche de
persistance) plutôt que d'échafauder une stack complète que personne n'a demandée.

## Ajouter des fonctionnalités : Flex, pas de câblage à la main

Installer les nouvelles fonctionnalités avec `composer require <paquet>` (par exemple
`symfony/lock`, `symfony/messenger`, `orm-pack`) et laisser la recette Flex
enregistrer le bundle et générer sa configuration. Ne pas modifier
`config/bundles.php` à la main ni écrire soi-même la configuration de base d'un
bundle : c'est le rôle de la recette. Ne pas renoncer à un composant adapté
simplement parce qu'il n'est pas encore installé : l'installer tient en une commande.

## Conventions

Suivre https://symfony.com/doc/current/best_practices.html pour écrire du Symfony
idiomatique :

- Utiliser les attributs PHP pour les métadonnées du framework, et pas seulement
  sur les contrôleurs : `#[Route]`, `#[MapRequestPayload]`, `#[IsGranted]` sur les
  actions, `#[Assert\...]` sur les propriétés, `#[AsCommand]`, `#[AsEventListener]`,
  `#[AsMessageHandler]`, et `#[AsAlias]` / `#[AsTaggedItem]` / `#[Autoconfigure]`
  sur les services. Pas de routage en YAML ou en XML.
- S'appuyer sur l'autowiring et l'autoconfiguration. Typer les arguments du
  constructeur et laisser le conteneur les résoudre. Quand un type ne suffit pas,
  rester dans la classe avec `#[Autowire]` (paramètres, variables d'environnement,
  expressions) ou `#[Target]` (une implémentation parmi plusieurs d'une interface).
  Une définition de service en YAML est le dernier recours, pas le premier.
- Les contrôleurs étendent `AbstractController`, restent fins et délèguent aux services.
- Utiliser le framework pour ce qu'il sait déjà faire : Form pour les formulaires
  rendus côté serveur, Validator pour la validation, Serializer pour le JSON,
  Messenger pour le travail asynchrone, Security (voters, authenticators) pour le
  contrôle d'accès, `path()` / `url()` de Twig plutôt que des URL en dur.
- Avant d'écrire soi-même de l'infrastructure (verrous, files, caches, clients HTTP,
  mailers, planificateurs) ou de recourir à une bibliothèque tierce, vérifier si un
  composant Symfony la couvre. C'est généralement le cas.

Trois points précis méritent d'être explicités, car on se trompe facilement :

- Lier les données de la requête avec `#[MapRequestPayload]` / `#[MapQueryString]`
  sur les arguments de l'action, ce qui branche automatiquement Serializer et
  Validator, plutôt que d'appeler `json_decode()` ou `SerializerInterface` à la main.
  Si aucun des deux paquets n'est encore installé, les installer avec
  `composer require` plutôt que de se rabattre sur un parsing manuel.
- Utiliser la promotion des propriétés du constructeur, et `readonly` pour les DTO
  et les objets valeur. Ne pas marquer un service `readonly` s'il risque de devenir
  `lazy: true` : un proxy lazy ne peut pas étendre une classe `readonly`.
- Utiliser `symfony/lock` (`LockFactory`) pour l'exclusion mutuelle. Un drapeau ou
  un fichier de verrou fait maison a l'air correct en revue et se révèle
  généralement faux en cas d'accès concurrents.

## Travail au quotidien

- Lancer l'application avec `symfony serve -d`, et les commandes avec
  `symfony console ...` (ou `bin/console` si le CLI Symfony n'est pas disponible).
- En cas d'échec, lire `var/log/dev.log` et le profiler web (`/_profiler`) avant
  de modifier le code.
- Si `maker-bundle` est installé, préférer `bin/console make:*` en passant tous les
  arguments d'emblée et `--no-interaction` quand c'est possible : par défaut, les
  makers posent des questions dans le terminal, ce qui bloque un shell non
  interactif. Si un maker exige malgré tout une saisie interactive, écrire le code
  à la main.
- Si Doctrine ORM est installé, toute modification du schéma passe par une migration
  (`bin/console make:migration`, puis `doctrine:migrations:migrate`), jamais par
  `doctrine:schema:update` ni par du SQL écrit à la main.
- `.env` est committé et ne contient que des valeurs par défaut. Les vrais secrets
  vont dans `.env.local` (ignoré par git) ou dans le coffre de secrets
  (`bin/console secrets:set`), et sont lus via `%env(...)%`.

## Tests

Installer `symfony/test-pack` s'il ne l'est pas déjà. Les tests fonctionnels / HTTP
étendent `WebTestCase` ; les tests de services étendent `KernelTestCase`. Lancer
`php bin/phpunit` (ou à défaut `vendor/bin/phpunit`). Une fonctionnalité n'est pas
terminée tant qu'un test ne l'exerce pas comme le ferait un appelant : une requête
HTTP pour un contrôleur, un appel de service pour un service, et pas seulement
« ça n'a pas levé d'exception ».

## Style de code

Le standard de code de Symfony, c'est-à-dire le jeu de règles `@Symfony` de
php-cs-fixer (un sur-ensemble dérivé de PSR-12). Lancer `vendor/bin/php-cs-fixer fix`
si `friendsofphp/php-cs-fixer` est installé ; il ne fait pas partie du squelette
par défaut.

## Chercher, ne pas deviner

Les API du framework changent d'une version à l'autre et les connaissances issues
de l'entraînement peuvent être périmées. Chercher dans le projet plutôt que de se
fier à sa mémoire :

- `bin/console about` : versions, environnement, chemins.
- `bin/console debug:router`, `debug:container`, `debug:autowiring <nom>`,
  `debug:config <bundle>`, `config:dump-reference <bundle>` : ce qui existe et
  comment c'est configuré.
- `bin/console lint:container`, ainsi que `lint:twig templates/` et
  `lint:yaml config/` quand ces paquets sont installés : valider avant d'exécuter.
- Lire le code source installé et les docblocks dans `vendor/`.
- Documentation : https://symfony.com/doc/current/ (basculer sur la version
  correspondant à `composer.json` si elle diffère).
