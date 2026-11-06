# 7. Tests unitaires et doublures

Le [niveau précédent](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/07-tests.md) a présenté PHPUnit, les tests fonctionnels, les assertions et les fournisseurs de données. Il n'y avait alors aucun test unitaire, faute de logique à isoler. C'est ici qu'ils arrivent.

## Ce que c'est

Un **test unitaire** vérifie une classe seule, sans démarrer Symfony : on crée l'objet avec `new`, on appelle une méthode et on vérifie le résultat.

Une **doublure** de test (_test double_) est un faux objet qui prend la place d'une dépendance, pour tester une classe sans la vraie.

Ce projet utilise trois classes de base, qui prouvent des choses différentes :

| Classe | Démarre Symfony ? | Fichiers de test | Tests |
|---|---|---|---|
| `TestCase` | Non | `LinearConverterTest`, `TemperatureConverterTest`, `UnitConverterTest` | 22 |
| `KernelTestCase` | Oui, sans requête HTTP | `UnitConverterServiceTest` | 3 |
| `WebTestCase` | Oui, avec un navigateur simulé | `ConverterControllerTest` | 15 |

S'y ajoutent les 4 tests de `DocsConsistencyTest`, repris du [niveau précédent](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/07-tests.md) sans changer leurs règles.

## Pourquoi ça existe

Les trois niveaux se complètent :

| Type | Ce qu'il prouve | Ce qu'il ne prouve pas |
|---|---|---|
| `TestCase` | Le calcul est juste | Que Symfony sait assembler cette classe |
| `KernelTestCase` | Le conteneur injecte les bons services et la bonne configuration | Que la page fonctionne |
| `WebTestCase` | L'utilisateur reçoit la bonne réponse | Tous les cas de calcul : ce serait lent et verbeux |

Un test unitaire est très rapide, et il désigne précisément ce qui casse. C'est pourquoi les nombreux cas de calcul vivent dans `TestCase`, et que la page ne teste que quelques conversions, plus les erreurs.

## Où le voir dans ce projet

- [tests/Conversion/LinearConverterTest.php](../../tests/Conversion/LinearConverterTest.php) : un test unitaire simple.
- [tests/Conversion/TemperatureConverterTest.php](../../tests/Conversion/TemperatureConverterTest.php) : un test unitaire avec fournisseurs de données.
- [tests/Conversion/UnitConverterTest.php](../../tests/Conversion/UnitConverterTest.php) : un test unitaire avec doublures.
- [tests/Conversion/UnitConverterServiceTest.php](../../tests/Conversion/UnitConverterServiceTest.php) : un service réel, tiré du conteneur.
- [tests/Controller/ConverterControllerTest.php](../../tests/Controller/ConverterControllerTest.php) : la page, de la requête à la réponse.
- [config/packages/framework.yaml](../../config/packages/framework.yaml) et [config/packages/twig.yaml](../../config/packages/twig.yaml) : les réglages propres à l'environnement de test.

## Comment lire ce code

### `TestCase` : aucune trace de Symfony

Ouvrez [tests/Conversion/LinearConverterTest.php](../../tests/Conversion/LinearConverterTest.php) :

```php
protected function setUp(): void
{
    $this->converter = new LinearConverter([
        'longueur' => ['m' => 1, 'km' => 1000, 'mi' => 1609.344],
        'masse' => ['g' => 1, 'kg' => 1000],
    ]);
}
```

`setUp()` s'exécute avant chaque test. Vous y voyez un des bénéfices de [l'injection de dépendances](02-injection-de-dependances.md) : l'objet reçoit ses données dans son constructeur, donc le test lui donne des unités à lui, plus courtes que la vraie configuration. Il ne dépend pas de `services.yaml`.

Pour vérifier qu'une exception est levée, on l'annonce **avant** l'appel :

```php
$this->expectException(UnsupportedConversionException::class);

$this->converter->convert(1.0, 'km', 'kg');
```

### Pourquoi un flottant ne se compare pas au strict

Les flottants sont stockés en binaire, et beaucoup de décimales n'y ont pas de représentation exacte. L'exemple classique :

