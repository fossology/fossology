/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

function hideLegend() {
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

$(document).ready(function () {
  $(".legendHider").click(hideLegend);
  $(".legendShower").click(showLengend);
  var legendOption = getOptionDefaultTrue("legendShow");
  if (legendOption) {
    showLengend();
  }
  else {
    hideLegend();
  }
});