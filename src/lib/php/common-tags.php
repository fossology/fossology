<?php
/***********************************************************
 Copyright (C) 2010-2012 Hewlett-Packard Development Company, L.P.

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

/**
 * \file common-tags.php
 * \brief common function of tag
 */


/**
 * \brief Get all Tags of this unploadtree_pk.
 *
 * \param $Item the uploadtree_pk
 * \param $Recurse boolean, to recurse or not
 * \param $uploadtree_tablename
 *
 * \return an array of: ag_pk and tag_name; return empty array: disable tagging on this upload
 */
function GetAllTags($Item, $Recurse=true, $uploadtree_tablename)
{
  global $PG_CONN;
  $sql = "select * from tag_manage where is_disabled = true and upload_fk in (select upload_fk from $uploadtree_tablename where uploadtree_pk = $Item);";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);    
  $count = pg_num_rows($result);
  /** check if disable tagging on this upload */
  if ($count > 0)  // yes, return
  {
    return array();
  }

  if (empty($PG_CONN)) { return; }
  if (empty($Item)) { return; }
  $List=array();

  if ($Recurse)
  {
    /* Get tree boundaries */
    $sql = "select lft,rgt, upload_fk from $uploadtree_tablename where uploadtree_pk=$Item";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $uploadtree_row = pg_fetch_assoc($result);

    $Condition = " lft between $uploadtree_row[lft] and $uploadtree_row[rgt] ";
    $upload_pk = $uploadtree_row['upload_fk'];
    pg_free_result($result);
  }
  else
  {
    $Condition = "  uploadtree.uploadtree_pk=$Item ";
  }

  /* Get list of unique tag_pk's for this item */
  $sql = "SELECT distinct(tag_fk) as tag_pk FROM tag_file, $uploadtree_tablename WHERE tag_file.pfile_fk = {$uploadtree_tablename}.pfile_fk and upload_fk=$upload_pk AND $Condition UNION SELECT tag_fk as tag_pk FROM tag_uploadtree WHERE tag_uploadtree.uploadtree_fk = $Item";

  /* simplify (i.e. speed up) for special case of looking at a single file */
//  if (($uploadtree_row['rgt'] - $uploadtree_row['lft']) == 1)
//    $sql = "select distinct(tag_fk) as tag_pk from uploadtree_tag_file_inner where uploadtree_pk='$Item' UNION select tag_fk as tag_pk from tag_uploadtree where tag_uploadtree.uploadtree_fk='$Item'";
//  else
//    $sql = "select distinct(tag_fk) as tag_pk from uploadtree_tag_file_inner where upload_fk='$upload_pk' and $Condition UNION select tag_fk as tag_pk from tag_uploadtree where tag_uploadtree.uploadtree_fk='$Item'";
//$uTime = microtime(true);
  $result = pg_query($PG_CONN, $sql);
//printf( "<br><small>%s Elapsed time: %.2f seconds</small>", $sql, microtime(true) - $uTime); 
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $SeenTag = array();
  while ($TagRow = pg_fetch_assoc($result))
  {
    $tag_pk = $TagRow['tag_pk'];
    if (empty($tag_pk)) continue;
    if (array_key_exists($tag_pk, $SeenTag)) continue;
    $SeenTag[$tag_pk] = 1;  
  }
  pg_free_result($result);

  /* get the tag names */
  foreach ($SeenTag as $tag_pk=>$One)
  {
    $sql = "select tag from tag where tag_pk=$tag_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $TagRow = pg_fetch_assoc($result);

    /** @todo   Ignore any tags the user doesn't have permission to see 
     **/

    $New['tag_pk'] = $tag_pk;
    $New['tag_name'] = $TagRow['tag'];
    array_push($List,$New);

    pg_free_result($result);
  }

  return($List);
} // GetAllTags()


/**
 * \brief Build a single choice select pulldown for tagging
 *
 * \param $KeyValArray   Assoc array.  Use key/val pairs for list
 * \param $SLName        Select list name (default is "unnamed")
 * \param $SelectedVal   Initially selected value or key, depends on $SelElt
 * \param $FirstEmpty    True if the list starts off with an empty choice (default is false)
 * \param $SelElt        True (default) if $SelectedVal is a value False if $SelectedVal is a key
 * \param $Options       Optional select element options
 *
 *\return string of html select
 */
function Array2SingleSelectTag($KeyValArray, $SLName="unnamed", $SelectedVal= "",
$FirstEmpty=false, $SelElt=true, $Options="")
{
  $str ="\n<select name='$SLName' $Options>\n";
  if ($FirstEmpty) $str .= "<option value='' > \n";
  foreach ($KeyValArray as $key => $val)
  {
    if ($SelElt == true)
      $SELECTED = ($val == $SelectedVal) ? "SELECTED" : "";
    else
      $SELECTED = ($key == $SelectedVal) ? "SELECTED" : "";
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
 * \param $SL_Name       Select list name (default is "unnamed")
 * \param $SL_ID         Select list ID (default is $SL_Name)
 * \param $SelectedVal   Initially selected value or key, depends on $SelElt
 * \param $FirstEmpty    True if the list starts off with an empty choice (default is false)
 *
 *\return string of html select
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
  if ($FirstEmpty) $str .= "<option value='' > \n";
  foreach ($KeyValArray as $key => $val)
  {
    if ($SelElt == true)
      $SELECTED = ($val == $SelectedVal) ? "SELECTED" : "";
    else
      $SELECTED = ($key == $SelectedVal) ? "SELECTED" : "";
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
 * \param $UploadtreeRows This array may be modified by this function.
 * \param $tag_pk
 * \param $uploadtree_tablename
 *
 *\return none
 */
function TagFilter(&$UploadtreeRows, $tag_pk, $uploadtree_tablename)
{
  foreach ($UploadtreeRows as $key=>$UploadtreeRow)
  {
    $found = false;
    $tags = GetAllTags($UploadtreeRow["uploadtree_pk"], true, $uploadtree_tablename);
    foreach($tags as $tagArray)
    {
      if ($tagArray['tag_pk'] == $tag_pk) 
      {
        $found = true;
        break;
      }
      if ($found) break;
    }
    if ($found == false) unset($UploadtreeRows[$key]);
  }
}

/**
 * \brief check if tagging on one upload is disabled or not
 * 
 * \param $upload_id - upload id
 * 
 * \return 1: enabled; 0: disabled
 */
function TagStatus($upload_id) 
{
  global $PG_CONN;
  /** check if this upload has been disabled */
  $sql = "select tag_manage_pk from tag_manage where upload_fk = $upload_id and is_disabled = true;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $count = pg_num_rows($result);
  pg_free_result($result);
  if ($count > 0) return 0;
  else return 1;
}
?>
