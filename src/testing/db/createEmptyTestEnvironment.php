<?php
/*
 SPDX-FileCopyrightText: © 2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \brief Create a fossology test database, test configuration directory and
 * test repository.
 *
 * @version "$Id$"
 *
 * Created on Apr 11, 2018 by Gaurav Mishra
 */

function setupTestEnv($workingDir, $agent, $agentTable=true)
{
  $SYSCONF_DIR = "$workingDir/testconf";
  $confFile = "fossology.conf";
  $agentDir = "$workingDir/..";

  exec("rm -rf $SYSCONF_DIR");
  if(!mkdir($SYSCONF_DIR)) {
    die("Unable to create $SYSCONF_DIR");
  }
  $confFile_fh = fopen("$SYSCONF_DIR/$confFile", 'w')
  or die("FAIL: Could not open $SYSCONF_DIR/$confFile for writing\n");
  fwrite($confFile_fh, ";fossology.conf for testing\n");
  fwrite($confFile_fh, "[FOSSOLOGY]\nport = 24693\n");
  fwrite($confFile_fh, "address = localhost\n");
  fwrite($confFile_fh, "depth = 0\n");
  fwrite($confFile_fh, "path = $SYSCONF_DIR\n");
  fwrite($confFile_fh, "[HOSTS]\n");
  fwrite($confFile_fh, "localhost = localhost AGENT_DIR 10\n");
  fwrite($confFile_fh, "[REPOSITORY]\n");
  fwrite($confFile_fh, "localhost = * 00 ff\n");
  fwrite($confFile_fh, "[DIRECTORIES]\n");
  fwrite($confFile_fh, "PROJECTUSER=fossy\n");
  fwrite($confFile_fh, "PROJECTGROUP=fossy\n");
  fwrite($confFile_fh, "MODDIR=$workingDir/../../..\n");
  fwrite($confFile_fh, 'LIBEXECDIR=$MODDIR/../install/db' . "\n");
  fwrite($confFile_fh, "LOGDIR=$SYSCONF_DIR\n");
  fclose($confFile_fh);
  symlink("$workingDir/../VERSION", "$SYSCONF_DIR/VERSION");
  mkdir("$SYSCONF_DIR/mods-enabled");
  symlink($agentDir, "$SYSCONF_DIR/mods-enabled/$agent");

  $DB_COMMAND  = __DIR__."/createTestDB.php -c $SYSCONF_DIR -e";

  exec($DB_COMMAND, $dbout, $rc);
  if ($rc != 0) {
    print "Can not create database for this testing sucessfully!\n";
    exit;
  }
  preg_match("/(\d+)/", $dbout[0], $matches);
  $test_name = $matches[1];
  $db_conf = $dbout[0];
  $version_array = parse_ini_file("$db_conf/VERSION");
  $db_array = parse_ini_file("$db_conf/Db.conf");
  $DB_NAME = $db_array["dbname"];
  $db_user = $db_array["user"];
  $db_pass = $db_array["password"];
  $db_host = $db_array["host"];
  $PG_CONN = pg_connect("host=$db_host port=5432 dbname=$DB_NAME user=$db_user password=$db_pass")
  or die("Could not connect");

  if($agentTable == true) {
    $sql = "CREATE TABLE agent (agent_pk serial, agent_name character varying(32), agent_rev character varying(32),"
         . " agent_desc character varying(255) DEFAULT NULL, agent_enabled boolean DEFAULT true, agent_parms text,"
         . " agent_ts timestamp with time zone DEFAULT now());";
    $result = pg_query($PG_CONN, $sql);
    pg_free_result($result);
    $sql = "INSERT INTO agent(agent_name, agent_rev) VALUES('$agent','\"" . $version_array["VERSION"]
         . "\"." . $version_array["COMMIT_HASH"] . "');";
    $result = pg_query($PG_CONN, $sql);
    pg_free_result($result);
  }
  return array($test_name, $db_conf, $DB_NAME, $PG_CONN);
}
