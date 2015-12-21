/*
 Copyright (C) 2014-2015, Siemens AG
 Author: Daniele Fognini, Johannes Najjar, Steffen Weber 
 
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

//! This only works if this number stays hard coded in licenseref.sql
var magicNumberNoLicenseFoundInt = 507;
var magicNumberNoLicenseFound = "507";
var noLicenseString = "No_license_found";


function jsArrayFromHtmlOptions(pListBox) {
  var options = new Array(pListBox.options.length);
  for (var i = 0; i < options.length; i++) {
    if (pListBox.options[i].value === magicNumberNoLicenseFound)
      continue;
    options[i] = pListBox.options[i];
  }
  return options;
}

function htmlOptionsFromJsArray(pListBox, options) {
  pListBox.options.length = 0;
  if(options===undefined) return;
  for (var i = 0; i < options.length; i++) {
      if(options[i]===undefined) continue;
      pListBox.options[i] = options[i];
  }
}

function sortList(pListBox) {
  var options = jsArrayFromHtmlOptions(pListBox);
  options.sort(compareText);
  if (options.length == 0)
    options[0] = (new Option(noLicenseString, magicNumberNoLicenseFound));
  htmlOptionsFromJsArray(pListBox, options);
}

function compareText(opt1, opt2) {
  return opt1.text < opt2.text ? -1 : opt1.text > opt2.text ? 1 : 0;
}

function moveLicense(theSelFrom, theSelTo) {
  var selLength = theSelFrom.length;
  var i;
  for (i = selLength - 1; i >= 0; i--) {
    if (theSelFrom.options[i].selected) {
      theSelTo.appendChild(theSelFrom.options[i].cloneNode(true));
      theSelFrom[i] = null;
    }
  }
  sortList(theSelFrom);
  sortList(theSelTo);
}


function selectNoLicenseFound(left, right) {
  var selLength = right.length;
  var i;
  for (i = selLength - 1; i >= 0; i--) {
    left.options[left.options.length] = (new Option(right.options[i].text, right.options[i].value));
    right[i] = null;
  }
  selLength = left.length;
  for (i = selLength - 1; i >= 0; i--) {
    if (left.options[i].value == magicNumberNoLicenseFound) {
      left[i] = null;
    }
  }
  right.options[right.options.length] = (new Option(noLicenseString, magicNumberNoLicenseFound));
  sortList(left);
}


function scheduledDeciderSuccess (data, resultEntity, callbackSuccess, callbackCloseModal) {
  var jqPk = data.jqid;
  if (jqPk) {
    resultEntity.html("scan scheduled as " + linkToJob(jqPk));
    if (callbackSuccess) {
      queueUpdateCheck(jqPk, callbackSuccess);
    }
    callbackCloseModal();
  } else {
    resultEntity.html("bad response from server");
  }
  resultEntity.show();
}

function scheduledDeciderError (responseobject, resultEntity) {
  var error = responseobject.responseJSON.error;
  if (error) {
    resultEntity.text("error: " + error);
  } else {
    resultEntity.text("error");
  }
  resultEntity.show();
}

function scheduleBulkScanCommon(resultEntity, callbackSuccess) {
  var post_data = {
    "bulkAction": getBulkFormTableContent(),
    "refText": $('#bulkRefText').val(),
    "bulkScope": $('#bulkScope').val(),
    "uploadTreeId": $('#uploadTreeId').val(),
    "forceDecision": $('#forceDecision').is(':checked')?1:0
  };

  resultEntity.hide();

  $.ajax({
    type: "POST",
    url: "?mod=change-license-bulk",
    data: post_data,
    success: function(data) { scheduledDeciderSuccess(data, resultEntity, callbackSuccess,  closeBulkModal); },
    error: function(responseobject) { scheduledDeciderError(responseobject, resultEntity); }
  });

}

function performPostRequestCommon(resultEntity, callbackSuccess) {
  var txt = [];
  $('#licenseRight option').each(function () {
    txt.push(this.value);
  });

  var data = {
    "licenseNumbersToBeSubmitted": txt,
    "uploadTreeId": $('#uploadTreeId').val(),
    "removed": removed
  };

  $.ajax({
    type: "POST",
    url: "?mod=change-license-processPost",
    data: data,
    success: function (data) { scheduledDeciderSuccess(data,resultEntity, callbackSuccess, closeUserModal); },
    error: function(responseobject) { scheduledDeciderError(responseobject, resultEntity); }
  });

}


function popUpLicenseText(popUpUri, title) {
  sel = $("#bulkLicense :selected").val();
  window.open(popUpUri + sel, title, 'width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes');
}

function modifyLicense(doWhat ,uploadId, uploadTreeId, licenseId) {
  $.getJSON("?mod=conclude-license&do=" + doWhat + "&upload=" + uploadId + "&item=" + uploadTreeId + "&licenseId=" + licenseId)
    .done(function (data) {
      if(data) {
        $('#decTypeSet').addClass('decTypeWip');
      }
      var table = createClearingTable();
      table.fnDraw(false);
    })
    .fail(failed);
}

function addLicense(uploadId, uploadTreeId, licenseId) {
  modifyLicense('addLicense', uploadId, uploadTreeId, licenseId);
}

function removeLicense(uploadId, uploadTreeId, licenseId) {
  modifyLicense('removeLicense', uploadId, uploadTreeId, licenseId);
}


function makeMainLicense(uploadId, licenseId) {
  $.getJSON("?mod=conclude-license&do=makeMainLicense&upload=" + uploadId + "&licenseId=" + licenseId)
    .done(function (data) {
       var table = createClearingTable();
      table.fnDraw(false);
    })
    .fail(failed);
}

function removeMainLicense(uploadId,licenseId) {
  if(confirm("Remove this license from the main license list?"))
  {
    $.getJSON("?mod=conclude-license&do=removeMainLicense&upload=" + uploadId + "&licenseId=" + licenseId)
      .done(function (data) {
        var table = createClearingTable();
        table.fnDraw(false);
      })
      .fail(failed);
  }
}