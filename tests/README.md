# Tests de développement

## PHP

`php tests/evolution.php` vérifie technologies, isolation des lecteurs, dossiers CVE, diagnostics et intégration Groq avec un transport simulé. Il inclut des processus PHP concurrents pour tester le quota atomique ; aucune clé réelle ni requête Groq ne sont utilisées.

`php tests/run.php` crée sa propre base temporaire et ne dépend d’aucun service externe. Il échoue au premier résultat incorrect. Aucun email ou webhook n’est envoyé.

## Navigateur

Prérequis supplémentaires : Node.js 20+, `npm ci` puis `npx playwright install chromium` (ou un Chromium système via `CHROMIUM_PATH`).

Préparez un serveur **isolé**, sans cron ni collecte réelle :

```sh
export ACTU_STORAGE="$(mktemp -d /tmp/actu-browser-XXXXXXXX)"
export ACTU_ADMIN_USER=admin-test
export ACTU_ADMIN_PASSWORD=local-testing-only-2026
ACTU_TEST_MODE=1 php tests/seed.php
php -S 127.0.0.1:8000 -t public public/router.php
```

Dans un autre terminal :

```sh
npm run test:browser
```

Les identifiants ci-dessus servent uniquement à ce serveur de test lié à localhost. Le test enregistre un favori, une recherche et une règle d’alerte, désactive une source, puis efface les données du navigateur. Utilisez une nouvelle base pour un nouveau scénario. Les captures sont écrites dans `/tmp/actu-mobile.png` et `/tmp/actu-desktop.png`.

Options : `ACTU_TEST_URL`, `ACTU_TEST_USER`, `ACTU_TEST_PASSWORD`, `CHROMIUM_PATH`. Ne visez jamais une installation de production. L’import KEV, CVSS et les refus de destinations internes sont couverts par les tests PHP ; la disponibilité réelle des éditeurs et la livraison email/webhook nécessitent une vérification d’intégration distincte avec vos services configurés.

## Paquet prêt à installer (Apache)

Ce parcours inclut `tests/evolution-browser.cjs` : technologies, regroupement CVE, paramètres Groq, cache, quotas, données privées et affichage mobile. Le ZIP de mise à jour est appliqué sur un schéma sans les nouvelles tables pour vérifier la migration et la conservation des données. Tous les appels Groq y sont simulés ou bloqués volontairement par le quota avant le transport.

Construisez l’image de test Apache avec `docker build -f tests/Dockerfile.apache -t actu:apache-test .`, puis exécutez `node tests/deployment.cjs`. Dans le cloud avec interception TLS, ajoutez `--secret id=proxy_ca,src=/etc/ssl/certs/ca-certificates.crt` à la construction.

Le test génère le ZIP, crée son propre conteneur temporaire, installe le site à la racine et dans `/veille`, puis vérifie l’assistant, les protections Apache, la configuration CLI, les liens, les cookies et les favoris. Il n’utilise ni le site ni les données de production. Le conteneur et les fichiers temporaires sont supprimés en fin de test. Les collectes externes sont désactivées dans les fixtures ; aucun email/webhook n’est envoyé.
