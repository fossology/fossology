<?php
/*
 SPDX-FileCopyrightText: © 2022 Rohit Pandey <rohit.pandey4900@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\SearchHelperDao;
use Fossology\Lib\Dao\UploadDao;

/**
 * @class DetectLicensesFolder
 * @brief Detects licenses declard inside LICENSES directory
 */
class DetectLicensesFolder
{
  /** @var UploadDao */
  private $uploadDao;

  /** @var SearchHelperDao */
  private $searchHelperDao;

  /** @var ClearingDao */
  private $clearingDao;

  function __construct()
  {
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
    $this->searchHelperDao = $GLOBALS['container']->get('dao.searchhelperdao');
    $this->clearingDao = $GLOBALS['container']->get('dao.clearing');
  }

  /**
   * @brief Get licenses decleared in LICENSES folder
   * @param int $uploadId
   * @return array $licenseId, if found. Empty array otherwise.
   */
  function getDeclearedLicenses($uploadId)
  {
    $uploadTreeId = $this->uploadDao->getUploadParent($uploadId);

    // Search the upload tree for LICENSES directory
    $Item = $uploadTreeId;
    $Filename = "LICENSES";
    $tag = "";
    $Page = 0;
    $Limit = 100;
    $SizeMin = "";
    $SizeMax = "";
    $searchtype = "containers";
    $License = "";
    $Copyright = "";

    list($result, $count) = $this->searchHelperDao->GetResults($Item, $Filename, $uploadId, $tag, $Page, $Limit, $SizeMin, $SizeMax, $searchtype, $License, $Copyright, $this->uploadDao, Auth::getGroupId());

    if ($count==0) {
      return array();
    }
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($result[0]['uploadtree_pk']);
    $condition = "(ut.lft BETWEEN $1 AND $2)";
    $params = array($itemTreeBounds->getLeft(), $itemTreeBounds->getRight());
    $licenseId = array();
    $containedItems = $this->uploadDao->getContainedItems($itemTreeBounds, $condition, $params);
    foreach ($containedItems as $item) {
      $licenseId[] = $this->cleanLicenseId($item->getFileName());
    }

    return $licenseId;
  }

  /**
   * @brief Get licenses declared in root LICENSE file
   * @param int $uploadId
   * @return array $licenseId, if found. Empty array otherwise.
   */
  function getLicenseFileDeclaredLicenses($uploadId)
  {
    $uploadTreeId = $this->uploadDao->getUploadParent($uploadId);
    $uploadTreeTableName = GetUploadtreeTableName($uploadId);
    $rootBounds = $this->uploadDao->getItemTreeBounds($uploadTreeId, $uploadTreeTableName);

    $condition = "(ut.ufile_name ilike 'LICENSE' OR ut.ufile_name ilike 'LICENSE.txt' OR ut.ufile_name ilike 'LICENSE.md' OR ut.ufile_name ilike 'LICENSE.rst')";
    $items = $this->uploadDao->getContainedItems($rootBounds, $condition);

    if (empty($items)) {
      return array();
    }

    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($items[0]->getId(), $uploadTreeTableName);

    $clearedLicenses = $this->clearingDao->getClearedLicenses($itemTreeBounds, Auth::getGroupId());
    $licenseIds = array();
    foreach ($clearedLicenses as $licenseRef) {
      $licenseIds[] = $licenseRef->getShortName();
    }

    return array_unique($licenseIds);
  }

  /**
   * @brief Truncate .txt from licenseFileName
   * @param string $licenseFileName - Filename of Declared License
   * @return string Clean license Id
   */
  public function cleanLicenseId($licenseFileName)
  {
    return preg_replace("/(.*)\\.txt$/i", "$1", $licenseFileName);
  }
}