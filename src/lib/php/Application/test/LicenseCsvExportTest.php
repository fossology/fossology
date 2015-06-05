<?php
/*
Copyright (C) 2015, Siemens AG

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

use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestLiteDb;
use Mockery as M;

class LicenseCsvExportTest extends \PHPUnit_Framework_TestCase
{
  public function setUp()
  {
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  public function tearDown() {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    M::close();
  }
  
 
  public function testCreateCsv()
  {
    $testDb = new TestLiteDb();
    $testDb->createPlainTables(array('license_ref','license_map'));
    $dbManager = $testDb->getDbManager();
    $licenses = array();
    for($i=1;$i<4;$i++){
      $licenses[$i] = array('rf_pk'=>$i,'rf_shortname'=>'lic'.$i,'rf_fullname'=>'lice'.$i,
          'rf_text'=>'text'.$i,'rf_url'=>$i.$i,'rf_notes'=>'note'.$i,'rf_source'=>'s'.$i,
          'rf_detector_type'=>1);
      $dbManager->insertTableRow('license_ref', $licenses[$i]);
    }

    $dbManager->insertTableRow('license_map', array('rf_fk'=>3,'rf_parent'=>1,'usage'=>LicenseMap::CONCLUSION));
    $dbManager->insertTableRow('license_map', array('rf_fk'=>3,'rf_parent'=>2,'usage'=>LicenseMap::REPORT));
    
    $licenseCsvExport = new LicenseCsvExport($dbManager);
    $head = array('shortname','fullname','text','parent_shortname','report_shortname','url','notes','source');
    $out = fopen('php://output', 'w');

    $csv = $licenseCsvExport->createCsv();
    ob_start();
    fputcsv($out, $head);
        fputcsv($out, array($licenses[1]['rf_shortname'],
        $licenses[1]['rf_fullname'],
        $licenses[1]['rf_text'],
        null,
        null,
        $licenses[1]['rf_url'],
        $licenses[1]['rf_notes'],
        $licenses[1]['rf_source']));
        
    fputcsv($out, array($licenses[2]['rf_shortname'],
        $licenses[2]['rf_fullname'],
        $licenses[2]['rf_text'],
        null,
        null,
        $licenses[2]['rf_url'],
        $licenses[2]['rf_notes'],
        $licenses[2]['rf_source']));
    
    fputcsv($out, array($licenses[3]['rf_shortname'],
        $licenses[3]['rf_fullname'],
        $licenses[3]['rf_text'],
        $licenses[1]['rf_shortname'],
        $licenses[2]['rf_shortname'],
        $licenses[3]['rf_url'],
        $licenses[3]['rf_notes'],
        $licenses[3]['rf_source']));
    $expected = ob_get_contents();
    ob_end_clean();
    assertThat($csv,is(equalTo($expected)));
    
    $delimiter = '|';
    $licenseCsvExport->setDelimiter($delimiter);
    $csv3 = $licenseCsvExport->createCsv(3);
    ob_start();
    fputcsv($out, $head, $delimiter);
    fputcsv($out, array($licenses[3]['rf_shortname'],
          $licenses[3]['rf_fullname'],
          $licenses[3]['rf_text'],
          $licenses[1]['rf_shortname'],
          $licenses[2]['rf_shortname'],
          $licenses[3]['rf_url'],
          $licenses[3]['rf_notes'],
          $licenses[3]['rf_source']),
        $delimiter);
    $expected3 = ob_get_contents();
    ob_end_clean();
    assertThat($csv3,is(equalTo($expected3)));    
  }
  

  
  public function testSetDelimiter()
  {
    $dbManager = M::mock(DbManager::classname());
    $licenseCsvExport = new LicenseCsvExport($dbManager);
    $reflection = new \ReflectionClass($licenseCsvExport); 
    $delimiter = $reflection->getProperty('delimiter');
    $delimiter->setAccessible(true);
    
    $licenseCsvExport->setDelimiter('|');
    assertThat($delimiter->getValue($licenseCsvExport),is('|'));
    
    $licenseCsvExport->setDelimiter('<>');
    assertThat($delimiter->getValue($licenseCsvExport),is('<'));
  }
  
  public function testSetEnclosure()
  {
    $dbManager = M::mock(DbManager::classname());
    $licenseCsvExport = new LicenseCsvExport($dbManager);
    $reflection = new \ReflectionClass($licenseCsvExport); 
    $enclosure = $reflection->getProperty('enclosure');
    $enclosure->setAccessible(true);
    
    $licenseCsvExport->setEnclosure('|');
    assertThat($enclosure->getValue($licenseCsvExport),is('|'));
    
    $licenseCsvExport->setEnclosure('<>');
    assertThat($enclosure->getValue($licenseCsvExport),is('<'));
  }

}
 