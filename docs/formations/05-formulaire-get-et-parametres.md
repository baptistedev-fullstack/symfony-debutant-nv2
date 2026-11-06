# 5. Formulaire GET et paramètres

## Ce que c'est

La **chaîne de requête**, ou _query string_, est la partie d'une URL située après le `?` :

```text
/?valeur=1&de=mi&vers=km
  └───────┬────────────┘
    trois paramètres, séparés par &
```

Un formulaire HTML en `method="get"` place ses champs à cet endroit. `#[MapQueryParameter]` est l'attribut de Symfony qui les transmet directement aux arguments d'un contrôleur.

## Pourquoi ça existe

Un formulaire n'a pas toujours besoin de `POST`. `GET` convient à une demande sans effet de bord, qui lit ou calcule sans rien modifier sur le serveur. Une conversion ne crée rien et ne change rien, et elle peut être refaite sans conséquence.

Le fait d'utiliser `GET` a des avantages concrets : le résultat a une URL, qu'on peut mettre en favori, partager ou recharger sans que le navigateur demande de confirmer un nouvel envoi.

`#[MapQueryParameter]` évite d'écrire `$request->query->get('valeur')` : l'action déclare ce qu'elle attend dans sa signature, avec son type.

## Où le voir dans ce projet

- [src/Controller/ConverterController.php](../../src/Controller/ConverterController.php) : la route et les arguments de `index()`.
- [templates/converter/index.html.twig](../../templates/converter/index.html.twig) : le formulaire écrit à la main.
- [tests/Controller/ConverterControllerTest.php](../../tests/Controller/ConverterControllerTest.php) : les cas passants et les saisies fausses.

## Comment lire ce code

Les routes sont expliquées dans [la fiche du niveau précédent](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/03-routage.md), et Twig dans [la sienne](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/05-twig.md). Seul ce qui est nouveau est détaillé ici.

### Le formulaire

Dans [templates/converter/index.html.twig](../../templates/converter/index.html.twig) :

```twig
<form method="get" action="{{ path('app_converter') }}">
```

`method="get"` envoie les champs dans l'URL, et chaque attribut `name` donne le nom d'un paramètre : `name="valeur"`, `name="de"`, `name="vers"`. Ils correspondent aux noms des arguments de `index()`.

```twig
<input type="number" step="any" id="valeur" name="valeur" required{% if valeur is not null %} value="{{ valeur }}"{% endif %}>
```

`type="number"` et `required` aident l'utilisateur, mais ne protègent de rien. Le navigateur peut être contourné : il suffit de taper l'URL à la main. C'est pourquoi le contrôleur vérifie tout de nouveau.

`step="any"` autorise les décimales. Sans lui, le navigateur n'accepte que des entiers.

Les listes d'unités sont construites à partir de `units`, avec les boucles déjà vues. Les catégories deviennent des `<optgroup>`, et `selected` rétablit le choix de l'utilisateur après l'envoi.

### `#[MapQueryParameter]`

```php
#[Route('/', name: 'app_converter', methods: ['GET'])]
public function index(
    UnitConverter $converter,
    #[MapQueryParameter] ?string $valeur = null,
    #[MapQueryParameter] ?string $de = null,
    #[MapQueryParameter] ?string $vers = null,
): Response {
```

Le nom de l'argument donne le nom du paramètre d'URL. La logique se trouve dans `QueryParameterValueResolver`, dans `vendor/symfony/http-kernel/Controller/ArgumentResolver/`, qui traite trois cas :

| URL | Valeur reçue | Pourquoi |
|---|---|---|
| `/` | `null` | Paramètre absent, et l'argument accepte `null` |
| `/?valeur=` | `''` | Paramètre présent mais vide : c'est une chaîne vide, pas `null` |
| `/?valeur[]=1` | Aucune : réponse 404 | Un tableau n'est pas une valeur simple, le contrôleur n'est pas appelé |

### Pourquoi `?valeur[]=1` donne une 404

En PHP, `valeur[]=1` crée un tableau, or l'argument est une chaîne. Le résolveur filtre la valeur en exigeant un scalaire, c'est-à-dire une valeur simple, avec `FILTER_REQUIRE_SCALAR`. Le filtre échoue et lève une exception :

```php
if (null === $value && !($attribute->flags & \FILTER_NULL_ON_FAILURE)) {
    throw HttpException::fromStatusCode($validationFailedCode, \sprintf('Invalid query parameter "%s".', $name));
}
```

`$validationFailedCode` vient de l'attribut, dont la valeur par défaut est `Response::HTTP_NOT_FOUND`, soit 404. Le `?` de `?string` n'y change rien : il couvre le paramètre absent, pas le paramètre invalide. L'erreur survient **avant** le contrôleur, ce qui évite une erreur de type, donc une 500. `testArrayParameterIsNotFound` le vérifie.

Ce statut pourrait se changer avec l'option `validationFailedStatusCode` de l'attribut. Ce projet garde la valeur par défaut.

### Pourquoi `?string` et pas `?float`

