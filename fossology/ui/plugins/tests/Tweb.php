#!/usr/bin/php
<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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
/*
 * Runner script that runs the web tests
 */
require_once('/usr/local/simpletest/web_tester.php');
require_once('/usr/local/simpletest/reporter.php');

$test = &new TestSuite('Fossology Repo Web site tests');
$test->addTestFile('foss_ui.php');
exit ($test->run(new TextReporter()) ? 0 : 1);


?>