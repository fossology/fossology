<?php
/*
 SPDX-FileCopyrightText: © 2008-2015 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014-2017 Siemens AG

 SPDX-License-Identifier: LGPL-2.1-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\UploadDao;

/**
 * \file
 * \brief Common Folder Functions
 * Design note:
 * Folders could be stored in a menu listing (using menu_insert).
 * However, since menu_insert() runs a usort() during each insert,
 * this can be really slow. For speed, folders are handled separately.
 */

/**
 * \brief DEPRECATED! Find the top-of-tree folder_pk for the current user.
 * \deprecated Use GetUserRootFolder()
 */
function FolderGetTop()
{
  /* Get the list of folders */
  if (! empty($_SESSION['Folder'])) {
    return ($_SESSION['Folder']);
  }
  global $PG_CONN;
  if (empty($PG_CONN)) {
    return;
  }
  $sql = "SELECT root_folder_fk FROM users ORDER BY user_pk ASC LIMIT 1";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);
  return($row['root_folder_fk']);
} // FolderGetTop()

/**
 * \brief Get the top-of-tree folder_pk for the current user.
 *  Fail if there is no user session.
 *
 * \return folder_pk for the current user
 */
function GetUserRootFolder()
{
  global $PG_CONN;

  /* validate inputs */
  $user_pk = Auth::getUserId();

    /* everyone has a user_pk, even if not logged in. But verify. */
  if (empty($user_pk)) {
    return "__FILE__:__LINE__ GetUserRootFolder(Not logged in)<br>";
  }

  /* Get users root folder */
  $sql = "select root_folder_fk from users where user_pk=$user_pk";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $UsersRow = pg_fetch_assoc($result);
  $root_folder_fk = $UsersRow['root_folder_fk'];
  pg_free_result($result);
  if (empty($root_folder_fk)) {
    $text = _("Missing root_folder_fk for user ");
    fatal("<h2>".$text.$user_pk."</h2>", __FILE__, __LINE__);
  }
  return $root_folder_fk;
} // GetUserRootFolder()

/**
 * \brief Return an array of folder_pk, folder_name
 *        from the users.root_folder_fk to $folder_pk
 *
 * Array is in top down order.
 * If you need to know the folder_pk of an upload or uploadtree, use GetFolderFromItem()
 *
 * \param int $folder_pk
 *
 * \return The folder list:
 * \code
 * FolderList = array({'folder_pk'=>folder_pk, 'folder_name'=>folder_name}, ...
 * \endcode
 */
function Folder2Path($folder_pk)
{
  global $PG_CONN;
  $FolderList = array();

  /* validate inputs */
  if (empty($folder_pk)) {
    return __FILE__.":".__LINE__." Folder2Browse(empty)<br>";
  }

  /* Get users root folder */
  $root_folder_fk = GetUserRootFolder();   // will fail if no user session

  while ($folder_pk) {
    $sql = "select folder_pk, folder_name from folder where folder_pk='$folder_pk'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $FolderRow = pg_fetch_assoc($result);
    pg_free_result($result);
    array_unshift($FolderList, $FolderRow);

    // Limit folders to user root.  Limit to an arbitrary 20 folders as a failsafe
    // against this loop going infinite.
    if (($folder_pk == $root_folder_fk) || (count($FolderList)>20)) {
      break;
    }

    $sql = "select parent_fk from foldercontents where child_id='$folder_pk' and foldercontents_mode=".FolderDao::MODE_FOLDER;
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $FolderRow = pg_fetch_assoc($result);
    pg_free_result($result);
    $folder_pk = $FolderRow['parent_fk'];
  }
  return($FolderList);
} // Folder2Path()


/**
 * \brief Find what folder an item is in.
 *
 * \param int $upload_pk NULL if $uploadtree_pk is passed in
 * \param int $uploadtree_pk NULL if $upload_pk is passed in
 *
 * \note If both $upload_pk and $uploadtree_pk are passed in, $upload_pk will be used.
 *
 * \return The folder_pk that the upload_pk (or uploadtree_pk) is in
 */
