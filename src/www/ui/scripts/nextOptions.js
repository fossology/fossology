/***********************************************************
 * Copyright (C) 2014 Siemens AG
 * Author: J.Najjar
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



function initializeOption(myvalue) {
    var radios  = $("input[name=FileSelection]:radio");
    radios.filter('[value='+myvalue+']').attr('checked',true);
}

function setOptionOnChange( name ) {
    setOption(name, $('input[name=FileSelection]:radio:checked').val());
}

$(document).ready(function(){
    var theOption = getOption("skipFile");
    if(theOption === "") theOption = "none";
  initializeOption ( theOption);
    $('input[name=FileSelection]:radio').bind('change', function () {
        setOptionOnChange("skipFile");
    });
});