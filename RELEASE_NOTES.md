# Notes de version

## Non publié

## v0.5.0 — 30 octobre 2026

### Module privé de suivi des CVE

- Tableau de suivi dans l’administration : recherche, filtres par statut, priorité, responsable, KEV, retards, réexamens et échéances sous sept jours ; pagination et compteurs.
- Une fiche par CVE, ouverte depuis les flux ou par saisie manuelle de son identifiant. La consultation seule n’écrit pas de décision.
- Statuts À analyser, Concerné, En cours, Corrigé, Non concerné et Risque accepté ; priorité interne, responsable, échéance, systèmes/versions et plan de remédiation.
- Actions avec responsables, échéances, états À faire/Terminée/Annulée ; les annulations sont justifiées. Une clôture exige une justification et aucune action ouverte.
- Acceptation du risque avec responsable et date de réexamen obligatoires ; rappels visibles dans le tableau, sans envoi externe.
- Notes, liens vers justificatifs et tickets, historique horodaté avec auteur et valeurs avant/après. La réouverture d’une décision est justifiée.
- Nouvelles publications, corrections de flux et changements KEV/CVSS signalés pour réexamen, sans écraser le statut. Les collectes identiques ne génèrent pas de faux rappel.
- Conflits entre formulaires détectés par révision et transaction SQLite ; la saisie non enregistrée reste consultable. Les informations arrivées après ouverture ne peuvent pas être acquittées silencieusement.
- CSV privé filtré et fiche Markdown avec décisions, actions, sources et historique. Données internes absentes des exports publics et des demandes Groq.

### Déploiement et compatibilité

- Migration SQLite additive automatique ; conservation des configurations, articles, favoris et données IA. Le suivi reste conservé quand les publications disparaissent.
- Paquets cPanel complets et de mise à jour, README embarqué, inventaire et contrôles de présence des pages du module.
- Routeur de développement complété pour le suivi et les écrans de la version 0.4.x.
- Les responsables sont des champs libres ; le compte administrateur existant reste le mécanisme d’accès. Pas d’envoi automatique de tickets ou de messages, ni d’analyse automatique de l’exposition des versions.

### Validation

- 393 contrôles réussis : 52 PHP existants, 12 collecte HTTP, 44 évolutions/Groq simulé, 60 suivi CVE, 33 parcours depuis les sources et 192 contrôles Apache/installation/migration. Détail dans `docs/VALIDATION.md`.
- Aucun accès au cPanel personnel ni appel Groq réel ; le déploiement sur l’hébergement reste à effectuer avec le paquet adapté.

## v0.4.1 — 29 octobre 2026

- Validation : 112 contrôles Apache réussis en racine et sous-dossier, incluant installation, mise à jour et paramètres IA.
- Paquets cPanel complets reconstruits depuis le même tag que la release.
- README embarqué dans le dossier privé et inventaire des fichiers `CONTENU.txt`.
- Contrôle de présence des pages IA, diagnostics, technologies et dossiers avant création du ZIP.
- Accès direct à Paramètres IA sur le tableau de bord administrateur.
- Deux archives : première installation et mise à jour préservant configuration et données.


## v0.4.0 — 29 octobre 2026

### Veille personnalisée

- Tableau « À traiter » : articles non lus des sept derniers jours, priorité aux mentions de CVE KEV ou CVSS ≥ 9, liens vers tous les résultats et alertes récentes pour l’administrateur.
- Jusqu’à 30 technologies suivies par navigateur ; filtre partagé entre actualités, vulnérabilités, dossiers, exports et recherches enregistrées. Correspondance textuelle Unicode sur titres/extraits et produits KEV, sans prétendre établir la vulnérabilité d’une version installée.
- Dossiers CVE : regroupement des publications par identifiant, nombre d’articles et de sources, conservation des originaux et pagination. Les références retirées lors d’une correction éditoriale quittent le dossier.
- Diagnostic des collectes : erreurs, retards, publications anciennes et historique du dernier passage CLI distinct des demandes web.

### Assistant IA Groq

