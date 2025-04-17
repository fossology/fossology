<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014-2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Application\ObligationCsvImport;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Exceptions\HttpClientException;
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
class AdminObligationFromCSV extends DefaultPlugin
{
  const NAME = "admin_obligation_from_csv";
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
      self::TITLE => "Admin Obligation Import",
      self::MENU_LIST => "Admin::Obligation Admin::Obligation Import",
      self::REQUIRES_LOGIN => true,
      self::PERMISSION => Auth::PERM_ADMIN
    ));
    /** @var ObligationCsvImport $obligationsCsvImport */
    $this->obligationsCsvImport = $GLOBALS['container']->get('app.obligation_csv_import');
    $this->sysconfig = $SysConf['SYSCONFIG'];
    $this->configuration = [
      'url' => trim($this->sysconfig['LicenseDBURL']),
      'uri' => trim($this->sysconfig['LicenseDBBaseURL']),
      'content' => trim($this->sysconfig['LicenseDBContentObligations']),
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
      $getHealth = $this->configuration['url'] . $this->configuration['uri'] . "/health";
      $vars['licenseDBHealth'] = HttpUtils::checkLicenseDBHealth($getHealth, $this->guzzleClient);
    }
    if ($request->isMethod('POST')) {
      if ($request->get('importFrom') === 'licensedb') {
        $startTime = microtime(true);
        $vars['message'] = $this->handleLicenseDbObligationImport();
        $fetchLicenseTime = microtime(true) - $startTime;
        $this->fileLogger->debug("Fetching Obligations and Check time took: " . sprintf("%0.3fms", 1000 * $fetchLicenseTime));
        $this->fileLogger->debug("****************** Message From LicenseDB import [" . date('Y-m-d H:i:s') . "] ******************");
        $this->fileLogger->debug($vars["message"]);
        $this->fileLogger->debug("****************** End Message From LicenseDB import ******************");
      } else {
        $uploadFile = $request->files->get(self::FILE_INPUT_NAME);
        $delimiter = $request->get('delimiter') ?: ',';
        $enclosure = $request->get('enclosure') ?: '"';
        $vars['message'] = $this->handleFileUpload($uploadFile, $delimiter, $enclosure);
      }
    }

    $vars[self::KEY_UPLOAD_MAX_FILESIZE] = ini_get(self::KEY_UPLOAD_MAX_FILESIZE);
    $vars['baseUrl'] = $request->getBaseUrl();
    $vars['license_csv_import'] = false;

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
   * Handles the import of obligation data from a database by sending a GET request to a specified URL,
   * decoding the retrieved JSON response, and passing the data to the CSV import logic.
   * Provides appropriate error handling for HTTP request failures and JSON decoding errors.
   *
   * @return string A message indicating the result of the operation, including error messages if applicable.
   */
  public function handleLicenseDbObligationImport()
  {
    $msg = '<br>';
    $data = null;
    $finalURL = $this->configuration['url'] . $this->configuration['uri'] . $this->configuration['content'];
    try {
      $startTimeReq = microtime(true);
      $response = $this->guzzleClient->get($finalURL);
      $fetchLicenseTimeReq = microtime(true) - $startTimeReq;
      $this->fileLogger->debug("LicenseDB req:' took " . sprintf("%0.3fms", 1000 * $fetchLicenseTimeReq));

      $data = HttpUtils::processHttpResponse($response);
      return $this->obligationsCsvImport->importJsonData($data, $msg);
    } catch (HttpClientException $e) {
      return $msg . $e->getMessage();
    } catch (RequestException|GuzzleException $e) {
      return $msg . _('Something Went Wrong, check if host is accessible') . ': ' . $e->getMessage();
    }
  }

  /**
   * @param UploadedFile $uploadedFile
   * @return null|string|array
   */
  public function handleFileUpload($uploadedFile, $delimiter = ',', $enclosure = '"', $fromRest = false)
  {
    $errMsg = '';
    if (!($uploadedFile instanceof UploadedFile)) {
      $errMsg = _("No file selected");
    } elseif ($uploadedFile->getSize() == 0 && $uploadedFile->getError() == 0) {
      $errMsg = _("Larger than upload_max_filesize ") . ini_get(self::KEY_UPLOAD_MAX_FILESIZE);
    } elseif ($uploadedFile->getClientOriginalExtension() != 'csv'
      && $uploadedFile->getClientOriginalExtension() != 'json') {
      $errMsg = _('Invalid extension ') .
        $uploadedFile->getClientOriginalExtension() . ' of file ' .
        $uploadedFile->getClientOriginalName();
    }
    if (!empty($errMsg)) {
      if ($fromRest) {
        return array(false, $errMsg, 400);
      }
      return $errMsg;
    }
    $this->obligationsCsvImport->setDelimiter($delimiter);
    $this->obligationsCsvImport->setEnclosure($enclosure);

    return array(true, $this->obligationsCsvImport->handleFile($uploadedFile->getRealPath(), $uploadedFile->getClientOriginalExtension()), 200);
  }
}

register_plugin(new AdminObligationFromCSV());
