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

function openUserModal() {
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