- Configuration administrateur : clé privée, modèle de conversation compatible JSON, activation facultative et limites quotidiennes.
- Résumé en français à la demande, produits et actions mentionnés dans l’extrait, informations manquantes et lien vers la publication originale. Aucun appel IA automatique à la collecte ou à la simple consultation.
- Appels HTTPS côté serveur à destination fixe, réponses bornées et validées, affichage échappé et protection administrateur/CSRF.
- Cache par article/texte/modèle, verrou contre les générations simultanées et quotas atomiques en SQLite ; les échecs comptent aussi. Le budget en unités prudentes n’est pas une facturation exacte ni un plafond monétaire.
- Clé stockée dans le dossier privé avec permissions 0600, jamais renvoyée au navigateur. Variable serveur `ACTU_GROQ_API_KEY` prioritaire ; aucun secret inclus dans les archives.

### Installation et mise à jour

- Deux ZIP cPanel : première installation et mise à jour, chacun avec SHA256. Le ZIP de mise à jour exclut configuration, stockage et activation de l’installateur.
- Évolution SQLite additive et automatique ; conservation des articles, favoris et identifiants existants. Sauvegarde complète avant remplacement du code.
- README enrichi : activation Groq, menus, confidentialité, budgets, dépannage et mise à jour pas à pas.

### Validation et limites

- 250 contrôles réussis : 52 PHP existants, 12 collecte HTTP, 44 évolutions/Groq simulé, 30 parcours navigateur et 112 contrôles Apache/installation/mise à jour. Résultats détaillés dans `docs/VALIDATION.md`.
- Aucun appel à un compte Groq réel : aucune clé utilisateur n’a été fournie et `api.groq.com` n’est pas autorisé par la politique réseau de cet environnement cloud. La première génération sur l’hébergement validera le compte, le modèle et la connectivité réels.
- Cette livraison couvre les évolutions prioritaires proposées. EPSS, comptes lecteurs multiappareils, PWA, digest et workflow de remédiation restent des pistes ultérieures.

## v0.3.0 — 28 octobre 2026

### Installation prête pour cPanel

- Archive de déploiement dédiée : fichiers publics à la racine, code/configuration/SQLite dans `.actu/` protégé par Apache/LiteSpeed.
- Assistant de première installation : contrôle des extensions, code privé généré sur le serveur, création de l’accès administrateur et initialisation SQLite.
- Configuration locale automatique commune au web et à la CLI ; aucun fichier `.env` ou bootstrap manuel à préparer.
- Verrouillage de l’installateur après installation, protection CSRF, mot de passe haché et verrou exclusif contre les installations simultanées.
- Navigation, formulaires, redirections et cookies compatibles avec les sous-dossiers.
- Collecte manuelle depuis l’administration, source par source, sans terminal. Les mises à jour sans visite nécessitent toujours une tâche planifiée.
- Générateur d’archive avec liste explicite des fichiers et somme SHA256 ; publication du paquet dans les assets GitHub.

### Validation

- 107 contrôles réussis : 52 PHP, 12 collecte HTTP et 43 installation réelle Apache en racine et sous-dossier.
- Aucun message externe envoyé ni accès au compte cPanel de l’utilisateur.

### Hébergement requis

- PHP 8.2+, extensions SQLite/cURL/SimpleXML/mbstring/session, Apache ou LiteSpeed avec `.htaccess` actif.
- HTTPS conseillé avant installation ; DNS et HTTPS sortants nécessaires aux flux.
- Aucun envoi externe d’alerte ni installation de cron n’est activé par l’assistant.
- Archive de première installation : préserver configuration et données lors des mises à jour.

## v0.2.0 — 28 octobre 2026

### Nouveautés

- Nouvelle interface responsive, navigation commune, thème clair/sombre/automatique et styles locaux.
- Historique SQLite sans limite de 300 articles, filtres combinés par période, catégorie, source et état de lecture.
- Favoris, articles lus et recherches enregistrées propres à chaque navigateur ; effacement des données de lecture.
- Gestion administrateur des sources : ajout, modification, activation, test, diagnostic et import/export OPML.
- Collecte CLI indépendante des visites, verrou exclusif, délai de 20 minutes et requêtes HTTP conditionnelles.
- Extraction des CVE, import du catalogue CISA KEV et enrichissement CVSS facultatif par NVD.
- Règles de veille, notifications internes et commandes d’envoi email/webhook configurables.
- Exports CSV et RSS filtrés, statistiques journalières agrégées, pages Confidentialité et Versions.
- Dockerfile sans privilèges, services Compose web/collecteur, tests PHP, parcours Playwright et workflow CI.

