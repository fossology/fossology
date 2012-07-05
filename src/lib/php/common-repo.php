<?php
/***********************************************************
 Copyright (C) 2008-2012 Hewlett-Packard Development Company, L.P.

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
 * \file common-repo.php
 * \brief This file contains common repository functions.
 */

/**
 * \brief Given an uploadtree_pk, return a string that describes
 * the mime type.  Note this only looks in the pfile rec.  For some mimetypes
 * unpack initializes the pfile mimetype.  Others require the mimetype agent.
 * (This is in common-repo since mimetypes apply to repo contents.)
 *
 * \param $Item - uploadtree pk
 *
 * \return string that describes the mime type.
 */
function GetMimeType($Item)
{
  global $PG_CONN;

  $Sql = "SELECT mimetype_name
	FROM uploadtree
	INNER JOIN pfile ON pfile_pk = pfile_fk
	INNER JOIN mimetype ON pfile.pfile_mimetypefk = mimetype.mimetype_pk
	WHERE uploadtree_pk = $Item LIMIT 1;";
  $result = pg_query($PG_CONN, $Sql);
  DBCheckResult($result, $Sql, __FILE__, __LINE__);
  if (pg_num_rows($result) > 0)
  {
    $row = pg_fetch_assoc($result);
    $Meta = $row['mimetype_name'];
  }
  else
    $Meta = 'application/octet-stream'; 

  pg_free_result($result);
  return($Meta);
} /* GetMimeType() */

/**
 * \brief Given a pfile id, retrieve the pfile path.
 * 
 * NOTE: The filename at the path may not exist.
 * In fact, the entire path may not exist!
 *
 * \param $PfilePk - pfile pk
 * \param $Repo - repository type
 *
 * \return the path, or NULL if the pfile record does not exist.
 */
function RepPath($PfilePk, $Repo="files")
{
  global $Plugins;
  global $LIBEXECDIR;
  global $PG_CONN;
  if (empty($PG_CONN)) { return; }

  $sql = "SELECT * FROM pfile WHERE pfile_pk = $PfilePk LIMIT 1;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $Row = pg_fetch_assoc($result);
  pg_free_result($result);
  if (empty($Row['pfile_sha1'])) { return(NULL); }
  $Hash = $Row['pfile_sha1'] . "." . $Row['pfile_md5'] . "." . $Row['pfile_size'];
  exec("$LIBEXECDIR/reppath $Repo $Hash", $Path);
  return($Path[0]);
} // RepPath()

/**
 * \brief Given an uploadtree_pk, retrieve the pfile path.
 *
 * NOTE: The filename at the path may not exist.
 * In fact, the entire path may not exist!
 *
 * \param $Item - uploadtree pk
 * \param $Repo - repository type
 *
 * \return the path, or NULL if the pfile record does not exist.
 */
function RepPathItem($Item, $Repo="files")
{
  global $Plugins;
  global $LIBEXECDIR;
  global $PG_CONN;
  if (empty($PG_CONN)) { return; }

  $sql = "SELECT * FROM pfile INNER JOIN uploadtree ON pfile_fk = pfile_pk
	  WHERE uploadtree_pk = $Item LIMIT 1;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $Row = pg_fetch_assoc($result);
  pg_free_result($result);
  if (empty($Row['pfile_sha1'])) { return(NULL); }
  $Hash = $Row['pfile_sha1'] . "." . $Row['pfile_md5'] . "." . $Row['pfile_size'];
  exec("$LIBEXECDIR/reppath $Repo $Hash", $Path);
  return($Path[0]);
} // RepPathItem()

?>
