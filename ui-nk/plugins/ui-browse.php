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

class ui_browse extends Plugin
  {
  var $Name="browse";
  var $Version="1.0";
  // var $MenuList="Tools::Browse";
  var $Dependency=array("db");

  /***********************************************************
   ShowItem(): Given a upload_pk, list every item in it.
   If it is an individual file, then list the file contents.
   ***********************************************************/
  function ShowItem($Upload,$Item,$Show)
    {
    global $Plugins;
    $V="";
    $DB = &$Plugins[plugin_find_id("db")];
    /* Use plugin "view" and "download" if they exist. */
    $ModDownload = &$Plugins[plugin_find_id("download")]; /* may be null */
    $ModView = &$Plugins[plugin_find_id("view")]; /* may be null */
    $Uri = Traceback_uri() . "?mod=" . $this->Name;

    /* Grab the directory */
    $Results = DirGetList($Upload,$Item);
    $ShowSomething=0;

    $V .= "<table class='text' border=0 cellpadding=0>\n";
    foreach($Results as $Row)
      {
      if (empty($Row['uploadtree_pk'])) { continue; }
      $ShowSomething=1;
      $View = NULL;
      $Download = NULL;
      $Link = NULL;
      $Meta = NULL;
      $Name = $Row['ufile_name'];
      $V .= "<tr>";

      /* Check for children */
      $Children = DirGetList($Upload,$Row['uploadtree_pk']);

      if (!empty($Row['pfile_fk']))
	{
	$View = Traceback_uri() . "?mod=view&pfile=" . $Row['pfile_fk'];
	$Download = Traceback_uri() . "?mod=download&pfile=" . $Row['pfile_fk'];
	}

      /* Scan for meta data */
      $HasRealChildren=0;
      $CountChildren=0;
      foreach($Children as $C)
        {
	if (empty($C['ufile_name'])) { continue; }
        $CountChildren++;
	if (Isartifact($C['ufile_mode']))
	  {
	  if (!Isdir($C['ufile_mode']))
		{
		$Meta = Traceback_uri() . "?mod=view&pfile=" . $C['pfile_fk'];
		}
	  }
	else { $HasRealChildren = 1; }
	} /* foreach Children */

      /* Set the traversal link */
      if ($CountChildren > 0) // if is directory
	{
	if ($HasRealChildren)
	  {
	  $Link = $Uri . "&show=$Show&upload=$Upload&item=" . $Row['uploadtree_pk'];
	  }
	else
	  {
	  $Link = $Uri . "&show=$Show&upload=$Upload&item=" . DirGetNonArtifact($Row['uploadtree_pk']);
	  }
	}

      /* Show details children */
      if ($Show == 'detail')
        {
	$V .= "<td>" . DirMode2String($Row['ufile_mode']) . "</td>";
	$V .= "<td>&nbsp;&nbsp;" . substr($Row['ufile_mtime'],0,19) . "</td>";
	if (!empty($Row['pfile_size']))
	  {
	  $V .= "<td align=right>&nbsp;&nbsp;" . number_format($Row['pfile_size'], 0, "", ",") . "&nbsp;&nbsp;</td>";
	  }
	else
	  {
	  $V .= "<td align=right>&nbsp;&nbsp;&nbsp;&nbsp;</td>";
	  }
	}

      /* Display item */
      $V .= "<td>";
      if (!empty($Link)) /* if it's a directory */
	{
	$V .= "<a href='$Link'><b>$Name";
	if (Isdir($Row['ufile_mode'])) { $V .= "/"; }
	$V .= "</b></a>\n";
	}
      else
	{
	$V .= "$Name\n";
	}
      $V .= "</td>\n";
      $V .= "<td>";
      if (!empty($Link)) { $V .= "[<a href='$Link'>Traverse</a>] "; }
      $V .= "</td><td>";
      if (!empty($ModView) && !empty($View)) { $V .= "[<a href='$View'>View</a>] "; }
      $V .= "</td><td>";
      if (!empty($ModView) && !empty($Meta)) { $V .= "[<a href='$Meta'>Meta</a>] "; }
      $V .= "</td><td>";
      if (!empty($ModDownload) && !empty($Download)) { $V .= "[<a href='$Download'>Download</a>] "; }
      $V .= "</td></tr>\n";
      } /* foreach($Results as $Row) */

    $V .= "</table>\n";
    if (!$ShowSomething) { $V .= "<b>No files</b>\n"; }
    return($V);
    } // ShowItem()

  /***********************************************************
   ShowFolder(): Given a Folder_pk, list every upload in the folder.
   ***********************************************************/
  function ShowFolder($Folder,$Show)
    {
    global $Plugins;
    $V="";
    $DB = &$Plugins[plugin_find_id("db")];
    $Sql = "SELECT * FROM upload WHERE upload_pk IN (SELECT child_id FROM foldercontents WHERE foldercontents_mode & 2 != 0 AND parent_fk = $Folder);";
    $Results = $DB->Action($Sql);

    $Uri = Traceback_uri() . "?mod=" . $this->Name;
    $V .= "<table class='text' border=0 width='100%' cellpadding=0>\n";
    $V .= "<tr><th>Upload Name and Description</th><th>Upload Date</th></tr>\n";
    foreach($Results as $Row)
      {
      if (empty($Row['upload_pk'])) { continue; }
      $Desc = htmlentities($Row['upload_desc']);
      if (empty($Desc)) { $Desc = "<i>No description</i>"; }
      $Sql = "SELECT ufile_name FROM ufile WHERE ufile_pk = " . $Row['ufile_fk'] . ";";
      $UResults = $DB->Action($Sql);
      $Name = $UResults[0]['ufile_name'];
      $V .= "<tr><td>";
      $V .= "<a href='$Uri&upload=" . $Row['upload_pk'] . "&show=$Show'>";
      $V .= $Name . "/";
      $V .= "</a><br>" . $Desc . "</td>\n";
      $V .= "<td align='right'>" . substr($Row['upload_ts'],0,16) . "</td></tr>\n";
      $V .= "<tr><td colspan=2>&nbsp;</td></tr>\n";
      }
    $V .= "</table>\n";
    return($V);
    } /* ShowFolder() */

  /***********************************************************
   Output(): This function returns the scheduler status.
   ***********************************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    $V="";
    $Folder = GetParm("folder",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);
    $Uri = Traceback_uri() . "?mod=" . $this->Name;

    switch(GetParm("show",PARM_STRING))
	{
	case 'detail':
		$Show='detail';
		break;
	case 'summary':
	default:
		$Show='summary';
		break;
	}

    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$V .= "<style type='text/css'>\n";
	$V .= ".text { height:24px; font:normal 10pt verdana, arial, helvetica; }\n";
	$V .= ".dir { height:24px; font:normal 10pt verdana, arial, helvetica; border: thin black; border-style: none none dotted none; }\n";
	$V .= "a { text-decoration:none; }\n";
	$V .= "div { padding:0; margin:0; }\n";
	$V .= "</style>\n";

	/*************************/
	/* Create the micro-menu */
	/*************************/
        $V .= "<div align=right><small>";
	$Opt = "";
	if ($Folder) { $Opt .= "&folder=$Folder"; }
	if ($Upload) { $Opt .= "&upload=$Upload"; }
	if ($Item) { $Opt .= "&item=$Item"; }
        if ($Show == 'detail') { $V .= "<a href='$Uri&show=summary$Opt'>Summary</a> | "; }
        else { $V .= "<a href='$Uri&show=detail$Opt'>Detail</a> | "; }
        $V .= "<a href='" . Traceback() . "'>Refresh</a>";
        $V .= "</small></div>\n";

	$V .= "<font class='text'>\n";

	/************************/
	/* Show the folder path */
	/************************/
	$Path = Dir2Path($Item);
	$FirstPath=1;
	$Opt = "";
	if ($Folder) { $Opt .= "&folder=$Folder"; }
	if ($Upload) { $Opt .= "&upload=$Upload"; }
	$Opt .= "&show=$Show";
	$V .= "<div style='border: thin dotted gray; background-color:lightyellow'>\n";
	foreach($Path as $P)
	  {
	  if (empty($P['ufile_name'])) { continue; }
	  if (!$FirstPath) { $V .= "/ "; }
	  $V .= "<a href='$Uri&item=" . $P['uploadtree_pk'] . "$Opt'>";
	  if (Isdir($P['ufile_mode']))
	    {
	    $V .= $P['ufile_name'];
	    }
	  else
	    {
	    if (!$FirstPath) { $V .= "<br>\n&nbsp;&nbsp;"; }
	    $V .= "<b>" . $P['ufile_name'] . "</b>";
	    }
	  $V .= "</a>";
	  $FirstPath=0;
	  }
	$V .= "</div><P />\n";

	/******************************/
	/* Get the folder description */
	/******************************/
	if (!empty($Folder))
	  {
	  $V .= $this->ShowFolder($Folder,$Show);
	  }
	if (!empty($Upload))
	  {
	  $V .= $this->ShowItem($Upload,$Item,$Show);
	  }
	$V .= "</font>\n";
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print "$V";
    return;
    }

  };
$NewPlugin = new ui_browse;
$NewPlugin->Initialize();

?>
