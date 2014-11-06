<?php
/*
Copyright (C) 2014, Siemens AG
Author: Andreas Würl, Johannes Najjar

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

namespace Fossology\Lib\Dao;

use DateTime;
use Fossology\Lib\BusinessRules\NewestEditedLicenseSelector;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\LicenseDecision\LicenseDecision;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;
use Mockery\MockInterface;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

class ClearingDaoTest extends \PHPUnit_Framework_TestCase
{
  /** @var  TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var NewestEditedLicenseSelector|MockInterface */
  private $licenseSelector;
  /** @var UploadDao|MockInterface */
  private $uploadDao;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var int */
  private $now;
  /** @var array */
  private $items;


  public function setUp()
  {
    $this->licenseSelector = M::mock(NewestEditedLicenseSelector::classname());
    $this->uploadDao = M::mock(UploadDao::classname());

    $logger = new Logger('default');
    $logger->pushHandler(new ErrorLogHandler());

    $this->testDb = new TestPgDb();
    $this->dbManager = &$this->testDb->getDbManager();

    $this->clearingDao = new ClearingDao($this->dbManager, $this->licenseSelector, $this->uploadDao);

    $this->testDb->createPlainTables(
        array(
            'clearing_decision',
            'clearing_decision_events',
            'clearing_decision_type',
            'license_decision_event',
            'license_decision_type',
            'clearing_licenses',
            'license_ref',
            'users',
            'group_user_member',
            'uploadtree'
        ));

    $this->testDb->insertData(
        array(
            'clearing_decision_type',
            'license_decision_type'
        ));

    $userArray = array(
        array('myself', 1),
        array('in_same_group', 2),
        array('in_trusted_group', 3));
    foreach ($userArray as $ur)
    {
      $this->dbManager->insertInto('users', 'user_name, root_folder_fk', $ur);
    }

    $gumArray = array(
        array(1, 1, 0),
        array(1, 2, 0),
        array(2, 3, 0),
        array(3, 4, 0)
    );
    foreach ($gumArray as $params)
    {
      $this->dbManager->insertInto('group_user_member', $keys = 'group_fk, user_fk, group_perm', $params, $logStmt = 'insert.gum');
    }

    $refArray = array(
        array(401, 'FOO', 'foo text'),
        array(402, 'BAR', 'bar text'),
        array(403, 'BAZ', 'baz text'),
        array(404, 'QUX', 'qux text')
    );
    foreach ($refArray as $params)
    {
      $this->dbManager->insertInto('license_ref', 'rf_pk, rf_shortname, rf_text', $params, $logStmt = 'insert.ref');
    }

    $modd = 536888320;
    $modf = 33188;

    /*                          (pfile,item,lft,rgt)
      upload101:   Afile         (201, 301,  1,  2)
                   Bfile         (202, 302,  3,  4)
      upload102:   Afile         (201, 303,  1,  2)
                   A-dir/        (  0, 304,  3,  6)
                   A-dir/Afile   (201, 305,  4,  5)
                   Bfile         (202, 306,  7,  8)
    */
    $this->items = array(
        301=>array(101, 301, 201, $modf, 1, 2, "Afile"),
        302=>array(101, 302, 202, $modf, 3, 4, "Bfile"),
        303=>array(102, 303, 201, $modf, 1, 2, "Afile"),
        304=>array(102, 304,   0, $modd, 3, 6, "A-dir"),
        305=>array(102, 305, 201, $modf, 4, 5, "Afile"),
        306=>array(102, 306, 202, $modf, 7, 8, "Bfile"),
    );
    foreach ($this->items as $ur)
    {
      $this->dbManager->insertInto('uploadtree', 'upload_fk,uploadtree_pk,pfile_fk,ufile_mode,lft,rgt,ufile_name', $ur);
    }
    $this->now = time();
  }

  private function buildProposals($licProp,$i=0)
  {
    foreach($licProp as $lp){
      $i++;
      list($item,$user,$rf,$isRm,$t) = $lp;
      $this->dbManager->insertInto('license_decision_event',
          'license_decision_event_pk, uploadtree_fk, user_fk, rf_fk, is_removed, type_fk, date_added',
          array($i,$item,$user,$rf,$isRm,1, $this->getMyDate($this->now+$t)));
    }
  }  

  private function buildDecisions($cDec,$j=0)
  {
    foreach($cDec as $cd){
      $j++;
      list($item,$user,$type,$t,$scope) = $cd;
      $this->dbManager->insertInto('clearing_decision',
          'clearing_decision_pk, uploadtree_fk, pfile_fk, user_fk, decision_type, date_added, scope',
          array($j,$item,$this->items[$item][2],$user,$type, $this->getMyDate($this->now+$t),$scope));
    }
  }
    
  function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;
  }

  private function getMyDate($in)
  {
    $date = new DateTime();
    return $date->setTimestamp($in)->format('Y-m-d H:i:s T');
  }

  private function getMyDate2($in)
  {
    $date = new DateTime();
    return $date->setTimestamp($in);
  }

  public function testCurrentLicenseDecisionViaGroupMembershipShouldBeSymmetric()
  {
    $this->buildProposals(array(
        array(301,1,401,false,-99),
        array(301,1,402,true,-98)
    ));
    $this->buildDecisions(array(
        array(301,1,DecisionTypes::IDENTIFIED,-90,DecisionScopes::REPO)
    ));
    list($added1, $removed1) = $this->clearingDao->getCurrentLicenseDecisions(1, 301);
    list($added2, $removed2) = $this->clearingDao->getCurrentLicenseDecisions(2, 301);
    assertThat($added1, is(equalTo($added2)));
    assertThat($removed1, is(equalTo($removed2)));
  }

  function testWip()
  {
    $this->buildProposals(array(
        array(301,1,401,false,-99),
        array(301,1,402,false,-98),
        array(301,1,401,true,-97),
    ));
    $this->buildDecisions(array(
        array(301,1,DecisionTypes::IDENTIFIED,-90,DecisionScopes::REPO)
    ));
    $watchThis = $this->clearingDao->isDecisionWip(301, 1);
    assertThat($watchThis,is(FALSE));
    $watchOther = $this->clearingDao->isDecisionWip(303, 1);
    assertThat($watchOther,is(FALSE));
    $this->buildProposals(array(
        array(301,1,403,false,-89),
    ),3);
    $this->clearingDao->markDecisionAsWip(301, 1);
    $watchThisNow = $this->clearingDao->isDecisionWip(301, 1);
    assertThat($watchThisNow,is(TRUE));
    $watchOtherNow = $this->clearingDao->isDecisionWip(303, 1);
    assertThat($watchOtherNow,is(FALSE));
  }
  
  
}
