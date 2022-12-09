<?php
/*
 SPDX-FileCopyrightText: © 2010-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;

/**
 * \file
 * \brief Common functions for tag
 */


/**
 * \brief Get all Tags of this uploadtree_pk.
 *
 * \param int $Item The uploadtree_pk
 * \param bool $Recurse To recurse or not
 * \param string $uploadtree_tablename
 *
 * \return An array of: tag_pk and tag_name; return empty array: disable tagging on this upload
 */
function GetAllTags($Item, $Recurse=true, $uploadtree_tablename="uploadtree")
{
  if (empty($Item)) {
    return array();
  }

  global $container;

  $dbManager = $container->get('db.manager');

  $stmt = __METHOD__.".$uploadtree_tablename";
  $sql = "select true from tag_manage, $uploadtree_tablename u where is_disabled = true and tag_manage.upload_fk = u.upload_fk and u.uploadtree_pk = $1";
  $tagDisabled = $dbManager->getSingleRow($sql, array($Item), $stmt);
  if ($tagDisabled !== false) {
    return array();
  }

  $stmt2 = $stmt.'.lftRgt';
  $sql = "select lft,rgt, upload_fk from $uploadtree_tablename where uploadtree_pk=$1";
  $uploadtree_row = $dbManager->getSingleRow($sql,array($Item), $stmt2);

  $params = array($Item, $uploadtree_row['upload_fk']);
  if ($Recurse) {
    $Condition = " lft between $3 and $4 ";
    $stmt .= ".recurse";
    $params[] = $uploadtree_row['lft'];
    $params[] = $uploadtree_row['rgt'];
  } else {
    $Condition = " uploadtree.uploadtree_pk=$1 ";
  }

  /* Get list of unique tag_pk's for this item */
  $sql = "SELECT distinct(tag_fk) as tag_pk FROM tag_file, $uploadtree_tablename WHERE tag_file.pfile_fk = {$uploadtree_tablename}.pfile_fk and upload_fk=$2 AND $Condition UNION SELECT tag_fk as tag_pk FROM tag_uploadtree WHERE tag_uploadtree.uploadtree_fk = $1";

  $stmt1 = $stmt.'.theTags';
  $dbManager->prepare($stmt1,"select tag.tag AS tag_name, tag.tag_pk from tag,($sql) subquery where tag.tag_pk=subquery.tag_pk group by tag.tag_pk, tag.tag");
  $res = $dbManager->execute($stmt1,$params);
  $List = $dbManager->fetchAll($res);
  $dbManager->freeResult($res);

  return($List);
} // GetAllTags()


/**
 * \brief Build a single choice select pull-down for tagging
 *
 * \param array  $KeyValArray Assoc array. Use key/val pairs for list
 * \param string $SLName      Select list name (default is "unnamed")
 * \param string $SelectedVal Initially selected value or key, depends on
 * $SelElt
 * \param bool   $FirstEmpty  True if the list starts off with an empty choice
 * (default is false)
 * \param bool   $SelElt      True (default) if $SelectedVal is a value False
 * if $SelectedVal is a key
 * \param string $Options     Optional select element options
 *
 *\return String of HTML select
 */
function Array2SingleSelectTag($KeyValArray, $SLName="unnamed", $SelectedVal= "",
$FirstEmpty=false, $SelElt=true, $Options="")
{
  $str ="\n<select name='$SLName' $Options>\n";
  if ($FirstEmpty) {
    $str .= "<option value='' > \n";
  }
  foreach ($KeyValArray as $key => $val) {
    if ($SelElt == true) {
      $SELECTED = ($val == $SelectedVal) ? "SELECTED" : "";
    } else {
      $SELECTED = ($key == $SelectedVal) ? "SELECTED" : "";
    }
    /** @todo GetTaggingPerms is commented out due to bug in it **/
    //    $perm = GetTaggingPerms($_SESSION['UserId'],$key);
    //    if ($perm > 1) {
      $str .= "<option value='$key' $SELECTED>$val\n";
    //    }
  }
  $str .= "</select>";
  return $str;
}

/**
 * \brief Build a single choice select pulldown for the user to select
 *        both a tag.
 *
 * \param string $SL_Name       Select list name (default is "unnamed")
 * \param string $SL_ID         Select list ID (default is $SL_Name)
 * \param bool   $SelectedVal   Initially selected value or key, depends on $SelElt
 * \param bool   $FirstEmpty    True if the list starts off with an empty choice (default is false)
 *
 *\return String of html select
 */
function TagSelect($SLName="unnamed", $SelectedVal= "",
                   $FirstEmpty=false, $SelElt=true)
{
  /* Find all the tag namespaces for this user */
  /*  UNUSED
  $sql = "select lft,rgt from uploadtree where uploadtree_pk=$Item";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $uploadtree_row = pg_fetch_assoc($result);
  */

  /* Find all the tags for this namespace */

  $str ="\n<select name='$SLName'>\n";
  if ($FirstEmpty) {
    $str .= "<option value='' > \n";
  }
  foreach ($KeyValArray as $key => $val) {
    if ($SelElt == true) {
      $SELECTED = ($val == $SelectedVal) ? "SELECTED" : "";
    } else {
      $SELECTED = ($key == $SelectedVal) ? "SELECTED" : "";
    }
    $perm = GetTaggingPerms($_SESSION['UserId'],$key);
    if ($perm > 1) {
      $str .= "<option value='$key' $SELECTED>$val\n";
    }
  }
  $str .= "</select>";
  return $str;
}

/**
 * \brief Given a list of uploadtree recs, remove recs that do not have $tag_pk.
 *
 * \param[in,out] array &$UploadtreeRows This array may be modified by this function.
 * \param int    $tag_pk
 * \param string $uploadtree_tablename
 */
function TagFilter(&$UploadtreeRows, $tag_pk, $uploadtree_tablename)
{
  foreach ($UploadtreeRows as $key=>$UploadtreeRow) {
    $found = false;
    $tags = GetAllTags($UploadtreeRow["uploadtree_pk"], true, $uploadtree_tablename);
    foreach ($tags as $tagArray) {
      if ($tagArray['tag_pk'] == $tag_pk) {
        $found = true;
        break;
      }
      if ($found) {
        break;
      }
    }
    if ($found == false) {
      unset($UploadtreeRows[$key]);
    }
  }
}

/**
 * \brief Check if tagging on one upload is disabled or not
 *
 * \param int $upload_id Upload id
 *
 * \return 1: enabled; 0: disabled, or no write permission
 */
function TagStatus($upload_id)
{
  global $PG_CONN, $container;
  /** @var UploadDao $uploadDao */
  $uploadDao = $container->get('dao.upload');
  if (!$uploadDao->isEditable($upload_id, Auth::getGroupId())) {
    return 0;
  }

  /* check if this upload has been disabled */
  $sql = "select tag_manage_pk from tag_manage where upload_fk = $upload_id and is_disabled = true;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $count = pg_num_rows($result);
  pg_free_result($result);
  return ($count > 0) ? 0 : 1;
}
