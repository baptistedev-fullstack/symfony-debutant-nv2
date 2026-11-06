# Symfony Debutant Nv2 : convertisseur d'unités

Deuxième niveau du parcours de formation Symfony. Le projet est un convertisseur d'unités : un formulaire, une valeur, deux unités et un résultat. Il couvre les longueurs, les masses et les températures. Il n'y a ni base de données, ni composant Form, ni connexion.

Le but n'est pas le convertisseur lui-même, mais de comprendre comment organiser le code d'une application : **services, injection de dépendances et configuration**. Le calcul quitte le contrôleur, Symfony assemble les objets, et un réglage change sans toucher au code.

Ce dépôt se lit : le code d'un côté, la documentation de l'autre. Commencez par [le guide de formation](docs/formations/index.md).

## Prérequis

- Avoir suivi le [niveau précédent](https://github.com/baptistedev-fullstack/symfony-debutant-nv1) : routes, contrôleurs et Twig ne sont pas réexpliqués ici.
- Docker avec Docker Compose : c'est tout ce qu'il faut pour lancer le site.
- Pour travailler hors de Docker : PHP 8.5.12 ou plus, Composer, et si possible le [Symfony CLI](https://symfony.com/download). Installez ensuite les dépendances avec `composer install`, puis lancez le serveur avec `symfony serve`.

## Démarrage

```bash
docker compose up -d --build
```

Ouvrez ensuite <http://localhost:8080>. Le site n'est accessible que depuis votre machine. Juste après le démarrage, la première réponse peut échouer ou demander quelques secondes : [docs/docker.md](docs/docker.md) explique pourquoi.

Pour lancer les tests puis tout arrêter :

```bash
docker compose exec app php bin/phpunit
docker compose down -v
```

L'image Docker de ce dépôt sert uniquement au développement local : elle ne doit être ni exposée ni publiée. La production arrive à Junior++Nv5. Les raisons sont dans [docs/docker.md](docs/docker.md).

## Ce que fait le site

Une seule page, `/`, en `GET`. Le formulaire envoie ses valeurs dans l'URL.

| Catégorie | Unités |
|---|---|
| longueur | `mm`, `cm`, `m`, `km`, `ft`, `mi` |
| masse | `g`, `kg`, `oz`, `lb` |
| température | `C`, `F`, `K` |

| URL | Résultat |
|---|---|
| `/` | Le formulaire vide |
| `/?valeur=1&de=mi&vers=km` | `1 mi = 1.6093 km` |
| `/?valeur=100&de=C&vers=F` | `100 C = 212 F` |
| `/?valeur=abc&de=km&vers=m` | Statut 422 et un message |

## Limites connues

Elles sont assumées à ce niveau :

- Un très grand résultat s'affiche dans la notation scientifique de PHP, par exemple `1.0E+15`.
- Un très petit résultat est arrondi à 4 décimales : `1 mm` donne `0 km`. Avec une précision plus élevée, il passe lui aussi en notation scientifique.

Le détail est dans [la fiche sur les erreurs](docs/formations/06-exceptions-et-erreurs.md).

## Documentation

| Document | Contenu |
|---|---|
| [docs/formations/index.md](docs/formations/index.md) | Ordre de lecture et parcours du code |
| [docs/arborescence.md](docs/arborescence.md) | Chaque dossier et fichier du projet |
| [docs/versions.md](docs/versions.md) | PHP, Symfony, FrankenPHP et chaque paquet |
| [docs/docker.md](docs/docker.md) | Chaque ligne de `Dockerfile`, `compose.yaml` et `.dockerignore` |
| [docs/ci-cd.md](docs/ci-cd.md) | Chaque job et chaque étape de la CI |

Ce niveau n'a pas de base de données : il n'y a donc pas de `docs/base-de-donnees.md`.

## Navigation dans le parcours

- Niveau précédent : [symfony-debutant-nv1](https://github.com/baptistedev-fullstack/symfony-debutant-nv1).
- Niveau suivant : [symfony-debutant-nv3](https://github.com/baptistedev-fullstack/symfony-debutant-nv3) (à venir).

## Licence

MIT, voir [LICENSE](LICENSE).
