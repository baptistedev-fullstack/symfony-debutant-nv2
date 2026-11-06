# Arborescence du projet

Chaque dossier et fichier du projet, avec son rôle et son origine. La colonne « Origine » indique qui l'a créé : **écrit** veut dire écrit pour ce projet, **recette** veut dire créé par une [recette Flex](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/02-composer-et-flex.md), **généré** veut dire créé par un outil.

```text
Debutant-Nv2/
├── .github/workflows/    Intégration continue
├── bin/                  Exécutables du projet
├── config/               Configuration de Symfony
├── docs/                 Documentation de formation
├── public/               Seul dossier exposé au web
├── src/                  Code PHP de l'application
├── templates/            Templates Twig
├── tests/                Tests automatisés
├── var/                  Cache et journaux (non versionné)
└── vendor/               Dépendances (non versionné)
```

## Racine

| Fichier | Rôle | Origine |
|---|---|---|
| [README.md](../README.md) | Présentation du niveau et démarrage | Écrit |
| [AGENTS.md](../AGENTS.md) | Consignes pour les assistants de code | Recette |
| [LICENSE](../LICENSE) | Licence MIT | Écrit |
| [composer.json](../composer.json) | Les dépendances demandées | Généré, puis complété |
| [composer.lock](../composer.lock) | Les versions exactes installées | Généré par Composer |
| [symfony.lock](../symfony.lock) | Les recettes appliquées | Généré par Flex |
| [Dockerfile](../Dockerfile) | La recette de l'image Docker, voir [docker.md](docker.md) | Écrit |
| [compose.yaml](../compose.yaml) | Le lancement du conteneur | Écrit |
| [.dockerignore](../.dockerignore) | Ce qui n'entre pas dans l'image | Écrit |
| [.env](../.env) | Les variables par défaut, dont `APP_CONVERSION_PRECISION` | Recette, puis complété |
| [.env.dev](../.env.dev) | Les variables de `dev` | Recette |
| [.env.test](../.env.test) | Les variables de `test` | Recette |
| [.gitignore](../.gitignore) | Ce que Git ne suit pas | Recette, puis complété |
| [.editorconfig](../.editorconfig) | Le format des fichiers pour les éditeurs | Recette |
| [phpunit.dist.xml](../phpunit.dist.xml) | La configuration de PHPUnit | Recette |

Les fichiers `.env.local`, `.env.local.php` et `.env.*.local` sont personnels : ils sont ignorés par Git et absents de l'image, mais visibles du conteneur lancé (voir [docker.md](docker.md)). Le projet n'en a pas besoin, mais `.env.local` permet d'essayer une autre précision, comme le montre [la fiche sur la configuration](formations/04-configuration.md). `.DS_Store` est un fichier technique de macOS, lui aussi ignoré.

## `.github/workflows/`

| Fichier | Rôle |
|---|---|
| [ci.yml](../.github/workflows/ci.yml) | La CI GitHub Actions, voir [ci-cd.md](ci-cd.md) |

## `bin/`

| Fichier | Rôle | Origine |
|---|---|---|
| [console](../bin/console) | Point d'entrée des commandes Symfony | Recette |
| [phpunit](../bin/phpunit) | Lanceur des tests | Recette |

## `config/`

Voir [la fiche sur la configuration](formations/04-configuration.md) et, pour les fichiers communs à tous les projets, [celle du niveau précédent](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/06-configuration-et-environnements.md).

| Fichier | Rôle | Origine |
|---|---|---|
| [bundles.php](../config/bundles.php) | Les bundles actifs par environnement | Flex |
| [routes.yaml](../config/routes.yaml) | Le chargement des routes par attributs | Recette |
| [routes/framework.yaml](../config/routes/framework.yaml) | La route d'aperçu des erreurs, en `dev` | Recette |
| [services.yaml](../config/services.yaml) | Le paramètre `app.conversion.units` et la déclaration des classes de `src/` comme services | Recette, puis complété |
| [preload.php](../config/preload.php) | Une optimisation de `prod`, inutilisée ici | Recette |
| [reference.php](../config/reference.php) | La description des options, à ne pas modifier | Généré |
| [packages/cache.yaml](../config/packages/cache.yaml) | Le cache applicatif, sans réglage actif | Recette |
| [packages/framework.yaml](../config/packages/framework.yaml) | Le secret, la session et le mode test | Recette |
| [packages/routing.yaml](../config/packages/routing.yaml) | Les réglages du routeur | Recette |
| [packages/twig.yaml](../config/packages/twig.yaml) | Les réglages de Twig, dont `strict_variables` sous `when@test` | Recette |

## `docs/`

