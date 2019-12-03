<?php
/*
Copyright (C) 2015, Siemens AG

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

namespace Fossology\Lib\Test;

require_once(dirname(dirname(dirname(__DIR__))) . "/vendor/autoload.php");

class TestInstaller
{
  /** @var string */
  private $sysConf;

  function __construct($sysConf)
  {
    $this->sysConf = $sysConf;
  }

  public function init()
  {
    $sysConf = $this->sysConf;

    $confFile = $sysConf."/fossology.conf";
    $fakeInstallationDir = "$sysConf/inst";

    $projectGroup = `id -g -n`;
    $config = "[FOSSOLOGY]\ndepth = 0\npath = $sysConf/repo\n"
      . "[DIRECTORIES]\nMODDIR = $fakeInstallationDir\nPROJECTGROUP = $projectGroup";
    file_put_contents($confFile, $config);

    if (! is_dir($fakeInstallationDir)) {
      mkdir($fakeInstallationDir, 0777, true);

      $libDir = dirname(dirname(dirname(__DIR__))) . "/lib";
      system("ln -sf $libDir $fakeInstallationDir/lib");

      if (! is_dir("$fakeInstallationDir/www/ui")) {
        mkdir("$fakeInstallationDir/www/ui/", 0777, true);
        touch("$fakeInstallationDir/www/ui/ui-menus.php");
      }
    }

    $topDir = dirname(dirname(dirname(dirname(__DIR__))));
    system("install -D $topDir/VERSION $sysConf");
  }

  public function clear()
  {
    system("rm $this->sysConf/inst -rf");
    $versionFile = $this->sysConf."/VERSION";
    if (file_exists($versionFile)) {
      unlink($versionFile);
    }
    $confFile = $this->sysConf . "/fossology.conf";
    if (file_exists($confFile)) {
      unlink($confFile);
    }
  }

  public function install($srcDir)
  {
    $sysConfDir = $this->sysConf;
    exec("make MODDIR=$sysConfDir DESTDIR= BINDIR=$sysConfDir SYSCONFDIR=$sysConfDir -C $srcDir install", $unused, $rt);
    return ($rt != 0);
  }

  public function uninstall($srcDir)
  {
    $sysConfDir = $this->sysConf;
    exec("make MODDIR=$sysConfDir DESTDIR= BINDIR=$sysConfDir SYSCONFDIR=$sysConfDir -C $srcDir uninstall", $unused, $rt);
    $modEnabled = "$sysConfDir/mods-enabled";
    if (is_dir($modEnabled)) {
      rmdir($modEnabled);
    }
    return ($rt != 0);
  }

  public function cpRepo()
  {
    $testRepoDir = __DIR__;
    system("cp -a $testRepoDir/repo $this->sysConf/");
  }

  public function rmRepo()
  {
    system("rm $this->sysConf/repo -rf");
  }
}
