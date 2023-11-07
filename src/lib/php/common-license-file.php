<?php
/*
 SPDX-FileCopyrightText: © 2009-2014 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: LGPL-2.1-only
*/

/**
 * \file
 * \brief This file contains common functions for the
 * license_file and license_ref tables.
 */


/**
 * \brief get all the licenses for a single file or uploadtree
 *
 * \param int $agent_pk Agent id
 * \param int $pfile_pk Pfile id, (if empty, $uploadtree_pk must be given)
 * \param int $uploadtree_pk (used only if $pfile_pk is empty)
 * \param string $uploadtree_tablename Upload tree table to use
 * \param bool $duplicate Get duplicated licenses or not, if NULL: No, or Yes
 *
 * \return Array of file licenses\n
 * LicArray[fl_pk] = rf_shortname if $duplicate is Not NULL\n
 * LicArray[rf_pk] = rf_shortname if $duplicate is NULL\n
 * FATAL if neither pfile_pk or uploadtree_pk were passed in
 */
function GetFileLicenses($agent, $pfile_pk, $uploadtree_pk, $uploadtree_tablename='uploadtree', $duplicate="")
{
  global $PG_CONN;

  if (empty($agent)) {
    Fatal("Missing parameter: agent", __FILE__, __LINE__);
  }

  if ($uploadtree_pk) {
    /* Find lft and rgt bounds for this $uploadtree_pk */
    $sql = "SELECT lft, rgt, upload_fk FROM $uploadtree_tablename
                   WHERE uploadtree_pk = $uploadtree_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $lft = $row["lft"];
    $rgt = $row["rgt"];
    $upload_pk = $row["upload_fk"];
    pg_free_result($result);

    $agentIdCondition = $agent != "any" ? "and agent_fk=$agent" : "";

    /* Strip out added upload_pk condition if it isn't needed */
    if (($uploadtree_tablename == "uploadtree_a") ||
      ($uploadtree_tablename == "uploadtree")) {
      $UploadClause = "upload_fk=$upload_pk and ";
    } else {
      $UploadClause = "";
    }

    /* Get the licenses under this $uploadtree_pk */
    $sql = "SELECT distinct(rf_shortname) as rf_shortname, rf_pk as rf_fk, fl_pk
              from license_file_ref,
                  (SELECT distinct(pfile_fk) as PF from $uploadtree_tablename
                     where $UploadClause lft BETWEEN $lft and $rgt) as SS
              where PF=pfile_fk $agentIdCondition
              order by rf_shortname asc";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
  } else {
    Fatal("Missing function inputs", __FILE__, __LINE__);
  }

  $LicArray = array();
  if ($duplicate) {
    // get duplicated licenses
    while ($row = pg_fetch_assoc($result)) {
      $LicArray[$row['fl_pk']] = $row['rf_shortname'];
    }
  } else { // do not return duplicated licenses
    while ($row = pg_fetch_assoc($result)) {
      $LicArray[$row['rf_fk']] = $row['rf_shortname'];
    }
  }
  pg_free_result($result);
  return $LicArray;
}

/**
 * \brief Same as GetFileLicenses() but returns license list as a single string
 * \param int $agent_pk Agent id
 * \param int $pfile_pk Pfile id, (if empty, $uploadtree_pk must be given)
 * \param int $uploadtree_pk (used only if $pfile_pk is empty)
 * \param string $uploadtree_tablename
 *
 * \return Licenses string for specified file
 * \see GetFileLicenses()
 */
function GetFileLicenses_string($agent_pk, $pfile_pk, $uploadtree_pk, $uploadtree_tablename='uploadtree')
{
  $LicStr = "";
  $LicArray = GetFileLicenses($agent_pk, $pfile_pk, $uploadtree_pk, $uploadtree_tablename);
  return implode(', ', $LicArray);
}

/**
 * \brief Get files with a given license (shortname).
 *
 * \param int $agent_pk Agent id
 * \param string $rf_shortname Short name of one license, like GPL, APSL, MIT, ...
 * \param int $uploadtree_pk Sets scope of request
 * \param bool $PkgsOnly If true, only list packages, default is false (all files are listed).
 * For now, $PkgsOnly is not yet implemented.
 * \param int $offset Select offset, default is 0
 * \param string $limit Select limit (num rows returned), default is no limit
 * \param string $order SQL order by clause, default is blank
 *                 e.g. `"order by ufile_name asc"`
 * \param int $tag_pk Optional tag_pk. Restrict results to files that have this tag.
 * \param string $uploadtree_tablename
 *
 * \return pg_query result.  See $sql for fields returned.
 *
 * \note Caller should use pg_free_result to free.
 */
