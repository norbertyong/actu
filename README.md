# Actu Cyber & IT

Application PHP de veille sur la cybersécurité, l’informatique et Linux. Elle agrège des flux RSS/Atom, déduplique les articles et les affiche du plus récent au plus ancien, avec recherche, filtres par source et catégorie, et pagination.

## Prérequis

- PHP 8.2 ou supérieur recommandé, avec les extensions `curl`, `SimpleXML`, `mbstring` et `session`.
- Un répertoire `cache/` accessible en écriture par PHP.
- Un accès HTTP/HTTPS aux sources configurées dans le code ; l’apparence utilise Tailwind via `https://cdn.tailwindcss.com`.

Aucune installation Composer, compilation JavaScript ou base de données n’est nécessaire.

## Démarrage local

Depuis la racine du dépôt :

```sh
php -v
php -r 'foreach (["curl", "SimpleXML", "mbstring", "session"] as $e) { if (!extension_loaded($e)) { fwrite(STDERR, "Extension manquante : $e\n"); exit(1); } }'
mkdir -p cache
php -S 127.0.0.1:8000 -t .
```

Ouvrez ensuite `http://127.0.0.1:8000` dans votre navigateur. Le serveur intégré PHP est destiné au développement local. La première collecte peut être lente : les sources sont interrogées séquentiellement, avec un délai maximal de 15 secondes par flux.

### Connexion administrateur

Avant de démarrer le serveur, définissez `ACTU_ADMIN_USER` et `ACTU_ADMIN_PASSWORD` dans son environnement. En Bash, vous pouvez les saisir sans inscrire le mot de passe dans l’historique :

```bash
read -r -p 'Utilisateur administrateur : ' ACTU_ADMIN_USER
read -r -s -p 'Mot de passe administrateur : ' ACTU_ADMIN_PASSWORD
printf '\n'
export ACTU_ADMIN_USER ACTU_ADMIN_PASSWORD
php -S 127.0.0.1:8000 -t .
```

La connexion est refusée si l’une de ces variables est vide ou absente. Aucun fichier `.env` n’est chargé automatiquement. Les pages administratives partagent la session PHP ; `logout.php` détruit cette session.

## Pages et fichiers

| Fichier | Rôle |
| --- | --- |
| `index.php` | Agrégation publique, recherche, filtres, pagination et affichage conditionnel des vulnérabilités. |
| `admin.php` | Connexion et liens vers les outils d’administration. |
| `analytics.php` | Historique des consultations de cette page après authentification, et lecture facultative d’un cache de vulnérabilités. |
| `vulprod.php` | Agrégation des flux de catégorie `vulprod`, après authentification. |
| `logout.php` | Déconnexion et redirection vers l’administration. |
| `cache/` | Cache des flux et journaux générés, exclus du suivi Git. |
| `RELEASE_NOTES.md` | Notes de la première importation et limites connues. |

## Configuration des flux

La liste utilisée par l’accueil est le tableau `$FEEDS` dans `index.php`. Les paramètres voisins définissent le nom du site, le cache (20 minutes), le fuseau `Europe/Paris` et la limite de 300 articles. La pagination affiche 30 articles par page.

`admin.php` et `vulprod.php` lisent séparément un fichier facultatif `feeds.php`, absent de l’archive. Pour alimenter la page des vulnérabilités, créez ce fichier à la racine, par exemple :

```php
<?php
$FEEDS = [
    'CERT-FR' => ['https://www.cert.ssi.gouv.fr/feed/', 'vulprod'],
];
```

Ce fichier n’est pas lu par `index.php` : il ne modifie donc pas les sources de l’accueil. Le panneau d’administration affiche les flux et des instructions d’édition ; il ne fournit pas d’éditeur qui enregistre les changements.

## Limites connues

- L’accueil lit éventuellement `vulprod.log` (tableau JSON avec les champs `produit`, `cve`, `details`, `date`). Analytics lit séparément `cache/vul_prod.json` (champs `title`, `summary`, `date`, avec une date Unix). Aucun des scripts importés ne produit ces deux fichiers : ces sections restent vides sans alimentation externe.
- Analytics journalise les consultations authentifiées de sa propre page, avec adresse IP, navigateur et date. Il ne mesure pas toutes les visites du site et ne réalise pas de géolocalisation.
- Certaines sources sont en HTTP ou peuvent être indisponibles. Un cache existant est utilisé en repli si la récupération échoue.
- Il n’existe pas encore de suite de tests automatisés ni de configuration de déploiement.

## Vérifications

Avec PHP installé, vérifiez la syntaxe :

```sh
for file in index.php admin.php analytics.php vulprod.php logout.php; do
    php -l "$file" || exit 1
done
```

Après démarrage, vérifiez l’affichage des articles, la recherche et les filtres ; puis la connexion, le refus des identifiants invalides et la déconnexion. Sans configuration `feeds.php`, la page des vulnérabilités n’affichera aucun article.

## Données importées et déploiement

Les fichiers actifs proviennent de `actu.zip`. Les caches XML, journaux (`*.log`, `error_log`) et anciennes copies `index.old` / `index.good` sont conservés localement mais ignorés par Git. Un nouveau clone recréera son cache lors des requêtes.

Les identifiants présents dans les trois scripts administratifs de l’archive ont été remplacés par les variables d’environnement ci-dessus. Renouvelez les anciens identifiants s’ils ont servi sur un site accessible. Les anciennes copies locales peuvent encore contenir des informations sensibles.

Avant un déploiement public, utilisez HTTPS et un serveur PHP adapté, retirez les sauvegardes de la racine web et interdisez l’accès HTTP aux caches, journaux et fichiers privés. `.gitignore` ne protège pas ces fichiers contre un accès web. Prévoyez aussi une politique de conservation pour les adresses IP journalisées.