function GetFolderFromItem($upload_pk="", $uploadtree_pk = "")
{
  global $PG_CONN;

  /* validate inputs */
  if (empty($uploadtree_pk) && empty($upload_pk)) {
    return "__FILE__:__LINE__ GetFolderFromItem(empty)<br>";
  }

  if (empty($upload_pk)) {
    $UTrec = GetSingleRec("uploadtree", "where uploadtree_pk=$uploadtree_pk");
    $upload_pk = $UTrec['upload_fk'];
  }

  $sql = "select parent_fk from foldercontents where child_id='$upload_pk' and foldercontents_mode=".FolderDao::MODE_UPLOAD;
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $FolderRow = pg_fetch_assoc($result);
  pg_free_result($result);
  return $FolderRow['parent_fk'];
} // GetFolderFromItem()


/**
 * \brief Create the folder tree, using OPTION tags.
 * \note The caller must already have created the FORM and SELECT tags.
 * \note This is recursive!
 * \note If there is a recursive loop in the folder table, then
 * this will loop INFINITELY.
 *
 * \param int $ParentFolder Parents folder_fk
 * \param int $Depth  Tree depth to create
 * \param bool $IncludeTop  True to include fossology root folder
 * \param int $SelectId folder_fk of selected folder
 * \param bool $linkParent If true, the option tag will have $OldParent and
 * $ParentFolder as the value
 * \param int $OldParent Parent of the parent folder
 *
 * \return HTML of the folder tree
 */
function FolderListOption($ParentFolder,$Depth, $IncludeTop=1, $SelectId=-1, $linkParent=false, $OldParent=0)
{
  if ($ParentFolder == "-1") {
    $ParentFolder = FolderGetTop();
  }
  if (empty($ParentFolder)) {
    return;
  }
  global $PG_CONN;
  if (empty($PG_CONN)) {
    return;
  }
  $V = "";

  if (($Depth != 0) || $IncludeTop) {
    if ($ParentFolder == $SelectId) {
      $V .= "<option value='$ParentFolder' SELECTED>";
    } elseif ($linkParent) {
      if (empty($OldParent)) {
        $OldParent = 0;
      }
      $V .= "<option value='$OldParent $ParentFolder'>";
    } else {
      $V .= "<option value='$ParentFolder'>";
    }
    if ($Depth != 0) {
      $V .= "&nbsp;&nbsp;";
    }
    for ($i=1; $i < $Depth; $i++) {
      $V .= "&nbsp;&nbsp;";
    }

    /* Load this folder's name */
    $sql = "SELECT folder_name FROM folder WHERE folder_pk=$ParentFolder LIMIT 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $Name = trim($row['folder_name']);
    if ($Name == "") {
      $Name = "[default]";
    }

    /* Load any subfolders */
    /* Now create the HTML */
    $V .= htmlentities($Name, ENT_HTML5 | ENT_QUOTES);
    $V .= "</option>\n";
  }
  /* Load any subfolders */
  $sql = "SELECT folder.folder_pk, folder.folder_name AS name,
            folder.folder_desc AS description,
            foldercontents.parent_fk AS parent,
            foldercontents.foldercontents_mode,
            NULL AS ts, NULL AS upload_pk, NULL AS pfile_fk, NULL AS ufile_mode
            FROM folder, foldercontents
            WHERE foldercontents.foldercontents_mode = ".FolderDao::MODE_FOLDER."
            AND foldercontents.parent_fk =$ParentFolder
            AND foldercontents.child_id = folder.folder_pk
            AND folder.folder_pk is not null
            ORDER BY name";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  if (pg_num_rows($result) > 0) {
    $Hide = "";
    if ($Depth > 0) {
      $Hide = "style='display:none;'";
    }
    while ($row = pg_fetch_assoc($result)) {
      $V .= FolderListOption($row['folder_pk'], $Depth+1,$IncludeTop,$SelectId,$linkParent,$row['parent']);
    }
  }
  pg_free_result($result);
  return($V);
} // FolderListOption()

