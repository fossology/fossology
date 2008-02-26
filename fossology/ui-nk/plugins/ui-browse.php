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
   RegisterMenus(): Customize submenus.
   ***********************************************************/
  function RegisterMenus()
    {
    $Upload = GetParm("upload",PARM_INTEGER);
    if (empty($Upload)) { return; }

    // For the Browse menu, permit switching between detail and summary.
    $URI = $this->Name . Traceback_parm_keep(array("upload","item"));
    menu_insert("Browse::[BREAK]",-1,"$URI&show=summary");
    $Show = GetParm("show",PARM_STRING);
    switch($Show)
      {
      case "detail":
	menu_insert("Browse::Summary",-10,"$URI&show=summary");
	menu_insert("Browse::Detail",-10);
	$URI .= "&show=detail";
	break;
      case "summary":
      default:
	menu_insert("Browse::Summary",-10);
	menu_insert("Browse::Detail",-10,"$URI&show=detail");
	$URI .= "&show=summary";
	break;
      }

    if (GetParm("mod",PARM_STRING) == $this->Name)
	{ 
	menu_insert("Browse::Browse",1);
	}
    else
	{
	menu_insert("Browse::Browse",1,$URI);
	}

    } // RegisterMenus()

  /***********************************************************
   ShowItem(): Given a upload_pk, list every item in it.
   If it is an individual file, then list the file contents.
   ***********************************************************/
  function ShowItem($Upload,$Item,$Show)
    {
    global $Plugins;
    $V="";
    global $DB;
    /* Use plugin "view" and "download" if they exist. */
    $ModView = &$Plugins[plugin_find_id("view")]; /* may be null */
    $ModDownload = &$Plugins[plugin_find_id("download")]; /* may be null */
    $ModLicense = &$Plugins[plugin_find_id("license")]; /* may be null */
    $Uri = Traceback_uri() . "?mod=" . $this->Name;

    /* Grab the directory */
    $Results = DirGetList($Upload,$Item);
    $ShowSomething=0;

    $V .= "<table class='text' style='border-collapse: collapse' border=0 padding=0>\n";
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
	$View = Traceback_uri() . "?mod=view&upload=$Upload&show=$Show&item=" . $Row['uploadtree_pk'] . "&ufile=" . $Row['ufile_pk'] . "&pfile=" . $Row['pfile_fk'];
	$Download = Traceback_uri() . "?mod=download&ufile=" . $Row['ufile_pk'] . "&pfile=" . $Row['pfile_fk'];
	$License = Traceback_uri() . "?mod=license&ufile=" . $Row['ufile_pk'] . "&pfile=" . $Row['pfile_fk'];
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
	  if (!Isdir($C['ufile_mode']) && (!strcmp($C['ufile_name'],"artifact.meta")))
		{
		$Meta = Traceback_uri() . "?mod=view&upload=$Upload&show=$Show&item=" . $Row['uploadtree_pk'] . "&ufile=" . $C['ufile_pk'] . "&pfile=" . $C['pfile_fk'];
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
	$V .= "<td class='mono'>" . DirMode2String($Row['ufile_mode']) . "</td>";
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
      if (Iscontainer($Row['ufile_mode'])) { $V .= "<b>"; }
      if (!empty($Link)) { $V .= "<a href='$Link'>"; }
      $V .= $Name;
      if (Isdir($Row['ufile_mode'])) { $V .= "/"; }
      if (!empty($Link)) { $V .= "</a>"; }
      if (Iscontainer($Row['ufile_mode'])) { $V .= "</b>"; }
      $V .= "</td>\n";
      $V .= "<td>";
      if (!empty($ModView) && !empty($View)) { $V .= "[<a href='$View'>View</a>] "; }
      $V .= "</td><td>";
      if (!empty($ModView) && !empty($Meta)) { $V .= "[<a href='$Meta'>Meta</a>] "; }
      $V .= "</td><td>";
      if (!empty($ModDownload) && !empty($Download)) { $V .= "[<a href='$Download'>Download</a>] "; }

if (0) {
      /* Code disabled due to speed on really large files */
      $V .= "</td><td>";
      if (!empty($ModLicense) && !empty($Row['pfile_fk']))
	{
	$Lic = LicenseGetAll($Row['uploadtree_pk']);
	$i = count($Lic);
	if ($i > 0)
	  {
	  $V .= "[<a href='$License'>$i&nbsp;license" . ($i == 1 ? "" : "s") . "</a>] ";
	  }
	}
}

      $V .= "</td>";
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
    global $DB;
    $Sql = "SELECT * FROM upload
	INNER JOIN ufile ON ufile_pk = ufile_fk
	WHERE upload_pk IN
	(SELECT child_id FROM foldercontents WHERE foldercontents_mode & 2 != 0 AND parent_fk = $Folder)
	ORDER BY upload_filename,upload_desc;";
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
    global $Plugins;
    global $DB;
    $ModLicense = &$Plugins[plugin_find_id("license")]; /* may be null */

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
	/*************************/
	/* Create the micro-menu */
	/*************************/
        $V .= "<div align=right><small>";
	$V .= menu_to_1html(menu_find("Browse",$MenuDepth),1);
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
