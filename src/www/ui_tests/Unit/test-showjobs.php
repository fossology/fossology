<?php
/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

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
class test_showjobs extends \PHPUnit\Framework\TestCase {
  /**
   * \brief initialization
   */
  protected function setUp() : void
  {
    DBconnect("/usr/local/etc/fossology/");
  }
  
  /**
   * \brief testing ShowJobDB()
   * \todo use testing DB, poplulate data
   */
  function test_ShowJobDB() {
    global $NewPlugin;
    $res = $NewPlugin->ShowJobDB(1);
  }


  /*
   * \brief testing Uploads2Jobs()
   * \todo use testing DB, poplulate data
   */
  function test_Uploads2Jobs() {
    global $NewPlugin;
    $res = $NewPlugin->Uploads2Jobs(array(6));
  }

  /**
   * \brief clean up
   */
  protected function tearDown() : void {
    global $PG_CONN;
    pg_close($PG_CONN);
  }
}
