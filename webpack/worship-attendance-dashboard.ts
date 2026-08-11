/**
 * Worship attendance dashboard: stats, tables, CSV export.
 */

import { buildAPIUrl, fetchAPIJSON } from "./api-utils";

type DashCfg = { defaultDate: string };

function getDashCfg(): DashCfg | undefined {
  const crm = (window as unknown as { CRM?: { worshipDashboard?: DashCfg } }).CRM;
  return crm?.worshipDashboard;
}

function t(msg: string): string {
  const i18n = (window as unknown as { i18next?: { t: (k: string) => string } }).i18next;
  return typeof i18n !== "undefined" ? i18n.t(msg) : msg;
}

function notify(msg: string, type: "success" | "danger" | "warning" | "info" = "info"): void {
  const crm = (window as unknown as { CRM?: { notify?: (m: string, o?: object) => void } }).CRM;
  if (crm?.notify) {
    crm.notify(msg, { type, delay: 5000 });
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

function getDate(): string {
  const el = document.getElementById("worshipDashDate") as HTMLInputElement | null;
  return el?.value || getDashCfg()?.defaultDate || "";
}

type Summary = {
  date: string;
  expectedTotal: number;
  attendedExpected: number;
  absentTotal: number;
  guestAttended: number;
  attendedAll: number;
};

async function refreshAll(): Promise<void> {
  const date = getDate();
  const statsEl = document.getElementById("worshipDashStats");
  const tbodyA = document.querySelector("#worshipDashAttendedTable tbody");
  const tbodyB = document.querySelector("#worshipDashAbsentTable tbody");
  const exportLink = document.getElementById("worshipDashExport") as HTMLAnchorElement | null;
  if (exportLink) {
    exportLink.href = `${buildAPIUrl(`worship/attendance/export-absentees.csv?date=${encodeURIComponent(date)}`)}`;
  }
  const exportPdfLink = document.getElementById("worshipDashExportPdf") as HTMLAnchorElement | null;
  if (exportPdfLink) {
    exportPdfLink.href = `${buildAPIUrl(`worship/attendance/export-absentees.pdf?date=${encodeURIComponent(date)}`)}`;
  }
  try {
    const summary = await fetchAPIJSON<Summary>(`worship/attendance/summary?date=${encodeURIComponent(date)}`);
    if (statsEl) {
      statsEl.innerHTML = `
        <div class="col-6 col-md-4 col-lg">
          <div class="card card-sm"><div class="card-body">
            <div class="text-secondary small">${esc(t("Expected"))}</div>
            <div class="fs-3 fw-semibold">${summary.expectedTotal}</div>
          </div></div>
        </div>
        <div class="col-6 col-md-4 col-lg">
          <div class="card card-sm"><div class="card-body">
            <div class="text-secondary small">${esc(t("Checked in (expected)"))}</div>
            <div class="fs-3 fw-semibold text-success">${summary.attendedExpected}</div>
          </div></div>
        </div>
        <div class="col-6 col-md-4 col-lg">
          <div class="card card-sm"><div class="card-body">
            <div class="text-secondary small">${esc(t("Absent"))}</div>
            <div class="fs-3 fw-semibold text-warning">${summary.absentTotal}</div>
          </div></div>
        </div>
        <div class="col-6 col-md-4 col-lg">
          <div class="card card-sm"><div class="card-body">
            <div class="text-secondary small">${esc(t("Guests (check-in)"))}</div>
            <div class="fs-3 fw-semibold text-azure">${summary.guestAttended}</div>
          </div></div>
        </div>
        <div class="col-6 col-md-4 col-lg">
          <div class="card card-sm"><div class="card-body">
            <div class="text-secondary small">${esc(t("Total check-ins"))}</div>
            <div class="fs-3 fw-semibold">${summary.attendedAll}</div>
          </div></div>
        </div>`;
    }

    const attended = await fetchAPIJSON<{
      attended: { personId: number; firstName: string; lastName: string; familyName: string; checkinTime: string }[];
    }>(`worship/attendance/attended?date=${encodeURIComponent(date)}`);
    if (tbodyA) {
      tbodyA.innerHTML = "";
      for (const a of attended.attended || []) {
        const tr = document.createElement("tr");
        const name = `${a.firstName} ${a.lastName}`.trim();
        tr.innerHTML = `<td>${esc(name)}</td><td>${esc(a.familyName || "")}</td><td>${esc(a.checkinTime || "")}</td>`;
        tbodyA.appendChild(tr);
      }
      if (!attended.attended?.length) {
        tbodyA.innerHTML = `<tr><td colspan="3" class="text-secondary">${esc(t("None"))}</td></tr>`;
      }
    }

    const absent = await fetchAPIJSON<{
      absent: {
        personId: number;
        firstName: string;
        lastName: string;
        cellPhone: string;
        homePhone: string;
        familyName: string;
      }[];
    }>(`worship/attendance/absent?date=${encodeURIComponent(date)}`);
    if (tbodyB) {
      tbodyB.innerHTML = "";
      for (const b of absent.absent || []) {
        const tr = document.createElement("tr");
        const name = `${b.firstName} ${b.lastName}`.trim();
        tr.innerHTML = `<td>${esc(name)}<br><small class="text-secondary">${esc(b.familyName || "")}</small></td><td>${esc(b.cellPhone)}</td><td>${esc(b.homePhone)}</td>`;
        tbodyB.appendChild(tr);
      }
      if (!absent.absent?.length) {
        tbodyB.innerHTML = `<tr><td colspan="3" class="text-secondary">${esc(t("None"))}</td></tr>`;
      }
    }
  } catch {
    notify(t("Could not load dashboard"), "danger");
  }
}

function init(): void {
  document.getElementById("worshipDashRefresh")?.addEventListener("click", () => {
    void refreshAll();
  });
  document.getElementById("worshipDashDate")?.addEventListener("change", () => {
    void refreshAll();
  });
  document.getElementById("worshipDashDatePickerBtn")?.addEventListener("click", () => {
    const dateEl = document.getElementById("worshipDashDate") as
      | (HTMLInputElement & { showPicker?: () => void })
      | null;
    dateEl?.showPicker?.();
  });
  void refreshAll();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init);
} else {
  init();
}
