/*
 Copyright (C) 2014, Siemens AG
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
    var i;
    var options = new Array(pListBox.options.length);
    for ( i = 0; i < options.length; i++){
        if(pListBox.options[i].value == magicNumberNoLicenseFound) continue;
         options[i] = pListBox.options[i];
    }
    return options;
}

function htmlOptionsFromJsArray(pListBox, options) {
    pListBox.options.length = 0;
    for (var i = 0; i < options.length; i++){
        pListBox.options[i] = options[i];
    }
}

function sortList(pListBox) {
    var options = jsArrayFromHtmlOptions(pListBox);
    options.sort(compareText);
    if(options.length == 0) options[0] = (new Option(noLicenseString, magicNumberNoLicenseFound));
    htmlOptionsFromJsArray(pListBox, options);
}

function compareText(opt1, opt2) {
    return opt1.text < opt2.text ? -1 : opt1.text > opt2.text ? 1 : 0;
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

function scheduleBulkScanCommon(resultEntity, callbackSuccess) {
    var post_data = {
        "removing": $('#bulkRemoving').val(),
        "refText": $('#bulkRefText').val(),
        "licenseId": $('#bulkLicense').val(),
        "bulkScope" : $('#bulkScope').val(),
        "uploadTreeId": $('#uploadTreeId').val()
    };

    resultEntity.hide();

    $.ajax({
        type: "POST",
        url: "?mod=change-license-bulk",
        data: post_data,
        success: function(data) {
            var jqPk = data.jqid;
            if (jqPk) {
                resultEntity.html("scan scheduled as " + linkToJob(jqPk));
                if (callbackSuccess) {
                    queueUpdateCheck(jqPk, callbackSuccess);
                }
                closeBulkModal();
            } else {
                resultEntity.html("bad response from server");
            }
            resultEntity.show();
        },
        error: function(responseobject) {
            var error = responseobject.responseJSON.error;
            if (error) {
                resultEntity.text("error: " + error );
            } else {
                resultEntity.text("error");
            }
            resultEntity.show();
        }
    });

}

function performPostRequest() {
    var txt = [];
    $('#licenseLeft option:selected').each(function () {
        txt.push(this.value);
    });

    var data = {
        "licenseNumbersToBeSubmitted": txt,
        "uploadTreeId": $('#uploadTreeId').val(),
        "type": $('[name="type"]:checked').val(),
        "scope": $('[name="scope"]:checked').val(),
        "comment": $('#comment').val(),
        "remark": $('#remark').val()
    };

    $.ajax({
        type: "POST",
        url: "?mod=change-license-processPost",
        data: data,
        success: clearingSuccess
    });

    closeUserModal();
}



function popUpLicenseText(popUpUri,title) {
  sel = $("#bulkLicense :selected").text();
  window.open(popUpUri+sel,title,'width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes');
}

function addLicense(uploadId, uploadTreeId, licenseId) {
    $.getJSON("?mod=conclude-license&do=addLicense&upload=" + uploadId + "&item=" + uploadTreeId + "&licenseId=" + licenseId + "&global=" + $('[name="global_license_decision"]:checked').val())
        .done(function (data) {
            var table = createLicenseDecisionTable();
            table.fnDraw(false);
        })
        .fail(failed);

}


function removeLicense(uploadId, uploadTreeId, licenseId) {
    $.getJSON("?mod=conclude-license&do=removeLicense&upload=" + uploadId + "&item=" + uploadTreeId + "&licenseId=" + licenseId + "&global=" + $('[name="global_license_decision"]:checked').val())
        .done(function (data) {
            var table = createLicenseDecisionTable();
            table.fnDraw(false);
          })
        .fail(failed);

}