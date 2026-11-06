# CI/CD

## L'essentiel

L'**intégration continue**, ou CI, vérifie automatiquement chaque changement sur une machine propre. Elle attrape ce qui marche « seulement chez moi ». Le **déploiement continu**, ou CD, publie ensuite le résultat : ce niveau n'en a pas.

**GitHub Actions** exécute un **workflow** décrit dans [.github/workflows/ci.yml](../.github/workflows/ci.yml).

| Terme | Sens |
|---|---|
| Workflow | Le fichier complet, déclenché par un événement |
| Job | Un groupe d'étapes, sur sa propre machine |
| Étape | Une commande, ou une action |
| Action | Un morceau de CI réutilisable, publié par d'autres |
| Runner | La machine temporaire qui exécute un job |

## Ce qui a changé depuis Debutant-Nv1

Le workflow est celui du niveau précédent, à deux différences près, et il est de nouveau expliqué en entier ci-dessous.

| Changement | Debutant-Nv1 | Ici | Pourquoi |
|---|---|---|---|
| Machine des deux jobs | `ubuntu-latest` | `ubuntu-24.04` | `ubuntu-latest` est une étiquette que GitHub fait passer d'une version d'Ubuntu à la suivante. Une version nommée ne bouge pas sans un changement dans ce dépôt |
| Contrôle HTTP | `/projets` et `Mini-blog` | Une conversion et `1 km = 1000 m` | Le contrôle prouve maintenant que le calcul traverse toute l'application |

Le prix de `ubuntu-24.04` est celui de tout épinglage : il faudra changer cette version soi-même quand elle sera retirée par GitHub.

Rien n'a changé pour le mode worker, toujours absent : [docker.md](docker.md#pourquoi-le-mode-worker-est-désactivé) explique pourquoi. Le job `docker` teste donc le même mode d'exécution que votre conteneur local.

## Les premières lignes

```yaml
name: CI
```

Le nom affiché dans l'onglet **Actions** de GitHub.

```yaml
on:
  push:
    branches: [main]
  pull_request:
```

Les déclencheurs : un `push` sur `main`, ou toute pull request. Une branche seule ne déclenche rien tant qu'elle n'a pas de pull request.

```yaml
permissions:
  contents: read
```

GitHub donne à chaque workflow un jeton, le `GITHUB_TOKEN`, pour agir sur le dépôt. Cette ligne le limite à la lecture du code, le seul droit nécessaire ici. C'est le **principe du moindre privilège** : si une action utilisée était compromise, elle ne pourrait ni pousser de code, ni modifier une release avec ce jeton.

## L'épinglage des actions par SHA

Chaque action est écrite ainsi :

```yaml
- uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
```

Le long nombre est le **SHA complet** d'un commit de l'action, et le commentaire rappelle la version à laquelle il correspond.

Il serait plus court d'écrire `actions/checkout@v7`, mais un tag n'est qu'une étiquette : son propriétaire, ou un attaquant qui a pris le contrôle du dépôt de l'action, peut le déplacer vers un autre code. La CI exécuterait ce nouveau code sans que rien ne change dans ce dépôt.

Un SHA est une empreinte du contenu : il ne peut pas désigner un autre code. Ce qui a été vérifié est donc ce qui s'exécute, à chaque fois. C'est plus strict qu'un tag.

Le prix à payer : les mises à jour ne sont pas automatiques, et il faut changer le SHA avec son commentaire.

## Le job `quality`

Il vérifie le code directement sur un runner Ubuntu 24.04, sans Docker.

```yaml
quality:
  runs-on: ubuntu-24.04
  timeout-minutes: 10
```

`timeout-minutes: 10` arrête le job au bout de 10 minutes. Sans cette ligne, GitHub attend jusqu'à 6 heures : une étape bloquée consommerait du temps de calcul pour rien. Les deux jobs ont cette limite.

