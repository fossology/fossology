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

class search_file_advance extends FO_Plugin
  {
  var $Name       = "search_file_advance";
  var $Title      = "Advanced Search for File";
  var $Version    = "1.0";
  var $MenuList   = "";
  var $Dependency = array("db","view","browse");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;

  /***********************************************************
   GetUploadtreeFromName(): Given a filename, return all uploadtree.
   ***********************************************************/
  function GetUploadtreeFromName($Filename,$Page,$MimetypeNot,$Mimetype,$SizeMin,$SizeMax)
    {
    global $DB;
    $Max = 50;
    $Filename = str_replace("'","''",$Filename); // protect DB
    $Terms = split("[[:space:]][[:space:]]*",$Filename);
    $SQL = "SELECT * FROM uploadtree INNER JOIN pfile ON pfile_pk = pfile_fk";
    foreach($Terms as $Key => $T)
	{
	$SQL .= " AND ufile_name like '$T'";
	}
    $NeedAnd=0;
    if (!empty($Mimetype) && ($Mimetype >= 0))
	{
	if ($NeedAnd) { $SQL .= " AND"; }
	else { $SQL .= " WHERE"; }
	$SQL .= " pfile.pfile_mimetypefk ";
	if ($MimetypeNot != 0) { $SQL .= "!"; }
	$SQL .= "= $Mimetype";
	$NeedAnd=1;
	}
    if (!empty($SizeMin) && ($SizeMin >= 0))
	{
	if ($NeedAnd) { $SQL .= " AND"; }
	else { $SQL .= " WHERE"; }
	$SQL .= " pfile.pfile_size > $SizeMin";
	$NeedAnd=1;
	}
    if (!empty($SizeMax) && ($SizeMax >= 0))
	{
	if ($NeedAnd) { $SQL .= " AND"; }
	else { $SQL .= " WHERE"; }
	$SQL .= " pfile.pfile_size < $SizeMax";
	$NeedAnd=1;
	}
    $Offset = $Page * $Max;
    $SQL .= " ORDER BY pfile_fk,ufile_name LIMIT $Max OFFSET $Offset;";
    $Results = $DB->Action($SQL);

    $V = "";
    $Count = count($Results);
    // $V .= "<pre>" . htmlentities($SQL) . "</pre>\n";

    if (($Page > 0) || ($Count >= $Max))
      {
      $Uri = Traceback_uri() . "?mod=" . $this->Name;
      $Uri .= "&filename=" . urlencode($Filename);
      $Uri .= "&sizemin=$SizeMin";
      $Uri .= "&sizemax=$SizeMax";
      $Uri .= "&notmimetype=$MimetypeNot";
      $Uri .= "&mimetype=$Mimetype";
      $VM = MenuEndlessPage($Page, ($Count >= $Max),$Uri) . "<P />\n";
      $V .= $VM;
      }
    else
      {
      $VM = "";
      }

    if ($Count == 0)
	{
	$V .= "No results.\n";
	return($V);
	}

    if ($Page==0)
      {
      $SQL = preg_replace('/\*/','COUNT(*) AS count',$SQL,1);
      $SQL = preg_replace('/ ORDER BY .*;/',';',$SQL);
      $Count = $DB->Action($SQL);
      $V .= "Total matched: " . number_format($Count[0]['count'],0,"",",") . "<br>\n";
      }

    $V .= Dir2FileList($Results,"browse","view",$Page*$Max + 1);

    /* put page menu at the bottom, too */
    if (!empty($VM)) { $V .= "<P />\n" . $VM; }
    return($V);
    } // GetUploadtreeFromName()

  /***********************************************************
   RegisterMenus(): Customize submenus.
   ***********************************************************/
  function RegisterMenus()
    {
    $URI = $this->Name;
    menu_insert("Search::Advanced",0,$URI,"Additional search options");
    } // RegisterMenus()

  /***********************************************************
   Output(): Display the loaded menu and plugins.
   ***********************************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $Plugins;
    global $DB;
    switch($this->OutputType)
      {
      case "XML":
        break;
      case "HTML":
	$V .= menu_to_1html(menu_find("Search",$MenuDepth),1);

	$Filename = GetParm("filename",PARM_STRING);
	$SizeMin = GetParm("sizemin",PARM_TEXT) . 'x';
	if ($SizeMin != 'x') { $SizeMin=intval($SizeMin); }
	else { $SizeMin = -1; }
	if ($SizeMin < 0) { $SizeMin=-1; }
	$SizeMax = GetParm("sizemax",PARM_TEXT) . 'x';
	if ($SizeMax != 'x') { $SizeMax=intval($SizeMax); }
	else { $SizeMax = -1; }
	if ($SizeMax < 0) { $SizeMax=-1; }
	$MimetypeNot = GetParm("notmimetype",PARM_INTEGER);
	$Mimetype = GetParm("mimetype",PARM_INTEGER);
	$Page = GetParm("page",PARM_INTEGER);

	$V .= "You can use '%' as a wild-card.\n";
	$V .= "<form action='" . Traceback_uri() . "?mod=" . $this->Name . "' method='POST'>\n";
	$V .= "<ul>\n";
	$V .= "<li>Enter the filename to find: ";
	$V .= "<INPUT type='text' name='filename' size='40' value='" . htmlentities($Filename) . "'>\n";

	$V .= "<li>Mimetype ";
	$V .= "<select name='notmimetype'>\n";
	if ($MimetypeNot == 0)
	  {
	  $V .= "<option value='0' selected>IS</option>\n";
	  $V .= "<option value='1'>IS NOT</option>\n";
	  }
	else
	  {
	  $V .= "<option value='0'>IS</option>\n";
	  $V .= "<option value='1' selected>IS NOT</option>\n";
	  }
	$V .= "</select>\n";
	$V .= "<select name='mimetype'>\n";
	$Results = $DB->Action("SELECT * FROM mimetype ORDER BY mimetype_name;");
	$V .= "<option value='-1'>Select mimetype...</option>\n";
	for($i=0; !empty($Results[$i]['mimetype_pk']); $i++)
	  {
	  if ($Results[$i]['mimetype_pk'] == $Mimetype)
	    {
	    $V .= "<option value='" . $Results[$i]['mimetype_pk'] . "' selected>";
	    }
	  else
	    {
	    $V .= "<option value='" . $Results[$i]['mimetype_pk'] . "'>";
	    }
	  $V .= $Results[$i]['mimetype_name'];
	  $V .= "</option>\n";
	  }
	$V .= "</select>\n";
	$Value=$SizeMin; if ($Value < 0) { $Value=''; }
	$V .= "<li>File size is &gt; <input name='sizemin' size=10 value='$Value'> bytes\n";
	$Value=$SizeMax; if ($Value < 0) { $Value=''; }
	$V .= "<li>File size is &lt; <input name='sizemax' size=10 value='$Value'> bytes\n";

	$V .= "</ul>\n";
	$V .= "<input type='submit' value='Search!'>\n";
	$V .= "</form>\n";

	if (!empty($Filename))
	  {
	  if (empty($Page)) { $Page = 0; }
	  $V .= "<hr>\n";
	  $V .= "<H2>Files matching " . htmlentities($Filename) . "</H2>\n";
	  $V .= $this->GetUploadtreeFromName($Filename,$Page,$MimetypeNot,$Mimetype,$SizeMin,$SizeMax);
	  }
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
$NewPlugin = new search_file_advance;
$NewPlugin->Initialize();

?>
