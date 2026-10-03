(() => {
  "use strict";
  const bell = document.querySelector(".tb-notifications");
  let offset = 0,
    busy = false,
    lastSuccess = null;
  let localPaused = false;
  try {
    localPaused = localStorage.getItem("tbp-counters-paused") === "1";
  } catch {}
  let globalEnabled =
      document.querySelector('script[src*="admin-monitoring.js"]')?.dataset
        .countersEnabled !== "0",
    timerInterval = null;
  const countersAllowed = () => globalEnabled && !localPaused;
  const configureCounters = () => {
    document.documentElement.classList.toggle(
      "tb-counters-off",
      !countersAllowed(),
    );
    document.querySelectorAll(".tb-counter-toggle").forEach((button) => {
      button.disabled = !globalEnabled;
      button.setAttribute("aria-pressed", String(localPaused));
      button.textContent = !globalEnabled
        ? "Compteurs désactivés dans les réglages système"
        : localPaused
          ? "Afficher les compteurs sur cet appareil"
          : "Masquer les compteurs sur cet appareil";
    });
    if (timerInterval) {
      clearInterval(timerInterval);
      timerInterval = null;
    }
    if (countersAllowed()) {
      tick();
      timerInterval = setInterval(tick, 1000);
    }
  };
  const duration = (seconds) => {
    seconds = Math.max(0, Math.floor(seconds));
    const h = Math.floor(seconds / 3600),
      m = Math.floor((seconds % 3600) / 60),
      s = seconds % 60;
    return (
      String(h).padStart(2, "0") +
      ":" +
      String(m).padStart(2, "0") +
      ":" +
      String(s).padStart(2, "0")
    );
  };
  const tick = () => {
    if (document.hidden || !countersAllowed()) return;
    const now = Date.now() / 1000 + offset;
    document.querySelectorAll(".tb-use-timer").forEach((el) => {
      const start = Number(el.dataset.start),
        due = Number(el.dataset.due),
        end = Number(el.dataset.end);
      if (!start) {
        el.textContent = "Début non confirmé";
        return;
      }
      const running = el.dataset.running === "1";
      const finish = running ? now : end || start;
      const elapsed = finish - start;
      el.replaceChildren();
      const value = document.createElement("strong");
      value.textContent = duration(elapsed) + " d’utilisation";
      el.append(value);
      if (due) {
        const remaining = due - finish,
          sub = document.createElement("span");
        sub.textContent =
          remaining >= 0
            ? "Durée restante : " + duration(remaining)
            : "Dépassement : " + duration(-remaining);
        sub.className = remaining < 0 ? "tb-overdue" : "";
        el.append(sub);
      }
      if (!running && !end) {
        const label = document.createElement("span");
        label.textContent = "État à vérifier";
        el.append(label);
      }
    });
  };
  configureCounters();
  document.querySelectorAll(".tb-counter-toggle").forEach((button) =>
    button.addEventListener("click", () => {
      localPaused = !localPaused;
      try {
        localStorage.setItem("tbp-counters-paused", localPaused ? "1" : "0");
      } catch {}
      configureCounters();
      if (bell) poll();
    }),
  );
  if (!bell) return;
  const status = (text) =>
    document
      .querySelectorAll(".tb-monitor-status")
      .forEach((el) => (el.textContent = text));
  const fetchJson = async (url, options = {}) => {
    const controller = new AbortController(),
      timeout = setTimeout(() => controller.abort(), 10000);
    try {
      const reply = await fetch(url, {
        ...options,
        signal: controller.signal,
        credentials: "same-origin",
        cache: "no-store",
      });
      if (
        !reply.ok ||
        !reply.headers.get("content-type")?.includes("application/json")
      )
        throw Error("Suivi indisponible");
      return await reply.json();
    } finally {
      clearTimeout(timeout);
    }
  };
  const render = (data) => {
    if (
      typeof data.countersEnabled === "boolean" &&
      globalEnabled !== data.countersEnabled
    ) {
      globalEnabled = data.countersEnabled;
      configureCounters();
    }
    offset = data.serverTime - Date.now() / 1000;
    (data.trackedRentals || []).forEach((r) =>
      document
        .querySelectorAll(".tb-use-timer[data-reference]")
        .forEach((el) => {
          if (el.dataset.reference !== r.reference) return;
          el.dataset.start = r.started_unix || 0;
          el.dataset.due = r.due_unix || 0;
          el.dataset.end = r.returned_unix || 0;
          el.dataset.running = r.status === "active" ? "1" : "0";
        }),
    );
    lastSuccess = new Date();
    status(
      "Dernière vérification à " +
        lastSuccess.toLocaleTimeString("fr-FR") +
        " · États vérifiés toutes les 45 s.",
    );
    const badge = bell.querySelector(".tb-notification-count");
    badge.textContent = data.notifications.unread;
    badge.hidden = !data.notifications.unread;
    const list = bell.querySelector(".tb-notification-list");
    list.replaceChildren();
    if (!data.notifications.items.length) {
      const p = document.createElement("p");
      p.textContent = "Aucune alerte active.";
      list.append(p);
    }
    data.notifications.items.forEach((item) => {
      const article = document.createElement("article");
      article.className =
        "tb-notification-item" + (Number(item.unread) ? " is-unread" : "");
      const link = document.createElement("a");
      if (item.link.startsWith("/admin/")) link.href = item.link;
      link.textContent = item.title;
      const message = document.createElement("p");
      message.textContent = item.message;
      const read = document.createElement("button");
      read.type = "button";
      read.className = "admin-ghost";
      const incident = String(item.id).startsWith("incident-");
      read.textContent = incident
        ? "Pris en compte"
        : Number(item.unread)
          ? "Marquer comme lu"
          : "Lu";
      read.disabled = !Number(item.unread);
      read.addEventListener("click", async () => {
        read.disabled = true;
        try {
          await fetchJson("/admin/notifications/read", {
            method: "POST",
            body: new URLSearchParams({ id: item.id, csrf: bell.dataset.csrf }),
          });
          article.classList.remove("is-unread");
          read.textContent = incident ? "Pris en compte" : "Lu";
          badge.textContent = Math.max(0, Number(badge.textContent) - 1);
          badge.hidden = Number(badge.textContent) === 0;
        } catch {
          read.disabled = false;
          status("Lecture non enregistrée. Réessayez.");
        }
      });
      article.append(link, message, read);
      list.append(article);
    });
    if (!data.snapshotUnavailable)
      document.querySelectorAll(".tb-active-rentals").forEach((list) => {
        list.replaceChildren();
        if (!data.activeRentals.length) {
          const p = document.createElement("p");
          p.className = "tb-empty";
          p.textContent = "Aucune location active.";
          list.append(p);
        }
        data.activeRentals.forEach((r) => {
          const card = document.createElement("article");
          card.className = "tb-battery-card";
          const link = document.createElement("a");
          link.href =
            "/admin/rentals/detail?reference=" +
            encodeURIComponent(r.reference);
          link.textContent = r.reference;
          const name = document.createElement("span");
          name.className = "tb-muted";
          name.textContent = r.customer_name + " · " + r.serial;
          const timer = document.createElement("div");
          timer.className = "tb-use-timer";
          timer.dataset.start = r.started_unix || 0;
          timer.dataset.due = r.due_unix || 0;
          timer.dataset.running = "1";
          card.append(link, name, timer);
          list.append(card);
        });
      });
    if (data.snapshotUnavailable)
      status(
        "Actualisation en cours sur un autre accès. Les compteurs conservent leur dernier état.",
      );
    else if (data.limited)
      status(
        "Affichage limité à 500 locations. Utilisez la page Locations pour rechercher une référence.",
      );
    tick();
  };
  const poll = async () => {
    if (busy || document.hidden) return;
    busy = true;
    try {
      const refs = [
        ...new Set(
          Array.from(
            document.querySelectorAll(".tb-use-timer[data-reference]"),
            (el) => el.dataset.reference,
          ),
        ),
      ].slice(0, 100);
      render(
        await fetchJson(
          "/admin/monitoring?" +
            new URLSearchParams({
              references: countersAllowed() ? refs.join(",") : "",
              counters: countersAllowed() ? "1" : "0",
            }),
        ),
      );
    } catch {
      status(
        "Suivi temporairement indisponible" +
          (lastSuccess
            ? " · Dernière vérification à " +
              lastSuccess.toLocaleTimeString("fr-FR")
            : "") +
          ". Les compteurs affichés utilisent le dernier état reçu.",
      );
    } finally {
      busy = false;
    }
  };
  poll();
  setInterval(poll, 45000);
  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) {
      tick();
      poll();
    }
  });
})();
