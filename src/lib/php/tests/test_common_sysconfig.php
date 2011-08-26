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

require_once('../common-sysconfig.php');
require_once('../common-db.php');
require_once('pathinclude.php');

/**
 * \class test_common_sysconfig
 */
class test_common_sysconfig extends PHPUnit_Framework_TestCase
{
  public $PG_CONN;
 
  /* initialization */
  protected function setUp() 
  {
    global $PG_CONN;
    /** require PHPUnit/Framework.php */
    print "Starting test unit test for common-sysconfig.php\n";
    $php_lib1 = "/usr/share/php/PHPUnit/Framework.php";
    $php_lib2 = "/usr/share/pear/PHPUnit/Framework.php";
    if(file_exists($php_lib1))
    {
      require_once($php_lib1);
    }
    else if(file_exists($php_lib2)) 
    {
      require_once($php_lib2);
    }
    else
    {
      die("Could not find PHPUnit/Framework.php\n");
    }
    $PG_CONN = DBconnect();
  }

  /**
   * \brief test for ConfigInit()
   * after ConfigInit() is executed, we can get some sysconfig information, 
   * include: SupportEmailLabel, SupportEmailAddr, SupportEmailSubject, 
   * BannerMsg, LogoImage, LogoLink, GlobalBrowse, FOSSologyURL
   */
  function testConfigInit()
  {
    print "hello";
    $SysConf = ConfigInit();
    $this->assertEquals("FOSSology Support",  $SysConf['SupportEmailSubject']);
    $this->assertEquals("false",  $SysConf['GlobalBrowse']);
    $hostname = exec("hostname -f");
    if (empty($hostname)) $hostname = "localhost";
    $FOSSologyURL = $hostname."/repo/";
    $this->assertEquals($FOSSologyURL,  $SysConf['FOSSologyURL']);
  }


  /**
   * \brief clean the env
   */
  protected function tearDown() {
    global $PG_CONN;
    pg_close($PG_CONN);
    print "ending test unit test fro common-sysconfig.php\n";
  }
}

?>
