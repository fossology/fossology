<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Application\CustomTextImport;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \brief Upload a file from the users computer using the UI.
 */
class AdminCustomTextImport extends DefaultPlugin
{
  const NAME = "admin_custom_text_import";
  const KEY_UPLOAD_MAX_FILESIZE = 'upload_max_filesize';
  const FILE_INPUT_NAME = 'file_input';

  /** @var CustomTextImport $customTextImport */
  protected $customTextImport;

  function __construct()
  {
    parent::__construct(self::NAME, array(
      self::TITLE => "Import Custom Text (CSV/JSON)",
      self::MENU_LIST => "Admin::Text Management::Import::CSV/JSON Import",
      self::REQUIRES_LOGIN => true,
      self::PERMISSION => Auth::PERM_ADMIN
    ));
    /** @var CustomTextImport $customTextImport */
    $this->customTextImport = $GLOBALS['container']->get('app.custom_text_import');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $vars = array();

    if ($request->isMethod('POST')) {
      $uploadFile = $request->files->get(self::FILE_INPUT_NAME);
      $delimiter = $request->get('delimiter') ?: ',';
      $enclosure = $request->get('enclosure') ?: '"';
      $vars['message'] = $this->handleFileUpload($uploadFile, $delimiter, $enclosure)[1];
    }

    $vars[self::KEY_UPLOAD_MAX_FILESIZE] = ini_get(self::KEY_UPLOAD_MAX_FILESIZE);
    $vars['baseUrl'] = $request->getBaseUrl();
    $vars['custom_text_import'] = true;

    return $this->render("admin_custom_text_import.html.twig", $this->mergeWithDefault($vars));
  }

  /**
   * @param UploadedFile $uploadedFile
   * @return array
   */
  public function handleFileUpload($uploadedFile, $delimiter = ',', $enclosure = '"')
  {
    $errMsg = '';
    if (!($uploadedFile instanceof UploadedFile)) {
      $errMsg = _("No file selected");
    } elseif ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
      $errMsg = $uploadedFile->getErrorMessage();
    } elseif ($uploadedFile->getSize() == 0 && $uploadedFile->getError() == 0) {
      $errMsg = _("Larger than upload_max_filesize ") .
        ini_get(self::KEY_UPLOAD_MAX_FILESIZE);
    } elseif ($uploadedFile->getClientOriginalExtension() != 'csv'
      && $uploadedFile->getClientOriginalExtension() != 'json') {
      $errMsg = _('Invalid file extension ') .
        $uploadedFile->getClientOriginalExtension() . ' of file ' .
        $uploadedFile->getClientOriginalName();
    }
    if (!empty($errMsg)) {
      return array(false, $errMsg, 400);
    }
    $this->customTextImport->setDelimiter($delimiter);
    $this->customTextImport->setEnclosure($enclosure);

    return array(true, $this->customTextImport->handleFile($uploadedFile->getRealPath(), $uploadedFile->getClientOriginalExtension()), 200);
  }
}

register_plugin(new AdminCustomTextImport());
