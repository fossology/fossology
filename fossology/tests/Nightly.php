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

/**
 * Nightly test runs of Top of Trunk
 *
 * @param
 *
 * @return
 *
 * @version "$Id: $"
 *
 * Created on Dec 18, 2008
 */

require_once('TestRun.php');

$tonight = new TestRun();
print "Updating svn sources\n";
if($tonight->svnUpdate() !== TRUE)
{
  print "Error, could not svn Update the sources, aborting test\n";
  exit(1);
}
print "Making sources\n";
if($tonight->makeSrcs() !== TRUE)
{
  print "There were Errors in the make of the sources examing make.out\n";
  exit(1);
}
/*
 * need to add in stopping the scheduler...
 */
print "Installing fossology\n";
if($tonight->makeInstall() !== TRUE)
{
  print "There were Errors in the Installation examine make-install.out\n";
  exit(1);
}
$tonight->stopScheduler();
/*
 * next steps:
 * run fo-postinstall
 * check scheduler
 * can you check if you can login as fossy?
 *
 */

?>
