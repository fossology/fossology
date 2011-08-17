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
* \brief Include file that describes the site quick tests to run
*
* @version "$Id$"
*
* Created on May 19, 2011 by Mark Donohoe
*/

// list of tests to run, add your test in the array
$stests = array(
  'AboutMenuTest.php',
  'login.php',
  'SearchMenuTest.php',
  'OrgFoldersMenuTest-Create.php',
  'OrgFoldersMenuTest-Delete.php',
  'OrgFoldersMenuTest-Edit.php',
  'OrgFoldersMenuTest-Move.php',
  'OrgUploadsMenuTest-Delete.php',
  'OrgUploadsMenuTest-Move.php',
  'UploadInstructMenuTest.php',
  'UploadFileMenuTest.php',
  'UploadServerMenuTest.php',
  'UploadUrlMenuTest.php',
  'UploadOne-ShotMenuTest.php',
);

// Test path is relative to ....fossology/tests/
$siteTests = array(
  'suiteName' => 'Site Quick Tests',
  'testPath'  => '../ui/tests/SiteTests',
  'tests' => $stests,
);
?>