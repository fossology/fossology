/*
 Copyright (C) 2014-2018, Siemens AG
 Author: Daniele Fognini, Johannes Najjar

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
      $(this).css({'width':'','height':''});
    }
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
  $('#uploadTreeId').val(uploadTreeId);
  bulkModal.toggle();
}

function closeBulkModal() {
  $('#bulkModal').hide();
}

function loadBulkHistoryModal() {
  refreshBulkHistory(function(data) {
    $('#bulkHistoryModal').modal('show');
  });
}

function openUserModal(uploadTreeId) {
  userModal = $('#userModal').modal('hide');
  $('#uploadTreeId').val(uploadTreeId);
  userModal.toggle();
}

function closeUserModal() {
  userModal.hide();
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

function markDecisions(uploadTreeIdForMultiple) {
  if(Array.isArray(uploadTreeIdForMultiple)){
    var data = {
      "uploadTreeId": uploadTreeIdForMultiple,
      "decisionMark": 'irrelevant'
    };
  }else{
    var data = {
      "uploadTreeId": $('#uploadTreeId').val(),
      "decisionMark": uploadTreeIdForMultiple
    };
  }
  resultEntity = $('#bulkIdResult');
  $.ajax({
    type: "POST",
    url: "?mod=change-license-processPost",
    data: data,
    success: function (data) { location.reload(); },
    error: function(responseobject) { scheduledDeciderError(responseobject, resultEntity); }
  });

}

function deleteMarkedDecisions(decisionToBeRemoved) {
  var data = {
    "uploadTreeId": $('#uploadTreeId').val(),
    "decisionMark": decisionToBeRemoved
  };
  resultEntity = $('#bulkIdResult');
    var txt;
    var pleaseConfirm = confirm("You are about to delete recent decisions. Please confirm!");
  if (pleaseConfirm == true) {
    $.ajax({
      type: "POST",
      url: "?mod=change-license-processPost",
      data: data,
      success: function (data) { location.reload(); },
      error: function(responseobject) { scheduledDeciderError(responseobject, resultEntity); }
      });
  }
}

function cleanText() {
  var $textField = $('#bulkRefText');
  var text = $textField.val();

  text = text.replace(/ [ ]*/gi, ' ')
             .replace(/(^|\n)[ \t]*/gi,'$1')
             .replace(/(^|\n) ?\/[\*\/]+/gi, '$1')
             .replace(/[\*]+\//gi, '')
             .replace(/(^|\n) ?#+/gi,'$1')
             ;
  $textField.val(text);
}
