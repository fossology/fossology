<?php
/*
Copyright (C) 2014, Siemens AG

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

use Fossology\Lib\Test\TestPgDb;

class LicenseMapTest extends \PHPUnit_Framework_TestCase
{
  /** @var DbManager */
  private $dbManager;
  /** @var int */
  private $groupId = 101;


  public function setUp()
  {
    $this->testDb = new TestPgDb();
    $this->testDb->createPlainTables(array('license_ref','license_map'));
    $this->dbManager = $this->testDb->getDbManager();
    $this->dbManager->queryOnce("CREATE TABLE license_candidate (group_fk integer) INHERITS (license_ref)");
    $this->dbManager->insertTableRow('license_map',array('rf_fk'=>2,'rf_parent'=>1,'usage'=>LicenseMap::CONCLUSION));
    $this->dbManager->insertTableRow('license_ref',array('rf_pk'=>1,'rf_shortname'=>'One'));
    $this->dbManager->insertTableRow('license_ref',array('rf_pk'=>2,'rf_shortname'=>'Two'));
    $this->dbManager->insertTableRow('license_candidate',array('rf_pk'=>3,'rf_shortname'=>'Three','group_fk'=>$this->groupId));
  }

  function tearDown()
  {
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
  
  function testProjectedShortNameOfUnmappedId()
  {
    $licenseMap = new LicenseMap($this->dbManager, $this->groupId);
    assertThat($licenseMap->getProjectedShortName(2),is('One'));
  }
  
}
 