# 1. Les services

## Ce que c'est

Un **service** est un objet qui fait un travail : convertir une valeur, envoyer un e-mail, lire un fichier. Il n'a pas d'identité propre à la façon d'un utilisateur ou d'un article : c'est un outil, que le reste de l'application utilise.

Dans Symfony, le mot a un sens plus précis : un service est un objet **créé et fourni par le conteneur**, une pièce de Symfony expliquée dans [la fiche suivante](02-injection-de-dependances.md).

Ce projet a trois services écrits pour lui, plus son contrôleur :

| Classe | Son travail |
|---|---|
| `LinearConverter` | Les conversions par simple facteur : longueurs et masses |
| `TemperatureConverter` | Les conversions de températures, qui ne se font pas par facteur |
| `UnitConverter` | Choisir le bon convertisseur, contrôler et arrondir le résultat |
| `ConverterController` | Lire la requête et produire la réponse |

## Pourquoi ça existe

Au [niveau précédent](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/04-controleurs.md), tout tenait dans le contrôleur, parce qu'il n'y avait presque rien à calculer. Ici, y écrire les conversions poserait trois problèmes.

- **Le test.** Pour vérifier un calcul, il faudrait simuler une requête HTTP. Un service se teste avec un simple appel de méthode.
- **La réutilisation.** Une commande console ou une API auraient besoin du même calcul. Dans un contrôleur, ce code n'est accessible à personne d'autre.
- **La lecture.** Le contrôleur mélangerait deux sujets : le dialogue HTTP et les règles de conversion.

La règle à retenir : le contrôleur traduit entre HTTP et le reste de l'application. Il lit la requête, appelle un service et produit une réponse. Il ne sait pas combien de mètres vaut un mile.

## Où le voir dans ce projet

- [src/Conversion/LinearConverter.php](../../src/Conversion/LinearConverter.php) : les conversions par facteur.
- [src/Conversion/TemperatureConverter.php](../../src/Conversion/TemperatureConverter.php) : les conversions de températures.
- [src/Conversion/UnitConverter.php](../../src/Conversion/UnitConverter.php) : le service que le contrôleur utilise.
- [src/Controller/ConverterController.php](../../src/Controller/ConverterController.php) : le contrôleur, qui ne contient aucune formule.

## Comment lire ce code

### Ces classes sont écrites à la main

`make:controller` a créé le contrôleur et `make:test` les fichiers de test, mais **aucune commande `make:*` ne génère un service comme ceux-ci**. Lancez `php bin/console list make` : chaque commande vise un rôle précis (contrôleur, commande console, écouteur, formulaire), et aucune ne crée une simple classe de logique.

Ce n'est pas un manque : un service est une classe PHP ordinaire. Il n'hérite de rien de Symfony, et il suffit de la placer dans `src/` pour que Symfony la prenne en charge. Toutes les classes de `src/Conversion/` ont donc été écrites à la main.

### Rien dans un service ne dépend de HTTP

Ouvrez [src/Conversion/LinearConverter.php](../../src/Conversion/LinearConverter.php). Il n'y a ni `Request`, ni `Response`, ni template :

```php
public function convert(float $value, string $from, string $to): float
{
    $category = $this->categoryOf($from, $to)
        ?? throw new UnsupportedConversionException(sprintf('Conversion impossible entre « %s » et « %s ».', $from, $to));

    return $value * $this->factors[$category][$from] / $this->factors[$category][$to];
}
```

Un `float` entre, un `float` sort. Le seul lien avec Symfony est un attribut sur le constructeur, qui ne change rien à la logique et que la [fiche sur la configuration](04-configuration.md) explique.

Le calcul passe par une **unité de base** : chaque unité a un facteur vers le mètre, ou vers le gramme pour les masses. `1 mi` vaut `1609.344` mètres, et `1 km` vaut `1000` mètres, donc `1 * 1609.344 / 1000` donne `1.609344` kilomètres.

Les températures ne marchent pas ainsi : 0 °C ne vaut pas 0 °F. Elles ont donc leur propre classe, qui passe par le kelvin avec `toKelvin()` puis `fromKelvin()`.

### Un service par responsabilité

`LinearConverter` et `TemperatureConverter` savent calculer. Ils ne savent pas arrondir, ni afficher quoi que ce soit.

`UnitConverter` ne connaît aucune formule. Sa méthode `convert()` cherche un convertisseur qui accepte les deux unités, lui confie le calcul, puis contrôle et arrondit le résultat :

```php
foreach ($this->converters as $converter) {
    if (!$converter->supports($from, $to)) {
        continue;
    }

    $result = $converter->convert($value, $from, $to);
    if (!is_finite($result)) {
        throw new UnsupportedConversionException('Le résultat est trop grand pour être affiché.');
    }

    // + 0.0 transforme -0.0 en 0.0 : sans lui, un petit résultat négatif arrondi s'afficherait « -0 ».
    return round($result, $this->precision) + 0.0;
}
```

Il y a ainsi un seul endroit où s'applique la précision et un seul où l'on refuse un résultat infini, quel que soit le convertisseur.

### Ce qui reste dans le contrôleur

[src/Controller/ConverterController.php](../../src/Controller/ConverterController.php) garde ce qui concerne la requête : savoir si le formulaire a été envoyé, vérifier que `valeur` est un nombre et que les unités existent, choisir le message et le statut. Le calcul tient en une ligne :

```php
$context['result'] = $converter->convert($number, $context['de'], $context['vers']);
```

### Le service ne garde rien entre deux appels

Ni `LinearConverter`, ni `TemperatureConverter`, ni `UnitConverter` ne modifient leurs propriétés après la construction : elles contiennent seulement des réglages. Le même appel donne toujours le même résultat, quel que soit le nombre d'appels précédents.

C'est une bonne habitude. Symfony ne crée en effet qu'un exemplaire de chaque service, que tout le monde partage. Un service qui garderait la dernière valeur convertie dans une propriété pourrait mélanger deux calculs. La ligne `Shared yes` de la fiche suivante montre ce partage.

## Commandes officielles utilisées

```bash
php bin/console list make
```

Liste les générateurs disponibles. Aucun ne produit un service de logique.

```bash
php bin/console make:controller ConverterController --no-interaction
php bin/console make:test TestCase 'Conversion\LinearConverterTest'
```

Les générateurs utilisés sur ce projet, pour le contrôleur et pour les tests. Le code obtenu a ensuite été remplacé par le vrai contenu.

```bash
php bin/console debug:container 'App\Conversion\UnitConverter'
```

Montre comment Symfony voit un service. Les guillemets sont nécessaires à cause des `\`. La sortie est lue dans la fiche suivante.

## À retenir

- Un service est un objet qui fait un travail, créé par le conteneur de Symfony.
- La logique va dans un service : elle se teste et se réutilise sans HTTP.
- Le contrôleur traduit entre HTTP et les services, et rien de plus.
- Aucune commande `make:*` ne génère ces classes : elles s'écrivent à la main, dans `src/`.
- Un service partagé ne doit pas garder de données d'un appel à l'autre.
