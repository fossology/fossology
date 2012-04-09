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
foreach($toRevert as $host => $vms)
{
  $host = trim($host);
  echo "DB: host is:$host\n";
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
?>