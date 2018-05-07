#!/usr/bin/php
<?php
/*
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

