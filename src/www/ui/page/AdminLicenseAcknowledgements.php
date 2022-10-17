<?php
/*
 SPDX-FileCopyrightText: © 2022 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * Allows users to manage set of standard license acknowledgements.
 */
namespace Fossology\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\LicenseAcknowledgementDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @class AdminLicenseAcknowledgements
 * Page to allow users to manage the standard license acknowledgements.
 */
class AdminLicenseAcknowledgements extends DefaultPlugin
{

  /**
   * @var string NAME
   *      Mod name
   */
  const NAME = "admin_license_acknowledgements";

  /**
   * @var string ACKNOWLEDGEMENT_UPDATE_PARAM_NAME
   *      Name of the parameter to denote form submit
   */
  const ACKNOWLEDGEMENT_UPDATE_PARAM_NAME = "formUpdated";

  /**
   * @var string ACKNOWLEDGEMENT_PARAM_NAME
   *      Parameter storing the acknowledgement names
   */
  const ACKNOWLEDGEMENT_PARAM_NAME = "licenseAcknowledgement";

  /**
   * @var string ACKNOWLEDGEMENT_ID_PARAM_NAME
   *      Parameter storing the acknowledgement IDs
   */
  const ACKNOWLEDGEMENT_ID_PARAM_NAME = "licenseAcknowledgementLaPK";

  /**
   * @var string ACKNOWLEDGEMENT_NAME_PARAM_NAME
   *      Parameter storing the acknowledgements
   */
  const ACKNOWLEDGEMENT_NAME_PARAM_NAME = "licenseAcknowledgementName";

  /**
   * @var string INSERT_ACKNOWLEDGEMENT_NAME_PARAM
   *      Parameter storing the new names
   */
  const INSERT_ACKNOWLEDGEMENT_NAME_PARAM = "insertLicNames";

  /**
   * @var string ACKNOWLEDGEMENT_INSERT_PARAM_NAME
   *      Parameter storing the new acknowledgements
   */
  const ACKNOWLEDGEMENT_INSERT_PARAM_NAME = "insertLicAcknowledgements";

  /**
   * @var string ACKNOWLEDGEMENT_ENABLE_PARAM_NAME
   *      Parameter storing the acknowledgement status
   */
  const ACKNOWLEDGEMENT_ENABLE_PARAM_NAME = "licAcknowledgementEnabled";

  /**
   * @var LicenseAcknowledgementDao $licenseAcknowledgementDao
   *      License acknowledgement DAO in use
   */
  private $licenseAcknowledgementDao;

  function __construct()
  {
    parent::__construct(self::NAME,
      array(
        self::TITLE => "Admin License Acknowledgements",
        self::MENU_LIST => "Admin::License Admin::Acknowledgements",
        self::REQUIRES_LOGIN => true,
        self::PERMISSION => Auth::PERM_ADMIN
      ));
    $this->licenseAcknowledgementDao = $this->getObject('dao.license.acknowledgement');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    if ($request->get(self::ACKNOWLEDGEMENT_UPDATE_PARAM_NAME, 0) == 1) {
      return new JsonResponse($this->updateAcknowledgements($request),
        JsonResponse::HTTP_OK);
    }

    $vars = [];
    $vars["updateParam"] = self::ACKNOWLEDGEMENT_UPDATE_PARAM_NAME;
    $vars["acknowledgementParam"] = self::ACKNOWLEDGEMENT_PARAM_NAME;
    $vars["acknowledgementIdParam"] = self::ACKNOWLEDGEMENT_ID_PARAM_NAME;
    $vars["acknowledgementNameParam"] = self::ACKNOWLEDGEMENT_NAME_PARAM_NAME;
    $vars["enableParam"] = self::ACKNOWLEDGEMENT_ENABLE_PARAM_NAME;

    $vars['acknowledgementArray'] = $this->licenseAcknowledgementDao->getAllAcknowledgements();
    return $this->render('admin_license_acknowledgements.html.twig',
      $this->mergeWithDefault($vars));
  }

  /**
   * Get the parameters from the request and update the acknowledgements.
   *
   * @param Request $request The request
   * @return array Number of acknowledgements updated/inserted as value of
   *         corresponding keys, or error (if any).
   */
  private function updateAcknowledgements(Request $request)
  {
    $acknowledgements = [];
    $update = [
      "updated" => -1,
      "inserted" => []
    ];
    $acknowledgementStrings = $request->get(self::ACKNOWLEDGEMENT_PARAM_NAME);
    $acknowledgementNames = $request->get(self::ACKNOWLEDGEMENT_NAME_PARAM_NAME);
    $insertNames = $request->get(self::INSERT_ACKNOWLEDGEMENT_NAME_PARAM);
    $insertAcknowledgements = $request->get(self::ACKNOWLEDGEMENT_INSERT_PARAM_NAME);
    if ($acknowledgementStrings !== null && !empty($acknowledgementStrings)) {
      foreach ($acknowledgementStrings as $acknowledgementPk => $acknowledgement) {
        $acknowledgements[$acknowledgementPk]['acknowledgement'] = $acknowledgement;
      }
    }
    if ($acknowledgementNames !== null && !empty($acknowledgementNames)) {
      foreach ($acknowledgementNames as $acknowledgementPk => $name) {
        $acknowledgements[$acknowledgementPk]['name'] = $name;
      }
    }
    if (! empty($acknowledgements)) {
      try {
        $update['updated'] = $this->licenseAcknowledgementDao->updateAcknowledgementFromArray(
          $acknowledgements);
      } catch (\UnexpectedValueException $e) {
        $update['updated'] = $e->getMessage();
      }
    }
    $update["inserted"] = $this->insertAcknowledgements($insertNames, $insertAcknowledgements);
    return $update;
  }

  /**
   * Insert new acknowledgements
   *
   * @param array $namesArray    Array containing new names
   * @param array $acknowledgementsArray Array containing new acknowledgements
   * @return number[]
   */
  private function insertAcknowledgements($namesArray, $acknowledgementsArray)
  {
    $returnVal = [];
    if (($namesArray !== null && $acknowledgementsArray !== null) &&
      (! empty($namesArray) && !empty($acknowledgementsArray))) {
      for ($i = 0; $i < count($namesArray); $i++) {
        $returnVal[] = $this->licenseAcknowledgementDao->insertAcknowledgement($namesArray[$i],
          $acknowledgementsArray[$i]);
      }
      $returnVal['status'] = 0;
      // Check if at least one value was inserted
      if (count(array_filter($returnVal, function($val) {
        return $val > 0; // No error
      })) > 0) {
        $returnVal['status'] |= 1;
      }
      // Check if an error occurred while insertion
      if (in_array(-1, $returnVal)) {
        $returnVal['status'] |= 1 << 1;
      }
      // Check if an exception occurred while insertion
      if (in_array(-2, $returnVal)) {
        $returnVal['status'] |= 1 << 2;
      }
    }
    return $returnVal;
  }
}

register_plugin(new AdminLicenseAcknowledgements());
