<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

class ui_view_info extends FO_Plugin
  {
  var $Name       = "view_info";
  var $Title      = "View File Information";
  var $Version    = "1.0";
  var $Dependency = array("db","browse");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;

  /***********************************************************
   RegisterMenus(): Customize submenus.
   ***********************************************************/
  function RegisterMenus()
    {
    // For the Browse menu, permit switching between detail and summary.
    $Parm = Traceback_parm_keep(array("upload","item","format"));
    $URI = $this->Name . $Parm;
    if (GetParm("mod",PARM_STRING) == $this->Name)
	{
	menu_insert("View::Info",1);
	menu_insert("View-Meta::Info",1);
	}
    else
	{
	menu_insert("View::Info",1,$URI,"View summary information about this file");
	menu_insert("View-Meta::Info",1,$URI,"View summary information about this file");
	}
    } // RegisterMenus()

  /***********************************************************
   ShowView(): Display the info data associated with the file.
   ***********************************************************/
  function ShowView($ShowMenu=0,$ShowHeader=0)
  {
    global $DB;
    $V = "";
    $Folder = GetParm("folder",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);
    if (empty($Upload) || empty($Item)) { return; }

    $Page = GetParm("page",PARM_INTEGER);
    if (empty($Page)) { $Page=0; }
    $Max = 50;
    $Offset = $Page * $Max;

    /**********************************
     Display micro header
     **********************************/
    if ($ShowHeader)
      {
      $V .= Dir2Browse("browse",$Item,NULL,1,"View-Meta");
      } // if ShowHeader

    /**********************************
     List File Info
     **********************************/
    if ($Page == 0)
      {
      $V .= "<H2>Information</H2>\n";
      $SQL = "SELECT * FROM uploadtree
	INNER JOIN pfile ON uploadtree_pk = $Item
	AND pfile_fk = pfile_pk
	LIMIT 1;";
      $Results = $DB->Action($SQL);
      $R = &$Results[0];
      $V .= "<table border=1>\n";
      $V .= "<tr><th>Attribute</th><th>Value</th></tr>\n";
      $Bytes = $R['pfile_size'];
      $BytesH = Bytes2Human($Bytes);
      $Bytes = number_format($Bytes, 0, "", ",");
      if ($BytesH == $Bytes) { $BytesH = ""; }
      else { $BytesH = '(' . $BytesH . ')'; }
      $V .= "<tr><td align='center'>File Size</td><td align='right'>$Bytes $BytesH</td></tr>\n";
      $V .= "<tr><td align='center'>SHA1 Checksum</td><td align='right'>" . $R['pfile_sha1'] . "</td></tr>\n";
      $V .= "<tr><td align='center'>MD5 Checksum</td><td align='right'>" . $R['pfile_md5'] . "</td></tr>\n";
      $V .= "<tr><td align='center'>Repository ID</td><td align='right'>" . $R['pfile_sha1'] . "." . $R['pfile_md5'] . "." . $R['pfile_size'] . "</td></tr>\n";
      $V .= "<tr><td align='center'>Pfile ID</td><td align='right'>" . $R['pfile_fk'] . "</td></tr>\n";
      $V .= "</table>\n";
      }

    /**********************************
     List the directory locations where this pfile is found
     **********************************/
    $V .= "<H2>Sightings</H2>\n";
    $SQL = "SELECT * FROM pfile,uploadtree
	WHERE pfile_pk=pfile_fk
	AND pfile_pk IN
	(SELECT pfile_fk FROM uploadtree WHERE uploadtree_pk = $Item)
	LIMIT $Max OFFSET $Offset";
    $Results = $DB->Action($SQL);
    $Count = count($Results);
    if (($Page > 0) || ($Count >= $Max))
      {
      $VM = "<P />\n" . MenuEndlessPage($Page, ($Count >= $Max)) . "<P />\n";
      }
    else { $VM = ""; }
    if ($Count > 0)
	{
	$V .= "This exact file appears in the following locations:\n";
	$V .= $VM;
	$Offset++;
	$V .= Dir2FileList($Results,"browse","view",$Offset);
	$V .= $VM;
	}
    else if ($Page > 0)
	{
	$V .= "End of listing.\n";
	}
    else
	{
	$V .= "This file does not appear in any other known location.\n";
	}

    return($V);
  } // ShowView()

  /***********************************************************
   Output(): This function is called when user output is
   requested.  This function is responsible for content.
   (OutputOpen and Output are separated so one plugin
   can call another plugin's Output.)
   This uses $OutputType.
   The $ToStdout flag is "1" if output should go to stdout, and
   0 if it should be returned as a string.  (Strings may be parsed
   and used by other plugins.)
   ***********************************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$V .= $this->ShowView(1,1);
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
    }

  };
$NewPlugin = new ui_view_info;
$NewPlugin->Initialize();
?>
