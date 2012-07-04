<?php
/*
   Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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
 */

  /**
   * \file test library
   */ 

  /**
   * \brief create DB 
   */
  function create_db() {
    global $SYSCONF_DIR;
    global $DB_NAME;
    global $REPO_NAME;
    global $PG_CONN;
    global $DB_COMMAND;

    // print "DB_COMMAND is:$DB_COMMAND\n";
    exec($DB_COMMAND, $dbout, $rc);
    preg_match("/(\d+)/", $dbout[0], $matches);
    $test_name = $matches[1];
    $DB_NAME = "fosstest".$test_name;
    $REPO_NAME = "testDbRepo".$test_name;
    $SYSCONF_DIR = $dbout[0];
    $PG_CONN = pg_connect("host=localhost port=5432 dbname=$DB_NAME user=fossy password=fossy")
                     or die("Could not connect");
    // print "DB_NAME is:$DB_NAME, $SYSCONF_DIR\n";
  }

  /**
   * \brief drop db
   */
  function drop_db() {
    global $PG_CONN;
    global $DB_COMMAND;
    global $DB_NAME;
    pg_close($PG_CONN);
    exec("$DB_COMMAND -d $DB_NAME");
  }

  /**
   * \brief get upload id
   *
   * \param $upload_info - The string to search in.
   *
   * \return upload Id, false on failure.
   */
  function get_upload_id($upload_info) {
    $upload_id = 0;
    preg_match("/UploadPk is: '(\d+)'/", $upload_info, $matches);
    $upload_id = $matches[1];
    if (!$upload_id) return false;
    else return $upload_id;
  }


  /**
   * \brief check if the agent you specify is complete
   *
   * \param $agent_name agent name, such as: ununpack, nomos, etc
   * \param $upload_id upload id
   *
   * \return 1 as complete sucessfully, other as failed or not scheduled
   */
  function check_agent_status($agent_name, $upload_id) {
    global $PG_CONN;
    $ars_table_name = $agent_name."_ars";
    $count = 0;
    $sql = "SELECT count(*) FROM $ars_table_name where upload_fk = $upload_id and ars_success=true;";
    // print "sql is:$sql\n";
    $result = pg_query($PG_CONN, $sql);
    $count = pg_num_rows($result);
    pg_free_result($result);
    if(1 == $count)  return 1;
    else return 0;
  }

  /**
   * \brief add a admin user, default fossy/fosssy
   */
  function add_user($user='fossy', $password='fossy') {
    global $PG_CONN;
    /* User does not exist.  Create it. */
    $Seed = rand() . rand();
    $Hash = sha1($Seed . $password);
    $sql = "SELECT * FROM users WHERE user_name = '$user';";
    $result = pg_query($PG_CONN, $sql);
    $row0 = pg_fetch_assoc($result);
    pg_free_result($result);
    if (empty($row0['user_name'])) {
      /* User does not exist.  Create it. */
      $SQL = "INSERT INTO users (user_name,user_desc,user_seed,user_pass," .
        "user_perm,user_email,email_notify,root_folder_fk)
        VALUES ('$user','Default Administrator','$Seed','$Hash',10,'$password','y',1);";
      // $text = _("*** Created default administrator: '$user' with password '$password'.");
      $result = pg_query($PG_CONN, $SQL);
      pg_free_result($result);
    }
  }

  /**
   * \brief replace default repo with new repo
   */
  function replace_repo() {
    global $SYSCONF_DIR;
    global $REPO_NAME;
    $file_list = array("$SYSCONF_DIR/mods-enabled/wget_agent/wget_agent.conf",
                       "$SYSCONF_DIR/mods-enabled/ununpack/ununpack.conf");
    foreach ($file_list as $file_name) {
      exec("sed s/repository/$REPO_NAME/ $file_name > /tmp/tmp.conf");
      exec("sudo mv /tmp/tmp.conf $file_name");
      exec("sudo chown fossy $file_name");
      exec("sudo chgrp fossy $file_name");
    }
    if (is_dir($REPO_NAME)) exec("sudo chmod 2770 $REPO_NAME"); /// change mode to 2770
  }

  /**
   * \brief rollback to default repo
   */
  function rollback_repo() {
    global $SYSCONF_DIR;
    global $REPO_NAME;
    $file_list = array("$SYSCONF_DIR/mods-enabled/wget_agent/wget_agent.conf",
                       "$SYSCONF_DIR/mods-enabled/ununpack/ununpack.conf");
    foreach ($file_list as $file_name) {
      exec("sed s/$REPO_NAME/repository/ $file_name > /tmp/tmp.conf");
      exec("sudo mv /tmp/tmp.conf $file_name");
      exec("sudo chown fossy $file_name");
      exec("sudo chgrp fossy $file_name");
    }
  }

  /**
   * \brief stop the running scheduler and start new schduler with new sysconfdir 
   */
  function scheduler_operation() {
    global $SYSCONF_DIR;
    $scheduler_path = "$SYSCONF_DIR/mods-enabled/scheduler/agent/fo_scheduler";
    exec("sudo $scheduler_path -k");  // kill the running scheduler
    exec("sudo $scheduler_path --daemon --reset --verbose=1 -c $SYSCONF_DIR"); // start the scheduler
  }

?>
