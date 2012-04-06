<?php
/*
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
* \brief common functions used to manage vm's from the command line
*
* @version "$Id: $"
* Created on Apr 6, 2012 by Mark Donohoe
*/

/**
 * \brief execute a vmware cmd on one or more vm machines
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