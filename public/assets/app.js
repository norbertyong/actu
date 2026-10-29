"use strict";
(() => {
  const button = document.querySelector("#theme");
  const systemDark = window.matchMedia("(prefers-color-scheme: dark)");
  let preference = "system";
  try {
    preference = localStorage.getItem("actu_theme") || "system";
  } catch (_) {}
  const apply = () => {
    const dark =
      preference === "dark" || (preference === "system" && systemDark.matches);
    document.body.dataset.theme = dark ? "dark" : "light";
    if (button) {
      button.textContent =
        preference === "system" ? "Thème auto" : dark ? "Sombre" : "Clair";
      button.setAttribute(
        "aria-label",
        `Thème ${preference === "system" ? "automatique" : dark ? "sombre" : "clair"}. Changer le thème.`,
      );
    }
  };
  button?.addEventListener("click", () => {
    preference =
      preference === "system"
        ? "light"
        : preference === "light"
          ? "dark"
          : "system";
    try {
      localStorage.setItem("actu_theme", preference);
    } catch (_) {}
    apply();
  });
  systemDark.addEventListener("change", apply);
  apply();
})();

const collectForm = document.querySelector('#web-collect');
collectForm?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const button = collectForm.querySelector('button');
  const status = document.querySelector('#collect-status');
  const results = document.querySelector('#collect-results');
  button.disabled = true; results.replaceChildren();
  let failures = 0;
  try {
    for (let i = 0; i < 150; i++) {
      status.textContent = 'Collecte en cours…';
      const response = await fetch(collectForm.action, {method:'POST', body:new FormData(collectForm), headers:{Accept:'application/json'}});
      if (!response.ok || response.redirected) throw new Error('Collecte interrompue. Vérifiez votre session et les diagnostics des sources.');
      const result = await response.json();
      if (result.done) {status.textContent = failures ? `Collecte terminée avec ${failures} source(s) en échec. Consultez Sources.` : 'Collecte terminée. Vous pouvez consulter les actualités.';return;}
      if (result.error) failures++;
      const item=document.createElement('li');
      item.textContent = result.source + ' : ' + (result.error || `${result.count} entrées traitées`);
      results.append(item);
    }
    status.textContent = 'Limite de ce passage atteinte. Relancez pour les sources restantes.';
  } catch (error) {status.textContent = error.message;}
  finally {button.disabled=false;}
});

// Normal POST retains the server-rendered error page when Groq is unavailable.
document.querySelector('.ai-generate')?.addEventListener('submit', (event) => {
  event.currentTarget.querySelector('button').disabled = true;
  event.currentTarget.querySelector('.ai-progress').textContent = ' Génération en cours… (40 secondes maximum)';
});
