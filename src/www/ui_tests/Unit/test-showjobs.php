<?php
/***********************************************************
 Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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
***********************************************************/

/**
 * \brief test showjobs.php
 * \file test_showjobs.php
 */

require_once(dirname(dirname(dirname(dirname(__FILE__))))."/lib/php/Plugin/FO_Plugin.php");
require_once(dirname(dirname(dirname(dirname(__FILE__))))."/lib/php/common.php");
require_once(dirname(dirname(dirname(__FILE__)))."/ui/showjobs.php");

/**
 * \class test_showjobs
 */
class test_showjobs extends PHPUnit_Framework_TestCase {
  /**
   * \brief initialization
   */
  protected function setUp()
  {
    DBconnect("/usr/local/etc/fossology/");
  }
  
  /**
   * \brief testing ShowJobDB()
   * \todo use testing DB, poplulate data
   */
  function test_ShowJobDB() {
    global $newPlugin;
    $res = $newPlugin->ShowJobDB(1);
  }


  /*
   * \brief testing Uploads2Jobs()
   * \todo use testing DB, poplulate data
   */
  function test_Uploads2Jobs() {
    global $newPlugin;
    $res = $newPlugin->Uploads2Jobs(array(6));
  }

  /**
   * \brief clean up
   */
  protected function tearDown() {
    global $PG_CONN;
    pg_close($PG_CONN);
  }
}
