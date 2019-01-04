<?php
/***********************************************************
Copyright (C) 2018 Atos

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
 * @file dbmigrate_smc.php
 * @brief Add a new column `smc` to upload.
 *        This is by default false.
 *        Automatically ignore SCM data when scaning
 *
 * This should be called if fossinit has already called apply_schema
 * before this patch.
 **/

$tableName="upload";
$columnName="scm";
echo "Migrate: Add and setup column=$columnName to table=$tableName\n";
if(! $dbManager->existsColumn($tableName, $columnName)) {
  $dbManager->queryOnce("ALTER TABLE $tableName ADD COLUMN $columnName BOOLEAN DEFAULT false;");
}
