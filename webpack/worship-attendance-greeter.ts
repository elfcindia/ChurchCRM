/**
 * Greeter worship check-in: search, check in, quick visitor.
 */

import { buildAPIUrl, fetchAPIJSON } from "./api-utils";

type GreeterCfg = { defaultDate: string };

function getGreeterCfg(): GreeterCfg | undefined {
  const crm = (window as unknown as { CRM?: { worshipGreeter?: GreeterCfg } }).CRM;
  return crm?.worshipGreeter;
}

function t(msg: string): string {
  const i18n = (window as unknown as { i18next?: { t: (k: string) => string } }).i18next;
  return typeof i18n !== "undefined" ? i18n.t(msg) : msg;
}

function notify(msg: string, type: "success" | "danger" | "warning" | "info" = "info"): void {
  const crm = (window as unknown as { CRM?: { notify?: (m: string, o?: object) => void } }).CRM;
  if (crm?.notify) {
    crm.notify(msg, { type, delay: 4000 });
  } else {
    // eslint-disable-next-line no-alert
    alert(msg);
  }
}

function esc(s: string): string {
  const crm = (window as unknown as { CRM?: { escapeHtml?: (x: string) => string } }).CRM;
  if (crm?.escapeHtml) {
    return crm.escapeHtml(s);
  }
  const d = document.createElement("div");
  d.textContent = s;
  return d.innerHTML;
}

function getSelectedDate(): string {
  const el = document.getElementById("worshipGreeterDate") as HTMLInputElement | null;
  return el?.value || getGreeterCfg()?.defaultDate || "";
}

type SearchRow = {
  id: number;
  firstName: string;
  lastName: string;
  familyName: string;
  checkedIn: boolean;
  checkinTime: string | null;
};

async function runSearch(q: string): Promise<void> {
  const box = document.getElementById("worshipGreeterSearchResults");
  if (!box) {
    return;
  }
  if (q.length < 2) {
    box.innerHTML = "";
    return;
  }
  const date = getSelectedDate();
  try {
    const data = await fetchAPIJSON<{ results: SearchRow[] }>(
      `worship/attendance/search?q=${encodeURIComponent(q)}&date=${encodeURIComponent(date)}`,
    );
    box.innerHTML = "";
    if (!data.results?.length) {
      box.innerHTML = `<div class="list-group-item text-secondary">${esc(t("No matches"))}</div>`;
      return;
    }
    for (const r of data.results) {
      const item = document.createElement("button");
      item.type = "button";
      item.className = "list-group-item list-group-item-action d-flex justify-content-between align-items-center";
      const name = `${r.firstName} ${r.lastName}`.trim();
      const badge = r.checkedIn
        ? `<span class="badge bg-success">${esc(t("Checked in"))}</span>`
        : `<span class="badge bg-secondary">${esc(t("Tap to check in"))}</span>`;
      item.innerHTML = `<span><strong>${esc(name)}</strong><br><small class="text-secondary">${esc(r.familyName || "")}</small></span>${badge}`;
      item.addEventListener("click", () => {
        void checkInPerson(r.id, r.checkedIn);
      });
      box.appendChild(item);
    }
  } catch {
    notify(t("Search failed"), "danger");
  }
}

async function checkInPerson(personId: number, already: boolean): Promise<void> {
  if (already) {
    notify(t("Already checked in"), "info");
    return;
  }
  const date = getSelectedDate();
  try {
    const res = await fetch(buildAPIUrl("worship/attendance/checkin"), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ personId, date }),
      credentials: "same-origin",
    });
    const body = await res.json().catch(() => ({}));
    if (!res.ok) {
      const b = body as { error?: string; message?: string };
      notify(b.error || b.message || t("Check-in failed"), "danger");
      return;
    }
    notify(t("Checked in"), "success");
    await loadTodayList();
    const q = (document.getElementById("worshipGreeterSearch") as HTMLInputElement)?.value?.trim() || "";
    if (q.length >= 2) {
      await runSearch(q);
    }
  } catch {
    notify(t("Check-in failed"), "danger");
  }
}

