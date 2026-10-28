const { chromium } = require("playwright");
const assert = require("node:assert/strict");
const fs = require("node:fs");
(async () => {
  const browser = await chromium.launch({
    executablePath:
      process.env.CHROMIUM_PATH ||
      (fs.existsSync("/usr/bin/chromium") ? "/usr/bin/chromium" : undefined),
    headless: true,
    args: ["--no-sandbox"],
  });
  const context = await browser.newContext();
  const page = await context.newPage();
  const errors = [];
  page.on("pageerror", (e) => errors.push(e.message));
  const base = process.env.ACTU_TEST_URL || "http://127.0.0.1:8000";
  let checks = 0;
  const check = (v, label) => {
    assert.ok(v, label);
    checks++;
    console.log("OK " + label);
  };
  try {
    await page.goto(base);
    check(
      (await page.locator(".article-card").count()) === 2,
      "Accueil avec articles",
    );
    check(
      (await page.locator("header").innerText()).includes("Vulnérabilités"),
      "Navigation commune",
    );
    await page
      .locator(".article-card")
      .first()
      .getByRole("button", { name: "Ajouter aux favoris" })
      .click();
    await page.goto(base + "/favorites.php");
    check(
      (await page.locator(".article-card").count()) === 1,
      "Favori enregistré",
    );
    await page.getByRole("button", { name: "Marquer comme lu" }).click();
    check(
      (await page.getByRole("button", { name: "Lu · annuler" }).count()) === 1,
      "Article marqué lu",
    );
    await page.goto(base + "/?q=Linux");
    check((await page.locator(".article-card").count()) === 1, "Recherche");
    await page.getByLabel("Mémoriser cette recherche").fill("Veille Linux");
    await page
      .getByRole("button", { name: "Enregistrer", exact: true })
      .click();
    await page.locator(".saved-searches summary").click();
    check(
      (await page
        .getByRole("link", { name: "Veille Linux", exact: true })
        .count()) === 1,
      "Recherche mémorisée",
    );
    const csv = await context.request.get(
      base + "/export.php?format=csv&q=Linux",
    );
    check(
      csv.status() === 200 && (await csv.text()).includes("Linux"),
      "Export CSV",
    );
    const rss = await context.request.get(
      base + "/export.php?format=rss&q=Linux",
    );
    check(
      rss.status() === 200 && (await rss.text()).includes("<rss"),
      "Export RSS",
    );
    check(
      (
        await context.request.post(base + "/action.php", {
          form: { action: "read", article: "1" },
        })
      ).status() === 403,
      "CSRF refusé",
    );
    check(
      (await context.request.get(base + "/storage/actu.sqlite")).status() ===
        404,
      "Base privée inaccessible",
    );
    check(
      (await context.request.get(base + "/../README.md")).status() === 404,
      "Fichiers du dépôt inaccessibles",
    );
    check(
      (await context.request.get(base + "/logout.php")).status() === 405,
      "Déconnexion par GET refusée",
    );
    await page.goto(base + "/admin.php");
    check(page.url().endsWith("/login.php"), "Administration protégée");
    await page
      .getByLabel("Utilisateur", { exact: true })
      .fill(process.env.ACTU_TEST_USER || "admin-test");
    await page
      .getByLabel("Mot de passe", { exact: true })
      .fill(process.env.ACTU_TEST_PASSWORD || "local-testing-only-2026");
    await page.getByRole("button", { name: "Se connecter" }).click();
    check(page.url().endsWith("/admin.php"), "Connexion");
    const session = await context.cookies();
    check(
      session.some(
        (c) => c.name === "actu_session" && c.httpOnly && c.sameSite === "Lax",
      ),
      "Cookie de session protégé",
    );
    await page.goto(base + "/alerts.php");
    await page.getByLabel("Nom de la veille").fill("Alerte Linux");
    await page.getByLabel("Mot-clé ou expression").fill("Linux");
    await page.getByRole("button", { name: "Créer la règle" }).click();
    check(
      (await page.locator(".notification").count()) >= 1,
      "Règle et notification",
    );
    await page.goto(base + "/manage.php");
    check(
      (await page
        .getByRole("heading", { name: "Sources configurées" })
        .count()) === 1,
      "Gestion des sources",
    );
    await page.getByLabel("Nom", { exact: true }).fill("Source de test");
    await page.getByLabel("URL publique du flux").fill("https://8.8.8.8/feed");
    await page.getByLabel("Source active").uncheck();
    await page
      .getByRole("button", { name: "Enregistrer", exact: true })
      .click();
    check(
      (await page.getByText("Source de test", { exact: true }).count()) === 1,
      "Ajout de source désactivée",
    );
    const row = page
      .locator("tr")
      .filter({ has: page.getByText("Source de test", { exact: true }) });
    await row.getByRole("link", { name: "Modifier", exact: true }).click();
    await page
      .getByLabel("Nom", { exact: true })
      .fill("Source de test modifiée");
    await page
      .getByRole("button", { name: "Enregistrer", exact: true })
      .click();
    check(
      (await page
        .getByText("Source de test modifiée", { exact: true })
        .count()) === 1,
      "Modification de source",
    );
    await page
      .getByLabel("Fichier OPML")
      .setInputFiles({
        name: "test.opml",
        mimeType: "text/xml",
        buffer: Buffer.from(
          '<opml><body><outline text="Flux OPML test" xmlUrl="https://example.org/opml-test"/></body></opml>',
        ),
      });
    await page.getByRole("button", { name: "Importer", exact: true }).click();
    check(
      (await page.getByText("Flux OPML test", { exact: true }).count()) === 1,
      "Import OPML dans l’administration",
    );
    const inactive = await page
      .getByRole("button", { name: "Activer", exact: true })
      .count();
    await page
      .getByRole("button", { name: "Désactiver", exact: true })
      .first()
      .click();
    check(
      (await page
        .getByRole("button", { name: "Activer", exact: true })
        .count()) ===
        inactive + 1,
      "Désactivation source",
    );
    const opml = await context.request.get(base + "/opml.php");
    check(
      opml.status() === 200 && (await opml.text()).includes("<opml"),
      "Export OPML authentifié",
    );
    await page.goto(base + "/analytics.php");
    check(
      (await page.locator(".chart-row").count()) > 0,
      "Statistiques agrégées",
    );
    await page.goto(base + "/index.php");
    await page.setViewportSize({ width: 390, height: 844 });
    check(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
      ),
      "Affichage mobile sans débordement",
    );
    await page.locator("#theme").click();
    check(
      (await page.locator("body").getAttribute("data-theme")) === "light",
      "Sélection du thème",
    );
    await page.locator("#theme").click();
    await page.reload();
    check(
      (await page.locator("body").getAttribute("data-theme")) === "dark",
      "Thème mémorisé",
    );
    await page.screenshot({ path: "/tmp/actu-mobile.png", fullPage: true });
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.screenshot({ path: "/tmp/actu-desktop.png", fullPage: true });
    await page
      .getByRole("button", { name: "Déconnexion", exact: true })
      .click();
    check(page.url().endsWith("/login.php"), "Déconnexion");
    const other = await browser.newContext();
    const p2 = await other.newPage();
    await p2.goto(base + "/favorites.php");
    check(
      (await p2.locator(".article-card").count()) === 0,
      "Favoris isolés entre navigateurs",
    );
    await other.close();
    await page.goto(base + "/privacy.php");
    await page
      .getByRole("button", { name: "Effacer mes données de lecture" })
      .click();
    await page.goto(base + "/favorites.php");
    check(
      (await page.locator(".article-card").count()) === 0,
      "Suppression des données personnelles",
    );
    check(errors.length === 0, "Aucune erreur JavaScript");
    console.log(`PASS : ${checks} parcours navigateur.`);
  } finally {
    await browser.close();
  }
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
