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
 Plugin for License Terms
 *************************************************/
class licterm_manage extends FO_Plugin
  {
  var $Name       = "licterm_manage";
  var $Title      = "Manage License Terms";
  var $Version    = "1.0";
  var $Dependency = array("db");
  var $DBaccess   = PLUGIN_DB_ANALYZE;
  var $MenuList   = "Organize::License::Manage Terms";
  var $LoginFlag  = 0;

  /***********************************************************
   Install(): Create and configure database tables
   ***********************************************************/
  function Install()
  {
     global $DB;
     if (empty($DB)) { return(1); } /* No DB */

    /****************
     Terms needed tables:
     Table #1: List of term groups (name, description) ("licterm")
     Table #2: List of terms ("licterm_words")
     Table #3: Associated matrix of terms to term groups ("licterm_map")
     ****************/

    /* Create TABLE licterm if it does not exist */
    $SQL = "SELECT relname FROM pg_class WHERE relkind = 'S' AND relname = 'licterm_licterm_pk_seq';";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['relname']))
      {
      $SQL1 = "CREATE SEQUENCE licterm_licterm_pk_seq START 1;";
      $DB->Action($SQL1);
      }
    $SQL = "SELECT table_name AS table
	FROM information_schema.tables
	WHERE table_type = 'BASE TABLE'
	AND table_schema = 'public'
	AND table_name = 'licterm';";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['table']))
      {
      $SQL1 = "CREATE TABLE licterm (
	licterm_pk integer PRIMARY KEY DEFAULT nextval('licterm_licterm_pk_seq'),
	licterm_name text UNIQUE,
	licterm_desc text UNIQUE
	);
	COMMENT ON COLUMN licterm.licterm_name IS 'Name of License Term Group';
	COMMENT ON COLUMN licterm.licterm_desc IS 'Description of License Term Group';
	";
      $DB->Action($SQL1);
      $Results = $DB->Action($SQL);
      if (empty($Results[0]['table']))
	{
	printf("ERROR: Failed to create table: licterm\n");
	return(1);
	}
      } /* create TABLE licterm */

    /* Create TABLE licterm_words if it does not exist */
    $SQL = "SELECT relname FROM pg_class WHERE relkind = 'S' AND relname = 'licterm_words_licterm_words_pk_seq';";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['relname']))
      {
      $SQL1 = "CREATE SEQUENCE licterm_words_licterm_words_pk_seq START 1;";
      $DB->Action($SQL1);
      }
    $SQL = "SELECT table_name AS table
	FROM information_schema.tables
	WHERE table_type = 'BASE TABLE'
	AND table_schema = 'public'
	AND table_name = 'licterm_words';";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['table']))
      {
      $SQL1 = "CREATE TABLE licterm_words (
	licterm_words_pk integer PRIMARY KEY DEFAULT nextval('licterm_words_licterm_words_pk_seq'),
	licterm_words_text text UNIQUE
	);
	COMMENT ON COLUMN licterm_words.licterm_words_text IS 'Text for a keyword term';
	";
      $DB->Action($SQL1);
      $Results = $DB->Action($SQL);
      if (empty($Results[0]['table']))
	{
	printf("ERROR: Failed to create table: licterm_words\n");
	return(1);
	}
      } /* create TABLE licterm_words */

    /* Check if TABLE licterm_map exists */
    $SQL = "SELECT relname FROM pg_class WHERE relkind = 'S' AND relname = 'licterm_map_licterm_map_pk_seq';";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['relname']))
      {
      $SQL1 = "CREATE SEQUENCE licterm_map_licterm_map_pk_seq START 1;";
      $DB->Action($SQL1);
      }
    $SQL = "SELECT table_name AS table
	FROM information_schema.tables
	WHERE table_type = 'BASE TABLE'
	AND table_schema = 'public'
	AND table_name = 'licterm_map';";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['table']))
      {
      $SQL1 = "CREATE TABLE licterm_map (
	licterm_map_pk integer PRIMARY KEY DEFAULT nextval('licterm_map_licterm_map_pk_seq'),
	licterm_fk      integer,
	licterm_words_fk integer,
	CONSTRAINT only_one_licterm_map UNIQUE (licterm_fk, licterm_words_fk),
	CONSTRAINT licterm_exist FOREIGN KEY(licterm_fk) REFERENCES licterm(licterm_pk) ON UPDATE RESTRICT ON DELETE RESTRICT,
	CONSTRAINT lictermwords_exist FOREIGN KEY(licterm_words_fk) REFERENCES licterm_words(licterm_words_pk) ON UPDATE RESTRICT ON DELETE RESTRICT
	);
	COMMENT ON COLUMN licterm_map.licterm_fk IS 'Key of parent term group';
	COMMENT ON COLUMN licterm_map.licterm_words_fk IS 'Key of term word group that belongs to licterm_fk';
	";
      $DB->Action($SQL1);
      $Results = $DB->Action($SQL);
      if (empty($Results[0]['table']))
	{
	printf("ERROR: Failed to create table: licterm_map\n");
	return(1);
	}
      } /* create TABLE licterm_map */

    /* Check if TABLE licterm_maplic exists */
    $SQL = "SELECT relname FROM pg_class WHERE relkind = 'S' AND relname = 'licterm_maplic_licterm_maplic_pk_seq';";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['relname']))
      {
      $SQL1 = "CREATE SEQUENCE licterm_maplic_licterm_maplic_pk_seq START 1;";
      $DB->Action($SQL1);
      }
    $SQL = "SELECT table_name AS table
	FROM information_schema.tables
	WHERE table_type = 'BASE TABLE'
	AND table_schema = 'public'
	AND table_name = 'licterm_maplic';";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['table']))
      {
      $SQL1 = "CREATE TABLE licterm_maplic (
	licterm_maplic_pk integer PRIMARY KEY DEFAULT nextval('licterm_maplic_licterm_maplic_pk_seq'),
	licterm_fk       integer,
	lic_fk           integer,
	CONSTRAINT only_one_licterm_maplic UNIQUE (licterm_fk, lic_fk),
	CONSTRAINT lictermlicterm_exist FOREIGN KEY(licterm_fk) REFERENCES licterm(licterm_pk) ON UPDATE RESTRICT ON DELETE RESTRICT
	);
	COMMENT ON COLUMN licterm_maplic.licterm_fk IS 'Key of parent term group';
	COMMENT ON COLUMN licterm_maplic.lic_fk IS 'License that belongs to licterm_fk';
	";
      $DB->Action($SQL1);
      $Results = $DB->Action($SQL);
      if (empty($Results[0]['table']))
	{
	printf("ERROR: Failed to create table: licterm_maplic\n");
	return(1);
	}
      } /* create TABLE licterm_maplic */

    /* Check if TABLE licterm_name exists */
    /** licterm_name: assign the name to a agent_lic_meta row. **/
    $SQL = "SELECT relname FROM pg_class WHERE relkind = 'S' AND relname = 'licterm_name_licterm_name_pk_seq';";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['relname']))
      {
      $SQL1 = "CREATE SEQUENCE licterm_name_licterm_name_pk_seq START 1;";
      $DB->Action($SQL1);
      }
    $SQL = "SELECT table_name AS table
	FROM information_schema.tables
	WHERE table_type = 'BASE TABLE'
	AND table_schema = 'public'
	AND table_name = 'licterm_name';";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['table']))
      {
      $SQL1 = "CREATE TABLE licterm_name (
	licterm_name_pk integer PRIMARY KEY DEFAULT nextval('licterm_name_licterm_name_pk_seq'),
	pfile_fk         integer,
	licterm_fk       integer,
	agent_lic_meta_fk integer,
	licterm_name_confidence integer,
	CONSTRAINT only_one_licterm_name UNIQUE (licterm_fk, agent_lic_meta_fk),
	CONSTRAINT lictermname_pfile_exist FOREIGN KEY(pfile_fk) REFERENCES pfile(pfile_pk) ON UPDATE RESTRICT ON DELETE RESTRICT,
	CONSTRAINT lictermname_licterm_exist FOREIGN KEY(licterm_fk) REFERENCES licterm(licterm_pk) ON UPDATE RESTRICT ON DELETE RESTRICT,
	CONSTRAINT lictermname_licmeta_exist FOREIGN KEY(agent_lic_meta_fk) REFERENCES agent_lic_meta(agent_lic_meta_pk) ON UPDATE RESTRICT ON DELETE RESTRICT
	);
	COMMENT ON COLUMN licterm_name.pfile_fk IS 'Pfile containing this canonical name';
	COMMENT ON COLUMN licterm_name.licterm_fk IS 'Canonical name to use (null means use the real license name)';
	COMMENT ON COLUMN licterm_name.agent_lic_meta_fk IS 'License range containing this canonical name';
	COMMENT ON COLUMN licterm_name.licterm_name_confidence IS 'Confidence that the license template name is correct (3=no, 2=partial, 1=style, 0=use the name)';
	";
      $DB->Action($SQL1);
      $Results = $DB->Action($SQL);
      if (empty($Results[0]['table']))
	{
	printf("ERROR: Failed to create table: licterm_name\n");
	return(1);
	}
      } /* create TABLE licterm_name */

  /* Make sure the licinspect status column exists */
  if (! $DB->ColExist('agent_lic_status','inspect_name'))
    {
    $DB->Action("ALTER TABLE agent_lic_status
	ADD COLUMN inspect_name boolean DEFAULT FALSE;
	COMMENT ON COLUMN agent_lic_status.inspect_name IS 'Has licinspect processed the pfile?';");
    }

  return(0);
  } // Install()

  /***********************************************************
   LicTermJavascript(): All of the Javascript needed for this plugin.
   ***********************************************************/
  function LicTermJavascript	()
    {
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
  for(i=0; i < List.options.length; i++) { List.options[i] = ListItem[i]; }
  }

