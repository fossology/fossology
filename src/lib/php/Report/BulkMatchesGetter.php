<?php
/*
 SPDX-FileCopyrightText: © 2014-2017 Siemens AG
 Author: Daniele Fognini
 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Report;

use Fossology\Lib\Dao\ClearingDao;

class BulkMatchesGetter extends ClearedGetterCommon
{
  /** @var ClearingDao */
  private $clearingDao;

  public function __construct()
  {
    global $container;
    $this->clearingDao = $container->get('dao.clearing');
    parent::__construct($groupBy = "bulkId");
  }

  protected function getStatements($uploadId, $uploadTreeTableName, $groupId=0)
  {
    $result = array();

    $parentTreeBounds = $this->uploadDao->getParentItemBounds($uploadId, $uploadTreeTableName);
    $bulkHistory = $this->clearingDao->getBulkHistory($parentTreeBounds, $groupId, false);

    foreach ($bulkHistory as $bulk) {
      $allLicenses = "";
      $bulkId = $bulk['bulkId'];
      foreach ($bulk['removedLicenses'] as $removedLics) {
        $allLicenses .= ($removedLics ? "[remove] " : "") . $removedLics.', ';
      }
      foreach ($bulk['addedLicenses'] as $addedLics) {
        $allLicenses .= ($addedLics ? "[add] " : "") . $addedLics.', ';
      }
      $allLicenses = trim($allLicenses, ', ');
      $content = $bulk['text'];

      foreach ($this->clearingDao->getBulkMatches($bulkId, $groupId) as $bulkMatch) {
        $uploadTreeId = $bulkMatch['itemid'];

        $result[] = array(
          'bulkId' => $bulkId,
          'content' => $content,
          'textfinding' => $allLicenses,
          'description' => $content,
          'uploadtree_pk' => $uploadTreeId
        );
      }
    }

    return $result;
  }
}

