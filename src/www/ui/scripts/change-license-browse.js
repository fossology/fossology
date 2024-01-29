/*
 SPDX-FileCopyrightText: © 2014-2018, 2021 Siemens AG
 Author: Daniele Fognini, Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

function clearingSuccess(data) {
  location.reload();
}

var bulkModal;
var userModal;
var removed = false;

$(document).ready(function () {

  $("input[type='search']").addClass("form-control-sm");
  clearingHistoryDataModal = $('#ClearingHistoryDataModal').modal('hide');
  $('#bulkModal').draggable({
    stop: function(){
      $(this).css('height', '');
    }
  });

  $("#markDecisionAdd").click(function() {
    var decision = $("#markDecision").val();
    return markDecisions(decision, false);
  });

  $("#markDecisionRemove").click(function() {
    var decision = $("#removeDecision").val();
    return markDecisions(decision, true);
  });
});

$("#textModal").on('show.bs.modal', function (e) {
    $("#bulkModal").modal("hide");
});

$("#textModal").on('hide.bs.modal', function (e) {
    $("#bulkModal").modal("show");
});

function openBulkModal(uploadTreeId) {
  bulkModal = $('#bulkModal').modal('hide');
  $('#bulkScope').val("f");
  $('#bulkScope').attr("disabled", true);
  $('#uploadTreeId').val(uploadTreeId);
  bulkModal.toggle();
}

function closeBulkModal() {
  $('#editFilter').val(0);
  $('#bulkModal').hide();
}

// Hide backdrop for bulk modal
$('#bulkModal').on('shown.bs.modal', function () {
  $('.modal-backdrop').css('display', 'none');
  $('#bulkModal').css({'width': 'fit-content', 'margin': '0 auto'});
});

function loadBulkHistoryModal() {
  refreshBulkHistory(function(data) {
    $('#bulkHistoryModal').modal('show');
  });
}

function openUserModal(uploadTreeId) {
  userModal = $('#userModal').modal({"show": false});
  userModal.modal('hide');
  $('#uploadTreeId').val(uploadTreeId);
  $("#bulkIdResult").hide();
  userModal.modal('show');
}

function closeUserModal() {
  $('#editFilter').val(0);
  userModal.modal('hide');
}

function openClearingHistoryDataModal(uploadTreeId) {
  $('#uploadTreeId').val(uploadTreeId);
  clearingHistoryDataModal.modal('show');
}

function closeClearingHistoryDataModal() {
  clearingHistoryDataModal.modal('hide');
}

function scheduleBulkScan() {
  scheduleBulkScanCommon($('#bulkIdResult'), function () {
    location.reload();
  });
}

function performPostRequest(doRemove) {
  removed = doRemove;
  performPostRequestCommon($('#bulkIdResult'), function () {
    location.reload();
  });
}

function markDecisions(decisionToBeApplied, isRemoval) {
  if (isRemoval == true) {
    var pleaseConfirm = confirm("You are about to delete recent decisions. Please confirm!");
    if (pleaseConfirm == false) {
      return false;
    }
  }
  var data = {
    "uploadTreeId": $('#uploadTreeId').val(),
    "decisionMark": decisionToBeApplied,
    "isRemoval": isRemoval
  };
  resultEntity = $('#bulkIdResult');
  $.ajax({
    type: "POST",
    url: "?mod=change-license-processPost",
    data: data,
    success: function (data) { location.reload(); },
    error: function(responseobject) {
      bootstrapAlertError(responseobject, resultEntity);
    }
  });
}

function cleanText(textField) {
  var text = textField.val();

  var delimiters = $("#delimdrop").val();
  if (delimiters.toLowerCase() === "default") {
    delimiters = '\t\f#^%*';
  }
  delimiters = escapeRegExp(delimiters);
  var re = new RegExp("[" + delimiters + "]+", "gi");
  text = text.replace(/ [ ]*/gi, ' ')
             .replace(/(^|\n) ?\/[\*\/]+/gi, '$1')
             .replace(/(^|\n) ?['"]{3}/gi, '$1')
             .replace(/[\*]+\//gi, '')
             .replace(/(^|\n) ?(dnl)+/gi, '$1')
             .replace(re, ' ')
             .replace(/(^|\n)[ \t]*/gim, '$1')
             ;
  textField.val(text);
}
