<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file test_common_sysconfig.php
 * \brief unit tests for common-sysconfig.php
 */

use Fossology\Lib\Test\TestInstaller;
use Fossology\Lib\Test\TestPgDb;

require_once(dirname(dirname(__FILE__)) . '/common-container.php');
require_once(dirname(dirname(__FILE__)) . '/common-db.php');
require_once(dirname(dirname(__FILE__)) . '/common-sysconfig.php');

/**
 * \class test_common_sysconfig
 */
class test_common_sysconfig extends \PHPUnit\Framework\TestCase
{
  public $sys_conf = "";

  /**
   * @var TestPgDb $testDb
   * Test DB
   */
  private $testDb;
  /**
   * @var TestInstaller $testInstaller
   * Test repo
   */
  private $testInstaller;

  /**
   * \brief initialization with db
   */
  protected function setUpDb()
  {
    if (!is_callable('pg_connect')) {
      $this->markTestSkipped("php-psql not found");
    }
    global $sys_conf;

    $this->testDb = new TestPgDb("sysconfTest");
    $sys_conf = $this->testDb->getFossSysConf();
    $this->testDb->getDbManager()->getDriver();

    $this->testDb->createPlainTables(['sysconfig'],false);
    $this->testDb->createSequences(['sysconfig_sysconfig_pk_seq'], false);
    $this->testDb->alterTables(['sysconfig'], false);
    Populate_sysconfig();

    $this->testInstaller = new TestInstaller($sys_conf);
    $this->testInstaller->init();
  }

  /**
   * \brief test for ConfigInit()
   * after ConfigInit() is executed, we can get some sysconfig information,
   * include: SupportEmailLabel, SupportEmailAddr, SupportEmailSubject,
   * BannerMsg, LogoImage, LogoLink, FOSSologyURL
   */
  function testConfigInit()
  {
    $this->setUpDb();
    global $sys_conf;
    ConfigInit($sys_conf, $SysConf);
    $this->assertEquals("FOSSology Support",  $SysConf['SYSCONFIG']['SupportEmailSubject']);
    $hostname = exec("hostname -f");
    if (empty($hostname)) {
      $hostname = "localhost";
    }
    $FOSSologyURL = $hostname."/repo/";
    $this->assertEquals($FOSSologyURL,  $SysConf['SYSCONFIG']['FOSSologyURL']);
    $this->tearDownDb();
  }

  /**
   * \brief clean the env db
   */
  protected function tearDownDb()
  {
    if (!is_callable('pg_connect')) {
      return;
    }

    $this->testInstaller->clear();
    $this->testDb->fullDestruct();
    $this->testDb = null;
  }

  /**
   * \brief clean the env
   */
  public function test_check_IP()
  {
    foreach (array(''=>false,'1.2.3.4'=>true,'1.7.49.343'=>false,'255.249.199.0'=>true) as $ip=>$correct) {
      $this->assertEquals(check_IP($ip),$correct,"result for IP $ip is false");
    }
  }
}
