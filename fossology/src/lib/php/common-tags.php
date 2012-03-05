<?php
/***********************************************************
 Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.

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

/**
 * \file common-tags.php
 * \brief common function of tag
 */


/**
 * \brief Get all Tags of this unploadtree_pk.
 *
 * \param $Item the uploadtree_pk
 * \param $Recurse boolean, to recurse or not
 *
 * \return an array of: ag_pk and tag_name
 */
function GetAllTags($Item, $Recurse=true)
{
  global $PG_CONN;
  if (empty($PG_CONN)) { return; }
  if (empty($Item)) { return; }
  $List=array();

  if ($Recurse)
  {
    /* Get tree boundaries */
    $sql = "select lft,rgt, upload_fk from uploadtree where uploadtree_pk=$Item";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $uploadtree_row = pg_fetch_assoc($result);

    $Condition = " lft>=$uploadtree_row[lft] and rgt<=$uploadtree_row[rgt] ";
    $upload_pk = $uploadtree_row['upload_fk'];
    pg_free_result($result);
  }
  else
  {
    $Condition = "  uploadtree.uploadtree_pk=$Item ";
  }

  /* Get list of unique tag_pk's for this item */
//  $sql = "SELECT distinct(tag_fk) as tag_pk FROM tag_file, uploadtree WHERE tag_file.pfile_fk = uploadtree.pfile_fk and upload_fk=$upload_pk AND $Condition UNION SELECT tag_fk as tag_pk FROM tag_uploadtree WHERE tag_uploadtree.uploadtree_fk = $Item";
  $sql = "select distinct(tag_fk) as tag_pk from uploadtree_tag_file_inner where upload_fk='$upload_pk' and $Condition UNION select tag_fk as tag_pk from tag_uploadtree where tag_uploadtree.uploadtree_fk='$Item'";
  $result = pg_query($PG_CONN, $sql);
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
 * \brief Get tags permissions
 *
 * \param $user_pk
 * \param $tag_ns_pk the tag namespace pk
 *
 * \return integer of:
 *  - 0  None
 *  - 1  Read Only
 *  - 2  Read/Write
 *  - 3  Admin
 */
function GetTaggingPerms($user_pk, $tag_ns_pk)
{
  global $PG_CONN;
  $perm = 0;

  if (empty($PG_CONN)) { return(0); } /* No DB */
  if(empty($user_pk)){
    return (0);
  }
  $sql = "SELECT * FROM group_user_member WHERE user_fk=$user_pk;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  if (pg_num_rows($result) > 0)
  {
    while ($row = pg_fetch_assoc($result))
    {
      $group_pk = $row['group_fk'];
      if (isset($tag_ns_pk))
        $sql = "SELECT * FROM tag_ns_group WHERE tag_ns_fk=$tag_ns_pk AND group_fk=$group_pk;";
      else
        $sql = "SELECT * FROM tag_ns_group WHERE group_fk=$group_pk;";
      $result1 = pg_query($PG_CONN, $sql);
      DBCheckResult($result1, $sql, __FILE__, __LINE__);
      if (pg_num_rows($result1) > 0)
      {
        while ($row1 = pg_fetch_assoc($result1))
        {
          if ($row1['tag_ns_fk'] == $tag_ns_pk)
          {
            pg_free_result($result1);
            return ($row1['tag_ns_perm']);
          }else{
            $temp = $row1['tag_ns_perm'];
            if ($temp > $perm) {$perm = $temp;}
          }
        }
      }
      pg_free_result($result1);
    }
    pg_free_result($result);
    return ($perm);
  }else{
    pg_free_result($result);
    return (0);
  }
}


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
  $sql = "select lft,rgt from uploadtree where uploadtree_pk=$Item";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $uploadtree_row = pg_fetch_assoc($result);

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
 *
 *\return none
 */
function TagFilter(&$UploadtreeRows, $tag_pk)
{
  foreach ($UploadtreeRows as $key=>$UploadtreeRow)
  {
    $found = false;
    $tags = GetAllTags($UploadtreeRow["uploadtree_pk"], true);
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
?>
