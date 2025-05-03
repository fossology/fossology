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
    "order": [[1, 'asc']],  
    "autoWidth": false,
    "columnDefs": [
        {
            "createdCell": function (cell) {
                $(cell).attr("style", "text-align:center");
            },
            "searchable": false, 
            "orderable": false,
            "targets": [0]  
        },
        {
            "orderable": true,
            "searchable": true,
            "targets": [1],
            "render": function (data, type, row) {
                if (type === 'display') {
                    return data;  
                }
                return $(data).val();  
            }
        },
        {
            "searchable": true,
            "targets": [2]
    }],
  });

  t.on('order.dt search.dt', function () {
    let rows = t.rows({ search: 'applied', order: 'applied' }).nodes();
    let lastIndex = 1;
    
    $(rows).each(function (index, row) {
        if ($(row).find(".newAcknowledgementInputs").length === 0) {
            $(row).find("td:first").html(lastIndex++);
        }
    });

    let newRow = $("#adminLicenseAcknowledgementTable tbody tr").last();
    if (newRow.find(".newAcknowledgementInputs").length > 0) {
        newRow.find("td:first").html(lastIndex);
    }
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

    var lastIndex = t.rows().count() + 1;

    var rowNode = t.row.add([
      lastIndex,
      '<input type="text" name="insertLicNames[]" ' +
        'placeholder="Please enter a name for the Acknowledgement" ' +
        'class="newAcknowledgementInputs" />',
      '<textarea rows="7" cols="80" name="insertLicAcknowledgements[]" ' +
        'placeholder="Please enter a acknowledgement statement" ' +
        'class="newAcknowledgementInputs"></textarea>',
      '<input type="checkbox" checked disabled />'
    ]).draw(false).node();

    $(rowNode).appendTo("#adminLicenseAcknowledgementTable tbody");

    $(rowNode).find("input, textarea").first().focus();

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