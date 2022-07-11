<?php
/***********************************************************
 Copyright (C) 2008-2015 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2014-2017 Siemens AG

 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 ***********************************************************/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\ProjectDao;
use Fossology\Lib\Dao\UploadDao;

/**
 * \file
 * \brief Common Project Functions
 * Design note:
 * Projects could be stored in a menu listing (using menu_insert).
 * However, since menu_insert() runs a usort() during each insert,
 * this can be really slow. For speed, projects are handled separately.
 */

/**
 * \brief DEPRECATED! Find the top-of-tree project_pk for the current user.
 * \deprecated Use GetUserRootProject()
 */
function ProjectGetTop()
{
  /* Get the list of projects */
  if (! empty($_SESSION['Project'])) {
    return ($_SESSION['Project']);
  }
  global $PG_CONN;
  if (empty($PG_CONN)) {
    return;
  }
  $sql = "SELECT root_project_fk FROM users ORDER BY user_pk ASC LIMIT 1";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);
  return($row['root_project_fk']);
} // ProjectGetTop()

/**
 * \brief Get the top-of-tree project_pk for the current user.
 *  Fail if there is no user session.
 *
 * \return project_pk for the current user
 */
function GetUserRootProject()
{

  global $PG_CONN;

  /* validate inputs */
  $user_pk = Auth::getUserId();

    /* everyone has a user_pk, even if not logged in. But verify. */
  if (empty($user_pk)) {
    return "__FILE__:__LINE__ GetUserRootProject(Not logged in)<br>";
  }

  /* Get users root project */
  $sql = "select root_project_fk from users where user_pk=$user_pk";
  $result = pg_query($PG_CONN, $sql);

  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $UsersRow = pg_fetch_assoc($result);
  $root_project_fk = $UsersRow['root_project_fk'];
  pg_free_result($result);
  if (empty($root_project_fk)) {
    $text = _("Missing root_project_fk for user ");
    fatal("<h2>".$text.$user_pk."</h2>", __FILE__, __LINE__);
  }
  return $root_project_fk;
} // GetUserRootProject()

/**
 * \brief Return an array of project_pk, project_name
 *        from the users.root_project_fk to $project_pk
 *
 * Array is in top down order.
 * If you need to know the project_pk of an upload or uploadtree, use GetProjectFromItem()
 *
 * \param int $project_pk
 *
 * \return The project list:
 * \code
 * ProjectList = array({'project_pk'=>project_pk, 'project_name'=>project_name}, ...
 * \endcode
 */
function Project2Path($project_pk)
{

  global $PG_CONN;
  $ProjectList = array();

  /* validate inputs */
  if (empty($project_pk)) {
    return __FILE__.":".__LINE__." Project2Browse(empty)<br>";
  }

  /* Get users root project */
  $root_project_fk = GetUserRootProject();   // will fail if no user session

  while ($project_pk) {
    $sql = "select project_pk, project_name from project where project_pk='$project_pk'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $ProjectRow = pg_fetch_assoc($result);
    pg_free_result($result);
    array_unshift($ProjectList, $ProjectRow);

    // Limit projects to user root.  Limit to an arbitrary 20 projects as a failsafe
    // against this loop going infinite.
    if (($project_pk == $root_project_fk) || (count($ProjectList)>20)) {
      break;
    }

    $sql = "select parent_fk from projectcontents where child_id='$project_pk' and projectcontents_mode=".ProjectDao::MODE_PROJECT;
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $ProjectRow = pg_fetch_assoc($result);
    pg_free_result($result);
    $project_pk = $ProjectRow['parent_fk'];
  }
  return($ProjectList);
} // Project2Path()


/**
 * \brief Find what project an item is in.
 *
 * \param int $upload_pk NULL if $uploadtree_pk is passed in
 * \param int $uploadtree_pk NULL if $upload_pk is passed in
 *
 * \note If both $upload_pk and $uploadtree_pk are passed in, $upload_pk will be used.
 *
 * \return The project_pk that the upload_pk (or uploadtree_pk) is in
 */
function GetProjectFromItem($upload_pk="", $uploadtree_pk = "")
{
  global $PG_CONN;

  /* validate inputs */
  if (empty($uploadtree_pk) && empty($upload_pk)) {
    return "__FILE__:__LINE__ GetProjectFromItem(empty)<br>";
  }

  if (empty($upload_pk)) {
    $UTrec = GetSingleRec("uploadtree", "where uploadtree_pk=$uploadtree_pk");
    $upload_pk = $UTrec['upload_fk'];
  }

  $sql = "select parent_fk from projectcontents where child_id='$upload_pk' and projectcontents_mode=".ProjectDao::MODE_UPLOAD;
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $ProjectRow = pg_fetch_assoc($result);
  pg_free_result($result);
  return $ProjectRow['parent_fk'];
} // GetProjectFromItem()


