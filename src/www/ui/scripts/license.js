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

  searchField = $('#dirlist_filter input');
  var dirListFilter = getCookie('dirListFilter');
  filterLicense(dirListFilter);
  searchField.keyup(function () {
    setCookie('dirListFilter', $(this).val());
  });
  
  var scanFilter = getCookie('scanFilter');
  if(scanFilter==="")
    scanFilter = 0;
  $('#scanFilter option[value='+scanFilter+']').parent().val(scanFilter);
  $('#scanFilter').change(function (){ filterScan($(this).val(),'scan'); });

  var conFilter = getCookie('conFilter');
  if(conFilter==="")
    conFilter = 0;
  $('#conFilter option[value='+conFilter+']').parent().val(conFilter);
  $('#conFilter').change(function (){ filterScan($(this).val(),'con'); });
  
  var openFilter = getCookie('openFilter');
  if(openFilter==='true' || openFilter==='checked')
    $('#openCBoxFilter').prop('checked',openFilter);
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
  searchField.trigger('keyup');
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

function dressContents(data, type, full) {
  if (type === 'display')
    return '<a href=\'#\' onclick=\'filterScan(' + data[1] + ',\"scan\")\'>' + data[0] + '</a>';
  return data;
}

function filterScan(id,keyword) {
  if(keyword==='scan'){
    $('#scanFilter').val(id);
    setCookie('scanFilter', id);
  }
  else if(keyword==='con')
  {
    $('#conFilter').val(id);
    setCookie('conFilter', id);
  }
  otable.fnFilter('');
}

function resetFilters()
{
  $('#scanFilter').val(0);
  $('#conFilter').val(0);
  $('#openCBoxFilter').attr('checked',false);
}