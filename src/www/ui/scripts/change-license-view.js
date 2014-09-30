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

var defaultScope;
var defaultType;


function clearingSuccess(data) {
    $('#clearingHistoryTable').html(data.tableClearing);
    $('#recentLicenseClearing').html(data.recentLicenseClearing);
}

function openBulkModal() {
  $('#userModal').hide();
  $('#bulkModal').show();
}

function closeBulkModal() {
  $('#bulkModal').hide();
}

function openUserModal() {
  $('#bulkModal').hide();
  $('#userModal').show();
}

function closeUserModal() {
  $('#userModal').hide();
}

function reloadClearingTable(){
    // TODO reload also highlights
    var table = createLicenseDecisionTable();
    table.fnDraw(false);
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

function showLengend() {
    $("#legendBox").show();
    $(".legendHider").show();
    $(".legendShower").hide();
    setOption("legendShow", true);
}

function calculateDivHeight(){
    var viewportWidth = $(window).width();
    var viewportHeight =  $( window ).height();

    var usedPixels =  $('#leftrightalignment').offset();
    var availablePixels = viewportHeight-usedPixels.top;
    var fixedPixelsLeft = 40;
    var availablePixelsLeft = availablePixels - fixedPixelsLeft;
    var availableWidth = viewportWidth / 2 - 20;

    $('.headerBox').css({ height:availablePixelsLeft + 'px', width: availableWidth + 'px' });
    $('.boxnew').css({ height:availablePixelsLeft + 'px', width: availableWidth + 'px' });
}


checkDefaultScope = function(){ $(this).prop("checked",$(this).val()==defaultScope);};
checkDefaultType = function(){ $(this).prop("checked",$(this).val()==defaultType);};

$(document).ready(function(){

  calculateDivHeight();
  $(".legendHider").click( hideLegend );
  $(".legendShower").click( showLengend );
  var legendOption =  getOptionDefaultTrue("legendShow");
  if(legendOption) {
    showLengend();
  }
  else {
    hideLegend();
  }
  defaultScope=getOption("defaultScope");
  defaultType=getOption("defaultType");
  $("input[name=scope]").each( function() {
      $(this).each(checkDefaultScope);
      $(this).click( function(){
          defaultScope = $(this).val();
          setOption("defaultScope", defaultScope);
          $("input[name=scope], input[name=scopeOutside]").each(checkDefaultScope);
        });
    } );
  $("input[name=type]").each( function() {
      $(this).each(checkDefaultType);
      $(this).click( function(){
          defaultType = $(this).val();
          setOption("defaultType", defaultType);
          $("input[name=type]").each(checkDefaultType);
        });
    } );

});

$(window).resize( calculateDivHeight );