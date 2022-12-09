<?php
/*
 SPDX-FileCopyrightText: © 2008-2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only
*/

/**
 * \file
 * \brief This file contains common repository functions.
 */

/**
 * \brief Given an uploadtree_pk, return a string that describes
 * the mime type.
 *
 * \note this only looks in the pfile rec.  For some mimetypes
 * unpack initializes the pfile mimetype.  Others require the mimetype agent.
 * (This is in common-repo since mimetypes apply to repo contents.)
 *
 * \param int $Item Uploadtree pk
 *
 * \return String that describes the mime type.
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
  if (pg_num_rows($result) > 0) {
    $row = pg_fetch_assoc($result);
    $Meta = $row['mimetype_name'];
  } else {
    $Meta = 'application/octet-stream';
  }

  pg_free_result($result);
  return($Meta);
} /* GetMimeType() */

/**
 * \brief Given a pfile id, retrieve the pfile path.
 *
 * \note The filename at the path may not exist.
 * In fact, the entire path may not exist!
 *
 * \param int $PfilePk Pfile pk
 * \param string $Repo Repository type
 *
 * \return The path, or NULL if the pfile record does not exist.
 */
function RepPath($PfilePk, $Repo="files")
{
  global $Plugins;
  global $LIBEXECDIR;
  global $PG_CONN;
  if (empty($PG_CONN)) {
    return;
  }

  $sql = "SELECT * FROM pfile WHERE pfile_pk = $PfilePk LIMIT 1;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $Row = pg_fetch_assoc($result);
  pg_free_result($result);
  if (empty($Row['pfile_sha1'])) {
    return (null);
  }
  $Hash = $Row['pfile_sha1'] . "." . $Row['pfile_md5'] . "." . $Row['pfile_size'];
  exec("$LIBEXECDIR/reppath $Repo $Hash", $Path);
  return($Path[0]);
} // RepPath()

/**
 * \brief Given an uploadtree_pk, retrieve the pfile path.
 *
 * \note The filename at the path may not exist.
 * In fact, the entire path may not exist!
 *
 * \param int $Item    Uploadtree pk
 * \param string $Repo Repository type
 *
 * \return The path, or NULL if the pfile record does not exist.
 */
function RepPathItem($Item, $Repo="files")
{
  global $LIBEXECDIR;
  global $PG_CONN;
  if (empty($PG_CONN)) {
    return;
  }

  $sql = "SELECT * FROM pfile INNER JOIN uploadtree ON pfile_fk = pfile_pk
	  WHERE uploadtree_pk = $Item LIMIT 1;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $Row = pg_fetch_assoc($result);
  pg_free_result($result);
  if (empty($Row['pfile_sha1'])) {
    return (null);
  }
  $Hash = $Row['pfile_sha1'] . "." . $Row['pfile_md5'] . "." . $Row['pfile_size'];
  exec("$LIBEXECDIR/reppath $Repo $Hash", $Path);
  return($Path[0]);
}
