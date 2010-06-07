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
 * Return all the buckets for a single file or uploadtree
 * for a given nomos and bucket agent.
 * Inputs:
 *   $nomosagent_pk
 *   $bucketagent_pk
 *   $pfile_pk       (if empty, $uploadtree_pk must be given)
 *   $uploadtree_pk  (used only if $pfile_pk is empty)
 * Returns:
 *   sql result for the result bucket_file records plus bucket_name
 *   FATAL if neither pfile_pk or uploadtree_pk were given
 */
function GetFileBuckets($nomosagent_pk, $bucketagent_pk, $pfile_pk, $uploadtree_pk)
{
  global $PG_CONN;

  if (empty($nomosagent_pk)|| empty($bucketagent_pk)) 
     Fatal("Missing parameter: nomosagent_pk $nomosagent_pk, bucketagent_pk: $bucketagent_pk<br>", __FILE__, __LINE__);

  // if no $pfile_pk, then get it for the given $uploadtree_pk
  if (!$pfile_pk)
  {
    $sql = "select pfile_fk from uploadtree where uploadtree_pk='$uploadtree_pk'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) == 0) return 0;
    $row = pg_fetch_assoc($result);
    $pfile_pk = $row['pfile_fk'];
    pg_free_result($result);
  }

    $sql = "SELECT *, bucket_name, bucketpool_name from bucket_file, bucket_def, bucketpool
              where pfile_fk='$pfile_pk' and nomosagent_fk='$nomosagent_pk' 
                    and agent_fk='$bucketagent_pk' and bucket_fk=bucket_pk
                    and bucketpool_pk=bucketpool_fk
              order by bucket_reportorder asc";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
  return $result;
}

// Returns array 'bucketpool name', 'bucket name 1', 'bucket name 2', etc
// Args are same as GetFileBuckets()
function GetFileBuckets_array($nomosagent_pk, $bucketagent_pk, $pfile_pk, $uploadtree_pk)
{
  $BuckArray = array();
  $BucketResult = GetFileBuckets($nomosagent_pk, $bucketagent_pk, $pfile_pk, $uploadtree_pk);
  $first = true;
  while ($row = pg_fetch_assoc($BucketResult))
  {
    if ($first)
    {
      $BuckArray[] = $row['bucketpool_name'];
      $first = false;
    }
    $BuckArray[] = $row['bucket_name'];
  }
  pg_free_result($BucketResult);
  
  return $BuckArray;
}
?>
