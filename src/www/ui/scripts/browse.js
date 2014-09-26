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

var commentModal = null;
var uploadId = 0;
var statusId = 0;

var assigneeSelected = 0;
var statusSelected = 0;

$(document).ready(function() {
  table =createBrowseTable();
  initPrioClick();
  table.on('draw', function(){
      initPrioClick();
      initPrioDraw();
  });
  commentModal = $('#commentModal').plainModal();
} );

function initPrioClick() {
  $("td.priobucket").click( function() {
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

function openCommentModal(upload,status,comment) {
  uploadId = upload;
  statusId = status;
  $("#commentText").val(comment);
  commentModal.plainModal('open');
}

function closeCommentModal() {
  commentModal.plainModal('close');
}


function commentColumn ( source, type, val ) {
  if (type === 'set') {
    source[2] = val;
    return;
  }
  if (type === 'display') {
    if (source[0]){
      return '<span ondblclick="openCommentModal('+source[0]+','+source[1]+',this.value)">'+source[2]+'</span>';
    }
    return source[2];
  }
  return source[2];
}

function mysuccess(){
    var oTable = createBrowseTable();
    oTable.fnDraw(false);
}

function mysuccess3(){
  closeCommentModal();
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
  if(columnName=='status_fk' && (sel.value==3 || sel.value==4)){
    openCommentModal(uploadId, sel.value, '');
  }
  else {
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
}

function filterAssignee() {
    assigneeSelected = $('#assigneeSelector').val();
    var oTable = createBrowseTable();
    oTable.fnDraw(false);
}

function filterStatus() {
    statusSelected = $('#statusSelector').val();
    var oTable = createBrowseTable();
    oTable.fnDraw(false);
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

function submitComment( ) {
    var post_data = {
        "uploadId" : uploadId,
        "commentText": $("#commentText").val(),
        "statusId": statusId
    };
    $.ajax({
        type: "POST",
        url:  "?mod=browse-processPost",
        data: post_data,
        success: mysuccess3
    });
}

function ShowHide(name) {
  if (name.length < 1) { return; }
  var Element, State;
  if (document.getElementById) // standard
    { Element = document.getElementById(name); }
  else if (document.all) // IE 4, 5, beta 6
    { Element = document.all[name]; }
  else // if (document.layers) // Netscape 4 and older
    { Element = document.layers[name]; }
  State = Element.style;
  if (State.display == 'none') { State.display='block'; }
  else { State.display='none'; }
}
  
function Expand() {
  var E = document.getElementsByTagName('div');
  for(var i = 0; i < E.length; i++)
    {
    if (E[i].id.substr(0,8) == 'TreeDiv-')
      {
      var Element, State;
      if (document.getElementById) // standard
        { Element = document.getElementById(E[i].id); }
      else if (document.all) // IE 4, 5, beta 6
        { Element = document.all[E[i].id]; }
      else // if (document.layers) // Netscape 4 and older
        { Element = document.layers[E[i].id]; }
      State = Element.style;
      State.display='block';
      }
    }
  }
  
function Collapse()
{
  var E = document.getElementsByTagName('div');
  var First=1;
  for(var i = 0; i < E.length; i++)
    {
    if (E[i].id.substr(0,8) == 'TreeDiv-')
      {
      var Element, State;
      if (document.getElementById) // standard
        { Element = document.getElementById(E[i].id); }
      else if (document.all) // IE 4, 5, beta 6
        { Element = document.all[E[i].id]; }
      else // if (document.layers) // Netscape 4 and older
        { Element = document.layers[E[i].id]; }
      State = Element.style;
      if (First) { State.display='block'; First=0; } 
      else { State.display='none'; } 
      }
    }
 }