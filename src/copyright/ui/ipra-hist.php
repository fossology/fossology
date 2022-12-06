<?php
/*
 SPDX-FileCopyrightText: © 2022 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

require_once('HistogramBase.php');

define("TITLE_IPRAHISTOGRAM", _("Patent Relevant Browser"));

class IpraHistogram extends HistogramBase
{
  function __construct()
  {
    $this->Name = "ipra-hist";
    $this->Title = TITLE_IPRAHISTOGRAM;
    $this->viewName = "ipra-view";
    $this->agentName = "ipra";
    parent::__construct();
  }

  /**
   * @param $upload_pk
   * @param $Uploadtree_pk
   * @param $filter
   * @param $Agent_pk
   * @return array
   */
  protected  function getTableContent($upload_pk, $Uploadtree_pk, $filter, $Agent_pk)
  {
    $type = 'ipra';
    $decription = _("Patent Relevant");

    $tableVars=array();
    list($VPatentRelevant,$ipraVars) = $this->getTableForSingleType($type, $decription, $upload_pk, $Uploadtree_pk, $filter, $Agent_pk);
    $tableVars['ipra']=$ipraVars;
    return  array($VPatentRelevant,$tableVars);
  }

  /**
   * @param $upload_pk
   * @param $Uploadtree_pk
   * @param $filter
   * @param $agentId
   * @param $VF
   * @return string
   */
  protected function fillTables($upload_pk, $Uploadtree_pk, $filter, $agentId, $VF)
  {
    list($VPatentRelevant, $tableVars) = $this->getTableContent($upload_pk, $Uploadtree_pk, $filter, $agentId);

    $V = "<table border=0 width='100%'>\n";
    $V .= "<tr><td valign='top' width='50%'>$VPatentRelevant</td><td valign='top'>$VF</td></tr>\n";
    $V .= "</table>\n";
    $V .= "<hr />\n";
    return array($V,$tableVars);
  }

  function RegisterMenus()
  {
    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array("show","format","page","upload","item"));
    $Item = GetParm("item",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    if (!empty($Item) && !empty($Upload)) {
      if (GetParm("mod",PARM_STRING) == $this->Name) {
        menu_insert("Browse::IPRA",10);
        menu_insert("Browse::[BREAK]",100);
      } else {
        $text = _("View patent relevant histogram");
        menu_insert("Browse::IPRA",10,$URI,$text);
      }
    }
  } // RegisterMenus()

  protected  function createScriptBlock()
  {
    return "

    $(document).ready(function() {
      tablePatents =  createTableipra();
    } );

    ";

  }
}

$NewPlugin = new IpraHistogram;
