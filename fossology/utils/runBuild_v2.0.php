#!/usr/bin/php
<?php
/***********************************************************
 Copyright (C) 2011-2012 Hewlett-Packard Development Company, L.P.

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
/**************************************************************
 runBuild v2.0

 Script to create packages use Project-Builder.

 \return 0 for success, 1 for failure.
 *************************************************************/
global $Version;
global $Trunk;
$VMS = array(
             'rhel-6-i386',
             'rhel-6-x86_64',
             'fedora-15-i386',
             'fedora-15-x86_64',
             'debian-6.0-i386',
             'debian-6.0-x86_64',   
             'ubuntu-11.10-i386',
             'ubuntu-11.10-x86_64',
             'ubuntu-11.04-i386',
             'ubuntu-11.04-x86_64',
             'ubuntu-10.04-i386',
             'ubuntu-10.04-x86_64');
//$VMS = NULL;
$Usage = "Usage: " . basename($argv[0]) . " [options]
  Options:
    -h       = this help message
    -v       = enable verbose debugging
    -V	     = version to create packages for
    -t       = create packages from trunk
  ";
$Verbose = 0;

for ($i = 1;$i < $argc;$i++) {
  switch ($argv[$i]) {
    case '-v':
      $Verbose++;
      break;
    case '-h':
    case '-?':
      print $Usage . "\n";
      return (0);
    case '-V':
      $i++;
      $Version = $argv[$i];
      break;  
    case '-t':
      $Trunk = 1;
      break;
    default:
      if (substr($argv[$i], 0, 1) == '-') {
        print "Unknown parameter: '" . $argv[$i] . "'\n";
        print $Usage . "\n";
        exit(1);
      }
      break;
    } /* switch */
  } /* for each parameter */

  if (empty($Version)) {
    $Version = "2.0.0";
  }

  /* Create new version of fossology project */
  $Cmd = "pb -p fossology -r $Version newproj fossology";
  if ($Verbose) {
    print "CMD=$Cmd\n";
  }
  system($Cmd);

  /* Update project-builder conf files */
  $Cmd = "rm -rf /home/build/pb/projects/fossology/pbconf/$Version/*";
  if ($Verbose) {
    print "CMD=$Cmd\n";
  }
  system($Cmd);
  system("rm -rf /home/build/pb/projects/fossology/pbconf/$Version/.svn");
  system("cd /home/build/pb/projects/fossology/pbconf/$Version/");
  if ($Trunk) {
    $Cmd = "svn co http://fossology.svn.sourceforge.net/svnroot/fossology/pbconf/trunk/ /home/build/pb/projects/fossology/pbconf/$Version/";
  } else {
    $Cmd = "svn co http://fossology.svn.sourceforge.net/svnroot/fossology/pbconf/tags/$Version/ /home/build/pb/projects/fossology/pbconf/$Version/";
  }
  if ($Verbose) {
    print "CMD=$Cmd\n";
  }
  system($Cmd); 
 
  /* Checkout source code for build */
  $Cmd = "pb -p fossology -r $Version sbx2build";
  if ($Verbose) {
    print "CMD=$Cmd\n";
  }

  /* if exist source code, svn update */
  if (file_exists("/home/build/pb/projects/fossology/$Version/")){
    system("svn update /home/build/pb/projects/fossology/$Version/");
  }  
  //system("perl -pi -e 's/#pbconfurl/pbconfurl/' /home/build/.pbrc");
  //system("perl -pi -e 's/1.4.1~rc1\//1.4.1~rc1/' /home/build/pb/projects/fossology/pbconf/$Version/fossology.pb");
  $showtime = date("Ymd");
  if ($Trunk){
    //$showtime = date("Ymd");
    system("perl -pi -e 's/\/var\/ftp\/pub\/fossology/\/var\/ftp\/pub\/fossology\/$Version\/testing\/$showtime/' /home/build/pb/projects/fossology/pbconf/$Version/fossology.pb");
    system("perl -pi -e 's/projver fossology = trunk/projver fossology = $Version/' /home/build/pb/projects/fossology/pbconf/$Version/fossology.pb");
    system("perl -pi -e 's/devel/$showtime/' /home/build/pb/projects/fossology/pbconf/$Version/fossology/deb/changelog");
  } else {
    system("perl -pi -e 's/$Version\//$Version/' /home/build/pb/projects/fossology/pbconf/$Version/fossology.pb");
    system("perl -pi -e 's/\/var\/ftp\/pub\/fossology/\/var\/ftp\/pub\/fossology\/$Version/' /home/build/pb/projects/fossology/pbconf/$Version/fossology.pb");
    system("perl -pi -e 's/projver fossology = trunk/projver fossology = $Version/' /home/build/pb/projects/fossology/pbconf/$Version/fossology.pb");
  }
  system($Cmd);
  //system("perl -pi -e 's/pbconfurl/#pbconfurl/' /home/build/.pbrc");