| Fichier | Rôle |
|---|---|
| [arborescence.md](arborescence.md) | Ce document |
| [versions.md](versions.md) | Les versions de PHP, Symfony, FrankenPHP et des paquets |
| [docker.md](docker.md) | Docker expliqué ligne par ligne |
| [ci-cd.md](ci-cd.md) | La CI expliquée étape par étape |
| [formations/index.md](formations/index.md) | L'ordre de lecture et le parcours du code |
| [formations/01-services.md](formations/01-services.md) | Les services |
| [formations/02-injection-de-dependances.md](formations/02-injection-de-dependances.md) | Le conteneur et l'autowiring |
| [formations/03-interfaces-et-tags.md](formations/03-interfaces-et-tags.md) | Les interfaces et les tags |
| [formations/04-configuration.md](formations/04-configuration.md) | Les paramètres et les variables d'environnement |
| [formations/05-formulaire-get-et-parametres.md](formations/05-formulaire-get-et-parametres.md) | Le formulaire et la chaîne de requête |
| [formations/06-exceptions-et-erreurs.md](formations/06-exceptions-et-erreurs.md) | Les exceptions et les statuts HTTP |
| [formations/07-tests-unitaires-et-doublures.md](formations/07-tests-unitaires-et-doublures.md) | Les tests unitaires et les doublures |

Il n'y a pas de `base-de-donnees.md` : ce niveau n'a pas de base.

## `public/`

| Fichier | Rôle | Origine |
|---|---|---|
| [index.php](../public/index.php) | Le contrôleur frontal, voir [la fiche HTTP](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/01-http-et-requete.md) | Recette |
| [css/app.css](../public/css/app.css) | La feuille de style du site | Écrit |

## `src/`

| Fichier | Rôle | Origine |
|---|---|---|
| [Kernel.php](../src/Kernel.php) | Le cœur de l'application | Recette |
| [Controller/ConverterController.php](../src/Controller/ConverterController.php) | La page de conversion | `make:controller`, puis réécrit |
| [Controller/.gitignore](../src/Controller/.gitignore) | Un fichier vide, venu de la création du projet | Recette |
| [Conversion/ConverterInterface.php](../src/Conversion/ConverterInterface.php) | Le contrat des convertisseurs et leur tag | Écrit à la main |
| [Conversion/LinearConverter.php](../src/Conversion/LinearConverter.php) | Les longueurs et les masses | Écrit à la main |
| [Conversion/TemperatureConverter.php](../src/Conversion/TemperatureConverter.php) | Les températures | Écrit à la main |
| [Conversion/UnitConverter.php](../src/Conversion/UnitConverter.php) | Le service utilisé par le contrôleur | Écrit à la main |
| [Conversion/UnsupportedConversionException.php](../src/Conversion/UnsupportedConversionException.php) | L'exception des conversions impossibles | Écrit à la main |

Aucune commande `make:*` ne génère ces services : la [fiche sur les services](formations/01-services.md) l'explique.

## `templates/`

| Fichier | Rôle | Origine |
|---|---|---|
| [base.html.twig](../templates/base.html.twig) | Le squelette de la page | Recette, puis réécrit |
| [converter/index.html.twig](../templates/converter/index.html.twig) | Le formulaire et le résultat | `make:controller`, puis réécrit |

## `tests/`

| Fichier | Rôle | Origine |
|---|---|---|
| [bootstrap.php](../tests/bootstrap.php) | Le démarrage des tests | Recette |
| [Conversion/LinearConverterTest.php](../tests/Conversion/LinearConverterTest.php) | Les tests unitaires des longueurs et des masses | `make:test`, puis complété |
| [Conversion/TemperatureConverterTest.php](../tests/Conversion/TemperatureConverterTest.php) | Les tests unitaires des températures | `make:test`, puis complété |
| [Conversion/UnitConverterTest.php](../tests/Conversion/UnitConverterTest.php) | Les tests unitaires avec doublures | `make:test`, puis complété |
| [Conversion/UnitConverterServiceTest.php](../tests/Conversion/UnitConverterServiceTest.php) | Le test du service réel, tiré du conteneur | `make:test`, puis complété |
| [Controller/ConverterControllerTest.php](../tests/Controller/ConverterControllerTest.php) | Les tests de la page | `make:test`, puis complété |
| [Docs/DocsConsistencyTest.php](../tests/Docs/DocsConsistencyTest.php) | Les tests de cohérence de la documentation | Repris de Debutant-Nv1 |

## Dossiers générés

Ces dossiers n'existent pas sur GitHub : ils sont recréés sur chaque machine et ignorés par Git.

| Dossier | Contenu | Recréé par |
|---|---|---|
| `vendor/` | Le code des dépendances | `composer install` |
| `var/cache/` | La configuration compilée, dont le conteneur, par environnement | Symfony, à la première requête |
| `var/log/` | Les journaux, s'il y en a | Symfony |
| `.phpunit.cache/` | Le cache de PHPUnit | PHPUnit |
| `public/bundles/` | Les fichiers publics des bundles, s'il y en a | `assets:install` |
