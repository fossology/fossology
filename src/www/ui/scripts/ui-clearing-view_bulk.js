/*
 Copyright (C) 2015, Siemens AG
 Author: maximilian.huber@tngtech.com

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

var bulkFormTableContent = (function(){
    var content = [];
    function updateTable(){
        var s = "";
        for (i = 0; i < content.length; ++i) {
            s += "<tr class=\"" + ((i % 2 == 1) ? "even" : "odd") + "\">"
               +    "<td style=\"border-right:1px dashed black;\">" + content[i].action + "</td>"
               +    "<td>" + content[i].licenseName + "</td>"
               +    "<td align=\"right\"><a href='#' onclick='bulkFormTableContent[2](" + content[i].licenseId + ")'>[remove row]</a></td>"
               + "</tr>";
        }
        $('#bulkFormTable tbody').html(s);
    }
    function maybeRemoveOldEntry(lic){
        for (i = 0; i < content.length; ++i) {
            if (content[i].licenseId === lic){
                content.splice(i, 1);
                return;
            }
        }
    }
    function removeOldEntry(lic){
        maybeRemoveOldEntry(lic);
        updateTable();
    }
    function addLicense(){
        var lic = parseInt($('#bulkLicense').val(), 10)
        if(lic > 0){
            maybeRemoveOldEntry(lic);
            content.push({
                licenseId: lic,
                licenseName: $('#bulkLicense option:selected').text(),
                action: "add"
            });
        }
        updateTable();
    }
    function rmLicense(){
        var lic = parseInt($('#bulkLicense').val(), 10)
        if(lic > 0){
            maybeRemoveOldEntry(lic);
            content.push({
                licenseId: lic,
                licenseName: $('#bulkLicense option:selected').text(),
                action: "remove"
            });
        }
        updateTable();
    }
    function getContent(){
        return content;
    }
    return [addLicense,rmLicense,removeOldEntry,getContent];
}());

$('#bulkFormAddLicense').click(function(){ bulkFormTableContent[0](); });
$('#bulkFormRmLicense').click(function(){ bulkFormTableContent[1](); });

function getBulkFormTableContent(){ return bulkFormTableContent[3](); }
