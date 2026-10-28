# Actu Cyber & IT

Application PHP de veille sur la cybersécurité, l’informatique et Linux. Elle agrège des flux RSS/Atom, déduplique les articles et les affiche du plus récent au plus ancien, avec recherche, filtres par source et catégorie, et pagination.

## Fonctionnement du site

À chaque ouverture de l’accueil, PHP réalise les étapes suivantes :

1. Charge la liste des sources définie dans `index.php`.
2. Lit le cache XML de chaque source s’il date de moins de 20 minutes ; sinon, tente de télécharger son flux RSS/Atom. En cas d’échec, réutilise l’ancien cache disponible.
3. Extrait le titre, le lien, la date, l’auteur et un résumé de chaque article.
4. Déduplique les articles selon leur lien et leur titre, les trie par date décroissante et conserve les 300 premiers.
5. Applique la recherche et les filtres, puis affiche 30 résultats par page.

La recherche porte sur le titre et le résumé, sans distinction entre majuscules et minuscules. Les filtres sont combinés : une recherche, une catégorie et une source peuvent être utilisés ensemble. Ils s’appliquent aux 300 articles retenus, pas à l’ensemble des archives des sources.

Les articles complets restent sur les sites d’origine : cliquer sur un titre ouvre la source dans un nouvel onglet. Les cartes présentent la source, la catégorie, la date, le résumé et l’auteur lorsqu’il est disponible. Les dates des articles sont affichées dans le fuseau `Europe/Paris` ; l’horloge de l’accueil utilise l’heure locale du navigateur.

Le rafraîchissement des flux est déclenché par les requêtes web. Aucun cron, traitement en arrière-plan ou rafraîchissement automatique des articles n’est fourni. Rechargez la page pour relancer la collecte ; le cache limite les téléchargements répétés.

## Navigation et menus

### Accueil public

L’accueil est accessible sans connexion à `/` ou `/index.php`.

| Élément | Utilisation |
| --- | --- |
| **Rechercher…** | Saisir un mot ou une expression à chercher dans les titres et résumés. |
| **Toutes catégories** | Choisir `CYBER`, `IT` ou `LINUX`, selon les catégories des sources configurées. |
| **Toutes sources** | Restreindre les résultats à une source précise. |
| **Filtrer** | Appliquer les valeurs du formulaire. |
| **Numéros de page** | Parcourir les résultats en conservant les filtres actifs. |
| **Top vulnérabilités Produits** | Section affichée uniquement si le fichier facultatif `vulprod.log` fournit des données. |
| **Admin**, en pied de page | Ouvrir la connexion ou le panneau d’administration. |

Pour réinitialiser les filtres et revenir à la première page, ouvrez `/index.php` sans paramètres. Les paramètres d’URL sont `q` (recherche), `cat` (catégorie), `src` (source) et `page`. Exemple : `/index.php?q=linux&cat=linux&page=1`.

### Administration

Le lien **Admin** mène à `/admin.php`. Sans session administrateur, un formulaire demande un utilisateur et un mot de passe ; après connexion, le panneau propose :

| Menu ou section | Fonction |
| --- | --- |
| **Retour Accueil** / **Voir le site** | Revenir à l’accueil public. |
| **Analytics** | Ouvrir `/analytics.php` : horloge, 50 dernières consultations authentifiées de cette page et éventuelles vulnérabilités en cache. |
| **Vulnérabilités Produits** | Ouvrir `/vulprod.php` : articles des flux de catégorie `vulprod` déclarés dans `feeds.php`. |
| **Gestion rapide des flux** | Consulter la liste et les indications pour modifier `feeds.php` à la main. |
| **Fonctions avancées** | Lire les indications et pistes d’évolution ; les alertes, exports CSV et notifications cités ne sont pas implémentés. |

Les pages Analytics et Vulnérabilités Produits proposent un lien **Retour Admin**. La page des vulnérabilités possède aussi une recherche, une sélection de source, un bouton **Filtrer** et une pagination. Dans le code importé, cette sélection utilise les URL comme valeurs alors que le filtre compare les noms des sources : ce filtre présente donc une incohérence connue ; la recherche textuelle reste distincte.

Pour vous déconnecter, ouvrez `/logout.php` : la session est détruite, puis le navigateur revient à l’administration. Aucun bouton de déconnexion n’est actuellement affiché dans les menus.

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

## Versionnement et livraisons

Le code et la documentation sont suivis avec Git. `main` est la branche de référence ; les développements peuvent être préparés sur des branches dédiées et intégrés par pull request. Les caches et journaux sont propres à chaque installation et ne font pas partie d’une version.

### Convention de version

Les prochaines versions utiliseront des tags `vMAJEURE.MINEURE.CORRECTIF`, par exemple `v0.1.0` :

| Partie | Quand l’incrémenter | Exemple |
| --- | --- | --- |
| **MAJEURE** | Changement incompatible de configuration, de format de données ou de comportement pris en charge. | `v1.2.3` → `v2.0.0` |
| **MINEURE** | Ajout de fonctionnalités compatibles. | `v1.2.3` → `v1.3.0` |
| **CORRECTIF** | Correction compatible ou livraison de documentation seule. | `v1.2.3` → `v1.2.4` |

La série `0.x.y` correspond à la phase initiale de développement, pendant laquelle les interfaces peuvent encore évoluer. `v0.1.0` est le nom prévu pour la première livraison ; aucun tag ni release GitHub n’a encore été créé. Un commit poussé sur `main` n’est pas automatiquement une release ou un déploiement. La version installée peut être identifiée par `git rev-parse --short HEAD` ; `git tag --list` liste les versions balisées disponibles localement.

### Préparer une version

1. Vérifier le code, la syntaxe PHP et les parcours décrits dans **Vérifications**.
2. Compléter la section **Non publié** de [RELEASE_NOTES.md](RELEASE_NOTES.md) avec les ajouts, corrections, adaptations et limites connues.
3. Au moment de livrer, remplacer ce titre par le numéro retenu et la date réelle de publication, puis créer une nouvelle section **Non publié** pour les prochains changements.
4. Intégrer les changements validés sur `main`, créer un tag annoté sur le commit retenu et pousser ce tag. Exemple pour la première livraison, à exécuter uniquement lors de sa publication :

   ```sh
   git tag -a v0.1.0 -m "Actu Cyber & IT v0.1.0"
   git push origin v0.1.0
   ```

5. Créer une release GitHub associée au tag et y reprendre les notes correspondantes. Le déploiement sur le serveur reste une opération séparée.

Ne déplacez pas un tag déjà publié : corrigez le problème dans une nouvelle version. Le numéro de version n’est actuellement ni stocké dans un fichier `VERSION`, ni affiché dans l’interface ; le tag Git et les notes de version servent de références.
