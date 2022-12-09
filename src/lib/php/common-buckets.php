<?php
/*
 SPDX-FileCopyrightText: © 2010-2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only
*/

/** *********************************************************
 * \file
 * \brief These are common bucket functions.
 ************************************************************/

/**
 * \brief Return a select list showing all the successful bucket
 *        runs on a particular $upload_pk.
 *
 *  This list can be included in UI's to let the user select
 *  which data they wish to view.
 *  The most recent results are the default selection.
 *
 * \param string $upload_pk
 * \param string &$ars_pk    Return ars_pk of the selected element, may be zero
 *                           if there are no data. This is also used to pass in
 *                           the selected ars_pk.
 * \param string $id         HTML element id
 * \param string $extra      Extra info for the select element, e.g. "onclick=..."
 *
 * \return Select string, select value is $ars_pk \n
 *         If there are no rows to select, $ars_pk is returned 0
 *         and a simple string $NoData is returned; \n
 *         If there are only 1 row, an empty string is returned, and $ars_pk is
 *         set to that row.
 */
function SelectBucketDataset($upload_pk, &$ars_pk, $id="selectbucketdataset", $extra="")
{
  global $PG_CONN;

  /* schedule bucket */
  $NoData = ActiveHTTPscript("Schedule");
  $NoData .= "<script language='javascript'>\n";
  $NoData .= "function Schedule_Reply()\n";
  $NoData .= "  {\n";
  $NoData .= "  if ((Schedule.readyState==4) && (Schedule.status==200))\n";
  $NoData .= "    document.getElementById('msgdiv').innerHTML = Schedule.responseText;\n";
  $NoData .= "  }\n";
  $NoData .= "</script>\n";

  $NoData .= "<form name='formy' method='post'>\n";
  $NoData .= "<div id='msgdiv'>\n";
  $NoData .= _("No data available.");
  $NoData .= "<input type='button' name='scheduleAgent' value='Schedule Agent'";
  $NoData .= "onClick='Schedule_Get(\"" . Traceback_uri() . "?mod=schedule_agent&upload=$upload_pk&agent=agent_bucket \")'>\n";
  $NoData .= "</input>";
  $NoData .= "</div> \n";
  $NoData .= "</form>\n";

  $name = $id;
  $select = "<select name='$name' id='$id' $extra>";

  /* get the bucketpool recs */
  $sql = "select ars_pk, bucketpool_pk, bucketpool_name, version from bucketpool, bucket_ars where active='Y' and bucketpool_fk=bucketpool_pk and ars_success=True and upload_fk='$upload_pk' order by ars_starttime desc";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $NumRows = pg_num_rows($result);
  if ($NumRows == 0) {
    return $NoData;
  }
  $rows = pg_fetch_all($result);
  pg_free_result($result);
  if ($NumRows == 1) {
    $ars_pk = $rows[0]['ars_pk'];
    return ""; /* only one row */
  }

  /* Find the users default_bucketpool_fk */
  $sql = "select default_bucketpool_fk from users where user_pk='$_SESSION[UserId]'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  $DefaultBucketpool_pk = $row['default_bucketpool_fk'];
  pg_free_result($result);

  /* Find the default selected row if ars_pk wasn't passed in */
  if (empty($ars_pk)) {
    foreach ($rows as $row) {
      if ($row['bucketpool_pk'] == $DefaultBucketpool_pk) {
        $ars_pk = $row['ars_pk'];
        break;
      }
    }
    reset($rows);
  }

  //  $select .= "<option value=''";
  foreach ($rows as $row) {
    $select .= "<option value='$row[ars_pk]'";

    if (empty($ars_pk)) {
      $select .= " SELECTED ";
      $ars_pk = $row["ars_pk"];
    } else if ($ars_pk == $row['ars_pk']) {
      $select .= " SELECTED ";
    }

    $select .= ">$row[bucketpool_name], v $row[version]\n";
  }
  $select .= "</select>";
  return $select;
}

