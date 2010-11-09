<?php
/***********************************************************
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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

require($WEBDIR.'/plugins/copyright/library.php');

/*************************************************
 This plugin is used to:
   List files for a given copyright statement/email/url in a given
   uploadtree.
 *************************************************/

define("TITLE_copyright_list", _("List Files for Copyright/Email/URL"));

class copyright_list extends FO_Plugin
{
  var $Name       = "copyrightlist";
  var $Title      = TITLE_copyright_list;
  var $Version    = "1.0";
  var $Dependency = array("db","copyrighthist");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;

  /***********************************************************
   RegisterMenus(): Customize submenus.
   ***********************************************************/
  function RegisterMenus()
  { 
    if ($this->State != PLUGIN_STATE_READY) { return(0); }

    // micro-menu
	$agent_pk = GetParm("agent",PARM_INTEGER);
	$uploadtree_pk = GetParm("item",PARM_INTEGER);
	$hash = GetParm("hash",PARM_RAW);
	$type = GetParm("type",PARM_RAW);
	$Page = GetParm("page",PARM_INTEGER);
	$Excl = GetParm("excl",PARM_RAW);

    $URL = $this->Name . "&agent=$agent_pk&item=$uploadtree_pk&hash=$hash&type=$type&page=-1";
    if (!empty($Excl)) $URL .= "&excl=$Excl";
$text = _("Show All Files");
    menu_insert($this->Name."::Show All",0, $URL, $text);

  } // RegisterMenus()
      

  /***********************************************************
   Output(): Display the loaded menu and plugins.
   ***********************************************************/
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    global $Plugins;
    global $DB, $PG_CONN;

    // make sure there is a db connection since I've pierced the core-db abstraction
    if (!$PG_CONN) { $dbok = $DB->db_init(); if (!$dbok) echo _("NO DB connection"); }

    $V="";
    $Time = time();
    $Max = 50;

    /*  Input parameters */
	$agent_pk = GetParm("agent",PARM_INTEGER);
	$uploadtree_pk = GetParm("item",PARM_INTEGER);
	$hash = GetParm("hash",PARM_RAW);
	$type = GetParm("type",PARM_RAW);
	$Excl = GetParm("excl",PARM_RAW);
	if (empty($uploadtree_pk) || empty($hash) || empty($type)) 
    {
$text = _("is missing required parameters");
      echo $this->Name . " $text.";
      return;
    }
	$Page = GetParm("page",PARM_INTEGER);
	if (empty($Page)) { $Page=0; }

    switch($this->OutputType)
    {
      case "XML":
	break;
      case "HTML":
      // micro menus
      $V .= menu_to_1html(menu_find($this->Name, $MenuDepth),0);

    $sql = "SELECT content from copyright WHERE hash='$hash' AND type='$type' limit 1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if ($row = pg_fetch_assoc($result)) {
        $content = strip_tags($row['content']);
    } else {
        $content = "";
    }

	/* Load licenses */
	$Offset = ($Page < 0) ? 0 : $Page*$Max;
    $order = "";
    $PkgsOnly = false;
    $Unique = CountFilesWithCopyright($agent_pk, $hash, $type, $uploadtree_pk, $PkgsOnly);
    $order = " order by ufile_name asc";
    $filesresult = GetFilesWithCopyright($agent_pk, $hash, $type, $uploadtree_pk,
                                $PkgsOnly, $Offset, $Max, $order);
    $Count = pg_num_rows($filesresult);
$text = _("files found");
$text1 = _("unique");
$text2 = _("with");
$text3 = _("copyright");
$text4 = _("email");
$text5 = _("url");
    $V.= "$Count $text ($Unique $text1) $text2 ";
    switch ($type) {
        case "statement":
            $V .= "$text3";
            break;
        case "email":
            $V .= "$text4";
            break;
        case "url":
            $V .= "$text5";
            break;
    }
    $V .= ": <b>$content</b>";

$text = _("Display excludes files with these extensions");
    if (!empty($Excl)) $V .= "<br>$text: $Excl";

	/* Get the page menu */
	if (($Count >= $Max) && ($Page >= 0))
	{
	  $VM = "<P />\n" . MenuEndlessPage($Page,intval((($Count+$Offset)/$Max))) . "<P />\n";
	  $V .= $VM;
	}
	else
	{
	  $VM = "";
	}

	/* Offset is +1 to start numbering from 1 instead of zero */
    $RowNum = $Offset;
    $LinkLast = "copyrightview&agent=$agent_pk";
    $ShowBox = 1;
    $ShowMicro=NULL;

    // base url
    $ucontent = rawurlencode($content);
    $URL = "?mod=" . $this->Name . "&agent=$agent_pk&item=$uploadtree_pk&lic=$ucontent&page=-1";

    while ($row = pg_fetch_assoc($filesresult))
    {
      // Allow user to exclude files with this extension
      $FileExt = GetFileExt($row['ufile_name']);
      if (!empty($Excl)) 
        $URL .= "&excl=$Excl:$FileExt";
      else
        $URL .= "&excl=$FileExt";
$text = _("Exclude this file type");
      $Header = "<a href=$URL>$text.</a>";

      $ok = true;
      if ($Excl)
      {
        $ExclArray = explode(":", $Excl);
        if (in_array($FileExt, $ExclArray)) $ok = false;
      }
      if ($ok) $V .= Dir2Browse("browse", $row['uploadtree_pk'], $LinkLast, $ShowBox, $ShowMicro, ++$RowNum, $Header);
    }

	if (!empty($VM)) { $V .= $VM . "\n"; }
	$V .= "<hr>\n";
	$Time = time() - $Time;
$text = _("Elaspsed time");
$text1 = _("seconds");
	$V .= "<small>$text: $Time $text1</small>\n";
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print($V);
    return;
  } // Output()


};
$NewPlugin = new copyright_list;
$NewPlugin->Initialize();

?>
