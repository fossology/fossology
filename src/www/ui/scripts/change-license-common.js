/*
 Copyright (C) 2014-2020, Siemens AG
 Authors: Daniele Fognini, Johannes Najjar, Steffen Weber,
          Andreas J. Reichel, Shaheem Azmal M MD

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
    if (pListBox.options[i].value === magicNumberNoLicenseFound) {
      continue;
    }
    options[i] = pListBox.options[i];
  }
  return options;
}

function htmlOptionsFromJsArray(pListBox, options) {
  pListBox.options.length = 0;
  if(options===undefined) { return;
  }
  for (var i = 0; i < options.length; i++) {
    if(options[i]===undefined) { continue;
    }
      pListBox.options[i] = options[i];
  }
}

function sortLicenseList(pListBox) {
  var options = jsArrayFromHtmlOptions(pListBox);
  options.sort(compareText);
  if (options.length == 0) {
    options[0] = (new Option(noLicenseString, magicNumberNoLicenseFound));
  }
  htmlOptionsFromJsArray(pListBox, options);
}

function compareText(opt1, opt2) {
  return opt1.text < opt2.text ? -1 : opt1.text > opt2.text ? 1 : 0;
}

function moveLicense(theSelFrom, theSelTo) {
  var selLength = theSelFrom.length;
  for (var i = selLength - 1; i >= 0; i--) {
    if (theSelFrom.options[i].selected) {
      theSelTo.appendChild(theSelFrom.options[i].cloneNode(true));
      theSelFrom[i] = null;
    }
  }

  sortLicenseList(theSelFrom);
  sortLicenseList(theSelTo);
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
  var error = false;
  if (responseobject.responseJSON !== undefined) {
    error = responseobject.responseJSON.error;
  }
  if (error) {
    resultEntity.text("error: " + error);
  } else {
    resultEntity.text("error");
  }
  resultEntity.show();
}

function isUserError(bulkActions, refText) {
    var errorText = "";
  if(bulkActions.length < 1) {
      errorText += "No licenses to bulk scan selected\n";
  }

  if(refText.trim().split(" ").length < 2) {
      errorText += "Reference text needs to be at least 2 words long"
  }

    //show errors to user
  if(errorText.length > 0) {
    alert("Bulk scan not scheduled: \n\n"+errorText);
    return true;
  }
    return false;
}

function scheduleBulkScanCommon(resultEntity, callbackSuccess) {
  var bulkActions = getBulkFormTableContent();
  var refText = $('#bulkRefText').val();
  var i;
  for (i = 0; i < bulkActions.length; i++) {
    bulkActions[i]["reportinfo"] = $("#"+bulkActions[i].licenseId+"reportinfoBulk").attr('title');
    bulkActions[i]["acknowledgement"] = $("#"+bulkActions[i].licenseId+"acknowledgementBulk").attr('title');
    bulkActions[i]["comment"] = $("#"+bulkActions[i].licenseId+"commentBulk").attr('title');
  }

  //checks for user errors
  if(isUserError(bulkActions, refText)) {
    return;
  }

  var post_data = {
    "bulkAction": bulkActions,
    "refText": refText,
    "bulkScope": $('#bulkScope').val(),
    "uploadTreeId": $('#uploadTreeId').val(),
    "forceDecision": $('#forceDecision').is(':checked')?1:0,
    "ignoreIrre": $('#bulkIgnoreIrre').is(':checked') ? 1 : 0
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

function htmlDecode(value) {
    if (value) {
        return $('<div/>').html(value).text();
    } else {
        return '';
    }
}

function openTextModel(uploadTreeId, licenseId, what, type) {
  var refTextId = "#referenceText"

  if(type === undefined) {
    type = 0;
  }

  if (what == 3 || what === 'acknowledgement') {
    // clicked to add button to display child modal
    $('#selectFromNoticeFile').css('display','inline-block');
  }

  if(type == 0) {
    let clearingsForSingleFile = $("#clearingsForSingleFile"+licenseId+what).attr("title");
    idLicUploadTree = uploadTreeId+','+licenseId;
    whatCol = what;
    $(refTextId).val(htmlDecode(clearingsForSingleFile));
    if (what == 4 || what == "comment") {
      createDropDown($("#textModal > form > div"), $("#referenceText"));
    } else {
      $("#licenseStdCommentDropDown-text").hide();
      $("#licenseStdCommentDropDown").next(".select2-container").hide();
    }
    textModal.dialog('open');
  } else {
    $(refTextId).val(htmlDecode($("#"+licenseId+what+type).attr('title')));
    whatCol = what;
    whatLicId = licenseId;
    if (what == 4 || what == "comment") {
      createDropDown($("#textModal > form > div"), $("#referenceText"));
    } else {
      $("#licenseStdCommentDropDown-text").hide();
      $("#licenseStdCommentDropDown").next(".select2-container").hide();
    }
    textModal.dialog('open');
  }
  whatType = type;
}

function closeTextModal() {
  $('#selectFromNoticeFile').css('display','none');
  textModal.dialog('close');
}

function ApplyNoticeText(idx)
{
  var hiddenNotice = $("#hiddennotice"+idx);
  var NoticeText = hiddenNotice.val();
  var textArea = $("#referenceText");

  var cursorPos = textArea.prop('selectionStart');
  var v = textArea.val();
  var textBefore = v.substring(0,  cursorPos);
  var textAfter  = v.substring(cursorPos, v.length);

  textArea.val(textBefore + NoticeText + textAfter);
  closeAckInputModal();
}

function UseThisNoticeButton(idx, text)
{
  return "<div>" +
         "<textarea id='hiddennotice"+idx+"' hidden='hidden'>" + text + "</textarea>" +
         "<input type=button onClick='ApplyNoticeText("+idx+")' value='Use this' />" +
         "</div>";
}

function doHandleNoticeFiles(response) {
  noticeSelectTable.clear();

  $.each(response, function(idx, el) {
    if (el.ufile_name !== undefined && el.contents_short !== undefined
        && el.contents !== undefined) {
      noticeSelectTable.row.add([el.ufile_name, el.contents_short, UseThisNoticeButton(idx, el.contents) ]);
    }
  });
  noticeSelectTable.draw();
  textAckInputModal.dialog("option", "position", {my: "center", at: "center", of: window });
}

function selectNoticeFile() {
  var GetNoticeFilesUrl = "?mod=ajax-notice-files&do=search";

  $.ajax({
    type: "POST",
    url: GetNoticeFilesUrl,
    data: {
      "uploadTreeId": $('#uploadTreeId').val()
    },
    success: (response) => doHandleNoticeFiles(response),
    failure: () => alert('Ajax: Could not get notice files.')
  });
}

function submitTextModal(){
  var refTextId = "#referenceText"
  var ConcludeLicenseUrl = "?mod=conclude-license&do=updateClearings";
  if(whatType == 0) {
    var post_data = {
      "id": idLicUploadTree,
      "columnId": whatCol,
      "value": $(refTextId).val()
    };
    $.ajax({
      type: "POST",
      url: ConcludeLicenseUrl,
      data: post_data,
      success: () => doOnSuccess(textModal)
    });
    $('#selectFromNoticeFile').css('display','none');
  } else {
    textModal.dialog('close');
    $("#"+ whatLicId + whatCol +"Bulk").attr('title', $(refTextId).val());
    referenceText = $(refTextId).val().trim();
    if(referenceText !== null && referenceText !== '') {
      $("#"+ whatLicId + whatCol + whatType).html($("#"+ whatLicId + whatCol + whatType).attr('title').slice(0, 10) + "...");
    } else {
      $("#"+ whatLicId + whatCol +"Bulk").attr('title','');
    }
    $('#selectFromNoticeFile').css('display','none');
  }
}

function checkIfEligibleForGlobalDecision()
{
  var checkBox = document.getElementById("globalDecision");
  if (checkBox.checked == true) {
    $.ajax({
      type: "POST",
      url: "?mod=conclude-license&do=checkCandidateLicense",
      data: {"item": $('#uploadTreeId').val()},
      success: function(data) {
        if (data != 'success') {
          alert(data);
          checkBox.checked = false;
        }
      },
      error: function(data) {
      }
    });
  }
}

function openAckInputModal(){
  selectNoticeFile();
  textAckInputModal.dialog('open');
}

function closeAckInputModal(){
  textAckInputModal.dialog('close');
}

function doOnSuccess(textModal) {
  textModal.dialog('close');
  $('#decTypeSet').addClass('decTypeWip');
  oTable = $('#licenseDecisionsTable').dataTable(selectedLicensesTableConfig).makeEditable(editableConfiguration);
  oTable.fnDraw(false);
}

$(document).ready(function () {
  textAckInputModal = $('#textAckInputModal').dialog({
    autoOpen:false, width:"auto", height:"auto", modal:true, resizable: false,
    open: function() {
      $(".ui-widget-overlay").addClass("grey-overlay");
      $(this).css("box-sizing", "border-box").css("max-height", "70vh")
        .css("min-height", "20vh").css("max-width", "70vw")
        .css("min-width", "20vw");
    }
  });

  noticeSelectTable = $('#noticeSelectTable').DataTable({
    paging: false,
    searching: false,
    data: []
  });

  textModal = $('#textModal').dialog({
    autoOpen:false, width:"auto",height:"auto",
    open: function() {
      $(this).css("box-sizing", "border-box").css("max-height", "70vh")
        .css("min-height", "20vh").css("max-width", "70vw")
        .css("min-width", "20vw");
    }
  });
});

function createDropDown(element, textBox) {
  let dropDown = null;
  if ($("#licenseStdCommentDropDown").length) {
    // The dropdown already exists
    $("#licenseStdCommentDropDown-text").show();
    dropDown = $("#licenseStdCommentDropDown");
    dropDown.val(null).trigger('change');
    dropDown.next(".select2-container").show();
    return;
  }
  dropDown = $("<select />", {
    "id": "licenseStdCommentDropDown",
    "class": "ui-render-select2"
  }).on("select2:select", function(e) {
    let id = e.params.data.id;
    getStdLicenseComments(id, function (comment) {
      if (comment.hasOwnProperty("error")) {
        console.log("Error while fetching standard comments: " + comment.error);
      } else {
        textBox.val(textBox.val() + "\n" + comment.comment);
      }
    });
  });
  dropDown.insertBefore(element);
  $("<p />", {
    "id": "licenseStdCommentDropDown-text"
  }).html("Select standard license comments:").insertBefore(dropDown);
  getStdLicenseComments("visible", function (data) {
    // Add a placeholder for select2
    data.splice(0, 0, {
      "lsc_pk": -1,
      "name": ""});
    dropDown.select2({
      selectOnClose: true,
      dropdownParent: textModal,
      placeholder: {
        id: '-1',
        text: "Select a comment"
      },
      data: $.map(data, function(obj) {
        return {
          "id": obj.lsc_pk,
          "text": obj.name
        };
      })
    });
  });
}

function getStdLicenseComments(scope, callback) {
  $.ajax({
    type: "GET",
    url: "?mod=ajax_license_std_comments",
    data: {"scope": scope},
    success: function(data) {
      callback(data);
    },
    error: function(data) {
      callback(data.error);
    }
  });
}
