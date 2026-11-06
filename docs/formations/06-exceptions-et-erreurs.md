# 6. Exceptions et erreurs

## Ce que c'est

Une **exception** est un objet qui interrompt le déroulement normal du code pour signaler un problème. Elle remonte de méthode en méthode jusqu'à ce qu'un `catch` la traite.

Un **code de statut HTTP** dit au client comment s'est passée sa requête. Ce projet en utilise quatre : 200, 404, 405 et 422.

`UnsupportedConversionException` est l'exception de ce projet. Elle veut dire : « cette conversion ne peut pas être faite ».

## Pourquoi ça existe

Sans exception, `convert()` devrait renvoyer une valeur spéciale, comme `null` ou `-1`, que chaque appelant devrait penser à tester. Or `-1` peut être un résultat correct. Une exception ne peut pas être ignorée par mégarde : si personne ne la traite, elle arrête le traitement et produit une erreur visible.

Avoir **sa propre classe** d'exception permet de ne capturer que ce cas. Une vraie panne, comme une erreur de programmation, n'est pas interceptée : elle reste une 500 et se voit.

## Où le voir dans ce projet

- [src/Conversion/UnsupportedConversionException.php](../../src/Conversion/UnsupportedConversionException.php) : l'exception.
- [src/Conversion/LinearConverter.php](../../src/Conversion/LinearConverter.php), [src/Conversion/TemperatureConverter.php](../../src/Conversion/TemperatureConverter.php) et [src/Conversion/UnitConverter.php](../../src/Conversion/UnitConverter.php) : là où elle est levée.
- [src/Controller/ConverterController.php](../../src/Controller/ConverterController.php) : là où elle est capturée.
- [templates/converter/index.html.twig](../../templates/converter/index.html.twig) : l'affichage du message.
- [tests/Controller/ConverterControllerTest.php](../../tests/Controller/ConverterControllerTest.php) : chaque cas d'erreur.

## Comment lire ce code

### L'exception

```php
final class UnsupportedConversionException extends \InvalidArgumentException
{
}
```

La classe est vide : elle n'apporte qu'un nom, et c'est tout ce qu'il faut. `\InvalidArgumentException` est une exception standard de PHP, qui veut dire « un argument reçu n'est pas acceptable ». Le `\` indique qu'elle n'est pas dans le namespace `App\Conversion`.

Elle est levée dans cinq situations :

| Où | Quand |
|---|---|
| `LinearConverter::convert()` | Les unités ne sont pas dans une même catégorie |
| `TemperatureConverter::convert()` | Les unités ne sont pas des températures |
| `TemperatureConverter::convert()` | La température est sous le zéro absolu |
| `UnitConverter::convert()` | Le résultat n'est pas un nombre fini |
| `UnitConverter::convert()` | Aucun convertisseur n'accepte ces unités |

Le contrat le signale dans [ConverterInterface](../../src/Conversion/ConverterInterface.php), par `@throws UnsupportedConversionException` au-dessus de `convert()`. Un service lève l'exception : il ne sait pas s'il est appelé depuis une page web, et ne choisit donc aucun statut HTTP.

### La capture

C'est le contrôleur qui traduit l'exception en réponse :

```php
try {
    $context['result'] = $converter->convert($number, $context['de'], $context['vers']);
} catch (UnsupportedConversionException) {
    $context['error'] = 'Cette conversion n\'est pas possible.';

    return $this->render('converter/index.html.twig', $context, new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
}
```

`catch (UnsupportedConversionException)` n'a pas de variable : PHP le permet quand on n'utilise pas l'exception, et c'est le cas ici.

### Les statuts de ce projet

| Statut | Sens | Exemple | Qui répond |
|---|---|---|---|
| 200 | Tout va bien | `/?valeur=1&de=km&vers=m` | Le contrôleur |
| 404 | Rien à cet endroit | `/?valeur[]=1` | `#[MapQueryParameter]`, avant le contrôleur |
| 405 | Méthode non autorisée | `POST /` | Le routeur, avant le contrôleur |
| 422 | Requête comprise, mais contenu inutilisable | `/?valeur=abc&de=km&vers=m` | Le contrôleur |

Les statuts 404 et 405 sont déjà couverts par [le niveau précédent](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/03-routage.md). La 404 de ce projet est expliquée dans [la fiche précédente](05-formulaire-get-et-parametres.md).

