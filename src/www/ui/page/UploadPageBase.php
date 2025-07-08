<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Plugin\AgentPlugin;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\UI\MenuHook;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;

abstract class UploadPageBase extends DefaultPlugin
{
  const NAME = "upload_file";
  const FOLDER_PARAMETER_NAME = 'folder';

  const DESCRIPTION_INPUT_NAME = 'descriptionInputName';
  const DESCRIPTION_VALUE = 'descriptionValue';
  const UPLOAD_FORM_BUILD_PARAMETER_NAME = 'uploadformbuild';
  const PUBLIC_ALL = 'public';
  const PUBLIC_GROUPS = 'protected';

  /** @var FolderDao */
  private $folderDao;
  /** @var UploadDao */
  private $uploadDao;
  /** @var Logger */
  private $logger;
  /** @var UserDao */
  private $userDao;

  public function __construct($name, $parameters = array())
  {
    parent::__construct($name, $parameters);

    $this->folderDao = $this->getObject('dao.folder');
    $this->uploadDao = $this->getObject('dao.upload');
    $this->logger = $this->getObject('logger');
    $this->userDao = $this->getObject('dao.user');
  }
  abstract protected function handleUpload(Request $request);
  abstract protected function handleView(Request $request, $vars);

  protected function handle(Request $request)
  {
    // Handle request
    $this->folderDao->ensureTopLevelFolder();

    $message = "";
    $description = "";
    if ($request->isMethod(Request::METHOD_POST)) {
      list($success, $message, $description) = $this->handleUpload($request);
    }
    $vars['message'] = $message;
    $vars['descriptionInputValue'] = $description ?: "";
    $vars['descriptionInputName'] = self::DESCRIPTION_INPUT_NAME;
    $vars['folderParameterName'] = self::FOLDER_PARAMETER_NAME;
    $vars['upload_max_filesize'] = ini_get('upload_max_filesize');
    $vars['agentCheckBoxMake'] = '';
    global $SysConf;
    $userId = Auth::getUserId();
    $UserRec = $this->userDao->getUserByPk($userId);
    if (!empty($UserRec['upload_visibility'])) {
      $vars['uploadVisibility'] = $UserRec['upload_visibility'];
    } else {
      $vars['uploadVisibility'] = $SysConf['SYSCONFIG']['UploadVisibility'];
    }
    $rootFolder = $this->folderDao->getDefaultFolder(Auth::getUserId());
    if ($rootFolder == NULL) {
      $rootFolder = $this->folderDao->getRootFolder(Auth::getUserId());
    }
    $folderStructure = $this->folderDao->getFolderStructure($rootFolder->getId());

    $vars['folderStructure'] = $folderStructure;
    $vars['baseUrl'] = $request->getBaseUrl();
    $vars['moduleName'] = $this->getName();
    $vars[self::FOLDER_PARAMETER_NAME] = $request->get(self::FOLDER_PARAMETER_NAME);

    $parmAgentList = MenuHook::getAgentPluginNames("ParmAgents");
    $vars['parmAgentContents'] = array();
    $vars['parmAgentFoots'] = array();
    foreach ($parmAgentList as $parmAgent) {
      $agent = plugin_find($parmAgent);
      $vars['parmAgentContents'][] = $agent->renderContent($vars);
      $vars['parmAgentFoots'][] = $agent->renderFoot($vars);
    }

    $session = $request->getSession();
    $session->set(self::UPLOAD_FORM_BUILD_PARAMETER_NAME, time().':'.$_SERVER['REMOTE_ADDR']);
    $vars['uploadFormBuild'] = $session->get(self::UPLOAD_FORM_BUILD_PARAMETER_NAME);
    $vars['uploadFormBuildParameterName'] = self::UPLOAD_FORM_BUILD_PARAMETER_NAME;

    if (@$_SESSION[Auth::USER_LEVEL] >= PLUGIN_DB_WRITE) {
      $skip = array("agent_unpack", "agent_adj2nest", "wget_agent");
      $vars['agentCheckBoxMake'] = AgentCheckBoxMake(-1, $skip);
    }
    $vars['configureExcludeFolders'] = ($exclude = $this->sanitizeExcludePatterns($SysConf['SYSCONFIG']['ExcludeFolders'] ?? '')) ? $exclude : "No Folder Configured";

    return $this->handleView($request, $vars);
  }