/**
 * \brief Return a select list containing all the active bucketpool's.
 *
 * \param string $selected Selected bucketpool_pk
 * \param string $active   'Y' (default) or 'all'
 *
 * \return String select list
 * \note list uses static element id="default_bucketpool_fk"
 *       the element name is the same as the id.
 */
function SelectBucketPool($selected, $active='Y')
{
  global $PG_CONN;

  $id = "default_bucketpool_fk";
  $name = $id;
  $select = "<select name='$name' id='$id' class='ui-render-select2'>";

  /* get the bucketpool recs */
  if ($active == 'Y') {
    $Where = "where active='Y'";
  } else {
    $Where = "";
  }
  $sql = "select * from bucketpool $Where order by bucketpool_name asc,version desc";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);

  while ($row = pg_fetch_assoc($result)) {
    $select .= "<option value='$row[bucketpool_pk]'";
    if ($row['bucketpool_pk'] == $selected) {
      $select .= " SELECTED ";
    }
    $select .= ">$row[bucketpool_name], v $row[version]</option>\n";
  }
  $select .= "</select>";
  return $select;
}

/**
 * \brief Get all the unique bucket_pk's for a given uploadtree_pk and
 * for a given nomos and bucket agent.
 *
 * \param int $nomosagent_pk  Nomos agent id
 * \param int $bucketagent_pk Bucket agent id
 * \param int $uploadtree_pk  File id in upload tree
 * \param int $bucketpool_pk  Bucket pool id
 *
 * \return Array of unique bucket_pk's, may be empty if no buckets.
 *   \b FATAL if any input is missing
 */
function GetFileBuckets($nomosagent_pk, $bucketagent_pk, $uploadtree_pk, $bucketpool_pk)
{
  global $PG_CONN;
  $BuckArray = array();

  if (empty($nomosagent_pk) || empty($bucketagent_pk) || empty($uploadtree_pk)) {
    Fatal(
      "Missing parameter: nomosagent_pk $nomosagent_pk, bucketagent_pk: $bucketagent_pk, uploadtree_pk: $uploadtree_pk<br>",
      __FILE__, __LINE__);
  }

  /* Find lft and rgt bounds for this $uploadtree_pk  */
  $sql = "SELECT lft,rgt,upload_fk FROM uploadtree
            WHERE uploadtree_pk = $uploadtree_pk";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  if (pg_num_rows($result) < 1) {
    pg_free_result($result);
    return $BuckArray;
  }
  $row = pg_fetch_assoc($result);
  $lft = $row["lft"];
  $rgt = $row["rgt"];
  $upload_pk = $row["upload_fk"];
  pg_free_result($result);

  /*select all the buckets for this tree */
  $sql = "SELECT distinct(bucket_fk) as bucket_pk
            from bucket_file, bucket_def,
                (SELECT distinct(pfile_fk) as PF from uploadtree
                   where upload_fk=$upload_pk
                     and ((ufile_mode & (1<<28))=0)
                     and uploadtree.lft BETWEEN $lft and $rgt) as SS
            where PF=pfile_fk and agent_fk=$bucketagent_pk
                  and bucket_file.nomosagent_fk=$nomosagent_pk
                    and bucket_pk=bucket_fk
                    and bucketpool_fk=$bucketpool_pk";

  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  while ($row = pg_fetch_assoc($result)) {
    $BuckArray[] = $row['bucket_pk'];
  }
  pg_free_result($result);

  return $BuckArray;
}


/**
 * \brief Get string of $delimiter delimited bucket names for the given inputs.
 * Args are same as GetFileBuckets().
 *
 * \param int $nomosagent_pk
 * \param int $bucketagent_pk
 * \param int $uploadtree_pk
 * \param array $bucketDefArray Array of bucket_def records indexed by bucket_pk
 *        see initBucketDefArray().
 * \param string  $delimiter Delimiter string to use to separate bucket names.
 * \param boolean $color if True, the string is returned as HTML with bucket names
 *         color coded.
 *
 * \return string of $delimiter delimited bucket names for the given inputs.
 */
