#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2011-2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file pkgConfig.php
 * \brief prepare a system for install testing.
 *
 *  pkgConfig prepares a system for the installation of fossology packages and
 *  installs the fossology packages.
 *
 *  @param string $fossVersion the version of fossology to install?
 *  @todo what should the api for this really be?
 *
 * @version "$Id $"
 * Created on Jul 19, 2011 by Mark Donohoe
 * Updated on Nov 15, 2012 by Vincent Ma
 */

require_once '../lib/TestRun.php';

global $Debian;
global $RedHat;

$debian = NULL;
$redHat = NULL;
$fedora = NULL;
$ubuntu = NULL;

/*
 * determine what os and version:
 * configure yum or apt for fossology
 * install fossology
 * stop scheduler (if install is good).
 * do the steps below.
 * 1. tune kernel
 * 2. postgres files
 * 3. php ini files
 * 4. fossology.org apache file (No needec)
 * 5. checkout fossology
 * 6. run fo-installdeps
 * 7. for RHEL what else?
 */

// Check for Super User
$euid = posix_getuid();
if($euid != 0) {
  print "Error, this script must be run as root\n";
  exit(1);
}

// determine os flavor
$distros = array();
$f = exec('cat /etc/issue', $dist, $dRtn);
$distros = explode(' ', $dist[0]);

//echo "DB: distros[0] is:{$distros[0]}\n";

// configure for fossology and install fossology packages.
// Note that after this point, you could just stop if one could rely on the install
// process to give a valid exit code... Another script will run the agent unit
// and functional tests.

