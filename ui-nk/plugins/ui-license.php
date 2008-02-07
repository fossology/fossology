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

class ui_license extends Plugin
  {
  var $Name="license";
  var $Version="1.0";
  // var $MenuList="Tools::License";
  var $Dependency=array("db","browse");

  /***********************************************************
   ShowUploadHist(): Given an Upload and UploadtreePk item, display:
   (1) The histogram for the directory BY LICENSE.
   (2) The histogram for the directory BY LICENSE FAMILY.
   (3) The file listing for the directory.
   ***********************************************************/
  function ShowUploadHist($Upload,$Item,$Uri)
    {
    /*****
     Get all the licenses PER item (file or directory) under this
     UploadtreePk.
     Save the data 3 ways:
       - Number of licenses PER item.
       - Number of items PER license.
       - Number of items PER license family.
     *****/
    $VF=""; // return values for file listing
    $V=""; // total return value
    global $Plugins;
    global $DB;
    $Time = time();

    /* Load licenses */
    $LicPk2GID=array();  // map lic_pk to the group id: lic_id
    $LicGID2Name=array(); // map lic_id to name.
    $Results = $DB->Action("SELECT lic_pk,lic_id,lic_name,lic_section FROM agent_lic_raw;");
    foreach($Results as $R)
      {
      if (empty($R['lic_name'])) { continue; }
      $Name = preg_replace("/^.*\//","",$R['lic_name']);
      $LicPk2Name[$R['lic_id']] = $Name;
      $LicID2GID[$R['lic_pk']] = $R['lic_id'];
      }

    /* Arrays for storying item->license and license->item mappings */
    $Item2LicGID = array();
    $LicGID2Item = array();

    /* Get the items under this UploadtreePk */
    $Children = DirGetList($Upload,$Item);
    $ChildCount=0;
    $VF .= "<table border=0>";
    foreach($Children as $C)
      {
      /* Store the item information */
      $VF .= "<div id='Lic-$ChildCount'>";

      $IsDir = Isdir($C['ufile_mode']);
      $IsContainer = Iscontainer($C['ufile_mode']);
      $Lics = array();
      if ($IsContainer) { LicenseGetAll($C['uploadtree_pk'],$Lics); }
      else { LicenseGet($C['pfile_fk'],$Lics); }
      if (!empty($C['pfile_fk']))
	{
	$LicUri = "$Uri&item=$Item&ufile=" . $C['ufile_pk'] . "&pfile=" . $C['pfile_fk'];
	}
      else
	{
	$LicUri = "$Uri&item=" . $C['uploadtree_pk'];
	}
      $LicCount = count($Lics);
      $VF .= "<tr name='Lic-$ChildCount'><td>";
      if ($LicCount > 0)
	{
	$VF .= "<a href='$LicUri'>";
	if ($IsContainer) { $VF .= "<b>"; };
	$VF .= $C['ufile_name'];
	if ($IsDir) { $VF .= "/"; };
	if ($IsContainer) { $VF .= "<b>"; };
	$VF .= "</a></td>";
	$VF .= "</td><td>[$LicCount license" . ($LicCount == 1 ? "" : "s") . "]</td>";
	}
      else
	{
	if ($IsContainer) { $VF .= "<b>"; };
	$VF .= $C['ufile_name'];
	if ($IsDir) { $VF .= "/"; };
	if ($IsContainer) { $VF .= "<b>"; };
	$VF .= "</td><td></td>";
	}
      $ChildCount++;
      }
    $VF .= "</table>\n";

    $Time = time() - $Time;
    $VF .= "<br>Elaspsed time: $Time seconds<br>\n";
    return($VF);
    } // ShowUploadHist()

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
    global $DB;

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
	$Opt = "";
	if ($Folder) { $Opt .= "&folder=$Folder"; }
	if ($Upload) { $Opt .= "&upload=$Upload"; }
	if ($Item) { $Opt .= "&item=$Item"; }
	$V .= "<a href='" . str_replace("mod=".$this->Name,"mod=browse",Traceback()) . "'>Browse</a> | ";
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
	  // $V .= $this->ShowFolder($Folder);
	  }
	if (!empty($Upload))
	  {
	  $Uri = preg_replace("/&item=([0-9]*)/","",Traceback());
	  $V .= $this->ShowUploadHist($Upload,$Item,$Uri);
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
$NewPlugin = new ui_license;
$NewPlugin->Initialize();

?>
