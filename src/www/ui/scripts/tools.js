/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

function setCookie(cookieName, cookieValue, exdays) {
  exdays = typeof exdays !== 'undefined' ? exdays : 1;
  var date = new Date();
  date.setTime(date.getTime() + (exdays * 24 * 60 * 60 * 1000));
  var expires = "expires=" + date.toUTCString();
  document.cookie = cookieName + "=" + cookieValue + "; " + expires;
}

function getCookie(cookieName) {
  var name = cookieName + "=";
  var allCookies = document.cookie.split(';');
  for (var i = 0; i < allCookies.length; i++) {
    var theCookie = allCookies[i];
    while (theCookie.charAt(0) == ' ') {
      theCookie = theCookie.substring(1);
    }
    if (theCookie.indexOf(name) != -1) {
      return theCookie.substring(name.length, theCookie.length);
    }
  }
  return "";
}

function setOption(name, value) {
  setCookie("option." + name, value, 1);
}

function getOption(name) {
  return getCookie("option." + name);
}

function getOptionDefaultTrue(name) {
  var theCookie = getCookie("option." + name);
  if (theCookie === "") {
    return true;
  }
  else {
    return theCookie === "true";
  }
}

function failed(jqXHR, textStatus, error) {
  var err = textStatus + ", " + error;
  respondTxt = jqXHR.responseText;
  if (respondTxt.indexOf('Module unavailable or your login session timed out.') != 0) {
    $('#dmessage').append(respondTxt);
    $('#dmessage').css('display', 'block');
  }
  else if (confirm("You are not logged in. Go to login page?")) {
    window.location.href = "?mod=auth";
  }
}

function rmDefaultText(caller, dflt) {
  if ($(caller).val() == dflt) {
    $(caller).val('');
  }
}

function sortList(selector)
{
    var options = $(selector);
    var arr = options.map(function(_, o) { return { t: $(o).text(), v: o.value }; }).get();
    arr.sort(function(o1, o2) { return o1.t.toLowerCase() > o2.t.toLowerCase() ? 1 : o1.t.toLowerCase() < o2.t.toLowerCase() ? -1 : 0; });
    options.each(function(i, o) {
        o.value = arr[i].v;
        $(o).text(arr[i].t);
    });
}
