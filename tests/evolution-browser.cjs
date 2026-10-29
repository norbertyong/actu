const assert = require('node:assert/strict');

module.exports = async ({context, page, base, docker, name, privateRoot, check}) => {
  const php = code => docker('exec', name, 'php', '-r', `require '${privateRoot}/app/bootstrap.php';${code}`);
  php(`$sources=run('SELECT id FROM sources ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
    save_article((int)$sources[0],['title'=>'FortiOS CVE-2026-12345','url'=>'https://example.org/package-test','summary'=>'Installer le correctif FortiOS.','author'=>'Test','published'=>time()]);
    save_article((int)$sources[1],['title'=>'Autre analyse FortiOS CVE-2026-12345','url'=>'https://example.org/second','summary'=>'Autre source sur le même sujet.','author'=>'Test','published'=>time()]);
    run("UPDATE cves SET exploited=1,cvss=9.8 WHERE id='CVE-2026-12345'");`);
  await page.goto(base+'/technologies.php');
  check((await context.request.post(base+'/technologies.php',{form:{name:'Intrusion'}})).status()===403,'Technologies protégées par CSRF');
  await page.getByLabel('Produit ou expression').fill('FortiOS');
  await page.getByRole('button',{name:'Suivre cette technologie'}).click();
  check(await page.getByRole('button',{name:'Retirer FortiOS',exact:true}).count()===1,'Technologie enregistrée');
  await page.goto(base+'/dashboard.php');
  check(await page.locator('.article-card').count()===2 && (await page.locator('.stats-grid').innerText()).includes('2'),'Tableau à traiter personnalisé');
  await page.locator('.article-card').first().getByRole('button',{name:'Marquer comme lu'}).click();
  check(page.url()===base+'/dashboard.php' && await page.locator('.article-card').count()===1,'Article lu retiré du tableau à traiter');
  await page.goto(base+'/dossiers.php?tracked=1');
  check(await page.locator('.article-card').count()===1 && (await page.locator('.article-card').innerText()).includes('2 source(s)'),'Dossier unique avec deux sources');
  await page.getByRole('link',{name:'CVE-2026-12345',exact:true}).click();
  check(await page.locator('.article-card').count()===2,'Dossier conserve les deux publications');
  await page.getByRole('button',{name:'Appliquer les filtres'}).click();
  check(page.url().includes('cve=CVE-2026-12345'),'Filtrage conserve le dossier CVE');
  const other=await context.browser().newContext();
  const otherPage=await other.newPage();
  await otherPage.goto(base+'/dashboard.php?tracked=1');
  check(await otherPage.locator('.article-card').count()===0,'Périmètres isolés entre navigateurs');
  for(const path of ['/ai.php?id=1','/settings.php','/diagnostics.php']) {
    const response=await other.request.get(base+path,{maxRedirects:0});
    check(response.status()===303 && response.headers().location.endsWith('/login.php'),'Accès administrateur exigé : '+path);
  }
  await other.close();
  await page.goto(base+'/settings.php');
  check(await page.getByLabel('Nouvelle clé API Groq').inputValue()==='' && !await page.getByLabel('Activer les résumés Groq').isChecked(),'IA inactive et clé vide par défaut');
  check((await context.request.post(base+'/settings.php',{form:{enabled:'1'}})).status()===403,'Configuration IA sans CSRF refusée');
  const fakeKey='test_fake_key_for_offline_browser_only';
  await page.getByLabel('Nouvelle clé API Groq').fill(fakeKey);
  await page.getByLabel('Activer les résumés Groq').check();
  await page.getByRole('button',{name:'Enregistrer les paramètres IA'}).click();
  check((await page.content()).includes('configurée') && !(await page.content()).includes(fakeKey) && await page.getByLabel('Nouvelle clé API Groq').inputValue()==='','Clé enregistrée mais jamais réaffichée');
  check((await context.request.get(base+'/.actu/storage/groq.json')).status()===403,'Clé Groq inaccessible par HTTP');
  await page.getByRole('button',{name:'Enregistrer les paramètres IA'}).click();
  check(php(`echo ai_config()['key']!==''?'present':'absent';`)==='present','Sauvegarde sans clé conserve la clé privée');
  await page.goto(base+'/ai.php?id=1');
  check((await context.request.post(base+'/ai.php?id=1',{form:{}})).status()===403,'Génération IA sans CSRF refusée');
  check((await context.request.get(base+'/ai.php?id=999999')).status()===404,'Article IA absent renvoie 404');
  // Exhaust quota so the real web endpoint is exercised WITHOUT an external API call.
  php(`run('INSERT OR REPLACE INTO ai_usage VALUES(?,20,90000)',[gmdate('Y-m-d')]);`);
  await page.getByRole('button',{name:'Générer le résumé'}).click();
  check((await page.getByRole('alert').innerText()).includes('Quota IA quotidien atteint'),'Génération web bloque le quota avant tout appel');
  php(`run('DELETE FROM ai_usage');$a=run('SELECT * FROM articles WHERE id=1')->fetch();
    ai_generate($a,fn()=>['choices'=>[['finish_reason'=>'stop','message'=>['content'=>json_encode(['summary'=>'<img src=x onerror=alert(1)> Synthèse de test','products'=>['FortiOS'],'actions'=>['Installer le correctif'],'uncertainties'=>'Extrait limité, vérifier la source.'])]]]]);`);
  await page.goto(base+'/ai.php?id=1');
  check(await page.locator('.ai-result').count()===1 && await page.locator('.ai-result img').count()===0,'Résumé en cache affiché comme texte sans exécution HTML');
  check(php(`echo ai_usage()['requests'];`)==='1','Consultation du résumé ne consomme pas de nouvel appel');
  await page.goto(base+'/settings.php');
  await page.getByRole('button',{name:'Supprimer les résumés IA'}).click();
  await page.goto(base+'/ai.php?id=1');
  check(await page.locator('.ai-result').count()===0,'Suppression du cache depuis l’administration');
  await page.goto(base+'/settings.php');
  await page.getByLabel('Supprimer la clé enregistrée localement').check();
  await page.getByLabel('Activer les résumés Groq').uncheck();
  await page.getByRole('button',{name:'Enregistrer les paramètres IA'}).click();
  check(php(`echo ai_config()['key']===''?'absent':'present';`)==='absent','Clé supprimée depuis l’administration');
  await page.goto(base+'/diagnostics.php');
  check((await page.locator('main').innerText()).includes('Aucun lancement CLI observé'),'Collecte web ne valide pas le cron');
  docker('exec',name,'php',privateRoot+'/bin/collect.php'); // all sources disabled earlier
  await page.reload();
  check((await page.locator('main').innerText()).includes('Passage CLI récent'),'Diagnostic détecte une exécution CLI terminée');
  for(const path of ['/dashboard.php','/dossiers.php','/settings.php','/ai.php?id=1','/diagnostics.php']) {
    await page.goto(base+path); await page.setViewportSize({width:390,height:844});
    check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Nouveau parcours mobile : '+path);
  }
  await page.goto(base+'/privacy.php');
  await page.getByRole('button',{name:'Effacer mes données de lecture'}).click();
  await page.goto(base+'/technologies.php');
  check(await page.getByRole('button',{name:'Retirer FortiOS',exact:true}).count()===0,'Effacement inclut les technologies suivies');
};