/**
 * \brief Given a folder_pk, return the full path to this folder.
 * \note This is recursive!
 * \note If there is a recursive loop in the folder table, then
 * this will loop INFINITELY.
 *
 * \param int $FolderPk Folder id
 * \param int $Top Optional, default is user's top folder. folder_pk of top of desired path.
 *
 * \return string full path of this folder
 */
function FolderGetName($FolderPk,$Top=-1)
{
  global $PG_CONN;
  if ($Top == -1) {
    $Top = FolderGetTop();
  }
  $sql = "SELECT folder_name,foldercontents.parent_fk FROM folder
	LEFT JOIN foldercontents ON foldercontents_mode = ".FolderDao::MODE_FOLDER."
	AND child_id = '$FolderPk'
	WHERE folder_pk = '$FolderPk'
	LIMIT 1;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  $Parent = $row['parent_fk'];
  $Name = $row['folder_name'];
  if (! empty($Parent) && ($FolderPk != $Top)) {
    $Name = FolderGetName($Parent,$Top) . "/" . $Name;
  }
  return($Name);
}


/**
 * \brief DEPRECATED! Given an upload number, return the
 * folder path in an array containing folder_pk and name.
 * \note This is recursive!
 * \note If there is a recursive loop in the folder table, then
 * this will loop INFINITELY.
 * \deprecated Use Folder2Path() and GetFolderFromItem()
 */
function FolderGetFromUpload($Uploadpk, $Folder = -1, $Stop = -1)
{
  global $PG_CONN;
  if (empty($PG_CONN)) {
    return;
  }
  if (empty($Uploadpk)) {
    return;
  }
  if ($Stop == - 1) {
    $Stop = FolderGetTop();
  }
  if ($Folder == $Stop) {
    return;
  }

  $sql = "";
  $Parm = "";
  if ($Folder < 0) {
    /* Mode 2 means child_id is an upload_pk */
    $Parm = $Uploadpk;
    $sql = "SELECT foldercontents.parent_fk,folder_name FROM foldercontents
              INNER JOIN folder ON foldercontents.parent_fk = folder.folder_pk
			  AND foldercontents.foldercontents_mode = " . FolderDao::MODE_UPLOAD."
			  WHERE foldercontents.child_id = $Parm LIMIT 1;";
  } else {
    /* Mode 1 means child_id is a folder_pk */
    $Parm = $Folder;
    $sql = "SELECT foldercontents.parent_fk,folder_name FROM foldercontents
			  INNER JOIN folder ON foldercontents.parent_fk = folder.folder_pk
			  AND foldercontents.foldercontents_mode = 1
			  WHERE foldercontents.child_id = $Parm LIMIT 1;";
  }
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $R = pg_fetch_assoc($result);
  if (empty($R['parent_fk'])) {
    pg_free_result($result);
    return;
  }
  $V = array();
  $V['folder_pk'] = $R['parent_fk'];
  $V['folder_name'] = $R['folder_name'];
  if ($R['parent_fk'] != 0) {
    $List = FolderGetFromUpload($Uploadpk, $R['parent_fk'],$Stop);
  }
  if (empty($List)) {
    $List = array();
  }
  $List[] = $V;
  pg_free_result($result);
  return($List);
} // FolderGetFromUpload()


/**
 * \brief Returns an array of uploads in a folder.
 *
 *  Only uploads for which the user has permission >= $perm are returned.
 *  This does NOT recurse.
 *  The returned array is sorted by ufile_name and upload_pk.
 * \param int $ParentFolder Optional folder_pk, default is users root folder.
 * \param int $perm Minimum permission
 * \return `array{upload_pk, upload_desc, upload_ts, ufile_name}`
 *  for all uploads in a given folder.
 *
 */
