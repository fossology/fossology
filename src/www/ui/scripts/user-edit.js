/***********************************************************
 * Copyright (C) 2019 Siemens AG
 * Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

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
