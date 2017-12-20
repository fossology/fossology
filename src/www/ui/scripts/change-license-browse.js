/*
 Copyright (C) 2014-2017, Siemens AG
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
  bulkModal = $('#bulkModal').plainModal();
  userModal = $('#userModal').plainModal();
  clearingHistoryDataModal = $('#ClearingHistoryDataModal').plainModal();
});

function openBulkModal(uploadTreeId) {
  $('#uploadTreeId').val(uploadTreeId);
  bulkModal.plainModal('open');
}

function closeBulkModal() {
  bulkModal.plainModal('close');
}

function loadBulkHistoryModal() {
  refreshBulkHistory(function(data) {
    $('#bulkHistoryModal').plainModal('open');
  });
}

function openUserModal(uploadTreeId) {
  $('#uploadTreeId').val(uploadTreeId);
  userModal.plainModal('open');
}

function closeUserModal() {
  userModal.plainModal('close');
}

function openClearingHistoryDataModal(uploadTreeId) {
  $('#uploadTreeId').val(uploadTreeId);
  clearingHistoryDataModal.plainModal('open');
}

function closeClearingHistoryDataModal() {
  clearingHistoryDataModal.plainModal('close');
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
      "decisionMark": 'irrelevant'
    };
  }
  resultEntity = $('bulkIdResult');
  $.ajax({
    type: "POST",
    url: "?mod=change-license-processPost",
    data: data,
    success: function (data) { location.reload(); },
    error: function(responseobject) { scheduledDeciderError(responseobject, resultEntity); }
  });

}

function deleteMarkedDecisions() {
  var data = {
    "uploadTreeId": $('#uploadTreeId').val(),
    "decisionMark": 'deleteIrrelevant'
  };
  resultEntity = $('bulkIdResult');
    var txt;
    var pleaseConfirm = confirm("You are about to delete all irrelevant decisions. Please confirm!");
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
