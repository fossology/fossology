<?php
/***********************************************************
 * Copyright (C) 2010-2014 Hewlett-Packard Development Company, L.P.
 * Copyright (C) 2014-2015 Siemens AG
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

define("TITLE_copyrightHistogram", _("Copyright/Email/URL/Author Browser"));

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
   * @param $uploadtreeId
   * @param $filter
   * @param $agentId
   * @return array
   */
  protected function getTableContent($upload_pk, $uploadtreeId, $filter, $agentId)
  {
    $type = 'statement';
    $description = _("Copyright");

    $tableVars=array();

    list($VCopyright, $varsCopyright)  =  $this->getTableForSingleType($type, $description, $upload_pk, $Uploadtree_pk, $filter, $Agent_pk);
    $tableVars[$type]=$varsCopyright;

    $type = 'email';
    $description = _("Email");

    list($VEmail, $varsEmail) =  $this->getTableForSingleType($type, $description, $upload_pk, $Uploadtree_pk, $filter, $Agent_pk);
    $tableVars[$type]=$varsEmail;

    $type = 'url';
    $description = _("URL");

    list($VUrl, $varsURL) =  $this->getTableForSingleType($type, $description, $upload_pk, $Uploadtree_pk, $filter, $Agent_pk);
    $tableVars[$type]=$varsURL;

    $type = 'author';
    $description = _("Author");

    list($VAuthor, $varsAuthor) =  $this->getTableForSingleType($type, $description, $upload_pk, $Uploadtree_pk, $filter, $Agent_pk);
    $tableVars[$type]=$varsAuthor;

    return array( $VCopyright, $VEmail, $VUrl, $VAuthor, $tableVars);
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
    list($VCopyright, $VEmail, $VUrl, $VAuthor, $tableVars) = $this->getTableContent($upload_pk, $Uploadtree_pk, $filter, $agentId);

    /* Combine VF and VLic */
    $text = _("Jump to");
    $text1 = _("Copyright Statements");
    $text2 = _("Emails");
    $text3 = _("URLs");
    $text4 = _("Authors or Maintainers");
    $V = "<table border=0 width='100%'>\n";
    $V .= "<tr><td><a name=\"statements\"></a>$text: <a href=\"#emails\">$text2</a> | <a href=\"#urls\">$text3</a> | <a href=\"#authors\">$text4</a></td><td></td></tr>\n";
    $V .= "<tr><td valign='top' width='50%'>$VCopyright</td><td valign='top'>$VF</td></tr>\n";
    $V .= "<tr><td><a name=\"emails\"></a>$text: <a href=\"#statements\">$text1</a> | <a href=\"#urls\">$text3</a> | <a href=\"#authors\">$text4</a></td><td></td></tr>\n";
    $V .= "<tr><td valign='top' width='50%'>$VEmail</td><td valign='top'></td></tr>\n";
    $V .= "<tr><td><a name=\"urls\"></a>$text: <a href=\"#statements\">$text1</a> | <a href=\"#emails\">$text2</a> | <a href=\"#authors\">$text4</a></td><td></td></tr>\n";
    $V .= "<tr><td valign='top' width='50%'>$VUrl</td><td valign='top'></td></tr>\n";
    $V .= "<tr><td><a name=\"authors\"></a>$text: <a href=\"#statements\">$text1</a> | <a href=\"#emails\">$text2</a> | <a href=\"#urls\">$text3</a></td><td></td></tr>\n";
    $V .= "<tr><td valign='top' width='50%'>$VAuthor</td><td valign='top'></td></tr>\n";
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


  protected function createScriptBlock()
  {
    return "

    $(document).ready(function() {
      tableCopyright =  createTablestatement();
      tableEmail = createTableemail();
      tableUrl = createTableurl();
      tableAuthor = createTableauthor();
    } );

    ";
  }

}

$NewPlugin = new CopyrightHistogram;
