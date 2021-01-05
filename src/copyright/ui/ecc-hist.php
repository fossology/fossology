<?php
/***********************************************************
 * Copyright (C) 2010-2014 Hewlett-Packard Development Company, L.P.
 * Copyright (C) 2014 Siemens AG
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

define("TITLE_ECCHISTOGRAM", _("Export restriction Browser"));

/**
 * @class EccHistogram
 * @brief Create UI plugin for ecc agent
 */
class EccHistogram  extends HistogramBase {
  function __construct()
  {
    $this->Name = "ecc-hist";
    $this->Title = TITLE_ECCHISTOGRAM;
    $this->viewName = "ecc-view";
    $this->agentName = "ecc";
    parent::__construct();
  }

  /**
   * @brief Get contents for ecc table
   * @param int    $upload_pk     Upload id for fetch request
   * @param int    $Uploadtree_pk Upload tree id of the item
   * @param string $filter        Filter to apply for query
   * @param int    $Agent_pk      Agent id which populate the result
   * @return array Ecc contents, upload tree items in result
   */
  protected  function getTableContent($upload_pk, $Uploadtree_pk, $filter, $Agent_pk)
  {
    $type = 'ecc';
    $decription = _("Export restriction");

    $tableVars=array();
    list($VEcc, $eccVars)  =  $this->getTableForSingleType($type, $decription, $upload_pk, $Uploadtree_pk, $filter, $Agent_pk);
    $tableVars['ecc']=$eccVars;
    return  array($VEcc,$tableVars);
  }

  /**
   * @copydoc HistogramBase::fillTables()
   * @see HistogramBase::fillTables()
   */
  protected function fillTables($upload_pk, $Uploadtree_pk, $filter, $agentId, $VF)
  {
    list($VEcc, $tableVars) = $this->getTableContent($upload_pk, $Uploadtree_pk, $filter, $agentId);

    $V = "<table border=0 width='100%'>\n";
    $V .= "<tr><td valign='top'>$VEcc</td><td valign='top'>$VF</td></tr>\n";
    $V .= "</table>\n";
    $V .= "<hr />\n";
    return array($V,$tableVars);
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
        menu_insert("Browse::ECC",10);
        menu_insert("Browse::[BREAK]",100);
      }
      else
      {
        $text = _("View ECC histogram");
        menu_insert("Browse::ECC",10,$URI,$text);
      }
    }
  } // RegisterMenus()

  /**
   * @copydoc HistogramBase::createScriptBlock()
   * @see HistogramBase::createScriptBlock()
   */
  protected  function createScriptBlock()
  {
    return "

    $(document).ready(function() {
      tableEcc =  createTableecc();
      $('#testReplacementecc').click(function() {
        testReplacement(tableEcc, 'ecc');
      });
    });

    ";

  }

}

$NewPlugin = new EccHistogram;
