/*
 SPDX-FileCopyrightText: © 2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Create a delayed timeout effect on a DOM element.
 * @param time    The amount of time which should pass before fadeout (in ms).
 * @param element The element to have this effect.
 */
function delayedFadeOut(time, element) {
  $(element).delay(time).fadeOut();
}

$(function() {
  $(".pat-revoke").on('click', function(e) {
    var button = this;
    var tokenId = button.id.split('-')[1];
    var parentTr = $(button).parent().parent();
    $.ajax({
      type : "POST",
      data : {
        "task" : "revoke",
        "token-id" : tokenId
      },
      url : "?mod=manage-token",
      success : function(data) {
        if (data.status == true) {
          button.disabled = true;   // Disable button to prevent new requests
          parentTr
              .html("<td colspan='6'>The token is revoked</td>");
          delayedFadeOut(5000, parentTr);
        } else {
          var infoMessage = $("<td>Unable to revoke token</td>");
          parentTr.append(infoMessage);
          delayedFadeOut(5000, infoMessage);
        }
      },
      error : function(data) {
        var infoMessage = $("<td>Some error occured</td>");
        parentTr.append(infoMessage);
        delayedFadeOut(5000, infoMessage);
      }
    });
  });

  $(".pat-reveal").on('click', function(e) {
    var button = this;
    var tokenId = button.id.split('-')[1];
    var parentTr = $(button).parent().parent();
    $.ajax({
      type : "POST",
      data : {
        "task" : "reveal",
        "token-id" : tokenId
      },
      url : "?mod=manage-token",
      success : function(data) {
        if (data.status == true) {
          button.disabled = true;   // Disable button to prevent new requests
          var tokenDisplay = $("<tr><td colspan='6'>"
              + "<textarea readonly style='width:100%'>" + data.token
              + "</textarea></td></tr>");
          parentTr.after(tokenDisplay);
        } else {
          var infoMessage = $("<tr><td colspan='6'>"
              + "<textarea readonly style='width:100%'>"
              + "Unable to reveal token</textarea></td></tr>");
          parentTr.after(infoMessage);
          delayedFadeOut(5000, infoMessage);
        }
      },
      error : function(data) {
        var infoMessage = $("<tr><td colspan='6'>"
            + "<textarea readonly style='width:100%'>"
            + "Some error occured</textarea></td></tr>");
        parentTr.after(infoMessage);
        delayedFadeOut(5000, infoMessage);
      }
    });
  });
});
