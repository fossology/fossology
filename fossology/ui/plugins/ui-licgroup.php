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

/************************************************
 Plugin for License Groups
 *************************************************/
class licgroup extends FO_Plugin
  {
  var $Name       = "licgroup";
  var $Title      = "License Groups";
  var $Version    = "1.0";
  var $Dependency = array("db","browse");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;

  /*--- Globals for this object ---*/
  var $LicInGroup=NULL; /* list of licenses in a group */
  var $GrpInGroup=NULL; /* list of groups in a group */
  var $SFbLG=-1;	/* is search file by licence group available? */
  var $SFbL=-1;		/* is search file by licence available? */

  /***********************************************************
   RegisterMenus(): Customize submenus.
   ***********************************************************/
  function RegisterMenus()
  {
    $Item = GetParm("item",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    $URI = $this->Name . Traceback_parm_keep(array("show","format","page","upload","item"));
    if (!empty($Item) && !empty($Upload))
      {
      if (GetParm("mod",PARM_TEXT) == $this->Name)
	{
	menu_insert("Browse::License Groups",1);
	menu_insert("Browse::[BREAK]",100);
	menu_insert("Browse::Clear",101,NULL,NULL,"<a title='Clear highlighting' href='javascript:LicColor(\"\",\"\",\"\",\"\");'>Clear</a>");
	}
      else
	{
	menu_insert("Browse::License Groups",1,$URI,"View license group histogram");
	}
      }
  } // RegisterMenus()

  /***********************************************************
   Install(): Create and configure database tables
   ***********************************************************/
   function Install()
   {
     global $DB;
     if (empty($DB)) { return(1); } /* No DB */

    /* Create TABLE licgroup if it does not exist */
    $SQL = "SELECT table_name AS table
	FROM information_schema.tables
	WHERE table_type = 'BASE TABLE'
	AND table_schema = 'public'
	AND table_name = 'licgroup';";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['table']))
      {
      $SQL1 = "CREATE SEQUENCE licgroup_licgroup_pk_seq START 1;";
      $DB->Action($SQL1);
      $SQL1 = "CREATE TABLE licgroup (
	licgroup_pk integer PRIMARY KEY DEFAULT nextval('licgroup_licgroup_pk_seq'),
	licgroup_name text UNIQUE,
	licgroup_desc text,
	licgroup_color text
	);
	COMMENT ON COLUMN licgroup.licgroup_name IS 'Name of License Group';
	COMMENT ON COLUMN licgroup.licgroup_desc IS 'Description of License Group';
	COMMENT ON COLUMN licgroup.licgroup_color IS 'Color to associate with License Group (#RRGGBB)';
	";
      $DB->Action($SQL1);
      $Results = $DB->Action($SQL);
      if (empty($Results[0]['table']))
	{
	printf("ERROR: Failed to create table: licgroup\n");
	return(1);
	}
      } /* create TABLE licgroup */

    /* Create TABLE licgroup_lics if it does not exist */
    $SQL = "SELECT table_name AS table
	FROM information_schema.tables
	WHERE table_type = 'BASE TABLE'
	AND table_schema = 'public'
	AND table_name = 'licgroup_lics';";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['table']))
      {
      $SQL1 = "CREATE SEQUENCE licgroup_lics_licgroup_lics_pk_seq START 1;";
      $DB->Action($SQL1);
      $SQL1 = "CREATE TABLE licgroup_lics (
	licgroup_lics_pk integer PRIMARY KEY DEFAULT nextval('licgroup_lics_licgroup_lics_pk_seq'),
	licgroup_fk      integer,
	lic_fk   integer,
	CONSTRAINT only_one UNIQUE (licgroup_lics_pk, licgroup_fk),
	CONSTRAINT licgroup_exist FOREIGN KEY(licgroup_fk) REFERENCES licgroup(licgroup_pk) ON UPDATE RESTRICT ON DELETE RESTRICT
	);
	COMMENT ON COLUMN licgroup_lics.licgroup_fk IS 'Parent License Group';
	COMMENT ON COLUMN licgroup_lics.lic_fk IS 'License in Group';
	";
// Commented out because 'there is no unique constraint matching given keys for referenced table "agent_lic_raw"'  -- Leave it for Bob to resolve. :-)
//	CONSTRAINT lic_exist FOREIGN KEY(lic_fk) REFERENCES agent_lic_raw(lic_pk) ON UPDATE RESTRICT ON DELETE RESTRICT
      $DB->Action($SQL1);
      $Results = $DB->Action($SQL);
      if (empty($Results[0]['table']))
	{
	printf("ERROR: Failed to create table: licgroup_lics\n");
	return(1);
	}
      } /* create TABLE licgroup_lics */

    /* Check if TABLE licgroup_grps exists */
    $SQL = "SELECT table_name AS table
	FROM information_schema.tables
	WHERE table_type = 'BASE TABLE'
	AND table_schema = 'public'
	AND table_name = 'licgroup_grps';";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['table']))
      {
      $SQL1 = "CREATE SEQUENCE licgroup_grps_licgroup_grps_pk_seq START 1;";
      $DB->Action($SQL1);
      $SQL1 = "CREATE TABLE licgroup_grps (
	licgroup_grps_pk integer PRIMARY KEY DEFAULT nextval('licgroup_grps_licgroup_grps_pk_seq'),
	licgroup_fk      integer,
	licgroup_memberfk integer,
	CONSTRAINT only_one_grp UNIQUE (licgroup_fk, licgroup_memberfk),
	CONSTRAINT licgroup_exist FOREIGN KEY(licgroup_fk) REFERENCES licgroup(licgroup_pk) ON UPDATE RESTRICT ON DELETE RESTRICT,
	CONSTRAINT licgroupmember_exist FOREIGN KEY(licgroup_memberfk) REFERENCES licgroup(licgroup_pk) ON UPDATE RESTRICT ON DELETE RESTRICT
	);
	COMMENT ON COLUMN licgroup_grps.licgroup_fk IS 'Key of parent license group';
	COMMENT ON COLUMN licgroup_grps.licgroup_memberfk IS 'Key of license group that belongs to licgroup_fk';
	";
      $DB->Action($SQL1);
      $Results = $DB->Action($SQL);
      if (empty($Results[0]['table']))
	{
	printf("ERROR: Failed to create table: licgroup_grps\n");
	return(1);
	}
      } /* create TABLE licgroup_grps */
   return(0);
   } // Install()

  /***********************************************************
   GroupColorMerge(): If the color of a group is white, then
   make the color a merger of all sub-groups.
   (If the color is not white, then don't change it.)
   THIS IS RECURSIVE.
   ***********************************************************/
  function GroupColorMerge	($Group=NULL)
    {
    if (empty($Group))
      {
      foreach($this->GrpInGroup as $G => $g)
        {
        if ($g['head'] == 1) { $this->GroupColorMerge($G); }
        }
      return;
      }
    if (empty($Group)) { return; }

    /* Recurse to bottom */
    foreach($this->GrpInGroup[$Group] as $G => $g)
      {
      if (empty($G)) { continue; }
      if (($g > 1) && (substr($G,0,1) == 'g'))
	{
	$this->GroupColorMerge($G);
	}
      }
    /* Now color them based on sub-colors */
    if ($this->GrpInGroup[$Group]['color'] == '#ffffff')
      {
      $Count = 0; /* Mostly default color */
      $cR = 255*$Count; $cG = 255*$Count; $cB = 255*$Count;
      foreach($this->GrpInGroup[$Group] as $G => $g)
        {
	if ($this->GrpInGroup[$G]['count'] <= 0) { continue; }
	if ($this->GrpInGroup[$G]['color'] == '#ffffff') { continue; }
        if (($g > 1) && (substr($G,0,1) == 'g'))
	  {
	  $Count++;
	  $cR += hexdec(substr($this->GrpInGroup[$G]['color'],1,2));
	  $cG += hexdec(substr($this->GrpInGroup[$G]['color'],3,2));
	  $cB += hexdec(substr($this->GrpInGroup[$G]['color'],5,2));
	  }
	}
      if ($Count > 0)
        {
        $cR = $cR / $Count;
        $cG = $cG / $Count;
        $cB = $cB / $Count;
        if ($cR < 16) { $cR = '0' . dechex($cR); } else { $cR = dechex($cR); }
        if ($cG < 16) { $cG = '0' . dechex($cG); } else { $cG = dechex($cG); }
        if ($cB < 16) { $cB = '0' . dechex($cB); } else { $cB = dechex($cB); }
        $this->GrpInGroup[$Group]['color'] = '#' . $cR . $cG . $cB;
	}
      }
    } // GroupColorMerge()

  /***********************************************************
   CmpGroupTables(): Sort function.
   ***********************************************************/
  function CmpGroupTables	($a,$b)
    {
    $Aname = $this->GrpInGroup[$a]['name'];
    $Bname = $this->GrpInGroup[$b]['name'];
    if (empty($Aname)) { $Aname = $a; }
    if (empty($Bname)) { $Bname = $b; }
    return(strcmp($Aname,$Bname));
    } // CmpGroupTables()

  /***********************************************************
   MakeGroupTables(): License groups can contain other license groups.
   This function populates two quick-lookup tables so it can
   be quickly determined if a license is in a group, or if
   a group is in a group.
   The contents of GrpInGroup:
     0 = loop
     1 = inherited relation
     2 = direct relation
   ***********************************************************/
  function MakeGroupTables	()
    {
    global $DB;

    /* Load the initial tables (these are explicit groups in groups) */
    $GrpInGroup = array();
    $SQL = "SELECT * FROM licgroup_grps;";
    $Results = $DB->Action($SQL);
    for($i=0; !empty($Results[$i]['licgroup_grps_pk']); $i++)
      {
      $R = &$Results[$i];
      /* Insert a 'g' for 'group', because otherwise merge treats the
	 keys as numbers and not strings.  (Numbers get renumbered.) */
      $GrpInGroup['g'.$R['licgroup_fk']]['g'.$R['licgroup_memberfk']] = 2;
      }

    /* Load the licenses per group */
    $Results = $DB->Action("SELECT * FROM licgroup_lics ORDER BY lic_fk;");
    $LicInGroup = array();
    for($i=0; !empty($Results[$i]['licgroup_lics_pk']); $i++)
      {
      $R = &$Results[$i];
      /* 'g' is for group, so 'l' is for license */
      $LicInGroup['g'.$R['licgroup_fk']]['l'.$R['lic_fk']] = 2;
      $GrpInGroup['g'.$R['licgroup_fk']]['l'.$R['lic_fk']] = 2;
      }

    /* Fill out group-in-groups implicit inheritance */
    foreach($GrpInGroup as $A => $a)
      {
      $Aa = &$GrpInGroup[$A];
      if (!is_array($Aa)) { $Aa = array(); }
      /* Find every element that depends on this one and merge lists */
      foreach($GrpInGroup as $B => $b)
	{
	if ($A == $B) { continue; }
	if (!is_array($Bb)) { $Bb = array(); }
	$Bb = &$GrpInGroup[$B];
	if (!empty($Bb[$A]))
	  {
	  /* Combine them manually, track inheritance */
	  foreach($Aa as $Key => $Val)
	    {
	    if (empty($Bb[$Key])) { $Bb[$Key] = 1; }
	    }
	  }
	} /* foreach $B */
      } /* foreach $A */

    /* Add the group name */
    $SQL = "SELECT * FROM licgroup;";
    $Results = $DB->Action($SQL);
    for($i=0; !empty($Results[$i]['licgroup_pk']); $i++)
      {
      $GrpInGroup['g'.$Results[$i]['licgroup_pk']]['name'] = $Results[$i]['licgroup_name'];
      $GrpInGroup['g'.$Results[$i]['licgroup_pk']]['color'] = $Results[$i]['licgroup_color'];
      $GrpInGroup['g'.$Results[$i]['licgroup_pk']]['id'] = $Results[$i]['licgroup_pk'];
      $GrpInGroup['g'.$Results[$i]['licgroup_pk']]['desc'] = $Results[$i]['licgroup_desc'];
      }

    /* Add in the phrase group */
    $GrpInGroup['g0']['name'] = 'Phrase';
    $GrpInGroup['g0']['id'] = 'phrase';
    $GrpInGroup['g0']['color'] = '#ffffff';
    $GrpInGroup['g0']['l1'] = '1';
    $LicInGroup['g0']['l1'] = 'Phrase';

    /* Look for self-loops: return the tree without loops */
    $LoopList=array();
    foreach($GrpInGroup as $A => $a)
      {
      $GrpInGroup[$A]['head']=1; /* assume it starts a chain */
      $GrpInGroup[$A]['count']=0; /* assume it no files loaded */
      }
    foreach($GrpInGroup as $A => $a)
      {
      if (substr($A,0,1) != 'g') { continue; } /* only look at groups */
      if (isset($GrpInGroup[$A][$A]))
	{
	$LoopList[$A]=1;
	unset($GrpInGroup[$A][$A]);
	}
      $Tail=1;
      foreach($GrpInGroup[$A] as $B => $b)
	{
	if (substr($B,0,1) != 'g') { continue; } /* only look at groups */
	if (isset($LoopList[$B]))
	  {
	  $GrpInGroup[$A][$B] = 0; /* mark with a '0' if it is a loop */
	  }
	else
	  {
	  unset($GrpInGroup[$B]['head']); /* not start of a chain */
	  $Tail=0; /* not a tail */
	  }
	}
      /* If this group is the end of a chain, then mark it. */
      if ($Tail) { $GrpInGroup[$A]['tail']=1; }
      }

    /* Save results */
    $this->LicInGroup = $LicInGroup;
    $this->GrpInGroup = $GrpInGroup;

    /* Sort results */
    uksort($this->GrpInGroup,array($this,"CmpGroupTables"));
    foreach($this->GrpInGroup as $G => $g)
      {
      uksort($this->GrpInGroup[$G],array($this,"CmpGroupTables"));
      }

    if (0) /* Debug code */
      {
      print "<pre>";
      // print "LicGroups:\n"; print_r($this->LicInGroup);
      print "GrpGroups:\n"; print_r($this->GrpInGroup);
      print "</pre>";
      print "<hr>";
      }
    } // MakeGroupTables()

  /*******************************************************************/
  /*******************************************************************/
  /*******************************************************************/

  /***********************************************************
   LightenColor(): Given an "#rrggbb" color, make it ligher.
   ***********************************************************/
  function LightenColor	($Color)
    {
    $cR = (hexdec(substr($Color,1,2)) * 3 + 255) / 4;
    $cG = (hexdec(substr($Color,3,2)) * 3 + 255) / 4;
    $cB = (hexdec(substr($Color,5,2)) * 3 + 255) / 4;
    if ($cR < 16) { $cR = '0' . dechex($cR); } else { $cR = dechex($cR); }
    if ($cG < 16) { $cG = '0' . dechex($cG); } else { $cG = dechex($cG); }
    if ($cB < 16) { $cB = '0' . dechex($cB); } else { $cB = dechex($cB); }
    return('#' . $cR . $cG . $cB);
    } // LightenColor()

  /***********************************************************
   ShowHistTable(): Given a loaded list of groups and an array
   that lists which files are in which groups, display the table.
   THIS IS RECURSIVE!
   ***********************************************************/
  var $ShowHistRow=0;
  function ShowHistTable	(&$Group, $Depth=0, &$Item)
    {
    if ($this->GrpInGroup[$Group]['count'] <= 0) { return; }
    $V .= "<table border='1' width='100%' style='border-top:none;'>";
    $V .= "<tr>";
    $V .= "<td align='right' width='15%'>";
    $V .= number_format($this->GrpInGroup[$Group]['count'],0,"",",");
    $V .= "</td>";

    if ($this->GrpInGroup[$Group]['id'] == 'phrase')
      {
      if ($this->SFbL >= 0)
	{
	$V .= "<td width='10%' align='center'><a href='";
	$V .= Traceback_uri();
	$V .= "?mod=search_file_by_license&item=$Item&lic=Phrase'>Show</a></td>";
	}
      }
    else if ($this->SFbLG >= 0)
      {
      $V .= "<td width='10%' align='center' bgcolor='" . $this->GrpInGroup[$Group]['color'] . "'>";
      $V .= "<a ";
      if ($this->GrpInGroup[$Group]['color'] == '#ff0000')
        {
	$V .= "onMouseOver='this.style.color=\"#ffffff\";' onMouseOut='this.style.color=\"#0000ff\";' ";
	}
      if ($this->GrpInGroup[$Group]['color'] == '#0000ff')
        {
	$V .= "style='color:#ffffff' ";
	$V .= "onMouseOut='this.style.color=\"#ffffff\";' onMouseOver='this.style.color=\"#ff0000\";' ";
	}
      $V .= "href='";
      $V .= Traceback_uri();
      $V .= "?mod=search_file_by_licgroup&item=$Item&licgroup=" . $this->GrpInGroup[$Group]['id'] . "'>";
      $V .= "<font style='text-shadow: black 0px 0px 5px;'>";
      $V .= "Show";
      $V .= "</a>";
      $V .= "</td>";
      }
    $V .= "</td>";

    /* Create the "+" for expanding the list */
    $V .= "<td width='1%' style='border-right:none;'>";
    /* Check if subgroups contain licenses */
    $Count=0;
    foreach($this->GrpInGroup[$Group] as $G => $g)
	{
	/* only do direct groups */
	if (($g > 1) && (substr($G,0,1) == 'g'))
	  {
	  $Count += $this->GrpInGroup[$G]['count'];
	  }
	}
    if (($Count > 0) && empty($this->GrpInGroup[$Group]['tail']))
      {
      $V .= "<a href='javascript:ShowHide(\"DivGrp-" . $this->ShowHistRow . "\")'>+</a>";
      }
    else
      {
      $V .= "<font color='white'>+</font>";
      }

    /* Show the license name */
    $V .= "</td><td id='LicGroup r" . $this->ShowHistRow . " $Group";
    foreach($this->GrpInGroup[$Group] as $G => $g)
	{
	if (($g > 1) && (substr($G,0,1) == 'g'))
	  {
	  $V .= " $G";
	  }
	}
    $V .= " ' style='border-left:none;'>";
    if ($Depth > 0)
      {
      $V .= "<font color='#999999'>";
      for($i=0; $i < $Depth; $i++) { $V .= "&#8230;"; }
      $V .= "</font>";
      }
    $V .= "<a href=\"javascript:LicColor('LicGroup','r" . $this->ShowHistRow . "','LicItem','$Group','yellow')\"";
    $V .= " title='" . htmlentities($this->GrpInGroup[$Group]['desc'],ENT_QUOTES) . "'>";
    $V .= htmlentities($this->GrpInGroup[$Group]['name']);
    $V .= "</a>";
    $V .= "</td></tr></table>";

    $this->ShowHistRow++;
    if (empty($this->GrpInGroup[$Group]['tail']))
      {
      $V .= "<div id='DivGrp-" . ($this->ShowHistRow-1) . "' style='display:none;'>";
      foreach($this->GrpInGroup[$Group] as $G => $g)
	{
	/* only process direct relations */
	if (($g > 1) && (substr($G,0,1) == 'g'))
	  {
	  $V .= $this->ShowHistTable($G,$Depth+1,$Item);
	  }
	}
      $V .= "</div>";
      }
    if ($Depth == 0)
      {
      $V .= "<div style='height:0.5em;'></div>";
      }
    return($V);
    } // ShowHistTable()

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
    $ModLicView = &$Plugins[plugin_find_id("view-license")];
    $MapLic2GID = array(); /* every license should have an ID number */
    $MapNext=0;

    /****************************************/
    /* Load licenses */
    $LicPk2GID=array();  // map lic_pk to the group id: lic_id
    $LicGID2Name=array(); // map lic_id to name.
    $Results = $DB->Action("SELECT lic_pk,lic_id,lic_name FROM agent_lic_raw ORDER BY lic_name;");
    foreach($Results as $Key => $R)
      {
      if (empty($R['lic_name'])) { continue; }
      $Name = basename($R['lic_name']);
      $GID = $R['lic_id'];
      $LicGID2Name[$GID] = $Name;
      $LicPk2GID[$R['lic_pk']] = $GID;
      }
    if (empty($LicGID2Name[1])) { $LicGID2Name[1] = 'Phrase'; }
    if (empty($LicPk2GID[1])) { $LicPk2GID[1] = 1; }

    /****************************************/
    /* Get the items under this UploadtreePk */
    $Children = DirGetList($Upload,$Item);
    $ChildCount=0; /* unique ID for file listing item */
    $VF .= "<table border=0>";
    foreach($Children as $C)
      {
      /* Store the item information */
      $IsDir = Isdir($C['ufile_mode']);
      $IsContainer = Iscontainer($C['ufile_mode']);

      /* Load licenses for the item */
      $Lics = array();
      if ($IsContainer) { LicenseGetAll($C['uploadtree_pk'],$Lics,1); }
      else { LicenseGet($C['pfile_fk'],$Lics,1); }

      /* Determine the hyperlinks */
      if (!empty($C['pfile_fk']) && !empty($ModLicView))
	{
	$LinkUri = "$Uri&item=$Item";
	$LinkUri = preg_replace("/mod=licgroup/","mod=view-license",$LinkUri);
	$LinkUri .= "&modback=" . $this->Name;
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

      /* Ensure every license name has an ID number */
      foreach($Lics as $Key => $Val)
        {
	if (empty($MapLic2GID[$Key])) { $MapLic2GID[$Key] = $MapNext++; }
	}

      /* Save the license results (also converts values to GID) */
      $GrpList=array();
      $LicCount=0;
      foreach($Lics as $Key => $Val)
	{
	if (!is_int($Key)) { continue; }
	if (empty($Val['lic_pk'])) { $GID = $LicPk2GID[$Val['lic_id']]; }
	else { $GID = $LicPk2GID[$Val['lic_pk']]; }
	/* Find every license group that includes the license */
	$FoundGroup=0;
	foreach($this->GrpInGroup as $G => $g)
	  {
	  if (!empty($this->GrpInGroup[$G]['l'.$GID]))
	    {
	    $this->GrpInGroup[$G]['count']++;
	    $GrpList[$G]=1;
	    $FoundGroup=1;
	    }
	  }
	if (!$FoundGroup)
	    {
	    $this->GrpInGroup['Gnone']['count']++;
	    $GrpList['Gnone']=1;
	    }
        $LicCount++;
	}

      /* Populate the output ($VF) */
      $VF .= '<tr>';
      $VF .= "<td id='LicItem i$ChildCount";
      foreach($GrpList as $G => $g) { $VF .= " $G"; }
      $VF .= " ' align='left'>";
      if ($LicCount > 0)
	{
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
	$VF .= "</td><td>[" . number_format($LicCount,0,"",",") . "&nbsp;";
	$VF .= "<a href=\"javascript:LicColor('LicItem','i$ChildCount','LicGroup','";
	foreach($GrpList as $G => $g) { $VF .= " $G"; }
	$VF .= "','lightgreen');\">";
	$VF .= "license" . ($LicCount == 1 ? "" : "s");
	$VF .= "</a>";
	$VF .= "]</td>";
	}
      else
	{
	if ($IsContainer) { $VF .= "<b>"; };
	$VF .= $C['ufile_name'];
	if ($IsDir) { $VF .= "/"; };
	if ($IsContainer) { $VF .= "<b>"; };
	$VF .= "</td><td></td>";
	}
      $VF .= "</tr>\n";

      $ChildCount++;
      }
    $VF .= "</table>\n";

    /****************************************/
    /* List the licenses */
    $this->GroupColorMerge(); /* muddy the colors! */
    $VH .= FolderListScript();
    $VH .= "<table border='1' width='100%'>";
    $VH .= "<tr><th width='15%'>Count</th>";
    if ($this->SFbLG >= 0) { $VH .= "<th width='10%'>Files</th>"; }
    $VH .= "<th colspan='2'>License Groups</th></tr>";
    $VH .= "</table>";
    foreach($this->GrpInGroup as $G => $g)
      {
      if ($g['head'] == 1)
	{
	$VH .= $this->ShowHistTable($G,0,$Item);
	}
      }
    /* Default: License is not in any group */
    if ($this->GrpInGroup['Gnone']['count'] > 0)
      {
      $VH .= "<table border='1' width='100%'>";
      $VH .= "<tr><td width='15%' align='right'>";
      $VH .= number_format($this->GrpInGroup['Gnone']['count'],0,"",",");
      $VH .= "</td>";
      $VH .= "<td width='10%' align='center'>";
      $VH .= "<a href='" . Traceback_uri();
      $VH .= "?mod=search_file_by_licgroup&item=$Item&licgroup=-1'>";
      $VH .= "<font style='text-shadow: black 0px 0px 5px;'>";
      $VH .= "Show";
      $VH .= "</a>";
      $VH .= "</td>";
      $VH .= "<td width='1%' style='border-right:none;'><font color='white'>+</font></td>";
      $VH .= "<td id='LicGroup Gnone ' style='border-left:none;'>";
      $VH .= "<a href=\"javascript:LicColor('LicGroup','Gnone','LicItem','Gnone','yellow')\">";
      $VH .= "License not part of any group";
      $VH .= "</a>";
      $VH .= "</td></tr></table>\n";
      }

    /****************************************/
    /* Licenses use Javascript to highlight */
    $VJ = ""; // return values for the javascript
    $VJ .= "<script language='javascript'>\n";
    $VJ .= "<!--\n";
    $VJ .= "function LicColor(Self,SelfList,Group,GroupList,color)\n";
    $VJ .= "{\n";
    $VJ .= "var UpdateList = new Array();\n";

    /* Clear all */
    $VJ .= "  {\n";
    $VJ .= "  var Contains = '//td[contains(@id,\"LicItem\") or contains(@id,\"LicGroup\")]';\n";
    $VJ .= "  var tds = document.evaluate(Contains,document,null,XPathResult.ANY_TYPE,null);\n";
    $VJ .= "  var Td = tds.iterateNext();\n";
    $VJ .= "  while(Td)\n";
    $VJ .= "    {\n";
    $VJ .= "    UpdateList[UpdateList.length] = Td.getAttribute('id');\n";
    $VJ .= "    Td = tds.iterateNext();\n";
    $VJ .= "    }\n";
    $VJ .= "  }\n";

    /* Clear everything in the update list */
    /* The select and update must be done in separate loops, because setting
       anything will invalidate the tds array. */
    $VJ .= "for(var i in UpdateList)\n";
    $VJ .= "  {\n";
    $VJ .= "  document.getElementById(UpdateList[i]).style.backgroundColor='white';\n";
    $VJ .= "  }\n";
    $VJ .= "UpdateList = new Array();\n";

    /* Color self */
    $VJ .= "if (Self!='')\n";
    $VJ .= "  {\n";
    $VJ .= "  SelfList.replace(/^\s+|\s+\$/g,'');\n";
    $VJ .= "  var List = SelfList.split(' ');\n";
    $VJ .= "  var Contains = '//td[contains(@id,\"' + Self + '\")]';\n";
    $VJ .= "  var tds = document.evaluate(Contains,document,null,XPathResult.ANY_TYPE,null);\n";
    $VJ .= "  var Td = tds.iterateNext();\n";
    $VJ .= "  while(Td)\n";
    $VJ .= "    {\n";
    $VJ .= "    for(var i in List)\n";
    $VJ .= "      {\n";
    $VJ .= "      var Attr = Td.getAttribute('id');\n";
    $VJ .= "      if (Attr.match(' ' + List[i] + ' '))\n";
    $VJ .= "        {\n";
    $VJ .= "        UpdateList[UpdateList.length] = Attr;\n";
    $VJ .= "        }\n";
    $VJ .= "      }\n";
    $VJ .= "    Td = tds.iterateNext();\n";
    $VJ .= "    }\n";
    $VJ .= "  }\n";

    /* Color group */
    $VJ .= "if (Group!='')\n";
    $VJ .= "  {\n";
    $VJ .= "  GroupList.replace(/^\s+|\s+\$/g,'');\n";
    $VJ .= "  var List = GroupList.split(' ');\n";
    $VJ .= "  var Contains = '//td[contains(@id,\"' + Group + '\")]';\n";
    $VJ .= "  var tds = document.evaluate(Contains,document,null,XPathResult.ANY_TYPE,null);\n";
    $VJ .= "  var Td = tds.iterateNext();\n";
    $VJ .= "  while(Td)\n";
    $VJ .= "    {\n";
    $VJ .= "    for(var i in List)\n";
    $VJ .= "      {\n";
    $VJ .= "      var Attr = Td.getAttribute('id');\n";
    $VJ .= "      if (Attr.match(' ' + List[i] + ' '))\n";
    $VJ .= "        {\n";
    $VJ .= "        UpdateList[UpdateList.length] = Attr;\n";
    $VJ .= "        }\n";
    $VJ .= "      }\n";
    $VJ .= "    Td = tds.iterateNext();\n";
    $VJ .= "    }\n";
    $VJ .= "  }\n";

    /* Clear everything in the update list */
    $VJ .= "for(var i in UpdateList)\n";
    $VJ .= "  {\n";
    $VJ .= "  document.getElementById(UpdateList[i]).style.backgroundColor=color;\n";
    $VJ .= "  }\n";

    /* End of Javascript function: LicColor */
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
    $this->MakeGroupTables();
    $Folder = GetParm("folder",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);
    $this->SFbLG = plugin_find_id("search_file_by_licgroup");
    $this->SFbL = plugin_find_id("search_file_by_license");

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
    print($V);
    return;
    }

  };
$NewPlugin = new licgroup;
$NewPlugin->Initialize();
?>
