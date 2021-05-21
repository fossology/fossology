<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * Allows users to manage set of license compatibility rules.
 */
namespace Fossology\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\LicenseStdCommentDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @class AdminLicenseCompatibilityRules
 * Page to allow users to manage the license compatibility rules.
 */
class AdminLicenseCompatibilityRules extends DefaultPlugin
{

  /**
   * @var string NAME
   *      Mod name
   */
  const NAME = "admin_license_compatibility_rules";

  /**
   * @var string UPDATE_PARAM_NAME
   *      Name of the parameter to denote form submit
   */
  const UPDATE_PARAM_NAME = "formUpdated";

  /**
   * @var string RULE_ID_PARAM_NAME
   *      Parameter storing the rule IDs
   */
  const RULE_ID_PARAM_NAME = "licenseRuleLrPK";

  /**
   * @var string INSERT_FIRST_LIC_TYPE_PARAM_NAME
   *      Parameter storing the new license type
   */
  const INSERT_FIRST_LIC_TYPE_PARAM_NAME = "insertFirstLicType";

  /**
   * @var string FIRST_LIC_TYPE_PARAM_NAME
   *      Parameter storing the license type
   */
  const FIRST_LIC_TYPE_PARAM_NAME = "firstLicenseType";

  /**
   * @var string INSERT_SECOND_LIC_TYPE_PARAM_NAME
   *      Parameter storing the new license type
   */
  const INSERT_SECOND_LIC_TYPE_PARAM_NAME = "insertSecondLicType";

  /**
   * @var string SECOND_LIC_TYPE_PARAM_NAME
   *      Parameter storing the license type
   */
  const SECOND_LIC_TYPE_PARAM_NAME = "secondLicenseType";

  /**
   * @var string INSERT_FIRST_LIC_NAME_PARAM_NAME
   *      Parameter storing the new license name
   */
  const INSERT_FIRST_LIC_NAME_PARAM_NAME = "insertFirstLicName";

  /**
   * @var string FIRST_LIC_NAME_PARAM_NAME
   *      Parameter storing the license name
   */
  const FIRST_LIC_NAME_PARAM_NAME = "firstLicenseName";

  /**
   * @var string INSERT_SECOND_LIC_NAME_PARAM_NAME
   *      Parameter storing the new license name
   */
  const INSERT_SECOND_LIC_NAME_PARAM_NAME = "insertSecondLicName";

  /**
   * @var string SECOND_LIC_NAME_PARAM_NAME
   *      Parameter storing the license name
   */
  const SECOND_LIC_NAME_PARAM_NAME = "secondLicenseName";

  /**
   * @var string INSERT_TEXT_PARAM_NAME
   *      Parameter storing the new rule description
   */
  const INSERT_TEXT_PARAM_NAME = "insertLicRuleText";

  /**
   * @var string TEXT_PARAM_NAME
   *      Parameter storing the rule description
   */
  const TEXT_PARAM_NAME = "licenseRuleText";

  /**
   * @var string INSERT_RESULT_PARAM_NAME
   *      Parameter storing the new compatibility result
   */
  const INSERT_RESULT_PARAM_NAME = "insertLicCompatibilityResult";

  /**
   * @var string RESULT_PARAM_NAME
   *      Parameter storing the compatibility result
   */
  const RESULT_PARAM_NAME = "licCompatibilityResult";

  /**
   * @var CompatibilityDao $compatibilityDao
   *      Compatibility DAO in use
   */
  private $compatibilityDao;

  /**
   * @var LicenseDao $licenseDao
   *      License DAO in use
   */
  private $licenseDao;

