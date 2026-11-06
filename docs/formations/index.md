# Guide de formation : Debutant Nv2

Ce guide accompagne la lecture du code. Vous n'avez rien à écrire : ouvrez chaque fiche à côté des fichiers qu'elle cite et suivez les liens.

## Ce que vous saurez expliquer à la fin

- Ce qu'est un service, et pourquoi le calcul n'a pas sa place dans un contrôleur.
- Comment Symfony construit vos objets et leur donne ce dont ils ont besoin.
- Comment ajouter une catégorie d'unités sans modifier la classe qui les utilise.
- Ce qui va dans un paramètre, et ce qui va dans une variable d'environnement.
- Ce que répond le site à une saisie fausse, et qui produit chaque code HTTP.
- Quel type de test prouve quoi, et pourquoi deux flottants ne se comparent pas au strict.

## Ordre de lecture

Chaque fiche suit le même plan : ce que c'est, pourquoi ça existe, où le voir dans ce projet, comment lire ce code, les commandes officielles utilisées, à retenir.

| Ordre | Fiche | Question à laquelle elle répond |
|---|---|---|
| 1 | [Les services](01-services.md) | Où mettre le code qui n'est pas du HTTP ? |
| 2 | [L'injection de dépendances](02-injection-de-dependances.md) | Qui crée les objets ? |
| 3 | [Interfaces et tags](03-interfaces-et-tags.md) | Comment ajouter une catégorie sans toucher à l'existant ? |
| 4 | [La configuration](04-configuration.md) | Où ranger une valeur qui peut changer ? |
| 5 | [Formulaire GET et paramètres](05-formulaire-get-et-parametres.md) | Comment la saisie arrive-t-elle au contrôleur ? |
| 6 | [Exceptions et erreurs](06-exceptions-et-erreurs.md) | Que se passe-t-il quand ça ne va pas ? |
| 7 | [Tests unitaires et doublures](07-tests-unitaires-et-doublures.md) | Comment tester une classe seule ? |

Puis deux documents de référence, à lire une fois et à rouvrir au besoin :

- [Docker](../docker.md) : comment le site tourne dans un conteneur.
- [CI/CD](../ci-cd.md) : ce que GitHub vérifie à chaque changement.

Deux autres servent de carte : [l'arborescence](../arborescence.md) et [les versions](../versions.md).

## Déjà vu au niveau précédent

Ces sujets ne sont pas réexpliqués ici. Rouvrez la fiche de `Debutant-Nv1` si un point vous échappe.

| Sujet | Fiche de Debutant-Nv1 | Ce qui sert dans ce niveau |
|---|---|---|
| HTTP et la requête | [01-http-et-requete.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/01-http-et-requete.md) | Le contrôleur frontal, les statuts HTTP |
| Composer et Flex | [02-composer-et-flex.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/02-composer-et-flex.md) | Les dépendances et les recettes |
| Le routage | [03-routage.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/03-routage.md) | `#[Route]`, `path()`, la réponse 405 |
| Les contrôleurs | [04-controleurs.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/04-controleurs.md) | `AbstractController`, `render()` |
| Twig | [05-twig.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/05-twig.md) | L'héritage, les boucles, l'échappement |
| Configuration et environnements | [06-configuration-et-environnements.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/06-configuration-et-environnements.md) | `APP_ENV`, l'ordre des fichiers `.env`, les secrets |
| Les tests fonctionnels | [07-tests.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/07-tests.md) | `WebTestCase`, les assertions, les fournisseurs de données |
| Git | [08-git.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/08-git.md) | Les commits, la création et le nommage des branches |

Ce niveau va plus loin sur deux de ces sujets : la configuration, avec les paramètres et les variables injectées dans un service, et les tests, avec les tests unitaires. Les fiches 4 et 7 partent de ce que vous savez déjà.

## Parcours du code conseillé

Suivez une vraie requête, `GET /?valeur=1&de=mi&vers=km`, fichier par fichier.

| Étape | Fichier | Ce qu'il faut y regarder |
|---|---|---|
| 1 | [public/index.php](../../public/index.php) | Le point d'entrée unique : il crée le Kernel |
| 2 | [config/services.yaml](../../config/services.yaml) | Les réglages du conteneur, et le paramètre `app.conversion.units` |
| 3 | [.env](../../.env) | `APP_CONVERSION_PRECISION=4`, la précision utilisée à l'étape 8 |
| 4 | [src/Controller/ConverterController.php](../../src/Controller/ConverterController.php) | La route `app_converter` et les arguments de `index()` : Symfony fournit `$converter`, et lit `$valeur`, `$de` et `$vers` dans l'URL |
| 5 | [src/Conversion/UnitConverter.php](../../src/Conversion/UnitConverter.php) | `units()` donne la liste des unités au contrôleur, qui vérifie `mi` et `km` |
| 6 | [src/Conversion/ConverterInterface.php](../../src/Conversion/ConverterInterface.php) | Le contrat commun, et le tag qui rassemble les convertisseurs |
| 7 | [src/Conversion/LinearConverter.php](../../src/Conversion/LinearConverter.php) | `supports('mi', 'km')` répond oui, puis `convert()` calcule `1 * 1609.344 / 1000`, soit `1.609344` |
| 8 | [src/Conversion/UnitConverter.php](../../src/Conversion/UnitConverter.php) | `is_finite()`, puis `round()` à 4 décimales : `1.6093` |
| 9 | [templates/converter/index.html.twig](../../templates/converter/index.html.twig) | Le formulaire est réaffiché, avec `1 mi = 1.6093 km` |
| 10 | [templates/base.html.twig](../../templates/base.html.twig) | Le squelette HTML commun |
| 11 | [tests/Controller/ConverterControllerTest.php](../../tests/Controller/ConverterControllerTest.php) | La preuve que la page fonctionne, avec `1 km = 1000 m` |
| 12 | [tests/Conversion/UnitConverterTest.php](../../tests/Conversion/UnitConverterTest.php) | La même logique testée sans Symfony |

[TemperatureConverter](../../src/Conversion/TemperatureConverter.php) est lui aussi créé, parce que `units()` interroge tous les convertisseurs. En revanche, sa méthode `convert()` n'est pas appelée sur cette requête : `LinearConverter` passe en premier et accepte `mi` et `km`. Vous pouvez refaire le parcours avec `GET /?valeur=100&de=C&vers=F` pour voir l'autre chemin.

## Pour essayer

Rien de tout cela n'est obligatoire, mais voir le site tourner aide à lire le code.

```bash
docker compose up -d --build
docker compose exec app php bin/console debug:container --tag=app.converter
docker compose exec app php bin/console debug:dotenv APP_CONVERSION
docker compose exec app php bin/phpunit
docker compose down -v
```

Le site répond sur <http://localhost:8080>.
