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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

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
  $SQL = "SELECT tag_pk, tag FROM tag, tag_file, uploadtree WHERE tag.tag_pk = tag_file.tag_fk AND tag_file.pfile_fk = uploadtree.pfile_fk AND uploadtree.uploadtree_pk = $Item;";
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
  global $DB,$PG_CONN;
  $perm = 0;

  if (!$PG_CONN) { $dbok = $DB->db_init(); if (!$dbok) echo "NO DB connection"; }
  
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
      $sql = "SELECT * FROM tag_ns_group WHERE group_fk=$group_pk;";
      $result1 = pg_query($PG_CONN, $sql);
      DBCheckResult($result1, $sql, __FILE__, __LINE__);
      if (pg_num_rows($result1) > 0){
        while ($row1 = pg_fetch_assoc($result1)){
          if ($row1['tag_ns_fk'] == $tag_ns_pk){
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
