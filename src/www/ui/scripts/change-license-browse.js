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
  bulkModal = $('#bulkModal').dialog({autoOpen:false, width:"auto",height:"auto", modal:true,open:function(){$(".ui-widget-overlay").addClass("grey-overlay");}});
  userModal = $('#userModal').dialog({autoOpen:false, width:"auto",height:"auto", modal:true,open:function(){$(".ui-widget-overlay").addClass("grey-overlay");}});
  clearingHistoryDataModal = $('#ClearingHistoryDataModal').dialog({autoOpen:false, width:"auto",height:"auto", modal:true,open:function(){$(".ui-widget-overlay").addClass("grey-overlay");}});
});

function openBulkModal(uploadTreeId) {
  $('#uploadTreeId').val(uploadTreeId);
  bulkModal.dialog('open');
}

function closeBulkModal() {
  bulkModal.dialog('close');
}

function loadBulkHistoryModal() {
  refreshBulkHistory(function(data) {
    $('#bulkHistoryModal').dialog('open');
  });
}

function openUserModal(uploadTreeId) {
  $('#uploadTreeId').val(uploadTreeId);
  userModal.dialog('open');
}

function closeUserModal() {
  userModal.dialog('close');
}

function openClearingHistoryDataModal(uploadTreeId) {
  $('#uploadTreeId').val(uploadTreeId);
  clearingHistoryDataModal.dialog('open');
}

function closeClearingHistoryDataModal() {
  clearingHistoryDataModal.dialog('close');
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
