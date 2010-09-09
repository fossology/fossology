
<?php
/*
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
 * testSuites
 * 
 * define the test suites used in testing fossology
 */

$basicText = 'Test folder creation, deletion, moving and editing properties.'
. 'Ensure duplicate folders cannot be created. Make sure that uploading a '
. 'File, Archive from server, directory from server, file from server and '
. 'Upload from URL work.';

$testSuites = array(
  'testcreateUIUsers' => 'Test that users can be created using the admin->users menu',
  'Site Tests' => 'Test that the fossology Web site it up and that the standard top menus exist',
  'Basic Functional Tests' => $basicText,
  'User Tests' => 'Test that users can be added,  no duplicate users allowed, email notification is set properly.',
  'Classifier Tests' => 'Verify copyright classifier is working',
  'Upload-Prep Tests' => 'Verifyies uploads work, used to test functionality of nomos, copyright and packagagent',
  'CopyRight Tests' => 'Verify that copyright found the correct number of copyrights, emails and urls',
  'Verify Tests' => 'Verify that nomos, and package agent processed the files loaded by the Upload-Prep Tests were process correctly'
);

?>