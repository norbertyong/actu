# Actu Cyber & IT

**Version 0.2.0 — refonte complète.** Agrégateur PHP de veille cybersécurité, informatique et Linux, avec historique SQLite, favoris, recherches enregistrées, gestion des sources et alertes. Voir les [notes de version](RELEASE_NOTES.md).

## Démarrage

Prérequis : PHP **8.2+**, `pdo_sqlite`, `curl`, `SimpleXML`, `mbstring` et `session`. Aucun Composer, npm ou serveur de base de données n’est nécessaire pour faire fonctionner le site.

```sh
php bin/init.php
php -S 127.0.0.1:8000 -t public public/router.php
```

Ouvrez `http://127.0.0.1:8000`. Une installation neuve affiche un état vide jusqu’à la première collecte. Dans un autre terminal :

```sh
php bin/collect.php
```

Le collecteur peut durer plusieurs minutes si des sources ne répondent pas. Les visites ne déclenchent aucun téléchargement. Les sources défaillantes restent signalées dans **Sources**, sans perte d’historique.

### Docker

```sh
docker build -t actu:dev .
docker volume create actu-data
docker run --rm -p 127.0.0.1:8000:8000 \
  --mount type=volume,src=actu-data,dst=/app/storage \
  --name actu actu:dev
# Dans un autre terminal :
docker exec actu php bin/collect.php
```

Pour administrer le site, transmettez aussi vos variables via `--env-file` depuis un fichier privé hors de la racine publique. L’image tourne sous l’utilisateur non privilégié `www-data` et ne contient aucune identité administrateur par défaut. Le volume nommé conserve SQLite après arrêt du conteneur. Un montage de dossier hôte exige des droits d’écriture compatibles avec cet utilisateur. En environnement avec interception TLS, utilisez le secret BuildKit `proxy_ca` pour la construction, puis montez le bundle CA complet en lecture seule et définissez `CURL_CA_BUNDLE` pour le runtime. Ne désactivez pas TLS.

### Services web et collecteur avec Compose

```sh
docker compose up --build -d
```

`compose.yml` lance le serveur web et un collecteur qui se répète toutes les 20 minutes, avec un volume SQLite partagé. Cette variante remplace le cron ; n’installez pas les deux. La commande `docker compose down` arrête les processus ; le volume persiste tant que vous n’ajoutez pas `--volumes`. Les variables administrateur sont lues depuis l’environnement de Compose. Le serveur intégré reste un serveur de développement : pour une exposition publique, utilisez PHP-FPM et un reverse proxy HTTPS adaptés.

Dans le cloud avec proxy, préparez l’image via la commande `docker build --secret` décrite plus haut et démarrez avec `--no-build` ; transmettez aussi les variables proxy et le montage du bundle CA au collecteur dans un fichier Compose d’override privé. Ne copiez pas les identifiants du proxy dans le dépôt.

## Connexion administrateur

Définissez `ACTU_ADMIN_USER` et, de préférence, `ACTU_ADMIN_PASSWORD_HASH` dans l’environnement du processus PHP. Pour générer une empreinte sans mettre le mot de passe dans l’historique Bash :

```bash
read -r -s -p 'Mot de passe (12 caractères minimum) : ' ACTU_NEW_PASSWORD
printf '\n'
printf '%s' "$ACTU_NEW_PASSWORD" | php bin/password.php
unset ACTU_NEW_PASSWORD
```

Enregistrez l’empreinte produite dans votre gestionnaire de secrets ou configuration de service. `ACTU_ADMIN_PASSWORD` reste accepté pour la migration ; si une empreinte est configurée, elle est prioritaire. Aucun `.env` n’est chargé automatiquement ; `.env.example` décrit les variables disponibles.

L’accès se fait par **Administration**. Après cinq échecs depuis la même adresse réseau, les tentatives sont bloquées pour le reste d’une fenêtre de 15 minutes. La session est renouvelée à la connexion et expire après 30 minutes d’inactivité. La déconnexion et toutes les modifications nécessitent POST et un jeton CSRF.

## Navigation

