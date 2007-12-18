<?php
/***********************************************************
libcp2foss.h.php
 Copyright (C) 2007 Hewlett-Packard Development Company, L.P.

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
***********************************************************//**

/**
 * Library of functions that help cp2* do their job.
 *
 * @package libcp2foss.h.php
 *
 * @author mark.donohoe@hp.com
 * @version $Id: libcp2foss.h.php 1520 2007-12-03 21:54:59Z danger $
 *
 */

/**
 * funciton: hash2bucket
 * 
 * Returns the folder name to place the archive in. 
 *
 * The folder name returned will be in the form x-x. For example, a-c.
 * If the name of the project does not start with an alpha, it will
 * be placed in the 'Other' directory.
 *
 * Both lower and upper case letters map to the same bucket. 
 * e.g. A => a-c, c => a-c
 *
 * @param string $name name of the project
 *
 */

function hash2bucket($name){

  // convert to lower case, both upper and lower letters map to the
  // same alpha-group e.g. A => a-c, c => a-c

  $lc_name = strtolower($name);

  $map = array('a' => 'a-c', 
               'b' => 'a-c',
               'c' => 'a-c',
               'd' => 'd-f',
               'e' => 'd-f',
               'f' => 'd-f',
               'g' => 'g-i',
               'h' => 'g-i',
               'i' => 'g-i',
               'j' => 'j-l',
               'k' => 'j-l',
               'l' => 'j-l',
               'm' => 'm-o',
               'n' => 'm-o',
               'o' => 'm-o',
               'p' => 'p-r',
               'q' => 'p-r',
               'r' => 'p-r',
               's' => 's-u',
               't' => 's-u',
               'u' => 's-u',
               'v' => 'v-z',
               'w' => 'v-z',
               'x' => 'v-z',
               'y' => 'v-z',
               'z' => 'v-z'
               );

  // return 'Other' if the name starts with a non-alpha char.
  $dir = $map[substr($lc_name,0,1)];
  if (isset($dir)){
    return($dir);
  }
  else {
    return('Other');
  }

}

/**
 * function pdbg
 *
 * print a debug message and optionally dump a structure
 *
 * prints the message prepended with a DBG-> as the prefix.   The string 
 * will have a new-line added to the end so that the caller does not have
 *  to supply it.
 *
 * @param string $message the debug message to display
 * @param mixed  $dump    if not null, will be printed using print_r.
 * 
 * @return void
 *
 */

function pdbg($message, $dump=''){
  
  $dbg_msg = 'DBG->' . $message . "\n";

  echo $dbg_msg;

  if(isset($dump)){
    //    echo "\$dump is:\n";
    print_r($dump);
    echo "\n";
  }
  return;
}
