<?php
/*
 SPDX-FileCopyrightText: © 2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * Handles ajax calls for standard license comments.
 */
namespace Fossology\UI\Ajax;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\LicenseStdCommentDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @class AjaxLicenseStdComments
 * Handles ajax calls for standard license comments.
 */
class AjaxLicenseStdComments extends DefaultPlugin
{

  /**
   * @var string NAME
   *      Mod name
   */
  const NAME = "ajax_license_std_comments";

  /**
   * @var LicenseStdCommentDao $licenseCommentDao
   *      License comment DAO in use
   */
  private $licenseCommentDao;

  function __construct()
  {
    parent::__construct(self::NAME,
      array(
        self::REQUIRES_LOGIN => true,
        self::PERMISSION => Auth::PERM_READ
      ));
    $this->licenseCommentDao = $this->getObject('dao.license.stdc');
  }

  /**
   * @brief Load the license comments based on request type.
   */
  protected function handle(Request $request)
  {
    $toggleCommentPk = $request->get("toggle");
    if ($toggleCommentPk !== null) {
      $status = false;
      try {
        $status = $this->licenseCommentDao->toggleComment(intval($toggleCommentPk));
      } catch (\UnexpectedValueException $e) {
        $status = $e->getMessage();
      }
      return new JsonResponse(["status" => $status]);
    }
    $reqScope = $request->get("scope", "all");
    $responseArray = null;
    if (strcasecmp($reqScope, "all") === 0) {
      $responseArray = $this->licenseCommentDao->getAllComments();
    } else if (strcasecmp($reqScope, "visible") === 0) {
      $responseArray = $this->licenseCommentDao->getAllComments(true);
    } else {
      try {
        $responseArray = [
          "comment" => $this->licenseCommentDao->getComment(intval($reqScope))
        ];
      } catch (\UnexpectedValueException $e) {
        $responseArray = [
          "status" => false,
          "error" => $e->getMessage()
        ];
        return new JsonResponse($responseArray, JsonResponse::HTTP_NOT_FOUND);
      }
    }
    return new JsonResponse($responseArray, JsonResponse::HTTP_OK);
  }
}

register_plugin(new AjaxLicenseStdComments());
