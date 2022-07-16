/*
 SPDX-FileCopyrightText: © 2014-2018, 2021 Siemens AG
 Author: Daniele Fognini, Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

var defaultScope;
var defaultType;


function clearingSuccess(data) {
  $('#recentLicenseClearing').html(data.recentLicenseClearing);
}

function openBulkModal() {
  $('#userModal').hide();
  $('#ClearingHistoryDataModal').hide();
  bulkModalOpened = 1;
  $('#bulkModal').toggle();
}

function closeBulkModal() {
  bulkModalOpened = 0;
  $('#bulkModal').hide();
}

// Hide backdrop for bulk modal
$('#bulkModal').on('shown.bs.modal', function () {
  $('.modal-backdrop').css('display', 'none');
  $('#bulkModal').css({'width': 'fit-content', 'margin': '0 auto'});
});

function openUserDecisionModal() {
  $('#bulkModal').hide();
  $('#ClearingHistoryDataModal').hide();
  $('#userModal').toggle();
  $("#globalDecision").prop("checked", false);
  if ($('#userModal').is(":visible")) {
    $('#licenseSelectionTable_filter label input').focus();
  }
}

function closeUserModal() {
  $('#userModal').hide();
}


$("#textModal").on('show.bs.modal', function (e) {
  if(bulkModalOpened) {
    $("#bulkModal").modal("hide");
  }
});

$("#textModal").on('hide.bs.modal', function (e) {
  if(bulkModalOpened) {
    $("#bulkModal").modal("show");
  }
});

function openClearingHistoryDataModal() {
  $('#bulkModal').hide();
  $('#userModal').hide();
  $('#ClearingHistoryDataModal').toggle();
  createClearingHistoryDataTable();
}

function closeClearingHistoryDataModal() {
  $('#ClearingHistoryDataModal').hide();
}

function reloadClearingTable() {
  // TODO reload also highlights
  var table = createClearingTable();
  table.fnDraw(false);
  $('#bulkIdResult').hide();
  refreshBulkHistory(function (data) {
    $('#bulkHistory').show();
  });
}

function scheduleBulkScan() {
  scheduleBulkScanCommon($('#bulkIdResult'), reloadClearingTable);
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


function calculateDivHeight() {
  var viewportWidth = $(window).width();
  var viewportHeight = $(window).height();

  var usedPixels = $('#leftrightalignment').offset();
  var availablePixels = viewportHeight - usedPixels.top;
  var fixedPixelsLeft = 40;
  var availablePixelsLeft = availablePixels - fixedPixelsLeft;
  var availableWidth = viewportWidth / 2 - 20;

  $('.headerBox').css({height: availablePixelsLeft + 'px', width: availableWidth + 'px'});
  $('.boxnew').css({height: availablePixelsLeft + 'px', width: availableWidth + 'px'});
}

$(document).ready(function () {
  calculateDivHeight();
});

$(window).resize(calculateDivHeight);
