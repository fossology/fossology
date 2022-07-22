/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG
 Author: Steffen Weber, Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

var myKey = 0;
var myVal = 0;

var commentModal = null;
var uploadId = 0;
var statusId = 0;

var assigneeSelected = 0;
var statusSelected = 0;

var staSel = null;

$(document).ready(function () {
  assigneeSelected = ($.cookie("assigneeSelected") || 0);
  $('#assigneeSelector').val(assigneeSelected);
  table = createBrowseTable();
  $('#insert_browsetbl_filter').append($('#browsetbl_filter'));
  $("input[type='search']").addClass("form-control-sm");
  $("input[type='search']").css({"width": "70%"});
  initPrioClick();
  table.on('draw', function () {
    initPrioClick();
    initPrioDraw();
    $('.cc').dblclick( function (){
        var source=table.cell(this).data();
        openCommentModal(source[0],source[1],source[2]);
    } );
    $('select.goto-active-option').change(function() {
      var url = $(this).val();
      if(url){ window.location = url;}
    });
  });
  commentModal = $('#commentModal').modal('hide');
  //$(document).tooltip({'items':"img"});
});

function initPrioClick() {
  $("td.priobucket").click(function () {
    table = createBrowseTable();
    elementData = table.cell(this).data();
    yourKey = elementData[0];
    if (myKey > 0 && myKey !== yourKey) {
      changePriority(myKey, yourKey);
      myKey = 0;
      myVal = 0;
      return;
    }
    else if (yourKey === myKey) {
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
  $("td.priobucket").each(function () {
    $('.ui-tooltip').remove();
    $(this).html(function () {
      return prioColumn(table.cell(this).data(), 'display');
    });
  });

  $('.limit-mover').click(function(){
    var uploadId = $(this).attr('data-source');
    var dir = $(this).attr('data-dir');
    move2limit(uploadId,dir);
  });

}

function move2limit(uploadId, direction) {
  myKey = 0;
  var post_data = {
    "uploadId": uploadId,
    "direction": direction
  };
  $.ajax({
    type: "POST",
    url: "?mod=browse-processPost",
    data: post_data,
    success: mysuccess4
  });
}

function openCommentModal(upload, status, comment) {
  uploadId = upload;
  statusId = status;
  $("#commentText").val(comment);
  commentModal.modal('show');
}

function closeCommentModal() {
  $(staSel).val( $(staSel).find('option[selected]').val() );
  commentModal.modal('hide');
}


function mysuccess() {
  var oTable = createBrowseTable();
  oTable.draw(false);
}

function mysuccess3() {
  closeCommentModal();
  var oTable = createBrowseTable();
  oTable.draw(false);
}

function mysuccess4() {
  myKey = 0;
  initPrioDraw();
  var oTable = createBrowseTable();
  oTable.draw(false);
}

function changeTableEntry(sel, uploadId, columnName) {
  if (columnName == 'status_fk' && (sel.value == 3 || sel.value == 4)) {
    staSel = sel;
    openCommentModal(uploadId, sel.value, '');
  }
  else {
    var post_data = {
      "columnName": columnName,
      "uploadId": uploadId,
      "value": sel.value
    };
    $.ajax({
      type: "POST",
      url: "?mod=browse-processPost",
      data: post_data,
      success: mysuccess
    });
  }
}

function filterAssignee() {
  assigneeSelected = $('#assigneeSelector').val();
  $.cookie("assigneeSelected", assigneeSelected);
  var oTable = createBrowseTable();
  oTable.draw(false);
}

function filterStatus() {
  statusSelected = $('#statusSelector').val();
  var oTable = createBrowseTable();
  oTable.draw(false);
}

function changePriority(move, beyond) {
  var post_data = {
    "move": move,
    "beyond": beyond
  };
  $.ajax({
    type: "POST",
    url: "?mod=browse-processPost",
    data: post_data,
    success: mysuccess
  });
}

function submitComment( ) {
  var post_data = {
    "uploadId": uploadId,
    "commentText": $("#commentText").val(),
    "statusId": statusId
  };
  $.ajax({
    type: "POST",
    url: "?mod=browse-processPost",
    data: post_data,
    success: mysuccess3
  });
}
