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

use Fossology\UI\Api\Helper\ResponseHelper;
use Psr\Http\Message\ServerRequestInterface;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\SearchResult;

require_once dirname(dirname(__DIR__)) . "/search-helper.php";

/**
 * @class SearchController
 * @brief Controller for Search model
 */
class SearchController extends RestController
{
  /**
   * Perform a search on FOSSology
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function performSearch($request, $response, $args)
  {
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
      $returnVal = new Info(400,
        "Bad Request. At least one parameter, containing a value is required",
        InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    /*
     * check if filesizeMin && filesizeMax are numeric, if existing
     */
    if ((! empty($filesizeMin) && (! is_numeric($filesizeMin) || $filesizeMin < 0)) ||
      (! empty($filesizeMax) && (! is_numeric($filesizeMax) || $filesizeMax < 0))) {
      $returnVal = new Info(400,
        "Bad Request. filesizemin and filesizemax need to be positive integers!",
        InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    /*
     * check if page && limit are numeric, if existing
     */
    if ((! ($page==='') && (! is_numeric($page) || $page < 1)) ||
      (! ($limit==='') && (! is_numeric($limit) || $limit < 1))) {
      $returnVal = new Info(400,
        "Bad Request. page and limit need to be positive integers!",
        InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
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
    list($results, $count) = GetResults($item, $filename, $uploadId, $tag, $page-1, $limit,
      $filesizeMin, $filesizeMax, $searchType, $license, $copyright,
      $this->restHelper->getUploadDao(), $this->restHelper->getGroupId(),
      $GLOBALS['PG_CONN']);
    $totalPages = intval(ceil($count / $limit));

    $searchResults = [];
    // rewrite it and add additional information about it's parent upload
    for ($i = 0; $i < sizeof($results); $i ++) {
      $currentUpload = $this->dbHelper->getUploads(
        $this->restHelper->getUserId(), $this->restHelper->getGroupId(), 1, 1,
        $results[$i]["upload_fk"], null, true)[1];
      if (! empty($currentUpload)) {
        $currentUpload = $currentUpload[0];
      } else {
        continue;
      }
      $uploadTreePk = $results[$i]["uploadtree_pk"];
      $filename = $this->dbHelper->getFilenameFromUploadTree($uploadTreePk);
      $currentResult = new SearchResult($currentUpload, $uploadTreePk, $filename);
      $searchResults[] = $currentResult->getArray();
    }
    return $response->withHeader("X-Total-Pages", $totalPages)->withJson($searchResults, 200);
  }
}
