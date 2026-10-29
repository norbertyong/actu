# Actu Cyber & IT

**Version 0.4.0 — veille personnalisée et résumés IA avec Groq.** Agrégateur PHP de veille cybersécurité, informatique et Linux, avec historique SQLite, favoris, recherches enregistrées, gestion des sources et alertes, tableau « À traiter », technologies suivies et dossiers CVE. Voir les [notes de version](RELEASE_NOTES.md).

**Sur cPanel :** utilisez l’[installation express](#installation-express--copier-ouvrir-configurer). Le [guide avancé](#installation-avancée-sur-cpanel-depuis-les-sources) reste disponible pour une installation manuelle depuis le code source.

## Choisir le bon téléchargement

| Votre situation | Archive et instructions |
| --- | --- |
| Première installation sur cPanel | [ZIP complet v0.4.0](https://github.com/norbertyong/actu/releases/download/v0.4.0/actu-cpanel-v0.4.0.zip), puis [installation express](#installation-express--copier-ouvrir-configurer). |
| Site déjà installé avec le paquet cPanel 0.3.x ou 0.4.x | [ZIP de mise à jour v0.4.0](https://github.com/norbertyong/actu/releases/download/v0.4.0/actu-cpanel-update-v0.4.0.zip), puis [procédure de mise à jour](#mettre-à-jour-un-site-cpanel-existant-03x--04x). Sauvegarder le site avant extraction. |
| Installation depuis les sources avec une racine `public/` | Code source de la [release v0.4.0](https://github.com/norbertyong/actu/releases/tag/v0.4.0), puis [guide avancé](#installation-avancée-sur-cpanel-depuis-les-sources). |

La release contient les deux ZIP cPanel et leurs sommes SHA256. Publier une release ou pousser sur GitHub ne met pas automatiquement à jour votre hébergement : appliquez l’archive adaptée à votre installation.

**Après la mise à jour :** ouvrez **À traiter → Mes technologies** pour personnaliser la veille, **Vulnérabilités → Regrouper par CVE** pour croiser les sources, et **Administration → Diagnostic** pour contrôler les collectes.

**Pour Groq :** ouvrez **Administration → Paramètres IA**, renseignez votre clé, choisissez le modèle et activez l’IA. Sur un article, cliquez sur **Résumé IA → Générer le résumé**. Consultez le [guide Groq](#activer-les-résumés-ia-avec-groq) pour les quotas, la confidentialité et le dépannage. L’IA reste désactivée tant que vous ne l’activez pas.

## Installation express : copier, ouvrir, configurer

**[Télécharger directement le ZIP prêt à installer](https://github.com/norbertyong/actu/releases/download/v0.4.0/actu-cpanel-v0.4.0.zip)** · [Somme SHA256](https://github.com/norbertyong/actu/releases/download/v0.4.0/actu-cpanel-v0.4.0.zip.sha256) · [Release v0.4.0](https://github.com/norbertyong/actu/releases/tag/v0.4.0)

Choisissez **`actu-cpanel-v0.4.0.zip`**, et non « Source code (zip) » : le paquet a déjà la bonne disposition pour cPanel.

1. Dans cPanel, choisissez PHP 8.2+ avec `pdo_sqlite`, `curl`, `SimpleXML`, `mbstring` et `session`.
2. Extrayez **tout le contenu** dans le répertoire du site (`public_html` pour un site dédié, ou `public_html/actu` pour un sous-dossier). Utilisez un dossier neuf ou sauvegardé. Gardez les fichiers cachés **`.htaccess` et `.actu/`** ; activez leur affichage dans le gestionnaire cPanel.
3. Ouvrez l’URL du dossier, par exemple `https://exemple.fr/actu/`. L’assistant s’affiche automatiquement.
4. Dans le gestionnaire cPanel, ouvrez `.actu/config/install.key`, créé sur votre serveur à la première visite. Copiez ce code dans l’assistant, puis choisissez votre utilisateur et votre mot de passe administrateur.
5. Connectez-vous à l’administration et cliquez sur **Récupérer les articles**. Gardez la page ouverte pendant le traitement des sources.

Après extraction dans un sous-dossier, vous devez obtenir directement :

```text
public_html/actu/
├── index.php
├── install.php
├── assets/
├── .htaccess
└── .actu/
    ├── app/
    ├── bin/
    ├── config/
    └── storage/
```

Ne créez pas un dossier `public/` supplémentaire pour ce paquet. Activez **Afficher les fichiers masqués** dans le gestionnaire cPanel avant de vérifier `.actu/`. Le fichier `install.key` apparaît **après la première ouverture du site** ; il est normal qu’il soit absent de l’archive téléchargée.

**Pas de terminal, de `.env`, de configuration manuelle PHP ou de base MySQL à créer.** L’assistant vérifie les prérequis, initialise SQLite, stocke une empreinte du mot de passe et se désactive après installation. Le code d’installation est généré sur l’hébergement ; il n’est jamais inclus dans l’archive publique. Aucun compte administrateur par défaut n’existe.

Ce paquet est destiné à **Apache/LiteSpeed avec `.htaccess` actif**, comme sur les offres cPanel compatibles. Les règles incluses interdisent tout accès HTTP à `.actu/`, qui contient configuration et données. Cette disposition n’est pas adaptée à Nginx seul sans règles équivalentes. HTTPS et les connexions sortantes vers les flux restent nécessaires. Le paquet ne peut pas installer une extension PHP absente de l’hébergement.

### Actualisation des articles

Vous pouvez conserver une collecte manuelle depuis l’administration. Pour l’actualisation sans administrateur connecté, ajoutez **une tâche cron cPanel toutes les 20 minutes**, en adaptant les deux chemins :

```sh
/opt/cpanel/ea-php83/root/usr/bin/php /home/CPUSER/public_html/actu/.actu/bin/collect.php >> /home/CPUSER/actu-collect.log 2>&1
```

Dans **Tâches cron**, mettez `*/20` dans **Minute**, puis `*` dans les quatre autres champs. Collez uniquement la commande ci-dessus dans **Commande**. Remplacez `CPUSER` par votre compte et faites confirmer le chemin PHP par votre hébergeur.

Pour un site installé directement dans `public_html`, retirez `/actu` du chemin. Le collecteur lit automatiquement la configuration créée par l’assistant. Les alertes email/webhook restent facultatives et désactivées tant que leurs destinations ne sont pas configurées.

**Mises à jour :** utilisez le [paquet de mise à jour dédié](#mettre-à-jour-un-site-cpanel-existant-03x--04x). Le paquet ci-dessus est une archive de première installation. Sauvegardez configuration et base ; ne remplacez pas `.actu/config/` ou `.actu/storage/` par les dossiers d’une archive neuve et ne réintroduisez pas `install.enabled`. Ne supprimez pas la configuration pour « réinstaller » un site existant. Le guide cPanel avancé ci-dessous concerne l’archive de code source et la disposition séparée `public/`.

### Si le démarrage bloque

| Problème | Action |
| --- | --- |
| Le site affiche une erreur 403 | Vérifier que tous les fichiers du paquet, notamment les deux niveaux de `.htaccess`, ont été extraits. |
| Une extension est signalée absente | L’activer dans le sélecteur PHP de cPanel ou demander son activation à l’hébergeur. |
| Le code d’installation est introuvable | Ouvrir le site une première fois, afficher les fichiers cachés et vérifier que `.actu/config/` est accessible en écriture par PHP. |
| L’accueil ne contient aucun article | Se connecter et cliquer sur **Récupérer les articles** ; consulter **Sources** si la récupération échoue. |
| L’assistant indique que l’installation est indisponible après configuration | C’est normal : il est verrouillé. Ouvrir `login.php` pour accéder à l’administration. |

### Construction du paquet pour les développeurs

Pour produire le paquet depuis les sources : `python3 bin/package.py`. Le fichier ZIP et son SHA256 sont créés dans `dist/`, sans caches, base locale, identifiants ou fichiers utilisateur.

## Mettre à jour un site cPanel existant (0.3.x → 0.4.x)

**[Télécharger le ZIP de mise à jour v0.4.0](https://github.com/norbertyong/actu/releases/download/v0.4.0/actu-cpanel-update-v0.4.0.zip)** · [SHA256](https://github.com/norbertyong/actu/releases/download/v0.4.0/actu-cpanel-update-v0.4.0.zip.sha256)

Ce ZIP convient à un site déjà installé avec le **paquet cPanel 0.3.x ou 0.4.x**, dont le code privé se trouve dans `.actu/`. Il ne contient ni configuration, ni stockage, ni marqueur d’activation de l’installateur.

1. Suspendez le cron et mettez le site en maintenance depuis l’hébergement. Attendez la fin des collectes/générations en cours.
2. Sauvegardez le dossier complet, en particulier `.actu/config/` et `.actu/storage/`. Pour une sauvegarde SQLite cohérente, arrêtez les écritures et conservez aussi les éventuels fichiers `-wal` et `-shm`.
3. Envoyez **`actu-cpanel-update-v0.4.0.zip`** dans le dossier du site et extrayez son contenu en remplaçant les fichiers du code. Activez l’affichage des fichiers cachés. **Ne supprimez pas les dossiers existants.**
4. Vérifiez que `.actu/config/local.php` et `.actu/storage/actu.sqlite` sont toujours présents. Leurs contenus sont conservés ; les nouvelles tables sont créées automatiquement au premier accès.
5. Rouvrez le site, connectez-vous et vérifiez **À traiter**, **Vulnérabilités → Regrouper par CVE** et **Administration → Diagnostic**. Réactivez le cron.
6. Configurez Groq ci-dessous si vous souhaitez activer l’IA.

Pour revenir en arrière, arrêtez les écritures puis restaurez ensemble le code, la configuration et le stockage sauvegardés. Pour une installation **depuis les sources** avec `public/`, mettez à jour le code source en conservant le stockage et la configuration privés ; n’appliquez pas le ZIP cPanel à cette disposition.

## Activer les résumés IA avec Groq

L’IA est facultative et désactivée par défaut. Elle fonctionne avec une clé de votre compte Groq et les connexions HTTPS sortantes vers **`api.groq.com`**. Aucun SDK ou service supplémentaire n’est à installer sur cPanel.

1. Ouvrez le site en **HTTPS** et connectez-vous à **Administration**.
2. Ouvrez **Paramètres IA**, puis collez votre clé dans **Nouvelle clé API Groq**. Ne la mettez pas dans Git, une URL ou une capture d’écran.
3. Renseignez un identifiant de modèle de conversation compatible avec le format JSON et disponible sur votre compte. La valeur proposée est `llama-3.3-70b-versatile` ; modifiez-la si sa disponibilité change chez Groq.
4. Ajustez les limites : par défaut **20 tentatives par jour** et **100 000 unités prudentes**. Cochez **Activer les résumés Groq**, puis enregistrez.
5. Dans **Actualités** ou **À traiter**, ouvrez **Résumé IA** sur un article, puis cliquez sur **Générer le résumé**. Cette première génération vérifie aussi la connexion au fournisseur.
6. Consultez le résumé français, les produits et actions mentionnés, les informations manquantes et le lien vers la source originale.

### Données, cache et consommation

- Les demandes passent uniquement par le serveur PHP vers l’API de conversation Groq, sans redirection HTTP ni outils exécutables. La clé est conservée dans `ACTU_STORAGE/groq.json` (par défaut `.actu/storage/groq.json` pour le paquet cPanel), avec permissions `0600`. Elle n’est jamais réaffichée dans les formulaires, ajoutée au prompt ou incluse dans les archives de livraison. Le fichier est privé mais **non chiffré** : protégez également les sauvegardes et l’accès à l’hébergement.
- Une variable serveur `ACTU_GROQ_API_KEY`, si renseignée, est prioritaire et n’est jamais recopiée dans ce fichier. Supprimer la clé locale ne supprime pas une variable serveur.
- Seuls le **titre (400 caractères maximum) et l’extrait RSS (6 000 maximum)** sont transmis à Groq à la demande d’un administrateur. Aucun téléchargement de l’article complet, envoi des technologies suivies ou traitement IA automatique pendant la collecte.
- Le résultat est consultable par les administrateurs et mis en cache localement. À texte et modèle identiques, rouvrir le résumé ne déclenche aucun appel. Changer le texte ou le modèle demande une nouvelle génération. **Supprimer les résumés IA** efface le cache ; cela ne rembourse pas les requêtes déjà envoyées.
- Les quotas sont communs à l’installation, réservés avant chaque appel et remis à zéro à **minuit UTC**. Les échecs comptent aussi ; aucune relance automatique. Le budget prudent utilise la taille en octets de la requête et une marge pour la sortie : ce n’est ni la consommation exacte en tokens, ni une limite monétaire. Consultez les tarifs, quotas et factures de votre compte Groq.
- Les résumés peuvent contenir des erreurs et se fondent sur un extrait. Le site ne remplace pas les avis des éditeurs et ne transforme pas une suggestion IA en commande exécutée.

| Message / problème | Vérification |
| --- | --- |
| Accès Groq refusé | Clé valide, autorisations du compte et éventuelle variable serveur prioritaire. |
| Modèle ou format refusé | Identifiant exact et prise en charge du mode JSON par le modèle choisi. |
| Groq injoignable | cURL, DNS, certificats et autorisation HTTPS sortante vers `api.groq.com` chez l’hébergeur. |
| Limite Groq ou quota quotidien atteint | Quota fournisseur ou limites locales dans Paramètres IA ; attendre ou ajuster selon votre budget. |
| Réponse incomplète / non structurée | Aucun faux résumé n’est sauvegardé ; vérifier le modèle puis réessayer manuellement. |

## Démarrage depuis les sources (développement)

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

## Installation avancée sur cPanel depuis les sources

Cette méthode manuelle reste disponible pour le code source v0.4.0, avec une racine web `public/` distincte. Pour copier l’archive prête à installer, suivez uniquement l’installation express ci-dessus.

Cette procédure installe **le code source sans Docker** sur un hébergement cPanel avec Apache/LiteSpeed et PHP-FPM/CGI. Les noms de menus peuvent varier selon l’hébergeur. Aucun MySQL, Node.js, Composer ou accès root n’est nécessaire.

Dans les exemples, remplacez partout :

| Exemple | Votre valeur |
| --- | --- |
| `CPUSER` | Votre identifiant de compte cPanel. |
| `/home/CPUSER` | Le répertoire personnel réel du compte, visible dans le gestionnaire de fichiers. Certains hébergeurs utilisent `/home2/…`. |
| `actu.exemple.fr` | Le domaine ou sous-domaine qui servira le site. |
| `/home/CPUSER/actu` | Le dossier de l’application, hors de `public_html`. |
| `/opt/cpanel/ea-php83/root/usr/bin/php` | Le binaire PHP CLI confirmé par votre hébergeur. Ce chemin est un exemple EasyApache PHP 8.3, pas une valeur universelle. |

### Étape 1 — Vérifier les fonctions de l’hébergement

Dans **Logiciel → MultiPHP Manager**, choisissez PHP **8.2 ou supérieur**, idéalement PHP 8.3, pour le domaine. Si votre hébergeur utilise **Select PHP Version / Sélectionner une version de PHP**, sélectionnez la version et activez les extensions dans cette interface.

Les extensions requises sont `pdo_sqlite`, `curl`, `SimpleXML`, `mbstring` et `session`. L’extension `PDO` doit aussi être disponible. Vérifiez les extensions pour le **site web et le binaire PHP utilisé par cron** : ils peuvent utiliser des configurations différentes. Sur une offre EasyApache, l’activation d’une extension absente peut nécessiter une intervention de l’hébergeur.

Vérifiez également la disponibilité de **Terminal ou SSH**, de **Tâches cron / Cron Jobs**, des connexions HTTPS sortantes et du DNS public. Si Terminal/SSH n’est pas proposé, utilisez le gestionnaire de fichiers pour l’envoi et demandez l’accès à un PHP CLI compatible pour l’initialisation et le cron. Une variante sans terminal est donnée à l’étape 8.

Ne créez pas de base MySQL dans cPanel : SQLite est initialisé automatiquement par l’application.

### Étape 2 — Télécharger et extraire la release

1. Ouvrez [la release v0.4.0](https://github.com/norbertyong/actu/releases/tag/v0.4.0), puis téléchargez **Source code (zip)** dans **Assets**.
2. Dans **Fichiers → Gestionnaire de fichiers**, ouvrez votre répertoire personnel `/home/CPUSER`, puis envoyez l’archive.
3. Extrayez-la. GitHub crée normalement un dossier `actu-0.4.0` ; renommez-le en `actu` si ce nom est libre. Ne remplacez pas une installation existante sans sauvegarde.
4. Dans les paramètres du gestionnaire, activez **Afficher les fichiers masqués / Show Hidden Files** et vérifiez la présence des fichiers `.htaccess` à la racine et dans `public/`.

L’arborescence attendue est :

```text
/home/CPUSER/
├── actu/
│   ├── app/
│   ├── bin/
│   ├── public/
│   │   ├── .htaccess
│   │   ├── index.php
│   │   └── assets/
│   ├── .htaccess
│   ├── VERSION
│   └── RELEASE_NOTES.md
└── public_html/          # éventuel autre site, à conserver
```

N’envoyez pas l’ancien `actu.zip` de sauvegarde : il contient des fichiers historiques et des journaux. Utilisez l’archive de la release GitHub. Les dépendances de test et Docker ne sont pas nécessaires sur cPanel.

### Étape 3 — Configurer le domaine vers `public/`

Dans **Domaines → Créer un nouveau domaine**, créez par exemple `actu.exemple.fr`. Si l’option existe, décochez le partage de la racine avec le domaine principal et réglez **Document Root / Racine du document** sur :

```text
/home/CPUSER/actu/public
```

Certaines interfaces demandent seulement `actu/public`, relatif au répertoire personnel. Pour un domaine existant, cherchez **Domaines → Gérer → Nouvelle racine du document**, si l’option est disponible. Configurez aussi l’enregistrement DNS du sous-domaine vers le serveur cPanel si le DNS est géré ailleurs.

**Point essentiel : le domaine doit servir le contenu de `public/`, jamais la racine `actu/`.** Ne déplacez pas uniquement `index.php` dans `public_html` : les chemins vers `app/` ne correspondraient plus. Ne supprimez pas le `.htaccess` qui protège la racine de l’application.

Le DocumentRoot du **domaine principal** n’est généralement pas modifiable depuis cPanel, et certaines offres imposent tous les domaines sous `public_html`. Dans ce cas, utilisez un sous-domaine dont la racine est configurable ou demandez à l’hébergeur de pointer le domaine vers `actu/public`. Si le panneau interdit un dossier hors de `public_html`, faites valider une disposition sûre par l’hébergeur ; ne rendez pas les dossiers `app/`, `bin/` ou `storage/` accessibles pour contourner la restriction. Cette procédure suppose cette racine publique correctement configurée.

Le paquet prêt à installer v0.4.0 prend également en charge les sous-dossiers. La procédure avancée ci-dessous conserve la disposition de développement avec une racine `public/` distincte.

### Étape 4 — Activer HTTPS

Dans **Sécurité → SSL/TLS Status / État SSL/TLS**, lancez **AutoSSL** si votre hébergeur le permet. Attendez que le certificat couvre le domaine, puis activez **Force HTTPS Redirect** dans la page **Domaines**.

Vérifiez que `https://actu.exemple.fr` présente un certificat valide avant de vous connecter à l’administration. Avec HTTPS reçu directement par PHP, la détection est automatique. N’ajoutez `ACTU_HTTPS=1` que si un proxy de confiance termine HTTPS et que PHP ne le détecte pas déjà.

### Étape 5 — Identifier le bon PHP CLI et générer le mot de passe

Dans **Avancé → Terminal**, ou en SSH, définissez le chemin confirmé à l’étape 1 :

```bash
ACTU_PHP=/opt/cpanel/ea-php83/root/usr/bin/php
"$ACTU_PHP" -v
"$ACTU_PHP" -r 'foreach (["pdo_sqlite", "curl", "SimpleXML", "mbstring", "session"] as $e) { if (!extension_loaded($e)) { fwrite(STDERR, "Extension manquante : $e\n"); exit(1); } } echo "Extensions CLI OK\n";'
```

Si le chemin n’existe pas, demandez celui correspondant à la version PHP du domaine. Ne supposez pas que `/usr/bin/php` utilise la même version.

Générez ensuite une empreinte de mot de passe, en Bash :

```bash
read -r -s -p 'Mot de passe administrateur (12 caractères minimum) : ' ACTU_NEW_PASSWORD
printf '\n'
printf '%s' "$ACTU_NEW_PASSWORD" | "$ACTU_PHP" /home/CPUSER/actu/bin/password.php
unset ACTU_NEW_PASSWORD
```

Conservez le mot de passe dans votre gestionnaire de mots de passe. Copiez **l’empreinte complète** affichée, généralement commençant par `$2y$`, pour l’étape suivante. Cette commande ne crée pas un compte en base : elle prépare la valeur utilisée par la configuration.

### Étape 6 — Créer la configuration et le stockage privés

Dans le gestionnaire de fichiers, créez `/home/CPUSER/actu-private`, puis son sous-dossier `storage`. Ces dossiers doivent être **hors de la racine web**. En terminal :

```sh
mkdir -p /home/CPUSER/actu-private/storage
chmod 700 /home/CPUSER/actu-private /home/CPUSER/actu-private/storage
```

Créez `/home/CPUSER/actu-private/settings.ini` avec ce contenu, en remplaçant les trois valeurs :

```ini
ACTU_STORAGE="/home/CPUSER/actu-private/storage"
ACTU_ADMIN_USER="votre-identifiant-admin"
ACTU_ADMIN_PASSWORD_HASH="COLLEZ_ICI_L_EMPREINTE_COMPLETE"
```

Créez ensuite `/home/CPUSER/actu-private/bootstrap.php`, en UTF-8 **sans BOM**, sans espace avant `<?php` et sans balise de fermeture :

```php
<?php
$config = parse_ini_file(__DIR__ . '/settings.ini', false, INI_SCANNER_RAW);
if ($config === false) {
    throw new RuntimeException('Configuration privée inaccessible.');
}
$allowed = [
    'ACTU_STORAGE', 'ACTU_ADMIN_USER', 'ACTU_ADMIN_PASSWORD_HASH',
    'ACTU_HTTPS', 'ACTU_GROQ_API_KEY', 'ACTU_ALERT_WEBHOOK', 'ACTU_ALERT_EMAIL', 'ACTU_MAIL_FROM',
];
foreach ($allowed as $name) {
    if (isset($config[$name]) && is_string($config[$name])) {
        putenv($name . '=' . $config[$name]);
    }
}
unset($config, $allowed, $name);
```

Appliquez les permissions `600` aux deux fichiers privés. Sous PHP-FPM exécuté avec votre utilisateur cPanel, les dossiers `700` et fichiers `600` suffisent. Si PHP tourne avec un utilisateur différent, demandez les droits de groupe adaptés à l’hébergeur ; n’utilisez pas `777`.

Le mot de passe en clair ne figure dans aucun de ces fichiers. Le fichier INI reste néanmoins privé, car son empreinte et les éventuelles destinations d’alerte sont sensibles. Ne le versionnez pas et ne le placez pas dans `public/`.

### Étape 7 — Faire charger la configuration par PHP-FPM

Créez `/home/CPUSER/actu/public/.user.ini` :

```ini
auto_prepend_file="/home/CPUSER/actu-private/bootstrap.php"
```

Cette directive demande à PHP de charger le bootstrap privé avant les scripts du site. Le `.htaccess` livré interdit l’accès web aux fichiers dont le nom commence par un point. Conservez `.user.ini` comme configuration locale lors des mises à jour ; ne l’ajoutez pas au dépôt.

Attendez jusqu’à environ **5 minutes**, selon `user_ini.cache_ttl`, ou demandez un rechargement du pool PHP-FPM à l’hébergeur. `.user.ini` fonctionne avec les handlers CGI/FastCGI qui l’autorisent ; ce n’est pas un mécanisme universel. Si votre offre ignore cette directive ou interdit `putenv`/`auto_prepend_file`, faites configurer ces variables ou cette initialisation par l’hébergeur pour le pool PHP du domaine. Ne remplacez pas cela par un script de configuration accessible publiquement.

**Exporter des variables dans le terminal ne les transmet pas à PHP-FPM.** Le MultiPHP INI Editor ne propose pas non plus nécessairement de champ pour des variables applicatives arbitraires. Un simple `.env` n’est pas lu par cette application.

### Étape 8 — Initialiser et lancer la première collecte

La CLI ne lit pas `.user.ini`. Réutilisez le bootstrap avec `-d auto_prepend_file=…` pour chaque commande :

```bash
ACTU_PHP=/opt/cpanel/ea-php83/root/usr/bin/php
"$ACTU_PHP" -d auto_prepend_file=/home/CPUSER/actu-private/bootstrap.php /home/CPUSER/actu/bin/init.php
"$ACTU_PHP" -d auto_prepend_file=/home/CPUSER/actu-private/bootstrap.php /home/CPUSER/actu/bin/collect.php
```

L’initialisation doit annoncer **13 sources configurées** sur une installation neuve. La collecte affiche ensuite le résultat par source. Un échec isolé ne détruit pas les articles déjà enregistrés : consultez **Sources** pour son diagnostic.

Sans Terminal/SSH, préparez l’empreinte sur votre ordinateur avec PHP ou faites-la générer via un accès CLI sécurisé fourni par l’hébergeur. Créez les fichiers des étapes 6 et 7 avec le gestionnaire. Le premier chargement du site initialise automatiquement la base ; configurez ensuite le cron de l’étape 10, attendez son passage et consultez son journal. Cette variante dépend toujours des extensions et du PHP CLI de l’hébergeur : elle ne remplace pas le collecteur par une requête web.

### Étape 9 — Vérifier le site et l’administration

1. Ouvrez `https://actu.exemple.fr` : l’interface et les styles doivent s’afficher.
2. Après une collecte réussie, vérifiez les articles, la recherche et les filtres.
3. Cliquez sur **Administration** et utilisez l’utilisateur de `settings.ini` et le mot de passe dont vous avez généré l’empreinte.
4. Dans **Gestion des sources**, vérifiez le dernier succès, testez une source, puis vérifiez un favori et la déconnexion.
5. Vérifiez que `/storage/actu.sqlite`, `/app/core.php`, `/.user.ini` et `/settings.ini` ne sont pas accessibles par HTTP : une réponse de refus ou « introuvable » est attendue. Aucun contenu privé ne doit être téléchargé.
6. Vérifiez avec le gestionnaire que la base réellement utilisée est `/home/CPUSER/actu-private/storage/actu.sqlite`. Si une seconde base apparaît dans `/home/CPUSER/actu/storage`, un des processus ne charge probablement pas la configuration privée : corrigez ce point avant de poursuivre.

### Étape 10 — Installer le cron de collecte

Dans **Avancé → Tâches cron / Cron Jobs → Ajouter une nouvelle tâche**, saisissez :

| Champ | Valeur |
| --- | --- |
| Minute | `*/20` |
| Heure | `*` |
| Jour du mois | `*` |
| Mois | `*` |
| Jour de la semaine | `*` |

Dans le champ **Commande**, collez cette ligne en remplaçant le chemin PHP et `CPUSER` :

```sh
/opt/cpanel/ea-php83/root/usr/bin/php -d auto_prepend_file=/home/CPUSER/actu-private/bootstrap.php /home/CPUSER/actu/bin/collect.php >> /home/CPUSER/actu-private/collect.log 2>&1
```

Les cinq champs définissent l’horaire : **ne recollez pas `*/20 * * * *` dans le champ Commande**. Le journal est privé ; ouvrez-le dans le gestionnaire après un passage du cron. Prévoyez sa rotation ou son archivage pour éviter une croissance indéfinie.

La commande dispose d’un verrou contre les exécutions simultanées. Si l’hébergeur impose une durée courte, essayez `--limit=2` à la fin des arguments PHP, avant `>>`, et adaptez la fréquence avec lui : seules deux sources seront traitées par passage, donc la fraîcheur de l’ensemble diminuera. Ne créez pas plusieurs collecteurs concurrents pour contourner les quotas.

### Étape 11 — Activer les options souhaitées

**CVSS NVD, facultatif :** lancez la commande suivante ou créez un cron séparé, par exemple quotidien :

```sh
/opt/cpanel/ea-php83/root/usr/bin/php -d auto_prepend_file=/home/CPUSER/actu-private/bootstrap.php /home/CPUSER/actu/bin/enrich.php --limit=5 >> /home/CPUSER/actu-private/enrich.log 2>&1
```

**Alertes, facultatif :** créez d’abord les règles dans **Administration → Alertes**. Pour les notifications internes, cela suffit une fois le collecteur planifié. Pour l’email, ajoutez à `settings.ini` :

```ini
ACTU_ALERT_EMAIL="votre-destinataire@exemple.fr"
ACTU_MAIL_FROM="actu@exemple.fr"
```

Utilisez une adresse d’expédition autorisée par l’hébergeur et vérifiez avec lui la disponibilité de `mail()`. Configurer une boîte dans cPanel ne configure pas automatiquement un client SMTP dans l’application ; cette version utilise le transport PHP `mail()`. Pour un webhook, ajoutez plutôt `ACTU_ALERT_WEBHOOK` avec une destination HTTPS acceptant le format JSON documenté plus bas.

Vérifiez l’aperçu sans envoi :

```sh
/opt/cpanel/ea-php83/root/usr/bin/php -d auto_prepend_file=/home/CPUSER/actu-private/bootstrap.php /home/CPUSER/actu/bin/notify.php
```

Ajoutez `--send` uniquement lorsque vous souhaitez effectivement envoyer les notifications. Vous pouvez ensuite planifier cette commande dans un cron distinct, par exemple aux minutes `10,30,50`. Aucun envoi externe n’est activé par défaut.

### Étape 12 — Sauvegarder et mettre à jour

- Sauvegardez le code, la configuration privée et SQLite. Pour une copie de fichiers SQLite cohérente, interrompez le collecteur et les écritures web avant la copie, ou utilisez un outil compatible avec l’API de sauvegarde SQLite. Une sauvegarde cPanel à chaud ne garantit pas à elle seule la cohérence de la base.
- Pour une mise à jour, lisez les notes de migration de la nouvelle release, sauvegardez, puis remplacez le code en conservant `/home/CPUSER/actu-private/` et `public/.user.ini`.
- Ne réinstallez pas une base vide à la place de la base existante. Vérifiez ensuite le pied de page, la connexion, les sources et le journal cron.
- L’application n’exige pas de Docker ni de commande `php -S` sur cet hébergement : cPanel sert les pages via son serveur web.

### Dépannage cPanel

| Symptôme | Vérification / correction |
| --- | --- |
| Erreur 403 sur l’accueil | Vérifier que le domaine pointe sur `actu/public`, pas `actu`. Conserver les deux `.htaccess`. Vérifier les règles de l’hébergeur si la racine est correcte. |
| Erreur 500 Apache/LiteSpeed | Lire **Métriques → Erreurs** et le journal du domaine. Certains hébergeurs interdisent `Options -Indexes` ; ne retirer cette seule directive que si le journal signale précisément ce problème, et faire désactiver l’indexation par l’hébergeur. Garder les règles de contrôle d’accès. |
| « Service temporairement indisponible » | Vérifier version/extensions PHP, droits du dossier privé, limites `open_basedir` et accès à SQLite. Le message public masque volontairement le détail des exceptions. |
| Commande `php` introuvable ou version différente | Utiliser le chemin absolu du bon binaire, confirmé par l’hébergeur, y compris dans chaque cron. |
| `could not find driver` | Activer `pdo_sqlite` dans le PHP concerné : web et/ou CLI. |
| Connexion administrateur refusée | Vérifier l’utilisateur, l’empreinte complète, le chargement de `.user.ini` et les éventuelles restrictions sur `putenv`. Après cinq échecs, attendre la fin de la fenêtre de 15 minutes. |
| Site et cron ne voient pas les mêmes articles | Charger le même bootstrap privé des deux côtés et utiliser le même `ACTU_STORAGE`. La CLI ignore `.user.ini`. |
| Accueil vide | Attendre/lancer une collecte, lire `collect.log` et **Sources** ; une visite seule ne récupère aucun flux. |
| Échecs réseau ou certificats | Faire vérifier DNS, cURL sortant HTTPS, pare-feu et certificats CA par l’hébergeur ; ne désactiver ni TLS ni les protections d’URL. |
| Modifications `.user.ini` sans effet | Attendre le délai de cache, vérifier le handler PHP du domaine et demander un rechargement/configuration du pool si nécessaire. |
| Erreur de session ou formulaire expiré | Vérifier `session.save_path`, l’accès en écriture des sessions, les cookies et HTTPS ; ne forcer `ACTU_HTTPS=1` que pour un vrai accès HTTPS. |

Ces instructions sont adaptées au code v0.4.0. L’installation cPanel réelle dépend des fonctions et limites de votre hébergeur ; elle n’a pas été exécutée sur votre compte depuis cet environnement.

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
| **À traiter** | Articles non lus des 7 derniers jours, priorité KEV ou CVSS ≥ 9 ; sélection des technologies suivies. |
| **Mes technologies** (depuis À traiter) | Jusqu’à 30 produits ou expressions propres au navigateur. |
| **Dossiers CVE** (depuis Vulnérabilités) | Publications regroupées par identifiant CVE, nombre de sources et accès aux originaux. |
| **Actualités** | Historique des articles, tri du plus récent au plus ancien. |
| **Vulnérabilités** | Articles de catégorie `vulprod` ou contenant une CVE ; filtre exploitation connue via CISA KEV. |
| **Favoris** | Articles enregistrés sur ce navigateur. |
| **Sources** | État, dernier succès de collecte et nombre d’articles conservés par source. |
| **Administration** | Tableau de bord, gestion des sources, alertes, statistiques, **Diagnostic**, **Paramètres IA** et déconnexion. |
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

Les favoris, états de lecture, technologies suivies et recherches sont associés à un cookie aléatoire propre au navigateur, sans compte lecteur. Ils ne sont pas synchronisés entre appareils. Les flux exportés dont le filtre dépend de ces préférences nécessitent ce cookie ; utilisez un filtre public pour un abonnement dans un lecteur RSS externe. La page **Confidentialité** permet d’effacer les données de lecture.

## Comment fonctionne la collecte

Le menu **Administration → Diagnostic** distingue les sources en erreur, les collectes sans succès depuis une heure et les flux sans publication depuis sept jours. Une source peu active peut être silencieuse sans être défaillante. Le dernier début/fin de collecteur CLI et la dernière demande web sont affichés séparément : un passage CLI récent indique une exécution, sans prouver à lui seul que le cron cPanel est configuré.

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

**Installation depuis les sources : le DocumentRoot doit pointer sur `public/`.** Le paquet cPanel prêt à installer dispose de sa propre disposition protégée `.actu/` et se copie directement dans le répertoire web, comme expliqué au début de ce README. Ne servez plus la racine du dépôt. Le routeur fourni est destiné au serveur de développement ; en production, configurez Apache/PHP-FPM ou Nginx/PHP-FPM avec la racine publique et l’exécution des seuls scripts PHP existants.

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
| `ACTU_GROQ_API_KEY` | Clé Groq facultative côté serveur, prioritaire sur la clé saisie dans l’administration ; activer l’IA dans Paramètres IA reste nécessaire. |
| `ACTU_HTTPS=1` | Seulement si un proxy de confiance termine HTTPS avant PHP ; active les cookies Secure et HSTS. |

Les données privées sont hors de `public/`. Le site utilise des requêtes SQL préparées, une politique CSP, des protections CSRF et des cookies HttpOnly/SameSite. Ne faites pas confiance à un en-tête HTTPS fourni directement par le client. Sauvegardez SQLite avec son API de backup ou arrêtez les écritures avant de copier la base et ses fichiers WAL ; une copie du seul fichier principal à chaud n’est pas une sauvegarde fiable.

### Statistiques et conservation

Les pages de veille incrémentent des compteurs journaliers agrégés, sans adresse IP ou identifiant lecteur. Conservation : 90 jours. Il ne s’agit pas de visiteurs uniques. Les articles sont conservés sans limite temporelle pour permettre la recherche historique ; surveillez l’espace disque. Les données de lecture sont supprimables par le lecteur depuis **Confidentialité**. Les journaux de l’hébergeur sont à gérer séparément.

## Validation

Résultats et limites de la validation locale : [docs/VALIDATION.md](docs/VALIDATION.md).

```sh
php tests/run.php
php tests/collector.php
php tests/evolution.php
for file in app/*.php public/*.php bin/*.php tests/*.php; do php -l "$file" || exit 1; done
```

Les tests PHP utilisent des fixtures RSS/Atom et une base SQLite temporaire : aucune alerte externe n’est envoyée. Ils couvrent parsing, sécurité des URL, déduplication, CVE/KEV/CVSS, filtres, favoris, OPML, règles, authentification et statistiques.

`tests/browser.cjs` couvre les parcours HTTP et l’affichage mobile avec Playwright et Chromium. Il attend un serveur de test isolé contenant les deux articles de `tests/fixtures/`, avec `ACTU_TEST_USER` / `ACTU_TEST_PASSWORD` ou les identifiants de test documentés dans le script. **Ne le lancez pas contre la production** : il modifie des favoris, une règle et l’état d’une source. Voir [tests/README.md](tests/README.md) pour sa préparation.

## Versionnement et releases

Convention : `vMAJEURE.MINEURE.CORRECTIF`. Une rupture augmente la majeure après 1.0 ; pendant la phase 0.x, une refonte incompatible augmente la mineure. Les corrections compatibles augmentent le correctif.

Avant livraison : mettre à jour `VERSION`, ce README et `RELEASE_NOTES.md`, exécuter les tests, intégrer sur `main`, puis créer un tag annoté sur le commit validé. Une release GitHub reprend les notes du tag dans [Releases](https://github.com/norbertyong/actu/releases). Un push ou une release ne déploie pas le site.

```sh
git fetch origin --tags
git switch --detach v0.4.0   # après publication de ce tag
```

Ne déplacez pas un tag publié. Le numéro courant est aussi affiché dans le pied de page du site. La publication GitHub est une opération distincte du déploiement.

### Publication automatique sur GitHub

Le workflow `.github/workflows/release.yml` crée la release lorsque vous poussez un tag `vMAJEURE.MINEURE.CORRECTIF`. Il vérifie la correspondance avec `VERSION`, extrait la section correspondante de `RELEASE_NOTES.md` et publie les notes avec le jeton intégré de GitHub Actions. Les archives cPanel de première installation et de mise à jour ainsi que leurs SHA256 sont jointes à la release. Une release existante est conservée. Le workflow peut aussi être relancé manuellement avec un tag existant depuis l’onglet Actions. GitHub Actions doit être activé sur le dépôt.
