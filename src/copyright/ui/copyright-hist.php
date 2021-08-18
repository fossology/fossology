<?php
/***********************************************************
 * Copyright (C) 2010-2014 Hewlett-Packard Development Company, L.P.
 * Copyright (C) 2014-2017,2019 Siemens AG
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

require_once('HistogramBase.php');

define("TITLE_COPYRIGHTHISTOGRAM", _("Copyright Browser"));

/**
 * @class CopyrightHistogram
 * @brief Create histogram plugin for copyright
 */
class CopyrightHistogram extends HistogramBase {
  function __construct()
  {
    $this->Name = "copyright-hist";
    $this->Title = TITLE_COPYRIGHTHISTOGRAM;
    $this->viewName = "copyright-view";
    $this->agentName = "copyright";
    parent::__construct();
  }

  /**
   * @brief Get contents for copyright table
   * @param int    $upload_pk    Upload id for fetch request
   * @param int    $uploadtreeId Upload tree id of the item
   * @param string $filter       Filter to apply for query
   * @param int    $agentId      Agent id which populate the result
   * @return array Copyright contents, upload tree items in result
   */
  protected function getTableContent($upload_pk, $uploadtreeId, $filter, $agentId)
  {
    $typeDescriptionPairs = array(
      'statement' => _("Agent Findings"),
      'copyFindings' => _("User Findings")
    );
    $tableVars = array();
    $output = array();
    foreach($typeDescriptionPairs as $type=>$description)
    {
      list ($out, $vars) = $this->getTableForSingleType($type, $description,
        $upload_pk, $uploadtreeId, $filter, $agentId);
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
    list ($vCopyright, $vTextFindings, $tableVars) = $this->getTableContent(
      $upload_pk, $Uploadtree_pk, $filter, $agentId);

    $out = $this->renderString('copyrighthist_tables.html.twig',
      array(
        'contCopyright' => $vCopyright,
        'contTextFindings' => $vTextFindings,
        'fileList' => $VF
      ));
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
    if (!empty($Item) && !empty($Upload))
    {
      if (GetParm("mod",PARM_STRING) == $this->Name)
      {
        menu_insert("Browse::Copyright",10);
        menu_insert("Browse::[BREAK]",100);
      }
      else
      {
        $text = _("View copyright histogram");
        menu_insert("Browse::Copyright",10,$URI,$text);
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

    var copyrightTabCookie = 'stickyCopyrightTab';

    $(document).ready(function() {
      tableCopyright =  createTablestatement();
      tableFindings = createPlainTablecopyFindings();
      $('#testReplacementstatement').click(function() {
        testReplacement(tableCopyright, 'statement');
      });
      $('#copyrightFindingsTabs').tabs({
        active: ($.cookie(copyrightTabCookie) || 0),
        activate: function(e, ui){
          // Get active tab index and update cookie
          var idString = $(e.currentTarget).attr('id');
          idString = parseInt(idString.slice(-1)) - 1;
          $.cookie(copyrightTabCookie, idString);
        }
      });
    });
    ";
  }

}

$NewPlugin = new CopyrightHistogram;
