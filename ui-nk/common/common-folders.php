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
 Design note:
 Folders could be stored in a menu listing (using menu_insert).
 However, since menu_insert() runs a usort() during each insert,
 this can be really slow.  For speed, folders are handled separately.
 *************************************************/

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

/************************************************************
 FolderGetTop(): Find the top-of-tree folder_pk for the current user.
 TBD: "username" will be added in the future and it may change
 how this function works.
 ************************************************************/
function FolderGetTop()
{
  global $Plugins;
  global $DB;

  /* Get the list of folders */
  if (!empty($_SESSION['Folder'])) { return($_SESSION['Folder']); }

  if (empty($DB)) { return; }
  $Results = $DB->Action("SELECT root_folder_fk FROM users ORDER BY user_pk ASC LIMIT 1;");
  $Row = $Results[0];
  return($Row['root_folder_fk']);
} // FolderGetTop()

/***********************************************************
 FolderListOption(): Create the tree, using OPTION tags.
 The caller must already have created the FORM and SELECT tags.
 It returns the full HTML.
 This is recursive!
 NOTE: If there is a recursive loop in the folder table, then
 this will loop INFINITELY.
 ***********************************************************/
function FolderListOption($ParentFolder,$Depth, $IncludeTop=1)
  {
  global $Plugins;
  if ($ParentFolder == "-1") { $ParentFolder = FolderGetTop(); }
  if (empty($ParentFolder)) { return; }
  global $DB;
  if (empty($DB)) { return; }
  $V="";

  if (($Depth != 0) || $IncludeTop)
    {
    $V .= "<option value='$ParentFolder'>";
    if ($Depth != 0) { $V .= "&nbsp;&nbsp;"; }
    for($i=1; $i < $Depth; $i++) { $V .= "&nbsp;&nbsp;"; }

    /* Load this folder's name */
    $Results = $DB->Action("SELECT folder_name FROM folder WHERE folder_pk=$ParentFolder LIMIT 1;");
    $Name = trim($Results[0]['folder_name']);
    if ($Name == "") { $Name = "[default]"; }

    /* Load any subfolders */
    /* Now create the HTML */
    $V .= htmlentities($Name);
    $V .= "</option>\n";
    }
  $Results = $DB->Action("SELECT folder_pk FROM leftnav WHERE parent=$ParentFolder AND folder_pk IS NOT NULL ORDER BY name;");
  if (isset($Results[0]['folder_pk']))
    {
    $Hide="";
    if ($Depth > 0) { $Hide = "style='display:none;'"; }
    foreach($Results as $R)
	{
	$V .= FolderListOption($R['folder_pk'],$Depth+1);
	}
    }
  return($V);
} // FolderListOption()

/***********************************************************
 FolderListScript(): Create the javascript for FolderListDiv().
 ***********************************************************/
