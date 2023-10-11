<?php
/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file dbmigrate_1.4-2.0.php
 * @brief This file is called by fossinit.php to create and initialize 
 *        new ARS tables when migrating from a 1.4 database to 2.0.
 *
 * This should be called after fossinit calls apply_schema.
 **/


/**
 * \brief Create the new ars tables and populate them from the job/jobqueue data.
 *
 * \param $DryRun Do not insert the ars records into the db.  Just print the insert statements.
 *        The ars table will still be created if it doesn't exist.
 *
 * \return int 0 on success, 1 on failure
 **/
function Migrate_14_20($DryRun)
{
  global $PG_CONN;

  /* array of agent_name, ars file name for tables to create */
  $ARSarray = array("pkgagent"  => "pkgagent_ars",
                    "copyright" => "copyright_ars",
                    "mimetype"  => "mimetype_ars",
                    "unpack"    => "ununpack_ars",
                    "ununpack"  => "ununpack_ars");

  foreach($ARSarray as $agent_name => $ARStablename)
  {
    /* Create the ars table if it doesn't exist */
    CreateARStable($ARStablename, $DryRun);

    /* Get the agent_pk */
    $agent_pk = GetAgentKey($agent_name, "");

    /* Select the jobqueue records for this agent */
    $sql = "select distinct job_upload_fk, jq_type, jq_starttime, jq_endtime from jobqueue
              join job on jq_job_fk=job_pk where jq_type='$agent_name' and (jq_end_bits=1) order by jq_type";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    /* Loop through jobqueue records inserting ars table rows */
    while ($row = pg_fetch_assoc($result))
    {
      $upload_fk = $row['job_upload_fk'];

      /* prevent duplicate insert */
      $sql = "select ars_pk from $ARStablename where agent_fk=$agent_pk and upload_fk=$upload_fk and ars_success=true";
      $checkrec = pg_query($PG_CONN, $sql);
      DBCheckResult($checkrec, $sql, __FILE__, __LINE__);
      $num_rows = pg_num_rows($checkrec);
      pg_free_result($checkrec);
      if ($num_rows > 0) continue;

      /* add ars rec */
      $sql = "insert into $ARStablename (agent_fk, upload_fk, ars_success, ars_starttime, ars_endtime)
                  values ($agent_pk, $upload_fk, true, '$row[jq_starttime]', '$row[jq_endtime]')";
      if ($DryRun)
        echo "DryRun: $sql\n";
      else
      {
        $insresult = pg_query($PG_CONN, $sql);
        DBCheckResult($insresult, $sql, __FILE__, __LINE__);
        pg_free_result($insresult);
      }
    }
    pg_free_result($result);
  }
                    
  return 0;
} // Migrate_14_20


/**
 * \brief Create ars table
 *
 * \param string $ARStablename  ARS table name
 *
 * \return void
 **/
function CreateARStable($ARStablename)
{
  global $PG_CONN;

  if (DB_TableExists($ARStablename)) return;

  $sql = "CREATE TABLE $ARStablename () INHERITS (ars_master)";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
}
