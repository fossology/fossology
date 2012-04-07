#!/usr/bin/php
<?php
/*
 Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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
 * \file vmcheck.php
 * \brief make sure the vm's for package testing are on and have a snapshot
 *
 * @version "$Id$"
 * Created on March 15, 2012 by Mark Donohoe
 */

require_once('../lib/common-vm.php');

$vmServers = array(
    'foss-vmhost1.usa.hp.com',
    'foss-vmhost2.usa.hp.com',
    'foss-vmhost3.usa.hp.com',
    'foss-vmhost4.usa.hp.com',
);

// vmware does not change the name of the initial vm, it just displays the new
// name.
$pkgVms = array(
    'squeze32',
    'squeze64',
    'fed15-32',
    'fed15-64',
    'rhel6-232',
    'rhel6-264_1',
    'u10-043-32_1',
    'u10-043-64',
    'ubun11-0432',
    'ubun11.04.64',
    'u1110-32',
    'u1110-64',
);


$hosts = array();
$listOut = array();
$vmList = array();

// gather vm's on each server
// determine what server each vm is on, build an array with [vmhost][vmname]

foreach($vmServers as $host)
{
  $host = trim($host);
  $cmd = "ssh $host  'vmware-cmd -l'";
  $last = exec($cmd, $listOut, $rtn);
  foreach($listOut as $vmMachine)
  {
    if(empty($vmMachine))
    {
      continue;
    }
    $parts = explode('/', $vmMachine);
    if(in_array(trim($parts[4]), $pkgVms))
    {
      //echo "DB: matched! {$parts[4]}\n";
      $vmList[] = $vmMachine;
    }
    $hosts[$host] = $vmList;
  } // foreach
  $vmList = array();
  $listOut = array();
} // foreach

/*
 * For each machine, turn it on, make sure there is a snapshot record machines
 * that are ready to test in an array.  Write the array to a vm.ini file.  This
 * file will be used by vmrevert to revert the vms to the current snapshot.
 *
 */
$machinesReady = array();
foreach($hosts as $host => $vms)
{
  if(empty($vms))
  {
    echo "Note: no vm's for host $host\n";
    continue;
  }
  foreach($vms as $vm)
  {
    if(!vmOps($host, $vm, 'start'))
    {
      echo "Warning: $vm would not start, not not in this run.\n";
      continue;
    }
    if(!vmOps($host, $vm, 'hassnapshot'))
    {
      echo "DB: hassnapshot: would adjusting list of vms\n";

    }
    $machinesReady[$host][] = $vm;
  }
}
//echo "DB: the machines that will be used to test are:\n";
//print_r($machinesReady) . "\n";

// create ini file, use vmname=vm for entries.

$dataFile = 'vm.ini';
$VM = fopen($dataFile, 'w') or die("FATAL! Cannot open $dataFile\n");
foreach ($machinesReady as $host => $vms)
{
  if(!fwrite($VM, '[' . $host . "]\n"))
  {
    echo "FATAL! could not write to $dataFile\n";
    exit(1);
  }
  foreach ($vms as $vm)
  {
    $vmParts = explode('/', $vm);
    if(!fwrite($VM, $vmParts[4] . '=' . "$vm\n"))
    {
      echo "FATAL! could not write to $dataFile\n";
      exit(1);
    }
  }

}
exit(0);
?>