**422** est nouveau. La page reste affichée, avec le formulaire et un message. Il serait tentant de répondre 200, puisqu'une page s'affiche, mais le statut dirait alors « tout va bien », ce qui est faux. Un programme qui appelle cette URL, comme les tests, peut ainsi distinguer un succès d'un échec.

Le statut se passe en troisième argument de `render()` :

```php
return $this->render('converter/index.html.twig', $context, new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
```

`Response::HTTP_UNPROCESSABLE_ENTITY` vaut 422 : la constante évite un nombre sans explication. Avec un formulaire du composant Form, que `Debutant-Nv4` présentera, `AbstractController` pose ce statut seul lorsque le formulaire est invalide. Ici, c'est écrit à la main.

Voici les réponses observées :

| URL | Statut | Message |
|---|---|---|
| `/?valeur=abc&de=km&vers=m` | 422 | `Indiquez un nombre valide et deux unités.` |
| `/?valeur=&de=km&vers=m` | 422 | `Indiquez un nombre valide et deux unités.` |
| `/?valeur=12,5&de=km&vers=m` | 422 | `Indiquez un nombre valide et deux unités.` |
| `/?valeur=1e999&de=km&vers=m` | 422 | `Indiquez un nombre valide et deux unités.` |
| `/?valeur=1&de=km` | 422 | `Indiquez un nombre valide et deux unités.` |
| `/?valeur=1&de=%3Cscript%3E&vers=m` | 422 | `Indiquez un nombre valide et deux unités.` |
| `/?valeur=1&de=km&vers=kg` | 422 | `Cette conversion n'est pas possible.` |
| `/?valeur=-300&de=C&vers=F` | 422 | `Cette conversion n'est pas possible.` |
| `/?valeur=1e308&de=km&vers=mm` | 422 | `Cette conversion n'est pas possible.` |

Aucune de ces saisies ne produit une 500.

### Un message fixe plutôt que la saisie

Les deux messages sont écrits en dur. Aucun ne contient ce que l'utilisateur a tapé. Le contrôleur n'affiche pas non plus le message de l'exception, alors que certains contiennent les unités reçues.

Deux raisons :

- **Ne jamais réécrire une saisie brute dans une page.** Si `de` valait `<script>...</script>`, le réafficher exposerait à une attaque **XSS**, du code injecté et exécuté dans la page. L'[échappement de Twig](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/05-twig.md) protège déjà, mais ne pas réécrire la saisie est une défense de plus. `testUnknownUnitIsNotReflected` vérifie que `<script>` n'apparaît pas dans le HTML.
- **Le message d'une exception s'adresse au développeur.** Ce texte n'est pas pensé pour un visiteur.

Le prix à payer est connu : un visiteur reçoit le même `Cette conversion n'est pas possible.`, qu'il s'agisse d'unités incompatibles, du zéro absolu ou d'un résultat trop grand. Le message précis, comme `Une température ne peut pas être inférieure au zéro absolu.`, existe dans l'exception, mais n'est pas affiché.

La même règle vaut pour les valeurs réaffichées dans le formulaire. Seules les valeurs validées reviennent dans la page :

```php
if (false !== $number) {
    $context['valeur'] = trim($valeur);
}
$context['de'] = \in_array($de, $knownUnits, true) ? $de : null;
```

Une unité inconnue est remplacée par `null`, et un nombre invalide n'est pas remis dans le champ. L'utilisateur doit donc le ressaisir : c'est un choix.

### Les flottants et `is_finite()`

Un **flottant** (`float`) est la façon dont un ordinateur représente les nombres à virgule. Il a une limite : au-delà d'environ `1.8e308`, le résultat devient la valeur spéciale `INF`, l'infini. PHP ne lève aucune erreur pour autant. Le calcul continue :

```text
1e308 km vers mm : 1e308 * 1000 = INF
```

Sans contrôle, l'écran afficherait `INF`. C'est pourquoi `UnitConverter` vérifie chaque résultat :

```php
$result = $converter->convert($value, $from, $to);
if (!is_finite($result)) {
    throw new UnsupportedConversionException('Le résultat est trop grand pour être affiché.');
}
```

