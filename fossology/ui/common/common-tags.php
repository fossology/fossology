<?php
/***********************************************************
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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

/* GetAllTags(): Returns an array of:
   tag_pk
   tag_name
 for all tags in a given uploadtree_pk.
 This does NOT recurse.
 ***********************************************************/
function GetAllTags($Item)
  {
  global $DB;
  if (empty($DB)) { return; }
  if (empty($Item)) { return; }
  $List=array();

  /* Get list of tags */
  $SQL = "SELECT tag_pk, tag FROM tag, tag_file, uploadtree WHERE tag.tag_pk = tag_file.tag_fk AND tag_file.pfile_fk = uploadtree.pfile_fk AND uploadtree.uploadtree_pk = $Item UNION SELECT tag_pk, tag FROM tag, tag_uploadtree WHERE tag.tag_pk = tag_uploadtree.tag_fk AND tag_uploadtree.uploadtree_fk = $Item;";
  $Results = $DB->Action($SQL);
  foreach($Results as $R)
    {
    if (empty($R['tag_pk'])) { continue; }
    $New['tag_pk'] = $R['tag_pk'];
    $New['tag_name'] = $R['tag'];
    array_push($List,$New);
    }
  return($List);
  } // GetAllTags()

/* GetTaggingPerms($user_pk, $tag_ns_pk): Returns integer of:
   0  None
   1  Read Only
   2  Read/Write
   3  Admin
 ***********************************************************/
function GetTaggingPerms($user_pk, $tag_ns_pk)
{
  global $DB;
  $perm = 0;

  //if (!$PG_CONN) { $dbok = $DB->db_init(); if (!$dbok) echo "NO DB connection"; }
  if (empty($DB)) { return(0); } /* No DB */
  if(empty($user_pk)){
    return (0);
  }
  $sql = "SELECT * FROM group_user_member WHERE user_fk=$user_pk;";
  $result = $DB->Action($sql);
  if (count($result) > 0)
  {
    foreach ($result as $row)
    {
      $group_pk = $row['group_fk'];
      if (isset($tag_ns_pk))
        $sql = "SELECT * FROM tag_ns_group WHERE tag_ns_fk=$tag_ns_pk AND group_fk=$group_pk;";
      else
        $sql = "SELECT * FROM tag_ns_group WHERE group_fk=$group_pk;";
      $result1 = $DB->Action($sql);
      if (count($result1) > 0)
      {
        foreach ($result1 as $row1)
        {
          if ($row1['tag_ns_fk'] == $tag_ns_pk)
          {
            return ($row1['tag_ns_perm']);
          }else{
            $temp = $row1['tag_ns_perm'];
            if ($temp > $perm) {$perm = $temp;}
          }
        }
      }
    }
    return ($perm);
  }else{
    return (0);
  }
}

/*****************************************
 Array2SingleSelectTag: Build a single choice select pulldown for tagging

 Params:
   $KeyValArray   Assoc array.  Use key/val pairs for list
   $SLName        Select list name (default is "unnamed")
   $SelectedVal   Initially selected value or key, depends
                  on $SelElt
   $FirstEmpty    True if the list starts off with an empty choice
                  (default is false)
   $SelElt        True (default) if $SelectedVal is a value
                  False if $SelectedVal is a key
 *****************************************/
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