```bash
php -r 'var_dump(0.1 + 0.2 === 0.3, 0.1 + 0.2);'
```

```text
bool(false)
float(0.30000000000000004)
```

Comparer deux flottants avec `===` ou `assertSame()` peut donc échouer sur un calcul correct. `assertEqualsWithDelta()` accepte un écart maximal, ici `1e-9`, soit 0,000000001 :

```php
self::assertEqualsWithDelta(3.218688, $this->converter->convert(2.0, 'mi', 'km'), 1e-9);
```

Ce test-là passerait d'ailleurs avec `assertSame()` : `2.0 * 1609.344 / 1000` tombe exactement sur `3.218688`. C'est un hasard du calcul, et une formule équivalente pourrait ne plus l'être. La tolérance décrit ce qu'on veut vraiment vérifier : un résultat juste, à une marge près.

Vous trouverez malgré tout des `assertSame()` sur des flottants, dans [tests/Conversion/UnitConverterTest.php](../../tests/Conversion/UnitConverterTest.php) et [tests/Conversion/UnitConverterServiceTest.php](../../tests/Conversion/UnitConverterServiceTest.php) :

```php
self::assertSame(1.6093, (new UnitConverter([$stub], 4))->convert(1.0, 'mi', 'km'));
```

Ce n'est pas une contradiction : ici, le résultat a été **arrondi**, et le test vérifie justement cet arrondi. `round(1.609344, 4)` donne le même flottant que le littéral `1.6093`, ce que `assertSame()` confirme. Une tolérance large cacherait une erreur d'arrondi. La règle pratique est donc : un calcul brut se compare avec un delta, et un résultat arrondi ou une valeur fixe peuvent se comparer strictement.

### `createStub()` : remplacer une dépendance

[tests/Conversion/UnitConverterTest.php](../../tests/Conversion/UnitConverterTest.php) teste `UnitConverter` **sans** vrai convertisseur :

```php
private function converter(bool $supports = false, float $result = 0.0, array $units = []): ConverterInterface
{
    $stub = $this->createStub(ConverterInterface::class);
    $stub->method('supports')->willReturn($supports);
    $stub->method('convert')->willReturn($result);
    $stub->method('units')->willReturn($units);

    return $stub;
}
```

`createStub()` fabrique un objet qui respecte l'interface et dont on fixe les réponses. Un **stub** ne vérifie rien : il se contente de répondre. Le test est ainsi maître des valeurs reçues :

```php
public function testRefusesAnInfiniteResult(): void
{
    $this->expectException(UnsupportedConversionException::class);

    (new UnitConverter([$this->converter(supports: true, result: INF)], 4))->convert(1.0, 'a', 'b');
}
```

Aucune unité réelle n'existe, puisque `'a'` et `'b'` sont inventées : ce test parle uniquement de `UnitConverter`. Il obtient `INF` en une ligne, sans chercher quelle conversion déborde. Si un jour `LinearConverter` a un bogue, ce test restera vert, et seul `LinearConverterTest` échouera : on sait où chercher.

Les classes de `src/Conversion/` sont `final`, ce qui les rend impossibles à doubler. Doubler `ConverterInterface` ne pose pas ce problème : c'est une autre utilité de l'interface. Le constructeur de `UnitConverter` accepte un tableau de stubs, puisqu'il attend un `iterable`.

### `KernelTestCase` : le vrai conteneur

Un test unitaire ne dit rien de `#[AutowireIterator]`, de `#[Autowire]` ni de `services.yaml`, puisqu'il fait les `new` lui-même. Il faut donc un autre test :

```php
final class UnitConverterServiceTest extends KernelTestCase
{
    public function testContainerInjectsTheConvertersAndThePrecision(): void
    {
        $converter = self::getContainer()->get(UnitConverter::class);

        self::assertSame(1.6093, $converter->convert(1.0, 'mi', 'km'));
        self::assertSame(212.0, $converter->convert(100.0, 'C', 'F'));
    }
```

