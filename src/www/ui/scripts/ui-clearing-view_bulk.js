/*
 SPDX-FileCopyrightText: © 2015, 2018 Siemens AG
 Author: maximilian.huber@tngtech.com

 SPDX-License-Identifier: GPL-2.0-only
*/

var bulkFormTableContent = (function(){
  var content = [];
  function updateTable(){
    var s = "";
    var uploadTreeId = $('#uploadTreeId').val();
    for (i = 0; i < content.length; ++i) {
      var licenseTitle = content[i].reportinfo || '';
      var licenseText = licenseTitle.length > 0 ? licenseTitle.slice(0, 10) + "..." : 'Click to add';
      var ackTitle = content[i].acknowledgement || '';
      var ackText = ackTitle.length > 0 ? ackTitle.slice(0, 10) + "..." : 'Click to add';
      var commentTitle = content[i].comment || '';
      var commentText = commentTitle.length > 0 ? commentTitle.slice(0, 10) + "..." : 'Click to add';

      s += `<tr class='${(i % 2 == 1) ? "even" : "odd"}'>
        <td align='center'>${content[i].action}</td>
        <td>${content[i].licenseName}</td>
        <td><a href='javascript:;' style='color:#000;'
          id='${content[i].licenseId}reportinfoBulk'
          onClick="openTextModel('${uploadTreeId}', ${content[i].licenseId},
            'reportinfo', 'Bulk');" title='${licenseTitle}'>
          ${licenseText}</a></td>
        <td><a href='javascript:;' style='color:#000;'
          id='${content[i].licenseId}acknowledgementBulk'
          onClick="openTextModel('${uploadTreeId}', ${content[i].licenseId},
            'acknowledgement', 'Bulk');" title='${ackTitle}'>
          ${ackText}</a></td>
        <td><a href='javascript:;' style='color:#000;'
          id='${content[i].licenseId}commentBulk'
          onClick="openTextModel('${uploadTreeId}', ${content[i].licenseId},
            'comment', 'Bulk');" title='${commentTitle}'>
          ${commentText}</a></td>
        <td><a href='#'
          onClick='bulkFormTableContent[2](${content[i].licenseId})'>
          <img src='images/icons/remove_16.png'
            title='Remove selected license row' alt='-' /></a></td>
      </tr>`;
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
          action: "Add",
          reportinfo: '',
          acknowledgement: '',
          comment: ''
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
          action: "Remove",
          reportinfo: '',
          acknowledgement: '',
          comment: ''
          });
    }
      updateTable();
  }
  function getContent(){
      return content;
  }
  function setLicenseText(licenseId, field, text) {
    for (var i = 0; i < content.length; i++) {
      if (content[i].licenseId === licenseId) {
        content[i][field] = text;
        var displayText = text.trim() !== '' ? text.slice(0, 10) + "..." : 'Click to add';
        $('#' + licenseId + field + 'Bulk').attr('title', text).html(displayText);
        break;
      }
    }
  }
    return [addLicense, rmLicense, removeOldEntry, getContent, setLicenseText];
}());

$('#bulkFormAddLicense').click(function(){ bulkFormTableContent[0](); });
$('#bulkFormRmLicense').click(function(){ bulkFormTableContent[1](); });

function getBulkFormTableContent(){ return bulkFormTableContent[3](); }
function setBulkLicenseText(licenseId, field, text){ bulkFormTableContent[4](licenseId, field, text); }
