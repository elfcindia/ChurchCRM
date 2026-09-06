/**
 * Sunday School Class View — webpack entry
 * Handles DataTable initialization, ApexCharts birthday chart, and birthday filter.
 */

import ApexCharts from "apexcharts";
import "./groups-sundayschool-class-view.css";

document.addEventListener("DOMContentLoaded", () => {
  const dataTable = $(".data-table").DataTable(window.CRM.plugin.dataTable);

  // Birthday chart — data stored in data-chart attribute on the div element
  const chartElement = document.getElementById("bar-chart");
  if (!chartElement) return;

  const barData = JSON.parse(chartElement.dataset.chart || "[]");
  const barLabels = barData.map((d) => d[0]);
  const barValues = barData.map((d) => d[1]);
  const maxBarValue = barValues.length > 0 ? Math.max(...barValues) : 0;

  const barChartOptions = {
    chart: {
      type: "bar",
      height: 300,
      toolbar: {
        show: false,
      },
      events: {
        click: (event, chartContext, opts) => {
          if (opts.dataPointIndex !== undefined) {
            applyBirthdayFilter(barLabels[opts.dataPointIndex]);
          }
        },
      },
    },
    plotOptions: {
      bar: {
        borderRadius: 4,
        dataLabels: {
          position: "top",
        },
      },
    },
    series: [
      {
        name: chartElement.dataset.chartLabel || "Birthdays by Month",
        data: barValues,
      },
    ],
    xaxis: {
      categories: barLabels,
    },
    yaxis: {
      title: {
        text: i18next.t("Count"),
      },
      forceNiceScale: true,
    },
    dataLabels: {
      enabled: false,
    },
    states: {
      hover: {
        filter: {
          type: "darken",
          value: 0.15,
        },
      },
    },
  };

  const barChart = new ApexCharts(chartElement, barChartOptions);
  barChart.render();
  window.barChart = barChart;

  // Birthday filter — click bar → filter DataTable by that month
  const birthDayFilter = document.querySelector(".birthday-filter");
  const monthLabel = birthDayFilter?.querySelector(".month");

  if (!birthDayFilter || !monthLabel) return;

  function applyBirthdayFilter(month) {
    dataTable.column(0).search(month).draw();
    birthDayFilter.classList.remove("d-none");
    monthLabel.textContent = month;
  }

  function hideBirthdayFilter() {
    dataTable.column(0).search("").draw();
    birthDayFilter.classList.add("d-none");
  }

  birthDayFilter.querySelector("i.fa-times")?.addEventListener("click", hideBirthdayFilter);

  // Remove student from class
  $(document).on("click", ".remove-from-class", function () {
    const $btn = $(this);
    const groupId = $btn.data("group-id");
    const personId = $btn.data("person-id");
    const personName = $btn.data("person-name");

    bootbox.confirm({
      message: i18next.t("Remove") + " <strong>" + personName + "</strong> " + i18next.t("from this class?"),
      buttons: {
        confirm: { label: i18next.t("Remove"), className: "btn-warning" },
        cancel: { label: i18next.t("Cancel"), className: "btn-secondary" },
      },
      callback: (result) => {
        if (!result) return;

        $.ajax({
          method: "DELETE",
          url: `${window.CRM.root}/api/groups/${groupId}/removeperson/${personId}`,
        })
          .done(() => {
            window.CRM.notify(i18next.t("Person removed from class."), { type: "success", delay: 3000 });
            dataTable.row($btn.closest("tr")).remove().draw();
          })
          .fail(() => {
            window.CRM.notify(i18next.t("Failed to remove from class. Please try again."), {
              type: "danger",
              delay: 5000,
            });
          });
      },
    });
  });

  // ─── Add Student modal ────────────────────────────────────────────────
  const $addStudentModalEl = document.getElementById("addStudentModal");
  if ($addStudentModalEl) {
    // Family typeahead — same TomSelect pattern as .personSearch (GroupView.js),
    // pointed at the family search endpoint instead of the person one.
    const familySearchEl = document.getElementById("asFamilySearch");
    let familyTomSelect = null;
    if (familySearchEl && !familySearchEl.tomselect) {
      familyTomSelect = new TomSelect(familySearchEl, {
        valueField: "Id",
        labelField: "displayName",
        searchField: "displayName",
        load: (query, callback) => {
          if (query.length < 2) return callback();
          fetch(`${window.CRM.root}/api/families/search/${encodeURIComponent(query)}`)
            .then((res) => res.json())
            .then((data) => callback(data.Families || []))
            .catch(() => callback());
        },
      });
    }

    const newFamilyToggle = document.getElementById("asNewFamilyToggle");
    const newFamilyNameInput = document.getElementById("asNewFamilyName");
    // TomSelect replaces #asFamilySearch with a sibling .ts-wrapper element
    // and hides the original <select> itself — toggle that sibling, not an
    // ancestor (there isn't one).
    const familyWrapperEl = familySearchEl?.parentElement.querySelector(".ts-wrapper");
    newFamilyToggle?.addEventListener("change", function () {
      if (this.checked) {
        familyTomSelect?.disable();
        familyWrapperEl?.classList.add("d-none");
        newFamilyNameInput.classList.remove("d-none");
      } else {
        familyTomSelect?.enable();
        familyWrapperEl?.classList.remove("d-none");
        newFamilyNameInput.classList.add("d-none");
      }
    });

    document.getElementById("addStudentForm")?.addEventListener("submit", (e) => {
      e.preventDefault();
      const $errorBox = $("#addStudentError").addClass("d-none");
      const groupId = parseInt(document.getElementById("asClass").value, 10);
      const firstName = document.getElementById("asFirstName").value.trim();
      const lastName = document.getElementById("asLastName").value.trim();
      const isNewFamily = newFamilyToggle.checked;
      const familyId = isNewFamily ? -1 : parseInt(familyTomSelect?.getValue() || "0", 10);

      if (firstName.length < 2 || lastName.length < 2) {
        $errorBox.text(i18next.t("First and last name must be at least 2 characters")).removeClass("d-none");
        return;
      }
      if (!isNewFamily && !familyId) {
        $errorBox
          .text(i18next.t("Please select an existing family or choose to create a new one"))
          .removeClass("d-none");
        return;
      }

      const payload = {
        firstName: firstName,
        lastName: lastName,
        gender: parseInt(document.getElementById("asGender").value, 10),
        birthMonth: document.getElementById("asBirthMonth").value || undefined,
        birthDay: document.getElementById("asBirthDay").value || undefined,
        birthYear: document.getElementById("asBirthYear").value || undefined,
        familyId: familyId,
        newFamilyName: isNewFamily ? newFamilyNameInput.value.trim() : undefined,
        teacherId: document.getElementById("asTeacher").value || undefined,
      };

      const $submitBtn = $("#addStudentSubmit").prop("disabled", true);

      window.CRM.APIRequest({
        method: "POST",
        path: `groups/${groupId}/add-student`,
        data: JSON.stringify(payload),
      })
        .done(() => {
          window.CRM.notify(i18next.t("Student added."), { type: "success", delay: 3000 });
          window.location.reload();
        })
        .fail((jqXHR) => {
          const msg = (jqXHR.responseJSON && jqXHR.responseJSON.message) || i18next.t("Could not create student");
          $errorBox.text(msg).removeClass("d-none");
          $submitBtn.prop("disabled", false);
        });
    });
  }

  // ─── Add Teacher modal ─────────────────────────────────────────────────
  // Reuses the existing .personSearch → window.CRM.groups.addPerson flow
  // (same pattern as GroupView.js), but the Teacher role is implied by
  // context here, so we skip the generic role-select modal.
  const atPersonSearchEl = document.getElementById("atPersonSearch");
  if (atPersonSearchEl && !atPersonSearchEl.tomselect) {
    new TomSelect(atPersonSearchEl, {
      valueField: "objid",
      labelField: "text",
      searchField: "text",
      load: (query, callback) => {
        if (query.length < 2) return callback();
        fetch(`${window.CRM.root}/api/persons/search/${encodeURIComponent(query)}`)
          .then((res) => res.json())
          .then((data) => callback(data))
          .catch(() => callback());
      },
      onChange: (personId) => {
        if (!personId) return;
        window.CRM.groups
          .getRoles(window.CRM.currentGroup)
          .done((roles) => {
            const teacherRole = roles.find((r) => r.OptionName === "Teacher");
            if (!teacherRole) {
              window.CRM.notify(i18next.t("This class has no Teacher role defined"), { type: "danger", delay: 5000 });
              return;
            }
            window.CRM.groups
              .addPerson(window.CRM.currentGroup, personId, teacherRole.OptionId)
              .done(() => {
                window.CRM.notify(i18next.t("Teacher added."), { type: "success", delay: 3000 });
                window.location.reload();
              })
              .fail(() => {
                window.CRM.notify(i18next.t("Failed to add teacher. Please try again."), {
                  type: "danger",
                  delay: 5000,
                });
              });
          })
          .fail(() => {
            window.CRM.notify(i18next.t("Failed to add teacher. Please try again."), { type: "danger", delay: 5000 });
          });
      },
    });
  }

  // ─── Assign Teacher (existing students) ────────────────────────────────
  $(document).on("click", ".assign-teacher-btn", function () {
    const $btn = $(this);
    const personId = $btn.data("person-id");
    const personName = $btn.data("person-name");
    const currentTeacherId = String($btn.data("teacher-id") || "");

    const teacherOptions = $(".ss-member[data-role='Teacher']")
      .map(function () {
        return {
          id: String($(this).data("person-id")),
          name: $(this).find("strong").first().text().trim(),
        };
      })
      .get();

    if (teacherOptions.length === 0) {
      window.CRM.notify(i18next.t("Add a teacher to this class first."), { type: "warning", delay: 4000 });
      return;
    }

    let optionsHtml = '<option value="">' + i18next.t("Unassigned") + "</option>";
    teacherOptions.forEach((t) => {
      optionsHtml +=
        '<option value="' + t.id + '"' + (t.id === currentTeacherId ? " selected" : "") + ">" + t.name + "</option>";
    });

    bootbox.dialog({
      title: i18next.t("Assign Teacher") + ": " + personName,
      message: '<select id="assignTeacherSelect" class="form-select">' + optionsHtml + "</select>",
      buttons: {
        cancel: { label: i18next.t("Cancel"), className: "btn-secondary" },
        confirm: {
          label: i18next.t("Save"),
          className: "btn-primary",
          callback: () => {
            const teacherId = document.getElementById("assignTeacherSelect").value;
            const groupId = window.CRM.currentGroup;
            window.CRM.APIRequest({
              method: "POST",
              path: `groups/${groupId}/sundayschool/students/${personId}/teacher`,
              data: JSON.stringify({ teacherId: teacherId || null }),
            })
              .done(() => {
                const teacherName = teacherId ? teacherOptions.find((t) => t.id === teacherId)?.name : null;
                const $cell = $btn.closest(".assigned-teacher-cell");
                $cell
                  .find(".assigned-teacher-name")
                  .text(teacherName || "—")
                  .toggleClass("text-muted", !teacherName);
                $btn.data("teacher-id", teacherId || 0);
                window.CRM.notify(i18next.t("Teacher assignment saved."), { type: "success", delay: 3000 });
              })
              .fail(() => {
                window.CRM.notify(i18next.t("Failed to save. Please try again."), { type: "danger", delay: 5000 });
              });
          },
        },
      },
    });
  });

  // ─── Quick Create Today's Event button ───────────────────────────────
  // One-click creates a Sunday School event for today, auto-linking the
  // current class group so a Kiosk can pull the roster. Backed by
  // POST /api/events/quick-create which is idempotent (returns existing
  // event if one already exists for the same type+date).
  $(document).on("click", "#quickCreateTodaysEventBtn", function () {
    const $btn = $(this);
    const groupId = parseInt($btn.data("group-id"), 10);
    if (!groupId) return;

    $btn
      .prop("disabled", true)
      .html('<span class="spinner-border spinner-border-sm me-1"></span>' + i18next.t("Creating..."));

    window.CRM.APIRequest({
      method: "POST",
      path: "events/quick-create",
      data: JSON.stringify({ groupId: groupId }),
    })
      .done((resp) => {
        const eventId = resp && resp.eventId;
        const wasCreated = resp && resp.created;
        if (!eventId) {
          window.CRM.notify(i18next.t("Failed to create event. Please try again."), {
            type: "danger",
            delay: 5000,
          });
          $btn.prop("disabled", false).html('<i class="ti ti-plus me-1"></i>' + i18next.t("Create Today's Event"));
          return;
        }
        window.CRM.notify(
          wasCreated
            ? i18next.t("Event created. Redirecting to check-in...")
            : i18next.t("Event already exists. Redirecting to check-in..."),
          { type: "success", delay: 2500 },
        );
        // Land directly on the check-in page so the volunteer can take attendance
        setTimeout(() => {
          window.location.href = `${window.CRM.root}/event/checkin/${eventId}`;
        }, 600);
      })
      .fail((jqXHR) => {
        const msg = (jqXHR.responseJSON && jqXHR.responseJSON.message) || i18next.t("Failed to create event.");
        window.CRM.notify(msg, { type: "danger", delay: 5000 });
        $btn.prop("disabled", false).html('<i class="ti ti-plus me-1"></i>' + i18next.t("Create Today's Event"));
      });
  });
});
