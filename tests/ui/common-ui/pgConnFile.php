#!/usr/bin/php
<?php
/*
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
 * dbCheckR-sqlf
 * \brief  ensure dbCheckResult produces correct output on sql failure
 *
 * @version "$Id$";
 *
 */

global $GlobalReady;
$GlobalReady = TRUE;
require_once ("../../../ui/common/common-ui.php");

global $PG_CONN;
global $DB;

$upStream = '/usr/local/share/fossology/php/pathinclude.php';
$pkg = '/usr/share/fossology/php/pathinclude.php';
if (file_exists($upStream))
{
  require_once ($upStream);
} else
  if (file_exists($pkg))
  {
    require_once ($pkg);
  } else
  {
    $this->assertFileExists($upStream, $message = 'FATAL: cannot find pathinclude.php file, stopping test\n');
    $this->assertFileExists($pkg, $message = 'FATAL: cannot find pathinclude.php file, stopping test\n');
  }
$path = "$SYSCONFDIR/$PROJECT/Db.conf";
$PG_CONN = pg_pconnect(str_replace(";", " ", file_get_contents($path)));
if (!$PG_CONN)
{
  echo "FATAL! Cannot open db\n";
  exit (1);
}

function badPGCONN()
{
  global $PG_CONN;

  echo "Starting badPGCONN\n";
  $PF = fopen('/tmp/testPG','w');
  $result = 0;
  $PG_CONN = $PF;
  DBCheckResult($result, $sql, __FILE__, __LINE__);
}

badPGCONN();
?>