function FolderListUploads_perm($ParentFolder, $perm)
{
  global $PG_CONN;

  if (empty($PG_CONN)) {
    return;
  }
  if (empty($ParentFolder)) {
    return;
  }
  if ($ParentFolder == "-1") {
    $ParentFolder = GetUserRootFolder();
  }
  $groupId = Auth::getGroupId();
  /* @var $uploadDao UploadDao */
  $uploadDao = $GLOBALS['container']->get('dao.upload');
  $List=array();

  /* Get list of uploads under $ParentFolder */
  /* mode 2 = upload_fk */
  $sql = "SELECT upload_pk, upload_desc, upload_ts, upload_filename
	FROM foldercontents,upload
  INNER JOIN uploadtree ON upload_fk = upload_pk AND upload.pfile_fk = uploadtree.pfile_fk AND parent IS NULL AND lft IS NOT NULL
	WHERE foldercontents.parent_fk = '$ParentFolder'
	AND foldercontents.foldercontents_mode = ".FolderDao::MODE_UPLOAD."
	AND foldercontents.child_id = upload.upload_pk
	ORDER BY upload_filename,upload_pk;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  while ($R = pg_fetch_assoc($result)) {
    if (empty($R['upload_pk'])) {
      continue;
    }
    if ($perm == Auth::PERM_READ &&
      ! $uploadDao->isAccessible($R['upload_pk'], $groupId)) {
      continue;
    }
    if ($perm == Auth::PERM_WRITE &&
      ! $uploadDao->isEditable($R['upload_pk'], $groupId)) {
      continue;
    }

    $New = array();
    $New['upload_pk'] = $R['upload_pk'];
    $New['upload_desc'] = $R['upload_desc'];
    $New['upload_ts'] = Convert2BrowserTime(substr($R['upload_ts'], 0, 19));
    $New['name'] = $R['upload_filename'];
    $List[] = $New;
  }
  pg_free_result($result);
  return($List);
} // FolderListUploads_perm()

/**
 * @brief Get uploads and folder info, starting from $ParentFolder.
 *
 * The array is sorted by folder and upload name.
 * Folders that are empty do not show up.
 * \note This is recursive!
 * \note If there is a recursive loop in the folder table, then
 * this will loop INFINITELY.
 *
 * @param int $ParentFolder folder_pk, -1 for users root folder
 * @param string $FolderPath Used for recursion, caller should not specify.
 * @param Auth::PERM_READ|Auth::PERM_WRITE $perm Permission required
 * @return array of `{upload_pk, upload_desc, name, folder}`
 */
function FolderListUploadsRecurse($ParentFolder=-1, $FolderPath = '',
  $perm = Auth::PERM_READ)
{
  global $PG_CONN;
  if (empty($PG_CONN)) {
    return array();
  }
  if (empty($ParentFolder)) {
    return array();
  }
  if ($perm != Auth::PERM_READ && $perm = Auth::PERM_WRITE) {
    return array();
  }
  if ($ParentFolder == "-1") {
    $ParentFolder = FolderGetTop();
  }
  $groupId = Auth::getGroupId();
  /* @var $uploadDao UploadDao */
  $uploadDao = $GLOBALS['container']->get('dao.upload');
  $List=array();

  /* Get list of uploads */
  /* mode 1<<1 = upload_fk */
  $sql = "SELECT upload_pk, upload_desc, ufile_name, folder_name FROM folder,foldercontents,uploadtree, upload
    WHERE
        foldercontents.parent_fk = '$ParentFolder'
    AND foldercontents.foldercontents_mode = ". FolderDao::MODE_UPLOAD ."
    AND foldercontents.child_id = upload.upload_pk
    AND folder.folder_pk = $ParentFolder
    AND uploadtree.upload_fk = upload.upload_pk
    AND uploadtree.parent is null
    ORDER BY uploadtree.ufile_name,upload.upload_desc";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  while ($R = pg_fetch_assoc($result)) {
    if (empty($R['upload_pk'])) {
      continue;
    }
    if ($perm == Auth::PERM_READ &&
      ! $uploadDao->isAccessible($R['upload_pk'], $groupId)) {
      continue;
    }
    if ($perm == Auth::PERM_WRITE &&
      ! $uploadDao->isEditable($R['upload_pk'], $groupId)) {
      continue;
    }

    $New = array();
    $New['upload_pk'] = $R['upload_pk'];
    $New['upload_desc'] = $R['upload_desc'];
    $New['name'] = $R['ufile_name'];
    $New['folder'] = $FolderPath . "/" . $R['folder_name'];
    $List[] = $New;
  }
  pg_free_result($result);

  /* Get list of subfolders and recurse */
  /* mode 1<<0 = folder_pk */
  $sql = "SELECT A.child_id AS id,B.folder_name AS folder,B.folder_name AS subfolder
	FROM foldercontents AS A
	INNER JOIN folder AS B ON A.parent_fk = B.folder_pk
	AND A.foldercontents_mode = ". FolderDao::MODE_FOLDER ."
	AND A.parent_fk = '$ParentFolder'
  AND B.folder_pk = $ParentFolder
	ORDER BY B.folder_name;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  while ($R = pg_fetch_assoc($result)) {
    if (empty($R['id'])) {
      continue;
    }
    /* RECURSE! */
    $SubList = FolderListUploadsRecurse($R['id'], $FolderPath . "/" . $R['folder'], $perm);
    $List = array_merge($List,$SubList);
  }
  pg_free_result($result);
  /* Return findings */
  return($List);
} // FolderListUploadsRecurse()


