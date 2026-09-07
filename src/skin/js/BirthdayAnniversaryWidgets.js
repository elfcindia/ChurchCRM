/**
 * Upcoming Birthdays & Anniversaries widgets (DataTables against
 * #PersonBirthdayDashboardItem / #FamiliesWithAnniversariesDashboardItem).
 *
 * Shared between the main dashboard (webpack/root-dashboard.js) and the
 * Calendar page (webpack/event-calendars.js) so both pages render an
 * identical widget without duplicating the DataTables config.
 */

// Helper to generate Tabler simple avatar with initials (not clickable).
// Also used by MainDashboard.js for its Families/People tables.
export function generateTablerAvatar(name, id, type = "person") {
  const parts = name.trim().split(/\s+/);
  let initials = "";
  if (parts.length >= 2) {
    initials = (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  } else if (parts.length === 1) {
    initials = parts[0].substring(0, 2).toUpperCase();
  }

  // Deterministic color based on name
  const colors = ["#667eea", "#764ba2", "#f093fb", "#4facfe", "#00f2fe", "#43e97b", "#fa709a", "#fee140"];
  const hash = name.split("").reduce((acc, char) => acc + char.charCodeAt(0), 0);
  const color = colors[hash % colors.length];

  return `<span class="avatar avatar-sm rounded-circle" style="background-color: ${color};"><span class="avatar-title fs-6 fw-bold">${initials}</span></span>`;
}

// Helper to generate clickable photo img when row.HasPhoto === true.
// Also used by MainDashboard.js for its Families/People tables.
export function generatePhotoImg(id, type) {
  const viewClass = type === "person" ? "view-person-photo" : "view-family-photo";
  const dataIdAttr = type === "person" ? `data-person-id="${id}"` : `data-family-id="${id}"`;
  const photoUrl = window.CRM.root + `/api/${type}/${id}/photo`;
  return `<img class="avatar avatar-sm rounded-circle ${viewClass}" src="${photoUrl}" ${dataIdAttr} alt="" style="cursor: pointer; object-fit: cover;" title="${i18next.t("View Photo")}" />`;
}

const dataTableDashboardDefaults = {
  paging: false,
  ordering: false,
  info: false,
  layout: {
    topStart: null,
    topEnd: null,
    bottomStart: null,
    bottomEnd: null,
  },
};

export function initializeBirthdayAnniversaryWidgets() {
  // Use the global jQuery which has DataTables and other plugins attached.
  const $ = window.jQuery;

  if (!$ || !$.extend) {
    console.error(
      "jQuery with plugins not available - skin-main.js may not have loaded yet. Birthday/Anniversary widget initialization deferred.",
    );
    setTimeout(() => initializeBirthdayAnniversaryWidgets(), 500);
    return;
  }

  // Not birthday/anniversary-specific, but small enough to duplicate here
  // rather than pull in a bigger shared module just for this.
  function syncCartButtons() {
    if (window.CRM && window.CRM.cartManager) {
      Promise.all([
        window.CRM.APIRequest({
          method: "GET",
          path: "cart/",
          suppressErrorDialog: true,
        }),
        window.CRM.APIRequest({
          method: "GET",
          path: "families/familiesInCart",
          suppressErrorDialog: true,
        }),
      ]).then((responses) => {
        const cartData = responses[0];
        const familiesData = responses[1];

        const peopleInCart = cartData.PeopleCart || [];
        const familiesInCart = familiesData.familiesInCart || [];
        const groupsInCart = cartData.GroupCart || [];

        window.CRM.cartManager.syncButtonStates(peopleInCart, familiesInCart, groupsInCart);
      });
    }
  }

  if ($("#PersonBirthdayDashboardItem").length > 0) {
    let dataTableConfig = {
      ajax: {
        url: window.CRM.root + "/api/persons/birthday",
        dataSrc: (json) => {
          if (!json.people || json.people.length === 0) {
            $("#PersonBirthdayDashboardItem")
              .closest(".card-body")
              .html(
                '<div class="empty py-4">' +
                  '<div class="empty-icon"><i class="fa-solid fa-cake-candles fa-2x text-muted"></i></div>' +
                  '<p class="empty-title">' +
                  i18next.t("No Birthdays") +
                  "</p>" +
                  '<p class="empty-subtitle text-muted">' +
                  i18next.t("No birthdays in the past or next 7 days") +
                  "</p>" +
                  "</div>",
              );
            return [];
          }
          return json.people;
        },
      },
      columns: [
        {
          width: "40px",
          title: "",
          data: "PersonId",
          orderable: false,
          className: "text-center",
          render: (data, type, row) => "",
        },
        {
          width: "60%",
          title: i18next.t("Name"),
          data: "FirstName",
          render: (data, type, row) => {
            if (type !== "display") return row.FormattedName || "";
            // The Birthdays widget lives in a narrow sidebar column at every
            // breakpoint (mobile, tablet, and desktop xl-sidebar). Renders MUST
            // tolerate ~120px of name space without breaking layout.
            //
            // Use the API's NumericAge + AgeUnit pair (added by people-persons
            // birthdays endpoint) so we can render a compact, locale-aware,
            // plural-aware label on its own line below the name. This handles
            // infants ("6 mo") and singular vs plural ("1 yr" vs "66 yrs"); the
            // legacy `row.Age` localized string is kept as a fallback for safety.
            let ageText = "";
            if (typeof row.NumericAge === "number" && row.AgeUnit) {
              let unit;
              if (row.AgeUnit === "month") {
                unit = row.NumericAge === 1 ? i18next.t("mo") : i18next.t("mos");
              } else {
                unit = row.NumericAge === 1 ? i18next.t("yr") : i18next.t("yrs");
              }
              ageText = '<div class="text-muted small lh-1 mt-1">' + row.NumericAge + " " + unit + "</div>";
            } else if (row.Age) {
              // Fallback: server didn't send NumericAge — render the localized
              // long string verbatim. Escape it via jQuery .text() before
              // injecting to avoid XSS.
              const safeAge = $("<div>").text(String(row.Age)).html();
              ageText = '<div class="text-muted small lh-1 mt-1">' + safeAge + "</div>";
            }
            // Show photo if available, otherwise show Tabler avatar with initials
            const photoIcon = row.HasPhoto
              ? generatePhotoImg(row.PersonId, "person")
              : generateTablerAvatar(row.FormattedName, row.PersonId, "person");
            return (
              '<div class="d-flex align-items-center gap-2">' +
              photoIcon +
              '<div class="min-w-0 flex-grow-1"><a class="text-break" href="' +
              window.CRM.root +
              "/PersonView.php?PersonID=" +
              row.PersonId +
              '"><strong>' +
              row.FormattedName +
              "</strong></a>" +
              ageText +
              "</div></div>"
            );
          },
        },
        {
          width: "35%",
          title: i18next.t("Birthday"),
          data: "DaysUntil",
          render: (data, type, row) => {
            if (type !== "display") return row.Birthday || "";
            if (row.Birthday === undefined) return "";
            const diff = row.DaysUntil;

            let badge = "";
            if (diff === 0) {
              badge = '<span class="badge bg-success-lt text-success">' + i18next.t("Today") + "!</span>";
            } else if (diff > 0) {
              badge =
                '<span class="badge bg-info-lt text-info">' +
                i18next.t("in") +
                " " +
                diff +
                " " +
                (diff === 1 ? i18next.t("day") : i18next.t("days")) +
                "</span>";
            } else {
              badge =
                '<span class="badge bg-secondary-lt text-secondary">' +
                Math.abs(diff) +
                " " +
                (Math.abs(diff) === 1 ? i18next.t("day") : i18next.t("days")) +
                " " +
                i18next.t("ago") +
                "</span>";
            }
            return row.Birthday + " " + badge;
          },
        },
        {
          width: "25%",
          title: i18next.t("Phone"),
          data: "Phone",
          render: (data) => (data ? '<a href="tel:' + encodeURIComponent(data) + '">' + data + "</a>" : ""),
        },
      ],
      // Paginate birthdays after 5 items
      paging: true,
      pageLength: 5,
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $.extend(dataTableConfig, dataTableDashboardDefaults);
    // Ensure paging settings aren't overridden by dashboard defaults
    dataTableConfig.paging = true;
    dataTableConfig.pageLength = 5;
    // Include pagination control in DOM (dashboard defaults remove it), plus
    // a compact buttons toolbar (Copy / Print-to-PDF) covering the FULL list,
    // not just the currently visible page.
    dataTableConfig.dom = "<'row'<'col-sm-12 d-flex justify-content-between align-items-center'B>><'row'<'col-sm-12'tr>><'row'<'col-sm-12'p>>";
    dataTableConfig.buttons = [
      {
        extend: "copyHtml5",
        text: '<i class="ti ti-copy"></i>',
        titleAttr: i18next.t("Copy to Clipboard"),
        title: i18next.t("Birthdays"),
        exportOptions: { columns: [1, 2, 3], modifier: { page: "all" } },
      },
      {
        extend: "print",
        text: '<i class="ti ti-printer"></i>',
        titleAttr: i18next.t("Print / Save as PDF"),
        title: i18next.t("Birthdays"),
        exportOptions: { columns: [1, 2, 3], modifier: { page: "all" } },
      },
    ];
    const birthdayPersonTable = $("#PersonBirthdayDashboardItem").DataTable(dataTableConfig);
    birthdayPersonTable.on("draw", () => {
      syncCartButtons();
      // Refresh image loader for dynamically added photos
      if (window.CRM && window.CRM.peopleImageLoader) {
        window.CRM.peopleImageLoader.refresh();
      }
    });
  }

  if ($("#FamiliesWithAnniversariesDashboardItem").length > 0) {
    let dataTableConfig = {
      ajax: {
        url: window.CRM.root + "/api/families/anniversaries",
        dataSrc: (json) => {
          if (!json.families || json.families.length === 0) {
            $("#FamiliesWithAnniversariesDashboardItem")
              .closest(".card-body")
              .html(
                '<div class="empty py-4">' +
                  '<div class="empty-icon"><i class="fa-solid fa-heart fa-2x text-muted"></i></div>' +
                  '<p class="empty-title">' +
                  i18next.t("No Anniversaries") +
                  "</p>" +
                  '<p class="empty-subtitle text-muted">' +
                  i18next.t("No anniversaries in the past or next 7 days") +
                  "</p>" +
                  "</div>",
              );
            return [];
          }
          return json.families;
        },
      },
      columns: [
        {
          width: "40%",
          title: i18next.t("Name"),
          data: "Name",
          render: (data, type, row) => {
            if (type !== "display") return data || "";
            // Anniversaries widget shares the narrow sidebar column with the
            // Birthdays widget — keep the markup symmetrical (gap-2, min-w-0,
            // text-break) so long family names wrap cleanly at every breakpoint.
            const photoIcon = row.HasPhoto
              ? generatePhotoImg(row.FamilyId, "family")
              : generateTablerAvatar(data, row.FamilyId, "family");
            return (
              '<div class="d-flex align-items-center gap-2">' +
              photoIcon +
              '<div class="min-w-0 flex-grow-1"><a class="text-break" href="' +
              window.CRM.root +
              "/v2/family/" +
              row.FamilyId +
              '"><strong>' +
              data +
              "</strong></a></div></div>"
            );
          },
        },
        {
          width: "35%",
          title: i18next.t("Anniversary"),
          data: "WeddingDate",
          render: (data, type, row) => {
            if (type !== "display") return data || "";
            if (!data) return "";
            const weddingDate = moment(data, ["MMMM D, YYYY", "MMMM D", "MM-DD-YYYY"]);
            const thisYear = moment().year();
            const anniversaryThisYear = weddingDate.clone().year(thisYear);
            const today = moment().startOf("day");
            const diff = anniversaryThisYear.diff(today, "days");
            const years = thisYear - weddingDate.year();

            let badge = "";
            if (diff === 0) {
              badge =
                '<span class="badge bg-success-lt text-success ms-2">' +
                years +
                " " +
                i18next.t("years") +
                " " +
                i18next.t("Today") +
                "!</span>";
            } else if (diff > 0) {
              badge =
                '<span class="badge bg-info-lt text-info ms-2">' +
                i18next.t("in") +
                " " +
                diff +
                " " +
                (diff === 1 ? i18next.t("day") : i18next.t("days")) +
                "</span>";
            } else {
              badge =
                '<span class="badge bg-secondary-lt text-secondary ms-2">' +
                Math.abs(diff) +
                " " +
                (Math.abs(diff) === 1 ? i18next.t("day") : i18next.t("days")) +
                " " +
                i18next.t("ago") +
                "</span>";
            }
            return data + badge;
          },
        },
        {
          width: "25%",
          title: i18next.t("Phone"),
          data: "Phone",
          render: (data) => (data ? '<a href="tel:' + encodeURIComponent(data) + '">' + data + "</a>" : ""),
        },
      ],
      // Paginate anniversaries after 5 items
      paging: true,
      pageLength: 5,
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $.extend(dataTableConfig, dataTableDashboardDefaults);
    // Ensure paging settings aren't overridden by dashboard defaults
    dataTableConfig.paging = true;
    dataTableConfig.pageLength = 5;
    // Include pagination control in DOM (dashboard defaults remove it), plus
    // a compact buttons toolbar (Copy / Print-to-PDF) covering the FULL list,
    // not just the currently visible page.
    dataTableConfig.dom = "<'row'<'col-sm-12 d-flex justify-content-between align-items-center'B>><'row'<'col-sm-12'tr>><'row'<'col-sm-12'p>>";
    dataTableConfig.buttons = [
      {
        extend: "copyHtml5",
        text: '<i class="ti ti-copy"></i>',
        titleAttr: i18next.t("Copy to Clipboard"),
        title: i18next.t("Anniversaries"),
        exportOptions: { columns: [0, 1, 2], modifier: { page: "all" } },
      },
      {
        extend: "print",
        text: '<i class="ti ti-printer"></i>',
        titleAttr: i18next.t("Print / Save as PDF"),
        title: i18next.t("Anniversaries"),
        exportOptions: { columns: [0, 1, 2], modifier: { page: "all" } },
      },
    ];
    const anniversaryFamiliesTable = $("#FamiliesWithAnniversariesDashboardItem").DataTable(dataTableConfig);
    anniversaryFamiliesTable.on("draw", () => {
      syncCartButtons();
      if (window.CRM && window.CRM.peopleImageLoader) {
        window.CRM.peopleImageLoader.refresh();
      }
    });
  }
}
