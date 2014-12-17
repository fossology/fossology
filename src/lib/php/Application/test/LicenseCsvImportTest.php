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

namespace Fossology\Lib\Application;

use Fossology\Lib\Data\LicenseUsageTypes;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestLiteDb;
use Mockery as M;

class LicenseCsvImportTest extends \PHPUnit_Framework_TestCase
{
  public function setUp()
  {
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  public function tearDown() {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    M::close();
  }
  
  public function testGetKeyFromShortname()
  {
    $testDb = new TestLiteDb();
    $testDb->createPlainTables(array('license_ref'));
    $shortname = 'licA';
    $knownId = 101;
    /** @var DbManager */
    $dbManager = &$testDb->getDbManager();
    $dbManager->insertTableRow('license_ref', array('rf_pk'=>$knownId,'rf_shortname'=>$shortname));
    $licenseCsvImport = new LicenseCsvImport($dbManager);
    $reflection = new \ReflectionClass($licenseCsvImport); 
    $method = $reflection->getMethod('getKeyFromShortname');
    $method->setAccessible(true);
    $method->invoke($licenseCsvImport,$shortname);
    assertThat($method->invoke($licenseCsvImport,$shortname), is($knownId));
    assertThat($method->invoke($licenseCsvImport,"no $shortname"), is(false));
  }
  
  public function testHandleCsvLicense()
  {
    $dbManager = M::mock('Fossology\Lib\Db\DbManager');
    $licenseCsvImport = new LicenseCsvImport($dbManager);
    $reflection = new \ReflectionClass($licenseCsvImport); 
    $nkMap = $reflection->getProperty('nkMap');
    $nkMap->setAccessible(true);
    $nkMap->setValue($licenseCsvImport,array('licA'=>101,'licB'=>false,'licC'=>false,'licE'=>false));
    
    $method = $reflection->getMethod('handleCsvLicense');
    $method->setAccessible(true);

    $dbManager->shouldReceive('getSingleRow')
            ->with('SELECT rf_shortname FROM license_ref WHERE rf_md5=md5($1)',anything())
            ->times(3)
            ->andReturn(false,false,array('rf_shortname'=>'licD'));
    $dbManager->shouldReceive('prepare');
    $dbManager->shouldReceive('execute');
    $dbManager->shouldReceive('freeResult');
    $dbManager->shouldReceive('fetchArray')->andReturn(array('rf_pk'=>102));
    $dbManager->shouldReceive('insertTableRow')->withArgs(array('license_map',
        array('rf_fk'=>102,'rf_parent'=>101,'usage'=>LicenseUsageTypes::CONCLUSION)))->once();
    
    $returnB = $method->invoke($licenseCsvImport,
            array('shortname'=>'licB','fullname'=>'liceB','text'=>'txB','url'=>'','notes'=>'','source'=>'',
                'parent_shortname'=>'licA'));
    assertThat($returnB, is("Inserted 'licB' in DB with conclusion 'licA'"));
    
    $returnC = $method->invoke($licenseCsvImport,
            array('shortname'=>'licC','fullname'=>'liceC','text'=>'txC','url'=>'','notes'=>'','source'=>'',
                'parent_shortname'=>null));
    assertThat($returnC, is("Inserted 'licC' in DB"));
    
    $returnA = $method->invoke($licenseCsvImport,
            array('shortname'=>'licA','fullname'=>'liceB','text'=>'txB','url'=>'','notes'=>'','source'=>'',
                'parent_shortname'=>null));
    assertThat($returnA, is("Shortname 'licA' already in DB"));

    $returnE = $method->invoke($licenseCsvImport,
            array('shortname'=>'licE','fullname'=>'liceE','text'=>'txD','url'=>'','notes'=>'','source'=>'',
                'parent_shortname'=>null));
    assertThat($returnE, is("Text of 'licE' already used for 'licD'"));
  }
  
  public function testHandleHeadCsv()
  {
    $dbManager = M::mock('Fossology\Lib\Db\DbManager');
    $licenseCsvImport = new LicenseCsvImport($dbManager);
    $reflection = new \ReflectionClass($licenseCsvImport);
    
    $method = $reflection->getMethod('handleHeadCsv');
    $method->setAccessible(true);
  
    assertThat($method->invoke($licenseCsvImport,array('shortname','foo','text','fullname','notes','bar')),
            is( array('shortname'=>0,'fullname'=>3,'text'=>2,'parent_shortname'=>false,'url'=>false,'notes'=>4,'source'=>false) ) );
    
    assertThat($method->invoke($licenseCsvImport,array('Short Name','URL','text','fullname','notes','Foreign ID')),
            is( array('shortname'=>0,'fullname'=>3,'text'=>2,'parent_shortname'=>false,'url'=>1,'notes'=>4,'source'=>5) ) );
  }
   
  /**
   * @expectedException Exception
   */
  public function testHandleHeadCsv_missingMandidatoryKey()
  {
    $dbManager = M::mock('Fossology\Lib\Db\DbManager');
    $licenseCsvImport = new LicenseCsvImport($dbManager);
    $reflection = new \ReflectionClass($licenseCsvImport);
    
    $method = $reflection->getMethod('handleHeadCsv');
    $method->setAccessible(true);
  
    $method->invoke($licenseCsvImport,array('shortname','foo','text'));
  }
  
  public function testSetDelimiter()
  {
    $dbManager = M::mock('Fossology\Lib\Db\DbManager');
    $licenseCsvImport = new LicenseCsvImport($dbManager);
    $reflection = new \ReflectionClass($licenseCsvImport); 
    $delimiter = $reflection->getProperty('delimiter');
    $delimiter->setAccessible(true);
    
    $licenseCsvImport->setDelimiter('|');
    assertThat($delimiter->getValue($licenseCsvImport),is('|'));
    
    $licenseCsvImport->setDelimiter('<>');
    assertThat($delimiter->getValue($licenseCsvImport),is('<'));
  }
  
  public function testSetEnclosure()
  {
    $dbManager = M::mock('Fossology\Lib\Db\DbManager');
    $licenseCsvImport = new LicenseCsvImport($dbManager);
    $reflection = new \ReflectionClass($licenseCsvImport); 
    $enclosure = $reflection->getProperty('enclosure');
    $enclosure->setAccessible(true);
    
    $licenseCsvImport->setEnclosure('|');
    assertThat($enclosure->getValue($licenseCsvImport),is('|'));
    
    $licenseCsvImport->setEnclosure('<>');
    assertThat($enclosure->getValue($licenseCsvImport),is('<'));
  }
  
  public function testHandleCsv()
  {
    $dbManager = M::mock('Fossology\Lib\Db\DbManager');
    $licenseCsvImport = new LicenseCsvImport($dbManager);
    $reflection = new \ReflectionClass($licenseCsvImport);
    $method = $reflection->getMethod('handleCsv');
    $method->setAccessible(true);

    $method->invoke($licenseCsvImport,array('shortname','foo','text','fullname','notes'));
    $headRow = $reflection->getProperty('headrow');
    $headRow->setAccessible(true);
    assertThat($headRow->getValue($licenseCsvImport),is(notNullValue()));
    
    $dbManager->shouldReceive('getSingleRow')->with('SELECT rf_shortname FROM license_ref WHERE rf_md5=md5($1)',anything())->andReturn(false);
    $dbManager->shouldReceive('prepare');
    $dbManager->shouldReceive('execute');
    $dbManager->shouldReceive('freeResult');
    $dbManager->shouldReceive('fetchArray')->andReturn(array('rf_pk'=>101));
    
    $nkMap = $reflection->getProperty('nkMap');
    $nkMap->setAccessible(true);
    $nkMap->setValue($licenseCsvImport,array('licA'=>false));
    $method->invoke($licenseCsvImport,array('licA','bar','txA','liceA','noteA'));
    assertThat($nkMap->getValue($licenseCsvImport),is(array('licA'=>101)));
  }

}
 