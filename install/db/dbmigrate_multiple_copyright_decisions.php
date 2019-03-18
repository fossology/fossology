<?php
/***********************************************************
Copyright (C) 2017 Siemens AG

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
 * @file dbmigrate_multiple_copyright_decisions.php
 * @brief Add a new column `is_enabled` to decisions.
 *        This is by default false except for the most recent decision which is active
 *        It migrates from 3.1.0 to 3.2.0
 *
 * This should be called after fossinit calls apply_schema.
 **/

function addBooleanColumnTo($dbManager, $tableName, $columnName = 'is_enabled')
{
  echo "Migrate: Add and setup column=$columnName to table=$tableName\n";
  if (! $dbManager->existsColumn($tableName, $columnName))
  {
    $dbManager->queryOnce("ALTER TABLE $tableName
                             ADD COLUMN $columnName BOOLEAN;");
  }

  $dbManager->queryOnce("UPDATE $tableName
                           SET $columnName = " . $tableName . "_pk IN
                             (SELECT MAX(" . $tableName . "_pk) AS enabled_pk
                                FROM $tableName
                                GROUP BY pfile_fk);");
  $dbManager->queryOnce("ALTER TABLE $tableName
                           ALTER COLUMN $columnName
                             SET NOT NULL;");
  $dbManager->queryOnce("ALTER TABLE $tableName
                           ALTER COLUMN $columnName
                             SET DEFAULT TRUE;");
}

foreach (array('copyright','ecc') as $name)
{
  /* @var $dbManager DbManager */
  addBooleanColumnTo($dbManager, $name.'_decision');
}
