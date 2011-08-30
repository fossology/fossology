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
 * \brief GetAllTags()
 *
 * \param $Item the uploadtree_pk
 *
 * \return an array of:
 *  tag_pk
 *  tag_name
 */
function GetAllTags($Item)
{
  global $PG_CONN;
  if (empty($PG_CONN)) { return; }
  if (empty($Item)) { return; }
  $List=array();

  /* Get list of tags */
  $sql = "SELECT tag_pk, tag FROM tag, tag_file, uploadtree WHERE tag.tag_pk = tag_file.tag_fk AND tag_file.pfile_fk = uploadtree.pfile_fk AND uploadtree.uploadtree_pk = $Item UNION SELECT tag_pk, tag FROM tag, tag_uploadtree WHERE tag.tag_pk = tag_uploadtree.tag_fk AND tag_uploadtree.uploadtree_fk = $Item;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  while ($R = pg_fetch_assoc($result))
  {
    if (empty($R['tag_pk'])) { continue; }
    $New['tag_pk'] = $R['tag_pk'];
    $New['tag_name'] = $R['tag'];
    array_push($List,$New);
  }
  pg_free_result($result);
  return($List);
} // GetAllTags()

/**
 * \brief GetTaggingPerms: Get tags permissions
 *
 * \param $user_pk
 * \param $tag_ns_pk the tag namespace pk
 *
 * \return integer of:
 *  0  None
 *  1  Read Only
 *  2  Read/Write
 *  3  Admin
 */
function GetTaggingPerms($user_pk, $tag_ns_pk)
{
  global $PG_CONN;
  $perm = 0;

  //if (!$PG_CONN) { $dbok = $DB->db_init(); if (!$dbok) echo "NO DB connection"; }
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
 * \brief Array2SingleSelectTag: Build a single choice select pulldown for tagging
 *
 * \param $KeyValArray   Assoc array.  Use key/val pairs for list
 * \param $SLName        Select list name (default is "unnamed")
 * \param $SelectedVal   Initially selected value or key, depends on $SelElt
 * \param $FirstEmpty    True if the list starts off with an empty choice (default is false)
 * \param $SelElt        True (default) if $SelectedVal is a value False if $SelectedVal is a key
 *
 *\return string of html select
 */
function Array2SingleSelectTag($KeyValArray, $SLName="unnamed", $SelectedVal= "",
$FirstEmpty=false, $SelElt=true)
{
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
?>
