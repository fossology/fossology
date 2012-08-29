<?php
/***********************************************************
 Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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
 * \file common-copyright-file.php
 * \brief This file contains common functions for getting copyright information
 */

/**
 * \brief get all the copyright for a single file or uploadtree
 * 
 * \param $agent_pk - agent id
 * \param $pfile_pk - pfile id, (if empty, $uploadtree_pk must be given)
 * \param $uploadtree_pk - (used only if $pfile_pk is empty)
 * \param $type - copyright statement/url/email
 * 
 * \return Array of file copyright CopyrightArray[ct_pk] = copyright.content
 * FATAL if neither pfile_pk or uploadtree_pk were passed in
 */
function GetFileCopyrights($agent_pk, $pfile_pk, $uploadtree_pk, $type)
{
  global $PG_CONN;

  if (empty($agent_pk)) Fatal("Missing parameter: agent_pk", __FILE__, __LINE__);

  // if $pfile_pk, then return the copyright for that one file
  if ($pfile_pk)
  {
    $sql = "SELECT ct_pk, content 
              from copyright
              where pfile_fk='$pfile_pk' and agent_fk=$agent_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
  }
  else if ($uploadtree_pk)
  {
    // Find lft and rgt bounds for this $uploadtree_pk 
    $sql = "SELECT lft, rgt, upload_fk FROM uploadtree
                   WHERE uploadtree_pk = $uploadtree_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $lft = $row["lft"];
    $rgt = $row["rgt"];
    $upload_pk = $row["upload_fk"];
    pg_free_result($result);

    $typesql = '';
    if ($type && "all" != $type) $typesql = "and type = '$type'";

    //  Get the copyright under this $uploadtree_pk
    $sql = "SELECT ct_pk, content from copyright ,
                  (SELECT distinct(pfile_fk) as PF from uploadtree
                     where upload_fk=$upload_pk
                       and uploadtree.lft BETWEEN $lft and $rgt) as SS
              where PF=pfile_fk and agent_fk=$agent_pk $typesql;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
  }
  else Fatal("Missing function inputs", __FILE__, __LINE__);

  $CopyrightArray = array();
  while ($row = pg_fetch_assoc($result))
  {
    $CopyrightArray[$row['ct_pk']] = $row["content"];
  }
  pg_free_result($result);
  return $CopyrightArray;
}

/**
 * \brief  returns copyright list as a single string
 * \param $agent_pk - agent id
 * \param $pfile_pk - pfile id, (if empty, $uploadtree_pk must be given)
 * \param $uploadtree_pk - (used only if $pfile_pk is empty)
 * \param $type - copyright statement/url/email
 *
 * \return copyright string for specified file
 */
function GetFileCopyright_string($agent_pk, $pfile_pk, $uploadtree_pk, $type)
{
  $CopyrightStr = "";
  $CopyrightArray = GetFileCopyrights($agent_pk, $pfile_pk, $uploadtree_pk, $type);
  $first = true;
  foreach($CopyrightArray as $ct)
  {
    if ($first)
    $first = false;
    else
    $CopyrightStr .= " ,";
    $CopyrightStr .= $ct;
  }

  return $CopyrightStr;
}

?>
