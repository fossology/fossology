/*
 Copyright (C) 2014, Siemens AG
 Author: Steffen Weber, Johannes Najjar

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

var myKey = 0;
var myVal = 0;

var theTable=null;

$(document).ready(function() {
  table =createBrowseTable();
  initPrioClick();
  table.on('draw', function(){
      initPrioClick();
      initPrioDraw();
  });
} );

function initPrioClick() {
  $("td.priobucket").click( function() {
//    table =  $('#browsetbl').dataTable();
      table =createBrowseTable();
    elementData = table.fnGetData( this );
    yourKey = elementData[0];
    if(myKey>0 && myKey!==yourKey){
        changePriority(myKey, yourKey);
        myKey = 0;
        myVal = 0;
      return;
    }
    else if (yourKey===myKey){
      myKey = 0;
      myVal = 0;
    }
    else
    {
      myKey = elementData[0];  //upload_pk
      myVal = elementData[1];  //priority
    }
    initPrioDraw();
  });
}
function initPrioDraw() {
  $("td.priobucket").each(function(){
    $(this).html( function(){        return prioColumn(table.fnGetData( this ),'display');   } );
  });

}

function prioColumn ( source, type, val ) {
  if (type === 'set') {
    source[1] = val;
    // Store the computed display and filter values for efficiency
    return;
  }
  if (type === 'display') {
    if (myVal===0){
     // return '<img alt="move" src="images/dataTable/sort_both.png"/>';
      return '<img alt="move" src="images/icons/arrow_down_32.png" class="icon-small"/>' +
          '<img alt="move" src="images/icons/blue_arrow_up_32.png" class="icon-small"/>';
    }
    else if (myVal<source[1]){
//      return '<img alt="move" src="images/dataTable/sort_asc.png"/>';
      return '<img alt="move" src="images/icons/blue_arrow_up_32.png" class="icon-small"/>';
    }
    else if (myVal>source[1]) {
//      return '<img alt="move" src="images/dataTable/sort_desc.png"/>';
      return '<img alt="move" src="images/icons/arrow_down_32.png" class="icon-small"/>';
    }
    else
        return 'click icon to insert<img alt="move" src="images/icons/close_32.png" class="icon-small"/>' ;
  }
  if (type==='sort') {
    return -source[1];
  }
  // 'filter', 'sort', 'type' and undefined all just use the integer
  return source[1];
}

function mysuccess(){
//    var oTable = createBrowseTable();
//    oTable.fnDraw();
}
function mysuccess2(){
    var oTable = createBrowseTable();
    oTable.fnDraw();
}

function changeTableEntry(sel, uploadId, columnName) {

    var post_data = {
        "columnName" : columnName,
        "uploadId": uploadId,
        "value":  sel.value
    };
    $.ajax({
        type: "POST",
        url:  "?mod=browse-processPost",
        data: post_data,
        success: mysuccess
    });


}


function changePriority( move, beyond) {

    var post_data = {
        "move" : move,
        "beyond": beyond,
    };
    $.ajax({
        type: "POST",
        url:  "?mod=browse-processPost",
        data: post_data,
        success: mysuccess2
    });


}