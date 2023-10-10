/*
 SPDX-FileCopyrightText: © 2014-2020 Siemens AG
 Authors: Daniele Fognini, Johannes Najjar, Steffen Weber, Andreas J. Reichel, Shaheem Azmal M MD

 SPDX-License-Identifier: GPL-2.0-only
*/

//! This only works if this number stays hard coded in licenseref.sql
var magicNumberNoLicenseFoundInt = 507;
var magicNumberNoLicenseFound = "507";
var noLicenseString = "No_license_found";
var bulkModalOpened = 0;


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
    "scanOnlyFindings": $('#scanOnlyFindings').is(':checked') ? 1 : 0,
    "ignoreIrre": $('#bulkIgnoreIrre').is(':checked') ? 1 : 0,
    "delimiters": $("#delimdrop").val()
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
    success: function (data) { scheduledBootstrapSuccess(data, resultEntity, callbackSuccess, closeUserModal); },
    error: function(responseobject) { bootstrapAlertError(responseobject, resultEntity); }
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
        $('#decTypeSet').addClass('border-danger');
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
  } else {
    $('#selectFromNoticeFile').css('display','none');
  }

  if (what == 2 || what === 'reportinfo') {
    // clicked to add button to display child modal
    $('#clearText').show();
  } else {
    $('#clearText').hide();
  }

  if(type == 0) {
    let clearingsForSingleFile = $("#clearingsForSingleFile"+licenseId+what).attr("title");
    idLicUploadTree = uploadTreeId+','+licenseId;
    whatCol = what;
    $(refTextId).val(htmlDecode(clearingsForSingleFile));
    if (what == 3 || what == "acknowledgement") {
      createAcknowledgementDropDown($("#textModalComment"), $("#referenceText"));
    } else {
      $("#licenseAcknowledgementDropDown-text").hide();
      $("#licenseAcknowledgementDropDown").next(".select2-container").hide();
    }
    if (what == 4 || what == "comment") {
      createDropDown($("#textModalComment"), $("#referenceText"));
    } else {
      $("#licenseStdCommentDropDown-text").hide();
      $("#licenseStdCommentDropDown").next(".select2-container").hide();
    }
    textModal.modal('show');
  } else {
    $(refTextId).val(htmlDecode($("#"+licenseId+what+type).attr('title')));
    whatCol = what;
    whatLicId = licenseId;
    if (what == 3 || what == "acknowledgement") {
      createAcknowledgementDropDown($("#textModalComment"), $("#referenceText"));
    } else {
      $("#licenseAcknowledgementDropDown-text").hide();
      $("#licenseAcknowledgementDropDown").next(".select2-container").hide();
    }
    if (what == 4 || what == "comment") {
      createDropDown($("#textModalComment"), $("#referenceText"));
    } else {
      $("#licenseStdCommentDropDown-text").hide();
      $("#licenseStdCommentDropDown").next(".select2-container").hide();
    }
    textModal.modal('show');
  }
  whatType = type;
}

function closeTextModal() {
  textModal.modal('hide');
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
  $('#textAckInputModal').modal('show');
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
  } else {
    textModal.modal('hide');
    $("#"+ whatLicId + whatCol +"Bulk").attr('title', $(refTextId).val());
    referenceText = $(refTextId).val().trim();
    if(referenceText !== null && referenceText !== '') {
      $("#"+ whatLicId + whatCol + whatType).html($("#"+ whatLicId + whatCol + whatType).attr('title').slice(0, 10) + "...");
    } else {
      $("#"+ whatLicId + whatCol +"Bulk").attr('title','');
    }
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
  $('#textAckInputModal').modal('show');
}

function closeAckInputModal(){
  $('#textAckInputModal').modal('show');
}

function doOnSuccess(textModal) {
  textModal.modal('hide');
  $('#decTypeSet').addClass('border-danger');
  oTable = $('#licenseDecisionsTable').dataTable(selectedLicensesTableConfig).makeEditable(editableConfiguration);
  oTable.fnDraw(false);
}

