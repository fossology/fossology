<?php
/*
Copyright (C) 2012-2014 Hewlett-Packard Development Company, L.P.
Copyright (C) 2015 Siemens AG

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

use Fossology\Lib\Test\TestInstaller;
use Fossology\Lib\Test\TestPgDb;

class test_fo_copyright_list extends \PHPUnit\Framework\TestCase
{
  /** @var string scheduler_path is the absolute path to the scheduler binary */
  public $fo_copyright_list_path;
  /** @var TestPgDb */
  private $testDb;
  /** @var TestInstaller */
  private $testInstaller;

  protected function setUp()
  {
    $this->testDb = new TestPgDb("fossclitest");
    $tables = array('users','upload','uploadtree_a','uploadtree','copyright','groups','group_user_member','agent','copyright_decision','copyright_ars','ars_master','copyright_event');
    $this->testDb->createPlainTables($tables);
    $this->testDb->createInheritedTables(array('uploadtree_a'));
    $this->testDb->createInheritedArsTables(array('copyright'));
    $this->testDb->insertData($tables);

    $sysConf = $this->testDb->getFossSysConf();
    $this->fo_copyright_list_path = dirname(__DIR__) . '/fo_copyright_list -c '.$sysConf;

    $this->testInstaller = new TestInstaller($sysConf);
    $this->testInstaller->init();
  }

  protected function tearDown()
  {
    return;
    $this->testInstaller->clear();
    $this->testDb->fullDestruct();
    $this->testDb = null;
  }


  function test_get_copyright_list_all()
  {
    $upload_id = 2;
    $auth = "--user fossy --password fossy";
    $uploadtree_id = 13;
    $command = "$this->fo_copyright_list_path $auth -u $upload_id -t $uploadtree_id --container 1";
    exec("$command 2>&1", $output, $return_value);

    $this->assertEquals(0, $return_value, "Non-zero exit status code with\n" . join('\n', $output));
    $this->assertEquals(27, count($output));
    $this->assertEquals("B.zip/B/1b/AAL_B: copyright (c) 2002 by author", $output[22]);
  }

  function test_get_copryright_list_email()
  {
    $upload_id = 2;
    $auth = "--user fossy --password fossy";
    $uploadtree_id = 13;
    $command = "$this->fo_copyright_list_path $auth -u $upload_id -t $uploadtree_id --type email --container 1";
    exec("$command 2>&1", $output, $return_value);

    $this->assertEquals(0, $return_value, "Non-zero exit status code with\n" . join('\n', $output));
    $this->assertEquals("B.zip/B/1b/3DFX_B: info@3dfx.com", $output[7]);
  }

  function test_get_copryright_list_withoutContainer()
  {
    $upload_id = 2;
    $auth = "--user fossy --password fossy";
    $uploadtree_id = 13;
    $command = "$this->fo_copyright_list_path $auth -u $upload_id -t $uploadtree_id --type email --container 0";
    exec("$command 2>&1", $output, $return_value);

    $this->assertEquals(0, $return_value, "Non-zero exit status code with\n" . join('\n', $output));
    $this->assertEquals("B.zip/B/1b/3DFX_B: info@3dfx.com", $output[4]);
  }

  public function test_help()
  {
    $auth = "--user fossy --password fossy";
    $command = "$this->fo_copyright_list_path $auth -h";
    exec("$command 2>&1", $output, $return_value);

    $this->assertEquals(0, $return_value, "Non-zero exit status code with\n" . join('\n', $output));
    $this->assertEquals(11, count($output));
  }

  public function test_help_noAuthentication()
  {
    $command = "$this->fo_copyright_list_path -h";
    exec("$command 2>&1", $output, $return_value);

    $this->assertEquals(0, $return_value, "Non-zero exit status code with\n" . join('\n', $output));
    $this->assertEquals(11, count($output));
  }
}
