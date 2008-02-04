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
  function ShowItem($Upload,$Item)
    {
    global $Plugins;
    $V="";
    $DB = &$Plugins[plugin_find_id("db")];
    $ModDownload = &$Plugins[plugin_find_id("download")]; /* may be null */
    $ModView = &$Plugins[plugin_find_id("view")]; /* may be null */
    $Uri = Traceback_uri() . "?mod=" . $this->Name;

    /* Grab the directory */
    $Results = DirGetList($Upload,$Item);

    $V .= "<table width='100%' border=0 class='text'>\n";
    foreach($Results as $Row)
      {
      if (empty($Row['uploadtree_pk'])) { continue; }
      $View = NULL;
      $Download = NULL;
      $Link = NULL;
      $Meta = NULL;
      $Name = $Row['ufile_name'];
      $V .= "<tr><td>";

      /* Check for children */
      $Children = DirGetList($Upload,$Row['uploadtree_pk']);

      if (!empty($Row['pfile_fk']))
	{
	$View = Traceback_uri() . "?mod=view&pfile=" . $Row['pfile_fk'];
	$Download = Traceback_uri() . "?mod=download&pfile=" . $Row['pfile_fk'];
	}

      /* Scan for meta data */
      $HasRealChildren=0;
      foreach($Children as $C)
        {
	if (empty($C['ufile_name'])) { continue; }
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
      if (count($Children) > 0) // if is directory
	{
	if ($HasRealChildren)
	  {
	  $Link = $Uri . "&upload=$Upload&item=" . $Row['uploadtree_pk'];
	  }
	else
	  {
	  $Link = $Uri . "&upload=$Upload&item=" . DirGetNonArtifact($Row['uploadtree_pk']);
	  }
	}

      /* Display it */
      if (!empty($Link)) /* if it's a directory */
	{
	$V .= "<a href='$Link'><b>$Name/</b></a>\n";
	}
      else
	{
	$V .= "$Name\n";
	}
      $V .= "</td>\n";
      $V .= "<td>";
      if (!empty($ModView) && !empty($View)) { $V .= "[<a href='$View'>View</a>] "; }
      if (!empty($ModDownload) && !empty($Download)) { $V .= "[<a href='$Download'>Download</a>] "; }
      if (!empty($Link)) { $V .= "[<a href='$Link'>Traverse</a>] "; }
      if (!empty($ModDownload) && !empty($Meta)) { $V .= "[<a href='$Meta'>Meta</a>] "; }
      $V .= "</td></tr>\n";
      } /* foreach($Results as $Row) */
    $V .= "</table>\n";
    return($V);
    } // ShowItem()

  /***********************************************************
   ShowFolder(): Given a Folder_pk, list every upload in the folder.
   ***********************************************************/
  function ShowFolder($Folder)
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
      $V .= "<a href='$Uri&upload=" . $Row['upload_pk'] . "'>";
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
    switch(GetParm("show",PARM_INTEGER))
	{
	case 'detail':
		$Show='detail';
		break;
	case 'name':
	default:
		$Show='name';
		break;
	}

    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$V .= "<style type='text/css'>\n";
	$V .= ".text { height:24px; font:normal 10pt verdana, arial, helvetica; }\n";
	$V .= "a { text-decoration:none; }\n";
	$V .= "div { padding:0; margin:0; }\n";
	$V .= "</style>\n";

	$V .= "<font class='text'>\n";

	/* Get the folder description */
	if (!empty($Folder))
	  {
	  $V .= $this->ShowFolder($Folder);
	  }
	if (!empty($Upload))
	  {
	  $V .= $this->ShowItem($Upload,$Item);
	  }

	/* Display the browse */
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
