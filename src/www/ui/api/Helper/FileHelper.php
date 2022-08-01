<?php
/*
 SPDX-FileCopyrightText: © 2018, 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Helper for file related queries
 */
namespace Fossology\UI\Api\Helper;

use Fossology\Lib\Dao\PfileDao;
use Fossology\UI\Api\Models\Hash;

/**
 * @class FileHelper
 * @brief Handle file related queries
 */
class FileHelper
{
  /**
   * @var PfileDao $pfileDao
   * Pfile Dao object
   */
  private $pfileDao;

  /**
   * Constructor for FileHelper
   *
   * @param PfileDao $pfileDao
   */
  public function __construct(PfileDao $pfileDao)
  {
    $this->pfileDao = $pfileDao;
  }

  /**
   * Get the pfile info for given Hash
   *
   * @param Hash $hash Hash to get pfile info from
   * @return array|NULL
   * @sa Fossology::Lib::Dao::PfileDao::getPfile()
   */
  public function getPfile($hash)
  {
    return $this->pfileDao->getPfile($hash->getSha1(), $hash->getMd5(),
      $hash->getSha256(), $hash->getSize());
  }

  /**
   * Get the scanner findings for given pfile
   *
   * @param integer $pfileId PfileId to get licenses from
   * @return array List of licenses found
   * @sa Fossology::Lib::Dao::PfileDao::getScannerFindings()
   */
  public function pfileScannerFindings($pfileId)
  {
    return $this->pfileDao->getScannerFindings($pfileId);
  }

  /**
   * Get the conclusions for given pfile done by given group
   *
   * @param integer $groupId Group to filter conclusions from
   * @param integer $pfileId Pfile to get conclusions for
   * @return array List of licenses concluded
   * @sa Fossology::Lib::Dao::PfileDao::getConclusions()
   */
  public function pfileConclusions($groupId, $pfileId)
  {
    return $this->pfileDao->getConclusions($groupId, $pfileId);
  }

  /**
   * Get the uploads where the pfile was uploaded as package
   *
   * @param integer $pfileId Pfileid to search from
   * @return array|NULL Array of uploads or NULL if not found
   * @sa Fossology::Lib::Dao::PfileDao::getUploadForPackage()
   */
  public function getPackageUpload($pfileId)
  {
    return $this->pfileDao->getUploadForPackage($pfileId);
  }

  /**
   * Get the copyright for given pfile
   *
   * @param integer $pfileId PfileId to get copyright for
   * @return array List of copyrights found
   * @sa Fossology::Lib::Dao::PfileDao::getCopyright()
   */
  public function pfileCopyright($pfileId)
  {
    return $this->pfileDao->getCopyright($pfileId);
  }
}