`is_finite()` renvoie `false` pour `INF` et pour `NAN`, une autre valeur spéciale qui veut dire « ce n'est pas un nombre ». Le contrôle est placé dans `UnitConverter`, et non dans chaque convertisseur, pour qu'aucun n'y échappe.

Il faut noter une conséquence : `LinearConverter` multiplie avant de diviser. `/?valeur=1e308&de=km&vers=km` répond donc 422, alors que le résultat tiendrait dans un flottant, car le calcul intermédiaire déborde. Un nombre aussi grand n'a pas d'usage réel ici.

### Le zéro absolu et la tolérance

Sous 0 kelvin, une température n'existe pas. [src/Conversion/TemperatureConverter.php](../../src/Conversion/TemperatureConverter.php) la refuse, avec une petite marge :

```php
private const TOLERANCE = 1e-9;
```

```php
if ($kelvin < -self::TOLERANCE) {
```

Comme les flottants sont approximatifs, un calcul qui devrait tomber sur 0 peut tomber très légèrement en dessous, et cette marge évite de le refuser à tort. C'est une précaution : `-273.15 C` et `-459.67 F` tombent exactement sur 0 avec ce code. L'effet se voit à la frontière : `-0.0000000001 K` passe, et `-0.00000001 K` est refusé.

### Le `-0`

Un flottant peut valoir `-0.0`, et PHP l'affiche `-0`. Sans précaution, `-0.00001 m` vers `km` afficherait `= -0 km`. La ligne de `UnitConverter` est donc :

```php
return round($result, $this->precision) + 0.0;
```

`-0.0 + 0.0` donne `0.0`. `testNegativeZeroIsDisplayedAsZero` vérifie le résultat à l'écran.

### Une limite connue : la notation scientifique

Le template affiche le flottant tel quel, avec `{{ result }}`. PHP le transforme en texte avec 14 chiffres significatifs, le réglage `precision`, au-delà desquels il passe à la **notation scientifique** :

| URL | Affichage |
|---|---|
| `/?valeur=1000000&de=km&vers=mm` | `1000000 km = 1000000000000 mm` |
| `/?valeur=1000000000000000&de=m&vers=m` | `1000000000000000 m = 1.0E+15 m` |
| `/?valeur=1000000000000&de=km&vers=mm` | `1000000000000 km = 1.0E+18 mm` |
| `/?valeur=123456789012345&de=m&vers=m` | `123456789012345 m = 1.2345678901234E+14 m` |

`1.0E+15` veut dire 1 × 10¹⁵. Le résultat reste juste à l'arrondi près, mais il est peu lisible. Le dernier exemple montre qu'un chiffre se perd à l'affichage.

Du côté des très petits résultats, la précision de 4 décimales intervient d'abord : `1 mm` vers `km` donne `0 km`. Avec une précision plus élevée, la notation scientifique apparaît aussi : avec `APP_CONVERSION_PRECISION=6`, la même conversion affiche `1 mm = 1.0E-6 km`.

C'est une limite connue de ce niveau, qui se corrigerait en formatant le nombre dans le template. Elle n'est pas traitée ici, pour ne pas ajouter de sujet.

## Commandes officielles utilisées

```bash
php bin/phpunit tests/Controller
```

Lance tous les cas d'erreur de la page. La sortie contient deux lignes commençant par `[error] Uncaught PHP Exception`, l'une pour la 405 et l'autre pour la 404. Elles sont normales : Symfony journalise ces exceptions, et ces deux tests les provoquent exprès.

```bash
curl -s -o /dev/null -w '%{http_code}\n' 'http://localhost:8080/?valeur=abc&de=km&vers=m'
```

Affiche le statut d'une URL, ici `422`, avec Docker démarré. Dans un navigateur, une page en 422 s'affiche normalement : seul l'onglet réseau de ses outils de développement montre le statut.

## À retenir

- Une exception signale un problème, et son type permet de ne capturer que ce qu'on sait traiter.
- Un service lève l'exception, le contrôleur choisit la réponse.
- Une saisie refusée donne 422 avec un message, jamais une 500.
- Les messages affichés sont fixes : la saisie brute ne revient jamais dans la page.
- Un calcul de flottants peut donner `INF` sans erreur : `is_finite()` le refuse.
- Les très grands nombres s'affichent en notation scientifique : c'est une limite assumée.
