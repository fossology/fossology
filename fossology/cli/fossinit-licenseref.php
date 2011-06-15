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
 * fossinit-licenseref
 *
 */
/**
 * initLicenseRefTable
 * \brief Initialize the license_ref table data
 *
 * @return 0 on success,1 on failure
 */
function initLicenseRefTable($Verbose, $Debug)
{
  global $LIBEXECDIR;
  global $PGCONN;

  print "  Importing license_ref table data\n";
  flush();

  if (!is_dir($LIBEXECDIR)) {
    print "FATAL: Directory '$LIBEXECDIR' does not exist.\n";
    return (1);
  }
  $Dir = opendir($LIBEXECDIR);
  if (!$Dir) {
    print "FATAL: Unable to access '$LIBEXECDIR'.\n";
    return (1);
  }
  $File = "$LIBEXECDIR/licenseref.sql";
  
  if (is_file($File)) {
    $Command = "su postgres -c 'psql < $File fossology'";
    if ($Debug) {
      print "$SQL;\n";
    }
    else {
      system($Command, $Status);
      if ($Status != 0) {
        print "FATAL: '$Command' failed to initialize license_ref table data\n";
        return (1);
      }
    }
  } else {
    print "FATAL: Unable to access '$File'.\n";
    return (1);
  }
  return (0);
} // initLicenseRefTable()
