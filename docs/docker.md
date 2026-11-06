# Docker

## L'essentiel

**Docker** fait tourner une application dans un **conteneur**, un environnement isolé qui contient tout ce qu'il lui faut : ici PHP, le serveur web et les extensions. Vous n'avez donc pas à installer la bonne version de PHP, et le site tourne pareil sur toutes les machines.

| Terme | Sens |
|---|---|
| Image | Un modèle figé, construit à partir du `Dockerfile` |
| Conteneur | Une image en cours d'exécution |
| Couche | Une étape de construction, gardée en cache |
| Volume | Un espace de stockage qui survit au conteneur |
| Bind mount | Un dossier de votre machine rendu visible dans le conteneur |

## Ce qui a changé depuis Debutant-Nv1

[Dockerfile](../Dockerfile), [compose.yaml](../compose.yaml) et [.dockerignore](../.dockerignore) sont **identiques** à ceux du niveau précédent : ce projet n'a besoin de rien de plus, et ce document les explique de nouveau ligne par ligne pour rester autonome.

Trois choses diffèrent ou méritent d'être regardées autrement :

- **La CI change.** Elle tourne sur `ubuntu-24.04` et contrôle une vraie conversion, comme l'explique [ci-cd.md](ci-cd.md).
- **Le mode worker reste absent.** Rien n'a changé, mais ce niveau introduit les services partagés, ce qui rend la raison plus concrète : voir [plus bas](#pourquoi-le-mode-worker-est-désactivé).
- **Une variable compte désormais.** `.env.local` n'était pas utile au niveau précédent. Ici, il peut changer `APP_CONVERSION_PRECISION`, et le conteneur le lit : voir [le bind mount](#composeyaml-ligne-par-ligne).

## Usage

```bash
docker compose up -d --build
```

Construit l'image si besoin et démarre le conteneur en arrière-plan. `-d` rend la main, `--build` reconstruit l'image. Le site répond sur <http://localhost:8080>.

```bash
docker compose ps
docker compose logs app
docker compose exec app php bin/phpunit
docker compose exec app php bin/console debug:router
docker compose down
docker compose down -v
```

Dans l'ordre : voir les conteneurs, lire les journaux, lancer une commande dans le conteneur (deux exemples), tout arrêter, tout arrêter en supprimant aussi les volumes.

## Une image de développement uniquement

**Cette image sert au développement local. Elle ne doit être ni exposée sur Internet ni publiée.**

- Elle tourne en `APP_ENV=dev`, la valeur par défaut de [.env](../.env), que ni le `Dockerfile` ni `compose.yaml` ne changent.
- Elle contient les dépendances de développement : `composer install` n'a pas l'option `--no-dev`.
- Le débogage est actif : une erreur affiche des pages détaillées, avec du code et des informations internes.

Tout cela aide à apprendre, et serait dangereux sur un serveur public. La production, avec son image dédiée, arrive à Junior++Nv5.

## Pourquoi FrankenPHP

**FrankenPHP** est un serveur d'applications PHP construit sur le serveur web **Caddy**. Il tient en un seul conteneur, là où une installation classique en demande deux, avec Nginx et PHP-FPM. Il n'y a donc pas de serveur séparé à configurer, ce qui fait gagner du temps en formation et en test.

Sa configuration par défaut suffit : l'image contient `/etc/frankenphp/Caddyfile`, qui prend `public/` pour racine et envoie à PHP les requêtes qui ne correspondent pas à un fichier. C'est exactement le [contrôleur frontal](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/01-http-et-requete.md).

## Le `Dockerfile`, ligne par ligne

Fichier : [Dockerfile](../Dockerfile).

```dockerfile
FROM dunglas/frankenphp:1.13.5-php8.5.12-trixie
```

L'image de départ. Le tag précise FrankenPHP 1.13.5, PHP 8.5.12 et Debian 13 `trixie`. Une version exacte évite qu'une reconstruction change PHP sans prévenir. Elle garantit aussi le minimum de 8.5.12, voir [versions.md](versions.md).

```dockerfile
RUN install-php-extensions @composer intl zip
```

`install-php-extensions` est un outil fourni par l'image.

- `@composer` installe Composer.
- `intl` apporte les fonctions d'internationalisation.
- `zip` permet à Composer de décompresser les paquets.

OPcache, qui garde le code PHP compilé en mémoire, est déjà intégré à l'image : le demander à `install-php-extensions` afficherait `Module already installed`.

```dockerfile
ENV SERVER_NAME=:80
```

