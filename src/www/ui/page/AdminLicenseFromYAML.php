<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Application\LicenseCompatibilityRulesYamlImport;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\UI\Api\Models\ApiVersion;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \brief Upload a file from the users computer using the UI.
 */
class AdminLicenseFromYAML extends DefaultPlugin
{
  const NAME = "admin_license_from_yaml";
  const KEY_UPLOAD_MAX_FILESIZE = 'upload_max_filesize';
  const FILE_INPUT_NAME = 'file_input';
  const FILE_INPUT_NAME_V2 = 'fileInput';
  function __construct()
  {
    parent::__construct(self::NAME, array(
          self::TITLE => "Admin License Rules Import",
          self::MENU_LIST => "Admin::License Admin::Rules Import",
          self::REQUIRES_LOGIN => true,
          self::PERMISSION => Auth::PERM_ADMIN
        ));
  }
  /**
   * @return string
   */
  public function getFileInputName($apiVersion = ApiVersion::V1)
  {
    if ($apiVersion == ApiVersion::V2) {
      return $this::FILE_INPUT_NAME_V2;
    } else {
      return $this::FILE_INPUT_NAME;
    }
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle (Request $request)
  {
    $vars = array();
    if ($request->isMethod('POST')) {
      $uploadFile = $request->files->get(self::FILE_INPUT_NAME);
      $vars['message'] = $this->handleFileUpload($uploadFile);
    }
    $vars[self::KEY_UPLOAD_MAX_FILESIZE] = ini_get(self::KEY_UPLOAD_MAX_FILESIZE);
    $vars['baseUrl'] = $request->getBaseUrl();
    return $this->render("admin_license_from_yaml.html.twig", $this->mergeWithDefault($vars));
  }

  /**
   * @param UploadedFile $uploadedFile
   * @param boolean $fromRest Return an array with the status code if called
   *        from the REST API
   * @return array|null|string
   */
  public function handleFileUpload($uploadedFile, $fromRest = false)
  {
    $errMsg = '';
    if (! ($uploadedFile instanceof UploadedFile)) {
      $errMsg = _("No file selected");
    } elseif ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
      $errMsg = $uploadedFile->getErrorMessage();
    } elseif ($uploadedFile->getSize() == 0 && $uploadedFile->getError() == 0) {
      $errMsg = _("Larger than upload_max_filesize ") .
      ini_get(self::KEY_UPLOAD_MAX_FILESIZE);
    } elseif ($uploadedFile->getClientOriginalExtension() != 'yaml' &&
        $uploadedFile->getClientOriginalExtension() != 'yml') {
      $errMsg = _('Invalid extension ') .
        $uploadedFile->getClientOriginalExtension() . ' of file ' .
        $uploadedFile->getClientOriginalName();
    }
    if (! empty($errMsg)) {
      if ($fromRest) {
        return array(false, $errMsg, 400);
      }
      return $errMsg;
    }
    /** @var LicenseCompatibilityRulesYamlImport $licenseYamlImport */
    $licenseYamlImport = $this->getObject('app.license_yaml_import');
    $msg = $licenseYamlImport->handleFile($uploadedFile->getRealPath());
    if ($fromRest) {
      return array(true, $msg, 200);
    }
    return $msg;
  }
}

register_plugin(new AdminLicenseFromYAML());
