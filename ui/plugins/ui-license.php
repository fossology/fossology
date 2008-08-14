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

class ui_license extends FO_Plugin
  {
  var $Name       = "license";
  var $Title      = "License Browser";
  var $Version    = "1.0";
  // var $MenuList= "Jobs::License";
  var $Dependency = array("db","browse");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;

  /***********************************************************
   RegisterMenus(): Customize submenus.
   ***********************************************************/
  function RegisterMenus()
    {
    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array("show","format","page","upload","item","ufile","pfile"));
    $Item = GetParm("item",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    if (!empty($Item) && !empty($Upload))
      {
      if (GetParm("mod",PARM_STRING) == $this->Name)
	{
	menu_insert("Browse::License",1);
	menu_insert("Browse::[BREAK]",100);
	menu_insert("Browse::Clear",101,NULL,NULL,NULL,"<a href='javascript:LicColor(\"\",\"\",\"\",\"\");'>Clear</a>");
	}
      else
	{
	menu_insert("Browse::License",1,$URI,"View license histogram");
	}
      }
    } // RegisterMenus()

  /***********************************************************
   SortName(): Given two elements sort them by name.
   Used for sorting the histogram.
   ***********************************************************/
  function SortName ($a,$b)
    {
    list($A0,$A1,$A2) = split("\|",$a,3);
    list($B0,$B1,$B2) = split("\|",$b,3);
    /* Sort by count */
    if ($A0 < $B0) { return(1); }
    if ($A0 > $B0) { return(-1); }
    /* Same count? sort by root name.
       Same root? place real before style before partial. */
    $A0 = str_replace('-partial$',"",$A1);
    if ($A0 != $A1) { $A1 = '-partial'; }
    else
      {
      $A0 = str_replace('-style',"",$A1);
      if ($A0 != $A1) { $A1 = '-style'; }
      else { $A1=''; }
      }
    $B0 = str_replace('-partial$',"",$B1);
    if ($B0 != $B1) { $B1 = '-partial'; }
    else
      {
      $B0 = str_replace('-style',"",$B1);
      if ($B0 != $B1) { $B1 = '-style'; }
      else { $B1=''; }
      }
    if ($A0 != $B0) { return(strcmp($A0,$B0)); }
    if ($A1 == "") { return(-1); }
    if ($B1 == "") { return(1); }
    if ($A1 == "-partial") { return(-1); }
    if ($B1 == "-partial") { return(1); }
    return(strcmp($A1,$B1));
    } // SortName()

  /***********************************************************
   ShowUploadHist(): Given an Upload and UploadtreePk item, display:
   (1) The histogram for the directory BY LICENSE.
   (2) The file listing for the directory, with license navigation.
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
    $VH=""; // return values for license histogram
    $V=""; // total return value
    global $Plugins;
    global $DB;
    $Time = time();
    $Lics = array(); // license summary for an item in the directory
    $ModLicView = &$Plugins[plugin_find_id("view-license")];

    /* Arrays for storying item->license and license->item mappings */
    $LicGID2Item = array();
    $LicItem2GID = array();
    $MapLic2GID = array(); /* every license should have an ID number */

    /*  Get the counts for each license under this UploadtreePk*/
    $Lics = array();
    LicenseGetAll($Item,$Lics);   // key is license name, value is count
    $LicTotal = $Lics['Total'];

    /* Ensure that every license is associated with an ID */
    /* MapLic2Gid key is license name, value is a sequence number (GID) */
    $MapNext=0;
    foreach($Lics as $Key => $Val) $MapLic2GID[$Key] = $MapNext++; 

    /* Save the license results (also converts values to GID) */
    /* Key is reported license name, value is count */
    /* "Total" is a special key containing the sum of the counts */
//    foreach($Lics as $Key => $Val)
//	{
//	  if ($Key != 'Total')
 //     {
//		$GID = $MapLic2GID[$Key];
//		$LicGID2Item[$GID] .= "$ChildCount ";
//		$LicItem2GID[$ChildCount] .= "$GID ";
 //     }
  //    else { $GID = $Key; }
	//}

    /****************************************/
    /* Get ALL the items under this UploadtreePk */
    $Children = DirGetList($Upload,$Item);
    $ChildCount=0;
    $VF .= "<table border=0>";
    foreach($Children as $C)
    {
//      if (empty($C)) { continue; }
      /* Store the item information */
      $IsDir = Isdir($C['ufile_mode']);
      $IsContainer = Iscontainer($C['ufile_mode']);

      /* Determine the hyperlinks */
      if (!empty($C['pfile_fk']) && !empty($ModLicView))
	  {
        $LinkUri = "$Uri&item=$Item";
        $LinkUri = preg_replace("/mod=license/","mod=view-license",$LinkUri);
      }
      else
      {
        $LinkUri = NULL;
      }

      if (Iscontainer($C['ufile_mode']))
      {
        $uploadtree_pk = DirGetNonArtifact($C['uploadtree_pk']);
        $LicUri = "$Uri&item=" . $uploadtree_pk;
      }
      else
      {
        $LicUri = NULL;
      }


      /* Populate the output ($VF) - file list */

      /* Find number of licenses in child 
       * But only if there are <500 licenses in the parent.  This number
       * is arbitrary but the idea is to save a whole bunch of LicenseCount
       * calls when the license counts are not that interesting (due to the large
       * number of licenses).
       */
      if ($LicTotal < 500)
      {
        $LicCount = LicenseCount($C['uploadtree_pk']);
      }
      else
        $LicCount = 0;

      $VF .= '<tr><td id="Lic-' . $LicCount . '" align="left">';
      $HasHref=0;
      if ($IsContainer)
      {
        $VF .= "<a href='$LicUri'>";
        $VF .= "<b>";
        $HasHref=1;
      }
      else if (!empty($LinkUri))
      {
        $VF .= "<a href='$LinkUri'>";
        $HasHref=1;
      }
      $VF .= $C['ufile_name'];
      if ($IsDir) { $VF .= "/"; };
      if ($IsContainer) { $VF .= "<b>"; };
      if ($HasHref) { $VF .= "</a>"; }
      $VF .= "</td><td>";
      if ($LicCount)
      {
        $VF .= "[" . number_format($LicCount,0,"",",") . "&nbsp;";
      //$VF .= "<a href=\"javascript:LicColor('Lic-$ChildCount','LicGroup-','" . trim($LicItem2GID[$ChildCount]) . "','lightgreen');\">";
        $VF .= "license" . ($LicCount == 1 ? "" : "s");
        $VF .= "</a>";
        $VF .= "]";
      }
      $VF .= "</td>";
      $VF .= "</tr>\n";

      $ChildCount++;
      }
    $VF .= "</table>\n";

    /****************************************/
    /* List the licenses */
    $VH .= "<table border=1 width='100%'>\n";
    $SFbL = plugin_find_id("search_file_by_license");
    $VH .= "<tr><th width='10%'>Count</th>";
    if ($SFbL >= 0) { $VH .= "<th width='10%'>Files</th>"; }
    $VH .= "<th>License</th>\n";

    /* krsort + arsort = consistent sorting order */
    arsort($Lics);
    /* Redo the sorting */
    $SortOrder=array();
    foreach($Lics as $Key => $Val)
      {
      if (empty($Val)) { continue; }
      $SortOrder[] = $Val . "|" . str_replace("'","",$Key) . "|" . $Key;
      }
    usort($SortOrder,array($this,"SortName"));
    $LicsTotal = array();
    foreach($SortOrder as $Key => $Val)
      {
      if (empty($Val)) { continue; }
      list($x,$y,$z) = split("\|",$Val,3);
      $LicsTotal[$z]=$x;
      }

    $Total=0;
    foreach($Lics as $Key => $Val)
      {
      if ($Key != 'Total')
	{
	$GID = $MapLic2GID[$Key];
	$VH .= "<tr><td align='right'>$Val</td>";
	$Total += $Val;
	if ($SFbL >= 0)
	  {
	  $VH .= "<td align='center'><a href='";
	  $VH .= Traceback_uri();
	  $VH .= "?mod=search_file_by_license&item=$Item&lic=" . urlencode($Key) . "'>Show</a></td>";
	  }
	$VH .= "<td id='LicGroup-$GID'>";
	$Uri = Traceback_uri() . "?mod=license_listing&item=$Item&lic=$GID";
	$VH .= "<a href=\"javascript:LicColor('LicGroup-$GID','Lic-','" . trim($LicGID2Item[$GID]) . "','yellow'); ";
	$VH .= "\">";
	$VH .= htmlentities($Key);
	$VH .= "</a>";
	$VH .= "</td></tr>\n";
	}
      }
    $VH .= "</table>\n";
    $VH .= "<br>\n";
    $VH .= "Total licenses: $Total\n";

    /****************************************/
    /* Licenses use Javascript to highlight */
    $VJ = ""; // return values for the javascript
    $VJ .= "<script language='javascript'>\n";
    $VJ .= "<!--\n";
    $VJ .= "var LastSelf='';\n";
    $VJ .= "var LastPrefix='';\n";
    $VJ .= "var LastListing='';\n";
    $VJ .= "function LicColor(Self,Prefix,Listing,color)\n";
    $VJ .= "{\n";
    $VJ .= "if (LastSelf!='')\n";
    $VJ .= "  { document.getElementById(LastSelf).style.backgroundColor='white'; }\n";
    $VJ .= "LastSelf = Self;\n";
    $VJ .= "if (LastPrefix!='')\n";
    $VJ .= "  {\n";
    $VJ .= "  List = LastListing.split(' ');\n";
    $VJ .= "  for(var i in List)\n";
    $VJ .= "    {\n";
    $VJ .= "    document.getElementById(LastPrefix + List[i]).style.backgroundColor='white';\n";
    $VJ .= "    }\n";
    $VJ .= "  }\n";
    $VJ .= "LastPrefix = Prefix;\n";
    $VJ .= "LastListing = Listing;\n";
    $VJ .= "if (Self!='')\n";
    $VJ .= "  {\n";
    $VJ .= "  document.getElementById(Self).style.backgroundColor=color;\n";
    $VJ .= "  }\n";
    $VJ .= "if (Listing!='')\n";
    $VJ .= "  {\n";
    $VJ .= "  List = Listing.split(' ');\n";
    $VJ .= "  for(var i in List)\n";
    $VJ .= "    {\n";
    $VJ .= "    document.getElementById(Prefix + List[i]).style.backgroundColor=color;\n";
    $VJ .= "    }\n";
    $VJ .= "  }\n";
    $VJ .= "}\n";
    $VJ .= "// -->\n";
    $VJ .= "</script>\n";

    /* Combine VF and VH */
    $V .= "<table border=0 width='100%'>\n";
    $V .= "<tr><td valign='top' width='50%'>$VH</td><td valign='top'>$VF</td></tr>\n";
    $V .= "</table>\n";
    $V .= "<hr />\n";
    $Time = time() - $Time;
    $V .= "<small>Elaspsed time: $Time seconds</small>\n";
    $V .= $VJ;
    return($V);
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
	$V .= "<font class='text'>\n";

	/************************/
	/* Show the folder path */
	/************************/
	$V .= Dir2Browse($this->Name,$Item,NULL,1,"Browse") . "<P />\n";

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
