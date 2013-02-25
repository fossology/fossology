#!/usr/bin/php -q

<?php
/***********************************************************
  Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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
 ***********************************************************/

/**
 * \file export license_ref into licenseref.sql
 * 
 * \exit 0: sucess; 1: failed
 */

# dump license_ref table into a temp file
$temp_file = "licenseref.sql";
$dump_command = "pg_dump -f licenseref.sql -a -t license_ref --column-inserts fossology -U fossy -w";
system($dump_command, $return_var);

if(!$return_var) exit (0);
else exit (1);
?>