function GetFilesWithLicense($agent_pk, $rf_shortname, $uploadtree_pk,
                             $PkgsOnly=false, $offset=0, $limit="ALL",
                             $order="", $tag_pk=null, $uploadtree_tablename="uploadtree")
{
  global $PG_CONN;

  /* Find lft and rgt bounds for this $uploadtree_pk  */
  $sql = "SELECT lft, rgt, upload_fk FROM $uploadtree_tablename WHERE uploadtree_pk = $uploadtree_pk";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  $lft = $row["lft"];
  $rgt = $row["rgt"];
  $upload_pk = $row["upload_fk"];
  pg_free_result($result);

  /* Find rf_pk for rf_shortname.  This will speed up the main query tremendously */
  $pg_shortname = pg_escape_string($rf_shortname);
  $sql = "SELECT rf_pk FROM license_ref WHERE rf_shortname='$pg_shortname'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  $rf_pk = $row["rf_pk"];
  while ($row = pg_fetch_assoc($result)) {
    $rf_pk .= "," . $row["rf_pk"];
  }
  pg_free_result($result);

  if (empty($rf_pk)) {
    return array(); // if when the rf_shortname does not exist
  }

  /* Optional tag restriction */
  if (empty($tag_pk)) {
    $TagTable = "";
    $TagClause = "";
  } else {
    $TagTable = "tag_file,";
    $TagClause = "and PF=tag_file.pfile_fk and tag_fk=$tag_pk";
  }

  $agentCondition = '';
  if (is_array($agent_pk)) {
    $agentCondition = ' AND agent_fk IN (' . implode(',', $agent_pk) . ')';
  } else if ($agent_pk != "any") {
    $agentCondition = "and agent_fk=$agent_pk";
  }

  /* Strip out added upload_pk condition if it isn't needed */
  if (($uploadtree_tablename == "uploadtree_a") || ($uploadtree_tablename == "uploadtree")) {
    $UploadClause = "upload_fk=$upload_pk and ";
  } else {
    $UploadClause = "";
  }
  $theLimit = ($limit=='ALL') ? '' : "LIMIT $limit";
  $sql = "select uploadtree_pk, license_file.pfile_fk, ufile_name, agent_name, max(agent_pk) agent_pk
          from license_file, agent, $TagTable
              (SELECT pfile_fk as PF, uploadtree_pk, ufile_name from $uploadtree_tablename
                 where $UploadClause
                 lft BETWEEN $lft and $rgt
                 and ufile_mode & (3<<28) = 0 )  as SS
          where PF=license_file.pfile_fk $agentCondition and rf_fk in ($rf_pk)
              AND agent_pk=agent_fk
              $TagClause
          GROUP BY uploadtree_pk, license_file.pfile_fk, ufile_name, agent_name
          $order $theLimit offset $offset";
  $result = pg_query($PG_CONN, $sql);  // Top uploadtree_pk's
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  return $result;
}

/**
 * \brief Given an uploadtree_pk, find all the non-artifact, immediate children
 * (uploadtree_pk's) that have license $rf_shortname.
 *
 * By "immediate" I mean the earliest direct non-artifact.
 *
 * For example:\n
 *    A > B, C  (A has children B and C)\n
 *    If C is an artifact, descend that tree till you find the first non-artifact
 *    and consider that non-artifact an immediate child.
 *
 * \param int $agent_pk Agent id
 * \param string $rf_shortname Short name of one license, like GPL, APSL, MIT, ...
 * \param int $uploadtree_pk   Sets scope of request
 * \param bool $PkgsOnly If true, only list packages, default is false (all
 * files are listed). $PkgsOnly is not yet implemented.
 * \param string $uploadtree_tablename
 *
 * \returns Array of uploadtree_pk ==> ufile_name
 */