### Corrections et sécurité

- Sources communes à toutes les pages ; suppression de la configuration divergente et du filtre par source incohérent.
- Pagination bornée, paramètres de requête validés et prise en charge Atom avec espace de noms.
- Authentification partagée, mot de passe haché configurable, renouvellement/expiration de session et limitation des tentatives.
- Protection CSRF sur les mutations et la déconnexion, cookies HttpOnly/SameSite et politique CSP.
- Validation des URL et redirections, refus des adresses privées/réservées, limites de taille et XML sans entités externes.
- SQLite et données privées hors de la racine publique ; disparition des lectures des anciens journaux visiteurs.

### Migration incompatible

- Le DocumentRoot doit désormais pointer sur `public/` et PHP doit disposer de `pdo_sqlite`.
- Lancer `php bin/init.php`, puis `php bin/collect.php`, et planifier la collecte séparément.
- Les anciennes données de cache et `feeds.php` ne sont pas importées automatiquement. Les anciennes données locales restent préservées hors de la racine publique.
- Les canaux d’alerte nécessitent leurs destinations ; l’email nécessite un transport mail configuré.

### Validation et limites

- 94 contrôles fonctionnels réussis : 52 PHP, 12 collecte HTTP simulée et 30 parcours Chromium. Rapport détaillé : `docs/VALIDATION.md`.
- L’accès réel aux sources, à NVD et aux canaux d’alerte dépend du réseau et des services configurés. Les fixtures ne prouvent pas leur disponibilité.
- Le cron et les transports externes ne sont pas installés automatiquement. Les favoris restent locaux au navigateur.
- La publication du tag et de la release est distincte du déploiement du site.

## v0.1.0 — 28 octobre 2026

Première version de développement.

### Documentation

- Description du cycle de collecte, du cache, de la déduplication et des filtres.
- Guide des menus publics et administratifs, des paramètres d’URL et de la déconnexion.
- Convention de versionnement, préparation des tags et distinction entre commit, release et déploiement.
- Précision sur les fonctions annoncées mais non implémentées et l’incohérence du filtre par source des vulnérabilités.

### Base de la version

Import initial issu du commit `3ab2db8`, avec sa documentation complétée.

### Ajouts

- Import de l’application PHP depuis l’archive `actu.zip`.
- Accueil de veille RSS/Atom : déduplication, tri chronologique, recherche, filtres, pagination et cache de 20 minutes.
- Pages d’administration, de consultation des visites et de veille sur les vulnérabilités produits ; déconnexion par session PHP.
- README en français décrivant les prérequis, le démarrage, la configuration et les limites du code importé.
- Règles Git excluant les caches, journaux, fichiers d’environnement privés et anciennes copies de l’accueil ; maintien du dossier de cache via `.gitkeep`.

### Adaptation de l’import

- Remplacement des identifiants administrateur codés en dur par `ACTU_ADMIN_USER` et `ACTU_ADMIN_PASSWORD` dans `admin.php`, `analytics.php` et `vulprod.php`.
- Refus de connexion lorsque ces variables ne sont pas renseignées.
- Conservation locale des données et sauvegardes de l’archive, sans les intégrer aux fichiers à versionner.

### Limites connues

- `feeds.php` n’est pas fourni ; les flux de l’accueil restent configurés séparément dans `index.php`.
- Les fichiers optionnels `vulprod.log` et `cache/vul_prod.json` ne sont pas générés par les scripts importés.
- Analytics enregistre uniquement les visites authentifiées de sa propre page.
- La collecte dépend de sources externes et peut être lente au premier chargement.
- Aucune suite de tests automatisés ni configuration de déploiement n’est fournie.

### Validation de cette importation

- Chemins de l’archive contrôlés avant extraction ; intégrité des fichiers actifs comparée à l’archive, hors adaptation explicite des identifiants.
- Exclusions Git et absence de valeurs d’identifiants dans les scripts actifs vérifiées.
- Syntaxe PHP et démarrage non exécutés : l’interpréteur PHP n’est pas installé dans l’environnement courant.

La publication du tag ne déploie pas automatiquement le site.
