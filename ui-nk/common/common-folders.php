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

/************************************************************
 FolderGetTop(): Find the top-of-tree folder_pk for the current user.
 TBD: "username" will be added in the future and it may change
 how this function works.
 ************************************************************/
function FolderGetTop()
{
  global $Plugins;
  global $DB;
  if (empty($DB)) { return; }
  if (!empty($username)) { $Where = "WHERE user_name='$username'"; }
  else { $Where = ""; }
  /* Get the list of folders */
  $Results = $DB->Action("SELECT root_folder_fk FROM users $Where ORDER BY user_pk ASC LIMIT 1;");
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
    $V .= "<option value='$ParentFolder'>\n";
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
 FolderListDiv(): Create the tree, using DIVs.
 It returns the full HTML.
 This is recursive!
 NOTE: If there is a recursive loop in the folder table, then
 this will loop INFINITELY.
 ***********************************************************/
function FolderListDiv($ParentFolder,$Depth)
  {
  global $Plugins;
  if ($ParentFolder == "-1")
	{
	return(FolderListDiv(FolderGetTop(),0));
	}
  if (empty($ParentFolder)) { return; }
  global $DB;
  if (empty($DB)) { return; }
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
  if (!empty($Browse)) { $V .= "<a $Title target='basenav' href='$Uri?mod=browse&folder=$ParentFolder'>"; }
  $V .= "<font class='treetext'>" . htmlentities($Name) . "</font>";
  if (!empty($Browse)) { $V .= "</a>"; }
  $V .= "<br>\n";
  if (isset($Results[0]['folder_pk']))
    {
    $Hide="";
    if ($Depth > 0) { $Hide = "style='display:none;'"; }
    $V .= "<div id='TreeDiv-$ParentFolder' $Hide>\n";
    foreach($Results as $R)
      {
      $V .= FolderListDiv($R['folder_pk'],$Depth+1);
      }
    $V .= "</div>\n";
    }
  return($V);
} /* FolderListDiv() */

?>