  protected function postUploadAddJobs(Request $request, $fileName, $uploadId, $jobId = null, $wgetDependency = false)
  {
    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();

    if ($jobId === null) {
      $jobId = JobAddJob($userId, $groupId, $fileName, $uploadId);
    }
    $dummy = "";

    global $SysConf;
    $unpackArgs = intval($request->get('scm')) === 1 ? '-I' : '';

    if (intval($request->get('excludefolder'))) {
      $rawExclude = $SysConf['SYSCONFIG']['ExcludeFolders'] ?? '';
      if (trim($rawExclude) !== '') {
        $excludeFolders = $this->sanitizeExcludePatterns($rawExclude);
        $excludeArgs = '-E ' . $excludeFolders;
        $unpackArgs .= ' ' . $excludeArgs;
      }
    }
    $adj2nestDependencies = array();
    if ($wgetDependency) {
      $adj2nestDependencies = array(array('name'=>'agent_unpack','args'=>$unpackArgs,AgentPlugin::PRE_JOB_QUEUE=>array('wget_agent')));
    }
    $adj2nestplugin = \plugin_find('agent_adj2nest');
    $adj2nestplugin->AgentAdd($jobId, $uploadId, $dummy, $adj2nestDependencies,
        null, null, (empty($adj2nestDependencies) ? $unpackArgs : ''));

    $checkedAgents = checkedAgents();
    AgentSchedule($jobId, $uploadId, $checkedAgents);

    $errorMsg = '';
    $parmAgentList = MenuHook::getAgentPluginNames("ParmAgents");
    $plainAgentList = MenuHook::getAgentPluginNames("Agents");
    $agentList = array_merge($plainAgentList, $parmAgentList);

    $this->rearrangeDependencies($parmAgentList);

    foreach ($parmAgentList as $parmAgent) {
      $agent = plugin_find($parmAgent);
      $agent->scheduleAgent($jobId, $uploadId, $errorMsg, $request, $agentList);
    }

    $status = GetRunnableJobList();
    $message = empty($status) ? _("Is the scheduler running? ") : "";
    $jobUrl = Traceback_uri() . "?mod=showjobs&upload=$uploadId";
    $message .= _("The file") . " " . $fileName . " " . _("has been uploaded. It is") .
        ' <a href=' . $jobUrl . '>upload #' . $uploadId . "</a>.\n";
    if ($request->get('public')==self::PUBLIC_GROUPS) {
      $this->getObject('dao.upload.permission')->makeAccessibleToAllGroupsOf($uploadId, $userId);
    }
    return $message;
  }

  /**
   * \brief checks, whether a string contains some special character without
   * escaping
   *
   * \param $str - the string to check
   * \param $char - the character to search for

   * \return boolean
   */
  function str_contains_notescaped_char($str, $char)
  {
    $pos = 0;
    while ($pos < strlen($str) &&
           ($pos = strpos($str,$char,$pos)) !== false) {
      foreach (range(($pos++) -1, 1, -2) as $tpos) {
        if ($tpos > 0 && $str[$tpos] !== '\\') {
          break;
        }
        if ($tpos > 1 && $str[$tpos - 1] !== '\\') {
          continue 2;
        }
      }
      return true;
    }
    return false;
  }

  /**
   * \brief checks, whether a path is a pattern from the perspective of a shell
   *
   * \param $path - the path to check
   *
   * \return boolean
   */
  function path_is_pattern($path)
  {
    return $this->str_contains_notescaped_char($path, '*')
      || $this->str_contains_notescaped_char($path, '?')
      || $this->str_contains_notescaped_char($path, '[')
      || $this->str_contains_notescaped_char($path, '{');
  }