function AddText()
  {
  var Text = document.getElementById("newtext").value.toLowerCase();
  Text = Text.replace(/[^a-zA-Z0-9]/g," ");
  Text = Text.replace(/  */g," ");
  Text = Text.replace(/^ */,"");
  Text = Text.replace(/ *$/,"");
  /* Reset */
  document.getElementById("newtext").value = "";
  if (Text == "") { return; } /* no blanks */
  /* No duplicates */
  var TermList = document.getElementById("termlist");
  var i;
  for(i=0; i < TermList.length; i++)
    {
    if (TermList.options[i].text == Text) { return; }
    }
  /* Add it */
  addOption(TermList,Text,Text);
  SortList(TermList);
  }

function UnselectForm(Name)
  {
  var i;
  List = document.getElementById(Name);
  for(i=0; i < List.options.length; i++) { List.options[i].selected = false; }
  return(1);
  }

function SelectAll()
  {
  var i;
  List = document.getElementById("termlist");
  for(i=0; i < List.options.length; i++) { List.options[i].selected = true; }
  List = document.getElementById("liclist");
  for(i=0; i < List.options.length; i++) { List.options[i].selected = true; }
  return(1);
  }

function ToggleForm(Value)
  {
  document.formy.name.disabled = Value;
  document.formy.desc.disabled = Value;
  document.formy.termlist.disabled = Value;
  document.formy.termavailable.disabled = Value;
  document.formy.newtext.disabled = Value;
  document.formy.addtext.disabled = Value;
  document.formy.liclist.disabled = Value;
  document.formy.licavailable.disabled = Value;
  document.formy.deleteword.disabled = Value;
  }
