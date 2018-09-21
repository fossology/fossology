<?php
/***********************************************************
 Copyright (C) 2015 Siemens AG
 
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
 * @file dbmigrate_bulk_license.php
 * @brief This file is called by fossinit.php to transform bulk licenses
 *        into bulk license sets and drop rf_fk column from license_ref_bulk
 *        It migrates from 2.6.3.3 to 3.0.0
 *
 * This should be called after fossinit calls apply_schema.
 **/

echo "Transform bulk licenses into bulk license sets...";
$dbManager->queryOnce('
  INSERT INTO license_set_bulk (lrb_fk, rf_fk, removing)
  SELECT lrb_pk lrb_fk, rf_fk, removing FROM license_ref_bulk');
echo "...and drop the old columns\n";
$libschema->dropColumnsFromTable(array('rf_fk','removing'), 'license_ref_bulk');