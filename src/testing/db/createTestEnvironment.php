#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

$libPhpDir = dirname(dirname(__DIR__)) . "/lib/php/Test";

require_once(__DIR__ . "/TestDbFactory.php");
require_once($libPhpDir."/TestInstaller.php");

$testDbFactory = new TestDbFactory();

$sysConfDir = $testDbFactory->setupTestDb("fosstest" . time());

$testInstaller = new Fossology\Lib\Test\TestInstaller($sysConfDir);
$testInstaller->init();

$opts = getopt("d:f", array());
if (array_key_exists("d", $opts)) {
  $srcDir = $opts["d"];
  foreach (explode(",", $srcDir) as $dir) {
    if (!empty($dir)) {
      $testInstaller->install($dir);
    }
  }
}
if (array_key_exists("f", $opts)) {
  require_once($libPhpDir."/TestPgDb.php");
  $testPgDb = new Fossology\Lib\Test\TestPgDb($testDbFactory->getDbName($sysConfDir), $sysConfDir);
  $testPgDb->createSequences(array(), true);
  $testPgDb->createPlainTables(array(), true);
  $testPgDb->createInheritedTables(array());
  $testPgDb->alterTables(array(), true);
  $testPgDb->createInheritedTables(array('uploadtree_a'));
}

print $sysConfDir;
