<?php
/*
 SPDX-FileCopyrightText: © 2018 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Controller for search queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Dao\SearchHelperDao;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\SearchResult;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @class SearchController
 * @brief Controller for Search model
 */
class SearchController extends RestController
{
  /** @var SearchHelperDao $searchHelperDao */
  private $searchHelperDao;

  /**
   * Perform a search on FOSSology
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpBadRequestException
   */
  public function performSearch($request, $response, $args)
  {
    $this->searchHelperDao = $this->container->get('dao.searchhelperdao');

    $searchType = $request->getHeaderLine("searchType");
    $filename = $request->getHeaderLine("filename");
    $tag = $request->getHeaderLine("tag");
    $filesizeMin = $request->getHeaderLine("filesizemin");
    $filesizeMax = $request->getHeaderLine("filesizemax");
    $license = $request->getHeaderLine("license");
    $copyright = $request->getHeaderLine("copyright");
    $uploadId = $request->getHeaderLine("uploadId");
    $page = $request->getHeaderLine("page");
    $limit = $request->getHeaderLine("limit");

    // set searchtype to search allfiles by default
    if (empty($searchType)) {
      $searchType = "allfiles";
    }

    // set uploadId to 0 - search in all files
    if (empty($uploadId)) {
      $uploadId = 0;
    }

    /*
     * check if at least one parameter was given
     */
    if (empty($filename) && empty($tag) && empty($filesizeMin) &&
      empty($filesizeMax) && empty($license) && empty($copyright)) {
      throw new HttpBadRequestException(
        "At least one parameter, containing a value is required");
    }

    /*
     * check if filesizeMin && filesizeMax are numeric, if existing
     */
    if ((! empty($filesizeMin) && (! is_numeric($filesizeMin) || $filesizeMin < 0)) ||
      (! empty($filesizeMax) && (! is_numeric($filesizeMax) || $filesizeMax < 0))) {
      throw new HttpBadRequestException(
        "filesizemin and filesizemax need to be positive integers!");
    }

    /*
     * check if page && limit are numeric, if existing
     */
    if ((! ($page==='') && (! is_numeric($page) || $page < 1)) ||
      (! ($limit==='') && (! is_numeric($limit) || $limit < 1))) {
      throw new HttpBadRequestException(
        "page and limit need to be positive integers!");
    }

    // set page to 1 by default
    if (empty($page)) {
      $page = 1;
    }

    // set limit to 50 by default and max as 100
    if (empty($limit)) {
      $limit = 50;
    } else if ($limit > 100) {
      $limit = 100;
    }

    $item = GetParm("item", PARM_INTEGER);
    list($results, $count) = $this->searchHelperDao->GetResults($item,
      $filename, $uploadId, $tag, $page-1, $limit,
      $filesizeMin, $filesizeMax, $searchType, $license, $copyright,
      $this->restHelper->getUploadDao(), $this->restHelper->getGroupId());
    $totalPages = intval(ceil($count / $limit));

    $searchResults = [];
    // rewrite it and add additional information about its parent upload
    foreach ($results as $result) {
      $currentUpload = $this->dbHelper->getUploads(
        $this->restHelper->getUserId(), $this->restHelper->getGroupId(), 1, 1,
        $result["upload_fk"], null, true)[1];
      if (! empty($currentUpload)) {
        $currentUpload = $currentUpload[0];
      } else {
        continue;
      }
      $uploadTreePk = $result["uploadtree_pk"];
      $filename = $this->dbHelper->getFilenameFromUploadTree($uploadTreePk);
      $currentResult = new SearchResult($currentUpload, $uploadTreePk, $filename);
      $searchResults[] = $currentResult->getArray();
    }
    return $response->withHeader("X-Total-Pages", $totalPages)->withJson($searchResults, 200);
  }
}
