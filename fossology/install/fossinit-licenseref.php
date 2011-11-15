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
 * \file fossinit-licenseref.php
 * \brief Initialize the license_ref table
 **/

/**
 * \brief initLicenseRefTable function, Initialize the license_ref table data.
 *
 * \param $Verbose verbose mode; $Debug display database debug information
 * \return 0 on success,1 on failure
 **/
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
  $dir = opendir($LIBEXECDIR);
  if (!$dir) {
    print "FATAL: Unable to access '$LIBEXECDIR'.\n";
    return (1);
  }
  $file = "$LIBEXECDIR/licenseref.sql";
  
  if (is_file($file)) {
    $handle = fopen($file, "r");
    $pattern = '/^INSERT INTO/';
    $sql = "";
    $flag = 0;
    while(!feof($handle))
    {
      $buffer = fgets($handle, 4096);
      if ( preg_match($pattern, $buffer) == 0)
      {
        $sql .= $buffer;
        continue;
      } else {
        if ($flag)
        {
          @$result = pg_query($PGCONN, $sql);
          if ($result == FALSE)
          {
            $PGError = pg_last_error($PGCONN);
            if ($Debug)
            {
              print "SQL failed: $PGError\n";
            }
          }
          @pg_free_result($result);
        }
        $sql = $buffer;
        $flag = 1;
      }
    }
    @$result = pg_query($PGCONN, $sql);
    if ($result == FALSE)
    {
      $PGError = pg_last_error($PGCONN);
      if ($Debug)
      {
        print "SQL failed: $PGError\n";
      }
    }
    @pg_free_result($result);
    fclose($handle);
  } else {
    print "FATAL: Unable to access '$file'.\n";
    return (1);
  }
  return (0);
} // initLicenseRefTable()