  /**
   * \brief checks, whether a path contains substrings, which could enable it to
   * escape his prefix
   *
   * \param $path - the path to check
   *
   * \return boolean
   */
  protected function path_can_escape($path)
  {
    return $this->str_contains_notescaped_char($path, '$')
      || strpos($path,'..') !== false;
  }

  /**
   * \brief normalizes an path and returns FALSE on errors
   *
   * \param $path - the path to normalize
   * \param $appendix - optional parameter, which is used for the recursive call
   *
   * \return normalized path on success
   *         FALSE on error
   *
   */
  function normalize_path($path, $host="localhost", $appendix="")
  {
    if (strpos($path,'/') === false || $path === '/') {
      return false;
    }
    if ($this->path_is_pattern($path)) {
      $bpath = basename($path);
      if ($this->path_can_escape($bpath)) {
        return false;
      }

      if (strcmp($host,"localhost") === 0) {
        return $this->normalize_path(dirname($path),
                                     $host,
                                     $bpath . ($appendix == '' ?
                                               '' :
                                               '/' . $appendix));
      } else {
        if ($this->path_can_escape($path)) {
          return false;
        }
        return $path . ($appendix == '' ?
                        '' :
                        '/' . $appendix);
      }
    } else {
      $rpath = realpath($path);
      if ($rpath === false) {
        return false;
      }
      return $rpath . ($appendix == '' ?
                       '' :
                       '/' . $appendix);
    }
  }

  function basicShEscaping($str)
  {
    $str = str_replace('\\', '\\\\', $str);
    $str = str_replace('"', '\"', $str);
    $str = str_replace('`', '\`', $str);
    $str = str_replace('$', '\$', $str);
    return $str;
  }

  /**
   * Make sure reuser is scheduled before decider so decider does not run
   * another reuser as dependency
   * @param[in,out] array $parmList List of parameterized agents
   */
  private function rearrangeDependencies(&$parmList)
  {
    $deciderKey = array_search('agent_decider', $parmList);
    $reuserKey = array_search('agent_reuser', $parmList);
    if ($deciderKey !== false && $reuserKey !== false) {
      $temp = $parmList[$deciderKey];
      $parmList[$deciderKey] = $parmList[$reuserKey];
      $parmList[$reuserKey] = $temp;
    }
  }
  /**
   * Sanitize a comma-separated list of exclude path patterns.
   *
   * This function processes a string of comma-separated path patterns and returns
   * a sanitized list as a comma-separated string. It:
   *   - Trims whitespace from each pattern.
   *   - Skips empty patterns, relative paths (e.g., '.', '..', './', '../'),
   *     and patterns containing special characters: ?, ", ,, {, }, :.
   *   - Ensures that each valid pattern ends with a forward slash ('/').
   *
   * Examples:
   *   Input:  "folder1, ./temp, ../secret, folder2, file?, folder3/"
   *   Output: "folder1/,folder2/,folder3/"
   *
   * @param string $patternStr Comma-separated path patterns to sanitize.
   *
   * @return string Comma-separated string of valid, sanitized, and normalized patterns,
   *                each ending with a trailing slash ('/').
   */
  private function sanitizeExcludePatterns($patternStr)
  {
    $patterns = explode(',', $patternStr);
    $sanitized = [];

    foreach ($patterns as $pattern) {
      $trimmed = trim($pattern);
      // Skip empty strings, relative paths (./, ../), or ones with special characters
      if (
        $trimmed === '' ||
        preg_match('#(^|/)(\.\.?)(/|$)|^[/.?]+$|[?,"{}:]#', $trimmed)
      ) {
        continue;
      }

      // Ensure the pattern ends with "/"
      if (substr($trimmed, -1) !== '/') {
        $trimmed .= '/';
      }

      $sanitized[] = $trimmed;
    }
    return implode(',', $sanitized);
  }
}
