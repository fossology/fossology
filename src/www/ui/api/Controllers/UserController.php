<?php
/*
 SPDX-FileCopyrightText: © 2018, 2021 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Controller for user queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Exceptions\DuplicateTokenKeyException;
use Fossology\Lib\Exceptions\DuplicateTokenNameException;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpConflictException;
use Fossology\UI\Api\Exceptions\HttpErrorException;
use Fossology\UI\Api\Exceptions\HttpInternalServerErrorException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Exceptions\HttpTooManyRequestException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Helper\UserHelper;
use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\TokenRequest;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\StreamFactory;

/**
 * @class UserController
 * @brief Controller for User model
 */
class UserController extends RestController
{
  /**
   * Get list of Users
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpNotFoundException
   */
  public function getUsers($request, $response, $args)
  {
    $this->throwNotAdminException();
    $apiVersion = ApiVersion::getVersion($request);
    $id = null;
    if (isset($args['pathParam'])) {
      if ($apiVersion == ApiVersion::V2) {
        $user = $this->restHelper->getUserDao()->getUserByName($args['pathParam']);
        if ($user === null) {
          throw new HttpNotFoundException("UserId doesn't exist");
        }
        $id = intval($user['user_pk']);
      } else {
        $id = intval($args['pathParam']);
      }
      if (! $this->dbHelper->doesIdExist("users", "user_pk", $id)) {
        throw new HttpNotFoundException("UserId doesn't exist");
      }
    }
    $users = $this->dbHelper->getUsers($id);

    $allUsers = array();
    foreach ($users as $user) {
      $allUsers[] = $user->getArray($apiVersion);
    }
    if ($id !== null) {
      $allUsers = $allUsers[0];
    }
    return $response->withJson($allUsers, 200);
  }

