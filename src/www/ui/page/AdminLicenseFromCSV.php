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
use Fossology\Lib\Util\HttpUtils;
use Fossology\UI\Api\Models\ApiVersion;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
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
  const FILE_INPUT_NAME_V2 = 'fileInput';

  /**
   * @var Client $guzzleClient
   */
  private $guzzleClient;
  /**
   * @var array
   */
  private $sysconfig;

  function __construct()
  {
    global $SysConf;
    parent::__construct(self::NAME, array(
      self::TITLE => "Admin License Import",
      self::MENU_LIST => "Admin::License Admin::License Import",
      self::REQUIRES_LOGIN => true,
      self::PERMISSION => Auth::PERM_ADMIN
    ));
    /** @var LicenseCsvImport $licenseCsvImport */
    $this->licenseCsvImport = $GLOBALS['container']->get('app.license_csv_import');
    $this->sysconfig = $SysConf['SYSCONFIG'];
    $this->configuration = [
      'url' => trim($this->sysconfig['LicenseDBURL']),
      'uri' => trim($this->sysconfig['LicenseDBBaseURL']),
      'content' => trim($this->sysconfig['LicenseDBContent']),
      'maxtime' => intval($this->sysconfig['LicenseDBSleep']),
      'token' => trim($this->sysconfig['LicenseDBToken'])
    ];

    $this->guzzleClient = HttpUtils::getGuzzleClient($SysConf, $this->configuration['uri'], $this->configuration['token']);
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
  protected function handle(Request $request)
  {
    $vars = array();
    if (!$request->isMethod('POST')) {
      $vars['licenseDBHealth'] = $this->checkLicenseDBHealth();
    }
    if ($request->isMethod('POST')) {
      if ($request->get('importFrom') === 'licensedb') {
        $startTime = microtime(true);
        $vars['message'] = $this->handleLicenseDbImport();
        $fetchLicenseTime = microtime(true) - $startTime;
        $this->fileLogger->debug("Fetching License and Check if exist took: " . sprintf("%0.3fms", 1000 * $fetchLicenseTime));
        $this->fileLogger->debug("****************** Message From LicenseDB import [" . date('Y-m-d H:i:s') . "] ******************");
        $this->fileLogger->debug($vars["message"]);
        $this->fileLogger->debug("****************** End Message From LicenseDB import ******************");
      } else {
        $uploadFile = $request->files->get(self::FILE_INPUT_NAME);
        $delimiter = $request->get('delimiter') ?: ',';
        $enclosure = $request->get('enclosure') ?: '"';
        $vars['message'] = $this->handleFileUpload($uploadFile, $delimiter,
          $enclosure)[1];
      }
    }
    $vars[self::KEY_UPLOAD_MAX_FILESIZE] = ini_get(self::KEY_UPLOAD_MAX_FILESIZE);
    $vars['baseUrl'] = $request->getBaseUrl();
    $vars['license_csv_import'] = true;

    if (!empty(trim($this->configuration['url']))) {
      $vars['baseURL'] = !empty($this->configuration['uri']);
      $vars['authToken'] = !empty($this->configuration['token']);
      $vars['exportEndpoint'] = !empty($this->configuration['content']);
      return $this->render("admin_license_from_licensedb.html.twig", $this->mergeWithDefault($vars));
    } else {
      return $this->render("admin_license_from_csv.html.twig", $this->mergeWithDefault($vars));
    }
  }

  /**
   * Handles the import of license data from a database by sending a GET request to a specified URL,
   * decoding the retrieved JSON response, and passing the data to the CSV import logic.
   * Provides appropriate error handling for HTTP request failures and JSON decoding errors.
   *
   * @return string A message indicating the result of the operation, including error messages if applicable.
   */
  public function handleLicenseDbImport()
  {
    $msg = '<br>';
    $data = null;
    $finalURL = $this->configuration['url'] . $this->configuration['uri'] . $this->configuration['content'];
    try {
      $startTimeReq = microtime(true);
      $response = $this->guzzleClient->get($finalURL);
      $fetchLicenseTimeReq = microtime(true) - $startTimeReq;
      $this->fileLogger->debug("LicenseDB req:' took " . sprintf("%0.3fms", 1000 * $fetchLicenseTimeReq));
      if ($response->getStatusCode() == 200) {
        $data = json_decode($response->getBody()->getContents());
      }

      if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        return $msg . "Error decoding JSON: " . json_last_error_msg() . "\n";
      } else {
        return $this->licenseCsvImport->importJsonData($data, $msg);
      }
    } catch (RequestException|GuzzleException $e) {
      return $msg . _('Something Went Wrong, check if host is accessible') . ': ' . $e->getMessage();
    }
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
    $this->licenseCsvImport->setDelimiter($delimiter);
    $this->licenseCsvImport->setEnclosure($enclosure);

    return array(true, $this->licenseCsvImport->handleFile($uploadedFile->getRealPath(), $uploadedFile->getClientOriginalExtension()), 200);
  }


  /**
   * Checks the health status of the license database by sending a GET request to a specified health endpoint.
   * Returns a boolean result indicating whether the database is reachable and healthy.
   * Implements error handling for HTTP request failures.
   *
   * @return bool True if the database health check succeeds with an HTTP 200 response, false otherwise.
   */
  public function checkLicenseDBHealth()
  {

    $getHealth = $this->configuration['url'] . $this->configuration['uri'] . "/health";
    try {
      $response = $this->guzzleClient->get($getHealth);
      if ($response->getStatusCode() === 200) {
        return true;
      }
    } catch (RequestException|GuzzleException $e) {
      return false;
    }

    return false;
  }
}

register_plugin(new AdminLicenseFromCSV());
