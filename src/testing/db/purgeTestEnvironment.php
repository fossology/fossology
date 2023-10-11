#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

require_once(__DIR__ . "/TestDbFactory.php");
require_once(dirname(dirname(__DIR__)) . "/lib/php/Test/TestInstaller.php");

$testDbFactory = new TestDbFactory();

$opts = getopt("c:d:", array());

if (array_key_exists("c", $opts)) {
  $sysConfDir = $opts['c'];
} else {
  $sysConfDir = null;
}

$testInstaller = new Fossology\Lib\Test\TestInstaller($sysConfDir);
$testInstaller->clear();

if (array_key_exists("d", $opts)) {
  $srcDir = $opts["d"];
  foreach (explode(",", $srcDir) as $dir) {
    if (!empty($dir)) {
      $testInstaller->uninstall($dir);
    }
  }
}

$testDbFactory->purgeTestDb($sysConfDir);
