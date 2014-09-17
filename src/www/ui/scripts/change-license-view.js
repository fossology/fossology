/*
 Copyright (C) 2014, Siemens AG
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
    $('#clearingHistoryTable').html(data.tableClearing);
    $('#recentLicenseClearing').html(data.recentLicenseClearing);
}

var bulkModal;
var userModal;
$(document).ready(function() {
  bulkModal = $('#bulkModal').plainModal();
  userModal = $('#userModal').plainModal();
});

function openBulkModal() {
  bulkModal.plainModal('open');
}

function closeBulkModal() {
  bulkModal.plainModal('close');
}

function openUserModal() {
  userModal.plainModal('open');
}

function closeUserModal() {
  userModal.plainModal('close');
}

function reloadClearingTable(){
    // TODO reload also highlights
    $.ajax({
        type: "POST",
        url: "?mod=change-license-newclearing",
        data: { "uploadTreeId": $('#uploadTreeId').val() },
        success: clearingSuccess
    });
    $('#bulkIdResult').hide();
}

function scheduleBulkScan() {
    scheduleBulkScanCommon($('#bulkIdResult'), reloadClearingTable);
}


function hideLegend(){
    $("#legendBox").hide();
    $(".legendShower").show();
    $(".legendHider").hide();
    setOption("legendShow", false);
}

function  showLengend() {
    $("#legendBox").show();
    $(".legendHider").show();
    $(".legendShower").hide();
    setOption("legendShow", true);
}

function calculateDivHeight(){
    var viewportHeight =  $( window ).height();
    var usedPixels =  $('#leftrightalignment').offset();
    var availablePixels = viewportHeight-usedPixels.top;
    $('#leftrightalignment').height(availablePixels);
    var fixedPixelsLeft = 40;
    var availablePixelsLeft = availablePixels - fixedPixelsLeft;

    var fixedPixelsRight = 350;
    var availablePixelsRight = availablePixels - fixedPixelsRight;

    $('.boxnew').height(availablePixelsLeft);
    $('.scrollable').css({ maxHeight: availablePixelsRight*0.20 + 'px' });
    $('.scrollable2').css({ maxHeight: availablePixelsRight*0.30 + 'px' });

}


$(document).ready(function(){

 calculateDivHeight();
  $(".legendHider").click(function(){
        hideLegend();
  });
  $(".legendShower").click(function(){
        showLengend()
  });
  var legendOption =  getOptionDefaultTrue("legendShow");
  if(legendOption) {
        showLengend();
  }
   else {
        hideLegend();
  }
});

$(window).resize( calculateDivHeight );