<?php
/*
 SPDX-FileCopyrightText: © 2022 Rohit Pandey <rohit.pandey4900@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;

class SearchHelperDao
{
  /**
   * @var DbManager
   */
  private $dbManager;

  function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
  }

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
   * @return array of uploadtree recs and total uploadtree recs count. Each record
   *         contains uploadtree_pk, parent, upload_fk, pfile_fk, ufile_mode, and
   *         ufile_name
   */
  public function GetResults($Item, $Filename, $Upload, $tag, $Page, $Limit, $SizeMin, $SizeMax, $searchtype, $License, $Copyright, $uploadDao, $groupID)
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

    $SQLBase = "SELECT DISTINCT uploadtree_pk, parent, upload_fk, uploadtree.pfile_fk, ufile_mode, ufile_name FROM uploadtree";
    $SQLWhere = " WHERE 1=1";
    $SQLOrderLimitOffset = "";
    $Filename = trim($Filename);
    $tag = trim($tag);
    $License = trim($License);
    $Copyright = trim($Copyright);

    if ($searchtype != "directory") {
      if (!empty($License)) {
        $SQLWhere .= ", ( SELECT license_ref.rf_shortname, license_file.rf_fk, license_file.pfile_fk
                  FROM license_file JOIN license_ref ON license_file.rf_fk = license_ref.rf_pk) AS pfile_ref";
      }
      if (!empty($Copyright)) {
        $SQLWhere .= ",copyright";
      }
    }

    /* Figure out the tag_pk's of interest */
    if (!empty($tag)) {
      $stmt = __METHOD__.$Filename;
      $sql = "select tag_pk from tag where tag ilike '" . pg_escape_string($tag) . "'";
      $tag_pk_array = $this->dbManager->getRows($sql, [], $stmt);
      if (empty($tag_pk_array)) {
        /* tag doesn't match anything, so no results are possible */
        return array($UploadtreeRecs, $totalUploadtreeRecsCount);
      }

      /* add the tables needed for the tag query */
      $sql = "select tag_file_pk from tag_file limit 1";
      $result = $this->dbManager->getRows($sql, [], $stmt);
      if (empty($result)) {
        /* tag_file didn't have data, don't add the tag_file table for tag query */
        $NeedTagfileTable = false;
      } else {
        $SQLWhere .= ", tag_file";
      }

      /* add the tables needed for the tag query */
      $sql = "select tag_uploadtree_pk from tag_uploadtree limit 1";
      $result = $this->dbManager->getRows($sql, [], $stmt);
      if (empty($result)) {
        /* tag_uploadtree didn't have data, don't add the tag_uploadtree table for tag query */
        $NeedTaguploadtreeTable = false;
      } else {
        $SQLWhere .= ", tag_uploadtree";
      }

      if (!$NeedTagfileTable && !$NeedTaguploadtreeTable) {
        $SQLWhere .= ", tag_file, tag_uploadtree";
      }
    }

    /* do we need the pfile table? Yes, if any of these are a search critieria.  */
    if (!empty($SizeMin) or !empty($SizeMax)) {
      $SQLWhere .= ", pfile where pfile_pk=uploadtree.pfile_fk ";
      $NeedAnd = true;
    } else {
      $SQLWhere .= " where ";
      $NeedAnd = false;
    }

    /* add the tag conditions */
    if (!empty($tag)) {
      if ($NeedAnd) {
        $SQLWhere .= " AND";
      }
      $SQLWhere .= "(";
      $NeedOr = false;
      foreach ($tag_pk_array as $tagRec) {
        if ($NeedOr) {
          $SQLWhere .= " OR";
        }
        $SQLWhere .= "(";
        $tag_pk = $tagRec['tag_pk'];
        if ($NeedTagfileTable && $NeedTaguploadtreeTable) {
          $SQLWhere .= "(uploadtree.pfile_fk=tag_file.pfile_fk and tag_file.tag_fk=$tag_pk) or (uploadtree_pk=tag_uploadtree.uploadtree_fk and tag_uploadtree.tag_fk=$tag_pk) ";
        } else if ($NeedTaguploadtreeTable) {
          $SQLWhere .= "uploadtree_pk=tag_uploadtree.uploadtree_fk and tag_uploadtree.tag_fk=$tag_pk";
        } else if ($NeedTagfileTable) {
          $SQLWhere .= "uploadtree.pfile_fk=tag_file.pfile_fk and tag_file.tag_fk=$tag_pk";
        } else {
          $SQLWhere .= "(uploadtree.pfile_fk=tag_file.pfile_fk and tag_file.tag_fk=$tag_pk) or (uploadtree_pk=tag_uploadtree.uploadtree_fk and tag_uploadtree.tag_fk=$tag_pk) ";
        }
        $SQLWhere .= ")";
        $NeedOr = 1;
      }
      $NeedAnd = 1;
      $SQLWhere .= ")";
    }

    if ($Filename) {
      if ($NeedAnd) {
        $SQLWhere .= " AND";
      }
      $SQLWhere .= " ufile_name ilike '" . $Filename . "'";
      $NeedAnd = 1;
    }

    if ($Upload != 0) {
      if ($NeedAnd) {
        $SQLWhere .= " AND";
      }
      $SQLWhere .= " upload_fk = " . $Upload . "";
      $NeedAnd = 1;
    }

    if (!empty($SizeMin) && is_numeric($SizeMin)) {
      if ($NeedAnd) {
        $SQLWhere .= " AND";
      }
      $SQLWhere .= " pfile.pfile_size >= " . $SizeMin;
      $NeedAnd = 1;
    }

    if (!empty($SizeMax) && is_numeric($SizeMax)) {
      if ($NeedAnd) {
        $SQLWhere .= " AND";
      }
      $SQLWhere .= " pfile.pfile_size <= " . $SizeMax;
      $NeedAnd = 1;
    }

    if ($Item) {
      if ($NeedAnd) {
        $SQLWhere .= " AND";
      }
      $SQLWhere .= "  upload_fk = $upload_pk AND lft >= $lft AND rgt <= $rgt";
      $NeedAnd = 1;
    }

    /* search only containers */
    if ($searchtype == 'containers') {
      if ($NeedAnd) {
        $SQLWhere .= " AND";
      }
      $SQLWhere .= " ((ufile_mode & (1<<29))!=0) AND ((ufile_mode & (1<<28))=0)";
      $NeedAnd = 1;
    }
    $dir_ufile_mode = 536888320;
    if ($searchtype == 'directory') {
      if ($NeedAnd) {
        $SQLWhere .= " AND";
      }
      $SQLWhere .= " ((ufile_mode & (1<<29))!=0) AND ((ufile_mode & (1<<28))=0) AND (ufile_mode != $dir_ufile_mode) and pfile_fk != 0";
      $NeedAnd = 1;
    }

    /** license and copyright */
    if ($searchtype != "directory") {
      if (!empty($License)) {
        if ($NeedAnd) {
          $SQLWhere .= " AND";
        }

        $SQLWhere .= " uploadtree.pfile_fk=pfile_ref.pfile_fk and pfile_ref.rf_shortname ilike '" .
          pg_escape_string($License) . "'";
        $NeedAnd = 1;
      }
      if (!empty($Copyright)) {
        if ($NeedAnd) {
          $SQLWhere .= " AND";
        }
        $SQLWhere .= " uploadtree.pfile_fk=copyright.pfile_fk and copyright.content ilike '%" .
          pg_escape_string($Copyright) . "%'";
      }
    }

    $Offset = $Page * $Limit;

    $SQLOrderLimitOffset = " ORDER BY ufile_name, uploadtree.pfile_fk LIMIT $Limit OFFSET $Offset";
    $PaginatedSQL = $SQLBase . $SQLWhere . $SQLOrderLimitOffset;

    $CountSQL = "SELECT COUNT(DISTINCT uploadtree_pk) FROM uploadtree" . $SQLWhere;

    $stmt = __METHOD__ . "_count";
    $countRow = $this->dbManager->getSingleRow($CountSQL, [], $stmt);
    $totalUploadtreeRecsCount = $countRow ? reset($countRow) : 0;

    $stmt = __METHOD__ . "_paginated";
    $rows = $this->dbManager->getRows($PaginatedSQL, [], $stmt);
    if (!empty($rows)) {
      foreach ($rows as $row) {
        if (!$uploadDao->isAccessible($row['upload_fk'], $groupID)) {
          continue;
        }
        $totalUploadtreeRecs[] = $row;
      }
    }
    return array($totalUploadtreeRecs, $totalUploadtreeRecsCount);
  }
}
