<?php
/*
 Copyright (C) 2014, Siemens AG
 Author: Daniele Fognini

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
 */

include_once("getReportDataCommon.php");

use Fossology\Lib\Data\DecisionTypes;

global $container;

/** @var CopyrightDao $copyrightDao */
$copyrightDao = $container->get('dao.copyright');

/** @var UploadDao $uploadDao */
$uploadDao = $container->get('dao.upload');

/** @var TreeDao $treeDao */
$treeDao = $container->get('dao.tree');

/** @var DbManager $dbManager */
$dbManger = $container->get('db.manager');

$uploadId = getUploadIdArg();
$uploadTreeTableName = $uploadDao->getUploadTreeTableName($uploadId);

$ungrupedStatements = $copyrightDao->getAllDecisions("copyright", $uploadId, $uploadTreeTableName, DecisionTypes::IDENTIFIED,  "statement");

$fileNames = array();
foreach($ungrupedStatements as $key => $statement) {
  $id = $statement['id'];
  $content = $statement['content'];
  $uploadTreeId = $statement['uploadtree_pk'];
  $filePathRow = $treeDao->getFullPath($uploadTreeId, $uploadTreeTableName);
  $fileNames[$id.$content][] = $filePathRow['file_path'];
}

$statements = array();
foreach($ungrupedStatements as $key => $statement) {
  $id = $statement['id'];
  $description = $statement['description'];
  //$textfinding = $statement['textfinding'];
  $content = $statement['content'];

  $statements[$id.$content] =
    array("content" => $content, //$textfinding,
          "text" => $description,
          "files" => array_values($fileNames[$id.$content]));
}

$result = array("statements" => array_values($statements));

print json_encode($result);
