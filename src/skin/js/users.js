$(document).ready(function () {
  $("#user-listing-table").DataTable(window.CRM.plugin.dataTable);

  $(".setting-tip").click(function () {
    bootbox.alert({
      message: $(this).data("tip"),
      backdrop: true,
      className: "setting-tip-box",
    });
  });

  var disallowedEl = document.getElementById("aDisallowedPasswords");
  if (disallowedEl && !disallowedEl.tomselect) {
    new TomSelect(disallowedEl, {
      create: true,
      persist: false,
      delimiter: ",",
      createOnBlur: true,
      plugins: ["remove_button"],
      options: [
        { value: "asfasf", text: "asfasf" },
        { value: "asdfasdf", text: "asdfasdf" },
      ],
      items: ["asfasf", "asdfasdf"],
    });
  }
});

function deleteUser(userId, userName) {
  bootbox.confirm({
    title: i18next.t("User Delete Confirmation"),
    message:
      '<p style="color: red">' +
      i18next.t("Please confirm removal of user status from") +
      ": <b>" +
      userName +
      "</b></p>",
    callback: function (result) {
      if (result) {
        window.CRM.AdminAPIRequest({
          path: "user/" + userId + "/",
          method: "DELETE",
        }).done(function () {
          window.location.href = window.CRM.root + "/admin/system/users";
        });
      }
    },
  });
}

function restUserLoginCount(userId, userName) {
  bootbox.confirm({
    title: i18next.t("Action Confirmation"),
    message:
      '<p style="color: red">' + i18next.t("Please confirm reset failed login count") + ": <b>" + userName + "</b></p>",
    callback: function (result) {
      if (result) {
        window.CRM.AdminAPIRequest({
          path: "user/" + userId + "/login/reset",
          method: "POST",
        }).done(function (data) {
          if (data.status === "success") window.location.href = window.CRM.root + "/admin/system/users";
        });
      }
    },
  });
}

function resetUserPassword(userId, userName) {
  bootbox.confirm({
    title: i18next.t("Action Confirmation"),
    message:
      '<p style="color: red">' +
      i18next.t("Please confirm the password reset of this user") +
      ": <b>" +
      userName +
      "</b></p>",
    callback: function (result) {
      if (result) {
        window.CRM.AdminAPIRequest({
          path: "user/" + userId + "/password/reset",
          method: "POST",
        }).done(function (data) {
          window.CRM.notify(i18next.t("Password reset for") + " " + userName, {
            type: "success",
          });
        });
      }
    },
  });
}

$(document).ready(function () {
  var addUserModalEl = document.getElementById("addUserModal");
  var resultModalEl = document.getElementById("addUserResultModal");

  $("#auSubmit").click(function () {
    var firstName = $("#auFirst").val().trim();
    var lastName = $("#auLast").val().trim();

    if (firstName.length < 2 || lastName.length < 2) {
      window.CRM.notify(i18next.t("First and last name must be at least 2 characters"), {
        type: "danger",
      });
      return;
    }

    window.CRM.AdminAPIRequest({
      path: "user",
      method: "POST",
      data: JSON.stringify({
        firstName: firstName,
        lastName: lastName,
        email: $("#auEmail").val().trim(),
        cellPhone: $("#auCell").val().trim(),
      }),
    }).done(function (data) {
      if (addUserModalEl) window.bootstrap.Modal.getOrCreateInstance(addUserModalEl).hide();
      $("#auResultUserName").val(data.userName);
      $("#auResultPassword").val(data.password);
      if (resultModalEl) window.bootstrap.Modal.getOrCreateInstance(resultModalEl).show();
    });
  });

  $("#auCopyUserName").click(function () {
    window.CRM.copyToClipboard($("#auResultUserName").val());
  });

  $("#auCopyPassword").click(function () {
    window.CRM.copyToClipboard($("#auResultPassword").val());
  });

  if (addUserModalEl) {
    addUserModalEl.addEventListener("hidden.bs.modal", function () {
      $("#auFirst, #auLast, #auEmail, #auCell").val("");
    });
  }

  if (resultModalEl) {
    resultModalEl.addEventListener("hidden.bs.modal", function () {
      $("#auResultUserName, #auResultPassword").val("");
      window.location.reload();
    });
  }
});

function disableUserTwoFactorAuth(userId, userName) {
  bootbox.confirm({
    title: i18next.t("Action Confirmation"),
    message:
      '<p style="color: red">' +
      i18next.t("Please confirm disabling 2 Factor Auth for this user") +
      ": <b>" +
      userName +
      "</b></p>",
    callback: function (result) {
      if (result) {
        window.CRM.AdminAPIRequest({
          path: "user/" + userId + "/disableTwoFactor",
          method: "POST",
        }).done(function (data) {
          window.location.href = window.CRM.root + "/admin/system/users";
        });
      }
    },
  });
}
