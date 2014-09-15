/*
 Copyright (C) 2014, Siemens AG
 Author: Johannes Najjar

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

function setCookie(cookieName, cookieValue, exdays) {
    var date = new Date();
    date.setTime(date.getTime() + (exdays*24*60*60*1000));
    var expires = "expires="+date.toUTCString();
    document.cookie = cookieName + "=" + cookieValue + "; " + expires;
}

function getCookie(cookieName) {
    var name = cookieName + "=";
    var allCookies = document.cookie.split(';');
    for(var i=0; i<allCookies.length; i++) {
        var theCookie = allCookies[i];
        while (theCookie.charAt(0)==' ') theCookie = theCookie.substring(1);
        if (theCookie.indexOf(name) != -1) return theCookie.substring(name.length,theCookie.length);
    }
    return "";
}

function setOption ( name , value ) {
    setCookie("option."+name, value, 1);
}

function getOption(name) {
    return getCookie("option."+name);
}

function getOptionDefaultTrue(name) {
    var theCookie= getCookie("option."+name);
    if(theCookie === "") {
        return true;
    }
    else {
        return theCookie === "true";
    }
}