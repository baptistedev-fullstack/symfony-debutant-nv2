# 3. Interfaces et tags

## Ce que c'est

Une **interface** est un contrat : elle liste des méthodes, sans leur code. Une classe qui l'implémente promet de les fournir.

Un **tag** est une étiquette posée sur un service. Elle n'agit pas seule : elle permet de demander au conteneur tous les services qui la portent.

Ce projet associe les deux. Tous les convertisseurs respectent `ConverterInterface`, ils reçoivent automatiquement le tag `app.converter`, et `UnitConverter` demande tous les services qui portent ce tag.

## Pourquoi ça existe

`UnitConverter` pourrait recevoir ses convertisseurs un par un :

```php
public function __construct(
    private LinearConverter $linear,
    private TemperatureConverter $temperature,
) {
}
```

Chaque nouveau type de conversion obligerait alors à modifier cette classe, déjà testée et qui fonctionne. Avec une interface et un tag, `UnitConverter` ne connaît aucun convertisseur par son nom : il manipule « tous ceux qui respectent le contrat ». Une nouvelle catégorie s'ajoute à côté, sans le toucher.

## Où le voir dans ce projet

- [src/Conversion/ConverterInterface.php](../../src/Conversion/ConverterInterface.php) : le contrat et le tag.
- [src/Conversion/LinearConverter.php](../../src/Conversion/LinearConverter.php) et [src/Conversion/TemperatureConverter.php](../../src/Conversion/TemperatureConverter.php) : les deux implémentations.
- [src/Conversion/UnitConverter.php](../../src/Conversion/UnitConverter.php) : la récupération de tous les convertisseurs.
- [tests/Conversion/UnitConverterServiceTest.php](../../tests/Conversion/UnitConverterServiceTest.php) : la preuve que le conteneur les injecte bien.
- [tests/Controller/ConverterControllerTest.php](../../tests/Controller/ConverterControllerTest.php) : un test qui compte les catégories affichées.

## Comment lire ce code

### Le contrat

```php
#[AutoconfigureTag('app.converter')]
interface ConverterInterface
{
    public function units(): array;

    public function supports(string $from, string $to): bool;

    public function convert(float $value, string $from, string $to): float;
}
```

| Méthode | Question à laquelle elle répond |
|---|---|
| `units()` | Quelles unités proposes-tu, par catégorie ? |
| `supports()` | Sais-tu convertir de cette unité vers celle-là ? |
| `convert()` | Quel est le résultat ? |

Le formulaire se remplit avec `units()`. Le choix du convertisseur se fait avec `supports()`, le calcul avec `convert()`.

`implements ConverterInterface`, sur chaque classe, oblige PHP à vérifier que ces trois méthodes existent avec ces signatures.

### `#[AutoconfigureTag]` : poser le tag une seule fois

L'attribut est sur l'**interface**, pas sur les classes. Il veut dire : « toute classe qui implémente ceci reçoit le tag `app.converter` ».

Cette règle n'est appliquée que parce que [config/services.yaml](../../config/services.yaml) contient `autoconfigure: true`. Passé à `false`, plus aucun service ne porte le tag, et la commande de contrôle répond `No tags found that match "app.converter".`

Le nom `app.converter` est choisi par ce projet : le préfixe `app.` le distingue des tags de Symfony. Il apparaît à deux endroits, et ces deux chaînes doivent rester identiques.

Vérifiez le résultat :

```bash
php bin/console debug:container --tag=app.converter
```

```text
  Service ID                            Class name
  App\Conversion\LinearConverter        App\Conversion\LinearConverter
  App\Conversion\TemperatureConverter   App\Conversion\TemperatureConverter
```

### `#[AutowireIterator]` : tout récupérer

Dans [src/Conversion/UnitConverter.php](../../src/Conversion/UnitConverter.php) :

```php
/**
 * @param iterable<ConverterInterface> $converters
 */
public function __construct(
    #[AutowireIterator('app.converter')]
    private iterable $converters,
```

L'attribut remplit l'argument avec tous les services portant ce tag. Le type `iterable` veut dire : « quelque chose que `foreach` peut parcourir ». PHP ne vérifie pas le type des éléments : `iterable<ConverterInterface>`, dans le commentaire, renseigne le lecteur et les outils.

Le conteneur ne donne pas un tableau, mais un générateur, comme le montre le code compilé vu dans [la fiche précédente](02-injection-de-dependances.md). Un convertisseur n'est donc créé que lorsque la boucle l'atteint. Dans `RewindableGenerator`, le mot _rewindable_ veut dire que la liste peut être reparcourue depuis le début, ce qui est nécessaire ici : le contrôleur appelle `units()`, puis `convert()`, qui bouclent tous les deux.