// create this class which can be used by any release/os
$testUtils = new TestRun();
// distro can be Debian, Red, Fedora, Ubuntu
switch ($distros[0]) {
  case 'Debian':
    $debian = TRUE;  // is this needed?
    $debianVersion = $distros[2];
    echo "debian version is:$debianVersion\n";
    try
    {
      $Debian = new ConfigSys($distros[0], $debianVersion);
    }
    catch (Exception $e)
    {
      echo "FATAL! could not process ini file for Debian $debianVersion system\n";
      exit(1);
    }

    if(insertDeb($Debian) === FALSE)
    {
      echo "FATAL! cannot insert deb line into /etc/apt/sources.list\n";
      exit(1);
    }
    echo "*** Installing fossology ***\n";
    if(!installFossology($Debian))
    {
      echo "FATAL! Could not install fossology on {$distros[0]} version $debianVersion\n";
      exit(1);
    }
    echo "*** stopping scheduler ***\n";
    // Stop scheduler so system files can be configured.
    $testUtils->stopScheduler();

    echo "*** Tuning kernel ***\n";
    tuneKernel();

    echo "*** Setting up config files ***\n";
    if(configDebian($distros[0], $debianVersion) === FALSE)
    {
      echo "FATAL! could not configure postgres or php config files\n";
      exit(1);
    }
    /*
     echo "*** Checking apache config ***\n";
     if(!configApache2($distros[0]))
     {
     echo "Fatal, could not configure apache2 to use fossology\n";
     }
     */
    if(!restart('apache2'))
    {
      echo "Erorr! Could not restart apache2, please restart by hand\n";
      exit(1);
    }
    
    echo "*** Starting FOSSology ***\n";
    if(!start('fossology'))
    {
      echo "Erorr! Could not start FOSSology, please restart by hand\n";
      exit(1);
    }
    break;
  case 'Red':
    $redHat = 'RedHat';
    $rhVersion = $distros[6];
    //echo "rh version is:$rhVersion\n";
    try
    {
      $RedHat = new ConfigSys($redHat, $rhVersion);
    }
    catch (Exception $e)
    {
      echo "FATAL! could not process ini file for RedHat $rhVersion system\n";
      echo $e;
      exit(1);
    }
    if(!configYum($RedHat))
    {
      echo "FATAL! could not install fossology.conf yum configuration file\n";
      exit(1);
    }
    echo "*** Tuning kernel ***\n";
    tuneKernel();

    echo "*** Installing fossology ***\n";
    if(!installFossology($RedHat))
    {
      echo "FATAL! Could not install fossology on $redHat version $rhVersion\n";
      exit(1);
    }
    echo "*** stopping scheduler ***\n";
    // Stop scheduler so system files can be configured.
    //$testUtils->stopScheduler();

    echo "*** Setting up config files ***\n";
    if(!configRhel($redHat, $rhVersion))
    {
      echo "FATAL! could not install php and postgress configuration files\n";
      exit(1);
    }
    /*
     echo "*** Checking apache config ***\n";
     if(!configApache2($distros[0]))
     {
     echo "Fatal, could not configure apache2 to use fossology\n";
     }
     */
    if(!restart('httpd'))
    {
      echo "Erorr! Could not restart httpd, please restart by hand\n";
      exit(1);
    }

    if(!stop('iptables'))
    {
      echo "Erorr! Could not stop Firewall, please stop by hand\n";
      exit(1);
    }

    break;
  case 'CentOS':
    $redHat = 'RedHat';
    $rhVersion = $distros[2];
    echo "rh version is:$rhVersion\n";
    try
    {
      $RedHat = new ConfigSys($redHat, $rhVersion);
    }
    catch (Exception $e)
    {
      echo "FATAL! could not process ini file for RedHat $rhVersion system\n";
      echo $e;
      exit(1);
    }
    if(!configYum($RedHat))
    {
      echo "FATAL! could not install fossology.conf yum configuration file\n";
      exit(1);
    }
    echo "*** Tuning kernel ***\n";
    tuneKernel();

    echo "*** Installing fossology ***\n";
    if(!installFossology($RedHat))
    {
      echo "FATAL! Could not install fossology on $redHat version $rhVersion\n";
      exit(1);
    }
    echo "*** stopping scheduler ***\n";
    // Stop scheduler so system files can be configured.
    //$testUtils->stopScheduler();

    echo "*** Setting up config files ***\n";
    if(!configRhel($redHat, $rhVersion))
    {
      echo "FATAL! could not install php and postgress configuration files\n";
      exit(1);
    }
    /*
     echo "*** Checking apache config ***\n";
     if(!configApache2($distros[0]))
     {
     echo "Fatal, could not configure apache2 to use fossology\n";
     }
     */
    if(!stop('httpd'))
    {
      echo "Erorr! Could not restart httpd, please restart by hand\n";
      exit(1);
    }
    if(!start('httpd'))
    {
      echo "Erorr! Could not restart httpd, please restart by hand\n";
      exit(1);
    }
    if(!stop('iptables'))
    {
      echo "Erorr! Could not stop Firewall, please stop by hand\n";
      exit(1);
    }
    echo "*** Starting fossology ***\n";
    if(!start('fossology'))
    {
      echo "Erorr! Could not start fossology, please restart by hand\n";
      exit(1);
    }

    break;
  case 'Fedora':
    $fedora = 'Fedora';
    $fedVersion = $distros[2];
    try
    {
      $Fedora = new ConfigSys($fedora, $fedVersion);
    }
    catch (Exception $e)
    {
      echo "FATAL! could not process ini file for Fedora $fedVersion system\n";
      echo $e;
      exit(1);
    }
    if(!configYum($Fedora))
    {
      echo "FATAL! could not install fossology.repo yum configuration file\n";
      exit(1);
      break;
    }
    echo "*** Installing fossology ***\n";
    if(!installFossology($Fedora))
    {
      echo "FATAL! Could not install fossology on $fedora version $fedVersion\n";
      exit(1);
    }
    echo "*** stopping scheduler ***\n";
    // Stop scheduler so system files can be configured.
    //$testUtils->stopScheduler();

    echo "*** Tuning kernel ***\n";
    tuneKernel();

    echo "*** Setting up config files ***\n";
    if(!configRhel($fedora, $fedVersion))
    {
      echo "FATAL! could not install php and postgress configuration files\n";
      exit(1);
    }
    /*
     echo "*** Checking apache config ***\n";
     if(!configApache2($distros[0]))
     {
     echo "Fatal, could not configure apache2 to use fossology\n";
     }
     */
    $last = exec("systemctl restart httpd.service", $out, $rtn);
    if($rtn != 0)
    {
      echo "Erorr! Could not restart httpd, please restart by hand\n";
      exit(1);
    }

    $last = exec("systemctl stop iptables.service", $out, $rtn);
    if($rtn != 0)
    {
      echo "Erorr! Could not stop Firewall, please stop by hand\n";
      exit(1);
    }
    echo "*** Starting fossology ***\n";
    $last = exec("systemctl restart fossology.service", $out, $rtn);
    if($rtn != 0)
    {
      echo "Erorr! Could not start FOSSology, please stop by hand\n";
      exit(1);
    }
    break;
  case 'Ubuntu':
    $distro = 'Ubuntu';
    $ubunVersion = $distros[1];
    echo "Ubuntu version is:$ubunVersion\n";
    try
    {
      $Ubuntu = new ConfigSys($distros[0], $ubunVersion);
    }
    catch (Exception $e)
    {
      echo "FATAL! could not process ini file for Ubuntu $ubunVersion system\n";
      echo $e . "\n";
      exit(1);
    }
    if(insertDeb($Ubuntu) === FALSE)
    {
      echo "FATAL! cannot insert deb line into /etc/apt/sources.list\n";
      exit(1);
    }
    echo "*** Tuning kernel ***\n";
    tuneKernel();

    echo "*** Installing fossology ***\n";
    if(!installFossology($Ubuntu))
    {
      echo "FATAL! Could not install fossology on {$distros[0]} version $ubunVersion\n";
      exit(1);
    }
    echo "*** stopping scheduler ***\n";
    // Stop scheduler so system files can be configured.
    $testUtils->stopScheduler();

    echo "*** Setting up config files ***\n";
    if(configDebian($distros[0], $ubunVersion) === FALSE)
    {
      echo "FATAL! could not configure postgres or php config files\n";
      exit(1);
    }
    /*
     echo "*** Checking apache config ***\n";
     if(!configApache2($distros[0]))
     {
     echo "Fatal, could not configure apache2 to use fossology\n";
     }
     */
    if(!restart('apache2'))
    {
      echo "Erorr! Could not restart apache2, please restart by hand\n";
      exit(1);
    }

    echo "*** Starting FOSSology ***\n";
    if(!start('fossology'))
    {
      echo "Erorr! Could not start FOSSology, please restart by hand\n";
      exit(1);
    }
    break;
  default:
    echo "Fatal! unrecognized distribution! {$distros[0]}\n" ;
    exit(1);
    break;
}
class ConfigSys {

