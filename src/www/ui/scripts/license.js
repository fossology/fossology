/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG
 SPDX-FileCopyrightText: © 2026 Gaurav Kumar <Student-sst@outlook.com>
 Author: Daniele Fognini, Johannes Najjar, Steffen Weber

 SPDX-License-Identifier: GPL-2.0-only
*/

function addArsGo(formid, selectid)
{
  var selectobj = document.getElementById(selectid);
  var agentId = selectobj.options[selectobj.selectedIndex].value;
  document.getElementById(formid).action = '$action' + '&agent=' + agentId;
  document.getElementById(formid).submit();
  return;
}

jQuery.extend(jQuery.fn.dataTableExt.oSort, {
  "num-html-pre": function (a) {
    var x = String(a).replace(/<[\s\S]*?>/g, "");
    return parseFloat(x);
  },
  "num-html-asc": function (a, b) {
    return ((a < b) ? -1 : ((a > b) ? 1 : 0));
  },
  "num-html-desc": function (a, b) {
    return ((a < b) ? 1 : ((a > b) ? -1 : 0));
  }
});

$(document).ready(function () {
  if (typeof createLicHistTable === 'function') {
    createLicHistTable();
  }
  if (typeof createReuseReportTable === 'function') {
    createReuseReportTable();
  }

  searchField = $('#dirlist_filter input');
  var dirListFilter = getCookie('dirListFilter');
  searchField.val(dirListFilter).trigger('keyup');
  searchField.keyup(function () {
    setCookie('dirListFilter', $(this).val());
  });
  
  ['scan', 'con'].forEach(function (keyword) {
    var select = $('#' + keyword + 'Filter');
    var saved = getCookie(keyword + 'Filter').split(',');
    // Other pages still use a single-select filter and share these cookies.
    select.val(select.prop('multiple') ? saved : (saved[0] || '0'));
    select.trigger('change.select2');
    select.change(function () { filterScan($(this).val(), keyword); });
  });

  var openFilter = getCookie('openFilter');
  if(openFilter==='true' || openFilter==='checked') {
    $('#openCBoxFilter').prop('checked',openFilter);
  }
  $('#openCBoxFilter').click(function (){
    setCookie('openFilter', $(this).prop('checked'));
    otable.fnFilter('');
  });

  if (typeof createDirlistTable === 'function') {
    createDirlistTable();
  }
  $("form[data-autosubmit] select").change(function () {
    $(this).closest('form').submit();
  });
});


function filterLicense(licenseShortName) {
  var searchField = $('#dirlist_filter input');
  searchField.val(licenseShortName);
  resetFilters();
  searchField.trigger('keyup');
}

function clearSearchLicense() {
  var searchField =  $('#lichistogram_filter input');
  searchField.val('');
  resetFilters();
  searchField.trigger('keyup.DT');
}

function clearSearchFiles() {
  $('#dirlist_filter_license').val('');
  var searchField = $('#dirlist_filter input');
  searchField.val('');
  resetFilters();
  setCookie('dirListFilter', '');
  otable.fnFilter('');
}


function scheduleScan(upload, agentName, resultEntityKey) {
  var post_data = {
    "agentName": agentName,
    "uploadId": upload
  };

  var resultEntity = $(resultEntityKey);
  resultEntity.hide();

  $.ajax({
    type: "POST",
    url: "?mod=scheduleAgentAjax",
    data: post_data,
    success: function (data) {
      var jqPk = data.jqid;
      if (jqPk) {
        resultEntity.html("scan scheduled as " + linkToJob(jqPk) + "<br/>");
        $('#' + agentName.replace("agent_", "") + "_span").hide();
        queueUpdateCheck(jqPk, function () {
            resultEntity.html(agentName.replace("agent_", "") + " done.<br/>");
        }, function () {
          resultEntity.html(agentName.replace("agent_", "") + " failed!<br/>");
        }
        );
      }
      else {
        resultEntity.html("Bad response from server. <br/>");
      }
      resultEntity.show();
    },
    error: function (responseobject) {
      var error = responseobject.responseJSON.error;
      resultEntity.html((error ? "error: " + error : "error") + "<br/>");
      resultEntity.show();
    }
  });
}

function makeMainLicenseHist(uploadId, licenseId) {
  $.getJSON("?mod=conclude-license&do=makeMainLicense&upload=" + uploadId + "&licenseId=" + licenseId)
    .done(function () {
      setHistogramRowIsMain(licenseId, true);
    })
    .fail(failed);
}

function removeMainLicenseHist(uploadId, licenseId) {
  if (!confirm("Remove this license from the main license list?")) return;
  $.getJSON("?mod=conclude-license&do=removeMainLicense&upload=" + uploadId + "&licenseId=" + licenseId)
    .done(function () {
      setHistogramRowIsMain(licenseId, false);
    })
    .fail(failed);
}

function setHistogramRowIsMain(licenseId, isMain) {
  if (!window.dTable) return;
  var rows = dTable.fnGetData();
  for (var i = 0; i < rows.length; i++) {
    var cell = rows[i][2];
    if (cell && String(cell[1]) === String(licenseId)) {
      cell[2] = !!isMain;
      dTable.fnUpdate(cell, i, 2, false, false);
      dTable.fnDraw(false);
      break;
    }
  }
}

function dressContents(data, type, full) {
  if (type === 'display') {
    return '<a href=\'#\' onclick=\'filterScan(' + data[1] + ',\"scan\")\'>' + data[0] + '</a>';
  }
  return data;
}

function filterScan(id,keyword) {
  if (keyword !== 'scan' && keyword !== 'con') return;
  var select = $('#' + keyword + 'Filter');
  select.val(id).trigger('change.select2');
  setCookie(keyword + 'Filter', select.val() || '');
  otable.fnFilter('');
}

function resetFilters()
{
  ['scan', 'con'].forEach(function (keyword) {
    var select = $('#' + keyword + 'Filter');
    select.val(select.prop('multiple') ? [] : '0').trigger('change.select2');
    setCookie(keyword + 'Filter', '');
  });
  $('#openCBoxFilter').prop('checked', false);
  setCookie('openFilter', false);
}
