#!/usr/bin/env php
<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

require_once dirname(__DIR__, 3) . '/lib/php/bootstrap.php';

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Dao\CompatibilityDao;

if ($argc < 5) {
    echo "Usage: {$argv[0]} <uploadId> <agentId> <userId> <groupId>\n";
    exit(1);
}

$uploadId = (int)$argv[1];
$agentId = (int)$argv[2];
$userId = (int)$argv[3];
$groupId = (int)$argv[4];

try {
    $dbManager = \Fossology\Lib\Db\DbManager::getInstance();
    $compatibilityDao = new CompatibilityDao($dbManager);
    
    $concluded = $compatibilityDao->autoConcludeCompatibleFiles($uploadId, $agentId, $userId, $groupId);
    echo "Successfully auto-concluded $concluded files for upload $uploadId\n";
    exit(0);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