| Étape | Ce qu'elle fait | Si elle échoue |
|---|---|---|
| `actions/checkout` | Récupère le code du dépôt | Problème de GitHub ou d'accès |
| Installer PHP | Installe PHP 8.5, l'extension `intl` et Composer | Version ou extension indisponible |
| Repérer le cache Composer | Trouve le dossier de cache de Composer | Composer n'est pas utilisable |
| Restaurer le cache Composer | Récupère les paquets déjà téléchargés | N'échoue pas en cas d'absence de cache |
| Valider composer.json | Lance `composer validate --strict` | `composer.json` invalide, ou `composer.lock` pas à jour |
| Installer les dépendances | Lance `composer install` | Paquet introuvable ou version de PHP trop ancienne |
| Linter la configuration YAML | Lance `lint:yaml config` | Erreur de syntaxe YAML dans `config/` |
| Linter les templates Twig | Lance `lint:twig templates` | Erreur de syntaxe dans un template |
| Linter le conteneur | Lance `lint:container` | Une valeur injectée dans un service n'a pas le bon type |
| Lancer les tests | Lance `php bin/phpunit` | Un test échoue |
| Auditer les dépendances | Lance `composer audit` | Une faille connue touche un paquet installé |

Un **linter** vérifie la forme d'un fichier sans l'exécuter. Il est rapide et donne l'endroit exact de l'erreur.

### Le détail du cache

Télécharger les paquets à chaque exécution serait lent. Trois morceaux coopèrent :

```yaml
- name: Repérer le cache Composer
  id: composer-cache
  run: echo "dir=$(composer config cache-files-dir)" >> "$GITHUB_OUTPUT"
```

La commande demande à Composer où est son cache, et l'écrit dans `$GITHUB_OUTPUT`, le fichier spécial qui transmet une valeur aux étapes suivantes. L'`id` permet de la relire avec `steps.composer-cache.outputs.dir`.

```yaml
key: composer-${{ runner.os }}-${{ hashFiles('composer.lock') }}
restore-keys: composer-${{ runner.os }}-
```

La **clé** contient une empreinte de `composer.lock` : quand les dépendances changent, la clé change. `restore-keys` permet de partir d'un cache proche si la clé exacte n'existe pas.

### L'installation de PHP

`php-version: '8.5'` prend la dernière version disponible de cette branche. `extensions: intl` et `tools: composer` ajoutent ce qui manque au runner. `coverage: none` désactive l'outil de couverture de code (Xdebug ou PCOV), dont ce niveau n'a pas besoin : PHP démarre plus vite et les tests tournent sans surcoût. `composer install` échouerait avec une version de PHP inférieure à 8.5.12, le minimum fixé par [composer.json](../composer.json).

## Le job `docker`

Il vérifie que le site marche vraiment, dans l'image décrite par [Dockerfile](../Dockerfile) et [compose.yaml](../compose.yaml). Les deux jobs tournent en parallèle, sur des machines séparées.

| Étape | Ce qu'elle fait | Si elle échoue |
|---|---|---|
| `actions/checkout` | Récupère le code du dépôt | Problème de GitHub ou d'accès |
| Démarrer les conteneurs | Construit l'image et lance le conteneur | Erreur dans le `Dockerfile` ou dans `composer install` |
| Vérifier qu'une conversion répond | Appelle le vrai site et cherche `1 km = 1000 m` | Le serveur ne répond pas, ou le calcul est faux |
| Lancer les tests dans le conteneur | Lance PHPUnit dans le conteneur | Différence entre l'image et le runner |
| Arrêter les conteneurs | Supprime conteneurs et volumes | Sans effet sur le code |

Les explications de Docker se trouvent dans [docker.md](docker.md).

### La vérification d'une conversion

```bash
body=$(curl --retry 10 --retry-delay 2 --retry-all-errors --max-time 10 -fsS 'http://localhost:8080/?valeur=1&de=km&vers=m')
grep -q '1 km = 1000 m' <<<"$body"
```

Les guillemets simples autour de l'URL sont indispensables : sans eux, le shell lirait `&` comme la fin d'une commande. Le texte cherché est le même que celui de `testConvertsAndKeepsTheInput` : la page affiche `1 km = 1000 m` dans son élément `<output>`.

Cette requête traverse tout : le serveur, le routeur, `#[MapQueryParameter]`, le conteneur, le paramètre des unités, la variable de précision et Twig.

