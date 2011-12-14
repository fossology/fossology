<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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
 ***********************************************************/

define("TITLE_search_file", _("Search for File"));

class search_file extends FO_Plugin
{
  var $Name       = "search_file";
  var $Title      = TITLE_search_file;
  var $Version    = "1.0";
  var $MenuList   = "Search";
  var $MenuOrder  = 90;
  var $Dependency = array("view","browse");
  var $DBaccess   = PLUGIN_DB_READ;
  public $LoginFlag  = 0;

  function PostInitialize()
  {
    /* This plugin is only valid if the system allows global searching */
    if (IsRestrictedTo())
    $this->State = PLUGIN_STATE_INVALID;
    else
    $this->State = PLUGIN_STATE_READY;
    return $this->State;
  }

  /**
   * \brief Customize submenus.
   */ 
  function RegisterMenus()
  {
    menu_insert("Main::" . $this->MenuList,$this->MenuOrder,$this->Name,$this->MenuTarget);
  } // RegisterMenus()

  /**
   * \brief Given a name, return all records.
   */
  function GetUploadtreeFromName($Filename,$Page, $ContainerOnly=1)
  {
    global $PG_CONN;
    $Max = 50;
    $Filename = str_replace("'","''",$Filename); // protect DB
    $Terms = split("[[:space:]][[:space:]]*",$Filename);
    $SQL = "SELECT * FROM uploadtree
      INNER JOIN pfile ON pfile_fk = pfile_pk";
    if ($ContainerOnly) $SQL .= " AND ((uploadtree.ufile_mode & (1<<29)) != 0) ";
    foreach($Terms as $Key => $T)
    {
      $SQL .= " AND ufile_name LIKE '$T'";
    }
    $Offset = $Page * $Max;
    $SQL .= " ORDER BY pfile_pk,ufile_name DESC LIMIT $Max OFFSET $Offset;";
    $result = pg_query($PG_CONN, $SQL);
    DBCheckResult($result, $SQL, __FILE__, __LINE__);
    $V = "";
    $Count = pg_num_rows($result);

    if (($Page > 0) || ($Count >= $Max))
    {
      $Uri = Traceback_uri() . "?mod=" . $this->Name;
      $Uri .= "&filename=" . urlencode($Filename);
      $Uri .= "&allfiles=" . GetParm("allfiles",PARM_INTEGER);
      $VM = MenuEndlessPage($Page, ($Count >= $Max),$Uri) . "<P />\n";
      $V .= $VM;
    }
    else
    {
      $VM = "";
    }

    if ($Count == 0)
    {
      $V .= _("No results.\n");
      pg_free_result($result);
      return($V);
    }

    $V .= Dir2FileList($result,"browse","view",$Page*$Max + 1);
    pg_free_result($result);

    /* put page menu at the bottom, too */
    if (!empty($VM)) { $V .= "<P />\n" . $VM; }
    return($V);
  } // GetUploadtreeFromName()

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $Plugins;
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $V .= menu_to_1html(menu_find("Search",$MenuDepth),1);

        $Filename = GetParm("filename",PARM_STRING);
        $Page = GetParm("page",PARM_INTEGER);
        $allfiles = GetParm("allfiles",PARM_INTEGER);
        $Uri = preg_replace("/&filename=[^&]*/","",Traceback());
        $Uri = preg_replace("/&page=[^&]*/","",$Uri);

        $V .= _("You can use '%' as a wild-card.\n");
        $V .= "<form action='$Uri' method='POST'>\n";
        $V .= "<ul>\n";
        $text = _("Enter the filename to find:");
        $V .= "<li>$text<P>";
        $V .= "<INPUT type='text' name='filename' size='40' value='" . htmlentities($Filename) . "'>\n";
        $text = _("By default only containers (rpms, tars, isos, etc) are shown.");
        $V .= "<li>$text<P>";
        $text = _("Show All Files");
        $V .= "<INPUT type='checkbox' name='allfiles' value='1'";
        if ($allfiles == '1') { $V .= " checked"; }
        $V .= "> $text\n";
        $V .= "</ul>\n";
        $text = _("Search");
        $V .= "<input type='submit' value='$text!'>\n";
        $V .= "</form>\n";

        if (!empty($Filename))
        {
          if (empty($Page)) { $Page = 0; }
          if (empty($allfiles)) { $ContainerOnly = 1; }
          $V .= "<hr>\n";
          $text = _("Files matching");
          $V .= "<H2>$text " . htmlentities($Filename) . "</H2>\n";
          $V .= $this->GetUploadtreeFromName($Filename,$Page, $ContainerOnly);
        }
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
  } // Output()

};
$NewPlugin = new search_file;
?>