/**
 * \brief Get an array of all the folders from a $RootFolder on down.
 *
 * Recursive. This is typically used to build a select list of folder names.
 *
 * \param int $RootFolder Default is entire software repository
 * \param[out] array $FolderArray Returned array of folder_pk=>folder_name's
 *
 * \return $FolderArray of `{folder_pk=>folder_name, folder_pk=>folder_name, ...}`
 * in folder order.
 * If no folders are in the list, an empty array is returned.
 *
 * \todo Possibly this could be a common function and FolderListOption() could
 *       use this for its data.  In general data collection and data formatting
 *       should be separate functions.
 */
function GetFolderArray($RootFolder, &$FolderArray)
{
  global $PG_CONN;

  if ($RootFolder == "-1") {
    $RootFolder = FolderGetTop();
  }
  if (empty($RootFolder)) {
    return $FolderArray;
  }

  /* Load this folder's name */
  $sql = "SELECT folder_name, folder_pk FROM folder WHERE folder_pk=$RootFolder LIMIT 1;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);

  $Name = trim($row['folder_name']);
  $FolderArray[$row['folder_pk']] = $row['folder_name'];

  /* Load any subfolders */
  $sql = "SELECT folder.folder_pk, folder.folder_name,
            foldercontents.parent_fk
            FROM folder, foldercontents
            WHERE foldercontents.foldercontents_mode = ".FolderDao::MODE_FOLDER."
            AND foldercontents.parent_fk =$RootFolder
            AND foldercontents.child_id = folder.folder_pk
            AND folder.folder_pk is not null
            ORDER BY folder_name";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  if (pg_num_rows($result) > 0) {
    while ($row = pg_fetch_assoc($result)) {
      GetFolderArray($row['folder_pk'], $FolderArray);
    }
  }
  pg_free_result($result);
}

/**
 * \brief Check if one file path contains an excluding text
 *
 * \param string $FilePath File path
 * \param string $ExcludingText Excluding text
 *
 * \return 1: include, 0: not include
 */
function ContainExcludeString($FilePath, $ExcludingText)
{
  $excluding_length = 0;
  $excluding_flag = 0; // 1: exclude 0: not exclude
  if ($ExcludingText) {
    $excluding_length = strlen($ExcludingText);
  }

  /* filepath contains 'xxxx/', '/xxxx/', 'xxxx', '/xxxx' */
  if ($excluding_length > 0 && strstr($FilePath, $ExcludingText)) {
    $excluding_flag = 1;
    /* filepath does not contain 'xxxx/' */
    if ('/' != $ExcludingText[0] && '/' == $ExcludingText[$excluding_length - 1] &&
      ! strstr($FilePath, '/'.$ExcludingText)) {
      $excluding_flag = 0;
    }
  }
  return $excluding_flag;
}