function Level1WithLicense($agent_pk, $rf_shortname, $uploadtree_pk, $PkgsOnly=false, $uploadtree_tablename="uploadtree")
{
  $pkarray = array();

  $Children = GetNonArtifactChildren($uploadtree_pk, $uploadtree_tablename);

  /* Loop throught each top level uploadtree_pk */
  $offset = 0;
  $limit = 1;
  $order = "";
  $tag_pk = null;
  $result = NULL;
  foreach ($Children as $row) {
    //$uTime2 = microtime(true);
    $result = GetFilesWithLicense($agent_pk, $rf_shortname, $row['uploadtree_pk'],
                                  $PkgsOnly, $offset, $limit, $order, $tag_pk, $uploadtree_tablename);
    //$Time = microtime(true) - $uTime2;
    //printf( "GetFilesWithLicense($row[ufile_name]) time: %.2f seconds<br>", $Time);

    if (pg_num_rows($result) > 0) {
      $pkarray[$row['uploadtree_pk']] = $row['ufile_name'];
    }
  }
  if ($result) {
    pg_free_result($result);
  }
  return $pkarray;
}


/**
 * \brief Get list of links: `[View][Info][Download]`
 *
 * \param int $upload_fk Upload id
 * \param int $uploadtree_pk Uploadtree id
 * \param int $napk Nomos agent pk
 * \param int $pfile_pk
 * \param bool $Recurse true if links should propapagate recursion.  Currently,
 *        this means that the displayed tags will be displayed for directory contents.
 * \param array &$UniqueTagArray Cumulative array of unique tags
 * \param string $uploadtree_tablename
 * \param bool $wantTags
 * \parblock If true add [Tag] ...
 *
 * For example:
 * \verbatim  Array
   (
     [0] => Array
       (
           [tag_pk] => 5
           [tag_name] => GPL false positive
       )
   )
 \endverbatim
 * \endparblock
 *
 * \returns String containing the links [View][Info][Download][Tag {tags}]
 */
function FileListLinks($upload_fk, $uploadtree_pk, $napk, $pfile_pk, $Recurse=True, &$UniqueTagArray = array(), $uploadtree_tablename = "uploadtree", $wantTags=true)
{
  $LinkStr = "";
  global $SysConf;

  if ($pfile_pk) {
    $text = _("View");
    $text1 = _("Info");
    $text2 = _("Download");

    $LinkStr .= "[<a href='" . Traceback_uri() . "?mod=view-license&upload=$upload_fk&item=$uploadtree_pk&napk=$napk' >$text</a>]";
    $LinkStr .= "[<a href='" . Traceback_uri() . "?mod=view_info&upload=$upload_fk&item=$uploadtree_pk&show=detail' >$text1</a>]";
    if ($_SESSION['UserLevel'] >= $SysConf['SYSCONFIG']['SourceCodeDownloadRights']) {
      $LinkStr .= "[<a href='" . Traceback_uri() . "?mod=download&upload=$upload_fk&item=$uploadtree_pk' >$text2</a>]";
    }

  }

  /********  Tag  ********/
  if ($wantTags && TagStatus($upload_fk)) { // check if tagging on one upload is disabled or not. 1: enable, 0: disable
    $TagArray = GetAllTags($uploadtree_pk, $Recurse, $uploadtree_tablename);
    $TagStr = "";
    foreach ($TagArray as $TagPair) {
      /* Build string of tags for this item */
      if (! empty($TagStr)) {
        $TagStr .= ",";
      }
      $TagStr .= " " . $TagPair['tag_name'];

      /* Update $UniqueTagArray */
      $found = false;
      foreach ($UniqueTagArray as $UTA_key => $UTA_row) {
        if ($TagPair['tag_pk'] == $UTA_row['tag_pk']) {
          $found = true;
          break;
        }
      }
      if (! $found) {
        $UniqueTagArray[] = $TagPair;
      }
    }

    $text3 = _("Tag");
    $LinkStr .= "[<a href='" . Traceback_uri() . "?mod=tag&upload=$upload_fk&item=$uploadtree_pk' >$text3</a>";

    $LinkStr .= "<span style='color:#2897B7'>";
    $LinkStr .= $TagStr;
    $LinkStr .= "</span>";
    $LinkStr .= "]";
  }
  return $LinkStr;
}