`getContainer()` démarre l'application si besoin. Le résultat prouve toute la chaîne : le paramètre des unités est arrivé, les deux convertisseurs sont là, et la précision de `.env` vaut 4.

### Comment un test obtient un service privé

`UnitConverter` est privé : [la fiche 2](02-injection-de-dependances.md) a montré `Public no`. Le conteneur normal refuse de le donner. `self::getContainer()` renvoie pourtant autre chose : le **conteneur de test**, un service nommé `test.service_container`, qui existe parce que [config/packages/framework.yaml](../../config/packages/framework.yaml) contient, sous `when@test` :

```yaml
framework:
    test: true
```

Ce conteneur spécial donne accès aux services privés. Il a toutefois une limite : **il ne peut donner que ce qui existe encore**. À la compilation, Symfony supprime les services privés que personne n'utilise.

`UnitConverterServiceTest` fonctionne donc parce que le contrôleur injecte `UnitConverter`. Le service est utilisé, il survit à la compilation, et le test peut le demander. C'est pour cela qu'aucune configuration de test spéciale n'est nécessaire dans `services.yaml`.

Essayez de retirer cet usage. Avec une classe présente dans `src/` mais que personne n'utilise, ou avec un contrôleur qui n'injecte plus `UnitConverter`, le test échoue ainsi :

```text
Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException: The "App\Conversion\Unused" service or alias has been removed or inlined when the container was compiled.
```

### `WebTestCase` : le dernier maillon

[tests/Controller/ConverterControllerTest.php](../../tests/Controller/ConverterControllerTest.php) suit la forme vue au [niveau précédent](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/07-tests.md). Il couvre ce que les autres ne voient pas : les paramètres de l'URL, le statut HTTP, le formulaire réaffiché, les messages.

```php
self::assertResponseStatusCodeSame(200);
self::assertSelectorTextContains('output.result', '1 km = 1000 m');
self::assertSelectorExists('input[name="valeur"][value="1"]');
```

Les saisies fausses passent par un fournisseur de données, `invalidNumbers()`, et chaque cas compte pour un test : c'est pourquoi la classe a 12 méthodes de test, mais 15 tests.

### Ce qui change en environnement de test

- `strict_variables` fait d'une variable absente d'un template une erreur. Il suit le mode debug : il est donc déjà actif en `dev` et en `test`, et désactivé en `prod`. La ligne placée sous `when@test` dans [config/packages/twig.yaml](../../config/packages/twig.yaml) le garantit seulement en `test`, même si le debug est coupé. Voir [la fiche Twig du niveau précédent](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/05-twig.md).
- `.env.local` n'est pas lu, voir [la fiche sur la configuration](04-configuration.md).
- `failOnDeprecation`, `failOnNotice` et `failOnWarning`, dans [phpunit.dist.xml](../../phpunit.dist.xml), font échouer la suite au moindre avertissement.

## Commandes officielles utilisées

```bash
php bin/phpunit
php bin/phpunit tests/Conversion
php bin/phpunit tests/Conversion/UnitConverterTest.php
php bin/phpunit --filter testRefusesAnInfiniteResult
```

Lancent toute la suite, un dossier, un fichier, ou les tests dont le nom correspond. Pendant le développement, on lance le test en cours, puis la suite complète avant de valider.

```bash
php bin/console make:test TestCase 'Conversion\LinearConverterTest'
php bin/console make:test KernelTestCase 'Conversion\UnitConverterServiceTest'
```

`make:test` crée un fichier de test vide. Le premier argument choisit la classe de base.

## À retenir

- `TestCase` prouve le calcul, `KernelTestCase` l'assemblage, `WebTestCase` la réponse à l'utilisateur.
- Beaucoup de cas en unitaire, peu de cas en fonctionnel.
- Un flottant calculé se compare avec `assertEqualsWithDelta()`.
- Un stub remplace une dépendance et donne des réponses fixées.
- Recevoir ses dépendances dans le constructeur est ce qui rend ces tests possibles.
- Le conteneur de test donne accès aux services privés, tant qu'ils sont utilisés quelque part.
