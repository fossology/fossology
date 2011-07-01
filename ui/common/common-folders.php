<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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

/************************************************************
 FolderGetTop(): Find the top-of-tree folder_pk for the current user.
 TBD: "username" will be added in the future and it may change
 how this function works.
 DEPRECATED - USE GetUserRootFolder()
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

/************************************************************
 GetUserRootFolder(): 
 Return the top-of-tree folder_pk for the current user.
 Fail if there is no user session.
 ************************************************************/
function GetUserRootFolder()
{
  global $PG_CONN;

  /* validate inputs */
  $user_pk = GetArrayVal("UserId", $_SESSION);

  /* everyone has a user_pk, even if not logged in.  But verify. */
  if (empty($user_pk)) return "__FILE__:__LINE__ GetUserRootFolder(Not logged in)<br>";

  /* Get users root folder */
  $sql = "select root_folder_fk from users where user_pk=$user_pk";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $UsersRow = pg_fetch_assoc($result);
  $root_folder_fk = $UsersRow['root_folder_fk'];
  pg_free_result($result);
  return $root_folder_fk;
} // GetUserRootFolder()

/************************************************************
 GetRootFolder(): 
 Return the top-of-tree folder_pk.
 This is typically the folder_pk for the folder "Software Repository"
 but this function has no dependency on the folder name.
 Technically, one could create multiple roots, but that would be a
 bad thing and the UI won't let you do that.
 ************************************************************/
function GetRootFolder()
{
  global $PG_CONN;

  /* if there is only a single folder, then that must be the root */
  $sql = "select folder_pk from folder limit 2";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  if (pg_num_rows($result) == 1)
  {
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    return $row['folder_pk'];
  }
  else if (pg_num_rows($result) == 0)
  {
    $text = _("This database has not been properly installed: No root folder found.");
    echo "$text<br>";
    echo __FILE__ . ":" . __LINE__ . ":". __FUNCTION__ ."<br>";
    exit;
  }

  /* Get all the folder_pk's  of folders that have children 
   * and remove all the folders that are themselves children.
   * The remainder (folder that is never a child) is the root.
   * We should probably give some thought to having all folders in foldercontents.
   * The root folder would just have a null parent.  That would be lots simpler
   * than what we have to go through here.
   */
  $sql = "select distinct parent_fk as folder_pk from foldercontents where foldercontents_mode=1
          except select distinct child_id as folder_pk from foldercontents where foldercontents_mode=1 ";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  if (pg_num_rows($result) == 0)
  {
    $text = _("This database has not been properly installed: No root folder found.");
    echo "$text<br>";
    echo __FILE__ . ":" . __LINE__ . ":". __FUNCTION__ ."<br>";
    exit;
  }
  if (pg_num_rows($result) > 1)
  {
    $text = _("This database has not been properly installed: Multiple root folders found.");
    echo "$text<br>";
    echo __FILE__ . ":" . __LINE__ . ":". __FUNCTION__ ."<br>";
    exit;
  }
  $row = pg_fetch_assoc($result);
  pg_free_result($result);


  return $row['folder_pk'];
} // GetRootFolder()


/************************************************************
 * Folder2Path(): 
 *   $folder_pk
 *  
 * Return an array of the folder_pk, folder_name
 * From the users.root_folder_fk to $folder_pk
 * Array is in top down order.
 * FolderList = array({'folder_pk'=>folder_pk, 'folder_name'=>folder_name}, ...
 *
 * If you need to know the folder_pk of an upload or uploadtree, use GetFolderFromItem()
 ************************************************************/
function Folder2Path($folder_pk)
{
  global $PG_CONN;
  $FolderList = array();

  /* validate inputs */
  if (empty($folder_pk)) return __FILE__.":".__LINE__." Folder2Browse(empty)<br>";
  
  /* Get users root folder */
  $root_folder_fk = GetUserRootFolder();   // will fail if no user session
  
  while($folder_pk)
  {
    $sql = "select folder_pk, folder_name from folder where folder_pk='$folder_pk'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $FolderRow = pg_fetch_assoc($result);
    pg_free_result($result);
    array_unshift($FolderList, $FolderRow);
    
    // Limit folders to user root.  Limit to an arbitrary 20 folders as a failsafe 
    // against this loop going infinite.
    if (($folder_pk == $root_folder_fk) or (count($FolderList)>20)) break;
    
    $sql = "select parent_fk from foldercontents where child_id='$folder_pk' and foldercontents_mode=1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $FolderRow = pg_fetch_assoc($result);
    pg_free_result($result);
    $folder_pk = $FolderRow['parent_fk'];
  } 
  return($FolderList);
} // Folder2Path()


/************************************************************
 * GetFolderFromItem(): 
 *   $upload_pk
 *   $uploadtree_pk
 * Either $upload_pk OR $uploadtree_pk must be passed in.
 *  
 * Return the folder_pk that the upload_pk (or uploadtree_pk) is in:
 ************************************************************/
function GetFolderFromItem($upload_pk="", $uploadtree_pk="")
{
  global $PG_CONN;

  /* validate inputs */
  if (empty($uploadtree_pk) and empty($upload_pk)) return "__FILE__:__LINE__ GetFolderFromItem(empty)<br>";
  
  if (empty($upload_pk))
  {
    $UTrec = GetSingleRec("uploadtree", "where uploadtree_pk=$uploadtree_pk");
    $upload_pk = $UTrec['upload_fk'];
  }
  
  $sql = "select parent_fk from foldercontents where child_id='$upload_pk' and foldercontents_mode=2";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $FolderRow = pg_fetch_assoc($result);
  pg_free_result($result);
  return $FolderRow['parent_fk'];
} // GetFolderFromItem()