Un test peut, lui, donner un simple tableau, puisqu'un tableau est aussi `iterable` : c'est ce que fait [tests/Conversion/UnitConverterTest.php](../../tests/Conversion/UnitConverterTest.php).

### Qui répond ?

`UnitConverter::convert()` essaie les convertisseurs dans l'ordre, et le premier qui répond oui à `supports()` fait le calcul. Aucune priorité n'est déclarée ici : l'ordre est celui qu'affiche `debug:container --tag`. Comme aucune unité n'appartient à deux convertisseurs, cet ordre ne change pas le résultat.

Si personne n'accepte les unités, la boucle se termine et l'exception est levée :

```php
throw new UnsupportedConversionException(sprintf('Conversion impossible entre « %s » et « %s ».', $from, $to));
```

`units()` fusionne les réponses de tous les convertisseurs, avec `array_merge_recursive()`. Le résultat contient `longueur` et `masse`, de `LinearConverter`, puis `température`, de `TemperatureConverter`.

### Ajouter une catégorie

Il y a deux cas, selon que la conversion se fait par un facteur ou non.

**Avec un simple facteur.** Il suffit d'une ligne dans le paramètre de [config/services.yaml](../../config/services.yaml), sans écrire de PHP :

```yaml
parameters:
    app.conversion.units:
        longueur: { mm: 0.001, cm: 0.01, m: 1, km: 1000, ft: 0.3048, mi: 1609.344 }
        masse: { g: 1, kg: 1000, oz: 28.349523125, lb: 453.59237 }
        duree: { s: 1, min: 60, h: 3600 }
```

**Avec un calcul différent.** Créez une classe dans `src/Conversion/` qui implémente le contrat :

```php
<?php

namespace App\Conversion;

final class DureeConverter implements ConverterInterface
{
    private const SECONDES = ['s' => 1, 'min' => 60, 'h' => 3600];

    public function units(): array
    {
        return ['durée' => array_keys(self::SECONDES)];
    }

    public function supports(string $from, string $to): bool
    {
        return isset(self::SECONDES[$from], self::SECONDES[$to]);
    }

    public function convert(float $value, string $from, string $to): float
    {
        if (!$this->supports($from, $to)) {
            throw new UnsupportedConversionException(sprintf('Conversion impossible entre « %s » et « %s ».', $from, $to));
        }

        return $value * self::SECONDES[$from] / self::SECONDES[$to];
    }
}
```

Ces durées tiendraient dans le paramètre : la classe ne sert ici qu'à montrer le mécanisme. Aucune ligne de configuration n'est nécessaire. `App\:` en fait un service, `autoconfigure` lui pose le tag, et `UnitConverter` la reçoit sans changer :

```text
  App\Conversion\DureeConverter         App\Conversion\DureeConverter
  App\Conversion\LinearConverter        App\Conversion\LinearConverter
  App\Conversion\TemperatureConverter   App\Conversion\TemperatureConverter
```

Les deux façons ont été essayées : `/?valeur=90&de=min&vers=h` affiche `90 min = 1.5 h`, et la nouvelle catégorie apparaît dans les listes. Les règles restent vraies : `/?valeur=90&de=min&vers=km` répond 422, car une durée ne se convertit pas en distance.

Une chose change quand même, dans les deux cas : `testEmptyFormIsDisplayed`, dans [tests/Controller/ConverterControllerTest.php](../../tests/Controller/ConverterControllerTest.php), attend exactement 3 catégories et échoue, avec `was expected to be found 3 time(s) but was found 4 time(s)`. Ce test doit donc être mis à jour. C'est son rôle : il signale qu'une fonctionnalité visible a changé. `UnitConverter` et ses tests, eux, ne bougent pas.

## Commandes officielles utilisées

```bash
php bin/console debug:container --tag=app.converter
```

Liste les services qui portent ce tag. C'est le premier réflexe si une nouvelle classe n'est pas prise en compte.

```bash
php bin/console debug:container 'App\Conversion\LinearConverter'
```

La ligne `Tags` doit indiquer `app.converter`, et la ligne `Usages`, `App\Conversion\UnitConverter`.

## À retenir

- Une interface fixe un contrat, et un tag regroupe des services.
- `#[AutoconfigureTag]` sur l'interface tague toutes ses implémentations, grâce à `autoconfigure: true`.
- `#[AutowireIterator]` injecte tous les services d'un tag.
- `UnitConverter` ne connaît aucun convertisseur par son nom : une catégorie s'ajoute sans le modifier.
- Un test qui compte les catégories doit évoluer avec elles : c'est normal.