$(document).ready(function () {
  noticeSelectTable = $('#noticeSelectTable').DataTable({
    paging: false,
    searching: false,
    data: []
  });

  $('[data-toggle="tooltip"]').tooltip();
  textModal = $('#textModal').modal('hide');
  $('#textModal, #ClearingHistoryDataModal, #userModal, #bulkHistoryModal').draggable({
    stop: function(){
      $(this).css({'width':'','height':''});
    }
  });
  $('#bulkModal').draggable({
    stop: function(){
      $(this).css('height', '');
    }
  });

  $('#custDelim').change(function () {
    if (this.checked) {
      $('#delimRow').removeClass("invisible").addClass("visible");
    } else {
      $('#delimRow').removeClass("visible").addClass("invisible");
      $('#resetDel').click();
    }
  });

  $('#resetDel').click(function () {
    $('#delimdrop').val('DEFAULT');
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
    "class": "ui-render-select2",
    "style": "width:100%"
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

function createAcknowledgementDropDown(element, textBox) {
  let dropDown = null;
  if ($("#licenseAcknowledgementDropDown").length) {
    // The dropdown already exists
    $("#licenseAcknowledgementDropDown-text").show();
    dropDown = $("#licenseAcknowledgementDropDown");
    dropDown.val(null).trigger('change');
    dropDown.next(".select2-container").show();
    return;
  }
  dropDown = $("<select />", {
    "id": "licenseAcknowledgementDropDown",
    "class": "ui-render-select2",
    "style": "width:100%"
  }).on("select2:select", function(e) {
    let id = e.params.data.id;
    getLicenseAcknowledgements(id, function (acknowledgement) {
      if (acknowledgement.hasOwnProperty("error")) {
        console.log("Error while fetching acknowledgements: " + acknowledgement.error);
      } else {
        textBox.val(textBox.val() + "\n" + acknowledgement.acknowledgement);
      }
    });
  });
  dropDown.insertBefore(element);
  $("<p />", {
    "id": "licenseAcknowledgementDropDown-text"
  }).html("Select license acknowledgements:").insertBefore(dropDown);
  getLicenseAcknowledgements("visible", function (data) {
    // Add a placeholder for select2
    data.splice(0, 0, {
      "la_pk": -1,
      "name": ""});
    dropDown.select2({
      selectOnClose: true,
      dropdownParent: textModal,
      placeholder: {
        id: '-1',
        text: "Select a acknowledgement"
      },
      data: $.map(data, function(obj) {
        return {
          "id": obj.la_pk,
          "text": obj.name
        };
      })
    });
  });
}

function getLicenseAcknowledgements(scope, callback) {
  $.ajax({
    type: "GET",
    url: "?mod=ajax_license_acknowledgements",
    data: {"scope": scope},
    success: function(data) {
      callback(data);
    },
    error: function(data) {
      callback(data.error);
    }
  });
}

function escapeRegExp(string){
  string = string.replace(/([.*+?^${}()|\[\]\/\\])/g, "\\$1");
  return string.replace(/\\\\([abfnrtv])/g, '\\$1'); // Preserve default escape sequences
}

function bootstrapAlertError(responseobject, resultEntity) {
  var error = false;
  if (responseobject.responseJSON !== undefined) {
    error = responseobject.responseJSON.error;
  }
  var errorSpan = resultEntity.find("span:first");
  if (error) {
    errorSpan.text("error: " + error);
  } else {
    errorSpan.text("error");
  }
  resultEntity.show();
}

function scheduledBootstrapSuccess (data, resultEntity, callbackSuccess, callbackCloseModal) {
  var jqPk = data.jqid;
  var errorSpan = resultEntity.find("span:first");
  if (jqPk) {
    errorSpan.html("scan scheduled as " + linkToJob(jqPk));
    if (callbackSuccess) {
      resultEntity.show();
      queueUpdateCheck(jqPk, callbackSuccess);
    }
    callbackCloseModal();
  } else {
    errorSpan.text("bad response from server");
  }
  resultEntity.show();
}
