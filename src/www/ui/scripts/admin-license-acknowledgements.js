/*
 SPDX-FileCopyrightText: © 2022 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

function showTheMessage(message) {
  $("html, body").animate({ scrollTop: 0 }, "slow");
  $("#messageSpace").html(message + "<hr />").fadeIn(500).delay(5000).fadeOut(500);
}

$(document).ready(function() {

  var form = $('form#adminLicenseAcknowledgementForm');

  var t = $("#adminLicenseAcknowledgementTable").DataTable({
    "processing": true,
    "paginationType": "listbox",
    "order": [[ 1, 'asc' ]],
    "autoWidth": false,
    "columnDefs": [{
      "createdCell": function (cell) {
        $(cell).attr("style", "text-align:center");
      },
      "searchable": false,
      "targets": [0]
    },{
      "orderable": false,
      "targets": [0,2,3]
    },{
      "orderable": true,
      "targets": [1]
    }],
  });

  t.on('order.dt search.dt', function () {
    t.column(0, {search:'applied', order:'applied'}).nodes().each( function (cell, i) {
      cell.innerHTML = i+1;
    });
  }).draw();

  form.find("input[type=text],textarea").on("change", function(){
    $(this).addClass("inputChanged");
  });

  form.submit(function(event) {
    var updatedFields = form.find(".inputChanged").serializeArray();
    var insertedFields = form.find(".newAcknowledgementInputs").serializeArray();
    if (updatedFields.length > 0 || insertedFields.length > 0) {
      var itemsToSend = $.merge(updatedFields, insertedFields);
      itemsToSend.push({"name": "formUpdated", "value": 1});
      $.ajax({
        url : '?mod=admin_license_acknowledgements',
        type : 'post',
        dataType : 'json',
        data : itemsToSend,
        success : function(data) {
          var message = "";
          if (data.updated == -1) {
            message = "No acknowledgements updated";
          } else if (data.updated > 0) {
            form.find(".inputChanged").removeClass("inputChanged");
            message = "Acknowledgements updated succesfully";
          } else {
            message = data.updated;
          }
          var messageIns = [];
          if (data.inserted.status != 0) {
            if (data.inserted.status & 1) {
              form.find(".newAcknowledgementInputs").each(function(){
                if ($(this).val().trim()) {
                  $(this).removeClass("newAcknowledgementInputs");
                }
              });
              messageIns.push("Acknowledgements inserted successfully");
            }
            if (data.inserted.status & 1<<1) {
              messageIns.push("errors during insertion");
            }
            if (data.inserted.status & 1<<2) {
              messageIns.push("exceptions during insertion");
            }
          }
          showTheMessage(message + ".<br />" + messageIns.join(" with some ") + ".");
        },
        error : function(data) {
          showTheMessage(data);
        }
      });
    }
    event.preventDefault();
  });

  $("#addLicAcknowledgement").on('click', function(){
    t.row.add([
      null,
      '<input type="text" name="insertLicNames[]" ' +
        'placeholder="Please enter a name for the Acknowledgement" ' +
        'class="newAcknowledgementInputs" />',
      '<textarea rows="7" cols="80" name="insertLicAcknowledgements[]" ' +
        'placeholder="Please enter a acknowledgement statement" ' +
        'class="newAcknowledgementInputs"></textarea>',
      '<input type="checkbox" checked disabled />'
    ]).draw(false).page("last").draw(false);
  });

  $(".licStdAckToggle").change(function(){
    var changedBox = $(this);
    var boxName = changedBox.attr("name");
    var idRegex = /licAcknowledgementEnabled\[(\d+)\]/g;
    var commId = idRegex.exec(boxName);
    commId = commId[1];
    $.ajax({
      url : '?mod=ajax_license_acknowledgements',
      type : 'post',
      dataType : 'json',
      data : {"toggle": commId},
      success : function(data) {
        if (data.status != true) {
          // Not updated, revert the UI
          var current = changedBox.prop("checked");
          changedBox.prop("checked", !current);
        }
      }
    });
  });
});