  public $osFlavor;
  public $osVersion = 0;
  private $fossVersion;
  private $osCodeName;
  public $deb;
  public $comment = '';
  public $yum;

  function __construct($osFlavor, $osVersion)
  {
    if(empty($osFlavor))
    {
      throw new Exception("No Os Flavor supplied\n");
    }
    if(empty($osVersion))
    {
      throw new Exception("No Os Version Supplied\n");
    }

    $dataFile = '../dataFiles/pkginstall/' . strtolower($osFlavor) . '.ini';
    $releases = parse_ini_file($dataFile, 1);
    //echo "DB: the parsed ini file is:\n";
    //print_r($releases) . "\n";
    foreach($releases as $release => $values)
    {
      if($values['osversion'] == $osVersion)
      {
        // found the correct os, gather attributes
        $this->osFlavor = $values['osflavor'];
        $this->osVersion =  $values['osversion'];
        $this->fossVersion =  $values['fossversion'];
        $this->osCodeName =  $values['codename'];
        // code below is needed to avoid php notice
        switch (strtolower($this->osFlavor)) {
          case 'ubuntu':
          case 'debian':
            $this->deb =  $values['deb'];
            break;
          case 'fedora':
          case 'redhat':
            $this->yum = $values['yum'];
            break;
          default:
            ;
            break;
        }
        $this->comment = $values['comment'];
      }
    }
    if($this->osVersion == 0)
    {
      throw new Exception("FATAL! no matching os flavor or version found\n");
    }
    return;
  } // __construct

  /**
   * prints all the classes attributes (properties)
   *
   * @return void
   */
  public function printAttr()
  {

    echo "Attributes of ConfigSys:\n";
    echo "\tosFlavor:$this->osFlavor\n";
    echo "\tosVersion:$this->osVersion\n";
    echo "\tfossVersion:$this->fossVersion\n";
    echo "\tosCodeName:$this->osCodeName\n";
    echo "\tdeb:$this->deb\n";
    echo "\tcomment:$this->comment\n";
    echo "\tyum:$this->yum\n";

    return;
  } //printAttr
} // ConfigSys

/**
 * \brief insert the fossology debian line in /etc/apt/sources.list
 *
 * @param object $objRef the object with the deb attribute
 *
 * @return boolean
 */
function insertDeb($objRef)
{

  if(!is_object($objRef))
  {
    return(FALSE);
  }
  // open file for append
  $APT = fopen('/etc/apt/sources.list', 'a+');
  if(!is_resource($APT))
  {
    echo "FATAL! could not open /etc/apt/sources.list for modification\n";
    return(FALSE);
  }
  $written = fwrite($APT, "\n");
  fflush($APT);

  if(empty($objRef->comment))
  {
    $comment = '# Automatically inserted by pkgConfig.php';
  }

  $com = fwrite($APT, $objRef->comment . "\n");
  if(!$written = fwrite($APT, $objRef->deb))
  {
    echo "FATAL! could not write deb line to /etc/apt/sources.list\n";
    return(FALSE);
  }
  fclose($APT);
  return(TRUE);
}  // insertDeb

