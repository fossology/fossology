<?php
/*
 SPDX-FileCopyrightText: © 2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * Allows users to manage set of standard license comments.
 */
namespace Fossology\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\LicenseStdCommentDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @class AdminLicenseStdComments
 * Page to allow users to manage the standard license comments.
 */
class AdminLicenseStdComments extends DefaultPlugin
{

  /**
   * @var string NAME
   *      Mod name
   */
  const NAME = "admin_license_std_comments";

  /**
   * @var string UPDATE_PARAM_NAME
   *      Name of the parameter to denote form submit
   */
  const UPDATE_PARAM_NAME = "formUpdated";

  /**
   * @var string COMMENT_PARAM_NAME
   *      Parameter storing the comment names
   */
  const COMMENT_PARAM_NAME = "licenseStdComment";

  /**
   * @var string COMMENT_ID_PARAM_NAME
   *      Parameter storing the comment IDs
   */
  const COMMENT_ID_PARAM_NAME = "licenseCommentLscPK";

  /**
   * @var string COMMENT_NAME_PARAM_NAME
   *      Parameter storing the comments
   */
  const COMMENT_NAME_PARAM_NAME = "licenseCommentName";

  /**
   * @var string INSERT_NAME_PARAM_NAME
   *      Parameter storing the new names
   */
  const INSERT_NAME_PARAM_NAME = "insertStdLicNames";

  /**
   * @var string INSERT_NAME_PARAM_NAME
   *      Parameter storing the new comments
   */
  const INSERT_COMMENT_PARAM_NAME = "insertStdLicComments";

  /**
   * @var string ENABLE_PARAM_NAME
   *      Parameter storing the comment status
   */
  const ENABLE_PARAM_NAME = "stdLicCommentEnabled";

  /**
   * @var LicenseStdCommentDao $licenseCommentDao
   *      License comment DAO in use
   */
  private $licenseCommentDao;

  function __construct()
  {
    parent::__construct(self::NAME,
      array(
        self::TITLE => "Admin Standard License Comments",
        self::MENU_LIST => "Admin::License Admin::Standard Comments",
        self::REQUIRES_LOGIN => true,
        self::PERMISSION => Auth::PERM_ADMIN
      ));
    $this->licenseCommentDao = $this->getObject('dao.license.stdc');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    if ($request->get(self::UPDATE_PARAM_NAME, 0) == 1) {
      return new JsonResponse($this->updateComments($request),
        JsonResponse::HTTP_OK);
    }

    $vars = [];
    $vars["updateParam"] = self::UPDATE_PARAM_NAME;
    $vars["commentParam"] = self::COMMENT_PARAM_NAME;
    $vars["commentIdParam"] = self::COMMENT_ID_PARAM_NAME;
    $vars["commentNameParam"] = self::COMMENT_NAME_PARAM_NAME;
    $vars["enableParam"] = self::ENABLE_PARAM_NAME;

    $vars['commentArray'] = $this->licenseCommentDao->getAllComments();
    return $this->render('admin_license_std_comments.html.twig',
      $this->mergeWithDefault($vars));
  }

  /**
   * Get the parameters from the request and update the comments.
   *
   * @param Request $request The request
   * @return array Number of comments updated/inserted as value of
   *         corresponding keys, or error (if any).
   */
  private function updateComments(Request $request)
  {
    $comments = [];
    $update = [
      "updated" => -1,
      "inserted" => []
    ];
    $commentStrings = $request->get(self::COMMENT_PARAM_NAME);
    $commentNames = $request->get(self::COMMENT_NAME_PARAM_NAME);
    $insertNames = $request->get(self::INSERT_NAME_PARAM_NAME);
    $insertComments = $request->get(self::INSERT_COMMENT_PARAM_NAME);
    if ($commentStrings !== null && !empty($commentStrings)) {
      foreach ($commentStrings as $commentPk => $comment) {
        $comments[$commentPk]['comment'] = $comment;
      }
    }
    if ($commentNames !== null && !empty($commentNames)) {
      foreach ($commentNames as $commentPk => $name) {
        $comments[$commentPk]['name'] = $name;
      }
    }
    if (! empty($comments)) {
      try {
        $update['updated'] = $this->licenseCommentDao->updateCommentFromArray(
          $comments);
      } catch (\UnexpectedValueException $e) {
        $update['updated'] = $e->getMessage();
      }
    }
    $update["inserted"] = $this->insertComments($insertNames, $insertComments);
    return $update;
  }

  /**
   * Insert new comments
   *
   * @param array $namesArray    Array containing new names
   * @param array $commentsArray Array containing new comments
   * @return number[]
   */
  private function insertComments($namesArray, $commentsArray)
  {
    $returnVal = [];
    if (($namesArray !== null && $commentsArray !== null) &&
      (! empty($namesArray) && !empty($commentsArray))) {
      for ($i = 0; $i < count($namesArray); $i++) {
        $returnVal[] = $this->licenseCommentDao->insertComment($namesArray[$i],
          $commentsArray[$i]);
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

register_plugin(new AdminLicenseStdComments());
