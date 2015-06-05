#!/usr/bin/php
<?php
/*
 Copyright (C) 2014 Siemens AG

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