| Menu | Fonctionnement |
| --- | --- |
| **Actualités** | Historique des articles, tri du plus récent au plus ancien. |
| **Vulnérabilités** | Articles de catégorie `vulprod` ou contenant une CVE ; filtre exploitation connue via CISA KEV. |
| **Favoris** | Articles enregistrés sur ce navigateur. |
| **Sources** | État, dernier succès de collecte et nombre d’articles conservés par source. |
| **Administration** | Tableau de bord, gestion des sources, alertes, statistiques et déconnexion. |
| **Thème** | Alterne automatique, clair et sombre ; choix mémorisé localement. |
| **Versions** / **Confidentialité** | Notes de livraison et gestion des données personnelles, en pied de page. |

### Recherche, lecture et partage

- Rechercher dans les titres et résumés ; combiner catégorie, source et période (24 h, 7 jours, 30 jours).
- Parcourir tout l’historique, par pages de 24 articles. Les pages hors limites sont ramenées à une page valide.
- Utiliser **Réinitialiser** pour vider les filtres.
- Marquer un article lu/non lu et afficher uniquement les non lus.
- Ajouter ou retirer un favori ; enregistrer jusqu’à 30 recherches nommées, puis les rouvrir ou les supprimer.
- Exporter les résultats en CSV ou RSS (1000 articles maximum par export). Le CSV neutralise les préfixes de formule.
- Ouvrir le titre pour lire l’article complet chez son éditeur, dans un nouvel onglet.

Les favoris, états de lecture et recherches sont associés à un cookie aléatoire propre au navigateur, sans compte lecteur. Ils ne sont pas synchronisés entre appareils. Les flux exportés dont le filtre dépend de ces préférences nécessitent ce cookie ; utilisez un filtre public pour un abonnement dans un lecteur RSS externe. La page **Confidentialité** permet d’effacer les données de lecture.

## Comment fonctionne la collecte

1. `bin/collect.php` prend un verrou exclusif et charge les sources actives depuis SQLite.
2. Il ignore les sources vérifiées depuis moins de 20 minutes (sauf `--force`).
3. Il utilise les en-têtes HTTP ETag / Last-Modified pour éviter les téléchargements inutiles.
4. Il lit RSS, Atom ou le catalogue JSON CISA KEV, nettoie les résumés et refuse les liens qui ne sont pas HTTP/HTTPS.
5. Il stocke les articles, dédupliqués par URL sans fragment, extrait les identifiants CVE et met à jour le diagnostic de la source.
6. Il génère les correspondances avec les règles d’alerte des sept derniers jours, sans doublon par règle et article.

La collecte est séquentielle et indépendante du serveur web. Les pages ne dépendent plus de la disponibilité immédiate des éditeurs. Les sources désactivées conservent leur historique. Les dates sont affichées dans le fuseau `Europe/Paris`.

```sh
php bin/collect.php --source=2 --force
php bin/collect.php --limit=3
```

Code de sortie : `0` si les sources traitées réussissent, `1` si au moins une échoue, `2` si une collecte tourne déjà. Une source ignorée car encore fraîche n’est pas un échec. Les pages publiques affichent les données existantes même lorsqu’un collecteur termine avec une erreur.

### Planification

Exemple cron à adapter aux chemins de votre serveur :

```cron
*/20 * * * * cd /srv/actu && /usr/bin/php bin/collect.php >> /var/log/actu-collect.log 2>&1
```

La planification cron n’est pas installée automatiquement ; Compose fournit une autre option explicite. Le service et le cron doivent partager `ACTU_STORAGE`, les permissions et les variables nécessaires. Faites tourner le collecteur avec le même utilisateur que PHP ou des droits compatibles.

## Sources et administration

La base initiale comporte 13 sources, dont CERT-FR et CISA KEV. Elles sont définies dans `app/seeds.php` uniquement pour une première installation. Ensuite, **Administration → Gestion des sources** est la référence commune à toutes les pages.

Fonctions disponibles : ajout, modification, activation/désactivation et test sans import des articles. Les URL doivent être publiques, sur les ports 80/443, sans identifiants intégrés. Chaque redirection est vérifiée ; un retour de HTTPS vers HTTP est refusé. Les réponses sont limitées à 8 Mo. Les adresses réseau privées/réservées et les déclarations XML externes sont refusées.