function FolderListScript()
{
  $V = "";
  $V .= "<script language='javascript'>\n";
  $V .= "<!--\n";
  $V .= "function ShowHide(name)\n";
  $V .= "  {\n";
  $V .= "  if (name.length < 1) { return; }\n";
  $V .= "  var Element, State;\n";
  $V .= "  if (document.getElementById) // standard\n";
  $V .= "    { Element = document.getElementById(name); }\n";
  $V .= "  else if (document.all) // IE 4, 5, beta 6\n";
  $V .= "    { Element = document.all[name]; }\n";
  $V .= "  else // if (document.layers) // Netscape 4 and older\n";
  $V .= "    { Element = document.layers[name]; }\n";
  $V .= "  State = Element.style;\n";
  $V .= "  if (State.display == 'none') { State.display='block'; }\n";
  $V .= "  else { State.display='none'; }\n";
  $V .= "  }\n";
  $V .= "function Expand()\n";
  $V .= "  {\n";
  $V .= "  var E = document.getElementsByTagName('div');\n";
  $V .= "  for(var i = 0; i < E.length; i++)\n";
  $V .= "    {\n";
  $V .= "    if (E[i].id.substr(0,8) == 'TreeDiv-')\n";
  $V .= "      {\n";
  $V .= "      var Element, State;\n";
  $V .= "      if (document.getElementById) // standard\n";
  $V .= "        { Element = document.getElementById(E[i].id); }\n";
  $V .= "      else if (document.all) // IE 4, 5, beta 6\n";
  $V .= "        { Element = document.all[E[i].id]; }\n";
  $V .= "      else // if (document.layers) // Netscape 4 and older\n";
  $V .= "        { Element = document.layers[E[i].id]; }\n";
  $V .= "      State = Element.style;\n";
  $V .= "      State.display='block';\n";
  $V .= "      }\n";
  $V .= "    }\n";
  $V .= "  }\n";
  $V .= "function Collapse()\n";
  $V .= "  {\n";
  $V .= "  var E = document.getElementsByTagName('div');\n";
  $V .= "  var First=1;\n";
  $V .= "  for(var i = 0; i < E.length; i++)\n";
  $V .= "    {\n"; 
  $V .= "    if (E[i].id.substr(0,8) == 'TreeDiv-')\n";
  $V .= "      {\n";
  $V .= "      var Element, State;\n";
  $V .= "      if (document.getElementById) // standard\n";
  $V .= "        { Element = document.getElementById(E[i].id); }\n";
  $V .= "      else if (document.all) // IE 4, 5, beta 6\n";
  $V .= "        { Element = document.all[E[i].id]; }\n";
  $V .= "      else // if (document.layers) // Netscape 4 and older\n";
  $V .= "        { Element = document.layers[E[i].id]; }\n";
  $V .= "      State = Element.style;\n";
  $V .= "      if (First) { State.display='block'; First=0; } \n";
  $V .= "      else { State.display='none'; } \n";
  $V .= "      }\n";
  $V .= "    }\n";
  $V .= "  }\n";
  $V .= "-->\n";
  $V .= "</script>\n";
  return($V);
} // FolderListScript()

/***********************************************************
 FolderGetName(): Given a folder_pk, return the full path
 to this folder.
 This is recursive!
 NOTE: If there is a recursive loop in the folder table, then
 this will loop INFINITELY.
 ***********************************************************/
