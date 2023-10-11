#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file vmrevert.php
 * \brief revert to the current snapshot on the vm.
 *
 * @version "$Id$"
 * Created on April 6, 2012 by Mark Donohoe
 */

require_once('../lib/common-vm.php');

// parse the ini file
// cycle through the arrays and revert each vm

$toRevert = parse_ini_file('vm.ini', 1);
// first shutdown then power off (don't need to power off VMs)
/*
foreach($toRevert as $host => $vms)
{
  $host = trim($host);
  foreach ($vms as $vmName => $vm)
  {
    //echo "DB: vmName is:$vmName\n";
    //echo "DB: vm is:$vm\n";
    echo "Performing a soft shutdown on host $host using $vmName on vm:\n$vm\n";
    if(!vmOps($host, $vm, 'stop soft'))
    {
      echo "FATAL! count not revert the current snapshot for $vmName on vm\n$vm\n";
    }
  } // foreach
} // foreach
*/
// now revert snapshot
foreach($toRevert as $host => $vms)
{
  $host = trim($host);
  foreach ($vms as $vmName => $vm)
  {
    //echo "DB: vmName is:$vmName\n";
    //echo "DB: vm is:$vm\n";
    echo "Reverting snapshot on host $host using $vmName on vm:\n$vm\n";
    if(!vmOps($host, $vm, 'revertsnapshot'))
    {
      echo "FATAL! count not revert the current snapshot for $vmName on vm\n$vm\n";
    }
  } // foreach
} // foreach
