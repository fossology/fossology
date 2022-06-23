<?php
/*
 SPDX-FileCopyrightText: © Darshan Kansagara <kansagara.darshan97@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file test_common_sysconfig.php
 * \brief unit tests for common-sysconfig.php
 */

require_once(dirname(dirname(__FILE__)) . '/common-container.php');
require_once(dirname(dirname(__FILE__)) . '/common-db.php');
require_once(dirname(dirname(__FILE__)) . '/fossdash-config.php');

/**
 * \class test_fossdash_config
 */
class test_fossdash_config extends \PHPUnit\Framework\TestCase
{
  public $PG_CONN;
  public $DB_COMMAND =  "";
  public $DB_NAME =  "";
  public $sys_conf = "";

  /**
   * \brief initialization with db
   */
  protected function setUpDb()
  {
    if (!is_callable('pg_connect')) {
      $this->markTestSkipped("php-psql not found");
    }
    global $PG_CONN;
    global $DB_COMMAND;
    global $sys_conf;

    $DB_COMMAND  = dirname(dirname(dirname(dirname(__FILE__))))."/testing/db/createTestDB.php";
    exec($DB_COMMAND, $dbout, $rc);
    $sys_conf = $dbout[0];
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
    $this->setUpDb();
    global $sys_conf;
    FossdashConfigInit($sys_conf, $SysConf);
    $this->assertEquals("0",  $SysConf['FOSSDASHCONFIG']['FossdashEnableDisable']);
    $this->assertEquals("* * * * *",  $SysConf['FOSSDASHCONFIG']['FossDashScriptCronSchedule']);
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
    global $PG_CONN;
    global $DB_COMMAND;
    global $DB_NAME;

    pg_close($PG_CONN);
    exec("$DB_COMMAND -d $DB_NAME");
  }

  /**
   * \brief check fossdash url
   */
  public function test_check_fossdash_url()
  {
    foreach (array('http://localhost:8086/write?db=fossology_db'=>true,
                   'http://influxdb:8086/write?db=fossology_db'=>true,
                   'http://127.0.0.1:8086/write?db=fossology_db'=>true,
                   'ssh://127.0.0.1:8086'=>false,) as $url=>$correct) {
      $this->assertEquals(check_fossdash_url($url),$correct,$message="result for URL $url is false");
      print('.');
    }
  }

  /**
   * \brief check cron job inteval
   */
  public function test_check_cron_job_inteval()
  {
    foreach (array('* * * * *'=>true,
                   '1 1 * 1 *'=>true,
                   '*/1 * * * *'=>true,
                   'abbkbkb'=>false,
                   '* * * * * 23567'=>false,
                   '1 1 1 1 1 1'=>false) as $cron=>$correct) {
      $this->assertEquals(check_cron_job_inteval($cron),$correct,$message="result for CRON $cron is false");
      print('.');
    }
  }

  /**
   * \brief check fossology instance name
   */
  public function test_check_fossology_instance_name()
  {
    foreach (array('fossology_instance_1'=>true,
                   'fossology-instance-1'=>true,
                   'instance=1'=>false,
                   ''=>false,
                   'fossology instance 1'=>false,
                   'instance1'=>true) as $instance_name=>$correct) {
      $this->assertEquals(check_fossology_instance_name($instance_name),$correct,$message="result for CRON $instance_name is false");
      print('.');
    }
  }

  /**
   * \brief check fossdash cleaning days
   */
  public function test_check_fossdash_cleaning()
  {
    foreach (array('22'=>true,
                   '1'=>true,
                   '0'=>true,
                   'ghgl'=>false,
                   'one'=>false) as $clening_interval=>$correct) {
      $this->assertEquals(check_fossdash_cleaning($clening_interval),$correct,$message="result for CRON $clening_interval is false");
      print('.');
    }
  }

  /**
   * \brief check fossdash metric config
   */
  public function test_check_fossdash_config()
  {
    foreach (array('sfksb ghs 124 () * * ?'=>true,
                   'drop table sysconfig'=>false,
                   'alter table sysconfig'=>false,
                   'INSERT INTO sysconfig'=>false) as $metric=>$correct) {
      $this->assertEquals(check_fossdash_config($metric),$correct,$message="result for metric $metric is false");
      print('.');
    }
  }
}

