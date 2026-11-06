# 4. La configuration

Le [niveau précédent](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/06-configuration-et-environnements.md) a expliqué les environnements, l'ordre des fichiers `.env` et les secrets. Cette fiche va plus loin : comment une valeur de configuration arrive dans **votre** service.

## Ce que c'est

Un **paramètre** est une valeur nommée, écrite dans la configuration de Symfony et figée lorsque le conteneur est compilé.

Une **variable d'environnement** est une valeur donnée au programme de l'extérieur, et lue pendant l'exécution.

Ce projet utilise un exemple de chacun :

| Nom | Type | Valeur | Rôle |
|---|---|---|---|
| `app.conversion.units` | Paramètre | Les unités et leurs facteurs | Les données de `LinearConverter` |
| `APP_CONVERSION_PRECISION` | Variable d'environnement | `4` | Le nombre de décimales de `UnitConverter` |

## Pourquoi ça existe

Écrire ces valeurs dans le PHP fonctionnerait, mais chaque réglage serait caché dans le code, et le changer voudrait dire le modifier.

La séparation répond à une question simple : **cette valeur change-t-elle selon la machine où le site tourne ?**

| | Paramètre | Variable d'environnement |
|---|---|---|
| Où | `config/services.yaml` | `.env`, ou l'environnement réel |
| Versionné | Oui | `.env` oui, `.env.local` non |
| Pour | Ce qui fait partie de l'application | Ce qui dépend de la machine |
| Type | Tableaux, nombres, chaînes | Toujours du texte au départ |
| Lu | À la compilation | À l'exécution |

Un kilomètre fait 1000 mètres partout : les facteurs sont un paramètre. Le nombre de décimales peut différer d'une installation à l'autre : c'est une variable.

Le commentaire de [.env](../../.env) précise `pas un secret`. Une variable d'environnement n'est pas forcément secrète, mais un secret ne s'écrit jamais dans un fichier versionné : voir [la fiche du niveau précédent](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/06-configuration-et-environnements.md).

## Où le voir dans ce projet

- [config/services.yaml](../../config/services.yaml) : le paramètre.
- [.env](../../.env) : la valeur par défaut de la variable.
- [src/Conversion/LinearConverter.php](../../src/Conversion/LinearConverter.php) : l'injection du paramètre.
- [src/Conversion/UnitConverter.php](../../src/Conversion/UnitConverter.php) : l'injection de la variable.
- [tests/Conversion/UnitConverterTest.php](../../tests/Conversion/UnitConverterTest.php) : le test de `round()` avec deux précisions.
- [tests/Conversion/UnitConverterServiceTest.php](../../tests/Conversion/UnitConverterServiceTest.php) : la preuve que la vraie valeur arrive jusqu'au service.

## Comment lire ce code

### Le paramètre

Dans [config/services.yaml](../../config/services.yaml) :

```yaml
parameters:
    app.conversion.units:
        longueur: { mm: 0.001, cm: 0.01, m: 1, km: 1000, ft: 0.3048, mi: 1609.344 }
        masse: { g: 1, kg: 1000, oz: 28.349523125, lb: 453.59237 }
```

La valeur est un tableau à deux niveaux : la catégorie, puis l'unité et son facteur vers l'unité de base, le mètre ou le gramme. Le préfixe `app.` est une convention, pour ne pas se mêler aux paramètres de Symfony.

Le code n'a pas besoin de connaître ces chiffres : ajouter une unité revient à ajouter une entrée ici.

### `#[Autowire(param:)]`

L'autowiring sait trouver un service à partir de son type. Pour un `array`, ce n'est pas possible : il faut dire lequel. C'est le rôle de l'attribut, dans [src/Conversion/LinearConverter.php](../../src/Conversion/LinearConverter.php) :

```php
public function __construct(
    #[Autowire(param: 'app.conversion.units')]
    private array $factors,
) {
}
```

Le code compilé montre l'effet de cette ligne :

```php
new \App\Conversion\LinearConverter($container->parameters['app.conversion.units']);
```

### La variable d'environnement

Dans [.env](../../.env) :

```bash
# Nombre de décimales conservées par le convertisseur (pas un secret)
APP_CONVERSION_PRECISION=4
```

Elle est versionnée, car c'est une valeur par défaut sans risque. [.env.test](../../.env.test) ne la redéfinit pas : les tests reçoivent aussi `4`.

### `#[Autowire(env:)]` et le processeur `int:`

Dans [src/Conversion/UnitConverter.php](../../src/Conversion/UnitConverter.php) :

```php
#[Autowire(env: 'int:APP_CONVERSION_PRECISION')]
private int $precision,
```

Symfony traduit cet attribut en `%env(int:APP_CONVERSION_PRECISION)%`, la syntaxe que vous croiserez aussi dans les fichiers YAML, par exemple dans [config/packages/routing.yaml](../../config/packages/routing.yaml).

Une variable d'environnement est toujours du texte. `int:` est un **processeur** : il convertit ce texte en entier avant de le donner au service. La commande suivante montre les deux étapes :

```bash
php bin/console debug:container --env-var=APP_CONVERSION_PRECISION
```

```text
%env(int:APP_CONVERSION_PRECISION)%
-----------------------------------

  Default value     n/a
  Real value        "4"
  Processed value   4
```

`"4"` entre, `4` sort.