/**
 * \brief Create the project tree, using OPTION tags.
 * \note The caller must already have created the FORM and SELECT tags.
 * \note This is recursive!
 * \note If there is a recursive loop in the project table, then
 * this will loop INFINITELY.
 *
 * \param int $ParentProject Parents project_fk
 * \param int $Depth  Tree depth to create
 * \param bool $IncludeTop  True to include fossology root project
 * \param int $SelectId project_fk of selected project
 * \param bool $linkParent If true, the option tag will have $OldParent and
 * $ParentProject as the value
 * \param int $OldParent Parent of the parent project
 *
 * \return HTML of the project tree
 */
function ProjectListOption($ParentProject,$Depth, $IncludeTop=1, $SelectId=-1, $linkParent=false, $OldParent=0)
{
  if ($ParentProject == "-1") {
    $ParentProject = ProjectGetTop();
  }
  if (empty($ParentProject)) {
    return;
  }
  global $PG_CONN;
  if (empty($PG_CONN)) {
    return;
  }
  $V = "";

  if (($Depth != 0) || $IncludeTop) {
    if ($ParentProject == $SelectId) {
      $V .= "<option value='$ParentProject' SELECTED>";
    } elseif ($linkParent) {
      if (empty($OldParent)) {
        $OldParent = 0;
      }
      $V .= "<option value='$OldParent $ParentProject'>";
    } else {
      $V .= "<option value='$ParentProject'>";
    }
    if ($Depth != 0) {
      $V .= "&nbsp;&nbsp;";
    }
    for ($i=1; $i < $Depth; $i++) {
      $V .= "&nbsp;&nbsp;";
    }

    /* Load this project's name */
    $sql = "SELECT project_name FROM project WHERE project_pk=$ParentProject LIMIT 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $Name = trim($row['project_name']);
    if ($Name == "") {
      $Name = "[default]";
    }

    /* Load any subprojects */
    /* Now create the HTML */
    $V .= htmlentities($Name);
    $V .= "</option>\n";
  }
  /* Load any subprojects */
  $sql = "SELECT project.project_pk, project.project_name AS name,
            project.project_desc AS description,
            projectcontents.parent_fk AS parent,
            projectcontents.projectcontents_mode,
            NULL AS ts, NULL AS upload_pk, NULL AS pfile_fk, NULL AS ufile_mode
            FROM project, projectcontents
            WHERE projectcontents.projectcontents_mode = ".ProjectDao::MODE_PROJECT."
            AND projectcontents.parent_fk =$ParentProject
            AND projectcontents.child_id = project.project_pk
            AND project.project_pk is not null
            ORDER BY name";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  if (pg_num_rows($result) > 0) {
    $Hide = "";
    if ($Depth > 0) {
      $Hide = "style='display:none;'";
    }
    while ($row = pg_fetch_assoc($result)) {
      $V .= ProjectListOption($row['project_pk'], $Depth+1,$IncludeTop,$SelectId,$linkParent,$row['parent']);
    }
  }
  pg_free_result($result);
  return($V);
} // ProjectListOption()

/**
 * \brief Given a project_pk, return the full path to this project.
 * \note This is recursive!
 * \note If there is a recursive loop in the project table, then
 * this will loop INFINITELY.
 *
 * \param int $ProjectPk Project id
 * \param int $Top Optional, default is user's top project. project_pk of top of desired path.
 *
 * \return string full path of this project
 */
function ProjectGetName($ProjectPk,$Top=-1)
{
  global $PG_CONN;
  if ($Top == -1) {
    $Top = ProjectGetTop();
  }
  $sql = "SELECT project_name,projectcontents.parent_fk FROM project
	LEFT JOIN projectcontents ON projectcontents_mode = ".ProjectDao::MODE_PROJECT."
	AND child_id = '$ProjectPk'
	WHERE project_pk = '$ProjectPk'
	LIMIT 1;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  $Parent = $row['parent_fk'];
  $Name = $row['project_name'];
  if (! empty($Parent) && ($ProjectPk != $Top)) {
    $Name = ProjectGetName($Parent,$Top) . "/" . $Name;
  }
  return($Name);
}


/**
 * \brief DEPRECATED! Given an upload number, return the
 * project path in an array containing project_pk and name.
 * \note This is recursive!
 * \note If there is a recursive loop in the project table, then
 * this will loop INFINITELY.
 * \deprecated Use Project2Path() and GetProjectFromItem()
 */
