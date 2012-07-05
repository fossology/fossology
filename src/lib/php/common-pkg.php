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
 * \file common-pkg.php
 * \brief This file contains common functions for the package agent.
 */

/**
 * \brief Get package mimetype
 *
 * \return:
 *   Array of mimetype_pk's in the following order:
 *     application/x-rpm
 *     application/x-debian-package
 *     application/x-debian-source
 */
function GetPkgMimetypes()
{
  global $PG_CONN;

  $pkArray = array();

  /* Find lft and rgt bounds for this $uploadtree_pk  */
  $sql = "select * from mimetype where
             mimetype_name='application/x-rpm'
             or mimetype_name='application/x-debian-package'
             or mimetype_name='application/x-debian-source'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  while ($row = pg_fetch_assoc($result))
  {
    if ($row['mimetype_name'] == 'application/x-rpm') $pkArray[0] = $row['mimetype_pk'];
    else if ($row['mimetype_name'] == 'application/x-debian-package') $pkArray[1] = $row['mimetype_pk'];
    else if ($row['mimetype_name'] == 'application/x-debian-source') $pkArray[2] = $row['mimetype_pk'];
  }
  pg_free_result($result);
  return $pkArray;
}

/**
 * \brief Increment counts of source package, binary package, and binary with no source
 *
 * \param $uploadtree_row Uploadtree row + pfile_mimetypefk
 * \param  $MimetypeArray  Assoc array of mimetype names and mimetype_pk (from GetPkgMimetypes)
 * \param  &$NumSrcPkgs  Incremented if this is a source package
 * \param  &$NumBinPkgs  Incremented if this is a binary package
 * \param  &$NumBinNoSrcPkgs  Incremented if this binary pkg has no source package
 * \return
 *   None.  This function increments values passed in by reference.
 */
function IncrSrcBinCounts($uploadtree_row, $MimetypeArray,
&$NumSrcPkgs, &$NumBinPkgs, &$NumBinNoSrcPkgs)
{
  global $PG_CONN;

  list($rpm_mtpk, $deb_mtsrcpk, $deb_mtbinpk) = $MimetypeArray;

  /* Debian source pkg? */
  if ($uploadtree_row['pfile_mimetypefk'] == $deb_mtsrcpk)
  {
    $NumSrcPkgs++;
    return;
  }

  /* Debian binary pkg? */
  if ($uploadtree_row['pfile_mimetypefk'] == $deb_mtbinpk)
  {
    $NumBinPkgs++;
    $srcpkgmt = $deb_mtsrcpk;

    /* Is the source package present in this upload? */
    /* First, find the source pkg name, there should only be 1 row */
    $sql = "select source from pkg_deb where pfile_fk=$uploadtree_row[pfile_fk] limit 1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $source = $row['source'];
    pg_free_result($result);
  }

  /* RPM pkg? */
  /* RPM mimetypes don't distinguish between source and binary.  So we have
   * to look at the package data (source_rpm).  If source_rpm is not empty
   * then we are looking at a binary rpm.  If source_rpm is empty, then
   * we are looking at a source rpm.
   */
  if ($uploadtree_row['pfile_mimetypefk'] == $rpm_mtpk)
  {
    $srcpkgmt = $rpm_mtpk;
    /* Is this a source or binary rpm? */
    $sql = "select source_rpm from pkg_rpm where pfile_fk=$uploadtree_row[pfile_fk] limit 1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $source = $row['source_rpm'];
    pg_free_result($result);
    if ((substr($source,0,6) == "(none)") || empty($source))
    {
      $NumSrcPkgs++;
      return;
    }
    else
    {
      $NumBinPkgs++;
    }
  }

  /* If $source is empty, then this isn't even a package */
  if (empty($source)) return;

  /* To get here we must be looking at a binary package */
  /* Find the source pkg in this upload */
  $source = trim($source);
  $sql = "select uploadtree_pk from uploadtree, pfile where
            upload_fk=$uploadtree_row[upload_fk] and ufile_name='$source' 
            and pfile_fk=pfile_pk and pfile_mimetypefk=$srcpkgmt limit 1";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  if (pg_num_rows($result) == 0) $NumBinNoSrcPkgs++;
  pg_free_result($result);
  return;
}

?>
