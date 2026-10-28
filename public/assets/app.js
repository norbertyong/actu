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