/**
 * \brief Install fossology using either apt or yum
 *
 * installFossology assumes that the correct configuration for yum and the
 * correct fossology version has been configured into the system.
 *
 * @param object $objRef an object reference (should be to ConfigSys)
 *
 * @return boolean
 */
function installFossology($objRef)
{
  if(!is_object($objRef))
  {
    return(FALSE);
  }
  $aptUpdate = 'apt-get update 2>&1';
  $aptInstall = 'apt-get -y --force-yes install fossology 2>&1';
  $yumUpdate = 'yum -y update 2>&1';
  $yumInstall = 'yum -y install fossology > fossinstall.log 2>&1';
  $debLog = NULL;
  $installLog = NULL;

  //echo "DB: IFOSS: osFlavor is:$objRef->osFlavor\n";
  switch ($objRef->osFlavor) {
    case 'Ubuntu':
    case 'Debian':
      $last = exec($aptUpdate, $out, $rtn);
      //echo "last is:$last\nresults of update are:\n";print_r($out) . "\n";
      $last = exec($aptInstall, $iOut, $iRtn);
      if($iRtn != 0)
      {
        echo "Failed to install fossology!\nTranscript is:\n";
        echo implode("\n",$iOut) . "\n";
        return(FALSE);
      }
      // check for php or other errors that don't make apt return 1
      echo "DB: in ubun/deb case, before installLog implode\n";
      $debLog = implode("\n",$iOut);
      if(!ckInstallLog($debLog))
      {
        echo "One or more of the phrases:\nPHP Stack trace:\nFATAL\n".
          "Could not connect to FOSSology database:\n" .
          "Unable to connect to PostgreSQL server:\n" .
          "Was found in the install output. This install is suspect and is considered FAILED.\n";
        return(FALSE);
      }
      // if any of the above are non zero, return false
      break;
    case 'Fedora':
    case 'RedHat':
      echo "** Running yum update **\n";
      $last = exec($yumUpdate, $out, $rtn);
      if($rtn != 0)
      {
        echo "Failed to update yum repositories with fossology!\nTranscript is:\n";
        echo implode("\n",$out) . "\n";
        return(FALSE);
      }
      echo "** Running yum install fossology **\n";
      $last = exec($yumInstall, $yumOut, $yumRtn);
      //echo "install of fossology finished, yumRtn is:$yumRtn\nlast is:$last\n";
      //$clast = system('cat fossinstall.log');
      if($yumRtn != 0)
      {
        echo "Failed to install fossology!\nTranscript is:\n";
        system('cat fossinstall.log');
        return(FALSE);
      }
      if(!($installLog = file_get_contents('fossinstall.log')))
      {
        echo "FATAL! could not read 'fossinstall.log\n";
        return(FALSE);
      }
      if(!ckInstallLog($installLog))
      {
        echo "One or more of the phrases:\nPHP Stack trace:\nFATAL\n".
          "Could not connect to FOSSology database:\n" .
          "Unable to connect to PostgreSQL server:\n" .
          "Was found in the install output. This install is suspect and is considered failed.\n";
        return(FALSE);
      }
      break;

    default:
      echo "FATAL! Unrecongnized OS/Release, not one of Ubuntu, Debian, RedHat" .
      " or Fedora\n";
      return(FALSE);
      break;
  }
  return(TRUE);
}

/**
 * \brief Check the fossology install output for errors in the install.
 *
 * These errors do not cause apt to think that the install failed, so the output
 * should be checked for typical failures during an install of packages.  See
 * the code for the specific checks.
 *
 * @param string $log the output from a fossology install with packages
 */
