<?php
/*
 SPDX-FileCopyrightText: © 2020 Orange

 SPDX-License-Identifier: GPL-2.0-only
 Author: Drozdz Bartlomiej <bartlomiej.drozdz@orange.com>
*/

/**
 * \file instance_uuid.php
 * Creates a single row table with random UUID.
 * Represents unique DB instance.
 */

 function CreateInstanceUUIDTable($dbManager)
 {
     if($dbManager == NULL){
         echo "Missing dbManager object. Can't create instance_uuid table.\n";
         return false;
     }

    $dbManager->queryOnce("
BEGIN;
CREATE EXTENSION IF NOT EXISTS \"uuid-ossp\";
CREATE TABLE IF NOT EXISTS instance (
    id bool PRIMARY KEY DEFAULT TRUE,
    instance_uuid UUID NOT NULL DEFAULT uuid_generate_v4(),
CONSTRAINT tbl_id_uni CHECK (id));
COMMIT;
     ");
     $row = $dbManager->getSingleRow("SELECT count(instance_uuid) FROM instance;", array(), 'instance_uuid.count' );
     if ($row['count'] == 0){
         echo "INSTANCE UUID Empty - creating...\n";
         $dbManager->queryOnce("INSERT INTO instance DEFAULT VALUES;");
     }

     $row_uuid = $dbManager->getSingleRow("SELECT instance_uuid FROM instance;", array(), 'instance_uuid' );
     echo "INSTANCE UUID: ", $row_uuid['instance_uuid'], "\n";

 }

echo "*** Instance UUID ***\n";
CreateInstanceUUIDTable($dbManager);
