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

class ui_browse extends FO_Plugin
  {
  var $Name       = "browse";
  var $Title      = "Browse";
  var $Version    = "1.0";
  var $MenuList   = "Browse";
  var $Dependency = array("db");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;

  /***********************************************************
   RegisterMenus(): Customize submenus.
   ***********************************************************/
  function RegisterMenus()
    {
    $Upload = GetParm("upload",PARM_INTEGER);
    if (empty($Upload)) { return; }

    // For the Browse menu, permit switching between detail and simple.
    $URI = $this->Name . Traceback_parm_keep(array("upload","item"));
    menu_insert("Browse::[BREAK]",-1,"$URI&show=simple","Basic directory listing");
    $Show = GetParm("show",PARM_STRING);
    switch($Show)
      {
      default:
      case "detail":
	menu_insert("Browse::Simple",-10,"$URI&show=simple","Basic directory listing");
	menu_insert("Browse::Detail",-10);
	$URI .= "&show=detail";
	break;
      case "simple":
	menu_insert("Browse::Simple",-10);
	menu_insert("Browse::Detail",-10,"$URI&show=detail","Detailed directory listing");
	$URI .= "&show=simple";
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
  function ShowItem($Upload,$Item,$Show,$Folder)
    {
    global $Plugins;
    $V="";
    global $DB;
    /* Use plugin "view" and "download" if they exist. */
    $Uri = Traceback_uri() . "?mod=" . $this->Name . "&folder=$Folder";
    $MenuPfile = menu_find("Browse-Pfile",$MenuDepth);
    $MenuUfile = menu_find("Browse-Ufile",$MenuDepth);

    /* Grab the directory */
    $Results = DirGetList($Upload,$Item);
    $ShowSomething=0;

    $V .= "<table class='text' style='border-collapse: collapse' border=0 padding=0>\n";
    foreach($Results as $Row)
      {
      if (empty($Row['uploadtree_pk'])) { continue; }
      $ShowSomething=1;
      $Link = NULL;
      $Name = $Row['ufile_name'];
      $V .= "<tr>";

      /* Check for children */
      $Children = DirGetList($Upload,$Row['uploadtree_pk']);

      $Parm = "upload=$Upload&show=$Show&item=" . $Row['uploadtree_pk'] . "&ufile=" . $Row['ufile_pk'];
      if (!empty($Row['pfile_fk']))
	{
	$Parm .= "&pfile=" . $Row['pfile_fk'];
	}

      /* Scan for meta data */
      $HasRealChildren=0;
      $CountChildren=0;
      foreach($Children as $C)
        {
	if (empty($C['ufile_name'])) { continue; }
        $CountChildren++;
	if (!Isartifact($C['ufile_mode'])) { $HasRealChildren = 1; }
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
        if (!Isdir($Row['ufile_mode']))
	  {
	  $V .= "<td align='right'>&nbsp;&nbsp;" . number_format($Row['pfile_size'], 0, "", ",") . "&nbsp;&nbsp;</td>";
	  }
	else
	  {
	  $V .= "<td>&nbsp;</td>";
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
      if (Isdir($Row['ufile_mode']))
	{
	$V .= menu_to_1list($MenuUfile,$Parm,"<td>","</td>\n");
	}
      else
	{
	$V .= menu_to_1list($MenuPfile,$Parm,"<td>","</td>\n");
	}

      $V .= "</td>";
      } /* foreach($Results as $Row) */

    $V .= "</table>\n";
    if (!$ShowSomething) { $V .= "<b>No files</b>\n"; }
    else
	{
	$V .= "<hr>\n";
	if (count($Results) == 1) { $V .= "1 item\n"; }
	else { $V .= count($Results) . " items\n"; }
	}
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
	ORDER BY ufile_name,upload_filename,upload_desc;";
    $Results = $DB->Action($Sql);

    $Uri = Traceback_uri() . "?mod=" . $this->Name;
    $V .= "<table border=1 width='100%'>";
    $V .= "<tr><td valign='top' width='20%'>\n";

	$V .= FolderListScript();
	$V .= "<center><H3>Folder Navigation</H3></center>\n";
	$V .= "<center><small>";
	if ($Folder != FolderGetTop())
	  {
	  $V .= "<a href='" . Traceback_uri() . "?mod=" . $this->Name . "'>Top</a> |";
	  }
	$V .= "<a href='javascript:Expand();'>Expand</a> |";
	$V .= "<a href='javascript:Collapse();'>Collapse</a> |";
	$V .= "<a href='" . Traceback() . "'>Refresh</a>";
	$V .= "</small></center>";
	$V .= "<P>\n";
        $V .= "<form>\n";
        $V .= FolderListDiv($Folder,0,$Folder,1);
        $V .= "</form>\n";

    $V .= "</td><td valign='top'>\n";

	$V .= "<center><H3>Uploads</H3></center>\n";
        $V .= "<table class='text' border=0 width='100%' cellpadding=0>\n";
        $V .= "<th>Upload Name and Description</th><th>Upload Date</th></tr>\n";
        foreach($Results as $Row)
          {
          if (empty($Row['upload_pk'])) { continue; }
          $Desc = htmlentities($Row['upload_desc']);
	  $UploadPk = $Row['upload_pk'];
          if (empty($Desc)) { $Desc = "<i>No description</i>"; }
          $Name = $Row['ufile_name'];
	  $Sql2 = "SELECT count(*) AS count FROM uploadtree WHERE upload_fk = '$UploadPk';";
          $SResults = $DB->Action($Sql2);
	  $ItemCount = number_format($SResults[0]['count'], 0, "", ",");
          $V .= "<tr><td>";
          $V .= "<a href='$Uri&upload=$UploadPk&folder=$Folder&show=$Show'>";
          $V .= $Name . "/";
          $V .= "</a>";
	  if ($Row['upload_mode'] & 1<<2) { $V .= "<br>Added by file upload: " . htmlentities($Row['upload_filename']); }
	  if ($Row['upload_mode'] & 1<<3) { $V .= "<br>Added by URL: " . htmlentities($Row['upload_filename']); }
	  if ($Row['upload_mode'] & 1<<4) { $V .= "<br>Added from filesystem: " . htmlentities($Row['upload_filename']); }
	  $V .= "<br>" . $Desc;
          $V .= "<br>Contains $ItemCount ";
	  if ($ItemCount != "1") { $V .= "items."; }
	  else { $V .= "item."; }
	  $V .= "</td>\n";
          $V .= "<td align='right'>" . substr($Row['upload_ts'],0,16) . "</td></tr>\n";

	  $Status = JobListSummary($UploadPk);
	  $V .= "<td>Scheduled ";
	  if (plugin_find_id('showjobs') >= 0)
	    { $V .= "<a href='" . Traceback_uri() . "?mod=showjobs&show=summary&history=1&upload=$UploadPk'>jobs</a>: "; }
	  else
	    { $V .= "jobs: "; }
	  $V .= $Status['total'] . " total; ";
	  $V .= $Status['completed'] . " completed; ";
	  $V .= $Status['active'] . " active; ";
	  $V .= $Status['failed'] . " failed.";

          $V .= "<tr><td colspan=2>&nbsp;</td></tr>\n";
          }
        $V .= "</table>\n";

    $V .= "</td></tr>\n";
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
    if (empty($Folder)) { $Folder = FolderGetTop(); }
    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);
    $Uri = Traceback_uri() . "?mod=" . $this->Name;
    global $Plugins;
    global $DB;

    switch(GetParm("show",PARM_STRING))
	{
	default:
	case 'detail':
		$Show='detail';
		break;
	case 'simple':
		$Show='simple';
		break;
	}

    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$V .= "<font class='text'>\n";

	/************************/
	/* Show the folder path */
	/************************/
	if (!empty($Item))
	  {
	  $V .= Dir2Browse($this->Name,$Item,-1,NULL,1,"Browse") . "\n";
	  }
	else if (!empty($Upload))
	  {
	  $V .= Dir2BrowseUpload($this->Name,$Upload,-1,NULL,1,"Browse") . "\n";
	  }

	/******************************/
	/* Get the folder description */
	/******************************/
	if (!empty($Upload))
	  {
	  $V .= $this->ShowItem($Upload,$Item,$Show,$Folder);
	  }
	else if (!empty($Folder))
	  {
	  $V .= $this->ShowFolder($Folder,$Show);
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