On pourrait écrire `?float $valeur`. Le résolveur valide alors le nombre lui-même, mais un `abc` donne une 404, sans formulaire ni message : c'est le résultat de l'essai.

Ce projet veut réafficher la page avec une explication. Il reçoit donc du texte, et le valide lui-même :

```php
$number = filter_var($valeur, \FILTER_VALIDATE_FLOAT);
```

`filter_var()` renvoie le nombre, ou `false` s'il n'est pas valide. La comparaison suivante est stricte, avec `false !== $number` : `0` est une valeur correcte, et `/?valeur=0&de=km&vers=m` affiche bien `0 km = 0 m`.

| `valeur` | `filter_var()` |
|---|---|
| `1` | `1.0` |
| ` 1 `, avec des espaces | `1.0` : d'où le `trim()` du contrôleur avant de réafficher la saisie |
| `abc`, vide, ou `12,5` | `false` |
| `1e999` | `false` : le nombre dépasse ce qu'un flottant peut contenir |
| Absent (`null`) | `false` |

La virgule est refusée : `12,5` n'est pas un nombre pour PHP. La [fiche suivante](06-exceptions-et-erreurs.md) montre la réponse dans ces cas.

### Trois situations distinctes

```php
if (null === $valeur && null === $de && null === $vers) {
    return $this->render('converter/index.html.twig', $context);
}
```

Si aucun paramètre n'est présent, le visiteur arrive sur la page : pas d'erreur à afficher. Dès qu'un paramètre existe, tout est vérifié : `/?de=km` répond 422. Après cette étape, soit la saisie est valide et le service calcule, soit le message d'erreur s'affiche.

Le tableau `$context` contient toutes les clés dès le départ, même à `null`. C'est nécessaire parce que `strict_variables` transforme une variable absente du template en erreur. Ce réglage suit le mode debug : il est déjà actif en `dev` et en `test`, et désactivé en `prod`, comme l'explique [la fiche Twig du niveau précédent](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/05-twig.md). Retirer la clé `error` fait échouer des tests de la page.

### Pourquoi pas de jeton CSRF

Une attaque **CSRF**, pour _Cross-Site Request Forgery_, consiste à piéger un visiteur connecté pour que son navigateur envoie une requête à sa place : changer un mot de passe, par exemple. Un jeton CSRF prouve que le formulaire vient bien du site.

Ici, il n'y a rien à protéger :

- La requête ne change rien sur le serveur : elle calcule.
- Le site n'a ni compte, ni donnée liée à un visiteur.
- Le pire scénario est donc de faire calculer une conversion à quelqu'un.

Ce choix tient parce que la page est sans effet de bord. Un formulaire qui écrit des données, lui, se fait en `POST` avec un jeton.

### `POST` est refusé

`methods: ['GET']` limite la route. Un `POST` reçoit une 405 du routeur, comme au niveau précédent, avec l'en-tête `Allow: GET`. La méthode `HEAD`, qui demande les seuls en-têtes, reste acceptée sur une route `GET` : elle répond 200.

### Pourquoi le composant Form attend Debutant-Nv4

Symfony a un composant **Form**, qui n'est pas installé ici, pas plus que Validator : ils sont absents de [composer.json](../../composer.json). Le formulaire est écrit à la main, pour deux raisons.

- Il n'y a que trois champs, sans donnée à enregistrer.
- Vous voyez ce que Form fera pour vous : écrire le HTML, lire les valeurs, valider, réafficher la saisie et le message d'erreur.

Tout ce travail est visible dans ce contrôleur et ce template. Quand `Debutant-Nv4` introduira Form et Validator, vous saurez quel problème ils résolvent.

## Commandes officielles utilisées

```bash
php bin/console debug:router
```

```text
  Name             Method   Path
  _preview_error   ANY      /_error/{code}.{_format}
  app_converter    GET      /
```

La route ne contient pas la chaîne de requête : `/?valeur=1` et `/` correspondent à la même route.

```bash
php bin/console router:match /
```

Confirme que `app_converter` répond et affiche son contrôleur.

```bash
curl -i 'http://localhost:8080/?valeur=1&de=mi&vers=km'
curl -g -i 'http://localhost:8080/?valeur[]=1'
curl -i -X POST http://localhost:8080/
```

Essaient la page en montrant le statut, avec Docker démarré. Les guillemets sont nécessaires, car `&` a un sens pour le terminal. `-g` empêche `curl` d'interpréter les crochets.

## À retenir

- `GET` convient à ce qui ne change rien sur le serveur, et donne une URL partageable.
- `#[MapQueryParameter]` lit la chaîne de requête et remplit un argument du contrôleur.
- Paramètre absent : `null`. Paramètre vide : `''`. Paramètre tableau : 404, avant le contrôleur.
- Le navigateur aide, mais seule la vérification du serveur compte.
- Pas de changement d'état, pas de compte : pas de jeton CSRF ici.
- Le formulaire est écrit à la main : Form et Validator arrivent à `Debutant-Nv4`.
