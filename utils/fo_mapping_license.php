#!/usr/bin/php

<?php

/**
 * \brief replase the old license shortname with new license shortname
 */

require_once("../src/lib/php/common.php");

/**
$Usage = "Usage: " . basename($argv[0]) . "
  -s old license
  -t new license
  -h  help 
  ";

$options = getopt("s:t:h");

if (empty($options) || !is_array($options))
{
  print $Usage;
  return 1;
}
*/

/** PLEASE PUT THE LICENSE MAP THERE */
/** will replace old_shortname with new_shortname in the later process */
$shortname_array = array(
    /* old_shortname => new_shortname */
    'GPL_V3' => 'GPL-3.0', 
    'GPL_V2' => 'GPL-2.0', 
    'GPL_V2.0' => 'GPL-2.0'
    );

$sysconfig = "/usr/local/etc/fossology/";
$PG_CONN = DBconnect($sysconfig);

foreach ($shortname_array as $old_shortname => $new_shortname)
{
  $old_rf_pk = check_shortname($old_shortname);
  $new_rf_pk = check_shortname($new_shortname);
  if (-1 != $old_rf_pk && -1 != $new_rf_pk)
  {
    $res = update_license($old_rf_pk, $new_rf_pk);
    if (0 == $res) 
    {
      print "update successfully, substitute rf_id from $old_rf_pk to $new_rf_pk.\n";
    }
  }
}

print "End!\n";

/** 
 * \brief check if the shortname is existing in license_ref table
 * 
 * \param $shortname - the license which you want to check 
 *
 * \return rf_id on existing; -1 on not existing
 */
function check_shortname($shortname)
{
  global $PG_CONN;
  $sql = "SELECT rf_pk from license_ref where rf_shortname = '$shortname'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);
  if ($row['rf_pk']) return $row['rf_pk'];
  else return -1;
}

/**
 * \brief update license from old to new
 * 1) update license_file set rf_fk=new_rf_pk where rf_fk=old_rf_pk
 * 2) update license_file_audit set rf_fk=new_rf_pk where rf_fk=old_rf_pk
 * 3) delete from license_ref where rf_pk=old_rf_pk
 *
 * \param $old_rf_pk - the rf_pk of old license shortname
 * \param $new_rf_pk - the rf_pk of new license shortname
 * 
 * \return 0 on sucess; 1 on failure
 */
function update_license($old_rf_pk, $new_rf_pk)
{
  global $PG_CONN;

  /** transaction begin */
  $sql = "BEGIN;";
  $result_begin = pg_query($PG_CONN, $sql);
  DBCheckResult($result_begin, $sql, __FILE__, __LINE__);
  pg_free_result($result_begin);

  /* Update license_file table, substituting the old_rf_id  with the new_rf_id */
  $sql = "update license_file set rf_fk = $new_rf_pk where rf_fk = $old_rf_pk;";
  $result_license_file = pg_query($PG_CONN, $sql);
  DBCheckResult($result_license_file, $sql, __FILE__, __LINE__);
  pg_free_result($result_license_file);

  /* Update license_file_audit table, substituting the old_rf_id  with the new_rf_id */
  $sql = "update license_file_audit set rf_fk = $new_rf_pk where rf_fk = $old_rf_pk;";
  $result_license_file_audit = pg_query($PG_CONN, $sql);
  DBCheckResult($result_license_file_audit, $sql, __FILE__, __LINE__);
  pg_free_result($result_license_file_audit);


  /** delete data of old license */
  $sql = "DELETE FROM license_ref where rf_pk = $old_rf_pk;";
  $result_delete = pg_query($PG_CONN, $sql);
  DBCheckResult($result_delete, $sql, __FILE__, __LINE__);
  pg_free_result($result_delete);

  /** transaction end */
  $sql = "COMMIT;";
  $result_end = pg_query($PG_CONN, $sql);
  DBCheckResult($result_end, $sql, __FILE__, __LINE__);
  pg_free_result($result_end);

  return 0;
}


?>
