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

class ui_view_info extends Plugin
  {
  var $Name       = "view_info";
  var $Title      = "View File Information";
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
	menu_insert("View::Info",1);
	menu_insert("View-Meta::Info",1);
	}
    else
	{
	menu_insert("View::Info",1,$URI);
	menu_insert("View-Meta::Info",1,$URI);
	}
    } // RegisterMenus()

  /***********************************************************
   ShowView(): Display the info data associated with the file.
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
    if (empty($Upload) || empty($Item))
	{ return; }

    /**********************************
     Display micro header
     **********************************/
    if ($ShowHeader)
      {
      $V .= Dir2Browse("browse",$Item,$Ufile,NULL,1,"View-Meta");
      } // if ShowHeader

    /**********************************
     Determine the contents of the container.
     **********************************/
    $V .= "<H2>Sightings</H2>\n";
    $SQL = "SELECT * FROM pfile INNER JOIN ufile ON ufile.pfile_fk = '$Pfile' AND ufile.pfile_fk = pfile.pfile_pk INNER JOIN uploadtree ON uploadtree.ufile_fk = ufile.ufile_pk WHERE uploadtree.uploadtree_pk != '$Item';";
    $Results = $DB->Action($SQL);
    if (count($Results) > 0)
	{
	$V .= "This file appears in the following alternate locations:\n";
        foreach($Results as $R)
          {
          if (empty($R['pfile_fk'])) { continue; }
          $V .= "<P />" . Dir2Browse("browse",$R['uploadtree_pk'],-1,"view") . "\n";
          }
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
