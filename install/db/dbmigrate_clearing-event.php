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
 * @file dbmigrate_clearing-event.php
 * @brief This file is called by fossinit.php to ensure that
 *        every user had chosen an active group, link decisions with groups,
 *        fill clearing event table with old decisions and link them with decisions
 *        It migrates from 2.6 to 2.6.3
 *
 * This should be called after fossinit calls apply_schema.
 **/

echo "Ensure that every user had chosen an active group";
$dbManager->queryOnce('UPDATE users SET group_fk=gum.group_fk FROM group_user_member gum WHERE users.group_fk is null and user_pk=user_fk');
        
echo "Link decisions with groups\n";
$dbManager->queryOnce('UPDATE clearing_decision cd SET group_fk=u.group_fk FROM users u WHERE cd.user_fk=u.user_pk');

echo "Fill clearing event table with old decisions...";
$dbManager->queryOnce('
  INSERT INTO clearing_event (  uploadtree_fk,
  rf_fk,
  removed,
  user_fk,
  group_fk,
  job_fk,
  type_fk,
  comment,
  reportinfo,
  date_added)
  SELECT 
  cd.uploadtree_fk,
  cl.rf_fk,
  (0=1) removed,
  cd.user_fk,
  cd.group_fk,
  null job_fk,
  type_fk,
  cd.comment,
  cd.reportinfo,
  cd.date_added
  FROM clearing_decision cd, clearing_licenses cl
  WHERE cd.clearing_pk=cl.clearing_fk');

echo " and link them with decisions\n";
$dbManager->queryOnce('
  INSERT INTO clearing_decision_event
  SELECT cd.clearing_pk clearing_fk,ce.clearing_event_pk clearing_event_fk
  FROM clearing_decision cd, clearing_event ce
  WHERE cd.date_added=ce.date_added');
