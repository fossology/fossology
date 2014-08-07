function addArsGo(formid, selectid)
{
    var selectobj = document.getElementById(selectid);
    var Agent_pk = selectobj.options[selectobj.selectedIndex].value;
    document.getElementById(formid).action='$action'+'&agent='+Agent_pk;
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
    if ((FileColor.readyState==4) && (FileColor.status==200))
    {
        /* remove previous highlighting */
        var numpks = Lastutpks.length;
        if (numpks > 0) $('#'+jQuerySelectorEscape(LastLic)).removeClass('highlight');
        while (numpks)
        {
            $('#'+jQuerySelectorEscape(Lastutpks[--numpks])).removeClass('highlight');
        }


        utpklist = FileColor.responseText.split(',');
        LastLic = utpklist.shift();
        numpks = utpklist.length;
        Lastutpks = utpklist;

        /* apply new highlighting */
        elt = $('#' + jQuerySelectorEscape(LastLic));
        if (elt != null) elt.addClass('highlight');
        while (numpks)
        {
            $('#'+jQuerySelectorEscape(utpklist[--numpks])).addClass('highlight');
        }
    }
    return;
}

jQuery.extend( jQuery.fn.dataTableExt.oSort, {
    "num-html-pre": function ( a ) {
        var x = String(a).replace( /<[\s\S]*?>/g, "" );
        return parseFloat( x );
    },
 
    "num-html-asc": function ( a, b ) {
        return ((a < b) ? -1 : ((a > b) ? 1 : 0));
    },
 
    "num-html-desc": function ( a, b ) {
        return ((a < b) ? 1 : ((a > b) ? -1 : 0));
    }
} );

$(document).ready(function() {
    createLicHistTable();
    createDirlistTable();
    /*
    $.fn.dataTableExt.afnFiltering.push(
        function( oSettings, aData, iDataIndex ) {
          if (aData.length!=3)
            return true;
          var licFilter = $('#dirlist_filter_license').val();
          var licScanCol = aData[1];
          var licAuditCol = aData[2];
          if(licScanCol.search(licFilter)>=0)
            return true;
          if(licAuditCol.search(licFilter)>=0)
            return true;
          return false;
        }
    );
    
    $('#dirlist_filter_license').keyup( function() { 
      $('#dirlist').dataTable().fnDraw(); 
    } );
    */
  } );


function filterLicense(licenseShortName) {
  // var searchField = $('#dirlist_filter_license');
  var searchField = $('#dirlist_filter input');
  searchField.val(licenseShortName);
  searchField.trigger('keyup');
}

function resetLicenseField() {
  var searchField =  $('#lichistogram_filter input');
  searchField.val('');
  searchField.trigger('keyup.DT');
}

function resetFileFields() {
    $('#dirlist_filter_license').val('');
    var searchField =  $('#dirlist_filter input');
    searchField.val('');
    searchField.trigger('keyup');
}
