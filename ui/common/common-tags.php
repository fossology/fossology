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
