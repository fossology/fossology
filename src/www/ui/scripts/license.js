function addArsGo(formid, selectid)
{
  var selectobj = document.getElementById(selectid);
  var Agent_pk = selectobj.options[selectobj.selectedIndex].value;
  document.getElementById(formid).action = '$action' + '&agent=' + Agent_pk;
  document.getElementById(formid).submit();
  return;
}

function jQuerySelectorEscape(expression) {
  return expression.replace(/[!"#$%&'()*+,.\/:;<=>?@\[\\\]^`{|}~]/g, '\\$&');
}
/* Add javascript for color highlighting
 This is the response script needed by ActiveHTTPscript
 responseText is license name',' followed by a comma seperated list of uploadtree_pk's */
var Lastutpks = '';   /* save last list of uploadtree_pk's */
var LastLic = '';   /* save last License (short) name */

function FileColor_Reply()
{
  if ((FileColor.readyState == 4) && (FileColor.status == 200))
  {
    /* remove previous highlighting */
    var numpks = Lastutpks.length;
    if (numpks > 0)
      $('#' + jQuerySelectorEscape(LastLic)).removeClass('highlight');
    while (numpks)
    {
      $('#' + jQuerySelectorEscape(Lastutpks[--numpks])).removeClass('highlight');
    }
    utpklist = FileColor.responseText.split(',');
    LastLic = utpklist.shift();
    numpks = utpklist.length;
    Lastutpks = utpklist;
    /* apply new highlighting */
    elt = $('#' + jQuerySelectorEscape(LastLic));
    if (elt != null)
      elt.addClass('highlight');
    while (numpks)
    {
      $('#' + jQuerySelectorEscape(utpklist[--numpks])).addClass('highlight');
    }
  }
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
    if(typeof createLicHistTable==='function') {
      createLicHistTable();
    }
    if(typeof createDirlistTable==='function') {
      createDirlistTable();
    }
    $("form[data-autosubmit] select").change(function() {
        $(this).closest('form').submit();
    });
});


function filterLicense(licenseShortName) {
  var searchField = $('#dirlist_filter input');
  searchField.val(licenseShortName);
  searchField.trigger('keyup');
}

function clearSearchLicense() {
  var searchField =  $('#lichistogram_filter input');
  searchField.val('');
  searchField.trigger('keyup.DT');
}

function clearSearchFiles() {
    $('#dirlist_filter_license').val('');
    var searchField =  $('#dirlist_filter input');
    searchField.val('');
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
      } else {
        resultEntity.html("Bad response from server. <br/>");
      }
      resultEntity.show();
    },
    error: function (responseobject) {
      var error = responseobject.responseJSON.error;
      if (error) {
        resultEntity.html("error: " + error + "<br/>");
      } else {
        resultEntity.html("error" + "<br/>");
      }
      resultEntity.show();
    }
  });

}
function dressContents ( data, type, full ) {
    if (type === 'display')
        return '<a onclick=\'filterLicense(\"'+data +'\")\'>'+data+'</a>';

    return data;


}

