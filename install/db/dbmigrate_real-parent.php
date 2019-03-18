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
 * @file dbmigrate_real-parent.php
 * @brief Add link to real parent in uploadtree table
 *        It migrates from 2.6.2 to 3.0.0
 *
 * This should be called after fossinit calls apply_schema.
 **/

echo "Adding link to real parent in uploadtree table\n";
$dbManager->queryOnce('UPDATE uploadtree SET realparent = getItemParent(uploadtree_pk) WHERE realparent IS NULL');