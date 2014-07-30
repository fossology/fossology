<?php
/***********************************************************
 Copyright (C) 2014 Siemens AG

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

function setActiveGroup($verbose)
{
  global $dbManager;
  $stmt = __METHOD__;
  $sql = "SELECT user_pk,group_pk FROM users LEFT JOIN groups ON group_name=user_name WHERE group_id IS NULL";
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
  $sql = "SELECT user_fk,min(group_fk) group_id FROM group_user_member WHERE user_fk=$1";
  $updateStmt = __METHOD__.'.update';
  $dbManager->prepare($updateStmt,"UPDATE users SET group_id=$2 WHERE user_pk=$1");
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

function blowAudit($verbose)
{
  global $dbManager;
  
  $stmt = __METHOD__.".blowAudit";
  $sql = "SELECT min(ut.uploadtree_pk) uploadtree_id, lfa.user_fk user_id, lfa.date date_added, lfa.reason reportinfo,
                 lf.rf_fk license_id, ut.pfile_fk pfile_id
          FROM license_file_audit lfa INNER JOIN license_file lf ON lfa.fl_fk=lf.fl_pk
               INNER JOIN uploadtree ut ON lf.pfile_fk=ut.pfile_fk
          GROUP BY lfa.user_fk, lfa.date, lfa.reason, lf.rf_fk, ut.pfile_fk";
  // ensure that these results where not inserted before
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
  $scope = $dbManager->getSingleRow('SELECT scope_pk FROM clearing_decision_scopes WHERE meaning=$1',array('global'));
  $scope = $scope['scope_pk'];
  $type = $dbManager->getSingleRow('SELECT type_pk FROM clearing_decision_types WHERE meaning=$1',array('userDecision'));
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
 * @param type $verbose tell about decisions
 * @return int number of inserted decisions
 */
function migrate_25_26($verbose)
{
  setActiveGroup($verbose);
  $nInsertedDecisions = blowAudit($verbose);
  return $nInsertedDecisions;
}
