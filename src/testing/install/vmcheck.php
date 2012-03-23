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
//echo "results are:\n";
// print_r($hosts) . "\n";

/*
 * For each machine, turn it on, make sure there is a snapshot, revert
 */
foreach($hosts as $host => $vms)
{
  //echo "DB: vms is:\n"; print_r($vms) . "\n";
  if(empty($vms))
  {
    echo "DB: no vm's for host $host\n";
    continue;
  }
  foreach($vms as $vm)
  {
    //echo "DB: vm to operate on:\n$vm\n";
    if(!vmOps($host, $vm, 'start'))
    {
      echo "DB: start: would adjust list of vms\n";
    }
    if(!vmOps($host, $vm, 'hassnapshot'))
    {
      echo "DB: hassnapshot: would adjust list of vms\n";
    }
  }
}
exit(0);

/**
 * \brief execute a vmware cmd one or more vm machines
 *
 * @param string $host, the host to operate on.
 * @param mixed $vm either a string or an array.  The array should be a list of
 * VM's to power on in the format returned by vmware cmd:
 *
 * e.g. /vmfs/volumes/4cbde042-037897c8-e60c-d8d385d9cf55/fed15-64/fed15-64.vmx
 *
 * @param string $command the vmware cmd to execute
 *
 * @return boolean
 */
function vmOps($host,$vm, $command)
{
  $inout = array();
  $inrtn = -1;
  $turnOnVm = NULL;
  $errors = 0;

  if(empty($host))
  {
    return(FALSE);   // void
  }
  if(empty($command))
  {
    return(FALSE);
  }
  if(is_array($vm))
  {
    foreach($vm as $machine)
    {
      $turnOnVm = "ssh $host 'vmware-cmd " . "\"" . $vm . "\"" .  " $command' 2>&1";
      $laston = exec($turnOnVm, $inout, $inrtn);
      if($inrtn != 0)
      {
        echo "Error: could not $command on $vm on $host\n";
        $errors++;
      }
      $inout = array();
    }
  }
  else
  {
    $turnOnVm = "ssh $host 'vmware-cmd " . "\"" . $vm . "\"" .  " start' 2>&1";
    $laston = exec($turnOnVm, $inout, $inrtn);
    if($inrtn != 0)
    {
      echo "Error: could not $command on $vm on $host\n";
      $errors++;
    }
  }
  if($errors)
  {
    return(FALSE);
  }
  else
  {
    return(TRUE);
  }
}
?>