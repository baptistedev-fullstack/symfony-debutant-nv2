# 2. L'injection de dépendances

## Ce que c'est

Une **dépendance** est un objet dont une classe a besoin pour travailler. `UnitConverter` a besoin de convertisseurs et d'une précision.

L'**injection de dépendances** consiste à recevoir ces éléments de l'extérieur, au lieu de les créer soi-même. La classe les demande dans son constructeur :

```php
public function __construct(
    #[AutowireIterator('app.converter')]
    private iterable $converters,
    #[Autowire(env: 'int:APP_CONVERSION_PRECISION')]
    private int $precision,
) {
}
```

Le **conteneur de services** est l'objet de Symfony qui fait ce travail : il sait créer chaque service et lui fournir ce qu'il demande. L'**autowiring**, ou câblage automatique, est sa capacité à trouver seul quoi fournir, en lisant les types du constructeur.

## Pourquoi ça existe

Comparez avec ce que le contrôleur devrait écrire sans conteneur :

```php
$converter = new UnitConverter(
    [
        new LinearConverter([
            'longueur' => ['mm' => 0.001, 'cm' => 0.01, 'm' => 1, /* ... */],
            'masse' => ['g' => 1, 'kg' => 1000, /* ... */],
        ]),
        new TemperatureConverter(),
    ],
    (int) $_ENV['APP_CONVERSION_PRECISION'],
);
```

Ce code a plusieurs défauts :

- Le contrôleur doit connaître la construction de tous les objets, leurs réglages compris.
- Tout autre code qui veut convertir doit répéter ces lignes.
- Ajouter une catégorie oblige à modifier cet assemblage.
- Dans un test, impossible de remplacer un convertisseur par une doublure : le `new` est écrit en dur. La [fiche sur les tests](07-tests-unitaires-et-doublures.md) montre à quel point recevoir ses dépendances rend le test simple.

Avec le conteneur, le contrôleur écrit seulement ce dont il a besoin :

```php
public function index(
    UnitConverter $converter,
```

## Où le voir dans ce projet

- [config/services.yaml](../../config/services.yaml) : les règles du conteneur.
- [src/Conversion/UnitConverter.php](../../src/Conversion/UnitConverter.php) : l'injection par le constructeur.
- [src/Conversion/LinearConverter.php](../../src/Conversion/LinearConverter.php) : l'injection d'un paramètre.
- [src/Conversion/TemperatureConverter.php](../../src/Conversion/TemperatureConverter.php) : un service sans aucune dépendance.
- [src/Controller/ConverterController.php](../../src/Controller/ConverterController.php) : l'injection dans une méthode de contrôleur.

## Comment lire ce code

### `config/services.yaml`, ligne par ligne

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true
```

`_defaults` donne des réglages communs à tous les services **de ce fichier**.

- `autowire: true` : le conteneur lit les types du constructeur pour trouver quoi injecter.
- `autoconfigure: true` : il ajoute seul des réglages selon ce que la classe est ou implémente. C'est ainsi qu'un contrôleur reçoit ses tags, et que les convertisseurs reçoivent le leur, voir [la fiche suivante](03-interfaces-et-tags.md).

```yaml
    App\:
        resource: '../src/'
```

Cette entrée demande de faire de **chaque classe de `src/`** un service, dont l'identifiant est le nom complet de la classe, par exemple `App\Conversion\UnitConverter`. C'est pour cela qu'une nouvelle classe fonctionne sans rien déclarer.

Cette règle prend même `UnsupportedConversionException`, qui n'est pas un outil. Ce n'est pas gênant : personne ne la réclame au conteneur, qui la retire à la compilation.

Le paramètre `app.conversion.units`, plus haut dans le même fichier, est expliqué dans [la fiche sur la configuration](04-configuration.md).

### Deux façons de recevoir une dépendance

| Façon | Où | Quand |
|---|---|---|
| Constructeur | `UnitConverter`, `LinearConverter` | Tout service : c'est la façon normale |
| Argument de méthode | `ConverterController::index()` | Les contrôleurs uniquement |

La seconde est propre aux contrôleurs. Ils portent le tag `controller.service_arguments`, visible plus bas, et le composant qui appelle `index()` cherche la valeur de chaque argument. Il prend `$converter` dans le conteneur, puis `$valeur`, `$de` et `$vers` dans l'URL, comme le montre [la fiche 5](05-formulaire-get-et-parametres.md). `TemperatureConverter`, lui, n'a pas de constructeur : il n'y a rien à lui injecter.

### Ce que le conteneur a compris

```bash
php bin/console debug:container 'App\Conversion\UnitConverter'
```

```text
  Service ID       App\Conversion\UnitConverter
  Class            App\Conversion\UnitConverter
  Tags             -
  Public           no
  Synthetic        no
  Lazy             no
  Shared           yes
  Abstract         no
  Autowired        yes
  Autoconfigured   yes
  Arguments        Tagged Iterator for "app.converter"
                   - Service(App\Conversion\LinearConverter)
                   - Service(App\Conversion\TemperatureConverter)
                   %env(int:APP_CONVERSION_PRECISION)%
  Usages           .service_locator.zr68sVq

 ! [NOTE] The "App\Conversion\UnitConverter" service or alias has been removed
 !        or inlined when the container was compiled.