Les options de `curl` :

| Option | Rôle |
|---|---|
| `--retry 10` | Réessayer jusqu'à 10 fois |
| `--retry-delay 2` | Attendre 2 secondes entre deux essais |
| `--retry-all-errors` | Réessayer quelle que soit l'erreur |
| `--max-time 10` | Abandonner un essai qui dure plus de 10 secondes |
| `-f` | Échouer si le code HTTP indique une erreur, un 422 par exemple |
| `-s` | Masquer la barre de progression |
| `-S` | Garder quand même les messages d'erreur |

`--max-time 10` limite chaque essai : sans lui, un serveur qui accepte la connexion sans répondre ferait attendre `curl` indéfiniment. Un essai trop long est alors abandonné, puis réessayé.

`--retry-all-errors` est nécessaire parce que le serveur peut renvoyer une réponse vide au démarrage. [docker.md](docker.md#pourquoi-la-page-peut-répondre-vide-au-démarrage) explique pourquoi.

### Pourquoi `body=$(curl ...)` et pas `curl ... | grep`

La forme la plus courante serait `curl ... | grep -q '1 km = 1000 m'`. Elle pose deux problèmes.

1. **Le statut d'un pipeline est celui de la dernière commande.** GitHub lance ces lignes avec `bash -e`, sans l'option `pipefail`. Un échec de `curl` ne ferait pas échouer l'étape par lui-même : seul le résultat de `grep` compterait, et le vrai message d'erreur serait noyé.
2. **`grep -q` s'arrête au premier résultat trouvé** et ferme le tuyau. Si `curl` est encore en train d'écrire, il échoue. Avec `pipefail`, un site qui fonctionne donnerait donc parfois un échec.

La forme choisie sépare les deux temps :

- `body=$(...)` attend la réponse complète et la range dans une variable. Le statut de cette ligne est celui de `curl` : s'il échoue, `bash -e` arrête l'étape sur l'erreur réelle.
- `<<<"$body"` donne le contenu de la variable à `grep` en entrée. Les guillemets gardent le texte intact.

### Les dernières étapes

```bash
docker compose exec -T app php bin/phpunit
```

`exec` lance une commande dans le conteneur démarré. `-T` désactive le terminal interactif, qui n'existe pas en CI. Ces tests ont besoin de PHPUnit : voilà une raison de plus d'avoir les dépendances de développement dans cette image.

```yaml
- name: Arrêter les conteneurs
  if: always()
  run: docker compose down -v
```

Après une étape en échec, les suivantes sont normalement ignorées. `if: always()` fait exécuter celle-ci quoi qu'il arrive, pour que le nettoyage ait lieu.

## Lire un échec

1. Sur GitHub, ouvrez l'onglet **Actions**, ou le lien **Details** de la pull request.
2. Ouvrez le job en rouge, `quality` ou `docker`.
3. Dépliez l'étape en rouge : c'est la première qui a échoué. Les suivantes sont ignorées ou sans intérêt.
4. Lisez la fin de son journal, puis remontez jusqu'à la première erreur.
5. Reproduisez la commande sur votre machine.

| Étape en échec | Commande à relancer en local |
|---|---|
| Valider composer.json | `composer validate --strict` |
| Linter la configuration YAML | `php bin/console lint:yaml config` |
| Linter les templates Twig | `php bin/console lint:twig templates` |
| Linter le conteneur | `php bin/console lint:container` |
| Lancer les tests | `php bin/phpunit` |
| Auditer les dépendances | `composer audit` |
| Démarrer les conteneurs | `docker compose up -d --build` |
| Vérifier qu'une conversion répond | `curl -i 'http://localhost:8080/?valeur=1&de=km&vers=m'` |
| Lancer les tests dans le conteneur | `docker compose exec app php bin/phpunit` |

Si `Lancer les tests` passe mais que le test du conteneur échoue, ou l'inverse, la différence vient de l'environnement. Pensez au volume `/app/vendor` périmé.

## Ce qui viendra plus tard

Ce niveau vérifie le code, sans rien publier. Une base de données en CI et la couverture de code arrivent au palier Junior, et la publication d'une image au palier Junior++.