  function __construct()
  {
    parent::__construct(self::NAME,
      array(
        self::TITLE => "License Compatibility Rules",
        self::MENU_LIST => "Admin::License Admin::Compatibility Rules",
        self::REQUIRES_LOGIN => true,
        self::PERMISSION => Auth::PERM_ADMIN
      ));
    $this->compatibilityDao = $this->getObject('dao.compatibility');
    $this->licenseDao = $this->getObject('dao.license');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    if ($request->get(self::UPDATE_PARAM_NAME, 0) == 1) {
      return new JsonResponse($this->updateRules($request),
        JsonResponse::HTTP_OK);
    } elseif ($request->get("action", 0) === "deleterule") {
        return new JsonResponse($this->deleteRules($request),
          JsonResponse::HTTP_OK);
    }
    global $SysConf;
    $groupId = Auth::getGroupId();
    $vars = [];
    $vars["firstTypeParam"] = self::INSERT_FIRST_LIC_TYPE_PARAM_NAME;
    $vars["secondTypeParam"] = self::INSERT_SECOND_LIC_TYPE_PARAM_NAME;
    $vars["firstNameParam"] = self::INSERT_FIRST_LIC_NAME_PARAM_NAME;
    $vars["secondNameParam"] = self::INSERT_SECOND_LIC_NAME_PARAM_NAME;
    $vars["desc"] = self::INSERT_TEXT_PARAM_NAME;
    $vars["result"] = self::INSERT_RESULT_PARAM_NAME;

    $vars["updateParam"] = self::UPDATE_PARAM_NAME;
    $vars["firstLicTypeParam"] = self::FIRST_LIC_TYPE_PARAM_NAME;
    $vars["secondLicTypeParam"] = self::SECOND_LIC_TYPE_PARAM_NAME;
    $vars["ruleIdParam"] = self::RULE_ID_PARAM_NAME;
    $vars["firstLicNameParam"] = self::FIRST_LIC_NAME_PARAM_NAME;
    $vars["secondLicNameParam"] = self::SECOND_LIC_NAME_PARAM_NAME;
    $vars["textParam"] = self::TEXT_PARAM_NAME;
    $vars["resultParam"] = self::RESULT_PARAM_NAME;

    $licenseType = $SysConf['SYSCONFIG']['LicenseTypes'];
    $licenseType = explode(',', $licenseType);
    $licenseType = array_map('trim', $licenseType);
    $vars['licenseTypes'] = array_combine($licenseType, $licenseType);

    $vars['ruleArray'] = $this->compatibilityDao->getAllRules();

    $licenseArray = $this->licenseDao->getLicenseArray(0);
    $licenseArray = array_column($licenseArray, 'shortname', 'id');

    $licenseList=[0=>"---"];
    foreach ($licenseArray as $id=>$shortname) {
        $licenseList[$id]=$shortname;
    }
    $vars['licenseArray'] = $licenseList;
    return $this->render('admin_license_compatibility_rules.html.twig',
      $this->mergeWithDefault($vars));
  }