async function loadTodayList(): Promise<void> {
  const ul = document.getElementById("worshipGreeterTodayList");
  if (!ul) {
    return;
  }
  const date = getSelectedDate();
  try {
    const data = await fetchAPIJSON<{
      attended: { personId: number; firstName: string; lastName: string; familyName: string; checkinTime: string }[];
    }>(`worship/attendance/attended?date=${encodeURIComponent(date)}`);
    ul.innerHTML = "";
    if (!data.attended?.length) {
      ul.innerHTML = `<li class="list-group-item text-secondary">${esc(t("No check-ins yet"))}</li>`;
      return;
    }
    for (const a of data.attended) {
      const li = document.createElement("li");
      li.className = "list-group-item d-flex justify-content-between align-items-start";
      const name = `${a.firstName} ${a.lastName}`.trim();
      li.innerHTML = `<div><div class="fw-semibold">${esc(name)}</div><small class="text-secondary">${esc(a.familyName || "")}</small></div><small class="text-muted">${esc(a.checkinTime || "")}</small>`;
      ul.appendChild(li);
    }
  } catch {
    ul.innerHTML = `<li class="list-group-item text-danger">${esc(t("Could not load list"))}</li>`;
  }
}

function debounce<T extends (...a: unknown[]) => void>(fn: T, ms: number): (...args: Parameters<T>) => void {
  let id: ReturnType<typeof setTimeout> | undefined;
  return (...args: Parameters<T>) => {
    if (id) {
      clearTimeout(id);
    }
    id = setTimeout(() => {
      fn(...args);
    }, ms);
  };
}

async function submitQuickVisitor(): Promise<void> {
  const first = (document.getElementById("wqFirst") as HTMLInputElement)?.value?.trim() || "";
  const last = (document.getElementById("wqLast") as HTMLInputElement)?.value?.trim() || "";
  const cell = (document.getElementById("wqCell") as HTMLInputElement)?.value?.trim() || "";
  const fam = (document.getElementById("wqFam") as HTMLInputElement)?.value?.trim() || "";
  const date = getSelectedDate();
  if (first.length < 2 || last.length < 2) {
    notify(t("First and last name must be at least 2 characters"), "warning");
    return;
  }
  try {
    const res = await fetch(buildAPIUrl("worship/attendance/quick-person"), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        firstName: first,
        lastName: last,
        cellPhone: cell || undefined,
        familyName: fam || undefined,
        date,
      }),
      credentials: "same-origin",
    });
    const body = await res.json().catch(() => ({}));
    if (!res.ok) {
      const b = body as { error?: string; message?: string };
      notify(b.error || b.message || t("Could not create person"), "danger");
      return;
    }
    notify(t("Visitor saved and checked in"), "success");
    const modalEl = document.getElementById("worshipQuickVisitorModal");
    if (modalEl) {
      const bs = (
        window as unknown as { bootstrap?: { Modal?: { getOrCreateInstance: (el: Element) => { hide: () => void } } } }
      ).bootstrap;
      bs?.Modal?.getOrCreateInstance(modalEl).hide();
    }
    (document.getElementById("wqFirst") as HTMLInputElement).value = "";
    (document.getElementById("wqLast") as HTMLInputElement).value = "";
    (document.getElementById("wqCell") as HTMLInputElement).value = "";
    (document.getElementById("wqFam") as HTMLInputElement).value = "";
    await loadTodayList();
  } catch {
    notify(t("Could not create person"), "danger");
  }
}

function init(): void {
  const search = document.getElementById("worshipGreeterSearch") as HTMLInputElement | null;
  const dateEl = document.getElementById("worshipGreeterDate") as HTMLInputElement | null;
  const datePickerBtn = document.getElementById("worshipGreeterDatePickerBtn");
  datePickerBtn?.addEventListener("click", () => {
    (dateEl as (HTMLInputElement & { showPicker?: () => void }) | null)?.showPicker?.();
  });
  if (search) {
    const debounced = debounce(() => {
      void runSearch(search.value.trim());
    }, 280);
    search.addEventListener("input", debounced);
  }
  if (dateEl) {
    dateEl.addEventListener("change", () => {
      void loadTodayList();
      const q = search?.value?.trim() || "";
      if (q.length >= 2) {
        void runSearch(q);
      }
    });
  }
  document.getElementById("wqSubmit")?.addEventListener("click", () => {
    void submitQuickVisitor();
  });
  void loadTodayList();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init);
} else {
  init();
}
