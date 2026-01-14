<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Controller to search files based on hash provided
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Helper\FileHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\File;
use Fossology\UI\Api\Models\Findings;
use Fossology\UI\Api\Models\Hash;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @class FileSearchController
 * @brief Controller for file searching
 */
class FileSearchController extends RestController
{
  /**
   * @var FileHelper $fileHelper
   * File helper object
   */
  private $fileHelper;

  /**
   * @var ClearingDao $clearingDao
   * Clearing Dao object
   */
  private $clearingDao;

  /**
   * @var LicenseDao $licenseDao
   * License Dao object
   */
  private $licenseDao;

  /**
   * @param ContainerInterface $container
   */
  public function __construct($container)
  {
    parent::__construct($container);
    $this->fileHelper = $this->container->get('helper.fileHelper');
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->licenseDao = $this->container->get('dao.license');
  }

  /**
   * Get the file information based on hashes sent
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getFiles($request, $response, $args)
  {
    $fileListJSON = $this->getParsedBody($request);
    if ($fileListJSON === null || !is_array($fileListJSON)) {
      throw new HttpBadRequestException("Request body is missing or invalid. Expected a JSON array of file hashes.");
    }
    $inputFileList = File::parseFromArray($fileListJSON);
    $existsList = [];
    $nonExistsList = [];

    foreach ($inputFileList as $inputFile) {
      if ($inputFile->getMessage() == File::INVALID) {
        $nonExistsList[] = $inputFile;
        continue;
      }

      $pfileId = $this->getPfileId($inputFile);
      if ($pfileId !== false) {
        $existsList[$pfileId] = $inputFile;
      } else {
        $inputFile->setMessage(File::NOT_FOUND);
        $nonExistsList[] = $inputFile;
      }
    }

    $this->getInfoForFiles($existsList);
    $existsList = array_values($existsList);

    foreach ($nonExistsList as $file) {
      $existsList[] = $file;
    }
    return $response->withJson(array_map(function ($file) {
        return $file->getArray();
    }, $existsList), 200);
  }

  /**
   * For given file, get the `pfile_pk` from the file hash. Return false if not
   * found.
   *
   * @param File $file File to get id for
   * @sa Fossology::UI::Api::Helper::FileHelper::getPfile()
   */
  private function getPfileId(&$file)
  {
    $hash = $file->getHash();
    $pfile = $this->fileHelper->getPfile($hash);
    if ($pfile === null) {
      return false;
    }
    $newHash = new Hash($pfile['pfile_sha1'], $pfile['pfile_md5'],
      $pfile['pfile_sha256'], $pfile['pfile_size']);
    $file->setHash($newHash);
    return intval($pfile['pfile_pk']);
  }

  /**
   * For given array of files, update the upload ids, scanner findings and
   * conclusions.
   *
   * @param File[] $inputFileList
   */
  private function getInfoForFiles(&$inputFileList)
  {
    foreach ($inputFileList as $pfileId => $file) {
      $uploads = $this->getPackageUpload($pfileId);
      if (! empty($uploads)) {
        $scannerFindings = [];
        $copyright = [];
        $conclusions = $this->getMainLicenses($uploads);
      } else {
        $scannerFindings = $this->getFileFindings($pfileId);
        $conclusions = $this->getFileConclusions($pfileId);
        $copyright = $this->getFileCopyright($pfileId);
      }
      $findings = new Findings();
      $findings->setScanner($scannerFindings);
      $findings->setConclusion($conclusions);
      $findings->setCopyright($copyright);
      $inputFileList[$pfileId]->setFindings($findings);
      $inputFileList[$pfileId]->setUploads($uploads);
    }
  }

  /**
   * Get the scanner findings for given pfile id
   * @param integer $pfileId
   * @sa Fossology::UI::Api::Helper::FileHelper::pfileScannerFindings()
   */
  private function getFileFindings($pfileId)
  {
    return $this->fileHelper->pfileScannerFindings($pfileId);
  }

  /**
   * Get the license conclusions for the given pfile id
   * @param integer $pfileId
   * @sa Fossology::UI::Api::Helper::FileHelper::pfileConclusions()
   */
  private function getFileConclusions($pfileId)
  {
    return $this->fileHelper->pfileConclusions($this->restHelper->getGroupId(),
      $pfileId);
  }

  /**
   * Get the copyright for given pfile id
   * @param integer $pfileId
   * @sa Fossology::UI::Api::Helper::FileHelper::pfileCopyright()
   */
  private function getFileCopyright($pfileId)
  {
    return $this->fileHelper->pfileCopyright($pfileId);
  }

  /**
   * Get the upload ids where the file has been uploaded as the source package
   * @param integer $pfileId
   */
  private function getPackageUpload($pfileId)
  {
    return $this->filterAccessibleUploads(
      $this->fileHelper->getPackageUpload($pfileId));
  }

  /**
   * From a list of uploads, filter out inaccessible uploads.
   * @param array $uploads List of uploads to filter from
   * @return array Array of accessible uploads
   */
  private function filterAccessibleUploads($uploads)
  {
    if (empty($uploads)) {
      return [];
    }
    return array_filter($uploads, function ($upload) {
      return $this->restHelper->getUploadDao()->isAccessible($upload,
        $this->restHelper->getGroupId());
    });
  }

  /**
   * Get the list of main licenses from a list of uploads
   * @param array $uploads Uploads to get main licenses from
   * @return array Unique array of main licenses for given uploads
   */
  private function getMainLicenses($uploads)
  {
    $mainLicenses = array();
    foreach ($uploads as $upload) {
      $licenses = $this->clearingDao->getMainLicenseIds($upload,
        $this->restHelper->getGroupId());
      foreach ($licenses as $licenseId) {
        $license = $this->licenseDao->getLicenseById($licenseId,
          $this->restHelper->getGroupId());
        if ($license !== null) {
          $mainLicenses[$license->getId()] = $license->getShortName();
        }
      }
    }
    natcasesort($mainLicenses);
    return array_values($mainLicenses);
  }
}