//-->';
    $V .= "</script>\n";

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
    } // LicTermJavascript()

  /***********************************************************
   LicTermCurrList(): Returns a list of the current term groups.
   ***********************************************************/
  function LicTermCurrList	($TermKey=NULL)
    {
    global $DB;
    $V = "<option value='-1'>[New Term]</option>\n";
    $SQL = "SELECT * FROM licterm ORDER BY licterm_name;";
    $Results = $DB->Action($SQL);
    for($i=0; !empty($Results[$i]['licterm_pk']); $i++)
      {
      $V .= "<option value='" . $Results[$i]['licterm_pk'] . "'";
      if ($Results[$i]['licterm_pk'] == $TermKey)
        {
	$V .= " selected";
	}
      $V .= ">";
      $V .= htmlentities($Results[$i]['licterm_name']);
      $V .= "</option>\n";
      }
    return($V);
    } // LicTermCurrList()

  /***********************************************************
   LicTermCurrWords(): Returns a list of the current term words
   in the group.
   ***********************************************************/
  function LicTermCurrWords	($Term)
    {
    global $DB;
    $V = "";
    $SQL = "SELECT licterm_words.licterm_words_text AS text FROM licterm
	INNER JOIN licterm_map ON licterm.licterm_pk = '$Term'
	AND licterm.licterm_pk = licterm_map.licterm_fk
	INNER JOIN licterm_words ON licterm_words_pk = licterm_map.licterm_words_fk
	ORDER BY licterm_words_text;";
    $Results = $DB->Action($SQL);
    for($i=0; !empty($Results[$i]['text']); $i++)
      {
      $Text = strtolower($Results[$i]['text']);
      $Text = preg_replace("[^[a-zA-Z0-9]"," ",$Text);
      $Text = trim(preg_replace(" +"," ",$Text));
      $V .= "<option value='$Text'>$Text</option>\n";
      }
    return($V);
    } // LicTermCurrWords()

  /***********************************************************
   LicTermForm(): Build the HTML form.
   ***********************************************************/
  function LicTermForm	($TermKey=NULL)
    {
    global $DB;
    $TermName = "";
    $TermDesc = "";
    $TermListWords = array(); /* words in this term group */

    if (!empty($TermKey))
      {
      $Results = $DB->Action("SELECT * FROM licterm WHERE licterm_pk = '$TermKey';");
      $TermKey = $Results[0]['licterm_pk'];
      }
    if (!empty($TermKey))
      {
      $TermName = $Results[0]['licterm_name'];
      $TermDesc = $Results[0]['licterm_desc'];
      }

    $V = "";
    $V .= "Keyword terms and phrases are used during license analysis to better identify license names.\n";
    $V .= "Terms consist of two parts: a canonical name for the class of terms, and a list of words or phrases that are members of the class.\n";
    $V .= "For example, the phrases 'GNU General Public License version 2' and 'GPL version 2' may both be parts of the 'GPLv2' class.\n"; 
    $V .= "<P />\n";
    $V .= "<b>Note</b>: Changes to this list will impact all new license analysis.\n";
    $V .= "However, all completed license analysis will be unchanged.\n";
    $V .= "You may wish to clear the license analysis for an upload and reschedule the analysis in order to apply changes.\n";
    $V .= "<P />\n";

    $V .= "<form name='formy' method='post' onSubmit='return SelectAll();'>\n";
    $V .= "<table style='border:1px solid black; text-align:left; background:lightyellow;' width='100%' border='1'>\n";

    /***********************************************************/
    /* List groups fields */
    $V .= "<tr>\n";
    $V .= "<td width='20%'>Select canonical group to edit</td>";
    $Uri = Traceback_uri() . "?mod=" . $this->Name . "&termkey=";
    $V .= "<td><select name='termkey' onChange='window.open(\"$Uri\"+this.value,\"_top\");'>\n";
    $V .= $this->LicTermCurrList($TermKey);
    $V .= "</select>\n";
    /* Permit delete */
    $V .= "<input type='checkbox' value='1' name='delete' onclick='ToggleForm(this.checked);'><b>Check to delete this canonical group!</b></td>\n";
    $V .= "</td>";
    $V .= "</tr>\n";

    /***********************************************************/
    /* Text fields */
    $V .= "<tr>\n";
    $V .= "<td width='20%'>Canonical name</td><td><input type='text' name='name' size='60' value='" . htmlentities($TermName,ENT_QUOTES) . "'></td>\n";
    $V .= "</tr><tr>\n";
    $V .= "<td>Description</td><td><input type='text' name='desc' size='60' value='" . htmlentities($TermDesc,ENT_QUOTES) . "'></td>\n";
    $V .= "</tr>\n";

    /***********************************************************/
    /* Add a new term */
    $V .= "<tr>\n";
    $V .= "<td width='20%'>Keywords, terms, and phrases specific to this group.</td>";

    $V .= "<td>";
    $V .= "<table width='100%'>";
    $V .= "<td align='center' width='45%'>Terms associated with this canonical group</td><td width='10%'></td><td width='45%' align='center'>Known terms</td></tr>";

    /* List these license terms */
    if (!empty($TermKey))
      {
      $TermList = $DB->Action("SELECT licterm_words_text AS text FROM licterm_words INNER JOIN licterm_map ON licterm_words_pk = licterm_words_fk AND licterm_fk = '$TermKey' ORDER BY licterm_words_text;"); 
      $TermAvailable = $DB->Action("SELECT licterm_words_text AS text FROM licterm_words WHERE licterm_words_pk NOT IN (SELECT licterm_words_fk FROM licterm_words INNER JOIN licterm_map ON licterm_words_pk = licterm_words_fk AND licterm_fk = '$TermKey') ORDER BY licterm_words_text;"); 
      }
    else
      {
      $TermList = array();
      $TermAvailable = $DB->Action("SELECT licterm_words_text AS text FROM licterm_words ORDER BY licterm_words_text;");
      }

    /* List all license terms */
    $V .= "<tr>";
    $V .= "<td>";
    $V .= "<select onFocus='UnselectForm(\"termavailable\");' onChange='document.getElementById(\"newtext\").value=this.value' multiple='multiple' id='termlist' name='termlist[]' size='10'>";
    for($i=0; !empty($TermList[$i]['text']); $i++)
      {
      $Text = strtolower($TermList[$i]['text']);
      $Text = preg_replace("/[^a-z0-9]/"," ",$Text);
      $Text = preg_replace("/ +/"," ",$Text);
      $Text = trim(preg_replace("/  */"," ",$Text));
      $V .= "<option value='$Text'>$Text</option>\n";
      }
    $V .= "</select>";
    $V .= "</td>\n";

    /* center list of options */
    $V .= "<td>";
    $V .= "<center>\n";
    $V .= "<a href='#' onClick='moveOptions(document.formy.termavailable,document.formy.termlist);'>&larr;Add</a><P/>\n";
    $V .= "<a href='#' onClick='moveOptions(document.formy.termlist,document.formy.termavailable);'>Remove&rarr;</a>\n";
    $V .= "</center></td>\n";

    $V .= "<td>";
    $V .= "<select onFocus='UnselectForm(\"termlist\");' onChange='document.getElementById(\"newtext\").value=this.value' multiple='multiple' id='termavailable' name='termavailable' size='10'>";
    for($i=0; !empty($TermAvailable[$i]['text']); $i++)
      {
      $Text = strtolower($TermAvailable[$i]['text']);
      $Text = preg_replace("/[^a-z0-9]/"," ",$Text);
      $Text = trim(preg_replace("/  */"," ",$Text));
      $V .= "<option value='$Text'>$Text</option>\n";
      }
    $V .= "</select>";
    $V .= "</td></table>\n";
    $V .= "</tr>\n";

    /* Permit new words */
    $V .= "<tr>";
    $V .= "<td>Add a new keyword, term, or phrase to this canonical group.\n";
    $V .= "</td>";
    $V .= "<td>";
    $V .= "<input type='text' id='newtext' size='60'>";
    $V .= "<input type='button' id='addtext' onClick='AddText(this)' value='Add!'>";
    $V .= "<br>\n";
    $V .= "Only letters, numbers, and spaces are permitted. Text will be normalized to lowercase letters with no more than one space between words.\n";
    $V .= "</td>";

    /***********************************************************/
    /* Permit associating licenses with canonical names */
    $V .= "<tr>\n";
    $V .= "<td width='20%'>Associate licenses with this canonical group.\n";
    $V .= "<P />\n";
    $V .= "Licenses that are associated will be referred by the canonical name.\n";
    $V .= "Unassociated licenses are referred by their license name.";
    $V .= "</td>";

    $V .= "<td>";
    $V .= "<table width='100%'>";
    $V .= "<td align='center' width='45%'>Licenses associated with this canonical group</td><td width='10%'></td><td width='45%' align='center'>Known Licenses</td></tr>";

    /* List these license terms */
    if (!empty($TermKey))
      {
      $LicList = $DB->Action("SELECT lic_pk AS id, lic_name AS text FROM agent_lic_raw INNER JOIN licterm_maplic ON lic_pk = lic_fk AND licterm_fk = '$TermKey' AND lic_id = lic_pk ORDER BY lic_name;"); 
      $LicAvailable = $DB->Action("SELECT lic_pk AS id, lic_name AS text FROM agent_lic_raw WHERE lic_id = lic_pk AND lic_pk NOT IN (SELECT lic_fk FROM licterm_maplic) ORDER BY lic_name;");
      }
    else
      {
      $LicList = array();
      $LicAvailable = $DB->Action("SELECT lic_pk AS id, lic_name AS text FROM agent_lic_raw WHERE lic_id = lic_pk AND lic_pk NOT IN (SELECT lic_fk FROM licterm_maplic) ORDER BY lic_name;");
      }

    /* List all license terms */
    $V .= "<tr>";
    $V .= "<td>";
    $V .= "<select onFocus='UnselectForm(\"licavailable\");' multiple='multiple' id='liclist' name='liclist[]' size='10'>";
    for($i=0; !empty($LicList[$i]['text']); $i++)
      {
      $Text = trim($LicList[$i]['text']);
      $Id = trim($LicList[$i]['id']);
      $V .= "<option value='$Id'>$Text</option>\n";
      }
    $V .= "</select>";
    $V .= "</td>\n";

    /* center list of options */
    $V .= "<td>";
    $V .= "<center>\n";
    $Uri = "if (document.getElementById('licavailable').value) { window.open('";
    $Uri .= Traceback_uri();
    $Uri .= "?mod=view-license";
    $Uri .= "&format=flow";
    $Uri .= "&lic=";
    $Uri .= "' + document.getElementById('licavailable').value + '";
    $Uri .= "&licset=";
    $Uri .= "' + document.getElementById('licavailable').value";
    $Uri .= ",'License','width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes'); }";
    $Uri .= " else ";
    $Uri .= "if (document.getElementById('liclist').value) { window.open('";
    $Uri .= Traceback_uri();
    $Uri .= "?mod=view-license";
    $Uri .= "&format=flow";
    $Uri .= "&lic=";
    $Uri .= "' + document.getElementById('liclist').value + '";
    $Uri .= "&licset=";
    $Uri .= "' + document.getElementById('liclist').value";
    $Uri .= ",'License','width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes'); }";
    $V .= "<a href='#' onClick=\"$Uri\">View</a><hr/>\n";
    $V .= "<a href='#' onClick='moveOptions(document.formy.licavailable,document.formy.liclist);'>&larr;Add</a><P/>\n";
    $V .= "<a href='#' onClick='moveOptions(document.formy.liclist,document.formy.licavailable);'>Remove&rarr;</a>\n";
    $V .= "</center></td>\n";

    $V .= "<td>";
    $V .= "<select onFocus='UnselectForm(\"liclist\");' multiple='multiple' id='licavailable' name='licavailable' size='10'>";
    for($i=0; !empty($LicAvailable[$i]['text']); $i++)
      {
      $Text = trim($LicAvailable[$i]['text']);
      $Id = trim($LicAvailable[$i]['id']);
      $V .= "<option value='$Id'>$Text</option>\n";
      }
    $V .= "</select>";
    $V .= "</td></table>\n";
    $V .= "</tr>\n";

    /***********************************************************/
    /* Delete a keyword */
    $V .= "<tr>";
    $V .= "<td>Delete a keyword from <i>all</i> canonical groups.\n";
    $V .= "</td><td>";
    $V .= "Use this to remove typographical errors or completely unnecessary keywords or phrases.\n";
    $V .= "<br>";
    $V .= "<select name='deleteword'>\n";
    $V .= "<option value=''></option>\n";
    $TermList = $DB->Action("SELECT licterm_words_text AS text FROM licterm_words ORDER BY licterm_words_text;");
    for($i=0; !empty($TermList[$i]['text']); $i++)
      {
      $Text = strtolower($TermList[$i]['text']);
      $Text = preg_replace("/[^a-z0-9]/"," ",$Text);
      $Text = trim(preg_replace("/ +/"," ",$Text));
      $V .= "<option value='$Text'>Delete: $Text</option>\n";
      }
    $V .= "</select>\n";
    $V .= "</td>";
    $V .= "</tr>";

    $V .= "</table>\n";
    $V .= "<input type='submit' name='submit' value='Commit!'>\n";
    $V .= "</form>\n";
    return($V);
    } // LicTermForm()

  /***********************************************************
   LicTermDelete(): Delete a term record from the DB.
   ***********************************************************/
  function LicTermDelete	()
    {
    global $DB;
    $TermName = GetParm('name',PARM_TEXT);
    $TermName = str_replace("'","''",$TermName);
    $TermKey = GetParm('termkey',PARM_INTEGER);
    /* To delete: name and key number must match */
    $Results = $DB->Action("SELECT * FROM licterm WHERE licterm_pk = '$TermKey';");
    $TermKey = $Results[0]['licterm_pk'];
    if (empty($TermKey)) { return("Record not found.  Nothing to delete."); }
    $TermName = GetParm('name',PARM_TEXT);

    $DB->Action("DELETE FROM licterm_map WHERE licterm_fk = '$TermKey';");
    $DB->Action("DELETE FROM licterm WHERE licterm_pk = '$TermKey';");
    $DB->Action("VACUUM ANALYZE licterm_map;");
    $DB->Action("VACUUM ANALYZE licterm;");
    } // LicTermDelete()

  /***********************************************************
   LicTermInsert(): Insert a term record into the DB.
   ***********************************************************/
  function LicTermInsert	($TermKey='',$TermName='',$TermDesc='',$TermListWords=NULL)
    {
    global $DB;
    if (empty($TermKey)) { $TermKey = GetParm('termkey',PARM_INTEGER); }
    if ($TermKey <= 0) { $TermKey=NULL; }
    if (empty($TermName)) { $TermName = GetParm('name',PARM_TEXT); }
    if (empty($TermDesc)) { $TermDesc = GetParm('desc',PARM_TEXT); }
    /* Check if values look good */
    $rc = $this->LicTermWordDelete();
    if (empty($TermName))
	{
	if ($rc == "") { return; }
	return("Term name must be specified.");
	}

    /* Protect for the DB */
    $TermName = str_replace("'","''",$TermName);
    $TermDesc = str_replace("'","''",$TermDesc);

    if (!empty($TermKey) && ($TermKey >= 0))
      {
      $SQL = "SELECT licterm_pk FROM licterm WHERE licterm_pk = '$TermKey';";
      }
    else
      {
      $SQL = "SELECT * FROM licterm WHERE licterm_name = '$TermName';";
      }
    $Results = $DB->Action($SQL);
    $TermKey = $Results[0]['licterm_pk'];

    /* Do the insert (or update) */
    if (empty($TermKey))
      {
      $SQL = "INSERT INTO licterm (licterm_name,licterm_desc)
	VALUES ('$TermName','$TermDesc');";
      }
    else
      {
      $SQL = "UPDATE licterm SET licterm_name = '$TermName',
        licterm_desc = '$TermDesc'
        WHERE licterm_pk = '$TermKey';";
      }
    $DB->Action($SQL);

    /* Check if it inserted */
    $Results = $DB->Action("SELECT * FROM licterm WHERE licterm_name = '$TermName';");
    if (empty($Results[0]['licterm_pk']))
      {
      return("Bad SQL: $SQL");
      }
    $TermKey = $Results[0]['licterm_pk'];

    /* Now add in all the terms */
    $TermList = GetParm('termlist',PARM_RAW);
    $DB->Action("DELETE FROM licterm_map WHERE licterm_fk = '$TermKey';");
    for($i=0; !empty($TermList[$i]); $i++)
      {
      $Term = strtolower($TermList[$i]);
      $Term = preg_replace("/[^a-z0-9]/"," ",$Term);
      $Term = trim(preg_replace("/  */"," ",$Term));
      $SQL = "SELECT * FROM licterm_words WHERE licterm_words_text = '$Term';";
      $Results = $DB->Action($SQL);
      if (empty($Results[0]['licterm_words_pk']))
	{
	$DB->Action("INSERT INTO licterm_words (licterm_words_text) VALUES ('$Term');");
        $Results = $DB->Action($SQL);
	if (empty($Results[0]['licterm_words_pk']))
	  {
	  return("Unable to insert '$Term' into the database.");
	  }
	}
      $DB->Action("INSERT INTO licterm_map (licterm_words_fk,licterm_fk)
	VALUES (" . $Results[0]['licterm_words_pk'] . ",$TermKey);");
      }
    $DB->Action("VACUUM ANALYZE licterm_map;");

    /* Now add in all the licenses */
    $LicList = GetParm('liclist',PARM_RAW);
    $DB->Action("DELETE FROM licterm_maplic WHERE licterm_fk = '$TermKey';");
    for($i=0; !empty($LicList[$i]); $i++)
      {
      $Lic = intval($LicList[$i]);
      $SQL = "SELECT * FROM agent_lic_raw WHERE lic_pk = '$Lic' AND lic_pk = lic_id;";
      $Results = $DB->Action($SQL);
      if (!empty($Results[0]['lic_pk']))
	{
	$DB->Action("INSERT INTO licterm_maplic (lic_fk,licterm_fk)
	VALUES (" . $Results[0]['lic_pk'] . ",$TermKey);");
	}
      }
    $DB->Action("VACUUM ANALYZE licterm_maplic;");

    return;
    } // LicTermInsert()

  /***********************************************************
   LicTermWordDelete(): Delete a term word.
   ***********************************************************/
  function LicTermWordDelete	()
    {
    global $DB;
    $TermDel = GetParm('deleteword',PARM_TEXT);
    if (!empty($TermDel))
      {
      $Term = strtolower($TermDel);
      $Term = preg_replace("/[^a-z0-9]/"," ",$Term);
      $Term = trim(preg_replace("/  */"," ",$Term));
      $SQL = "SELECT * FROM licterm_words WHERE licterm_words_text = '$Term';";
      $Results = $DB->Action($SQL);
      if (!empty($Results[0]['licterm_words_pk']))
	{
	$DB->Action("DELETE FROM licterm_map WHERE licterm_words_fk = '" . $Results[0]['licterm_words_pk'] . "';");
	$DB->Action("DELETE FROM licterm_words WHERE licterm_words_pk = '" . $Results[0]['licterm_words_pk'] . "';");
	$DB->Action("VACUUM ANALYZE licterm_words;");
	}
      return;
      }
    else
      {
      return("No words deleted.");
      }
    } // LicTermWordDelete()

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
        $Submit = GetParm('submit',PARM_STRING);
        $Delete = GetParm('delete',PARM_INTEGER);
        if (!empty($Submit))
          {
          if ($Delete == 1) { $rc = $this->LicTermDelete(); }
          else { $rc = $this->LicTermInsert(); }
          if (empty($rc))
            {
            /* Need to refresh the screen */
            $V .= "<script language='javascript'>\n";
            $V .= "alert('License term information updated.')\n";
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
        $TermKey = GetParm('termkey',PARM_INTEGER);
        if ($TermKey <= 0) { $TermKey = NULL; }
	$V .= $this->LicTermJavascript($TermKey);
        $V .= $this->LicTermForm($TermKey);
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
$NewPlugin = new licterm_manage;
$NewPlugin->Initialize();
?>
