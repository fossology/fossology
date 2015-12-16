<?php
/*
Copyright (C) 2014-2015, Siemens AG

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

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Test\TestPgDb;

class LicenseMapTest extends \PHPUnit_Framework_TestCase
{
  /** @var DbManager */
  private $dbManager;
  /** @var int */
  private $groupId = 101;


  protected function setUp()
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
  }

  protected function tearDown()
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
    assertThat($map,is(arrayWithSize(2)));
    $expected = array(
        array('rf_origin'=>1, 'rf_pk'=>1,'rf_shortname'=>'One','rf_fullname'=>'One-1'),
        array('rf_origin'=>2, 'rf_pk'=>1,'rf_shortname'=>'One','rf_fullname'=>'One-1')
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