L'adresse sur laquelle Caddy écoute. Sans cette ligne, le Caddyfile utilise `localhost` et Caddy gère le HTTPS. Avec `:80`, il écoute en HTTP simple sur le port 80, pour n'importe quel nom de domaine. C'est ce qu'il faut en local.

```dockerfile
WORKDIR /app
```

Le dossier de travail de l'image : les commandes suivantes s'y exécutent. Le Caddyfile cherche `public/` à partir de lui.

```dockerfile
COPY . /app
```

Copie le projet dans l'image, sauf ce qu'exclut [.dockerignore](../.dockerignore).

```dockerfile
RUN composer install --no-interaction --prefer-dist
```

Installe les dépendances de `composer.lock`. `--no-interaction` interdit toute question : personne n'est là pour répondre. `--prefer-dist` télécharge des archives plutôt que des dépôts Git.

### Le prix de `COPY` avant `composer install`

Docker met chaque couche en cache. Il réutilise une couche tant que ce qu'elle a reçu n'a pas changé, et dès qu'une couche change, toutes les suivantes sont refaites.

`COPY . /app` reçoit tout le projet. Le moindre fichier modifié invalide donc cette couche, et `composer install`, placé juste après, réinstalle toutes les dépendances à chaque construction. Le cache des couches ne sert à rien pour cette étape.

On le garde parce que cela rend le `Dockerfile` très simple à ce niveau. La construction classique copie d'abord `composer.json` et `composer.lock`, installe, puis copie le reste. Ce sera à sa place avec l'image de production.

## `compose.yaml`, ligne par ligne

Fichier : [compose.yaml](../compose.yaml). **Docker Compose** décrit dans un fichier comment lancer les conteneurs.

```yaml
services:
  app:
    build: .
```

Un seul service, nommé `app`, construit à partir du `Dockerfile` du dossier courant.

```yaml
    ports:
      - "127.0.0.1:8080:80"
```

Publie un port du conteneur sur votre machine, au format `adresse:port_machine:port_conteneur`.

- `80` : le port où Caddy écoute dans le conteneur.
- `8080` : le port à utiliser sur votre machine.
- `127.0.0.1` : l'adresse locale. Seule votre machine peut joindre le site.

Écrire simplement `8080:80` ouvrirait le port sur toutes les interfaces réseau, donc à toute personne sur le même réseau. Pour une image avec le débogage actif, ce serait une mauvaise idée.

```yaml
    volumes:
      - .:/app
```

Le **bind mount** : le dossier du projet apparaît dans le conteneur à la place de `/app`. Quand vous modifiez un fichier, le conteneur le voit tout de suite, sans reconstruction.

Attention à une conséquence : **ce montage rend visible tout le dossier, y compris ce que `.dockerignore` exclut**. Un fichier `.env.local` sur votre machine n'est pas dans l'image, mais il est lu par le conteneur en cours d'exécution. De même, `docs/` et `var/` y sont visibles. `.dockerignore` protège l'image, pas le conteneur lancé avec ce montage.

L'essai a été fait avec `APP_CONVERSION_PRECISION=2` dans `.env.local` : le fichier est absent d'une image construite ainsi, mais avec le montage, `debug:dotenv` affiche la valeur `2` dans le conteneur. La [fiche sur la configuration](formations/04-configuration.md) montre son effet sur la page.

Autre risque connu de ce montage : sur un hôte Linux, le conteneur s'exécute en `root`, donc `var/`, `vendor/` et `.phpunit.cache` peuvent appartenir à `root` sur votre machine et demander un `sudo chown` pour être supprimés.

```yaml
      - /app/vendor
```

Un **volume anonyme**, sans nom, placé sur `/app/vendor`. Il existe à cause de la ligne précédente : le bind mount masque l'image entière, y compris le `vendor/` installé pendant la construction. Le `vendor/` utilisé serait alors celui de votre machine, peut-être absent ou installé pour un autre système. Ce volume remet par-dessus une copie du `vendor/` de l'image, créée à son premier démarrage.

**Le piège** : ce volume survit aux redémarrages et aux reconstructions. Après un changement de `composer.lock`, l'image a de nouvelles dépendances, mais le conteneur utilise encore l'ancien `vendor/`, devenu périmé. La solution :

```bash
docker compose down -v
docker compose up -d --build
```

`-v` supprime les volumes, et le prochain démarrage recopie le `vendor/` à jour.

```yaml
      - caddy_data:/data
      - caddy_config:/config

volumes:
  caddy_data:
  caddy_config:
```

