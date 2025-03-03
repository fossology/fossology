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
      // Set defaults
      var licenseTitle = $(`#${content[i].licenseId}reportinfoBulk`)
        .attr('title');
      var licenseText = 'Click to add';
      var ackTitle = $(`#${content[i].licenseId}acknowledgementBulk`)
        .attr('title');
      var ackText = 'Click to add';
      var commentTitle = $(`#${content[i].licenseId}commentBulk`)
        .attr('title');
      var commentText = 'Click to add';

      // If titles are undefined, make them empty string
      licenseTitle ??= "";
      ackTitle ??= "";
      commentTitle ??= "";

      // Change display text if value exists
      if (licenseTitle.length != 0) {
        licenseText = licenseTitle.slice(0, 10) + "...";
      }
      if (ackTitle.length != 0) {
        ackText = ackTitle.slice(0, 10) + "...";
      }
      if (commentTitle.length != 0) {
        commentText = commentTitle.slice(0, 10) + "...";
      }

      s += `<tr class='${(i % 2 == 1) ? "even" : "odd"}'>
        <td align='center'>${content[i].action}</td>
        <td ${(content[i].isExpression) ? 'colspan="2"' : ''}>${content[i].licenseName}</td>
        <td ${(content[i].isExpression) ? 'style="display: none;"' : ''}><a href='javascript:;' style='color:#000;'
          id='${content[i].licenseId}reportinfoBulk'
          onClick="openTextModel(${uploadTreeId}, ${content[i].licenseId},
            'reportinfo', 'Bulk');" title='${licenseTitle}'>
          ${licenseText}</a></td>
        <td ${(content[i].isExpression) ? 'colspan="2"' : ''}><a href='javascript:;' style='color:#000;'
          id='${content[i].licenseId}acknowledgementBulk'
          onClick="openTextModel(${uploadTreeId}, ${content[i].licenseId},
            'acknowledgement', 'Bulk');" title='${ackTitle}'>
          ${ackText}</a></td>
        <td ${(content[i].isExpression) ? 'style="display: none;"' : ''}><a href='javascript:;' style='color:#000;'
          id='${content[i].licenseId}commentBulk'
          onClick="openTextModel(${uploadTreeId}, ${content[i].licenseId},
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
          action: "Add"
          });
    }
      updateTable();
  }
  function addExpressionBulk(id, text) {
    for (i = 0; i < content.length; ++i) {
      if (content[i].isExpression){
        content.splice(i, 1);
      }
    }
    content.unshift({
      licenseId: id,
      licenseName: text,
      action: "Add",
      isExpression: true
    });
    updateTable();
  }
  function rmLicense(){
      var lic = parseInt($('#bulkLicense').val(), 10)
    if(lic > 0){
        maybeRemoveOldEntry(lic);
        content.push({
          licenseId: lic,
          licenseName: $('#bulkLicense option:selected').text(),
          action: "Remove"
          });
    }
      updateTable();
  }
  function getContent(){
      return content;
  }
    return [addLicense,rmLicense,removeOldEntry,getContent,addExpressionBulk];
}());

$('#bulkFormAddLicense').click(function(){ bulkFormTableContent[0](); });
$('#bulkFormRmLicense').click(function(){ bulkFormTableContent[1](); });

function getBulkFormTableContent(){ return bulkFormTableContent[3](); }
