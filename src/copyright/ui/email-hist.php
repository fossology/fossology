<?php
/*
 SPDX-FileCopyrightText: © 2010-2014 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014-2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

require_once('HistogramBase.php');

define("TITLE_EMAILHISTOGRAM", _("Email/URL/Author Browser"));

class EmailHistogram extends HistogramBase
{
  function __construct()
  {
    $this->Name = "email-hist";
    $this->Title = TITLE_EMAILHISTOGRAM;
    $this->viewName = "email-view";
    $this->agentName = "copyright";
    parent::__construct();
  }

  /**
   * @brief Get contents for author table
   * @param int    $upload_pk    Upload id for fetch request
   * @param int    $uploadtreeId Upload tree id of the item
   * @param string $filter       Filter to apply for query
   * @param int    $agentId      Agent id which populate the result
   * @return array Email contents, upload tree items in result
   */
  protected function getTableContent($upload_pk, $uploadtreeId, $filter, $agentId)
  {
    $typeDescriptionPairs = array(
            'email' => _("Email"),
            'url' => _("URL"),
            'author' => _("Author"),
            'scancode_author' => _("Author"),
            'scancode_url' => _("URL"),
            'scancode_email' => _("Email")
      );

    $tableVars = array();
    $output = array();
    foreach ($typeDescriptionPairs as $type=>$description) {
      if ($type =="scancode_author" || $type =="scancode_email" || $type =="scancode_url") {
        $agentId=LatestAgentpk($upload_pk, 'scancode_ars');
        $this->agentName = "scancode";
      }
      list($out, $vars) = $this->getTableForSingleType($type, $description, $upload_pk, $uploadtreeId, $filter, $agentId);
      $tableVars[$type] = $vars;
      $output[] = $out;
    }

    $output[] = $tableVars;
    return $output;
  }


  /**
   * @copydoc HistogramBase::fillTables()
   * @see HistogramBase::fillTables()
   */
  protected function fillTables($upload_pk, $Uploadtree_pk, $filter, $agentId, $VF)
  {
    list($VEmail, $VUrl, $VAuthor, $VScanAuthor, $VScanUrl, $VScanEmail, $tableVars) = $this->getTableContent($upload_pk, $Uploadtree_pk, $filter, $agentId);

    $out = $this->renderString('emailhist_tables.html.twig',
            array('contEmail'=>$VEmail,
            'contUrl'=>$VUrl,
            'contAuthor'=>$VAuthor,
            'contScanAuthor' => $VScanAuthor,
            'contScanUrl' => $VScanUrl,
            'contScanEmail' => $VScanEmail,
            'fileList'=>$VF));
    return array($out, $tableVars);
  }

  /**
   * @copydoc FO_Plugin::RegisterMenus()
   * @see FO_Plugin::RegisterMenus()
   */
  function RegisterMenus()
  {
    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array("show","format","page","upload","item"));
    $Item = GetParm("item",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    if (!empty($Item) && !empty($Upload)) {
      if (GetParm("mod",PARM_STRING) == $this->Name) {
        menu_insert("Browse::Email/URL/Author",10);
        menu_insert("Browse::[BREAK]",100);
      } else {
        $text = _("View email/URL/author histogram");
        menu_insert("Browse::Email/URL/Author",10,$URI,$text);
      }
    }
  }

  /**
   * @copydoc HistogramBase::createScriptBlock()
   * @see HistogramBase::createScriptBlock()
   */

  protected function createScriptBlock()
  {
    return "

    var emailTabCookie = 'stickyEmailTab';
    var emailTabFossCookie = 'stickyEmailFossTab';
    var emailTabScanCookie = 'stickyEmailScanTab';
    $(document).ready(function() {
      tableEmail = createTableemail();
      tableUrl = createTableurl();
      tableAuthor = createTableauthor();
      tableScanEmail = createTablescancode_email();
      tableScanUrl = createTablescancode_url();
      tableScanAuthor = createTablescancode_author();
      $('#testReplacementemail').click(function() {
        testReplacement(tableEmail, 'email');
      });
      $('#testReplacementurl').click(function() {
        testReplacement(tableUrl, 'url');
      });
      $('#testReplacementauthor').click(function() {
        testReplacement(tableAuthor, 'author');
      });
      $('#testReplacementScanemail').click(function() {
        testReplacement(tableScanEmail, 'email');
      });
      $('#testReplacementScanurl').click(function() {
        testReplacement(tableScanUrl, 'url');
      });
      $('#testReplacementScanauthor').click(function() {
        testReplacement(tableScanAuthor, 'author');
      });
      $('#EmailUrlAuthorTabs').tabs({
        active: ($.cookie(emailTabCookie) || 0),
        activate: function(e, ui){
          // Get active tab index and update cookie
          var idString = $(e.currentTarget).attr('id');
          idString = parseInt(idString.slice(-1)) - 1;
          $.cookie(emailTabCookie, idString);
        }
      });
      $('#FossEmailUrlAuthorTabs').tabs({
        active: ($.cookie(emailTabFossCookie) || 0),
        activate: function(e, ui){
          // Get active tab index and update cookie
          var tabIdFoss = $(ui.newPanel).attr('id');
          var idStringFoss = 0;
          if (tabIdFoss == 'FossEmailTab') {
            idStringFoss = 0;
          } else if (tabIdFoss == 'FossUrlTab') {
            idStringFoss = 1;
          } else if (tabIdFoss == 'FossAuthorTab') {
            idStringFoss = 2;
          }
          $.cookie(emailTabFossCookie, idStringFoss);
        }
      });
      $('#ScanEmailUrlAuthorTabs').tabs({
        active: ($.cookie(emailTabScanCookie) || 0),
        activate: function(e, ui){
          // Get active tab index and update cookie
          var tabIdScan = $(ui.newPanel).attr('id');
          var idStringScan = 0;
          if (tabIdScan == 'ScanEmailTab') {
            idStringScan = 0;
          } else if (tabIdScan == 'ScanUrlTab') {
            idStringScan = 1;
          } else if (tabIdScan == 'ScanAuthorTab') {
            idStringScan = 2;
          }
          $.cookie(emailTabScanCookie, idStringScan);
        }
      });
    });
    ";
  }
}

$NewPlugin = new EmailHistogram;
