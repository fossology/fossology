<?php
/***************************************************************
 Copyright (C) 2024 FOSSology Team

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
 ***************************************************************/

use Fossology\Lib\Test\TestPgDb;
use Fossology\Lib\Test\TestInstaller;
use Fossology\Lib\Test\TestLiteDb;

class TextPhraseAgentTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  
  /** @var TestInstaller */
  private $testInstaller;
  
  /** @var TextPhraseAgent */
  private $agent;

  protected function setUp()
  {
    $this->testDb = new TestPgDb("textphraseagenttest");
    $this->testInstaller = new TestInstaller($this->testDb->getDbName());
    $this->testInstaller->init();
    
    $this->agent = new TextPhraseAgent();
  }

  protected function tearDown()
  {
    $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->testInstaller = null;
    $this->agent = null;
  }

  public function testProcessUpload()
  {
    // Create test data
    $uploadId = 1;
    $pfileId = 1;
    $licenseId = 1;
    
    // Insert test license
    $this->testDb->createPlainTables(array('license_ref'));
    $this->testDb->insertData(array('license_ref'), array(
      'rf_pk' => $licenseId,
      'rf_shortname' => 'Test-License',
      'rf_fullname' => 'Test License',
      'rf_text' => 'Test license text'
    ));
    
    // Insert test text phrase
    $this->testDb->createPlainTables(array('text_phrases'));
    $this->testDb->insertData(array('text_phrases'), array(
      'id' => 1,
      'text' => 'test phrase',
      'license_fk' => $licenseId,
      'is_active' => true
    ));
    
    // Create test file
    $this->testDb->createPlainTables(array('pfile', 'uploadtree'));
    $this->testDb->insertData(array('pfile'), array(
      'pfile_pk' => $pfileId,
      'pfile_sha1' => 'testsha1',
      'pfile_md5' => 'testmd5',
      'pfile_size' => 100
    ));
    
    $this->testDb->insertData(array('uploadtree'), array(
      'uploadtree_pk' => 1,
      'upload_fk' => $uploadId,
      'pfile_fk' => $pfileId,
      'ufile_name' => 'test.txt',
      'ufile_mode' => 33188
    ));
    
    // Process upload
    $this->agent->processUpload($uploadId);
    
    // Check findings
    $this->testDb->createPlainTables(array('text_phrase_findings'));
    $findings = $this->testDb->getRows("SELECT * FROM text_phrase_findings");
    
    $this->assertNotEmpty($findings);
    $this->assertEquals($pfileId, $findings[0]['pfile_fk']);
    $this->assertEquals($licenseId, $findings[0]['license_fk']);
  }
} 