  /**
   * @brief update the already existing rules
   * @param Request $request
   * @return array containing the updated values of the licenses
   */
  private function updateRules(Request $request)
  {
    $rules = [];
    $update = [
      "updated" => -1,
      "inserted" => []
    ];
    $licFirstName = $request->get(self::FIRST_LIC_NAME_PARAM_NAME);
    $insertFirstName = $request->get(self::INSERT_FIRST_LIC_NAME_PARAM_NAME);

    $licSecondName = $request->get(self::SECOND_LIC_NAME_PARAM_NAME);
    $insertSecondName = $request->get(self::INSERT_SECOND_LIC_NAME_PARAM_NAME);

    $licFirstType = $request->get(self::FIRST_LIC_TYPE_PARAM_NAME);
    $insertFirstType = $request->get(self::INSERT_FIRST_LIC_TYPE_PARAM_NAME);

    $licSecondType = $request->get(self::SECOND_LIC_TYPE_PARAM_NAME);
    $insertSecondType = $request->get(self::INSERT_SECOND_LIC_TYPE_PARAM_NAME);

    $licText = $request->get(self::TEXT_PARAM_NAME);
    $insertText = $request->get(self::INSERT_TEXT_PARAM_NAME);

    $licResult = $request->get(self::RESULT_PARAM_NAME);
    $insertResult = $request->get(self::INSERT_RESULT_PARAM_NAME);

    if ($licFirstName !== null && !empty($licFirstName)) {
      foreach ($licFirstName as $rulePk => $firstLic) {
        $rules[$rulePk]['firstLic'] = $firstLic;
      }
    }
    if ($licSecondName !== null && !empty($licSecondName)) {
      foreach ($licSecondName as $rulePk => $secondLic) {
        $rules[$rulePk]['secondLic'] = $secondLic;
      }
    }
    if ($licFirstType !== null && !empty($licFirstType)) {
      foreach ($licFirstType as $rulePk => $firstType) {
        $rules[$rulePk]['firstType'] = $firstType;
      }
    }
    if ($licSecondType !== null && !empty($licSecondType)) {
      foreach ($licSecondType as $rulePk => $secondType) {
        $rules[$rulePk]['secondType'] = $secondType;
      }
    }
    if ($licText !== null && !empty($licText)) {
      foreach ($licText as $rulePk => $text) {
        $rules[$rulePk]['text'] = $text;
      }
    }
    if ($licResult !== null && !empty($licResult)) {
      foreach ($licResult as $rulePk => $result) {
        $rules[$rulePk]['result'] = $result;
      }
    }

    if (! empty($rules)) {
      try {
        $update['updated'] = $this->compatibilityDao->updateRuleFromArray(
          $rules);
      } catch (\UnexpectedValueException $e) {
        $update['updated'] = $e->getMessage();
      }
    }

    $update["inserted"] = $this->insertRules($insertFirstName, $insertSecondName, $insertFirstType, $insertSecondType, $insertText, $insertResult);
    return $update;
  }

  /**
   * @brief insert new rules in the UI
   * @param unknown $firstNameArray
   * @param unknown $secondNameArray
   * @param unknown $firstTypeArray
   * @param unknown $secondTypeArray
   * @param unknown $textArray
   * @param unknown $resultArray
   * @return array containing the status whether rule is inserted or not
   */
  private function insertRules($firstNameArray, $secondNameArray, $firstTypeArray, $secondTypeArray, $textArray, $resultArray)
  {
    $returnVal = [];
    if (($firstNameArray !== null && $secondNameArray !== null && $firstTypeArray !== null && $secondTypeArray !== null && $textArray !== null) &&
        (!empty($firstNameArray) && !empty($secondNameArray) && !empty($firstTypeArray) && !empty($secondTypeArray) && !empty($textArray))) {
      for ($i = 0; $i < count($textArray); $i++) {
        if ($firstNameArray[$i] == "0") {
          $firstNameArray[$i]=null;
        }
        if ($secondNameArray[$i] == "0") {
          $secondNameArray[$i]=null;
        }
        if ($firstTypeArray[$i] == "---") {
          $firstTypeArray[$i]=null;
        }
        if ($secondTypeArray[$i] == "---") {
          $secondTypeArray[$i]=null;
        }
          $returnVal[] = $this->compatibilityDao->insertRule($firstNameArray[$i], $secondNameArray[$i], $firstTypeArray[$i], $secondTypeArray[$i], $textArray[$i], $resultArray[$i]);
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

  /**
   * @brief delete a rule from the UI
   * @param Request $request
   * @return array if 1 then rule is deleted otherwise not
   */
  private function deleteRules(Request $request)
  {
    $returnVal = [];
    $rulePk = $request->get("rule");
    $val = $this->compatibilityDao->deleteRule($rulePk);
    if ($val) {
      $returnVal['status'] = 1;
    } else {
        $returnVal['status'] = -1;
    }
    return $returnVal;
  }
}

register_plugin(new AdminLicenseCompatibilityRules());