function FolderGetName($FolderPk,$Top=-1)
{
  global $DB;
  if ($Top == -1) { $Top = FolderGetTop(); }
  $Results = $DB->Action("SELECT folder_name,parent_fk FROM folder
	LEFT JOIN foldercontents ON foldercontents_mode = 1
	AND child_id = '$FolderPk'
	WHERE folder_pk = '$FolderPk'
	LIMIT 1;");
  $Parent = $Results[0]['parent_fk'];
  $Name = $Results[0]['folder_name'];
  if (!empty($Parent) && ($FolderPk != $Top))
      {
      $Name = FolderGetName($Parent,$Top) . "/" . $Name;
      }
  return($Name);
} // FolderGetName()

/***********************************************************
 FolderListDiv(): Create the tree, using DIVs.
 It returns the full HTML.
 This is recursive!
 NOTE: If there is a recursive loop in the folder table, then
 this will loop INFINITELY.
 ***********************************************************/
function FolderListDiv($ParentFolder,$Depth,$Highlight=0,$ShowParent=0)
  {
  global $Plugins;
  global $DB;
  if (empty($DB)) { return; }
  if (empty($ParentFolder)) { return; }
  if ($ParentFolder == "-1") { return(FolderListDiv(FolderGetTop(),0)); }
  $Browse = &$Plugins[plugin_find_id("browse")];
  $Uri = Traceback_uri();
  $V="";

  if ($Depth != 0)
    {
    $V .= "<font class='treehide1' color='white'>";
    $V .= "+&nbsp;";
    $V .= "</font>";
    if ($Depth > 1)
      {
      for($i=1; $i < $Depth; $i++)
	{
	$V .= "<font class='treehide'>";
	$V .= "+&nbsp;";
	$V .= "</font>";
	}
      }
    }

  /* Load this folder's parent */
  if ($ShowParent && ($ParentFolder != FolderGetTop()))
    {
    $Results = $DB->Action("SELECT parent_fk FROM foldercontents WHERE foldercontents_mode = 1 AND child_id = '$ParentFolder' LIMIT 1;");
    $P = $Results[0]['parent_fk'];
    if (!empty($P) && ($P != 0)) { $ParentFolder=$P; }
    }

  /* Load this folder's name */
  $Results = $DB->Action("SELECT folder_name,folder_desc FROM folder WHERE folder_pk=$ParentFolder LIMIT 1;");
  $Name = trim($Results[0]['folder_name']);
  $Desc = trim($Results[0]['folder_desc']);
  if ($Name == "") { $Name = "[default]"; }
  $Desc = str_replace('"',"&quot;",$Desc);

  /* Load any subfolders */
  $Results = $DB->Action("SELECT folder_pk FROM leftnav WHERE parent=$ParentFolder AND folder_pk IS NOT NULL ORDER BY name;");
  /* Now create the HTML */
  if (isset($Results[0]['folder_pk']))
    {
    $V .= '<a href="javascript:ShowHide(' . "'TreeDiv-$ParentFolder'" . ')"><font class="treebranch">+</font></a>';
    }
  else
    {
    $V .= "<font class='treearm'>&ndash;</font>";
    }
  $V .= "&nbsp;";
  if (!empty($Desc)) { $Title = 'title="' . $Desc . '"'; }
  else { $Title = ""; }

  if (plugin_find_id("basenav") >= 0) { $Target = "target='basenav'"; }
  else { $Target = ""; }
  if (!empty($Browse)) { $V .= "<a $Title $Target class='treetext' href='$Uri?mod=browse&folder=$ParentFolder'>"; }
  if (!empty($Highlight) && ($Highlight == $ParentFolder))
    { $V .= "<font style='border: 1pt solid; color:red; font-weight:bold;'>"; }
  $V .= htmlentities($Name);
  if (!empty($Highlight) && ($Highlight == $ParentFolder))
    { $V .= "</font>"; }
  if (!empty($Browse)) { $V .= "</a>"; }
  $V .= "<br>\n";
  if (isset($Results[0]['folder_pk']))
    {
    $Hide="";
    if ($Depth > 0) { $Hide = "style='display:none;'"; }
    $V .= "<div id='TreeDiv-$ParentFolder' $Hide>\n";
    foreach($Results as $R)
      {
      $V .= FolderListDiv($R['folder_pk'],$Depth+1,$Highlight);
      }
    $V .= "</div>\n";
    }
  return($V);
} /* FolderListDiv() */

/***********************************************************
 FolderGetFromUpload(): Given an upload number, return the
 folder path in an array containing folder_pk and name.
 This is recursive!
 NOTE: If there is a recursive loop in the folder table, then
 this will loop INFINITELY.
 ***********************************************************/
$FolderGetFromUpload_1_Prepared=0;
$FolderGetFromUpload_2_Prepared=0;
function FolderGetFromUpload ($Uploadpk,$Folder=-1,$Stop=-1)
{
  global $DB;
  if (empty($DB)) { return; }
  if (empty($Uploadpk)) { return; }
  if ($Stop == -1) { $Stop = FolderGetTop(); }
  if ($Folder == $Stop) { return; }

  $SQL = "";
  $Parm = "";
  if ($Folder < 0)
    {
    /* Mode 2 means child_id is an upload_pk */
    global $FolderGetFromUpload_1_Prepared;
    $SQL = "FolderGetFromUpload_1";
    $Parm = $Uploadpk;
    if (!$FolderGetFromUpload_1_Prepared)
	{
	$DB->Prepare($SQL,"SELECT parent_fk,folder_name FROM foldercontents
			  INNER JOIN folder ON foldercontents.parent_fk = folder.folder_pk
			  AND foldercontents.foldercontents_mode = 2
			  WHERE foldercontents.child_id = $1 LIMIT 1;");
	$FolderGetFromUpload_1_Prepared=1;
	}
    }
  else
    {
    /* Mode 1 means child_id is a folder_pk */
    global $FolderGetFromUpload_2_Prepared;
    $SQL = "FolderGetFromUpload_2";
    $Parm = $Folder;
    if (!$FolderGetFromUpload_2_Prepared)
	{
	$DB->Prepare($SQL,"SELECT parent_fk,folder_name FROM foldercontents
			  INNER JOIN folder ON foldercontents.parent_fk = folder.folder_pk
			  AND foldercontents.foldercontents_mode = 1
			  WHERE foldercontents.child_id = $1 LIMIT 1;");
	$FolderGetFromUpload_2_Prepared=1;
	}
    }
  $Results = $DB->Execute($SQL,array($Parm));
  $R = &$Results[0];
  if (empty($R['parent_fk'])) { return; }
  $V = array();
  $V['folder_pk'] = $R['parent_fk'];
  $V['folder_name'] = $R['folder_name'];
  if ($R['parent_fk'] != 0)
	{
	$List = FolderGetFromUpload($Uploadpk,$R['parent_fk'],$Stop);
	}
  if (empty($List)) { $List = array(); }
  array_push($List,$V);
  return($List);
} // FolderGetFromUpload()

/***********************************************************
 FolderListUploads(): Returns an array of all uploads and upload_pk
 for a given folder.
 This does NOT recurse.
 The array is sorted by upload name.
 Folders may be empty!
 ***********************************************************/
function FolderListUploads($ParentFolder=-1)
  {
  global $DB;
  if (empty($DB)) { return; }
  if (empty($ParentFolder)) { return; }
  if ($ParentFolder == "-1") { $ParentFolder = FolderGetTop(); }
  $List=array();

  /* Get list of uploads */
  /** mode 1<<1 = upload_fk **/
  $SQL = "SELECT * FROM foldercontents
	INNER JOIN upload ON upload.upload_pk = foldercontents.child_id
	AND foldercontents.parent_fk = '$ParentFolder'
	AND foldercontents.foldercontents_mode = 2
	INNER JOIN ufile ON upload.ufile_fk = ufile.ufile_pk
	ORDER BY ufile.ufile_name,upload.upload_desc;";
  $Results = $DB->Action($SQL);
  foreach($Results as $R)
    {
    if (empty($R['upload_pk'])) { continue; }
    $New['upload_pk'] = $R['upload_pk'];
    $New['upload_desc'] = $R['upload_desc'];
    $New['name'] = $R['ufile_name'];
    array_push($List,$New);
    }
  return($List);
  } // FolderListUploads()

/***********************************************************
 FolderListUploadsRecurse(): Returns an array of all uploads, upload_pk,
 and folders, starting from the ParentFolder.
 The array is sorted by folder and upload name.
 Folders that are empty do not show up.
 This is recursive!
 NOTE: If there is a recursive loop in the folder table, then
 this will loop INFINITELY.
 ***********************************************************/
function FolderListUploadsRecurse($ParentFolder=-1, $FolderPath=NULL)
  {
  global $DB;
  if (empty($DB)) { return; }
  if (empty($ParentFolder)) { return; }
  if ($ParentFolder == "-1") { $ParentFolder = FolderGetTop(); }
  $List=array();

  /* Get list of uploads */
  /** mode 1<<1 = upload_fk **/
  $SQL = "SELECT * FROM foldercontents
	INNER JOIN upload ON upload.upload_pk = foldercontents.child_id
	AND foldercontents.parent_fk = '$ParentFolder'
	AND foldercontents.foldercontents_mode = 2
	INNER JOIN folder ON foldercontents.parent_fk = folder.folder_pk
	INNER JOIN ufile ON upload.ufile_fk = ufile.ufile_pk
	ORDER BY ufile.ufile_name,upload.upload_desc;";
  $Results = $DB->Action($SQL);
  foreach($Results as $R)
    {
    if (empty($R['upload_pk'])) { continue; }
    $New['upload_pk'] = $R['upload_pk'];
    $New['upload_desc'] = $R['upload_desc'];
    $New['name'] = $R['ufile_name'];
    $New['folder'] = $FolderPath . "/" . $R['folder_name'];
    array_push($List,$New);
    }

  /* Get list of subfolders and recurse */
  /** mode 1<<0 = folder_pk **/
  $SQL = "SELECT A.child_id AS id,B.folder_name AS folder,C.folder_name AS subfolder
	FROM foldercontents AS A
	INNER JOIN folder AS B ON A.parent_fk = B.folder_pk
	AND A.foldercontents_mode = 1
	AND A.parent_fk = '$ParentFolder'
	INNER JOIN folder AS C ON A.child_id = C.folder_pk
	ORDER BY C.folder_name;";
  $Results = $DB->Action($SQL);
  foreach($Results as $R)
    {
    if (empty($R['id'])) { continue; }
    /* RECURSE! */
    $SubList = FolderListUploadsRecurse($R['id'],$FolderPath . "/" . $R['folder']);
    $List = array_merge($List,$SubList);
    }

  /* Return findings */
  return($List);
  } // FolderListUploadsRecurse()
?>