/***********************************************************
 FolderListOption(): Create the tree, using OPTION tags.
 The caller must already have created the FORM and SELECT tags.
 It returns the full HTML.
 This is recursive!
 NOTE: If there is a recursive loop in the folder table, then
 this will loop INFINITELY.
 ***********************************************************/
function FolderListOption($ParentFolder,$Depth, $IncludeTop=1, $SelectId=-1)
  {
  global $Plugins;
  if ($ParentFolder == "-1") { $ParentFolder = FolderGetTop(); }
  if (empty($ParentFolder)) { return; }
  global $DB;
  if (empty($DB)) { return; }
  $V="";

  if (($Depth != 0) || $IncludeTop)
    {
    if ($ParentFolder == $SelectId)
      {
      $V .= "<option value='$ParentFolder' SELECTED>";
      }
    else
      {
      $V .= "<option value='$ParentFolder'>";
      }
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
    /* Load any subfolders */
    $Results = $DB->Action("SELECT folder.folder_pk, folder.folder_name AS name, 
                                   folder.folder_desc AS description, 
                                   foldercontents.parent_fk AS parent, 
                                   foldercontents.foldercontents_mode, 
                                   NULL AS ts, NULL AS upload_pk, NULL AS pfile_fk, NULL AS ufile_mode
                            FROM folder, foldercontents
                            WHERE foldercontents.foldercontents_mode = 1
                                  AND foldercontents.parent_fk =$ParentFolder
                                  AND foldercontents.child_id = folder.folder_pk
                                  AND folder.folder_pk is not null
                            ORDER BY name");
  if (isset($Results[0]['folder_pk']))
    {
    $Hide="";
    if ($Depth > 0) { $Hide = "style='display:none;'"; }
    foreach($Results as $R)
	{
	$V .= FolderListOption($R['folder_pk'],$Depth+1,$IncludeTop,$SelectId);
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
  if ($ParentFolder == "-1") { return(FolderListDiv(GetUserRootFolder(),0)); }
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
  if ($ShowParent && ($ParentFolder != GetUserRootFolder()))
  {
    $Results = $DB->Action("SELECT parent_fk FROM foldercontents WHERE foldercontents_mode = 1 AND child_id = '$ParentFolder' LIMIT 1;");
    if (count($Results) > 0)
    {
      $P = $Results[0]['parent_fk'];
      if (!empty($P) && ($P != 0)) { $ParentFolder=$P; }
    }
    else
    {
      // No parent
      return "";
    }
  }

  /* Load this folder's name */
  $Results = $DB->Action("SELECT folder_name,folder_desc FROM folder WHERE folder_pk=$ParentFolder LIMIT 1;");
  $Name = trim($Results[0]['folder_name']);
  $Desc = trim($Results[0]['folder_desc']);
  if ($Name == "") { $Name = "[default]"; }
  $Desc = str_replace('"',"&quot;",$Desc);

  /* Load any subfolders */
  $Results = $DB->Action("SELECT folder.folder_pk, folder.folder_name AS name, 
                                 folder.folder_desc AS description, 
                                 foldercontents.parent_fk AS parent, 
                                 foldercontents.foldercontents_mode, 
                                 NULL AS ts, NULL AS upload_pk, NULL AS pfile_fk, NULL AS ufile_mode
                          FROM folder, foldercontents
                          WHERE foldercontents.foldercontents_mode = 1
                                AND foldercontents.parent_fk =$ParentFolder
                                AND foldercontents.child_id = folder.folder_pk
                                AND folder.folder_pk is not null
                          ORDER BY name");

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
      if (!HaveFolderPerm($R['folder_pk'])) continue;
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
 DEPRECATED!  USE Folder2Path() and GetFolderFromItem()
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
 FolderListUploads(): Returns an array of:
   upload_pk
   upload_desc
   upload_ts
   ufile_name
 for all uploads in a given folder.
 This does NOT recurse.
 The returned array is sorted by ufile_name and ufile_desc.
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
  $SQL = "SELECT upload_pk, upload_desc, upload_ts, ufile_name
	FROM foldercontents,uploadtree,upload
	WHERE foldercontents.parent_fk = '$ParentFolder'
	AND foldercontents.foldercontents_mode = 2
	AND foldercontents.child_id = upload.upload_pk
	AND uploadtree.upload_fk = upload.upload_pk
	AND uploadtree.parent IS NULL
	ORDER BY uploadtree.ufile_name,upload_pk;";
  $Results = $DB->Action($SQL);
  foreach($Results as $R)
    {
    if (empty($R['upload_pk'])) { continue; }
    $New['upload_pk'] = $R['upload_pk'];
    $New['upload_desc'] = $R['upload_desc'];
    $New['upload_ts'] = substr($R['upload_ts'],0,19);
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
 $SQL = "SELECT upload_pk, upload_desc, ufile_name, folder_name FROM foldercontents,uploadtree, u
pload
    WHERE 
        foldercontents.parent_fk = '$ParentFolder'
    AND foldercontents.foldercontents_mode = 2 
    AND foldercontents.child_id = upload.upload_pk
    AND uploadtree.upload_fk = upload.upload_pk
    AND uploadtree.parent is null
    ORDER BY uploadtree.ufile_name,upload.upload_desc;";
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