```

| Ligne | Ce qu'elle dit |
|---|---|
| `Public no` | Le service est **privé** : on ne peut pas le demander au conteneur par son nom pendant l'exécution, seulement le recevoir par injection |
| `Shared yes` | Un seul exemplaire est créé, et partagé par tous ceux qui le demandent |
| `Autowired yes` | Vient de `autowire: true` |
| `Autoconfigured yes` | Vient de `autoconfigure: true` |
| `Arguments` | Les deux arguments du constructeur : les services tagués, puis la variable d'environnement |
| `Usages` | Qui utilise ce service : ici, un petit localisateur créé pour les arguments de `index()` |

La note finale surprend : le service n'a pas disparu. Elle rappelle qu'il n'est pas accessible directement dans le conteneur compilé, puisqu'il est privé. La page de conversion prouve qu'il est bien créé.

Le contrôleur, lui, est public, et porte les tags dont on parlait :

```text
  Tags             controller.service_arguments
                   routing.controller
                   container.service_subscriber
  Calls            setContainer
  Public           yes
```

### Le conteneur écrit vos `new`

Il n'y a pas de magie à l'exécution. Symfony **compile** le conteneur : il génère du PHP dans `var/cache/dev/`, et ne recommence que si un fichier surveillé change : en `dev`, la configuration, mais aussi les classes de `src/`. Après une première requête, cherchez `getUnitConverterService.php` dans un dossier `var/cache/dev/Container*/` :

```php
return $container->privates['App\\Conversion\\UnitConverter'] = new \App\Conversion\UnitConverter(new RewindableGenerator(function () use ($container) {
    yield 0 => ($container->privates['App\\Conversion\\LinearConverter'] ?? $container->load('getLinearConverterService'));
    yield 1 => ($container->privates['App\\Conversion\\TemperatureConverter'] ??= new \App\Conversion\TemperatureConverter());
}, 2), $container->getEnv('int:APP_CONVERSION_PRECISION'));
```

C'est bien le `new` du début de cette fiche, écrit par Symfony, avec son argument `getEnv()`. La partie `privates[...] =` retient l'objet créé : c'est le `Shared yes` en action.

Le nom du dossier change d'une compilation à l'autre. Ce fichier ne se modifie jamais : il est généré.

### Quand l'autowiring ne peut pas choisir

Le conteneur s'appuie sur les types. Il lui faut donc une réponse unique. `ConverterInterface` a deux implémentations : si un argument demandait ce type, le conteneur ne saurait pas laquelle donner. L'essai sur `index()` échoue avec la fin d'un message plus long, qui commence par `Controller "App\Controller\ConverterController::index" requires the "$x" argument that could not be resolved.` :

```text
Cannot autowire argument $x of "App\Controller\ConverterController::index()": it references interface "App\Conversion\ConverterInterface" but no such service exists. You should maybe alias this interface to one of these existing services: "App\Conversion\LinearConverter", "App\Conversion\TemperatureConverter".
```

Ce projet ne demande pas un convertisseur, mais tous : c'est le sujet de [la fiche suivante](03-interfaces-et-tags.md).

## Commandes officielles utilisées

```bash
php bin/console debug:container 'App\Conversion\UnitConverter'
php bin/console debug:container 'App\Controller\ConverterController'
```

Détaillent un service : son caractère public, ses tags, ses arguments.

```bash
php bin/console debug:autowiring Conversion --all
```

Liste les types que vous pouvez demander dans un constructeur, filtrés sur le mot `Conversion` :

```text
 App\Conversion\LinearConverter

 App\Conversion\TemperatureConverter

 App\Conversion\UnitConverter

 App\Conversion\UnsupportedConversionException
```

L'option `--all` ajoute les services qui n'ont pas d'alias. C'est le cas des nôtres : sans elle, la commande répond seulement `4 more concrete services would be displayed when adding the "--all" option.` Remarquez que `ConverterInterface` n'est pas dans la liste, pour la raison vue plus haut.

```bash
php bin/console lint:container
```

Vérifie que les valeurs injectées sont compatibles avec les types déclarés. La [CI](../ci-cd.md) le lance à chaque changement. Elle ne voit pas tout : pendant l'essai sur `index()` décrit plus haut, elle répondait OK, et l'erreur n'est apparue qu'à l'appel de la page.

## À retenir

- Une classe demande ses dépendances, elle ne les crée pas.
- Le conteneur construit les objets, et l'autowiring trouve quoi injecter à partir des types.
- `App\:` fait de chaque classe de `src/` un service, `_defaults` règle le comportement.
- Les services sont privés et partagés par défaut.
- Le conteneur est compilé en PHP dans `var/cache/` : il écrit les `new` à votre place.
- En cas de doute : `debug:container` et `debug:autowiring`.
