<?php
/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \brief common functions used to manage vm's from the command line
 *
 * @version "$Id$"
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
      $turnOnVm = "vmware-cmd -H $host -U root -P iforgot $vm $command 2>&1";
      $laston = exec($turnOnVm, $inout, $inrtn);
      echo "DB: Ops: inrtn is:$inrtn\n";
      echo "DB: Ops: inout is:\n"; print_r($inout) . "\n";
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
    $turnOnVm = "vmware-cmd -H $host -U root -P iforgot $vm $command 2>&1";
    $laston = exec($turnOnVm, $inout, $inrtn);
    //echo "DB: Ops: inrtn is:$inrtn\n";
    //echo "DB: Ops: inout is:\n"; print_r($inout) . "\n";
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
