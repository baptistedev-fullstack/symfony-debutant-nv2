# Versions

Les versions de ce niveau. [tests/Docs/DocsConsistencyTest.php](../tests/Docs/DocsConsistencyTest.php) compare ce document à [composer.lock](../composer.lock), [composer.json](../composer.json) et au [Dockerfile](../Dockerfile) : le test échoue si une version ne correspond plus.

Pour lire une contrainte comme `8.1.*`, voir [la fiche sur Composer](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/02-composer-et-flex.md).

## Socle

| Élément | Version | Rôle |
|---|---|---|
| PHP | 8.5.12 | Le langage. Version de l'image Docker. `composer.json` exige `>=8.5.12` : la 8.5.11 a des failles activement exploitées |
| Symfony | `8.1.*` | Le framework. Tous les paquets Symfony sont limités à cette branche |
| FrankenPHP | 1.13.5 | Le serveur web et PHP, voir [docker.md](docker.md) |

L'image Docker `dunglas/frankenphp:1.13.5-php8.5.12-trixie` repose sur Debian 13, dite `trixie`, et embarque Caddy 2.11.9.

La CI demande PHP `8.5` : elle reçoit la dernière version disponible de cette branche, qui doit rester au moins égale à 8.5.12.

Les paquets Symfony n'ont pas tous le même numéro de correctif, par exemple `symfony/asset` et `symfony/console` ci-dessous. C'est normal : un composant sans changement ne reçoit pas de nouvelle version. La commande `php bin/console about` donne la version du framework.

Ce niveau n'ajoute aucun paquet à ceux de Debutant-Nv1 : services, conteneur et attributs `#[Autowire]` viennent déjà avec `symfony/framework-bundle`.

## Paquets de production (`require`)

| Paquet | Version | Rôle |
|---|---|---|
| `symfony/asset` | v8.1.0 | La fonction `asset()`, qui donne l'URL de la feuille de style |
| `symfony/console` | v8.1.11 | Les commandes `bin/console` |
| `symfony/dotenv` | v8.1.6 | La lecture des fichiers `.env` |
| `symfony/flex` | v2.12.0 | Le plugin Composer qui applique les recettes |
| `symfony/framework-bundle` | v8.1.11 | Le cœur de Symfony : Kernel, contrôleurs, configuration |
| `symfony/runtime` | v8.1.11 | Le démarrage de l'application depuis `public/index.php` |
| `symfony/twig-bundle` | v8.1.9 | L'intégration de Twig |
| `symfony/yaml` | v8.1.11 | La lecture des fichiers YAML et `lint:yaml` |

`composer.json` exige aussi deux extensions de PHP, `ext-ctype` et `ext-iconv`, présentes dans l'image Docker.

## Paquets de développement (`require-dev`)

| Paquet | Version | Rôle |
|---|---|---|
| `phpunit/phpunit` | 13.4.6 | Le lancement des tests |
| `symfony/browser-kit` | v8.1.11 | Le navigateur simulé des tests fonctionnels |
| `symfony/css-selector` | v8.1.10 | Les sélecteurs CSS dans les assertions |
| `symfony/maker-bundle` | 1.68.0 | Les commandes `make:*` qui génèrent du code |

## Dépendances indirectes

Les paquets ci-dessus en installent d'autres, comme `symfony/routing`, `symfony/http-kernel` ou `twig/twig`. Ils sont tous dans [composer.lock](../composer.lock), mais ce projet ne choisit directement que les paquets listés plus haut.

```bash
composer show --direct
composer show --tree
```

La première commande liste les paquets directs et leur version installée, la seconde montre qui dépend de quoi.

## Durée de vie

Le parcours reste sur Symfony 8.1. La commande `php bin/console about` indique sa fin de maintenance, en janvier 2027 : ce n'est pas une version à support long.