function ckInstallLog($log) {
  if(empty($log))
  {
    return(FALSE);
  }
  // check for php or other errors that don't make apt return 1
  $traces = $fates = $connects = $postgresFail = 0;
  $stack = '/PHP Stack trace:/';
  $fatal = '/FATAL/';
  $noConnect = '/Could not connect to FOSSology database/';
  $noPG = '/Unable to connect to PostgreSQL server:/';

  $traces = preg_match_all($stack, $log, $stackMatches);
  $fates = preg_match_all($fatal, $log, $fatalMatches);
  $connects =  preg_match_all($noConnect, $log, $noconMatches);
  $postgresFail =  preg_match_all($noPG, $log, $noPGMatches);
  echo "Number of PHP stack traces found:$traces\n";
  echo "Number of FATAL's found:$fates\n";
  echo "Number of 'cannot connect' found:$connects\n";
  echo "Number of 'cannot connect to postgres server' found:$postgresFail\n";
  //print "DB: install log is:\n$log\n";
  if($traces ||
  $fates ||
  $connects ||
  $postgresFail)
  {
    return(FALSE);
  }
  return(TRUE);
}
/**
 * \brief copyFiles, copy one or more files to the destination,
 * throws exception if file is not copied.
 *
 * The method can be used to rename a single file, but not a directory.  It
 * cannot rename multiple files.
 *
 * @param mixed $files the files to copy (string), use an array for multiple files.
 * @param string $dest the destination path (must exist, must be writable).
 *
 * @retrun boolean
 *
 */
function copyFiles($files, $dest)
{
  if(empty($files))
  {
    throw new Exception('No file to copy', 0);
  }
  if(empty($dest))
  {
    throw new Exception('No destination for copy', 0);
  }
  //echo "DB: copyFiles: we are at:" . getcwd() . "\n";
  $login = posix_getlogin();
  //echo "DB: copyFiles: running as:$login\n";
  //echo "DB: copyFiles: uid is:" . posix_getuid() . "\n";
  if(is_array($files))
  {
    foreach($files as $file)
    {
      // Get left name and check if dest is a directory, copy cannot copy to a
      // dir.
      $baseFile = basename($file);
      if(is_dir($dest))
      {
        $to = $dest . "/$baseFile";
      }
      else
      {
        $to = $dest;
      }
      //echo "DB: copyfiles: file copied is:$file\n";
      //echo "DB: copyfiles: to is:$to\n";
      if(!copy($file, $to))
      {
        throw new Exception("Could not copy $file to $to");
      }
      //$lastcp = exec("cp -v $file $to", $cpout, $cprtn);
      //echo "DB: copyfiles: cprtn is:$cprtn\n";
      //echo "DB: copyfiles: lastcp is:$lastcp\n";
      //echo "DB: copyfiles: out is:\n";print_r($cpout) . "\n";
    }
  }
  else
  {
    $baseFile = basename($files);
    if(is_dir($dest))
    {
      $to = $dest . "/$baseFile";
    }
    else
    {
      $to = $dest;
    }
    //echo "DB: copyfiles-single: file copied is:$files\n";
    //echo "DB: copyfiles-single: to is:$to\n";
    if(!copy($files,$to))
    {
      throw new Exception("Could not copy $file to $to");
    }
  }
  return(TRUE);
} // copyFiles


/**
 * \brief find the version of postgres and return major release and sub release.
 * For example, if postgres is at 8.4.8, this function will return 8.4.
 *
 * @return boolean
 */
function findVerPsql()
{
  $version = NULL;

  $last = exec('psql --version', $out, $rtn);
  if($rtn != 0)
  {
    return(FALSE);
  }
  else
  {
    // isolate the version number and return it
    list( , ,$ver) = explode(' ', $out[0]);
    $version = substr($ver, 0, 3);
  }
  return($version);
}

/**
 * \brief tune the kernel for this boot and successive boots
 *
 * returns void
 */
function tuneKernel()
{
  // check to see if we have already done this... so the sysctl.conf file doesn't
  // end up with dup entries.
  $grepCmd = 'grep shmmax=512000000 /etc/sysctl.conf /dev/null 2>&1';
  $last = exec($grepCmd, $out, $rtn);
  if($rtn == 0)   // kernel already configured
  {
    echo "NOTE: kernel already configured.\n";
    return;
  }
  $cmd1 = "echo 512000000 > /proc/sys/kernel/shmmax";
  $cmd2 = "echo 'kernel.shmmax=512000000' >> /etc/sysctl.conf";
  // Tune the kernel
  $last1 = exec($cmd1, $cmd1Out, $rtn1);
  if ($rtn1 != 0)
  {
    echo "Fatal! Could not set kernel shmmax in /proc/sys/kernel/shmmax\n";
  }
  $last2 = exec($cmd2, $cmd2Out, $rtn2);
  // make it permanent
  if ($rtn2 != 0)
  {
    echo "Fatal! Could not turn kernel.shmmax in /etc/sysctl.conf\n";
  }
  return;
} // tuneKernel

