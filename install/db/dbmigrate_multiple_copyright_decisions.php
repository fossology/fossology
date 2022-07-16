<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

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