L’import OPML accepte 1 Mo et 100 sources au maximum, ignore les URL déjà présentes et désactive les nouvelles sources jusqu’à validation. L’export OPML contient les sources RSS/Atom ; le catalogue KEV est un format distinct.

### Proxy réseau

Le collecteur respecte les variables proxy standard. Avec un proxy qui réalise seul la résolution DNS, `ACTU_PROXY_HOSTS` peut contenir une liste de noms de domaines de confiance séparés par des virgules. Seuls ces noms exacts peuvent être résolus par le proxy si le DNS local est indisponible. N’ajoutez que des domaines d’éditeurs connus : le proxy doit lui-même interdire l’accès aux réseaux privés et résoudre les destinations de façon sûre. Sans cette option, un échec DNS bloque la source.

En cloud à accès restreint, autorisez les domaines des sources **et de leurs redirections** dans les paramètres réseau. Les domaines initiaux sont ceux de `app/seeds.php`, avec `services.nvd.nist.gov` pour l’enrichissement facultatif. Enregistrer la configuration ne l’applique pas automatiquement à l’instance courante.

## Vulnérabilités, CVSS et alertes

Les CVE sont extraites des titres et résumés. Le catalogue **CISA KEV** renseigne les produits, l’exploitation connue, l’action recommandée et l’échéance CISA dans le résumé et les métadonnées. Une mention d’article n’implique pas une exploitation avérée. L’échéance CISA n’est pas une échéance réglementaire universelle pour tous les lecteurs.

Le score CVSS est absent tant qu’une source ne l’a pas fourni ; il n’est jamais inventé. Pour enrichir quelques CVE via l’API publique NVD :

```sh
php bin/enrich.php --limit=5
```

Cette commande cible les CVE sans score, espace les requêtes de sept secondes et accepte jusqu’à 20 CVE par exécution. Les scores disponibles sont affichés sur les cartes ; la page Vulnérabilités permet de classer les articles par score CVSS décroissant, les scores inconnus venant après les scores renseignés. Les erreurs et limitations de l’API sont signalées ; il n’existe pas de garantie de complétude des CVE ou des scores.

### Règles et notifications

Dans **Administration → Alertes**, créez jusqu’à 50 règles avec un nom, une expression et une catégorie facultative. Une règle peut être suspendue ou supprimée. Les 100 dernières notifications sont consultables dans le panneau ; une création de règle examine les articles ingérés au cours des sept derniers jours.

Sans configuration externe, les notifications restent sur le site. Les envois passent par une commande séparée :

```sh
php bin/notify.php          # aperçu, aucun envoi
php bin/notify.php --send   # envoyer vers les canaux configurés
```

| Variable | Usage |
| --- | --- |
| `ACTU_ALERT_WEBHOOK` | Destination HTTPS publique recevant un JSON `id`, `rule`, `title`, `url`. Redirections refusées. |
| `ACTU_ALERT_EMAIL` | Adresse de réception des emails. |
| `ACTU_MAIL_FROM` | Adresse d’expédition requise pour l’email. |

L’email nécessite un transport `mail()` réellement configuré sur le serveur ; l’image Docker n’inclut pas de serveur SMTP. Le webhook doit accepter le format JSON générique fourni. Aucun message externe n’est envoyé lors de la navigation ou de la collecte seule. Une exécution traite au maximum 100 notifications et mémorise les succès par canal. Un crash entre l’envoi et l’enregistrement peut provoquer une répétition ; le webhook reçoit une clé `Idempotency-Key` pour permettre la déduplication côté destinataire.

## Déploiement et migration depuis 0.1.0

**Changement incompatible : le DocumentRoot doit pointer sur `public/`.** Ne servez plus la racine du dépôt. Le routeur fourni est destiné au serveur de développement ; en production, configurez Apache/PHP-FPM ou Nginx/PHP-FPM avec la racine publique et l’exécution des seuls scripts PHP existants.

