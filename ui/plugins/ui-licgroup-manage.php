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

 -----------------------------------------------------

 The Javascript code to move values between tables is based
 on: http://www.mredkj.com/tutorials/tutorial_mixed2b.html
 The page, on 28-Apr-2008, says the code is "public domain".
 His terms and conditions (http://www.mredkj.com/legal.html)
 says "Code marked as public domain is without copyright, and
 can be used without restriction."
 This segment of code is noted in this program with "mredkj.com".
 ***********************************************************/

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

/************************************************
 Plugin for creating License Groups
 *************************************************/
class licgroup_manage extends FO_Plugin
  {
  var $Name       = "license_groups_manage";
  var $Title      = "Manage License Groups";
  var $Version    = "1.0";
  var $MenuList   = "Organize::License::Manage Groups";
  var $Dependency = array("db","licgroup","view-license");
  var $DBaccess   = PLUGIN_DB_ANALYZE;
  var $LoginFlag  = 1; /* must be logged in to use this */

  /***********************************************************
   LicGroupJavascript(): Return Javascript needed for this plugin.
   ***********************************************************/
  function LicGroupJavascript	()
    {
    $V = "";
    /* Javascript to sort a list */
    $V .= '
    <script language="JavaScript" type="text/javascript">
<!--
  function compSortList(Item1,Item2)
    {
    if (Item1.text < Item2.text) { return(-1); }
    if (Item1.text > Item2.text) { return(1); }
    return(0);
    }

  function SortList(List)
    {
    var ListItem = new Array(List.options.length);
    var i;
    for(i=0; i < List.options.length; i++)
      {
      ListItem[i] = new Option (
	List.options[i].text,
	List.options[i].value,
	List.options[i].selected,
	List.options[i].defaultSelected
	);
      }
    ListItem.sort(compSortList);
    for(i=0; i < List.options.length; i++)
      {
      List.options[i] = ListItem[i];
      }
    }

  function SelectAll()
    {
    var i;
    List = document.getElementById("liclist");
    for(i=0; i < List.options.length; i++)
      {
      List.options[i].selected = true;
      }
    List = document.getElementById("grplist");
    for(i=0; i < List.options.length; i++)
      {
      List.options[i].selected = true;
      }
    return(1);
    }
//-->
</script>';
  $V .= "\n";

    /*** BEGIN: code from mredkj.com ***/
    $V .= '
<script language="JavaScript" type="text/javascript">
<!--

var NS4 = (navigator.appName == "Netscape" && parseInt(navigator.appVersion) < 5);

function addOption(theSel, theText, theValue)
{
  var newOpt = new Option(theText, theValue);
  var selLength = theSel.length;
  theSel.options[selLength] = newOpt;
}

function deleteOption(theSel, theIndex)
{ 
  var selLength = theSel.length;
  if(selLength>0)
  {
    theSel.options[theIndex] = null;
  }
}

function moveOptions(theSelFrom, theSelTo)
{
  
  var selLength = theSelFrom.length;
  var selectedText = new Array();
  var selectedValues = new Array();
  var selectedCount = 0;
  
  var i;
  
  // Find the selected Options in reverse order
  // and delete them from the "from" Select.
  for(i=selLength-1; i>=0; i--)
  {
    if(theSelFrom.options[i].selected)
    {
      selectedText[selectedCount] = theSelFrom.options[i].text;
      selectedValues[selectedCount] = theSelFrom.options[i].value;
      deleteOption(theSelFrom, i);
      selectedCount++;
    }
  }
  
  // Add the selected text/values in reverse order.
  // This will add the Options to the "to" Select
  // in the same order as they were in the "from" Select.
  for(i=selectedCount-1; i>=0; i--)
  {
    addOption(theSelTo, selectedText[i], selectedValues[i]);
  }
  SortList(theSelTo); // NAK: Added sorting the destination list
  
  if(NS4) history.go(0);
}

//-->
</script>';
    /*** END: code from mredkj.com ***/
    $V .= "\n";
    return($V);
    } // LicGroupJavascript()

  /***********************************************************
   LicGroupCurrList(): Return the list of current groups, in
   a heirarchical tree.
   ***********************************************************/
  function LicGroupCurrList	($SelectKey=NULL, $PermitNew=0)
    {
    global $DB;
    /* Get list of groups */
    $V = "";
    if ($PermitNew) { $V .= "<option value='-1'>[New Group]</option>\n"; }
    $Results = $DB->Action("SELECT licgroup_name,licgroup_pk FROM licgroup ORDER BY licgroup_name;");
    for($i=0; !empty($Results[$i]['licgroup_pk']); $i++)
      {
      $V .= "<option ";
      if ($SelectKey == $Results[$i]['licgroup_pk']) { $V .= "selected "; }
      $V .= "value='" . $Results[$i]['licgroup_pk'] . "'>";
      $V .= htmlentities($Results[$i]['licgroup_name']);
      $V .= "</option>\n";
      }
    return($V);
    } // LicGroupCurrList()

  /***********************************************************
   LicGroupDelete(): Delete data!
   Returns NULL on success, or error string.
   ***********************************************************/
  function LicGroupDelete	()
    {
    global $DB;
    $GroupName = GetParm('name',PARM_TEXT);
    $GroupName = str_replace("'","''",$GroupName);
    $GroupKey = GetParm('groupkey',PARM_INTEGER);
    /* To delete: name and key number must match */
    $Results = $DB->Action("SELECT * FROM licgroup WHERE licgroup_pk = '$GroupKey';");
    $GroupKey = $Results[0]['licgroup_pk'];
    if (empty($GroupKey)) { return("Record not found.  Nothing to delete."); }
    $GroupName = GetParm('name',PARM_TEXT);
    if ($GroupName != $Results[0]['licgroup_name'])
      {
      return("Group name ($GroupName) does not match name field (" . $Results[0]['licgroup_name'] . ").  Delete aborted.");
      }

    $DB->Action("DELETE FROM licgroup_lics WHERE licgroup_fk = '$GroupKey';");
    $DB->Action("DELETE FROM licgroup_grps WHERE licgroup_fk = '$GroupKey';");
    $DB->Action("DELETE FROM licgroup_grps WHERE licgroup_memberfk = '$GroupKey';");
    $DB->Action("DELETE FROM licgroup WHERE licgroup_pk = '$GroupKey';");
    $DB->Action("VACUUM ANALYZE licgroup_lics;");
    $DB->Action("VACUUM ANALYZE licgroup_grps;");
    $DB->Action("VACUUM ANALYZE licgroup;");
    return;
    } // LicGroupDelete()

  /***********************************************************
   LicGroupInsert(): Someone posted data!  Add or update the group!
   Returns NULL on success, or error string.
   ***********************************************************/
  function LicGroupInsert	($GroupKey='',$GroupName='',$GroupDesc='',$GroupColor='',$GroupListLic=NULL,$GroupListGrp=NULL)
    {
    global $DB;
    if (empty($GroupKey)) { $GroupKey = GetParm('groupkey',PARM_INTEGER); }
    if ($GroupKey <= 0) { $GroupKey=NULL; }
    if (empty($GroupName)) { $GroupName = GetParm('name',PARM_TEXT); }
    if (empty($GroupDesc)) { $GroupDesc = GetParm('desc',PARM_TEXT); }
    if (empty($GroupColor)) { $GroupColor = GetParm('color',PARM_TEXT); }
    if (empty($GroupListLic)) { $GroupListLic = GetParm('liclist',PARM_RAW); } /* licenses in this group */
    if (empty($GroupListGrp)) { $GroupListGrp = GetParm('grplist',PARM_RAW); } /* groups in this group */
    /* Protect for the DB */
    $GroupName = str_replace("'","''",$GroupName);
    $GroupDesc = str_replace("'","''",$GroupDesc);
    if (preg_match("/^#[0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f]$/",$GroupColor) != 1)
      {
      return("Invalid color: $GroupColor\n");
      }
    /* Check if values look good */
    if (empty($GroupName)) { return("Group name must be specified.\n"); }

    if (!empty($GroupKey) && ($GroupKey >= 0))
      {
      $SQL = "SELECT licgroup_pk FROM licgroup WHERE licgroup_pk = '$GroupKey';";
      }
    else
      {
      $SQL = "SELECT * FROM licgroup WHERE licgroup_name = '$GroupName';";
      }
    $Results = $DB->Action($SQL);
    $GroupKey = $Results[0]['licgroup_pk'];

    /* Do the insert (or update) */
    if (empty($GroupKey))
      {
      $SQL = "INSERT INTO licgroup (licgroup_name,licgroup_desc,licgroup_color)
	VALUES ('$GroupName','$GroupDesc','$GroupColor');";
      }
    else
      {
      $SQL = "UPDATE licgroup SET licgroup_name = '$GroupName',
	licgroup_desc = '$GroupDesc',
	licgroup_color = '$GroupColor'
	WHERE licgroup_pk = '$GroupKey';";
      }
    $DB->Action($SQL);

    /* Check if it inserted */
    $Results = $DB->Action("SELECT * FROM licgroup WHERE licgroup_name = '$GroupName';");
    if (empty($Results[0]['licgroup_pk']))
      {
      return("Bad SQL: $SQL\n");
      }
    $GroupKey = $Results[0]['licgroup_pk'];

    /* Set licenses in the group */
    $DB->Action("BEGIN;");
    $Results = $DB->Action("DELETE FROM licgroup_lics WHERE licgroup_fk = '$GroupKey';");
    for($i=0; !empty($GroupListLic[$i]); $i++)
      {
      $LicNum = intval($GroupListLic[$i]);
      $DB->Action("INSERT INTO licgroup_lics (licgroup_fk,lic_fk)
	VALUES ('$GroupKey','$LicNum');");
      }
    $Results = $DB->Action("DELETE FROM licgroup_grps WHERE licgroup_fk = '$GroupKey';");
    for($i=0; !empty($GroupListGrp[$i]); $i++)
      {
      $GrpNum = intval($GroupListGrp[$i]);
      $DB->Action("INSERT INTO licgroup_grps (licgroup_fk,licgroup_memberfk)
	VALUES ('$GroupKey','$GrpNum');");
      }
    $DB->Action("COMMIT;");
    $DB->Action("VACUUM ANALYZE licgroup_lics;");
    $DB->Action("VACUUM ANALYZE licgroup_grps;");

    return;
    } // LicGroupInsert() */

  /***********************************************************
   LicGroupForm(): This creates the license group form.
   If no group name is passed in, then this is a "CREATE", otherwise
   it is an "EDIT".
   ***********************************************************/
  function LicGroupForm	($GroupKey=NULL)
    {
    global $DB;
    $V = "";
    $ColorParts1 = array("ff","00");  /* common colors */
    $ColorParts = array("ff","cc","99","66","33","00"); /* web safe colors */
    $GroupName = "";
    $GroupDesc = "";
    $GroupColor = "#ffffff";
    $GroupListLic = array(); /* licenses in this group */
    $GroupListGrp = array(); /* groups in this group */

    /* Get list of available licenses */
    $SQL = "SELECT lic_pk,lic_name FROM agent_lic_raw WHERE lic_id = lic_pk;";
    $Results = $DB->Action($SQL);
    $LicAvailable = array();
    for($i=0; !empty($Results[$i]['lic_pk']); $i++)
      {
      $Name = preg_replace("@^.*/@","",$Results[$i]['lic_name']);
      $LicAvailable[$Name] = $Results[$i]['lic_pk'];
      }
    ksort($LicAvailable);

    /* Get current (edit) settings */
    if (!empty($GroupKey))
      {
      /* Make sure it exists */
      $Results = $DB->Action("SELECT * FROM licgroup WHERE licgroup_pk = '$GroupKey';");
      $GroupKey = $Results[0]['licgroup_pk'];
      }
    if (!empty($GroupKey))
      {
      $GroupName = $Results[0]['licgroup_name'];
      $GroupDesc = $Results[0]['licgroup_desc'];
      $GroupColor = $Results[0]['licgroup_color'];
      /* Get list of licenses already in this group */
      $SQL = "SELECT lic_pk,lic_name FROM licgroup_lics
	INNER JOIN agent_lic_raw ON lic_fk = lic_pk
	WHERE licgroup_fk = '$GroupKey';";
      $Results = $DB->Action($SQL);
      for($i=0; !empty($Results[$i]['lic_pk']); $i++)
	{
	$Name = preg_replace("@^.*/@","",$Results[$i]['lic_name']);
	$GroupListLic[$Name] = $Results[$i]['lic_pk'];
	unset($LicAvailable[$Name]); /* make it unavailable */
	}
      }

    $V .= $this->LicGroupJavascript();

    $V .= "<form name='formy' method='post' onSubmit='return SelectAll();'>\n";
    $V .= "<table style='border:1px solid black; text-align:left; background:lightyellow;' width='100%' border='1'>\n";

    /* List groups fields */
    $V .= "<tr>\n";
    $V .= "<td width='20%'>Select management action</td>";
    $Uri = Traceback_uri() . "?mod=" . $this->Name . "&groupkey=";
    $V .= "<td><select name='groupkey' onChange='window.open(\"$Uri\"+this.value,\"_top\");'>\n";
    $V .= $this->LicGroupCurrList($GroupKey,1);
    $V .= "</select>\n";
    $V .= "<td>";
    $V .= "</td>";

    /* Text fields */
    $V .= "</tr><tr>\n";
    $V .= "<td width='20%'>Group name</td><td><input type='text' name='name' size='60' value='" . htmlentities($GroupName,ENT_QUOTES) . "'></td>\n";
    $V .= "</tr><tr>\n";
    $V .= "<td>Group description</td><td><input type='text' name='desc' size='60' value='" . htmlentities($GroupDesc,ENT_QUOTES) . "'></td>\n";

    $V .= "</tr><tr>\n";
    $V .= "<td>Group color</td><td>";
    $V .= "<select name='color' style='background-color:$GroupColor' onSelect='this.style.background=this.value;' onChange='this.style.background=this.value;'>\n";
    foreach($ColorParts1 as $C1)
    foreach($ColorParts1 as $C2)
    foreach($ColorParts1 as $C3)
      {
      $Color = "#" . $C1 . $C2 . $C3;
      $V .= "<option value='$Color' style='background-color:$Color'";
      if (!strcasecmp($Color,$GroupColor)) { $V .= " selected"; }
      $V .= ">";
      // $V .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
      $V .= $Color;
      $V .= "</option>";
      }
    foreach($ColorParts as $C1)
    foreach($ColorParts as $C2)
    foreach($ColorParts as $C3)
      {
      $Color = "#" . $C1 . $C2 . $C3;
      $V .= "<option value='$Color' style='background-color:$Color'";
      if (!strcasecmp($Color,$GroupColor)) { $V .= " selected"; }
      $V .= ">";
      // $V .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
      $V .= $Color;
      $V .= "</option>";
      }
    $V .= "</select>\n";

    /* Get the list of licenses */
    $V .= "</tr><tr>\n";
    $V .= "<td>Select licenses to include in the group</td><td>";
    $V .= "<table width='100%'>";
    $V .= "<tr><td align='center' width='45%'>Available licenses</td><td width='10%'></td><td width='45%' align='center'>Licenses in this Group</td></tr>";
    $V .= "<tr>";
    $V .= "<tr><td>";
    $V .= "<select multiple='multiple' id='licavailable' name='licavailable' size='10'>";
    foreach($LicAvailable as $Name => $Key)
      {
      $V .= "<option value='$Key'";
      $V .= ">" . htmlentities($Name) . "</option>\n";
      }
    $V .= "</select>";

    /* center list of options */
    /*** <-- View ***/
    $V .= "</td><td>";
    $V .= "<center>\n";
    $Uri = "onClick=\"javascript:if (document.getElementById('licavailable').value) window.open('";
    $Uri .= Traceback_uri();
    $Uri .= "?mod=view-license";
    $Uri .= "&format=flow";
    $Uri .= "&lic=";
    $Uri .= "' + document.getElementById('licavailable').value + '";
    $Uri .= "&licset=";
    $Uri .= "' + document.getElementById('licavailable').value";
    $Uri .= ",'License','width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes');\"";

    /*** View --> ***/
    $V .= "<a href='#' $Uri>&larr;View</a><P/>\n";
    $Uri = "onClick=\"javascript:if (document.getElementById('liclist').value) window.open('";
    $Uri .= Traceback_uri();
    $Uri .= "?mod=view-license";
    $Uri .= "&format=flow";
    $Uri .= "&lic=";
    $Uri .= "' + document.getElementById('liclist').value + '";
    $Uri .= "&licset=";
    $Uri .= "' + document.getElementById('liclist').value";
    $Uri .= ",'License','width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes');\"";
    $V .= "<a href='#' $Uri>View&rarr;</a><hr/>\n";

    /*** Add --> ***/
    $V .= "<a href='#' onClick='moveOptions(document.formy.licavailable,document.formy.liclist);'>Add&rarr;</a><P/>\n";

    /*** <-- Remove ***/
    $V .= "<a href='#' onClick='moveOptions(document.formy.liclist,document.formy.licavailable);'>&larr;Remove</a>\n";
    $V .= "</center>\n";

    /* List the license groups */
    $V .= "</td><td>";
    $V .= "<select multiple='multiple' id='liclist' name='liclist[]' size='10'>";
    ksort($GroupListLic);
    foreach($GroupListLic as $Name => $Key)
      {
      $V .= "<option value='$Key'>";
      $V .= htmlentities($Name) . "</option>\n";
      }
    $V .= "</select>";
    $V .= "</td></table>\n";

    /* Groups can contain groups */
    $V .= "</tr><tr>\n";
    $V .= "<td>Select subgroups to include in this group</td><td>";
    $V .= "<table width='100%'>";
    $V .= "<tr><td align='center' width='45%'>Available subgroups</td><td width='10%'></td><td width='45%' align='center'>Subgroups in this Group</td></tr>";
    $V .= "<tr>";
    $V .= "<tr><td>";
    $V .= "<select multiple='multiple' id='grpavailable' name='grpavailable' size='10'>";
    if (!empty($GroupKey))
      {
      $SQL = "SELECT DISTINCT licgroup_pk,licgroup_name
	FROM licgroup
	LEFT OUTER JOIN licgroup_grps ON licgroup_memberfk = licgroup_pk
	WHERE licgroup_pk != '$GroupKey'
	AND
	  (licgroup_fk IS NULL OR licgroup_fk != '$GroupKey')
	ORDER BY licgroup_name;";
      }
    else
      {
      $SQL = "SELECT * FROM licgroup ORDER BY licgroup_name;";
      }
    $Results = $DB->Action($SQL);
    for($i=0; !empty($Results[$i]['licgroup_pk']); $i++)
      {
      $V .= "<option value='" . $Results[$i]['licgroup_pk'] . "'>";
      $V .= htmlentities($Results[$i]['licgroup_name']) . "</option>\n";
      }
    $V .= "</select>";

    /*** Add --> ***/
    $V .= "</td><td>";
    $V .= "<a href='#' onClick='moveOptions(document.formy.grpavailable,document.formy.grplist);'>Add&rarr;</a><P/>\n";

    /*** <-- Remove ***/
    $V .= "<a href='#' onClick='moveOptions(document.formy.grplist,document.formy.grpavailable);'>&larr;Remove</a>\n";
    $V .= "</center>\n";

    /* List the license subgroups */
    $V .= "</td><td>";
    if (!empty($GroupKey))
      {
      $SQL = "SELECT DISTINCT licgroup_memberfk,licgroup_name FROM licgroup INNER JOIN licgroup_grps ON licgroup_memberfk = licgroup_pk WHERE licgroup_fk = '$GroupKey' ORDER BY licgroup_name;";
      $Results = $DB->Action($SQL);
      }
    else { $Results = NULL; }
    $V .= "<select multiple='multiple' id='grplist' name='grplist[]' size='10'>";
    for($i=0; !empty($Results[$i]['licgroup_memberfk']); $i++)
      {
      $V .= "<option value='" . $Results[$i]['licgroup_memberfk'] . "'>";
      $V .= htmlentities($Results[$i]['licgroup_name']) . "</option>\n";
      }
    $V .= "</select>";

    $V .= "</td></table>\n";

    /* Permit delete */
    $V .= "</tr><tr>\n";
    $V .= "<td>Delete</td>";
    $V .= "<td><input type='checkbox' value='1' name='delete'><b>Check to delete this license group!</b></td>\n";

    $V .= "</tr>\n";
    $V .= "</table>\n";
    $V .= "<input type='submit' value='Go!'>\n";
    $V .= "</form>\n";
    return($V);
    } // LicGroupForm()

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
	$Name = GetParm('name',PARM_STRING);
	$Delete = GetParm('delete',PARM_INTEGER);
	if (!empty($Name))
	  {
	  if ($Delete == 1) { $rc = $this->LicGroupDelete(); }
	  else { $rc = $this->LicGroupInsert(); }
	  if (empty($rc))
	    {
	    /* Need to refresh the screen */
	    $V .= "<script language='javascript'>\n";
	    $V .= "alert('License group information updated.')\n";
	    $V .= "</script>\n";
	    }
	  else
	    {
	    $V .= "<script language='javascript'>\n";
	    $rc = htmlentities($rc,ENT_QUOTES);
	    $V .= "alert('$rc')\n";
	    $V .= "</script>\n";
	    }
	  }
	$GroupKey = GetParm('groupkey',PARM_INTEGER);
	if ($GroupKey <= 0) { $GroupKey = NULL; }
	$V .= $this->LicGroupForm($GroupKey);
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print($V);
    return;
    }

  };
$NewPlugin = new licgroup_manage;
$NewPlugin->Initialize();
?>
