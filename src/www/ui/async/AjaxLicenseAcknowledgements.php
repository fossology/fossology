<?php
/*
 SPDX-FileCopyrightText: © 2022 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * Handles ajax calls for standard license acknowledgements.
 */
namespace Fossology\UI\Ajax;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\LicenseAcknowledgementDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @class AjaxLicenseStdAcknowledgements
 * Handles ajax calls for standard license acknowledgements.
 */
class AjaxLicenseStdAcknowledgements extends DefaultPlugin
{

  /**
   * @var string NAME
   *      Mod name
   */
  const NAME = "ajax_license_acknowledgements";

  /**
   * @var LicenseAcknowledgementDao $licenseAcknowledgementDao
   *      License acknowledgement DAO in use
   */
  private $licenseAcknowledgementDao;

  function __construct()
  {
    parent::__construct(self::NAME,
      array(
        self::REQUIRES_LOGIN => true,
        self::PERMISSION => Auth::PERM_READ
      ));
    $this->licenseAcknowledgementDao = $this->getObject('dao.license.acknowledgement');
  }

  /**
   * @brief Load the license acknowledgements based on request type.
   */
  protected function handle(Request $request)
  {
    $toggleAcknowledgementPk = $request->get("toggle");
    if ($toggleAcknowledgementPk !== null) {
      $status = false;
      try {
        $status = $this->licenseAcknowledgementDao->toggleAcknowledgement(intval($toggleAcknowledgementPk));
      } catch (\UnexpectedValueException $e) {
        $status = $e->getMessage();
      }
      return new JsonResponse(["status" => $status]);
    }
    $reqScope = $request->get("scope", "all");
    $responseArray = null;
    if (strcasecmp($reqScope, "all") === 0) {
      $responseArray = $this->licenseAcknowledgementDao->getAllAcknowledgements();
    } else if (strcasecmp($reqScope, "visible") === 0) {
      $responseArray = $this->licenseAcknowledgementDao->getAllAcknowledgements(true);
    } else {
      try {
        $responseArray = [
          "acknowledgement" => $this->licenseAcknowledgementDao->getAcknowledgement(intval($reqScope))
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

register_plugin(new AjaxLicenseStdAcknowledgements());
