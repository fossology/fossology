<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file dbmigrate_real-parent.php
 * @brief Add link to real parent in uploadtree table
 *        It migrates from 2.6.2 to 3.0.0
 *
 * This should be called after fossinit calls apply_schema.
 **/

echo "Adding link to real parent in uploadtree table\n";
$dbManager->queryOnce('UPDATE uploadtree SET realparent = getItemParent(uploadtree_pk,\'uploadtree\') WHERE realparent IS NULL');