/**
 * \brief check to see if fossology is configured into apache.  If not copy the
 * config file and configure it.  Restart apache if configured.
 *
 * @param string $osType type of the os, e.g. Debian, Ubuntu, Red, Fedora
 *
 * @return boolean
 */

function configApache2($osType)
{
  if(empty($osType))
  {
    return(FALSE);
  }
  switch ($osType) {
    case 'Ubuntu':
    case 'Debian':
      if(is_link('/etc/apache2/conf.d/fossology'))
      {
        break;
      }
      else
      {
        // copy config file, create sym link
        if(!copy('../dataFiles/pkginstall/fo-apache.conf', '/etc/fossology/fo-apache.conf'))
        {
          echo "FATAL!, Cannot configure fossology into apache2\n";
          return(FALSE);
        }
        if(!symlink('/etc/fossology/fo-apache.conf','/etc/apache2/conf.d/fossology'))
        {
          echo "FATAL! Could not create symlink in /etc/apache2/conf.d/ for fossology\n";
          return(FALSE);
        }
        // restart apache so changes take effect
        if(!restart('apache2'))
        {
          echo "Erorr! Could not restart apache2, please restart by hand\n";
          return(FALSE);
        }
      }
      break;
    case 'Red':
      // copy config file, no symlink needed for redhat
      if(!copy('../dataFiles/pkginstall/fo-apache.conf', '/etc/httpd/conf.d/fossology.conf'))
      {
        echo "FATAL!, Cannot configure fossology into apache2\n";
        return(FALSE);
      }
      // restart apapche so changes take effect
      if(!restart('httpd'))
      {
        echo "Erorr! Could not restart httpd, please restart by hand\n";
        return(FALSE);
      }
      break;
    default:
      ;
      break;
  }
  return(TRUE);
} // configApache2

/**
 * \brief config a debian based system to install fossology.
 *
 * copy postgres, php config files so that fossology can run.
 *
 * @param string $osType either Debian or Ubuntu
 * @param string $osVersion the particular version to install
 *
 * @return boolean
 */
function configDebian($osType, $osVersion)
{
  if(empty($osType))
  {
    return(FALSE);
  }
  if(empty($osVersion))
  {
    return(FALSE);
  }

  // based on type read the appropriate ini file.

  //echo "DB:configD: osType is:$osType\n";
  //echo "DB:configD: osversion is:$osVersion\n";

  // can't check in pg_hba.conf as it shows HP's firewall settings, get it
  // internally

  // No need to update pg_hba.conf file
  //getPGhba('../dataFiles/pkginstall/debian/6/pg_hba.conf');

  $debPath = '../dataFiles/pkginstall/debian/6/';

  $psqlFiles = array(
  //$debPath . 'pg_hba.conf',
  $debPath . 'postgresql.conf');

  switch ($osVersion)
  {
    case '6.0':
      echo "debianConfig got os version 6.0!\n";
      // copy config files
      /*
      * Change the structure of data files:
      * e.g. debian/5/pg_hba..., etc, all files that go with this version
      *      debian/6/pg_hba....
      *      and use a symlink for the 'codename' squeeze -> debian/6/
      */
      try
      {
        copyFiles($psqlFiles, "/etc/postgresql/8.4/main");
      }
      catch (Exception $e)
      {
        echo "Failure: Could not copy postgres 8.4 config file\n";
      }
      try
      {
        copyFiles($debPath . 'cli-php.ini', '/etc/php5/cli/php.ini');
      } catch (Exception $e)
      {
        echo "Failure: Could not copy php.ini to /etc/php5/cli/php.ini\n";
        return(FALSE);
      }
      try
      {
        copyFiles($debPath . 'apache2-php.ini', '/etc/php5/apache2/php.ini');
      } catch (Exception $e)
      {
        echo "Failure: Could not copy php.ini to /etc/php5/apache2/php.ini\n";
        return(FALSE);
      }
      break;
    case '10.04.3':
    case '11.04':
    case '11.10':
      try
      {
        copyFiles($psqlFiles, "/etc/postgresql/8.4/main");
      }
      catch (Exception $e)
      {
        echo "Failure: Could not copy postgres 8.4 config file\n";
      }
      try
      {
        copyFiles($debPath . 'cli-php.ini', '/etc/php5/cli/php.ini');
      } catch (Exception $e)
      {
        echo "Failure: Could not copy php.ini to /etc/php5/cli/php.ini\n";
        return(FALSE);
      }
      try
      {
        copyFiles($debPath . 'apache2-php.ini', '/etc/php5/apache2/php.ini');
      } catch (Exception $e)
      {
        echo "Failure: Could not copy php.ini to /etc/php5/apache2/php.ini\n";
        return(FALSE);
      }
      break;
    case '12.04.1':
    case '12.10':
      //postgresql-9.1 can't use 8.4 conf file
      /*
      try
      {
        copyFiles($psqlFiles, "/etc/postgresql/9.1/main");
      }
      catch (Exception $e)
      {
        echo "Failure: Could not copy postgres 9.1 config file\n";
      }
      */
      try
      {
        copyFiles($debPath . 'cli-php.ini', '/etc/php5/cli/php.ini');
      } catch (Exception $e)
      {
        echo "Failure: Could not copy php.ini to /etc/php5/cli/php.ini\n";
        return(FALSE);
      }
      try
      {
        copyFiles($debPath . 'apache2-php.ini', '/etc/php5/apache2/php.ini');
      } catch (Exception $e)
      {
        echo "Failure: Could not copy php.ini to /etc/php5/apache2/php.ini\n";
        return(FALSE);
      }
      break;
    default:
      return(FALSE);     // unsupported debian version
      break;
  }
  // restart apache and postgres so changes take effect
  if(!restart('apache2'))
  {
    echo "Erorr! Could not restart apache2, please restart by hand\n";
    return(FALSE);
  }
  // Get the postrgres version so the correct file is used.
  $pName = 'postgresql';
  if($osVersion == '10.04.3')
  {
    $ver = findVerPsql();
    //echo "DB: returned version is:$ver\n";
    $pName = 'postgresql-' . $ver;
  }
  echo "DB pName is:$pName\n";
  if(!restart($pName))
  {
    echo "Erorr! Could not restart $pName, please restart by hand\n";
    return(FALSE);
  }
  return(TRUE);
}  // configDebian

