<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Application\LicenseCsvImport;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \brief Upload a file from the users computer using the UI.
 */
class AdminLicenseFromCSV extends DefaultPlugin
{
  const NAME = "admin_license_from_csv";
  const KEY_UPLOAD_MAX_FILESIZE = 'upload_max_filesize';
  const FILE_INPUT_NAME = 'file_input';

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "Admin License CSV Import",
        self::MENU_LIST => "Admin::License Admin::CSV Import",
        self::REQUIRES_LOGIN => true,
        self::PERMISSION => Auth::PERM_ADMIN
    ));
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
      $delimiter = $request->get('delimiter') ?: ',';
      $enclosure = $request->get('enclosure') ?: '"';
      $vars['message'] = $this->handleFileUpload($uploadFile, $delimiter,
        $enclosure)[1];
    }

    $vars[self::KEY_UPLOAD_MAX_FILESIZE] = ini_get(self::KEY_UPLOAD_MAX_FILESIZE);
    $vars['baseUrl'] = $request->getBaseUrl();
    $vars['license_csv_import'] = true;

    return $this->render("admin_license_from_csv.html.twig", $this->mergeWithDefault($vars));
  }


  /**
   * @param UploadedFile $uploadedFile
   * @return array
   */
  public function handleFileUpload($uploadedFile,$delimiter=',',$enclosure='"')
  {
    $errMsg = '';
    if (! ($uploadedFile instanceof UploadedFile)) {
      $errMsg = _("No file selected");
    } elseif ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
      $errMsg = $uploadedFile->getErrorMessage();
    } elseif ($uploadedFile->getSize() == 0 && $uploadedFile->getError() == 0) {
      $errMsg = _("Larger than upload_max_filesize ") .
        ini_get(self::KEY_UPLOAD_MAX_FILESIZE);
    } elseif ($uploadedFile->getClientOriginalExtension() != 'csv') {
      $errMsg = _('Invalid extension ') .
        $uploadedFile->getClientOriginalExtension() . ' of file ' .
        $uploadedFile->getClientOriginalName();
    }
    if (! empty($errMsg)) {
      return array(false, $errMsg,400);
    }
    /** @var LicenseCsvImport $licenseCsvImport */
    $licenseCsvImport = $this->getObject('app.license_csv_import');
    $licenseCsvImport->setDelimiter($delimiter);
    $licenseCsvImport->setEnclosure($enclosure);

    return array(true,$licenseCsvImport->handleFile($uploadedFile->getRealPath()),200);
  }

  /**
   * @return string
   */
  public function getFileInputName()
  {
    return $this::FILE_INPUT_NAME;
  }
}

register_plugin(new AdminLicenseFromCSV());