  /**
   * Create a user
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpInternalServerErrorException
   */
  public function addUser($request, $response, $args)
  {
    $this->throwNotAdminException();
    $apiVersion = ApiVersion::getVersion($request);
    $userDetails = $this->getParsedBody($request);
    if ($userDetails === null || !is_array($userDetails)) {
      throw new HttpBadRequestException("Request body is empty or malformed.");
    }
    if (empty($userDetails['name'])) {
      throw new HttpBadRequestException("Username must be specified.");
    }
    $userHelper = new UserHelper();
    // creating symphony request
    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $symfonyRequest->request->set('username', $userDetails['name']);
    $symfonyRequest->request->set('pass1', $userDetails[$apiVersion == ApiVersion::V2 ? 'userPass' : 'user_pass']);
    $symfonyRequest->request->set('pass2', $userDetails[$apiVersion == ApiVersion::V2 ? 'userPass' : 'user_pass']);
    $symfonyRequest->request->set('description', $userDetails['description']);
    $symfonyRequest->request->set('permission', $userHelper->getEquivalentValueForPermission($userDetails['accessLevel']));
    $symfonyRequest->request->set('folder', $userDetails['rootFolderId']);
    $symfonyRequest->request->set('enote', $userDetails['emailNotification'] ? 'y' : 'n');
    $symfonyRequest->request->set('email', $userDetails['email']);
    $symfonyRequest->request->set('public', $userDetails['defaultVisibility']);
    $symfonyRequest->request->set('default_bucketpool_fk', $userDetails['defaultBucketpool'] ?? 2);

    $agents = array();
    if (isset($userDetails['agents'])) {
      if (is_string($userDetails['agents'])) { // If 'x-www-form-urlencoded', inner elements are not decoded
        $userDetails['agents'] = json_decode($userDetails['agents'], true);
      }
      $agents['Check_agent_mimetype'] = isset($userDetails['agents']['mime']) && $userDetails['agents']['mime'] ? 1 : 0;
      $agents['Check_agent_monk'] = isset($userDetails['agents']['monk']) && $userDetails['agents']['monk'] ? 1 : 0;
      $agents['Check_agent_ojo'] = isset($userDetails['agents']['ojo']) && $userDetails['agents']['ojo'] ? 1 : 0;
      $agents['Check_agent_bucket'] = isset($userDetails['agents']['bucket']) && $userDetails['agents']['bucket'] ? 1 : 0 ;
      $agents['Check_agent_copyright'] = isset($userDetails['agents'][$apiVersion == ApiVersion::V2 ? 'copyrightEmailAuthor' : 'copyright_email_author']) && $userDetails['agents'][$apiVersion == ApiVersion::V2 ? 'copyrightEmailAuthor' : 'copyright_email_author'] ? 1 : 0;
      $agents['Check_agent_ecc'] = isset($userDetails['agents']['ecc']) && $userDetails['agents']['ecc'] ? 1 : 0;
      $agents['Check_agent_keyword'] = isset($userDetails['agents']['keyword']) && $userDetails['agents']['keyword'] ? 1 : 0;
      $agents['Check_agent_nomos'] = isset($userDetails['agents']['nomos']) && $userDetails['agents']['nomos'] ? 1 : 0;
      $agents['Check_agent_pkgagent'] = isset($userDetails['agents']['package']) && $userDetails['agents']['package'] ? 1 : 0;
      $agents['Check_agent_reso'] = isset($userDetails['agents']['reso']) && $userDetails['agents']['reso'] ? 1 : 0;
      $agents['Check_agent_shagent'] = isset($userDetails['agents']['heritage']) && $userDetails['agents']['heritage'] ? 1 : 0 ;
    }

    $symfonyRequest->request->set('user_agent_list', userAgents($agents));

    // initialising the user_add object
    global $container;
    $restHelper = $container->get('helper.restHelper');
    $userAddObj = $restHelper->getPlugin('user_add');

    // calling the add function
    $ErrMsg = $userAddObj->add($symfonyRequest);

    if ($ErrMsg != '') {
      throw new HttpInternalServerErrorException($ErrMsg);
    }

    $returnVal = new Info(201, "User created successfully", InfoType::INFO);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Build a legacy user_add compatible request from an import row and
   * create the user through the user_add plugin. Used only by the bulk
   * CSV/JSON import in handleImportUsers() -- kept separate from addUser()
   * so the single-user REST endpoint's behavior/validation is untouched by
   * the bulk-import feature.
   *
   * Unlike addUser(), a creation failure is returned as [false, message]
   * instead of thrown, so one bad row in a bulk import doesn't abort the
   * rest of the file.
   *
   * Import rows are always parsed into the V2-shaped agents DTO (see
   * AGENT_KEYS) by importRowToUserDetails(), regardless of which API
   * version's URL the request came in on, so this always uses the V2 agent
   * keys -- unlike addUser(), there is no V1 request shape to support here.
   *
   * @param array $userDetails Associative array from importRowToUserDetails()
   *   (name, userPass, description, email, accessLevel, rootFolderId,
   *   emailNotification, defaultBucketpool, agents{...}).
   * @return array{0: bool, 1: string} [success, message or error]
   */
  protected function createUserFromImportRow($userDetails)
  {
    // Not empty(): empty("0") is true in PHP, which would wrongly reject
    // the literal (if weak) password "0".
    if (!is_string($userDetails['userPass'] ?? null) || $userDetails['userPass'] === '') {
      return [false, "Password must be specified."];
    }
    $userHelper = new UserHelper();
    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $symfonyRequest->request->set('username', $userDetails['name']);
    $symfonyRequest->request->set('pass1', $userDetails['userPass']);
    $symfonyRequest->request->set('pass2', $userDetails['userPass']);
    $symfonyRequest->request->set('description', $userDetails['description'] ?? '');
    $symfonyRequest->request->set('permission', $userHelper->getEquivalentValueForPermission($userDetails['accessLevel'] ?? null));
    $symfonyRequest->request->set('folder', $userDetails['rootFolderId'] ?? '');
    $symfonyRequest->request->set('enote', !empty($userDetails['emailNotification']) ? 'y' : 'n');
    $symfonyRequest->request->set('email', $userDetails['email'] ?? '');
    $symfonyRequest->request->set('public', $userDetails['defaultVisibility'] ?? '');
    $symfonyRequest->request->set('default_bucketpool_fk', $userDetails['defaultBucketpool'] ?? 2);

    $agents = $this->buildImportAgentCheckboxes($userDetails['agents'] ?? null);
    $symfonyRequest->request->set('user_agent_list', userAgents($agents));

    global $container;
    $restHelper = $container->get('helper.restHelper');
    $userAddObj = $restHelper->getPlugin('user_add');

    $errMsg = $userAddObj->add($symfonyRequest);

    if ($errMsg != '') {
      return [false, $errMsg];
    }
    return [true, "User '" . $userDetails['name'] . "' created successfully."];
  }

  /**
   * Translate a V2 agents DTO (bucket, copyrightEmailAuthor, ecc, keyword,
   * mime, monk, nomos, ojo, reso) into the legacy Check_agent_* map
   * expected by userAgents(). Used only by createUserFromImportRow(); every
   * key in AGENT_KEYS must have a branch here -- a missing one is silently
   * treated as "unchecked" rather than erroring.
   *
   * pkgagent, softwareHeritage and ipra are deliberately not in AGENT_KEYS
   * and have no branch here: addUser()'s existing V2 handling of those
   * three has a pre-existing key-mapping bug (unrelated to this feature,
   * left untouched), so round-tripping them through import/export isn't
   * reliable; imported users simply get those three left at their default
   * (unchecked) rather than silently mis-mapped.
   *
   * @param array|string|null $agentsDto
   * @return array<string, int>
   */
  private function buildImportAgentCheckboxes($agentsDto)
  {
    if (empty($agentsDto)) {
      return array();
    }
    if (is_string($agentsDto)) { // If 'x-www-form-urlencoded', inner elements are not decoded
      $agentsDto = json_decode($agentsDto, true);
    }
    $isChecked = function ($key) use ($agentsDto) {
      return isset($agentsDto[$key]) && $agentsDto[$key] ? 1 : 0;
    };

    return array(
      'Check_agent_mimetype' => $isChecked('mime'),
      'Check_agent_monk' => $isChecked('monk'),
      'Check_agent_ojo' => $isChecked('ojo'),
      'Check_agent_bucket' => $isChecked('bucket'),
      'Check_agent_copyright' => $isChecked('copyrightEmailAuthor'),
      'Check_agent_ecc' => $isChecked('ecc'),
      'Check_agent_keyword' => $isChecked('keyword'),
      'Check_agent_nomos' => $isChecked('nomos'),
      'Check_agent_reso' => $isChecked('reso'),
    );
  }

  /**
   * Delete a given user
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpNotFoundException
   */
  public function deleteUser($request, $response, $args)
  {
    $this->throwNotAdminException();
    $apiVersion = ApiVersion::getVersion($request);
    if ($apiVersion == ApiVersion::V2) {
      $user = $this->restHelper->getUserDao()->getUserByName($args['pathParam']);
      if ($user === null) {
        throw new HttpNotFoundException("UserId doesn't exist");
      }
      $id = intval($user['user_pk']);
    } else {
      $id = intval($args['pathParam']);
    }
    if (!$this->dbHelper->doesIdExist("users","user_pk", $id)) {
      throw new HttpNotFoundException("UserId doesn't exist");
    }

    $this->dbHelper->deleteUser($id);
    $returnVal = new Info(202, "User will be deleted", InfoType::INFO);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Get information of current user
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getCurrentUser($request, $response, $args)
  {
    $apiVersion = ApiVersion::getVersion($request);
    $user = $this->dbHelper->getUsers($this->restHelper->getUserId())[0]->getArray($apiVersion);
    if ($apiVersion == ApiVersion::V2) {
      return $response->withJson($user, 200);
    }
    $userDao = $this->restHelper->getUserDao();
    $defaultGroup = $userDao->getUserAndDefaultGroupByUserName($user["name"])["group_name"];
    $user['default_group'] = $defaultGroup;
    return $response->withJson($user, 200);
  }

  /**
   * Updates the user details
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpNotFoundException
   */
  public function updateUser($request, $response, $args)
  {
    $apiVersion = ApiVersion::getVersion($request);
    if ($apiVersion == ApiVersion::V2) {
      $user = $this->restHelper->getUserDao()->getUserByName($args['pathParam']);
      if ($user === null) {
        throw new HttpNotFoundException("UserId doesn't exist");
      }
      $id = intval($user['user_pk']);
    } else {
      $id = intval($args['pathParam']);
    }
    if ($id !== intval($this->restHelper->getUserId())) {
      $this->throwNotAdminException();
    }
    if (!$this->dbHelper->doesIdExist("users","user_pk", $id)) {
      throw new HttpNotFoundException("UserId doesn't exist");
    }
    $reqBody = $this->getParsedBody($request);
    $userHelper = new UserHelper($id);
    $returnVal = $userHelper->modifyUserDetails($reqBody, $apiVersion);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Create a new REST API Token
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function createRestApiToken($request, $response, $args)
  {
    $reqBody = $this->getParsedBody($request);
    $tokenRequest = TokenRequest::fromArray($reqBody,
      ApiVersion::getVersion($request));
    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();

    // translating values for symfony request
    $symfonyRequest->request->set('pat_name', $tokenRequest->getTokenName());
    $symfonyRequest->request->set('pat_expiry', $tokenRequest->getTokenExpire());
    $symfonyRequest->request->set('pat_scope', $tokenRequest->getTokenScope());

    // initialising the user_edit plugin
    global $container;
    /** @var RestHelper $restHelper */
    $restHelper = $container->get('helper.restHelper');
    /** @var \UserEditPage $userEditObj */
    $userEditObj = $restHelper->getPlugin('user_edit');

    // creating the REST token
    try {
      $token = $userEditObj->generateNewToken($symfonyRequest);
    } catch (DuplicateTokenKeyException $e) {
      throw new HttpTooManyRequestException("Please try again later.", $e);
    } catch (DuplicateTokenNameException $e) {
      throw new HttpConflictException($e->getMessage(), $e);
    } catch (\UnexpectedValueException $e) {
      throw new HttpBadRequestException($e->getMessage(), $e);
    }

    $returnVal = new Info(201, "Token created successfully", InfoType::INFO);
    $res = $returnVal->getArray();
    $res['token'] = $token;
    return $response->withJson($res, $returnVal->getCode());
  }

  /**
   * Get all the REST API tokens (active | expired)
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpBadRequestException
   */
  public function getTokens($request, $response, $args)
  {
    $apiVersion = ApiVersion::getVersion($request);
    $tokenType = $args['type'];
    if ($tokenType != "active" && $tokenType != "expired") {
      throw new HttpBadRequestException("Invalid request!");
    }
    // initialising the user_edit plugin
    global $container;
    $restHelper = $container->get('helper.restHelper');
    $userEditObj = $restHelper->getPlugin('user_edit');

    // getting the list of tokens based on the type of token requested
    $tokens = $tokenType == "active" ? $userEditObj->getListOfActiveTokens() : $userEditObj->getListOfExpiredTokens();
    $manageTokenObj = $restHelper->getPlugin('manage-token');

    $finalTokens = array();
    foreach ($tokens as $token) {
      list($tokenPk) = explode(".", $token['id']);
      $tokenVal = $manageTokenObj->revealToken($tokenPk);
      $finalTokens[] = array_merge($token, ['token' => $tokenVal['token']]);
    }

    $returnVal = new Info(200, "Success", InfoType::INFO);
    $res = $returnVal->getArray();
    $res[$tokenType . ($apiVersion == ApiVersion::V2 ? 'Tokens' : '_tokens')] = $finalTokens;
    return $response->withJson($res, $returnVal->getCode());
  }

  /**
   * Agent DTO keys included in user export/import, in the same
   * naming used by the addUser() V2 agents object (and by extension the
   * REST User schema and the frontend's Add User form).
   *
   * pkgagent, softwareHeritage and ipra are intentionally excluded: issue
   * #428 and the FOSSologyUI Operations page that consumes these endpoints
   * don't require agent-setting fidelity, and addUser()'s existing V2
   * handling of those three has a pre-existing key-mapping bug that's out
   * of scope to fix here (see buildImportAgentCheckboxes()).
   */
  const AGENT_KEYS = [
    'bucket', 'copyrightEmailAuthor', 'ecc', 'keyword', 'mime',
    'monk', 'nomos', 'ojo', 'reso',
  ];

  /**
   * Allowed values for the "delimiter" field of handleImportUsers(),
   * matching the enum documented in openapiv2.yaml and the dropdown a
   * standard admin UI is expected to offer.
   */
  const CSV_DELIMITERS = [',', ';', "\t"];

  /**
   * Allowed values for the "enclosure" field of handleImportUsers(),
   * matching the enum documented in openapiv2.yaml.
   */
  const CSV_ENCLOSURES = ['"', "'"];

  /**
   * Export all users to a CSV or JSON file. The requested format comes from
   * the "format" query parameter (?format=csv|json, defaults to csv), so
   * this single /users/export handler backs both formats. "delimiter"/
   * "enclosure" query parameters are validated against
   * CSV_DELIMITERS/CSV_ENCLOSURES the same way handleImportUsers() does
   * (see resolveCsvOption()), but only when format=csv; they're ignored
   * entirely when format=json.
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpBadRequestException if format is not 'csv'/'json', or if
   *   delimiter/enclosure is set but not one of the allowed values
   * @throws HttpErrorException
   */
  public function handleExportUsers($request, $response, $args)
  {
    $this->throwNotAdminException();
    $queryParams = $request->getQueryParams();
    $formatRaw = $queryParams['format'] ?? 'csv';
    // Guard against a non-string value (e.g. ?format[]=csv, which PHP
    // parses into an array) the same way resolveCsvOption() already
    // guards delimiter/enclosure -- strtolower() on a non-string throws
    // a TypeError on PHP 8 instead of hitting the clean 400 below.
    $format = strtolower(is_string($formatRaw) ? $formatRaw : '');
    if ($format !== 'csv' && $format !== 'json') {
      throw new HttpBadRequestException("Invalid format '" . $format .
        "'. Only 'csv' and 'json' are supported.");
    }
    if ($format === 'csv') {
      $delimiter = $this->resolveCsvOption($queryParams['delimiter'] ?? null,
        self::CSV_DELIMITERS, ',', 'delimiter');
      $enclosure = $this->resolveCsvOption($queryParams['enclosure'] ?? null,
        self::CSV_ENCLOSURES, '"', 'enclosure');
    }
    $users = $this->dbHelper->getUsers();
    if ($format === 'json') {
      return $this->exportUsersToJSON($response, $users);
    }
    return $this->exportUsersToCSV($response, $users, $delimiter, $enclosure);
  }

  /**
   * Render the CSV export response body for handleExportUsers().
   *
   * @param ResponseHelper $response
   * @param \Fossology\UI\Api\Models\User[] $users
   * @param string $delimiter One of CSV_DELIMITERS
   * @param string $enclosure One of CSV_ENCLOSURES
   * @return ResponseHelper
   */
  private function exportUsersToCSV($response, $users, $delimiter = ',', $enclosure = '"')
  {
    $content = $this->generateUsersCsv($users, $delimiter, $enclosure);
    $fileName = "fossology-users-export-" . date("YMj-Gis");
    $newResponse = $response->withHeader('Content-type', 'text/csv, charset=UTF-8')
      ->withHeader('Content-Disposition', 'attachment; filename=' . $fileName . '.csv')
      ->withHeader('Pragma', 'no-cache')
      ->withHeader('Cache-Control', 'no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0')
      ->withHeader('Expires', 'Expires: Thu, 19 Nov 1981 08:52:00 GMT');
    $sf = new StreamFactory();
    return $newResponse->withBody($sf->createStream($content));
  }

  /**
   * Render the JSON export response body for handleExportUsers().
   *
   * @param ResponseHelper $response
   * @param \Fossology\UI\Api\Models\User[] $users
   * @return ResponseHelper
   */
  private function exportUsersToJSON($response, $users)
  {
    $content = json_encode($this->generateUsersExportArray($users), JSON_PRETTY_PRINT);
    $fileName = "fossology-users-export-" . date("YMj-Gis");
    $newResponse = $response->withHeader('Content-type', 'text/json, charset=UTF-8')
      ->withHeader('Content-Disposition', 'attachment; filename=' . $fileName . '.json')
      ->withHeader('Pragma', 'no-cache')
      ->withHeader('Cache-Control', 'no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0')
      ->withHeader('Expires', 'Expires: Thu, 19 Nov 1981 08:52:00 GMT');
    $sf = new StreamFactory();
    return $newResponse->withBody($sf->createStream($content));
  }

  /**
   * Import users from an uploaded CSV or JSON file (fileInput) via the
   * single /users/import endpoint. The file extension of the upload
   * determines the parser used -- there is no separate route per format.
   *
   * The uploaded rows are always parsed into the V2-shaped agents DTO (see
   * AGENT_KEYS) regardless of which API version's URL the request came in
   * on -- these endpoints are only documented in openapiv2.yaml, so
   * createUserFromImportRow() always builds the V2 agent keys. This avoids
   * silently dropping agent flags for a request that happened to reach
   * this shared '/users' route group via the /repo/api/v1 prefix.
   *
   * For CSV, "delimiter"/"enclosure" are validated against
   * CSV_DELIMITERS/CSV_ENCLOSURES (see resolveCsvOption()) -- a value
   * outside that fixed set is rejected rather than truncated.
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpBadRequestException if delimiter/enclosure is set but not
   *   one of the allowed values
   * @throws HttpErrorException
   */
  public function handleImportUsers($request, $response, $args)
  {
    $this->throwNotAdminException();
    $symReq = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    $uploadedFile = $symReq->files->get('fileInput', null);

    if (!($uploadedFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile)) {
      throw new HttpBadRequestException("No file selected");
    }
    if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
      throw new HttpBadRequestException($uploadedFile->getErrorMessage());
    }

    $extension = strtolower($uploadedFile->getClientOriginalExtension());
    if ($extension !== 'csv' && $extension !== 'json') {
      throw new HttpBadRequestException("Invalid file extension '" . $extension .
        "' of file " . $uploadedFile->getClientOriginalName() .
        ". Only csv and json files are supported.");
    }

    if ($extension === 'csv') {
      $requestBody = $this->getParsedBody($request) ?? [];
      $delimiter = $this->resolveCsvOption($requestBody['delimiter'] ?? null,
        self::CSV_DELIMITERS, ',', 'delimiter');
      $enclosure = $this->resolveCsvOption($requestBody['enclosure'] ?? null,
        self::CSV_ENCLOSURES, '"', 'enclosure');
      $userRows = $this->parseUsersCsv($uploadedFile->getRealPath(), $delimiter, $enclosure);
    } else {
      $userRows = $this->parseUsersJson($uploadedFile->getRealPath());
    }

    if ($userRows === false) {
      throw new HttpBadRequestException("Uploaded file is empty or malformed.");
    }
    if (empty($userRows)) {
      throw new HttpBadRequestException("No users found in the uploaded file.");
    }

    $successCount = 0;
    $errors = [];
    foreach ($userRows as $index => $userDetails) {
      // CSV rows carry their physical file line number; JSON rows (which
      // have no comparable notion of a skipped line) fall back to their
      // position among the parsed rows.
      $rowLabel = $userDetails['_csvLineNumber'] ?? ($index + 1);
      unset($userDetails['_csvLineNumber']);
      if (empty($userDetails['name'])) {
        $errors[] = "Row " . $rowLabel . ": Username must be specified.";
        continue;
      }
      list($success, $message) = $this->createUserFromImportRow($userDetails);
      if ($success) {
        $successCount++;
      } else {
        $errors[] = "Row " . $rowLabel . " ('" . $userDetails['name'] . "'): " . $message;
      }
    }

    $summary = "$successCount of " . count($userRows) . " user(s) imported successfully.";
    if (!empty($errors)) {
      $summary .= "\n" . implode("\n", $errors);
    }

    if ($successCount === 0) {
      throw new HttpBadRequestException($summary);
    }

    $newInfo = new Info(200, $summary, InfoType::INFO);
    return $response->withJson($newInfo->getArray(), $newInfo->getCode());
  }

  /**
   * Resolve a CSV "delimiter"/"enclosure" form field against its fixed
   * enum (CSV_DELIMITERS/CSV_ENCLOSURES), enforcing the contract
   * documented in openapiv2.yaml. A missing/empty/non-string value
   * (e.g. a malformed multipart array) is treated as "not provided" and
   * falls back to $default rather than erroring, since that's a client
   * quirk rather than a deliberate selection; a present string value
   * that isn't one of $allowed is rejected outright.
   *
   * @param mixed $value Raw value from the parsed multipart body, or null.
   * @param string[] $allowed Allowed single-byte values, e.g. CSV_DELIMITERS.
   * @param string $default Value to use when $value is missing/empty/non-string.
   * @param string $fieldName Field name, for the error message.
   * @return string
   * @throws HttpBadRequestException if $value is a string but not in $allowed.
   */
  private function resolveCsvOption($value, array $allowed, $default, $fieldName)
  {
    if (!is_string($value) || $value === '') {
      return $default;
    }
    if (!in_array($value, $allowed, true)) {
      $allowedDesc = implode(', ', array_map(function ($option) {
        return $option === "\t" ? 'tab' : "'" . $option . "'";
      }, $allowed));
      throw new HttpBadRequestException("Invalid " . $fieldName . ". Must be one of: " .
        $allowedDesc . ".");
    }
    return $value;
  }

  /**
   * Build the (nested) export DTO for a single user, ready to be
   * json_encode()'d directly or flattened for a CSV row by
   * flattenExportRowForCsv(). Translates Analysis::getArray(V2)'s
   * "mimetype" key back to "mime" so the export stays symmetric with the
   * rest of the V2 API (addUser DTO, Analysis::setUsingArray).
   *
   * @param \Fossology\UI\Api\Models\User $user
   * @return array
   */
  private function exportRowFromUser($user)
  {
    $userArray = $user->getArray(ApiVersion::V2);
    $agents = $userArray['agents'] ?? [];
    $row = [
      'id' => $userArray['id'] ?? null,
      'name' => $userArray['name'] ?? '',
      'description' => $userArray['description'] ?? '',
      'email' => $userArray['email'] ?? '',
      'accessLevel' => $userArray['accessLevel'] ?? '',
      'rootFolderId' => $userArray['rootFolderId'] ?? '',
      'defaultGroup' => $userArray['defaultGroup'] ?? null,
      'emailNotification' => $userArray['emailNotification'] ?? false,
      'defaultBucketpool' => $userArray['defaultBucketpool'] ?? null,
      'agents' => [],
    ];
    foreach (self::AGENT_KEYS as $agentKey) {
      $sourceKey = $agentKey === 'mime' ? 'mimetype' : $agentKey;
      $row['agents'][$agentKey] = $agents[$sourceKey] ?? false;
    }
    return $row;
  }

  /**
   * Flatten a nested exportRowFromUser() row into a single-level array
   * with "agent_xxx" columns, suitable for a CSV row.
   *
   * @param array $row
   * @return array
   */
  private function flattenExportRowForCsv($row)
  {
    $flat = $row;
    $agents = $flat['agents'];
    unset($flat['agents']);
    foreach ($agents as $agentKey => $value) {
      $flat['agent_' . $agentKey] = $value;
    }
    return $flat;
  }

  /**
   * CSV header / column order used by both generateUsersCsv() and
   * flattenExportRowForCsv().
   *
   * @return string[]
   */
  private function csvHeader()
  {
    $agentColumns = array_map(function ($agentKey) {
      return 'agent_' . $agentKey;
    }, self::AGENT_KEYS);
    return array_merge([
      'id', 'name', 'description', 'email', 'accessLevel', 'rootFolderId',
      'defaultGroup', 'emailNotification', 'defaultBucketpool',
    ], $agentColumns);
  }

  /**
   * Export all users to a JSON-ready array of nested user DTOs.
   *
   * @param \Fossology\UI\Api\Models\User[] $users
   * @return array[]
   */
  private function generateUsersExportArray($users)
  {
    return array_map(function ($user) {
      return $this->exportRowFromUser($user);
    }, $users);
  }

  /**
   * Generate a CSV document listing all given users.
   *
   * @param \Fossology\UI\Api\Models\User[] $users
   * @param string $delimiter One of CSV_DELIMITERS
   * @param string $enclosure One of CSV_ENCLOSURES
   * @return string CSV content
   */
  private function generateUsersCsv($users, $delimiter = ',', $enclosure = '"')
  {
    $out = fopen('php://temp', 'r+');
    $header = $this->csvHeader();
    fputcsv($out, $header, $delimiter, $enclosure);
    foreach ($users as $user) {
      $flat = $this->flattenExportRowForCsv($this->exportRowFromUser($user));
      $csvRow = [];
      foreach ($header as $field) {
        $value = $flat[$field];
        if (is_bool($value)) {
          $csvRow[] = $value ? 1 : 0;
        } else {
          $csvRow[] = $this->escapeCsvFormula($value);
        }
      }
      fputcsv($out, $csvRow, $delimiter, $enclosure);
    }
    rewind($out);
    $content = stream_get_contents($out);
    fclose($out);
    return $content;
  }

  /**
   * Neutralize CSV/spreadsheet formula injection: user-controlled fields
   * (name, description, email) are exported verbatim, and a value starting
   * with =, +, -, @ or a tab/CR is interpreted as a formula by Excel/
   * LibreOffice/Sheets when the file is opened. Prefixing with a single
   * quote keeps the value readable while forcing it to be treated as text.
   *
   * @param mixed $value
   * @return mixed
   */
  private function escapeCsvFormula($value)
  {
    if (!is_string($value) || $value === '') {
      return $value;
    }
    if (strpbrk($value[0], "=+-@\t\r") !== false) {
      return "'" . $value;
    }
    return $value;
  }

  /**
   * Convert a single import row into the createUserFromImportRow() DTO
   * shape. Accepts
   * either a CSV row (flat, with "agent_xxx" columns) or a JSON row
   * (optionally with a nested "agents" object) -- whichever shape is
   * present is used. Unknown/extra keys (e.g. id, defaultGroup) are
   * ignored.
   *
   * "userPass" is read here (column/field name "userPass" in both CSV
   * and JSON, same as the other non-agent fields) but is never produced
   * by exportRowFromUser()/generateUsersExportArray() -- passwords
   * aren't stored in a recoverable form, so a file exported from
   * /users/export needs a userPass column/field added before it can be
   * re-imported. createUserFromImportRow() rejects the row if this is
   * empty.
   *
   * @param array $row
   * @return array
   */
  private function importRowToUserDetails($row)
  {
    $toBool = function ($value) {
      return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    };
    $agentsSource = isset($row['agents']) && is_array($row['agents']) ? $row['agents'] : $row;

    $agents = [];
    foreach (self::AGENT_KEYS as $agentKey) {
      $value = $agentsSource[$agentKey] ?? $agentsSource['agent_' . $agentKey] ?? false;
      $agents[$agentKey] = $toBool($value);
    }

    return [
      'name' => trim((string) ($row['name'] ?? '')),
      'userPass' => $row['userPass'] ?? '',
      'description' => $row['description'] ?? '',
      'email' => $row['email'] ?? '',
      'accessLevel' => $row['accessLevel'] ?? '',
      'rootFolderId' => $row['rootFolderId'] ?? '',
      'emailNotification' => $toBool($row['emailNotification'] ?? false),
      'defaultBucketpool' => $row['defaultBucketpool'] ?? null,
      'agents' => $agents,
    ];
  }

  /**
   * Parse an uploaded CSV file of users into an array of
   * createUserFromImportRow() DTOs. Each DTO carries an internal
   * "_csvLineNumber" key (the physical line in the file, header = line
   * 1, accounting for embedded newlines in earlier quoted fields) that
   * handleImportUsers() uses for its per-row error messages and strips
   * before passing the DTO on.
   *
   * @param string $filePath Path to the uploaded file on disk
   * @param string $delimiter CSV column delimiter
   * @param string $enclosure CSV field enclosure
   * @return array[]|false List of user DTOs, or false on unreadable file
   *   or a header with a duplicate column name
   */
  private function parseUsersCsv($filePath, $delimiter, $enclosure)
  {
    $handle = @fopen($filePath, 'r');
    if ($handle === false) {
      return false;
    }
    $header = fgetcsv($handle, 0, $delimiter, $enclosure);
    if ($header === false) {
      fclose($handle);
      return false;
    }
    // Strip a leading UTF-8 BOM (e.g. from Excel's "CSV UTF-8" export, or
    // a /users/export?format=csv file re-saved through Excel) from the
    // first header cell -- trim() does not remove it, and left in place
    // it silently turns the "name" column into an unmatched "\xEF\xBB\xBFname"
    // key, making every row look like it has no username.
    if (isset($header[0])) {
      $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    }
    $header = array_map('trim', $header);
    if (count($header) !== count(array_unique($header))) {
      // array_combine() below would otherwise silently keep only the
      // last column's value for a repeated header name (e.g.
      // "name,email,name"), discarding the rest with no error.
      fclose($handle);
      return false;
    }
    $rows = [];
    $lineNumber = 1; // the header itself is physical line 1
    while (($data = fgetcsv($handle, 0, $delimiter, $enclosure)) !== false) {
      // A quoted field may embed a newline, making fgetcsv() consume
      // more than one physical line for a single logical row; count
      // those too so later rows' _csvLineNumber stays accurate.
      $lineNumber++;
      $rowStartLine = $lineNumber;
      foreach ($data as $field) {
        $lineNumber += substr_count((string) $field, "\n");
      }
      if (count($data) === 1 && trim((string) $data[0]) === '') {
        continue; // skip blank lines
      }
      // Normalize row length to the header, tolerating ragged CSV rows.
      $data = array_pad(array_slice($data, 0, count($header)), count($header), '');
      $row = array_combine($header, $data);
      $userDetails = $this->importRowToUserDetails($row);
      $userDetails['_csvLineNumber'] = $rowStartLine;
      $rows[] = $userDetails;
    }
    fclose($handle);
    return $rows;
  }

  /**
   * Parse an uploaded JSON file of users into an array of
   * createUserFromImportRow() DTOs.
   * Accepts a raw array of user objects, a single user object, or an
   * object with a "users" array property (as produced by the JSON export).
   *
   * A single user object is detected by shape (not-a-list), not by the
   * presence of a "name" key -- a malformed single object missing "name"
   * is still wrapped as one row, so it surfaces as a normal per-row
   * "Username must be specified" error instead of being misread as a
   * bag of scalar values and silently producing zero rows.
   *
   * @param string $filePath Path to the uploaded file on disk
   * @return array[]|false List of user DTOs, or false on malformed JSON
   */
  private function parseUsersJson($filePath)
  {
    $content = @file_get_contents($filePath);
    if ($content === false) {
      return false;
    }
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
      return false;
    }
    // PHP 7.4-compatible array_is_list() (added in PHP 8.1, below the
    // 7.4.3 minimum in composer.json).
    $isList = $data === [] || array_keys($data) === range(0, count($data) - 1);
    if (array_key_exists('users', $data) && is_array($data['users'])) {
      $data = $data['users'];
    } elseif (!$isList) {
      $data = [$data];
    }
    $rows = [];
    foreach ($data as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $rows[] = $this->importRowToUserDetails($entry);
    }
    return $rows;
  }
}