/**
 * \brief copy configuration files and restart apache and postgres
 *
 * @param string $osType
 * @param string $osVersion
 * @return boolean
 */
function configRhel($osType, $osVersion)
{
  if(empty($osType))
  {
    return(FALSE);
  }
  if(empty($osVersion))
  {
    return(FALSE);
  }

  $rpmPath = '../dataFiles/pkginstall/redhat/6.x';
  // getPGhba($rpmPath . '/pg_hba.conf');
  $psqlFiles = array(
  //$rpmPath . '/pg_hba.conf',
  $rpmPath . '/postgresql.conf');
  // fossology tweaks the postgres files so the packages work....  don't copy
  /*
  try
  {
  copyFiles($psqlFiles, "/var/lib/pgsql/data/");
  }
  catch (Exception $e)
  {
  echo "Failure: Could not copy postgres 8.4 config files\n";
  }
  */
  try
  {
    copyFiles($rpmPath . '/php.ini', '/etc/php.ini');
  } catch (Exception $e)
  {
    echo "Failure: Could not copy php.ini to /etc/php.ini\n";
    return(FALSE);
  }
  // restart postgres, postgresql conf didn't change do not restart
  /*
  $pName = 'postgresql';
  if(!restart($pName))
  {
    echo "Erorr! Could not restart $pName, please restart by hand\n";
    return(FALSE);
  }
  */
  return(TRUE);
} // configRhel

/**
 * \brief config yum on a redhat based system to install fossology.
 *
 * Copies the Yum configuration file for fossology to
 *
 * @param object $objRef, a reference to the ConfigSys object
 *
 * @return boolean
 */
