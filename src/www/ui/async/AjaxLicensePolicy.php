<?php
/*
 SPDX-FileCopyrightText: © 2026 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * Handles ajax calls for user-defined license policies.
 */
namespace Fossology\UI\Ajax;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\PolicyDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @class AjaxLicensePolicy
 * Handles ajax calls for License Policies.
 */
class AjaxLicensePolicy extends DefaultPlugin
{
  const NAME = "ajax_license_policy";

  /** @var PolicyDao */
  private $policyDao;

  function __construct()
  {
    parent::__construct(self::NAME,
      array(
        self::REQUIRES_LOGIN => true,
        self::PERMISSION => Auth::PERM_READ
      ));
    global $container;
    $this->policyDao = new PolicyDao($container->get('db.manager'), $container->get('logger'));
  }

  private function verifyCsrfToken(Request $request) {
    if ($request->getMethod() !== 'POST') {
      return true;
    }
    
    if (empty($_SESSION['csrfToken'])) {
        $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
    }
    
    $clientToken = $request->get('csrfToken') ?? $request->headers->get('X-CSRF-Token');
    if (!$clientToken || !hash_equals($_SESSION['csrfToken'], $clientToken)) {
       return false;
    }
    return true;
  }

  protected function handle(Request $request)
  {
    $action = $request->get("action", "get_all");

    // Enforce CSRF token for mutations
    if (!$this->verifyCsrfToken($request)) {
       return new JsonResponse(["status" => false, "error" => "CSRF Token Validation Failed"], JsonResponse::HTTP_FORBIDDEN);
    }

    if ($action === 'get_all') {
      // Return all policies
      $policies = $this->policyDao->getAllPolicies();
      return new JsonResponse(["status" => true, "policies" => $policies]);
    }

    if ($action === 'get_filter') {
      $filters = $this->policyDao->getPolicyFilter(Auth::getUserId());
      return new JsonResponse(["status" => true, "filters" => $filters]);
    }
    
    if ($action === 'set_filter') {
      $filters = $request->get('filters');
      if (!is_array($filters)) $filters = [];
      $this->policyDao->setPolicyFilter(Auth::getUserId(), $filters);
      return new JsonResponse(["status" => true]);
    }
    
    if ($action === 'get_token') {
       if (empty($_SESSION['csrfToken'])) {
           $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
       }
       return new JsonResponse(["status" => true, "csrfToken" => $_SESSION['csrfToken']]);
    }

    // Require CADMIN for mutations
    if (!Auth::isClearingAdmin()) {
      return new JsonResponse(["status" => false, "error" => "Permission denied"], JsonResponse::HTTP_FORBIDDEN);
    }

    $licenseId = $request->get("licenseId");
    if (!$licenseId) {
      return new JsonResponse(["status" => false, "error" => "Missing licenseId"], JsonResponse::HTTP_BAD_REQUEST);
    }

    if ($action === 'set_policy') {
      $rank = $request->get("policy_rank");
      if ($rank === null || !in_array((int)$rank, [0, 1, 2], true)) {
        return new JsonResponse(["status" => false, "error" => "Invalid policy rank"], JsonResponse::HTTP_BAD_REQUEST);
      }
      
      $this->policyDao->setLicensePolicy(
        (int)$licenseId, 
        (int)$rank, 
        Auth::getUserId(), 
        'UI_AJAX', 
        $request->getClientIp()
      );
      return new JsonResponse(["status" => true]);
      
    } else if ($action === 'delete_policy') {
      $this->policyDao->deleteLicensePolicy(
        (int)$licenseId, 
        Auth::getUserId(), 
        'UI_AJAX', 
        $request->getClientIp()
      );
      return new JsonResponse(["status" => true]);
    }

    return new JsonResponse(["status" => false, "error" => "Unknown action"], JsonResponse::HTTP_BAD_REQUEST);
  }
}

register_plugin(new AjaxLicensePolicy());
