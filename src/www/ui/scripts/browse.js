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

var rejectorModal = null;
var uploadId = 0;

$(document).ready(function() {
  table =createBrowseTable();
  initPrioClick();
  table.on('draw', function(){
      initPrioClick();
      initPrioDraw();
  });
  rejectorModal = $('#rejectorModal').plainModal();
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
    $(this).html( function(){    return prioColumn(table.fnGetData( this ),'display');   } );
  });

}

function move2limit(uploadId,direction){
  myKey=0;
  var post_data = {
      "uploadId" : uploadId,
      "direction": direction
  };
  $.ajax({
      type: "POST",
      url:  "?mod=browse-processPost",
      data: post_data,
      success: mysuccess4
  });
}

function prioColumn ( source, type, val ) {
  if (type === 'set') {
    source[1] = val;
    // Store the computed display and filter values for efficiency?
    return;
  }
  if (type === 'display') {
    if (myKey===0){
      return '<img alt="move" src="images/icons/blue_arrow_up_32.png" class="icon-small" onclick="move2limit('+source[0]+',\'top\')"/>'
      +' <img alt="move" src="images/icons/arrow_updown_32.png" class="icon-small"/>'
      +' <img alt="move" src="images/icons/blue_arrow_down_32.png" class="icon-small" onclick="move2limit('+source[0]+',\'-1\')"/>';
    }
    else if (myVal<source[1]){
      return '<img alt="move" src="images/icons/arrow_up_32.png" class="icon-small"/>';
    }
    else if (myVal>source[1]) {
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

function openRejectorModal(k) {
  uploadId = k;
  rejectorModal.plainModal('open');
}

function closeRejectorModal() {
  rejectorModal.plainModal('close');
}


function rejectorColumn ( source, type, val ) {
  if (type === 'set') {
    source[1] = val;
    return;
  }
  if (type === 'display') {
    if (source[1]){
      return 'rejected by '+source[2];
    }
    else{
      return '<a class="button" onclick="openRejectorModal('+source[0]+')"><img alt="move" src="images/icons/close_32.png" class="icon-small"/>reject</a>';
    }
  }
  if (type==='sort') {
    return -source[1];
  }
  return source[1];
}

function mysuccess(){
    var oTable = createBrowseTable();
    oTable.fnDraw(false);
}

function mysuccess3(){
  closeRejectorModal();
  var oTable = createBrowseTable();
  oTable.fnDraw(false);
}

function mysuccess4(){
  myKey=0;
  initPrioDraw();
  var oTable = createBrowseTable();
  oTable.fnDraw(false);
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
        "beyond": beyond
    };
    $.ajax({
        type: "POST",
        url:  "?mod=browse-processPost",
        data: post_data,
        success: mysuccess
    });
}

function submitRejector( ) {
    var post_data = {
        "uploadId" : uploadId,
        "commentText": $("#commentText").val()
    };
    $.ajax({
        type: "POST",
        url:  "?mod=browse-processPost",
        data: post_data,
        success: mysuccess3
    });
}