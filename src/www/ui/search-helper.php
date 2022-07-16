<?php
/*
 SPDX-FileCopyrightText: © 2010-2014 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015-2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \brief Given a filename, return all uploadtree.
 * @param $Item     int - uploadtree_pk of tree to search, if empty, do global search
 * @param $Filename string - filename or pattern to search for, false if unused
 * @param $tag      string - tag (or tag pattern mytag%) to search for, false if unused
 * @param $Page     int - display page number
 * @param $Limit    int - size of the page
 * @param $SizeMin  int - Minimum file size, -1 if unused
 * @param $SizeMax  int - Maximum file size, -1 if unused
 * @param $searchtype "containers", "directory" or "allfiles"
 * @param $License string
 * @param $Copyright string
 * @param $uploadDao \Fossology\Lib\Dao\UploadDao
 * @param $groupID int
 * @param $PG_CONN resource
 * @return array of uploadtree recs and total uploadtree recs count. Each record
 *         contains uploadtree_pk, parent, upload_fk, pfile_fk, ufile_mode, and
 *         ufile_name
 */
function GetResults($Item, $Filename, $Upload, $tag, $Page, $Limit, $SizeMin, $SizeMax, $searchtype,
                    $License, $Copyright, $uploadDao, $groupID, $PG_CONN)
{
  $UploadtreeRecs = array();  // uploadtree record array to return
  $totalUploadtreeRecs = array();  // total uploadtree record array
  $totalUploadtreeRecsCount = 0; // total uploadtree records count to return
  $NeedTagfileTable = true;
  $NeedTaguploadtreeTable = true;

  if ($Item) {
    /* Find lft and rgt bounds for this $Uploadtree_pk  */
    $row = $uploadDao->getUploadEntry($Item);
    if (empty($row)) {
      $text = _("Invalid URL, nonexistant item");
      return "<h2>$text $Item</h2>";
    }
    $lft = $row["lft"];
    $rgt = $row["rgt"];
    $upload_pk = $row["upload_fk"];

    /* Check upload permission */
    if (!$uploadDao->isAccessible($upload_pk, $groupID)) {
      return array($UploadtreeRecs, $totalUploadtreeRecsCount);
    }
  }

  /* Start the result select stmt */
  $SQL = "SELECT DISTINCT uploadtree_pk, parent, upload_fk, uploadtree.pfile_fk, ufile_mode, ufile_name FROM uploadtree";

  if ($searchtype != "directory") {
    if (! empty($License)) {
      $SQL .= ", ( SELECT license_ref.rf_shortname, license_file.rf_fk, license_file.pfile_fk
                  FROM license_file JOIN license_ref ON license_file.rf_fk = license_ref.rf_pk) AS pfile_ref";
    }
    if (! empty($Copyright)) {
      $SQL .= ",copyright";
    }
  }

  /* Figure out the tag_pk's of interest */
  if (! empty($tag)) {
    $sql = "select tag_pk from tag where tag ilike '" . pg_escape_string($tag) . "'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1) {
      /* tag doesn't match anything, so no results are possible */
      pg_free_result($result);
      return array($UploadtreeRecs, $totalUploadtreeRecsCount);
    }

    /* Make a list of the tag_pk's that satisfy the criteria */
    $tag_pk_array = pg_fetch_all($result);
    pg_free_result($result);

    /* add the tables needed for the tag query */
    $sql = "select tag_file_pk from tag_file limit 1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1) {
      /* tag_file didn't have data, don't add the tag_file table for tag query */
      $NeedTagfileTable = false;
    } else {
      $SQL .= ", tag_file";
    }
    pg_free_result($result);

    /* add the tables needed for the tag query */
    $sql = "select tag_uploadtree_pk from tag_uploadtree limit 1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1) {
      /* tag_uploadtree didn't have data, don't add the tag_uploadtree table for tag query */
      $NeedTaguploadtreeTable = false;
    } else {
      $SQL .= ", tag_uploadtree";
    }
    pg_free_result($result);

    if (!$NeedTagfileTable && !$NeedTaguploadtreeTable) {
      $SQL .= ", tag_file, tag_uploadtree";
    }
  }

  /* do we need the pfile table? Yes, if any of these are a search critieria.  */
  if (!empty($SizeMin) or !empty($SizeMax)) {
    $SQL .= ", pfile where pfile_pk=uploadtree.pfile_fk ";
    $NeedAnd = true;
  } else {
    $SQL .= " where ";
    $NeedAnd = false;
  }

  /* add the tag conditions */
  if (!empty($tag)) {
    if ($NeedAnd) {
      $SQL .= " AND";
    }
    $SQL .= "(";
    $NeedOr = false;
    foreach ($tag_pk_array as $tagRec) {
      if ($NeedOr) {
        $SQL .= " OR";
      }
      $SQL .= "(";
      $tag_pk = $tagRec['tag_pk'];
      if ($NeedTagfileTable && $NeedTaguploadtreeTable) {
        $SQL .= "(uploadtree.pfile_fk=tag_file.pfile_fk and tag_file.tag_fk=$tag_pk) or (uploadtree_pk=tag_uploadtree.uploadtree_fk and tag_uploadtree.tag_fk=$tag_pk) ";
      } else if ($NeedTaguploadtreeTable) {
        $SQL .= "uploadtree_pk=tag_uploadtree.uploadtree_fk and tag_uploadtree.tag_fk=$tag_pk";
      } else if ($NeedTagfileTable) {
        $SQL .= "uploadtree.pfile_fk=tag_file.pfile_fk and tag_file.tag_fk=$tag_pk";
      } else {
        $SQL .= "(uploadtree.pfile_fk=tag_file.pfile_fk and tag_file.tag_fk=$tag_pk) or (uploadtree_pk=tag_uploadtree.uploadtree_fk and tag_uploadtree.tag_fk=$tag_pk) ";
      }
      $SQL .= ")";
      $NeedOr=1;
    }
    $NeedAnd=1;
    $SQL .= ")";
  }

  if ($Filename) {
    if ($NeedAnd) {
      $SQL .= " AND";
    }
    $SQL .= " ufile_name ilike '". pg_escape_string($Filename) . "'";
    $NeedAnd=1;
  }

  if ($Upload != 0) {
    if ($NeedAnd) {
      $SQL .= " AND";
    }
    $SQL .= " upload_fk = ". pg_escape_string($Upload) . "";
    $NeedAnd=1;
  }

  if (!empty($SizeMin) && is_numeric($SizeMin)) {
    if ($NeedAnd) {
      $SQL .= " AND";
    }
    $SQL .= " pfile.pfile_size >= ".pg_escape_string($SizeMin);
    $NeedAnd=1;
  }

  if (!empty($SizeMax) && is_numeric($SizeMax)) {
    if ($NeedAnd) {
      $SQL .= " AND";
    }
    $SQL .= " pfile.pfile_size <= ".pg_escape_string($SizeMax);
    $NeedAnd=1;
  }

  if ($Item) {
    if ($NeedAnd) {
      $SQL .= " AND";
    }
    $SQL .= "  upload_fk = $upload_pk AND lft >= $lft AND rgt <= $rgt";
    $NeedAnd=1;
  }

  /* search only containers */
  if ($searchtype == 'containers') {
    if ($NeedAnd) {
      $SQL .= " AND";
    }
    $SQL .= " ((ufile_mode & (1<<29))!=0) AND ((ufile_mode & (1<<28))=0)";
    $NeedAnd=1;
  }
  $dir_ufile_mode = 536888320;
  if ($searchtype == 'directory') {
    if ($NeedAnd) {
      $SQL .= " AND";
    }
    $SQL .= " ((ufile_mode & (1<<29))!=0) AND ((ufile_mode & (1<<28))=0) AND (ufile_mode != $dir_ufile_mode) and pfile_fk != 0";
    $NeedAnd = 1;
  }

  /** license and copyright */
  if ($searchtype != "directory") {
    if (! empty($License)) {
      if ($NeedAnd) {
        $SQL .= " AND";
      }

      $SQL .= " uploadtree.pfile_fk=pfile_ref.pfile_fk and pfile_ref.rf_shortname ilike '" .
        pg_escape_string($License) . "'";
      $NeedAnd = 1;
    }
    if (! empty($Copyright)) {
      if ($NeedAnd) {
        $SQL .= " AND";
      }
      $SQL .= " uploadtree.pfile_fk=copyright.pfile_fk and copyright.content ilike '%" .
        pg_escape_string($Copyright) . "%'";
    }
  }

  $Offset = $Page * $Limit;
  $SQL .= " ORDER BY ufile_name, uploadtree.pfile_fk";
  $result = pg_query($PG_CONN, $SQL);
  DBCheckResult($result, $SQL, __FILE__, __LINE__);
  if (pg_num_rows($result)) {
    while ($row = pg_fetch_assoc($result)) {
      if (! $uploadDao->isAccessible($row['upload_fk'], $groupID)) {
        continue;
      }
      $totalUploadtreeRecs[] = $row;
    }
  }
  pg_free_result($result);
  $UploadtreeRecs = array_slice($totalUploadtreeRecs, $Offset, $Limit);
  $totalUploadtreeRecsCount = sizeof($totalUploadtreeRecs);
  return array($UploadtreeRecs, $totalUploadtreeRecsCount);
} // GetResults()
