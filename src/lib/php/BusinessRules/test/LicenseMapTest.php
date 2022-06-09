<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;

class LicenseMapTest extends \PHPUnit\Framework\TestCase
{
  /** @var DbManager $dbManager
   * DbManager */
  private $dbManager;
  /** @var int $groupId
   * Test group */
  private $groupId = 101;


  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->testDb->createPlainTables(
      array(
        'license_ref',
        'license_map',
        'obligation_ref',
        'obligation_map',
        'obligation_candidate_map'
      ));
    $this->dbManager = $this->testDb->getDbManager();
    $this->dbManager->queryOnce("CREATE TABLE license_candidate (group_fk integer) INHERITS (license_ref)");
    $this->dbManager->insertTableRow('license_map',array('license_map_pk'=>0,'rf_fk'=>2,'rf_parent'=>1,'usage'=>LicenseMap::CONCLUSION));
    $this->dbManager->insertTableRow('license_ref',array('rf_pk'=>1,'rf_shortname'=>'One','rf_fullname'=>'One-1'));
    $this->dbManager->insertTableRow('license_ref',array('rf_pk'=>2,'rf_shortname'=>'Two','rf_fullname'=>'Two-2'));
    $this->dbManager->insertTableRow('license_candidate',
            array('rf_pk'=>3,'rf_shortname'=>'Three','rf_fullname'=>'Three-3','group_fk'=>$this->groupId));
    $this->dbManager->insertTableRow('obligation_ref',
      array(
        'ob_pk' => 2,
        'ob_type' => 'Obligation',
        'ob_topic' => 'Obligation-1',
        'ob_text' => 'Obligation text',
        'ob_classification' => 'white',
        'ob_modifications' => 'Yes',
        'ob_comment' => 'Obligation comment',
        'ob_active' => true,
        'ob_text_updatable' => false,
        'ob_md5' => '0ffdddc657a16b95894437b4af736102'
      ));
    $this->dbManager->insertTableRow('obligation_map',
      array(
        'om_pk' => 2,
        'ob_fk' => 2,
        'rf_fk' => 2
      ));
    $this->dbManager->insertTableRow('obligation_candidate_map',
      array(
        'om_pk' => 2,
        'ob_fk' => 2,
        'rf_fk' => 3
      ));
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
  }

  function testProjectedIdOfUnmappedIdIsIdItself()
  {
    $licenseMap = new LicenseMap($this->dbManager, $this->groupId);
    $licenseId = 1;
    assertThat($licenseMap->getProjectedId($licenseId),is($licenseId));
  }

  function testProjectedIdOfCandidatesAreRecognized()
  {
    $licenseMap = new LicenseMap($this->dbManager, $this->groupId);
    $licenseId = 3;
    assertThat($licenseMap->getProjectedId($licenseId),is($licenseId));
  }

  function testProjectedIdOfUnmappedIdIsParentId()
  {
    $licenseMap = new LicenseMap($this->dbManager, $this->groupId);
    $licenseMap->getGroupId();
    assertThat($licenseMap->getProjectedId(2),is(1));
  }

  function testProjectedShortNameOfMappedId()
  {
    $licenseMap = new LicenseMap($this->dbManager, $this->groupId);
    assertThat($licenseMap->getProjectedShortName(2),is('One'));
  }

  function testObligationForLicense()
  {
    $licenseMap = new LicenseMap($this->dbManager, $this->groupId);
    assertThat($licenseMap->getObligationsForLicenseRef(2), contains(2));
  }

  function testObligationForUnassignedLicense()
  {
    $licenseMap = new LicenseMap($this->dbManager, $this->groupId);
    assertThat($licenseMap->getObligationsForLicenseRef(1), is(emptyArray()));
  }

  function testObligationForCandidateLicense()
  {
    $licenseMap = new LicenseMap($this->dbManager, $this->groupId);
    assertThat($licenseMap->getObligationsForLicenseRef(3, true), contains(2));
  }

  function testProjectedIdOfMappedIdIsIdItselfIfTrivialMap()
  {
    $licenseMap = new LicenseMap($this->dbManager, $this->groupId, LicenseMap::TRIVIAL);
    assertThat($licenseMap->getProjectedId(2),is(2));
  }

  function testGetTopLevelLicenseRefs()
  {
    $licenseMap = new LicenseMap($this->dbManager, $this->groupId, LicenseMap::CONCLUSION);
    $topLevelLicenses = $licenseMap->getTopLevelLicenseRefs();
    assertThat($topLevelLicenses,hasItemInArray(new LicenseRef(1,'One','One-1')));
    assertThat($topLevelLicenses, not(hasKeyInArray(2)));
  }


  public function testGetMappedLicenseRefView()
  {
    $this->testDb = new TestPgDb();
    $this->testDb->createPlainTables(array('license_ref','license_map'));
    $this->dbManager = $this->testDb->getDbManager();
    $this->dbManager->queryOnce("CREATE TABLE license_candidate (group_fk integer) INHERITS (license_ref)");
    $this->dbManager->insertTableRow('license_map',array('license_map_pk'=>0,'rf_fk'=>2,'rf_parent'=>1,'usage'=>LicenseMap::CONCLUSION));
    $this->dbManager->insertTableRow('license_ref',array('rf_pk'=>1,'rf_shortname'=>'One','rf_fullname'=>'One-1'));
    $this->dbManager->insertTableRow('license_ref',array('rf_pk'=>2,'rf_shortname'=>'Two','rf_fullname'=>'Two-2'));
    $this->dbManager->insertTableRow('license_candidate',
            array('rf_pk'=>3,'rf_shortname'=>'Three','rf_fullname'=>'Three-3','group_fk'=>$this->groupId));
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();

    $view = LicenseMap::getMappedLicenseRefView(LicenseMap::CONCLUSION);
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt,$view);
    $res = $this->dbManager->execute($stmt);
    $map = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    assertThat($map,is(arrayWithSize(3)));
    $expected = array(
        array('rf_origin'=>1, 'rf_pk'=>1,'rf_shortname'=>'One','rf_fullname'=>'One-1'),
        array('rf_origin'=>2, 'rf_pk'=>1,'rf_shortname'=>'One','rf_fullname'=>'One-1'),
        array('rf_origin'=>3, 'rf_pk'=>3,'rf_shortname'=>'Three','rf_fullname'=>'Three-3')
    );
    assertThat($map,containsInAnyOrder($expected));
  }

  public function testFullMap()
  {
    $licenseMap = new LicenseMap($this->dbManager, $this->groupId+1, LicenseMap::CONCLUSION, true);
    $map = \Fossology\Lib\Test\Reflectory::getObjectsProperty($licenseMap, 'map');
    assertThat($map,hasItemInArray(array('rf_fk'=>1,'parent_shortname'=>'One','rf_parent'=>1)));
    assertThat($map,hasItemInArray(array('rf_fk'=>2,'parent_shortname'=>'One','rf_parent'=>1)));
  }

  public function testFullMapWithCandidates()
  {
    $licenseMap = new LicenseMap($this->dbManager, $this->groupId, LicenseMap::CONCLUSION, true);
    $map = \Fossology\Lib\Test\Reflectory::getObjectsProperty($licenseMap, 'map');
    assertThat($map,hasItemInArray(array('rf_fk'=>1,'parent_shortname'=>'One','rf_parent'=>1)));
    assertThat($map,hasItemInArray(array('rf_fk'=>2,'parent_shortname'=>'One','rf_parent'=>1)));
    assertThat($map,hasItemInArray(array('rf_fk'=>3,'parent_shortname'=>'Three','rf_parent'=>3)));
  }
}