function GetFileBuckets_string($nomosagent_pk, $bucketagent_pk, $uploadtree_pk,
                               $bucketDefArray, $delimiter, $color)
{
  $outstr = "";
  $defrec = current($bucketDefArray);
  $bucketpool_pk = $defrec['bucketpool_fk'];
  $BuckArray = GetFileBuckets($nomosagent_pk, $bucketagent_pk, $uploadtree_pk, $bucketpool_pk);
  if (empty($BuckArray)) {
    return "";
  }

  /* convert array of bucket_pk's to array of bucket names */
  $BuckNames = array();
  foreach ($BuckArray as $bucket_pk) {
    $BuckNames[$bucket_pk] = $bucketDefArray[$bucket_pk]['bucket_name'];
  }

  /* sort $BuckArray */
  natcasesort($BuckNames);

  $first = true;
  foreach ($BuckNames as $bucket_name) {
    if ($first) {
      $first = false;
    } else {
      $outstr .= $delimiter . " ";
    }

    if ($color) {
      $bucket_pk = array_search($bucket_name, $BuckNames);
      $bucket_color = $bucketDefArray[$bucket_pk]['bucket_color'];
      $outstr .= "<span style='background-color:$bucket_color'>";
      $outstr .= $bucket_name;
      $outstr .= "</span>";
    } else {
      $outstr .= $bucket_name;
    }
  }

  return $outstr;
}


/**
 * \brief Check if a bucket_pk is found in a tree
 * for a given nomos and bucket agent.
 *
 * \param int $bucket_pk
 * \param int $uploadtree_pk
 *
 * \return True if bucket_pk is found in the tree,
 *   false if not
 */
function BucketInTree($bucket_pk, $uploadtree_pk)
{
  global $PG_CONN;
  $BuckArray = array();

  if (empty($bucket_pk) || empty($uploadtree_pk)) {
    Fatal(
      "Missing parameter: bucket_pk: $bucket_pk, uploadtree_pk: $uploadtree_pk<br>",
      __FILE__, __LINE__);
  }

  /* Find lft and rgt bounds for this $uploadtree_pk  */
  $sql = "SELECT lft,rgt, upload_fk FROM uploadtree WHERE uploadtree_pk = $uploadtree_pk";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  if (pg_num_rows($result) < 1) {
    pg_free_result($result);
    return false;
  }
  $row = pg_fetch_assoc($result);
  $lft = $row["lft"];
  $rgt = $row["rgt"];
  $upload_fk = $row["upload_fk"];
  pg_free_result($result);

  /* search for bucket in tree */
  $sql = "SELECT bucket_fk from bucket_file,
            (SELECT distinct(pfile_fk) as PF from uploadtree
               where uploadtree.lft BETWEEN $lft and $rgt and upload_fk='$upload_fk') as SS
          where PF=pfile_fk and bucket_fk='$bucket_pk' limit 1";

  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  if (pg_num_rows($result) == 0) {
    $rv = false;
  } else {
    $rv = true;
  }
  pg_free_result($result);

  return $rv;
}


/**
 * \brief Initializes array of bucket_def records.
 *
 * \param int $bucketpool_pk Bucketpool id
 *
 * \return List of bucket def records.
 */
function initBucketDefArray($bucketpool_pk)
{
  global $PG_CONN;

  $sql = "select * from bucket_def where bucketpool_fk=$bucketpool_pk";
  $result_name = pg_query($PG_CONN, $sql);
  DBCheckResult($result_name, $sql, __FILE__, __LINE__);
  $bucketDefArray = array();
  while ($name_row = pg_fetch_assoc($result_name)) {
    $bucketDefArray[$name_row['bucket_pk']] = $name_row;
  }
  pg_free_result($result_name);
  return $bucketDefArray;
}

