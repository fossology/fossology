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

define("TITLE_ipHistogram", _("Patent Browser"));

class IpHistogram  extends HistogramBase {
  function __construct()
  {
    $this->Name = "ip-hist";
    $this->Title = TITLE_ipHistogram;
    $this->viewName = "ip-view";
    $this->agentName = "ip";
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
    $type = 'ip';
    $decription = _("Patent");
    $descriptionTotal = _("Total Patents");

    $VPatent  =  $this->getTableForSingleType($type, $decription, $descriptionTotal, $upload_pk, $Uploadtree_pk, $filter, $Agent_pk);
    return  $VPatent;
  }

  /**
   * @param $upload_pk
   * @param $Uploadtree_pk
   * @param $filter
   * @param $Agent_pk
   * @param $VF
   * @return string
   */
  protected function fillTables($upload_pk, $Uploadtree_pk, $filter, $Agent_pk, $VF)
  {
    $VPatent = $this->getTableContent($upload_pk, $Uploadtree_pk, $filter, $Agent_pk);


    $V = "<table border=0 width='100%'>\n";
    $V .= "<tr><td valign='top' width='50%'>$VPatent</td><td valign='top'>$VF</td></tr>\n";
    $V .= "</table>\n";
    $V .= "<hr />\n";
    return $V;
  }

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
        menu_insert("Browse::Patents",1);
        menu_insert("Browse::[BREAK]",100);
      }
      else
      {
        $text = _("View patent histogram");
        menu_insert("Browse::Patents",10,$URI,$text);
      }
    }
  } // RegisterMenus()

  protected  function createScriptBlock()
  {
    return "

    $(document).ready(function() {
      tablePatents =  createTableip();
    } );

    ";

  }

}

$NewPlugin = new IpHistogram;
//$NewPlugin->Initialize();