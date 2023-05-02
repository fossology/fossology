<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file dbmigrate_bulk_license.php
 * @brief This file is called by fossinit.php to transform bulk licenses
 *        into bulk license sets and drop rf_fk column from license_ref_bulk
 *        It migrates from 2.6.3.3 to 3.0.0
 *
 * This should be called after fossinit calls apply_schema.
 **/

$rf_fkExists = $dbManager->existsColumn("license_ref_bulk", "rf_fk");
$removingExists = $dbManager->existsColumn("license_ref_bulk", "removing");

if ($rf_fkExists && $removingExists) {
  echo "Transform bulk licenses into bulk license sets...";
  $dbManager->queryOnce('
  INSERT INTO license_set_bulk (lrb_fk, rf_fk, removing)
  SELECT lrb_pk lrb_fk, rf_fk, removing FROM license_ref_bulk');
  echo "...and drop the old columns\n";
  $libschema->dropColumnsFromTable(array('rf_fk', 'removing'), 'license_ref_bulk');
}
