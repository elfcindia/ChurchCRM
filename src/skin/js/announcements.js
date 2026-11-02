$(document).ready(function () {
  var $groupPickerRow = $("#groupPickerRow");
  var $groupSelect = $("#announcementGroupId");
  var $countLabel = $("#recipientCountLabel");
  var $message = $("#announcementMessage");
  var $charCount = $("#messageCharCount");

  function currentRecipientType() {
    return $("input[name='recipientType']:checked").val();
  }

  function refreshRecipientCount() {
    var type = currentRecipientType();
    var groupId = $groupSelect.val();

    if (type === "group" && !groupId) {
      $countLabel.html('<i class="fa-solid fa-circle-info me-1"></i>' + i18next.t("Choose a group"));
      return;
    }

    $countLabel.html('<i class="fa-solid fa-spinner fa-spin me-1"></i>' + i18next.t("Counting recipients…"));

    var params = { type: type };
    if (type === "group") params.groupId = groupId;

    $.ajax({
      url: window.CRM.root + "/v2/text/announcements/recipient-count",
      method: "GET",
      dataType: "json",
      data: params,
    }).done(function (data) {
      $countLabel.html(
        '<i class="fa-solid fa-user-group me-1"></i>' +
          i18next.t("This will text {{count}} people", { count: data.count }),
      );
    });
  }

  $("input[name='recipientType']").on("change", function () {
    $groupPickerRow.toggle(currentRecipientType() === "group");
    refreshRecipientCount();
  });

  $groupSelect.on("change", refreshRecipientCount);

  $.ajax({
    url: window.CRM.root + "/api/groups/",
    method: "GET",
    dataType: "json",
  }).done(function (groups) {
    groups.forEach(function (group) {
      $groupSelect.append($("<option>", { value: group.Id, text: group.Name }));
    });
  });

  refreshRecipientCount();

  $message.on("input", function () {
    var len = $message.val().length;
    var segments = len === 0 ? 1 : Math.ceil(len / 160);
    $charCount.text(len + " / " + segments * 160 + " · " + segments + " " + i18next.t("segment", { count: segments }));
  });

  $("#sendAnnouncement").on("click", function () {
    var message = $message.val().trim();
    if (!message) {
      window.CRM.notify(i18next.t("Write a message first"), { type: "danger" });
      return;
    }

    var type = currentRecipientType();
    if (type === "group" && !$groupSelect.val()) {
      window.CRM.notify(i18next.t("Choose a group first"), { type: "danger" });
      return;
    }

    bootbox.confirm({
      title: i18next.t("Send announcement?"),
      message: $countLabel.text() + '<br><br><em>"' + $("<div>").text(message).html() + '"</em>',
      callback: function (confirmed) {
        if (!confirmed) return;

        var $btn = $("#sendAnnouncement");
        $btn.prop("disabled", true);

        $.ajax({
          url: window.CRM.root + "/v2/text/announcements/send",
          method: "POST",
          contentType: "application/json",
          dataType: "json",
          data: JSON.stringify({
            recipientType: type,
            groupId: type === "group" ? $groupSelect.val() : null,
            message: message,
          }),
        })
          .always(function () {
            $btn.prop("disabled", false);
          })
          .done(function (data) {
            window.CRM.notify(i18next.t("Sent to {{sent}} of {{total}}", { sent: data.sent, total: data.total }), {
              type: data.failed > 0 ? "warning" : "success",
              delay: 6000,
            });
            $message.val("").trigger("input");
          });
      },
    });
  });
});