Deux **volumes nommés**, déclarés en bas du fichier, pour les données et la configuration de Caddy. L'image place ces données dans `/data` et `/config`, à l'aide des variables `XDG_DATA_HOME` et `XDG_CONFIG_HOME`. C'est l'usage prévu par FrankenPHP : ces données survivent à la recréation du conteneur.

## `.dockerignore`, ligne par ligne

Fichier : [.dockerignore](../.dockerignore). Il liste ce que `COPY . /app` ne doit pas emporter.

| Ligne | Pourquoi l'exclure |
|---|---|
| `.git` | L'historique est inutile dans l'image |
| `.github` | La CI ne sert pas à l'exécution |
| `.env.local` | Des réglages privés de votre machine |
| `.env.local.php` | La version compilée de ces réglages |
| `.env.*.local` | Des réglages privés par environnement |
| `config/secrets/**/*.decrypt.private.php` | Les clés privées du coffre-fort de Symfony |
| `var` | Le cache et les journaux de votre machine |
| `vendor` | Il est recréé par `composer install` dans l'image |
| `docs` | La documentation ne sert pas à faire tourner le site |
| `.DS_Store` | Un fichier technique de macOS |

Une image peut être partagée et chacune de ses couches peut être lue : un secret qui y entre en sort difficilement. Les tests de la documentation passent quand même dans le conteneur, parce que le bind mount y rend `docs/` visible.

## Pourquoi le mode worker est désactivé

FrankenPHP a deux façons de faire tourner PHP :

- **Mode classique** : à chaque requête, l'application démarre et s'arrête. Rien ne reste d'une requête à l'autre.
- **Mode worker** : l'application démarre une seule fois, reste en mémoire et traite les requêtes à la suite. C'est beaucoup plus rapide.

Ce projet utilise le mode classique : dans le Caddyfile de l'image, la ligne `worker` est commentée et aucune variable n'active ce mode.

La raison principale est l'**état partagé**. Vous avez vu dans [la fiche sur l'injection de dépendances](formations/02-injection-de-dependances.md) qu'un service est partagé : `Shared yes`. En mode classique, cet exemplaire disparaît à la fin de la requête. En mode worker, les objets et les variables statiques restent en mémoire entre deux requêtes. Un service qui garde une valeur dans une propriété pourrait servir à un visiteur les données du visiteur précédent. Il faut donc écrire son code en sachant qu'il survit à la requête, et apprendre à repérer ces fuites : c'est trop tôt à ce niveau, où l'on découvre tout juste les services.

Les services de ce projet ne gardent rien entre deux appels et fonctionneraient sans doute en mode worker, mais ce mode n'a pas été essayé ici : il n'apporterait rien à ce niveau.

Il y a une seconde raison : un code qui reste en mémoire doit être rechargé ou redémarré pour qu'un changement soit pris en compte, alors qu'en mode classique, il suffit de recharger la page.

Une page de calcul n'a de toute façon pas besoin de ce gain de vitesse.

## Pourquoi la page peut répondre vide au démarrage

Juste après `docker compose up -d`, un premier appel peut donner ceci :

```text
curl: (52) Empty reply from server
```

Ce n'est pas un bug du site. `docker compose up -d` rend la main dès que le conteneur est lancé, sans attendre que le serveur soit prêt. Docker publie déjà le port, donc la connexion est acceptée, puis elle se ferme sans réponse, car FrankenPHP n'écoute pas encore dans le conteneur. Quelques instants plus tard, tout fonctionne.

C'est pour cela que la [CI](ci-cd.md) lance `curl` avec `--retry-all-errors`. L'option `--retry` seule ne réessaie que certaines erreurs dites transitoires, comme un délai dépassé ou un code 503. Une réponse vide n'en fait pas partie : `curl` s'arrêterait au premier essai et la CI échouerait au hasard. `--retry-all-errors` réessaie quelle que soit l'erreur.

La première vraie réponse peut aussi être un peu lente : Symfony compile alors son cache `dev`.

## Pannes fréquentes

| Symptôme | Cause probable | Solution |
|---|---|---|
| `Empty reply from server` juste après le démarrage | Le serveur démarre encore | Réessayer après quelques secondes |
| `port is already allocated` | Le port 8080 est déjà pris | Arrêter l'autre programme |
| Classe introuvable après un changement de `composer.lock` | Le volume `/app/vendor` est périmé | `docker compose down -v`, puis `docker compose up -d --build` |
| Un réglage inattendu apparaît | Un fichier `.env.local` est lu grâce au bind mount | Vérifier ce fichier sur votre machine |
