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
use Fossology\Lib\Dao\CompatibilityDao;
use Fossology\Lib\Dao\LicenseDao;
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
    if ($request->get('action') === 'fetchLicenseData') {
      return $this->fetchLicenseData();
    }
    if ($request->get("action", "") === "fetchRules") {
      return $this->fetchRules($request);
    }

    if ($request->get(self::UPDATE_PARAM_NAME, 0) == 1) {
      return new JsonResponse($this->updateRules($request),
        JsonResponse::HTTP_OK);
    } elseif ($request->get("action", 0) === "deleterule") {
      return new JsonResponse($this->deleteRules($request),
        JsonResponse::HTTP_OK);
    } elseif ($request->get("action", 0) === "addrule") {
      return $this->addRule();
    }
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

    return $this->render('admin_license_compatibility_rules.html.twig',
      $this->mergeWithDefault($vars));
  }

  /**
   * @brief Fetch the available license data
   * @return JsonResponse
   */
  private function fetchLicenseData()
  {
    global $SysConf;

    $licenseArray = $this->licenseDao->getLicenseArray(0);
    $licenseArray = array_column($licenseArray, 'shortname', 'id');
    $licenseList = [0 => "---"];
    $licenseList += $licenseArray;

    $licenseTypes = $SysConf['SYSCONFIG']['LicenseTypes'];
    $licenseTypes = explode(',', $licenseTypes);
    $licenseTypes = array_map('trim', $licenseTypes);
    $licenseTypes = ["---", ...$licenseTypes];
    $licenseTypeList = array_combine($licenseTypes, $licenseTypes);

    return new JsonResponse([
      'licenseArray' => $licenseList,
      'licenseTypes' => $licenseTypeList,
    ], JsonResponse::HTTP_OK);
  }

  /**
   * @brief Fetch the compatibility rules based on search query and pagination
   * @param Request $request The request containing query parameters for pagination and search
   * @return JsonResponse
   */
  private function fetchRules(Request $request)
  {
    $offset = intval($request->query->get('start', 0));
    $limit = intval($request->query->get('length', 10));
    $draw = intval($request->query->get('draw', 1));
    $searchQuery = $_GET['search']['value'] ?? '';

    if (!empty($searchQuery)) {
      $searchQuery = '%' . $searchQuery . '%';
    }

    $totalCount = $this->compatibilityDao->getTotalRulesCount($searchQuery);
    $ruleArray = $this->compatibilityDao->getAllRules($limit, $offset, $searchQuery);

    return new JsonResponse([
      "draw" => $draw,
      "recordsTotal" => $totalCount,
      "recordsFiltered" => $totalCount,
      "data" => $ruleArray,
    ], JsonResponse::HTTP_OK);
  }

  /**
   * @brief Add a new empty compatibility rule
   * @return JsonResponse
   */
  private function addRule()
  {
    $result = $this->compatibilityDao->insertEmptyRule();
    if ($result > 0) {
      return new JsonResponse(["lr_pk" => $result], JsonResponse::HTTP_OK);
    }
    return new JsonResponse(["error" => "Failed to add rule."], JsonResponse::HTTP_BAD_REQUEST);
  }

  /**
   * @brief Update the already existing rules
   * @param Request $request
   * @return array Containing the updated values of the licenses
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

    if (!empty($licFirstName)) {
      foreach ($licFirstName as $rulePk => $firstLic) {
        if ($firstLic == "0") {
          $rules[$rulePk]['firstLic'] = null;
        } else {
          $rules[$rulePk]['firstLic'] = $firstLic;
        }
      }
    }
    if (!empty($licSecondName)) {
      foreach ($licSecondName as $rulePk => $secondLic) {
        if ($secondLic == "0") {
          $rules[$rulePk]['secondLic'] = null;
        } else {
          $rules[$rulePk]['secondLic'] = $secondLic;
        }
      }
    }
    if (!empty($licFirstType)) {
      foreach ($licFirstType as $rulePk => $firstType) {
        if ($firstType == "---") {
          $rules[$rulePk]['firstType'] = null;
        } else {
          $rules[$rulePk]['firstType'] = $firstType;
        }
      }
    }
    if (!empty($licSecondType)) {
      foreach ($licSecondType as $rulePk => $secondType) {
        if ($secondType == "---") {
          $rules[$rulePk]['secondType'] = null;
        } else {
          $rules[$rulePk]['secondType'] = $secondType;
        }
      }
    }
    if (!empty($licText)) {
      foreach ($licText as $rulePk => $text) {
        $rules[$rulePk]['comment'] = $text;
      }
    }
    if (!empty($licResult)) {
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

    $update["inserted"] = $this->insertRules($insertFirstName,
      $insertSecondName, $insertFirstType, $insertSecondType, $insertText,
      $insertResult);
    return $update;
  }

  /**
   * @brief Insert new rules from the UI
   * @param array $firstNameArray
   * @param array $secondNameArray
   * @param array $firstTypeArray
   * @param array $secondTypeArray
   * @param array $commentArray
   * @param array $resultArray
   * @return array Containing the status whether rule is inserted or not
   */
  private function insertRules($firstNameArray, $secondNameArray,
                               $firstTypeArray, $secondTypeArray, $commentArray,
                               $resultArray)
  {
    $returnVal = [];
    if ((!empty($firstNameArray) && !empty($secondNameArray)
        && !empty($firstTypeArray) && !empty($secondTypeArray)
        && !empty($commentArray))) {
      for ($i = 0; $i < count($commentArray); $i++) {
        if ($firstNameArray[$i] == "0") {
          $firstNameArray[$i] = null;
        }
        if ($secondNameArray[$i] == "0") {
          $secondNameArray[$i] = null;
        }
        if ($firstTypeArray[$i] == "---") {
          $firstTypeArray[$i] = null;
        }
        if ($secondTypeArray[$i] == "---") {
          $secondTypeArray[$i] = null;
        }
        $returnVal[] = $this->compatibilityDao->insertRule($firstNameArray[$i],
            $secondNameArray[$i], $firstTypeArray[$i], $secondTypeArray[$i],
            $commentArray[$i], $resultArray[$i]);
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
   * @brief Delete a rule from the UI
   * @param Request $request
   * @return array If 1 then rule is deleted otherwise not
   */
  private function deleteRules(Request $request)
  {
    $returnVal = [];
    $rulePk = $request->get("rule");
    $val = $this->compatibilityDao->deleteRule($rulePk);
    $returnVal['status'] = $val ? 1 : -1;
    return $returnVal;
  }
}

register_plugin(new AdminLicenseCompatibilityRules());
