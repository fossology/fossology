<?php
/*
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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

/**
 * \file test_common_sysconfig.php
 * \brief unit tests for common-sysconfig.php
 */

require_once(dirname(__FILE__) . '/../common-container.php');
require_once(dirname(__FILE__) . '/../common-db.php');
require_once(dirname(__FILE__) . '/../common-sysconfig.php');

/**
 * \class test_common_sysconfig
 */
class test_common_sysconfig extends PHPUnit_Framework_TestCase
{
  public $PG_CONN;
  public $DB_COMMAND =  "";
  public $DB_NAME =  "";  
  public $sys_conf = ""; 

  /**
   * \brief initialization
   */
  protected function setUp()
  {
    if (!is_callable('pg_connect')) {
      $this->markTestSkipped("php-psql not found");
    }
    global $PG_CONN;
    global $DB_COMMAND;
    global $DB_NAME;
    global $sys_conf;
    
    #$sysconfig = './sysconfigDirTest';
    #$PG_CONN = DBconnect($sysconfig);
    $DB_COMMAND  = dirname(dirname(dirname(dirname(__FILE__))))."/testing/db/createTestDB.php";
    exec($DB_COMMAND, $dbout, $rc);
    preg_match("/(\d+)/", $dbout[0], $matches);
    $test_name = $matches[1];
    $sys_conf = $dbout[0];
//    $DB_NAME = "fosstest".$test_name;
    #$sysconfig = './sysconfigDirTest';
    $PG_CONN = DBconnect($sys_conf);
  }

  /**
   * \brief test for ConfigInit()
   * after ConfigInit() is executed, we can get some sysconfig information,
   * include: SupportEmailLabel, SupportEmailAddr, SupportEmailSubject,
   * BannerMsg, LogoImage, LogoLink, FOSSologyURL
   */
  function testConfigInit()
  {
    global $sys_conf;
    print "\nStart unit test for common-sysconfig.php\n";
    print "test function ConfigInit()\n";
    #$sysconfig = './sysconfigDirTest';
    ConfigInit($sys_conf, $SysConf);
    $this->assertEquals("FOSSology Support",  $SysConf['SYSCONFIG']['SupportEmailSubject']);
    $hostname = exec("hostname -f");
    if (empty($hostname)) $hostname = "localhost";
    $FOSSologyURL = $hostname."/repo/";
    $this->assertEquals($FOSSologyURL,  $SysConf['SYSCONFIG']['FOSSologyURL']);
    print "unit test for common-sysconfig.php end\n";
  }


  /**
   * \brief clean the env
   */
  protected function tearDown() {
    if (!is_callable('pg_connect')) {
      return;
    }
    global $PG_CONN;
    global $DB_COMMAND;
    global $DB_NAME;

    pg_close($PG_CONN);
    exec("$DB_COMMAND -d $DB_NAME");
  }

}

?>
