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

class ui_view_meta extends Plugin
  {
  var $Name       = "view_meta";
  var $Version    = "1.0";
  var $Dependency = array("db","browse");
  var $DBaccess   = PLUGIN_DB_READ;

  /***********************************************************
   RegisterMenus(): Customize submenus.
   ***********************************************************/
  function RegisterMenus()
    {
    // For the Browse menu, permit switching between detail and summary.
    $Parm = Traceback_parm_keep(array("upload","item","ufile","pfile","format"));
    $URI = $this->Name . $Parm;
    if (GetParm("mod",PARM_STRING) == $this->Name)
	{
	menu_insert("View::Meta",1);
	menu_insert("View-Meta::Meta",1);
	}
    else
	{
	menu_insert("View::Meta",1,$URI);
	menu_insert("View-Meta::Meta",1,$URI);
	}
    } // RegisterMenus()

  /***********************************************************
   ShowView(): Display the meta data associated with the file.
   ***********************************************************/
  function ShowView($ShowMenu=0,$ShowHeader=0)
  {
    global $DB;
    $V = "";
    $Pfile = GetParm("pfile",PARM_INTEGER);
    $Ufile = GetParm("ufile",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    $Folder = GetParm("folder",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);
    if (empty($Item) || empty($Pfile) || empty($Ufile) || empty($Upload))
	{ return; }

    /***********************************
     Create micro menu
     ***********************************/
    if ($ShowMenu)
	{
	$V .= "<div align=right><small>";
	$V .= menu_to_1html(menu_find("View-Meta",$MenuDepth),1);
	$V .= "</small></div>\n";
	} // if ShowMenu

    /**********************************
     Display micro header
     **********************************/
    if ($ShowHeader)
      {
      $V .= Dir2Browse("browse",$Item,$Ufile) . "<P />";
      } // if ShowHeader

    /**********************************
     Display meta data
     **********************************/
    $V .= "<table width='100%' border='1'>\n";
    $V .= "<tr><th>Meta Data</th><th>Value</th></tr>\n";

    $SQL = "SELECT * FROM mimetype
	INNER JOIN pfile ON pfile_pk = '$Pfile'
	AND pfile.pfile_mimetypefk = mimetype.mimetype_pk;";
    $Results = $DB->Action($SQL);
    foreach($Results as $R)
	{
	if (empty($R['mimetype_pk'])) { continue; }
	$V .= "<tr><td width='20%'>Unpacked file type";
	$V .= "</td><td>" . htmlentities($R['mimetype_name']) . "</td></tr>\n";
	}

    $SQL = "SELECT * FROM attrib
	INNER JOIN key ON key_pk = attrib_key_fk
	AND key_parent_fk IN
	(SELECT key_pk FROM key WHERE key_parent_fk=0 AND
	  (key_name = 'pkgmeta' OR key_name = 'specagent') )
	WHERE pfile_fk = '$Pfile'
	AND key_name != 'Processed' ORDER BY key_name;";
    $Results = $DB->Action($SQL);
    foreach($Results as $R)
	{
	if (empty($R['attrib_pk'])) { continue; }
	$V .= "<tr><td width='20%'>" . htmlentities($R['key_name']);
	$V .= "</td><td>" . htmlentities($R['attrib_value']) . "</td></tr>\n";
	}

    $V .= "</table>\n";
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
$NewPlugin = new ui_view_meta;
$NewPlugin->Initialize();
?>
