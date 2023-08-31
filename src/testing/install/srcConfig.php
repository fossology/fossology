#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2013-2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file srcConfig.php
 * \brief prepare a system for source install testing.
 *
 *  srcConfig prepares a system for the installation of fossology from source
 *
 */
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

// create this class which can be used by any release/os
//$testUtils = new TestRun();
// distro can be Debian, Red, Fedora, Ubuntu
switch ($distros[0]) {
  case 'Debian1':
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
    echo "*** Configure fossology ***\n";
    if(!configFossology($Debian))
    {
      echo "FATAL! Could not config fossology on {$distros[0]} version $debianVersion\n";
      exit(1);
    }
    break;
  case 'Red1':
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
    echo "*** Configure fossology ***\n";
    if(!configFossology($RedHat))
    {
      echo "FATAL! Could not config fossology on $redHat version $rhVersion\n";
      exit(1);
    }
    
    if(!stop('iptables'))
    {
      echo "Erorr! Could not stop Firewall, please stop by hand\n";
      exit(1);
    }

    break;
  case 'CentOS1':
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
    echo "*** Configure fossology ***\n";
    if(!configFossology($RedHat))
    {
      echo "FATAL! Could not config fossology on $redHat version $rhVersion\n";
      exit(1);
    }

    if(!stop('iptables'))
    {
      echo "Erorr! Could not stop Firewall, please stop by hand\n";
      exit(1);
    }
    
    break;
  case 'Fedora1':
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
    echo "*** Configure fossology ***\n";
    if(!configFossology($Fedora))
    {
      echo "FATAL! Could not config fossology on $fedora version $fedVersion\n";
      exit(1);
    }
    $last = exec("systemctl stop iptables.service", $out, $rtn);
    if($rtn != 0)
    {
      echo "Erorr! Could not stop Firewall, please stop by hand\n";
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
    echo "*** Configure fossology ***\n";
    if(!configDebian($distros[0], $ubunVersion))
    {
      echo "FATAL! Could not config fossology on {$distros[0]} version $ubunVersion\n";
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
 * \brief Config fossology
 *
 * @param object $objRef an object reference (should be to ConfigSys)
 *
 * @return boolean
 */
function configFossology($objRef)
{
  if(!is_object($objRef))
  {
    return(FALSE);
  }
  $debLog = NULL;
  $installLog = NULL;

  //echo "DB: IFOSS: osFlavor is:$objRef->osFlavor\n";
  switch ($objRef->osFlavor) {
    case 'Ubuntu':
    case 'Debian':
      $debApache = "ln -s /usr/local/etc/fossology/conf/src-install-apache-example.conf /etc/apache2/sites-enabled/fossology.conf";
      $last = exec($debApache, $out, $rtn);
      //echo "last is:$last\nresults of update are:\n";print_r($out) . "\n";
      if($rtn != 0)
      {
        echo "Failed to config fossology!\nTranscript is:\n";
        echo implode("\n",$out) . "\n";
        return(FALSE);
      }
      break;
    case 'Fedora':
    case 'RedHat':
      $initPostgres = "service postgresql initdb";
      $startPostgres = "service postgresql start";
      $restartPostgres = "service postgresql restart";
      $psqlFile = "../dataFiles/pkginstall/redhat/6.x/pg_hba.conf";       
 
      echo "** Initial postgresql **\n";
      $last = exec($initPostgres, $out, $rtn);
      if($rtn != 0)
      {
        echo "Failed to initial postgresql!\nTranscript is:\n";
        echo implode("\n",$out) . "\n";
        return(FALSE);
      }
      echo "** Start postgresql **\n";
      $last = exec($startPostgres, $out, $rtn);
      if($rtn != 0)
      {
        echo "Failed to start postgresql!\nTranscript is:\n";
        echo implode("\n",$out) . "\n";
        return(FALSE);
      }
      echo "** Configure pg_hba.conf **\n";
      try
      {
        copyFiles($psqlFile, "/var/lib/pgsql/data/");
      }
      catch (Exception $e)
      {
        echo "Failure: Could not copy postgres config files\n";
      }
      $last = exec($restartPostgres, $out, $rtn);
      if($rtn != 0)
      {
        echo "Failed to restart postgresql!\nTranscript is:\n";
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

  switch ($osVersion)
  {
    case '6.0':
    case '7.0':
      echo "debianConfig got os version 6.0!\n";
      break;
    case '10.04.3':
    case '11.04':
    case '11.10':
      echo "debianConfig got os version $osVersion!\n";
      break;
    case '12.04.1':
    case '12.04.2':
    case '12.04.3':
    case '12.04.4':
      echo "debianConfig got os version $osVersion!\n";
      echo "Old PHPunit installation with PEAR is deprecated, it is now done with composer.\n";
      echo "To install composer type:\n";
      echo "curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer\n ";
      break;
    case '12.10':
      echo "debianConfig got os version $osVersion!\n";
      break;
    default:
      return(FALSE);     // unsupported debian version
      break;
  }
  return(TRUE);
}  // configDebian

/**
 * \brief config redhat based system to install fossology.
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

  if ($objRef->osFlavor == 'RedHat')
  {
     $last = exec("yum -y install wget", $out, $rtn);
     if($rtn != 0)
     {
       echo "FATAL! install EPEL repo fail\n";
       echo "transcript is:\n";print_r($out) . "\n";
       return(FALSE);
     }
     $last = exec("wget -e http_proxy=http://lart.usa.hp.com:3128 http://dl.fedoraproject.org/pub/epel/6/i386/epel-release-6-8.noarch.rpm", $out, $rtn);
     if($rtn != 0)
     {
       echo "FATAL! install EPEL repo fail\n";
       echo "transcript is:\n";print_r($out) . "\n";
       return(FALSE);
     }
     $last = exec("rpm -ivh epel-release-6-8.noarch.rpm", $out, $rtn);
     if($rtn != 0)
     {
       echo "FATAL! install EPEL repo fail\n";
       echo "transcript is:\n";print_r($out) . "\n";
       return(FALSE);
     }
     $last = exec("yum -y install php-phpunit-PHPUnit", $out, $rtn);
     if($rtn != 0)
     {
       echo "FATAL! install PHPUnit fail\n";
       echo "transcript is:\n";print_r($out) . "\n";
       return(FALSE);
     }
  }
  return(TRUE);
}  // configYum
