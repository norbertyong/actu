const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const evolution = require('./evolution-browser.cjs');
const tracking = require('./tracking-browser.cjs');
const env = {...process.env};
for (const key of ['DOCKER_HOST','DOCKER_CONTEXT','DOCKER_TLS','DOCKER_TLS_VERIFY','DOCKER_CERT_PATH']) delete env[key];
const docker = (...args) => execFileSync('docker',['--host=unix:///var/run/docker.sock',...args],{env,encoding:'utf8',stdio:['pipe','pipe','pipe']});
const name = 'actu-package-'+process.pid;
const temp = fs.mkdtempSync(path.join(os.tmpdir(),'actu-package-'));
let browser;let started=false;let checks=0;
const check=(v,label)=>{assert.ok(v,label);checks++;console.log('OK '+label);};
(async()=>{
 try {
  const zip=path.join(temp,'deployment.zip');
  execFileSync('python3',['bin/package.py','--output',zip],{stdio:'pipe'});
  execFileSync('python3',['-c',`import zipfile,sys,pathlib
with zipfile.ZipFile(sys.argv[1]) as z:
 names=z.namelist()
 assert '.actu/config/install.enabled' in names
 assert not any(n.endswith(('local.php','install.key','.sqlite','.log')) for n in names)
 for folder in [pathlib.Path(sys.argv[2]),pathlib.Path(sys.argv[2])/'veille']:z.extractall(folder)
`,zip,path.join(temp,'site')]);
  check(true,'Archive sans configuration, clé ni données locales');
  const updateZip=path.join(temp,'update.zip');
  execFileSync('python3',['bin/package.py','--update','--output',updateZip],{stdio:'pipe'});
  execFileSync('python3',['-c',`import zipfile,sys
with zipfile.ZipFile(sys.argv[1]) as z:
 assert not any(n.startswith(('.actu/config/','.actu/storage/')) for n in z.namelist())
 assert '.actu/app/ai.php' in z.namelist()
 z.extractall(sys.argv[2])
`,updateZip,path.join(temp,'update')]);
  check(true,'Paquet de mise à jour sans configuration, stockage ou activation installateur');
  docker('run','-d','--name',name,'-p','127.0.0.1::80','actu:apache-test');started=true;
  docker('cp',path.join(temp,'site')+'/.',name+':/var/www/html');
  docker('exec',name,'chown','-R','www-data:www-data','/var/www/html');
  const port=docker('port',name,'80/tcp').trim().split(':').pop();const origin='http://127.0.0.1:'+port;
  browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||(fs.existsSync('/usr/bin/chromium')?'/usr/bin/chromium':undefined),args:['--no-sandbox']});
  for(const prefix of ['', '/veille']) {
   const context=await browser.newContext();const page=await context.newPage();const base=origin+prefix;const privateRoot='/var/www/html'+prefix+'/.actu';
   await page.goto(base+'/');check(page.url()===base+'/install.php','Assistant automatique '+(prefix||'/'));
   check((await context.request.get(base+'/assets/app.css')).status()===200,'Styles de l’assistant');
   for(const target of ['/.actu/config/install.key','/.actu/app/core.php','/.actu/storage/actu.sqlite','/.actu/.htaccess'])check((await context.request.get(base+target)).status()===403,'Protection Apache '+target);
   const bad=await context.request.post(base+'/install.php',{form:{csrf:'bad',key:'bad',user:'intruder',pass:'not-a-real-password',confirm:'not-a-real-password'}});check((await bad.text()).includes('Formulaire expiré'),'Installation sans CSRF refusée');
   const key=docker('exec',name,'cat',privateRoot+'/config/install.key').trim();check(/^[a-f0-9]{64}$/.test(key),'Clé générée uniquement sur l’hébergement');
   await page.getByLabel('Code d’installation',{exact:true}).fill('0'.repeat(64));await page.getByLabel('Utilisateur administrateur',{exact:true}).fill('cpanel-test');await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill('local-cpanel-test-password');await page.getByLabel('Confirmer le mot de passe',{exact:true}).fill('local-cpanel-test-password');await page.getByRole('button',{name:'Installer le site'}).click();check((await page.locator('body').innerText()).includes('Code d’installation incorrect'),'Code incorrect refusé');
   await page.getByLabel('Code d’installation',{exact:true}).fill(key);await page.getByLabel('Utilisateur administrateur',{exact:true}).fill('cpanel-test');await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill('local-cpanel-test-password');await page.getByLabel('Confirmer le mot de passe',{exact:true}).fill('local-cpanel-test-password');await page.getByRole('button',{name:'Installer le site'}).click();check(page.url()===base+'/login.php?installed=1','Installation et redirection correcte');
   check((await context.request.get(base+'/install.php')).status()===404,'Installateur verrouillé');
   check((await context.request.get(base+'/.actu/config/local.php')).status()===403,'Configuration générée inaccessible par HTTP');
   const init=docker('exec',name,'php',privateRoot+'/bin/init.php');check(init.includes('13 sources'),'CLI réutilise automatiquement la configuration');
   await page.getByLabel('Utilisateur',{exact:true}).fill('cpanel-test');await page.getByLabel('Mot de passe',{exact:true}).fill('local-cpanel-test-password');await page.getByRole('button',{name:'Se connecter'}).click();check(page.url()===base+'/admin.php','Connexion après installation');
   check(await page.getByRole('button',{name:'Récupérer les articles'}).count()===1,'Collecte depuis l’administration disponible');
   const cookie=(await context.cookies()).find(c=>c.name==='actu_session');check(cookie?.path===(prefix||'')+'/','Cookie limité au dossier du site');
   docker('exec',name,'php','-r',`require '${privateRoot}/app/bootstrap.php';run('UPDATE sources SET enabled=0');$s=(int)run('SELECT id FROM sources LIMIT 1')->fetchColumn();save_article($s,['title'=>'Article de test du paquet','url'=>'https://example.org/package-test','summary'=>'Fixture sans collecte réseau','author'=>'Test','published'=>time()]);`);
   await page.getByRole('button',{name:'Récupérer les articles'}).click();await page.getByText('Collecte terminée. Vous pouvez consulter les actualités.',{exact:true}).waitFor();check(true,'Collecte web verrouillée et terminée sans réseau');
   await page.getByRole('link',{name:'Actualités',exact:true}).click();check(page.url()===base+'/index.php' && await page.locator('.article-card').count()===1,'Actualités et liens compatibles sous-dossier');
   await page.getByRole('button',{name:'Ajouter aux favoris'}).click();await page.getByRole('link',{name:'Favoris',exact:true}).click();check(page.url()===base+'/favorites.php' && await page.locator('.article-card').count()===1,'Formulaires et redirections des favoris');
   // Simulate the pre-0.4 schema with real existing credentials, articles and favorites.
   const snapshot=`echo hash('sha256',file_get_contents('${privateRoot}/config/local.php').json_encode(run('SELECT * FROM articles')->fetchAll()).json_encode(run('SELECT * FROM states')->fetchAll()));`;
   const before=docker('exec',name,'php','-r',`require '${privateRoot}/app/bootstrap.php';${snapshot}db()->exec('DROP TABLE cve_events; DROP TABLE cve_tasks; DROP TABLE cve_cases; DROP TABLE technologies; DROP TABLE ai_cache; DROP TABLE ai_usage');`);
   docker('cp',path.join(temp,'update')+'/.',name+':/var/www/html'+prefix);
   docker('exec',name,'chown','-R','www-data:www-data','/var/www/html'+prefix);
   const after=docker('exec',name,'php','-r',`require '${privateRoot}/app/bootstrap.php';${snapshot}`);
   check(before===after,'Mise à jour préserve identifiants, articles et favoris existants');
   check(docker('exec',name,'php','-r',`require '${privateRoot}/app/bootstrap.php';echo run("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('technologies','ai_cache','ai_usage')")->fetchColumn();`)==='3','Migration additive crée les trois nouvelles tables');
   check((await context.request.get(base+'/install.php')).status()===404,'Mise à jour ne réactive pas l’installateur');
   await evolution({context,page,base,docker,name,privateRoot,check});
   check(docker('exec',name,'php','-r',`require '${privateRoot}/app/bootstrap.php';echo run("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('cve_cases','cve_tasks','cve_events')")->fetchColumn();`)==='3','Migration additive du module CVE');
   // Upgrade from a populated 0.4-style database; only the new workflow tables are absent.
   docker('exec',name,'php','-r',`require '${privateRoot}/app/bootstrap.php';ai_save_config(['model'=>'llama-3.3-70b-versatile','daily_requests'=>'20','daily_tokens'=>'100000','key'=>'test_preserved_key_for_upgrade_only']);add_technology('upgrade-fixture','FortiOS');run('INSERT OR REPLACE INTO ai_usage VALUES(?,3,10000)',[gmdate('Y-m-d')]);run('INSERT OR REPLACE INTO ai_cache VALUES(1,?,?,?,?)',['fixture','offline-model','{}',time()]);`);
   const aiSnapshot=`echo hash('sha256',file_get_contents(store_dir().'/groq.json').json_encode(run('SELECT * FROM ai_usage ORDER BY day')->fetchAll()).json_encode(run('SELECT * FROM ai_cache ORDER BY article_id')->fetchAll()).json_encode(run('SELECT * FROM technologies ORDER BY id')->fetchAll()));`;
   const aiBefore=docker('exec',name,'php','-r',`require '${privateRoot}/app/bootstrap.php';${aiSnapshot}db()->exec('DROP TABLE cve_events; DROP TABLE cve_tasks; DROP TABLE cve_cases');`);
   docker('cp',path.join(temp,'update')+'/.',name+':/var/www/html'+prefix);
   docker('exec',name,'chown','-R','www-data:www-data','/var/www/html'+prefix);
   const aiAfter=docker('exec',name,'php','-r',`require '${privateRoot}/app/bootstrap.php';${aiSnapshot}`);
   check(aiBefore===aiAfter,'Migration depuis 0.4 conserve clé Groq, cache, quotas et technologies');
   await tracking({context,page,base,docker,name,privateRoot,check});
   await page.setViewportSize({width:390,height:844});check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Affichage mobile');
   await page.getByRole('button',{name:'Déconnexion',exact:true}).click();check(page.url()===base+'/login.php','Déconnexion du paquet');
   await context.close();
  }
  console.log(`PASS : ${checks} contrôles du paquet Apache, racine et sous-dossier.`);
 } finally {if(browser)await browser.close();if(started)docker('rm','-f',name);fs.rmSync(temp,{recursive:true,force:true});}
})().catch(e=>{console.error(e.message);process.exitCode=1;});
