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
  const KEY_UPLOAD_MAX_FILESIZE = 'upload_max_filesize';

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "Admin License CSV Import",
        self::MENU_LIST => "Admin::License Admin::CSV Import",
        self::REQUIRES_LOGIN => true,
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
      $delimiter = $request->get('delimiter')?:',';
      $enclosure = $request->get('enclosure')?:'"';
      $vars['message'] = $this->handleFileUpload($uploadFile,$delimiter,$enclosure);
    }

    $vars[self::KEY_UPLOAD_MAX_FILESIZE] = ini_get(self::KEY_UPLOAD_MAX_FILESIZE);
    $vars['baseUrl'] = $request->getBaseUrl();

    return $this->render("admin_license_from_csv.html.twig", $this->mergeWithDefault($vars));
  }

  /**
   * @param UploadedFile $uploadedFile
   * @return null|string
   */
  protected function handleFileUpload($uploadedFile,$delimiter=',',$enclosure='"')
  {
    $errMsg = '';
    if ( !($uploadedFile instanceof UploadedFile) )
    {
      $errMsg = _("No file selected");
    }
    elseif ($uploadedFile->getSize() == 0 && $uploadedFile->getError() == 0)
    {
      $errMsg = _("Larger than upload_max_filesize ") . ini_get(self::KEY_UPLOAD_MAX_FILESIZE);
    }
    elseif($uploadedFile->getClientOriginalExtension()!='csv')
    {
      $errMsg = _('Invalid extension ').$uploadedFile->getClientOriginalExtension().' of file '.$uploadedFile->getClientOriginalName();
    }
    if (!empty($errMsg))
    {
      return $errMsg;
    }
    /** @var LicenseCsvImport */
    $licenseCsvImport = $this->getObject('app.license_csv_import');
    $licenseCsvImport->setDelimiter($delimiter);
    $licenseCsvImport->setEnclosure($enclosure);
    return $licenseCsvImport->handleFile($uploadedFile->getRealPath());
  }

}

register_plugin(new AdminLicenseFromCSV());