1. Sauvegarder l’ancienne installation et ses données privées hors de la racine web.
2. Installer les extensions PHP, notamment SQLite.
3. Configurer le stockage et les identifiants dans l’environnement du serveur.
4. Exécuter `php bin/init.php` puis `php bin/collect.php`.
5. Changer le DocumentRoot vers `public/`, activer HTTPS et installer le cron.
6. Vérifier connexion, collecte, favoris, filtres et diagnostics des sources.

Les anciens caches XML, journaux visiteurs et `feeds.php` ne sont pas migrés automatiquement. Les données de l’archive restent localement ignorées par Git ; importez vos sources via OPML ou recréez-les dans l’administration. Le site ne lit plus `vulprod.log` ou `cache/vul_prod.json`.

| Variable | Valeur / rôle |
| --- | --- |
| `ACTU_STORAGE` | Dossier privé SQLite et verrous, par défaut `storage/` à la racine du dépôt. |
| `ACTU_ADMIN_USER` | Utilisateur administrateur, obligatoire pour se connecter. |
| `ACTU_ADMIN_PASSWORD_HASH` | Empreinte du mot de passe, recommandée. |
| `ACTU_ADMIN_PASSWORD` | Alternative de compatibilité ; ne pas versionner sa valeur. |
| `ACTU_HTTPS=1` | Seulement si un proxy de confiance termine HTTPS avant PHP ; active les cookies Secure et HSTS. |

Les données privées sont hors de `public/`. Le site utilise des requêtes SQL préparées, une politique CSP, des protections CSRF et des cookies HttpOnly/SameSite. Ne faites pas confiance à un en-tête HTTPS fourni directement par le client. Sauvegardez SQLite avec son API de backup ou arrêtez les écritures avant de copier la base et ses fichiers WAL ; une copie du seul fichier principal à chaud n’est pas une sauvegarde fiable.

### Statistiques et conservation

Les pages de veille incrémentent des compteurs journaliers agrégés, sans adresse IP ou identifiant lecteur. Conservation : 90 jours. Il ne s’agit pas de visiteurs uniques. Les articles sont conservés sans limite temporelle pour permettre la recherche historique ; surveillez l’espace disque. Les données de lecture sont supprimables par le lecteur depuis **Confidentialité**. Les journaux de l’hébergeur sont à gérer séparément.

## Validation

Résultats et limites de la validation locale : [docs/VALIDATION.md](docs/VALIDATION.md).

```sh
php tests/run.php
php tests/collector.php
for file in app/*.php public/*.php bin/*.php tests/*.php; do php -l "$file" || exit 1; done
```

Les tests PHP utilisent des fixtures RSS/Atom et une base SQLite temporaire : aucune alerte externe n’est envoyée. Ils couvrent parsing, sécurité des URL, déduplication, CVE/KEV/CVSS, filtres, favoris, OPML, règles, authentification et statistiques.

`tests/browser.cjs` couvre les parcours HTTP et l’affichage mobile avec Playwright et Chromium. Il attend un serveur de test isolé contenant les deux articles de `tests/fixtures/`, avec `ACTU_TEST_USER` / `ACTU_TEST_PASSWORD` ou les identifiants de test documentés dans le script. **Ne le lancez pas contre la production** : il modifie des favoris, une règle et l’état d’une source. Voir [tests/README.md](tests/README.md) pour sa préparation.

## Versionnement et releases

Convention : `vMAJEURE.MINEURE.CORRECTIF`. Une rupture augmente la majeure après 1.0 ; pendant la phase 0.x, une refonte incompatible augmente la mineure. Les corrections compatibles augmentent le correctif.

Avant livraison : mettre à jour `VERSION`, ce README et `RELEASE_NOTES.md`, exécuter les tests, intégrer sur `main`, puis créer un tag annoté sur le commit validé. Une release GitHub reprend les notes du tag dans [Releases](https://github.com/norbertyong/actu/releases). Un push ou une release ne déploie pas le site.

```sh
git fetch origin --tags
git switch --detach v0.2.0   # après publication de ce tag
```

Ne déplacez pas un tag publié. Le numéro courant est aussi affiché dans le pied de page du site. Cette refonte ne crée pas automatiquement de tag ni de release GitHub.