//exit;
  /* Build packages from VMs */
  foreach ($VMS as $VM) {
    $Cmd = "pb -p fossology -r $Version -m $VM build2vm";
    if ($Verbose) {
      print "CMD=$Cmd\n";
    }
    system($Cmd);
    system("sleep 10");
  }

  /* update dataFiles */
  if ($Trunk){
    system("perl -pi -e 's/^(deb.*debian\/)/deb = \"deb http:\/\/fossbuild.usa.hp.com\/fossology\/$Version\/testing\/$showtime\/debian\//' /home/build/pb/fossology/branches/fossology2.0/fossology/src/testing/dataFiles/pkginstall/debian.ini");
    system("perl -pi -e 's/^(deb.*ubuntu\/)/deb = \"deb http:\/\/fossbuild.usa.hp.com\/fossology\/$Version\/testing\/$showtime\/ubuntu\//' /home/build/pb/fossology/branches/fossology2.0/fossology/src/testing/dataFiles/pkginstall/ubuntu.ini");
    system("perl -pi -e 's/^(yum.*rhel\/)/yum = \"http:\/\/fossbuild.usa.hp.com\/fossology\/$Version\/testing\/$showtime\/rhel\//' /home/build/pb/fossology/branches/fossology2.0/fossology/src/testing/dataFiles/pkginstall/redhat.ini");
    system("perl -pi -e 's/^(yum.*fedora\/15\/)/yum = \"http:\/\/fossbuild.usa.hp.com\/fossology\/$Version\/testing\/$showtime\/fedora\/15\//' /home/build/pb/fossology/branches/fossology2.0/fossology/src/testing/dataFiles/pkginstall/fedora.ini");
  } else {
    system("perl -pi -e 's/^(deb.*debian\/)/deb = \"deb http:\/\/fossbuild.usa.hp.com\/fossology\/$Version\/debian\//' /home/build/pb/fossology/branches/fossology2.0/fossology/src/testing/dataFiles/pkginstall/debian.ini");
    system("perl -pi -e 's/^(deb.*ubuntu\/)/deb = \"deb http:\/\/fossbuild.usa.hp.com\/fossology\/$Version\/ubuntu\//' /home/build/pb/fossology/branches/fossology2.0/fossology/src/testing/dataFiles/pkginstall/ubuntu.ini");
    system("perl -pi -e 's/^(yum.*rhel\/)/yum = \"http:\/\/fossbuild.usa.hp.com\/fossology\/$Version\/rhel\//' /home/build/pb/fossology/branches/fossology2.0/fossology/src/testing/dataFiles/pkginstall/redhat.ini");
    system("perl -pi -e 's/^(yum.*fedora\/15\/)/yum = \"http:\/\/fossbuild.usa.hp.com\/fossology\/$Version\/fedora\/15\//' /home/build/pb/fossology/branches/fossology2.0/fossology/src/testing/dataFiles/pkginstall/fedora.ini");
  }

  system("svn commit /home/build/pb/fossology/branches/fossology2.0/fossology/src/testing/dataFiles/pkginstall/debian.ini /home/build/pb/fossology/branches/fossology2.0/fossology/src/testing/dataFiles/pkginstall/ubuntu.ini /home/build/pb/fossology/branches/fossology2.0/fossology/src/testing/dataFiles/pkginstall/redhat.ini /home/build/pb/fossology/branches/fossology2.0/fossology/src/testing/dataFiles/pkginstall/fedora.ini -m 'New $Version changes to conf files for package testing'");
  return (0);
?>

