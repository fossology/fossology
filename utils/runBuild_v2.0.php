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

 Script to create packages using Project-Builder (http://project-builder.org)

 --------------------------------------------------------------------
 NOTE:  This script is _HIGHLY_ customized for the legacy fossology
        team's package build environment.  It is _EXTREMELY UNLIKELY_
        that it will work outside of this environment.  If you would 
        like to build packages for FOSSology, it is not hard, and
        we would greatly welcome your contributions, but this script
        is probably not the place to start!!!
 --------------------------------------------------------------------

 \return 0 for success, 1 for failure.
 *************************************************************/
global $Version;
global $Trunk;
$VMS = array(
             'rhel-6-i386',
             'rhel-6-x86_64',
#             'fedora-15-i386',
#             'fedora-15-x86_64',
             'debian-6.0-i386',
             'debian-6.0-x86_64',   
             'debian-7.0-i386',
             'debian-7.0-x86_64',
#             'ubuntu-11.10-i386',
#             'ubuntu-11.10-x86_64',
#             'ubuntu-11.04-i386',
#             'ubuntu-11.04-x86_64',
#             'ubuntu-10.04-i386',
#             'ubuntu-10.04-x86_64',
             'fedora-17-i386',
             'fedora-17-x86_64',
             'ubuntu-12.04-i386',
             'ubuntu-12.04-x86_64',
             'ubuntu-12.10-i386',
             'ubuntu-12.10-x86_64',
             'fedora-18-i386',
             'fedora-18-x86_64'
);
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
    $Version = "3.0.0";
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
  system("rm -rf /home/build/pb/projects/fossology/pbconf/$Version/.git");
  system("rm -rf /home/build/pb/projects/fossology/pbconf/$Version/.gitignore");
  system("rm -rf /home/build/pb/projects/fossology/pbconf/$Version/.travis.yml");
  system("cd /home/build/pb/projects/fossology/pbconf/$Version/");
  if ($Trunk) {
    $Cmd = "git clone https://github.com/fossology/fossology.git /home/build/pb/projects/fossology/pbconf/$Version/";
  } else {
    $Cmd = "git clone https://github.com/fossology/fossology.git /home/build/pb/projects/fossology/pbconf/$Version/";
  }
  if ($Verbose) {
    print "CMD=$Cmd\n";
  }
  system($Cmd);
  if ($Trunk) {}
  else {
    system("cd /home/build/pb/projects/fossology/pbconf/$Version/; git checkout $Version;");
  } 
  system("rm -rf /home/build/pb/projects/fossology/pbconf/$Version/.git");
  system("rm -rf /home/build/pb/projects/fossology/pbconf/$Version/[CHLMRVisu]*");
  system("mkdir /home/build/pb/projects/fossology/pbconf/$Version/fossology");
  system("mv /home/build/pb/projects/fossology/pbconf/$Version/packaging/deb /home/build/pb/projects/fossology/pbconf/$Version/packaging/rpm /home/build/pb/projects/fossology/pbconf/$Version/packaging/pbcl /home/build/pb/projects/fossology/pbconf/$Version/fossology");
  system("mv /home/build/pb/projects/fossology/pbconf/$Version/packaging/* /home/build/pb/projects/fossology/pbconf/$Version");
  system("rm -rf /home/build/pb/projects/fossology/pbconf/$Version/packaging");
  /* Checkout source code for build */
  $Cmd = "pb -p fossology -r $Version sbx2build";
  if ($Verbose) {
    print "CMD=$Cmd\n";
  }

  /* if source code exists, git update */
  if (file_exists("/home/build/pb/projects/fossology/$Version/")){
    system("rm -rf /home/build/pb/projects/fossology/$Version/");
  }  

  //system("perl -pi -e 's/#pbconfurl/pbconfurl/' /home/build/.pbrc");
  //system("perl -pi -e 's/1.4.1~rc1\//1.4.1~rc1/' /home/build/pb/projects/fossology/pbconf/$Version/fossology.pb");
  // prevnt annoying warnings from date() by setting the timezone
  date_default_timezone_set('America/Denver');
  $showtime = date("Ymd");
  if ($Trunk){
    //$showtime = date("Ymd");
    system("perl -pi -e 's/\/var\/ftp\/pub\/fossology/\/var\/ftp\/pub\/fossology\/$Version\/testing\/$showtime/' /home/build/pb/projects/fossology/pbconf/$Version/fossology.pb");
    system("perl -pi -e 's/code\/trunk\/fossology\//code\/trunk\/fossology/' /home/build/pb/projects/fossology/pbconf/$Version/fossology.pb");
    system("perl -pi -e 's/projver fossology = trunk/projver fossology = $Version/' /home/build/pb/projects/fossology/pbconf/$Version/fossology.pb");
    system("perl -pi -e 's/trunk/$Version~$showtime/' /home/build/pb/projects/fossology/pbconf/$Version/fossology/deb/changelog");
  } else {
    system("perl -pi -e 's/$Version\//$Version/' /home/build/pb/projects/fossology/pbconf/$Version/fossology.pb");
    system("perl -pi -e 's/\/var\/ftp\/pub\/fossology/\/var\/ftp\/pub\/fossology\/$Version/' /home/build/pb/projects/fossology/pbconf/$Version/fossology.pb");
    system("perl -pi -e 's/projver fossology = trunk/projver fossology = $Version/' /home/build/pb/projects/fossology/pbconf/$Version/fossology.pb");
    system("perl -pi -e 's/testver fossology = true/testver fossology = false/' /home/build/pb/projects/fossology/pbconf/$Version/fossology.pb");  
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
    system("perl -pi -e 's/^(deb.*debian\/)/deb = \"deb http:\/\/fossbuild.ftc.hpecorp.net\/fossology\/$Version\/testing\/$showtime\/debian\//' /home/build/pb/fossology/trunk/fossology/src/testing/dataFiles/pkginstall/debian.ini");
    system("perl -pi -e 's/^(deb.*ubuntu\/)/deb = \"deb http:\/\/fossbuild.ftc.hpecorp.net\/fossology\/$Version\/testing\/$showtime\/ubuntu\//' /home/build/pb/fossology/trunk/fossology/src/testing/dataFiles/pkginstall/ubuntu.ini");
    system("perl -pi -e 's/^(yum.*rhel\/)/yum = \"http:\/\/fossbuild.ftc.hpecorp.net\/fossology\/$Version\/testing\/$showtime\/rhel\//' /home/build/pb/fossology/trunk/fossology/src/testing/dataFiles/pkginstall/redhat.ini");
    system("perl -pi -e 's/^(yum.*fedora\/15\/)/yum = \"http:\/\/fossbuild.ftc.hpecorp.net\/fossology\/$Version\/testing\/$showtime\/fedora\/15\//' /home/build/pb/fossology/trunk/fossology/src/testing/dataFiles/pkginstall/fedora.ini");
  } else {
    system("perl -pi -e 's/^(deb.*debian\/)/deb = \"deb http:\/\/fossbuild.ftc.hpecorp.net\/fossology\/$Version\/debian\//' /home/build/pb/fossology/trunk/fossology/src/testing/dataFiles/pkginstall/debian.ini");
    system("perl -pi -e 's/^(deb.*ubuntu\/)/deb = \"deb http:\/\/fossbuild.ftc.hpecorp.net\/fossology\/$Version\/ubuntu\//' /home/build/pb/fossology/trunk/fossology/src/testing/dataFiles/pkginstall/ubuntu.ini");
    system("perl -pi -e 's/^(yum.*rhel\/)/yum = \"http:\/\/fossbuild.ftc.hpecorp.net\/fossology\/$Version\/rhel\//' /home/build/pb/fossology/trunk/fossology/src/testing/dataFiles/pkginstall/redhat.ini");
    system("perl -pi -e 's/^(yum.*fedora\/15\/)/yum = \"http:\/\/fossbuild.ftc.hpecorp.net\/fossology\/$Version\/fedora\/15\//' /home/build/pb/fossology/trunk/fossology/src/testing/dataFiles/pkginstall/fedora.ini");
  }

  // update a copy of the current packages on the build machine 
  // in /var/ftp/pub/ called 'current', so that it includes the 
  // packages we have just built
  // Note:  A symlink would make more sense here, but the vsftpd
  // FTP server does not allow symbolic links.
  $ftp_base = "/var/ftp/pub/fossology/$Version/testing";
  // first delete any existing directory called 'current'
  $command = "sudo rm -rf $ftp_base/current";
  exec($command);
  // then re-create the 'current' directory with a copy of 
  // the latest package directory
  $command = "sudo cp -R $ftp_base/$showtime/ $ftp_base/current";
  exec($command);
  

# temporarily disable this commit since we should really not be
# making svn commits from within test code (not a good practice)
# but I don't fully grok why it's doing this;  might someday be
# useful but let's skip it for now
#  system("svn commit /home/build/pb/fossology/trunk/fossology/src/testing/dataFiles/pkginstall/debian.ini /home/build/pb/fossology/trunk/fossology/src/testing/dataFiles/pkginstall/ubuntu.ini /home/build/pb/fossology/trunk/fossology/src/testing/dataFiles/pkginstall/redhat.ini /home/build/pb/fossology/trunk/fossology/src/testing/dataFiles/pkginstall/fedora.ini -m 'New $Version changes to conf files for package testing'");
  return (0);
?>