function ProjectGetFromUpload($Uploadpk, $Project = -1, $Stop = -1)
{
  global $PG_CONN;
  if (empty($PG_CONN)) {
    return;
  }
  if (empty($Uploadpk)) {
    return;
  }
  if ($Stop == - 1) {
    $Stop = ProjectGetTop();
  }
  if ($Project == $Stop) {
    return;
  }

  $sql = "";
  $Parm = "";
  if ($Project < 0) {
    /* Mode 2 means child_id is an upload_pk */
    $Parm = $Uploadpk;
    $sql = "SELECT projectcontents.parent_fk,project_name FROM projectcontents
              INNER JOIN project ON projectcontents.parent_fk = project.project_pk
			  AND projectcontents.projectcontents_mode = " . ProjectDao::MODE_UPLOAD."
			  WHERE projectcontents.child_id = $Parm LIMIT 1;";
  } else {
    /* Mode 1 means child_id is a project_pk */
    $Parm = $Project;
    $sql = "SELECT projectcontents.parent_fk,project_name FROM projectcontents
			  INNER JOIN project ON projectcontents.parent_fk = project.project_pk
			  AND projectcontents.projectcontents_mode = 1
			  WHERE projectcontents.child_id = $Parm LIMIT 1;";
  }
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $R = pg_fetch_assoc($result);
  if (empty($R['parent_fk'])) {
    pg_free_result($result);
    return;
  }
  $V = array();
  $V['project_pk'] = $R['parent_fk'];
  $V['project_name'] = $R['project_name'];
  if ($R['parent_fk'] != 0) {
    $List = ProjectGetFromUpload($Uploadpk, $R['parent_fk'],$Stop);
  }
  if (empty($List)) {
    $List = array();
  }
  array_push($List,$V);
  pg_free_result($result);
  return($List);
} // ProjectGetFromUpload()


/**
 * \brief Returns an array of uploads in a project.
 *
 *  Only uploads for which the user has permission >= $perm are returned.
 *  This does NOT recurse.
 *  The returned array is sorted by ufile_name and upload_pk.
 * \param int $ParentProject Optional project_pk, default is users root project.
 * \param int $perm Minimum permission
 * \return `array{upload_pk, upload_desc, upload_ts, ufile_name}`
 *  for all uploads in a given project.
 *
 */
function ProjectListUploads_perm($ParentProject, $perm)
{
  global $PG_CONN;

  if (empty($PG_CONN)) {
    return;
  }
  if (empty($ParentProject)) {
    return;
  }
  if ($ParentProject == "-1") {
    $ParentProject = GetUserRootProject();
  }
  $groupId = Auth::getGroupId();
  /* @var $uploadDao UploadDao */
  $uploadDao = $GLOBALS['container']->get('dao.upload');
  $List=array();

  /* Get list of uploads under $ParentProject */
  /* mode 2 = upload_fk */
  $sql = "SELECT upload_pk, upload_desc, upload_ts, upload_filename
	FROM projectcontents,upload
  INNER JOIN uploadtree ON upload_fk = upload_pk AND upload.pfile_fk = uploadtree.pfile_fk AND parent IS NULL AND lft IS NOT NULL
	WHERE projectcontents.parent_fk = '$ParentProject'
	AND projectcontents.projectcontents_mode = ".ProjectDao::MODE_UPLOAD."
	AND projectcontents.child_id = upload.upload_pk
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
    array_push($List,$New);
  }
  pg_free_result($result);
  return($List);
} // ProjectListUploads_perm()

/**
 * @brief Get uploads and project info, starting from $ParentProject.
 *
 * The array is sorted by project and upload name.
 * Projects that are empty do not show up.
 * \note This is recursive!
 * \note If there is a recursive loop in the project table, then
 * this will loop INFINITELY.
 *
 * @param int $ParentProject project_pk, -1 for users root project
 * @param string $ProjectPath Used for recursion, caller should not specify.
 * @param Auth::PERM_READ|Auth::PERM_WRITE $perm Permission required
 * @return array of `{upload_pk, upload_desc, name, project}`
 */
