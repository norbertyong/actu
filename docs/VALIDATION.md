# Validation

## Version 0.4.0 — 29 octobre 2026

**250 contrôles fonctionnels réussis**, sur des bases temporaires sans données utilisateur :

| Vérification | Résultat |
| --- | --- |
| `php tests/run.php` | 52 contrôles existants, tous réussis. |
| `php tests/collector.php` | 12 contrôles HTTP via proxy local simulant les flux, tous réussis. |
| `php tests/evolution.php` | 44 contrôles : technologies Unicode et isolation, filtres CVE, corrections éditoriales, fraîcheur, séparation CLI/web, contrat Groq simulé, cache, clé privée, budgets, erreurs, sorties non fiables et concurrence réelle de cinq processus sur le quota SQLite. |
| `node tests/browser.cjs` | 30 parcours existants rejoués dans une installation isolée. |
| `node tests/deployment.cjs` | 112 contrôles sur un vrai Apache, racine et `/veille` : installation, archive de mise à jour, migration additive depuis un schéma sans les nouvelles tables, conservation des identifiants/articles/favoris, nouveaux écrans, CSRF/authentification, confidentialité de la clé, affichage des réponses IA échappées, cache, quotas web, diagnostic CLI, effacement et mobile. |
| Syntaxe | Tous les fichiers PHP, JavaScript modifiés et générateur Python vérifiés ; `git diff --check` réussi. |

Le transport Groq est simulé dans les tests : **aucune clé utilisateur, aucun appel facturable et aucune réponse réelle de Groq**. Le contrat de requête/réponse et les parcours applicatifs sont couverts ; le compte, la disponibilité du modèle, TLS et la connectivité réelle vers `api.groq.com` restent à vérifier après configuration sur l’hébergement. Ce domaine n’est pas autorisé dans la politique réseau de l’environnement cloud courant.

Les tests Apache utilisent le paquet de déploiement réel, mais ne constituent pas un déploiement sur le cPanel personnel de l’utilisateur. Aucun email ou webhook n’a été envoyé. La validation des flux externes reste soumise aux limites réseau décrites plus bas.

## Historique : refonte 0.2.0

Validation locale du code dans une image PHP 8.3 avec SQLite, cURL, SimpleXML et mbstring, exécutée sous `www-data`.

| Vérification | Résultat |
| --- | --- |
| `php tests/run.php` | 52 contrôles réussis : parsing RSS/Atom, URL, XML, SQLite, déduplication, CVE/KEV/CVSS, recherche Unicode, classement, préférences, OPML, règles, connexion et compteurs. |
| `php tests/collector.php` | 12 contrôles réussis via un proxy HTTP local simulant l’éditeur : succès, ETag, 304, délai, redirections, taille, erreurs HTTP/XML et conservation des articles. |
| `node tests/browser.cjs` | 30 contrôles réussis dans Chromium : parcours publics et administrateur, sources, import/export, préférences, alertes, protection CSRF, effacement et mobile 390 px. |
| Syntaxe PHP | Tous les fichiers sous `app/`, `public/`, `bin/`, `tests/` vérifiés sans erreur. |
| JavaScript | Syntaxe des scripts vérifiée ; aucune erreur JavaScript pendant les parcours navigateur. |
| Docker | Construction réussie, démarrage et tests dans l’image sans montage du code, sous utilisateur non privilégié. |
| Compose | `docker compose config --quiet` réussi. |
| Notifications CLI | Mode aperçu exécuté, aucun message envoyé. |

Total : **94 contrôles fonctionnels réussis**. Les fixtures et identifiants de test appartiennent à des bases temporaires et ne sont pas des données de production.

## Vérifications externes distinctes

- La collecte CERT-FR a été tentée via le proxy cloud : refus de tunnel HTTP CONNECT 403. Le DNS public local n’est pas disponible dans cette machine.
- Les domaines des flux initiaux et de NVD ont été ajoutés au brouillon réseau cloud. La sauvegarde du brouillon ne les active pas sur l’instance courante : revoir/enregistrer les paramètres et publier l’environnement selon l’interface, puis retester.
- Les flux réels, l’enrichissement NVD et les livraisons email/webhook n’ont donc pas été validés de bout en bout avec des services externes.
- Les notifications externes nécessitent des destinations configurées et, pour l’email, un transport mail opérationnel. Aucun destinataire n’a été inventé et aucun envoi n’a été effectué.
- Le workflow CI est fourni ; une exécution locale ne prouve pas que GitHub Actions l’a exécuté.
- Apache/PHP-FPM, Nginx/PHP-FPM, HTTPS public et le déploiement sur le serveur final ne sont pas validés par le serveur de développement.

La configuration de déploiement doit pointer sur `public/`. Le dossier privé SQLite ne doit jamais être exposé par le serveur web. Voir le README pour migration, sauvegardes et planification.

## Paquet cPanel 0.3.0

- `tests/deployment.cjs` : **43 contrôles réussis** sur un vrai serveur Apache, en racine et sous `/veille`.
- Vérifications : archive sans secrets/données, code d’installation généré sur le serveur, refus d’un mauvais code et d’un formulaire sans CSRF, installation, verrouillage, accès HTTP refusé aux fichiers privés, configuration partagée CLI/web, connexion, favoris, cookies, collecte manuelle sans source externe et affichage mobile.
- Les **52 tests fonctionnels PHP** et **12 tests de collecte HTTP** sont rejoués pour cette version : total de **107 contrôles** pour ce cycle de validation.
- L’hébergement cPanel personnel de l’utilisateur n’a pas été testé ; il doit offrir PHP 8.2+, les extensions requises, Apache/LiteSpeed et `.htaccess` actif. La disponibilité réelle des flux et des canaux externes reste distincte.
