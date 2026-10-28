# Notes de version

## Non publié

Aucun changement en attente.

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