function configYum($objRef)
{
  if(!is_object($objRef))
  {
    return(FALSE);
  }
  if(empty($objRef->yum))
  {
    echo "FATAL, no yum install line to install\n";
    return(FALSE);
  }

  $RedFedRepo = 'redfed-fossology.repo';   // name of generic repo file.
  // replace the baseurl line with the current one.
  $n = "../dataFiles/pkginstall/" . $RedFedRepo;
  $fcont = file_get_contents($n);
  //if(!$fcont);
  //{
  //echo "FATAL! could not read repo file $n\n";
  //exit(1);
  //}
  //echo "DB: contents is:\n$fcont\n";
  $newRepo = preg_replace("/baseurl=(.*)?/", 'baseurl=' . $objRef->yum, $fcont,-1, $cnt);
  // write the file, fix below to copy the correct thing...
  if(!($written = file_put_contents("../dataFiles/pkginstall/" . $RedFedRepo, $newRepo)))
  {
    echo "FATAL! could not write repo file $RedFedRepo\n";
    exit(1);
  }
  // coe plays with yum stuff, check if yum.repos.d exists and if not create it.
  if(is_dir('/etc/yum.repos.d'))
  {
    copyFiles("../dataFiles/pkginstall/" . $RedFedRepo, '/etc/yum.repos.d/fossology.repo');
  }
  else
  {
    // create the dir and then copy
    if(!mkdir('/etc/yum.repos.d'))
    {
      echo "FATAL! could not create yum.repos.d\n";
      return(FALSE);
    }
    copyFiles("../dataFiles/pkginstall/" . $RedFedRepo, '/etc/yum.repos.d/fossology.repo');
  }
  //print_r($objRef);
  if ($objRef->osFlavor == 'RedHat')
  {
     $last = exec("yum -y install wget", $out, $rtn);
     if($rtn != 0)
     {
       echo "FATAL! install EPEL repo fail\n";
       echo "transcript is:\n";print_r($out) . "\n";
       return(FALSE);
     }
     $last = exec("wget -e http_proxy=http://lart.usa.hp.com:3128 http://dl.fedoraproject.org/pub/epel/6/i386/epel-release-6-7.noarch.rpm", $out, $rtn);
     if($rtn != 0)
     {
       echo "FATAL! install EPEL repo fail\n";
       echo "transcript is:\n";print_r($out) . "\n";
       return(FALSE);
     }
     $last = exec("rpm -ivh epel-release-6-7.noarch.rpm", $out, $rtn);
     if($rtn != 0)
     {
       echo "FATAL! install EPEL repo fail\n";
       echo "transcript is:\n";print_r($out) . "\n";
       return(FALSE);
     }
  }
  return(TRUE);
}  // configYum

/**
 * \brief restart the application passed in, so any config changes will take
 * affect.  Assumes application is restartable via /etc/init.d/<script>.
 * The application passed in should match the script name in /etc/init.d
 *
 * @param string $application the application to restart. The application passed
 *  in should match the script name in /etc/init.d
 *
 *  @return boolen
 */
function restart($application)
{
  if(empty($application))
  {
    return(FALSE);
  }

  $last = exec("/etc/init.d/$application restart 2>&1", $out, $rtn);
  if($rtn != 0)
  {
    echo "FATAL! could not restart $application\n";
    echo "transcript is:\n";print_r($out) . "\n";
    return(FALSE);
  }
  return(TRUE);
} // restart

/**
 * \brief start the application
 * Assumes application is restartable via /etc/init.d/<script>.
 * The application passed in should match the script name in /etc/init.d
 *
 * @param string $application the application to start. The application passed
 *  in should match the script name in /etc/init.d
 *
 *  @return boolen
 */
function start($application)
{
  if(empty($application))
  {
    return(FALSE);
  }

  echo "Starting $application ...\n";
  $last = exec("/etc/init.d/$application restart 2>&1", $out, $rtn);
  if($rtn != 0)
  {
    echo "FATAL! could not start $application\n";
    echo "transcript is:\n";print_r($out) . "\n";
    return(FALSE);
  }
  return(TRUE);
} // start
/**
 * \brief stop the application
 * Assumes application is restartable via /etc/init.d/<script>.
 * The application passed in should match the script name in /etc/init.d
 *
 * @param string $application the application to stop. The application passed
 *  in should match the script name in /etc/init.d
 *
 *  @return boolen
 */
function stop($application)
{
  if(empty($application))
  {
    return(FALSE);
  }

  $last = exec("/etc/init.d/$application stop 2>&1", $out, $rtn);
  if($rtn != 0)
  {
    echo "FATAL! could not stop $application\n";
    echo "transcript is:\n";print_r($out) . "\n";
    return(FALSE);
  }
  return(TRUE);
} // stop

/**
 * \brief wget the pg_hba.conf file and place it in $destPath
 *
 * @param string $destPath the full path to the destination
 * @return boolean, TRUE on success, FALSE otherwise.
 *
 */
function getPGhba($destPath)
{
  if(empty($destPath))
  {
    return(FALSE);
  }
  $wcmd = "wget -q -O $destPath " .
    "http://fonightly.usa.hp.com/testfiles/pg_hba.conf ";

  $last = exec($wcmd, $wOut, $wRtn);
  if($wRtn != 0)
  {
    echo "Error, could not download pg_hba.conf file, pleases configure by hand\n";
    echo "wget output is:\n" . implode("\n",$wOut) . "\n";
    return(FALSE);
  }
  return(TRUE);
}
?>
