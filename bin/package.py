#!/usr/bin/env python3
"""Build a cPanel Apache/LiteSpeed deployment, excluding all local data and secrets."""
import argparse
import hashlib
import re
import zipfile
from pathlib import Path

root = Path(__file__).resolve().parents[1]
version = (root / 'VERSION').read_text().strip()
if not re.fullmatch(r'\d+\.\d+\.\d+', version):
    raise SystemExit('Invalid VERSION')
parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, default=root / 'dist' / f'actu-cpanel-v{version}.zip')
args = parser.parse_args()
args.output.parent.mkdir(parents=True, exist_ok=True)
files = {}
for path in (root / 'public').glob('*.php'):
    if path.name == 'router.php':
        continue
    text = path.read_text().replace("__DIR__.'/../app/", "__DIR__.'/.actu/app/")
    if path.name == 'install.php':
        text = text.replace("$root=__DIR__.'/..';", "$root=__DIR__.'/.actu';")
    files[path.name] = text.encode()
for path in (root / 'public/assets').glob('*'):
    if path.is_file(): files['assets/' + path.name] = path.read_bytes()
for folder in ['app', 'bin']:
    for path in (root / folder).glob('*.php'):
        files[f'.actu/{folder}/{path.name}'] = path.read_bytes()
for name in ['VERSION', 'RELEASE_NOTES.md']:
    files['.actu/' + name] = (root / name).read_bytes()
files['.htaccess'] = b'''DirectoryIndex index.php
Require all granted
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^\\.actu(?:/|$) - [F,L]
</IfModule>
<FilesMatch "^\\.">
Require all denied
</FilesMatch>
'''
files['.actu/.htaccess'] = b'Require all denied\n'
files['.actu/config/.htaccess'] = b'Require all denied\n'
files['.actu/config/install.enabled'] = b'Fresh installation only.\n'
files['.actu/storage/.htaccess'] = b'Require all denied\n'
files['INSTALLATION.txt'] = '''ACTU CYBER & IT — INSTALLATION CPANEL

1. Dans cPanel, activez PHP 8.2+ et les extensions pdo_sqlite, curl, SimpleXML, mbstring, session.
2. Copiez/extrayez TOUT le contenu de cette archive dans le dossier du site, y compris .htaccess et .actu.
3. Ouvrez l’URL du dossier : le site affiche l’assistant d’installation.
4. Dans le gestionnaire cPanel, affichez les fichiers cachés et lisez .actu/config/install.key (créé sur le serveur à la première visite).
5. Saisissez ce code dans l’assistant et choisissez votre utilisateur/mot de passe administrateur.
6. Connectez-vous et cliquez sur « Récupérer les articles » dans l’administration.

Aucun terminal, fichier .env ou base MySQL à préparer. Les règles .htaccess doivent être actives (Apache/LiteSpeed). Ne pas utiliser cette disposition sous Nginx sans règles explicites interdisant tout accès à .actu.
Utilisez HTTPS. L’assistant se verrouille après installation ; aucun identifiant par défaut n’est livré.
La récupération initiale nécessite DNS et HTTPS sortants autorisés par l’hébergement.

Pour les mises à jour : sauvegarder, ne pas écraser .actu/config ni .actu/storage, ne pas recopier install.enabled. Cette archive est destinée à une PREMIÈRE installation.
Pour une mise à jour automatique hors connexion des visiteurs, ajouter une tâche cron cPanel toutes les 20 minutes :
/CHEMIN/DU/PHP /home/COMPTE/public_html/actu/.actu/bin/collect.php
Adapter les chemins. Sans cron, la collecte peut être lancée depuis l’administration.
'''.encode()
with zipfile.ZipFile(args.output, 'w', zipfile.ZIP_DEFLATED) as archive:
    for name, data in sorted(files.items()):
        info = zipfile.ZipInfo(name, date_time=(2026, 1, 1, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        archive.writestr(info, data)
digest = hashlib.sha256(args.output.read_bytes()).hexdigest()
args.output.with_suffix('.zip.sha256').write_text(f'{digest}  {args.output.name}\n')
print(f'Package: {args.output.name}; {len(files)} files; SHA256: {digest}')