function ProjectListUploadsRecurse($ParentProject=-1, $ProjectPath = '',
  $perm = Auth::PERM_READ)
{
  global $PG_CONN;
  if (empty($PG_CONN)) {
    return array();
  }
  if (empty($ParentProject)) {
    return array();
  }
  if ($perm != Auth::PERM_READ && $perm = Auth::PERM_WRITE) {
    return array();
  }
  if ($ParentProject == "-1") {
    $ParentProject = ProjectGetTop();
  }
  $groupId = Auth::getGroupId();
  /* @var $uploadDao UploadDao */
  $uploadDao = $GLOBALS['container']->get('dao.upload');
  $List=array();

  /* Get list of uploads */
  /* mode 1<<1 = upload_fk */
  $sql = "SELECT upload_pk, upload_desc, ufile_name, project_name FROM project,projectcontents,uploadtree, upload
    WHERE
        projectcontents.parent_fk = '$ParentProject'
    AND projectcontents.projectcontents_mode = ". ProjectDao::MODE_UPLOAD ."
    AND projectcontents.child_id = upload.upload_pk
    AND project.project_pk = $ParentProject
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
    $New['project'] = $ProjectPath . "/" . $R['project_name'];
    array_push($List,$New);
  }
  pg_free_result($result);

  /* Get list of subprojects and recurse */
  /* mode 1<<0 = project_pk */
  $sql = "SELECT A.child_id AS id,B.project_name AS project,B.project_name AS subproject
	FROM projectcontents AS A
	INNER JOIN project AS B ON A.parent_fk = B.project_pk
	AND A.projectcontents_mode = ". ProjectDao::MODE_PROJECT ."
	AND A.parent_fk = '$ParentProject'
  AND B.project_pk = $ParentProject
	ORDER BY B.project_name;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  while ($R = pg_fetch_assoc($result)) {
    if (empty($R['id'])) {
      continue;
    }
    /* RECURSE! */
    $SubList = ProjectListUploadsRecurse($R['id'], $ProjectPath . "/" . $R['project'], $perm);
    $List = array_merge($List,$SubList);
  }
  pg_free_result($result);
  /* Return findings */
  return($List);
} // ProjectListUploadsRecurse()


/**
 * \brief Get an array of all the projects from a $RootProject on down.
 *
 * Recursive. This is typically used to build a select list of project names.
 *
 * \param int $RootProject Default is entire software repository
 * \param[out] array $ProjectArray Returned array of project_pk=>project_name's
 *
 * \return $ProjectArray of `{project_pk=>project_name, project_pk=>project_name, ...}`
 * in project order.
 * If no projects are in the list, an empty array is returned.
 *
 * \todo Possibly this could be a common function and ProjectListOption() could
 *       use this for its data.  In general data collection and data formatting
 *       should be separate functions.
 */
function GetProjectArray($RootProject, &$ProjectArray)
{
  global $PG_CONN;

  if ($RootProject == "-1") {
    $RootProject = ProjectGetTop();
  }
  if (empty($RootProject)) {
    return $ProjectArray;
  }

  /* Load this project's name */
  $sql = "SELECT project_name, project_pk FROM project WHERE project_pk=$RootProject LIMIT 1;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);

  $Name = trim($row['project_name']);
  $ProjectArray[$row['project_pk']] = $row['project_name'];

  /* Load any subprojects */
  $sql = "SELECT project.project_pk, project.project_name,
            projectcontents.parent_fk
            FROM project, projectcontents
            WHERE projectcontents.projectcontents_mode = ".ProjectDao::MODE_PROJECT."
            AND projectcontents.parent_fk =$RootProject
            AND projectcontents.child_id = project.project_pk
            AND project.project_pk is not null
            ORDER BY project_name";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  if (pg_num_rows($result) > 0) {
    while ($row = pg_fetch_assoc($result)) {
      GetProjectArray($row['project_pk'], $ProjectArray);
    }
  }
  pg_free_result($result);
}

// /**
//  * \brief Check if one file path contains an excluding text
//  *
//  * \param string $FilePath File path
//  * \param string $ExcludingText Excluding text
//  *
//  * \return 1: include, 0: not include
//  */
// function ContainExcludeString($FilePath, $ExcludingText)
// {
//   $excluding_length = 0;
//   $excluding_flag = 0; // 1: exclude 0: not exclude
//   if ($ExcludingText) {
//     $excluding_length = strlen($ExcludingText);
//   }

//   /* filepath contains 'xxxx/', '/xxxx/', 'xxxx', '/xxxx' */
//   if ($excluding_length > 0 && strstr($FilePath, $ExcludingText)) {
//     $excluding_flag = 1;
//     /* filepath does not contain 'xxxx/' */
//     if ('/' != $ExcludingText[0] && '/' == $ExcludingText[$excluding_length - 1] &&
//       ! strstr($FilePath, '/'.$ExcludingText)) {
//       $excluding_flag = 0;
//     }
//   }
//   return $excluding_flag;
// }