Soyons précis sur son utilité. Sans `int:`, ce projet marche quand même, tests et `lint:container` compris : l'appel `new UnitConverter(...)` se trouve dans le fichier généré `getUnitConverterService.php`, qui n'active pas `strict_types`, donc PHP convertit lui-même `"4"` en `4`. Cette règle dépend du fichier qui fait l'appel : ajouter `declare(strict_types=1);` à `UnitConverter.php` n'y changerait rien. Le processeur rend la conversion explicite et donne une meilleure erreur. Avec `APP_CONVERSION_PRECISION=abc` :

| Écriture | Erreur obtenue |
|---|---|
| Avec `int:` | `Non-numeric env var "APP_CONVERSION_PRECISION" cannot be cast to int.` |
| Sans `int:` | `App\Conversion\UnitConverter::__construct(): Argument #2 ($precision) must be of type int, string given` |

La première désigne la variable fautive. Dans les deux cas, le site répond 500 sur toutes les conversions et même sur le formulaire vide, puisque `index()` a besoin du service. Une configuration fausse doit se voir tout de suite.

### Surcharger avec `.env.local`

Pour essayer une autre précision sur votre machine, sans changer un fichier versionné, créez `.env.local` à la racine :

```bash
APP_CONVERSION_PRECISION=2
```

`debug:dotenv` montre alors d'où vient chaque valeur :

```bash
php bin/console debug:dotenv APP_CONVERSION
```

```text
Scanned Files (in descending priority)
--------------------------------------

 * ⨯ .env.local.php
 * ⨯ .env.dev.local
 * ✓ .env.dev
 * ✓ .env.local
 * ✓ .env

Variables
---------

  Variable                   Value   .env.dev   .env.local   .env
  APP_CONVERSION_PRECISION   2       n/a        2            4
```

La page affiche alors `1 mi = 1.61 km` au lieu de `1 mi = 1.6093 km`, sans vider le cache : la variable est lue à l'exécution. Supprimez ensuite ce fichier. `.gitignore` empêche de le commiter, mais il reste propre à votre machine.

Trois précisions :

- **Une vraie variable d'environnement gagne sur les fichiers.** L'en-tête de `.env` le rappelle : `Real environment variables win over .env files.` Lancée avec `APP_CONVERSION_PRECISION=1` dans l'environnement, la page affiche `1 mi = 1.6 km`, même avec un `.env.local`.
- **Les tests ignorent `.env.local`.** C'est voulu : `Dotenv` l'écarte en environnement `test`, pour que les tests donnent le même résultat chez tout le monde. `php bin/console debug:dotenv APP_CONVERSION --env=test` ne l'affiche pas, et `UnitConverterServiceTest` continue d'attendre `1.6093`.
- **Dans Docker, `.env.local` est lu aussi.** `.dockerignore` le tient hors de l'image, mais le montage `.:/app` le rend visible dans le conteneur lancé. [docker.md](../docker.md) détaille ce point.

### Paramètre contre variable : quand le changement se voit

Un paramètre est écrit dans le conteneur compilé. Une variable est lue pendant la requête. La différence se voit en `prod`, où Symfony ne surveille pas les fichiers de configuration :

| Changement | En `dev` | En `prod` |
|---|---|---|
| Une variable d'environnement | Visible tout de suite | Visible tout de suite |
| Le paramètre de `services.yaml` | Visible tout de suite : Symfony recompile | Invisible avant `cache:clear` |

Une exception à « visible tout de suite » : après `composer dump-env`, le fichier `.env.local.php` fige les valeurs des fichiers `.env`. Les modifier ensuite ne change plus rien tant que ce fichier n'a pas été régénéré par une nouvelle commande `composer dump-env`. Une vraie variable d'environnement reste prioritaire.

C'est vérifiable : en `prod`, passer `km` à `2000` dans le YAML laisse `1 km = 1000 m` à l'écran, jusqu'à `php bin/console cache:clear --env=prod`.

## Commandes officielles utilisées

```bash
php bin/console debug:container --parameter=app.conversion.units --format=json
```

Affiche la valeur complète d'un paramètre. `php bin/console debug:container --parameters` les liste tous, mais coupe les valeurs longues dans son tableau :

```text
  app.conversion.units                         {"longueur":{"mm":0.001,"cm":0.01,"m":1,"km":1000,"ft":0.304...
```

```bash
php bin/console debug:dotenv
php bin/console debug:dotenv APP_CONVERSION
php bin/console debug:dotenv APP_CONVERSION --env=test
```

Montrent les fichiers `.env` lus et la valeur retenue, éventuellement filtrés sur un nom ou pour un autre environnement.

```bash
php bin/console debug:container --env-var=APP_CONVERSION_PRECISION
```

Montre la valeur brute et la valeur traitée par le processeur.

## À retenir

- Paramètre : fait partie de l'application, versionné, figé à la compilation.
- Variable d'environnement : dépend de la machine, lue à l'exécution, toujours du texte au départ.
- `#[Autowire(param:)]` et `#[Autowire(env:)]` amènent une valeur là où un type ne suffit pas.
- `int:` convertit le texte en entier et donne une erreur claire si la valeur est fausse.
- `.env.local` surcharge `.env` sur votre machine, sauf en `test`, et ne se commite jamais.
- En cas de doute : `debug:container --parameters`, `debug:container --env-var` et `debug:dotenv`.
