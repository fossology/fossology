<?php
/*
SPDX-FileCopyrightText: © 2014 Siemens AG
 SPDX-FileCopyrightText: © 2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file dbmigrate_2.5-2.6.php
 * @brief This file is called by fossinit.php to migrate from
 *        a 2.5 database to 2.6.
 *        Specifically, this is to set active group for current users,
 *        insert clearing decisions from license_file_audit table
 *        to support 2.6 decisions.
 *
 * This should be called after fossinit calls apply_schema and
 * table license_file_audit and clearing_decision exists.
 **/

/**
 * @brief Set active group for existing users from group_user_member table
 * @return void
 */
function setActiveGroup($verbose)
{
  global $dbManager;
  $stmt = __METHOD__;
  $sql = "SELECT user_pk,group_pk FROM users LEFT JOIN groups ON group_name=user_name WHERE group_fk IS NULL";
  $dbManager->prepare($stmt,$sql);
  $res = $dbManager->execute($stmt);
  if (pg_num_rows($res)==0)
  {
    pg_free_result($res);
    return 0;
  }
  $userGroupMap = pg_fetch_all($res);
  pg_free_result($res);
  $selectStmt = __METHOD__.'.select';
  $sql = "SELECT user_fk,min(group_fk) group_fk FROM group_user_member WHERE user_fk=$1";
  $updateStmt = __METHOD__.'.update';
  $dbManager->prepare($updateStmt,"UPDATE users SET group_fk=$2 WHERE user_pk=$1");
  foreach($userGroupMap as $row)
  {
    if (!empty($row['group_pk']))
    {
      pg_free_result( $dbManager->execute($updateStmt,$row) );
      continue;
    }
    $rowrow = $dbManager->getSingleRow($sql,array($row['user_pk']),$selectStmt);
    pg_fetch_result($dbManager->execute($updateStmt,$rowrow) );
  }
}

/**
 * @brief Copy decisions from license_file_audit table to clearing_decision
 * @global type $dbManager
 * @param boolean $verbose tell about decisions
 * @return int number of inserted decisions
 */
function blowAudit($verbose)
{
  global $dbManager;
  
  $stmt = __METHOD__.".blowAudit";
  $sql = "SELECT min(ut.uploadtree_pk) uploadtree_id, lfa.user_fk user_id, lfa.date date_added, lfa.reason reportinfo,
                 lfa.rf_fk license_id, ut.pfile_fk pfile_id
          FROM license_file_audit lfa INNER JOIN license_file lf ON lfa.fl_fk=lf.fl_pk
               INNER JOIN uploadtree ut ON lf.pfile_fk=ut.pfile_fk
          GROUP BY lfa.user_fk, lfa.date, lfa.reason, lfa.rf_fk, ut.pfile_fk";
  // ensure that these results were not inserted before
  $sql = "SELECT pureInserts.* FROM ($sql) pureInserts
            LEFT JOIN clearing_decision cd
              ON pureInserts.uploadtree_id=cd.uploadtree_fk and pureInserts.user_id=cd.user_fk
                AND pureInserts.date_added=cd.date_added and pureInserts.reportinfo=cd.reportinfo
          WHERE cd.clearing_pk is null";
  $dbManager->prepare($stmt,$sql);
  $res = $dbManager->execute($stmt);
  if (pg_num_rows($res)==0)
  {
    if ($verbose)
    {
      echo "no unknown decision\n";
    }
    $dbManager->freeResult($res);
    return 0;
  }
  $auditDecisions = pg_fetch_all($res);
  $dbManager->freeResult($res);
  $scope = $dbManager->getSingleRow('SELECT scope_pk FROM clearing_decision_scope WHERE meaning=$1',array('global'));
  $scope = $scope['scope_pk'];
  $type = $dbManager->getSingleRow('SELECT type_pk FROM clearing_decision_type WHERE meaning=$1',array('userDecision'));
  $type = $scope['type_pk'];
  $dbManager->prepare($stmt='insertClearingDecision',
          'INSERT INTO clearing_decision'
          . ' (uploadtree_fk,pfile_fk,user_fk,type_fk,scope_fk,comment,reportinfo,date_added) VALUES ($1,$2,$3,$4,$5,$6,$7,$8)'
          . ' RETURNING clearing_pk');
  $dbManager->prepare($stmt='insertClearingLicense','INSERT INTO clearing_licenses'
          . ' (clearing_fk,rf_fk) VALUES ($1,$2)');
  $pfiles = array();
  foreach($auditDecisions as $audit)
  {
   $cd = $dbManager->execute('insertClearingDecision',
           array($audit['uploadtree_id'],$audit['pfile_id'] ,$audit['user_id'],$type,$scope,'migrated',$audit['reportinfo'],$audit['date_added']));
   $clearing = $dbManager->fetchArray($cd);
   $clearingId = $clearing['clearing_pk'];
   $dbManager->freeResult($cd);
   $dbManager->freeResult( $dbManager->execute('insertClearingLicense',array($clearingId,$audit['license_id'])) );
   $pfiles[$audit['pfile_id']] = 0;
  }
  if ($verbose)
  {
   echo "inserted ".count($auditDecisions)." clearing decisions for ".count($pfiles)." files\n";
  }
  return count($auditDecisions);
}


/**
 * @global type $dbManager
 * @param boolean $verbose tell about decisions
 * @return int number of inserted decisions
 */
function migrate_25_26($verbose)
{
  setActiveGroup($verbose);
  //$nInsertedDecisions = blowAudit($verbose);
  $nInsertedDecisions = 0;
  return $nInsertedDecisions;
}
