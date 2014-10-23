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

define("TITLE_copyrightHistogram", _("Copyright/Email/URL Browser NEW"));

class CopyrightHistogram  extends HistogramBase {
  function __construct()
  {
    $this->Name = "copyright-hist";
    $this->Title = TITLE_copyrightHistogram;
    $this->viewName = "copyright-view";
    $this->agentName = "copyright";
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
  $type = 'statement';
  $decription = _("Copyright");

  $tableVars=array();

  list($VCopyright, $varsCopyright)  =  $this->getTableForSingleType($type, $decription, $upload_pk, $Uploadtree_pk, $filter, $Agent_pk);
  $tableVars['statement']=$varsCopyright;

  $type = 'email';
  $decription = _("Email");

  list($VEmail, $varsEmail) =  $this->getTableForSingleType($type, $decription, $upload_pk, $Uploadtree_pk, $filter, $Agent_pk);
  $tableVars['email']=$varsEmail;

  $type = 'url';
  $decription = _("URL");

  list($VUrl, $varsURL) =  $this->getTableForSingleType($type, $decription, $upload_pk, $Uploadtree_pk, $filter, $Agent_pk);
  $tableVars['url']=$varsURL;

  return array( $VCopyright, $VEmail, $VUrl, $tableVars);
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
    list($VCopyright, $VEmail, $VUrl, $tableVars) = $this->getTableContent($upload_pk, $Uploadtree_pk, $filter, $Agent_pk);

    /* Combine VF and VLic */
    $text = _("Jump to");
    $text1 = _("Emails");
    $text2 = _("Copyright Statements");
    $text3 = _("URLs");
    $V = "<table border=0 width='100%'>\n";
    $V .= "<tr><td><a name=\"statements\"></a>$text: <a href=\"#emails\">$text1</a> | <a href=\"#urls\">$text3</a></td><td></td></tr>\n";
    $V .= "<tr><td valign='top' width='50%'>$VCopyright</td><td valign='top'>$VF</td></tr>\n";
    $V .= "<tr><td><a name=\"emails\"></a>Jump to: <a href=\"#statements\">$text2</a> | <a href=\"#urls\">$text3</a></td><td></td></tr>\n";
    $V .= "<tr><td valign='top' width='50%'>$VEmail</td><td valign='top'></td></tr>\n";
    $V .= "<tr><td><a name=\"urls\"></a>Jump To: <a href=\"#statements\">$text2</a> | <a href=\"#emails\">$text1</a></td><td></td></tr>\n";
    $V .= "<tr><td valign='top' width='50%'>$VUrl</td><td valign='top'></td></tr>\n";
    $V .= "</table>\n";
    $V .= "<hr />\n";
    return array($V, $tableVars);
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
        menu_insert("Browse::Copyright/Email/URL",1);
        menu_insert("Browse::[BREAK]",100);
      }
      else
      {
        $text = _("View copyright/email/url histogram");
        menu_insert("Browse::Copyright/Email/URL",10,$URI,$text);
      }
    }
  } // RegisterMenus()



  protected  function createScriptBlock()
  {
    return "

    $(document).ready(function() {
      tableCopyright =  createTablestatement();
      tableEmail = createTableemail();
      tableUrl =createTableurl();
    } );

    ";

  }



}

$NewPlugin = new CopyrightHistogram;
//$NewPlugin->Initialize();