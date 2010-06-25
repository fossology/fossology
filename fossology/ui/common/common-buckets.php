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

/************************************************************
 These are common bucket functions.
 ************************************************************/

/**
 * SelectBucketpool
 * \brief Return a select list for bucketpool's
 *
 * @param string $selected, selected bucketpool_pk
 *
 * @return string select list
 * Note: list uses static element id="default_bucketpool_fk"
 *       the element name is the same as the id.
 */
function SelectBucketPool($selected)
{
  global $PG_CONN;

  $id = "default_bucketpool_fk";
  $name = $id;
  $select = "<select name='$name' id='$id'>";

  /* get the bucketpool recs */
  $sql = "select * from bucketpool where active='Y'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);

  $select .= "<option value=''";
  while ($row = pg_fetch_assoc($result)) 
  {
    $select .= "<option value='$row[bucketpool_pk]'";
    if ($row['bucketpool_pk'] == $selected) $select .= " SELECTED ";
    $select .= ">$row[bucketpool_name], v $row[version]\n";
  }
  $select .= "</select>";
  return $select;
}

/*
 * Return all the unique bucket_pk's for a given uploadtree_pk and
 * for a given nomos and bucket agent.
 * Inputs:
 *   $nomosagent_pk
 *   $bucketagent_pk
 *   $uploadtree_pk  
 * Returns:
 *   array of unique bucket_pk's, may be empty if no buckets.
 *   FATAL if any input is missing
 */
function GetFileBuckets($nomosagent_pk, $bucketagent_pk, $uploadtree_pk, $bucketpool_pk)
{
  global $PG_CONN;
  $BuckArray = array();

  if (empty($nomosagent_pk)|| empty($bucketagent_pk) || empty($uploadtree_pk)) 
     Fatal("Missing parameter: nomosagent_pk $nomosagent_pk, bucketagent_pk: $bucketagent_pk, uploadtree_pk: $uploadtree_pk<br>", __FILE__, __LINE__);

  /* Find lft and rgt bounds for this $uploadtree_pk  */
  $sql = "SELECT lft,rgt,upload_fk FROM uploadtree 
            WHERE uploadtree_pk = $uploadtree_pk";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  if (pg_num_rows($result) < 1)
  {
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
  while ($row = pg_fetch_assoc($result)) $BuckArray[] = $row['bucket_pk'];
  pg_free_result($result);

  return $BuckArray;
}


/* Returns string of $delimiter delimited bucket names for the given inputs.
 * Args are same as GetFileBuckets().
 * $bucketDefArray is array of bucket_def records indexed by bucket_pk
 *        see initBucketDefArray().
 * $delimiter is delimiter string to use to seperate bucket names.
 * $color if True, the string is returned as html with bucket names
          color coded.
 */
function GetFileBuckets_string($nomosagent_pk, $bucketagent_pk, $uploadtree_pk, 
                               $bucketDefArray, $delimiter, $color)
{
  $outstr = "";
  $defrec = current($bucketDefArray);
  $bucketpool_pk = $defrec['bucketpool_fk'];
  $BuckArray = GetFileBuckets($nomosagent_pk, $bucketagent_pk, $uploadtree_pk, $bucketpool_pk);
  if (empty($BuckArray)) return "";

  /* convert array of bucket_pk's to array of bucket names */
  $BuckNames = array();
  foreach ($BuckArray as $bucket_pk) 
  {
    $BuckNames[$bucket_pk] = $bucketDefArray[$bucket_pk]['bucket_name'];
  }

  /* sort $BuckArray */
  natcasesort($BuckNames);

  $first = true;
  foreach ($BuckNames as $bucket_name)
  {
    if ($first)
      $first = false;
    else
      $outstr .= $delimiter . " ";

    if ($color)
    {
      $bucket_pk = array_search($bucket_name, $BuckNames);
      $bucket_color = $bucketDefArray[$bucket_pk]['bucket_color'];
      $outstr .= "<span style='background-color:$bucket_color'>";
      $outstr .= $bucket_name;
      $outstr .= "</span>";
    }
    else
      $outstr .= $bucket_name;
  }
  
  return $outstr;
}


/* Initializes array of bucket_def records.
 */
function initBucketDefArray($bucketpool_pk)
{
  global $PG_CONN;

  $sql = "select * from bucket_def where bucketpool_fk=$bucketpool_pk";
  $result_name = pg_query($PG_CONN, $sql);
  DBCheckResult($result_name, $sql, __FILE__, __LINE__);
  $bucketDefArray = array();
  while ($name_row = pg_fetch_assoc($result_name))
    $bucketDefArray[$name_row['bucket_pk']] = $name_row;
  pg_free_result($result_name);
  return $bucketDefArray;
}
?>
