<?php
/***********************************************************
 * Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.
 * Copyright (C) 2014 Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

namespace Fossology\UI\Page;

use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Fossology\Lib\Application\LicenseCsvImport;

/**
 * \brief Upload a file from the users computer using the UI.
 */
class AdminLicenseFromCSV extends DefaultPlugin
{
  const NAME = "admin_license_from_csv";
  var $headrow = false;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "Admin License CSV Import",
        self::MENU_LIST => "Admin::License Admin::CSV Import",
        self::REQUIRES_LOGIN => true,
        self::DEPENDENCIES => array(\ui_menu::NAME),
        self::PERMISSION => self::PERM_ADMIN
    ));
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle (Request $request)
  {
    $vars = array();

    if ($request->isMethod('POST'))
    {
      $uploadFile = $request->files->get('file_input');
      $vars['message'] = $this->handleFileUpload($uploadFile);
    }

    $vars['upload_max_filesize'] = ini_get('upload_max_filesize');
    $vars['baseUrl'] = $request->getBaseUrl();

    return $this->render("admin_license_from_csv.html.twig", $this->mergeWithDefault($vars));
  }

  /**
   * @param UploadedFile $uploadedFile
   * @return null|string
   */
  protected function handleFileUpload(UploadedFile $uploadedFile)
  {
    if ($uploadedFile == null)
    {
      return _("Error: no file selected");
    }
    if ($uploadedFile->getSize() == 0 && $uploadedFile->getError() == 0)
    {
      return _("Larger than upload_max_filesize ") . ini_get('upload_max_filesize');
    }
    if($uploadedFile->getClientOriginalExtension()!='csv')
    {
      return _('Invalid extension ').$uploadedFile->getClientOriginalExtension().' of file '.$uploadedFile->getClientOriginalName();
    }
    /** @var LicenseCsvImport */
    $licenseCsvImport = $this->getObject('app.license_csv_import');
    return $licenseCsvImport->handleFile($uploadedFile->getRealPath());
  }

}

register_plugin(new AdminLicenseFromCSV());