<?php
/*
 SPDX-FileCopyrightText: © 2014-2017 Siemens AG
 Author: Daniele Fognini, Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\BusinessRules\ClearingDecisionFilter;
use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\Clearing\ClearingResult;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Proxy\ScanJobProxy;
use Fossology\Lib\Proxy\UploadTreeProxy;
use Fossology\Lib\UI\Component\MicroMenu;
use Fossology\Lib\View\HighlightProcessor;
use Fossology\Lib\View\HighlightRenderer;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

define("TITLE_CLEARINGVIEW", _("Change concluded License "));

class ClearingView extends FO_Plugin
{
  /** @var UploadDao */
  private $uploadDao;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var AgentDao */
  private $agentsDao;
  /** @var Logger */
  private $logger;
  /** @var HighlightDao */
  private $highlightDao;
  /** @var HighlightProcessor */
  private $highlightProcessor;
  /** @var HighlightRenderer */
  private $highlightRenderer;
  /** @var ClearingDecisionProcessor */
  private $clearingDecisionEventProcessor;
  /** @var ClearingDecisionFilter */
  private $clearingDecisionFilter;
  /** @var bool */
  private $invalidParm = false;
  /** @var DecisionTypes */
  private $decisionTypes;
  /** @var int */
  private $searchChunkSize = 1048576;
  /** @var int */
  private $searchBlockSize = 81920;

  function __construct()
  {
    $this->Name = "view-license";
    $this->Title = TITLE_CLEARINGVIEW;
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->Dependency = array("view");
    $this->LoginFlag = 0;
    $this->NoMenu = 0;
    parent::__construct();

    global $container;
    $this->licenseDao = $container->get('dao.license');
    $this->uploadDao = $container->get('dao.upload');
    $this->clearingDao = $container->get('dao.clearing');
    $this->agentsDao = $container->get('dao.agent');
    $this->logger = $container->get("logger");
    $this->highlightDao = $container->get("dao.highlight");
    $this->highlightRenderer = $container->get("view.highlight_renderer");
    $this->highlightProcessor = $container->get("view.highlight_processor");

    $this->decisionTypes = $container->get('decision.types');

    $this->clearingDecisionEventProcessor = $container->get(
      'businessrules.clearing_decision_processor');
    $this->clearingDecisionFilter = $container->get(
      'businessrules.clearing_decision_filter');
  }


  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param $licenseId
   * @param $selectedAgentId
   * @param $highlightId
   * @param int $clearingId
   * @param int $uploadId
   * @return Highlight[]
   */
  public function getSelectedHighlighting(ItemTreeBounds $itemTreeBounds, $licenseId, $selectedAgentId, $highlightId, $clearingId, $uploadId)
  {
    $unmaskAgents = $selectedAgentId;
    if (empty($selectedAgentId)) {
      $scanJobProxy = new ScanJobProxy($this->agentsDao,$uploadId);
      $scanJobProxy->createAgentStatus(array_keys(AgentRef::AGENT_LIST));
      $unmaskAgents = $scanJobProxy->getLatestSuccessfulAgentIds();
    }
    $highlightEntries = $this->highlightDao->getHighlightEntries($itemTreeBounds,
      $licenseId, $unmaskAgents, $highlightId, $clearingId);
    $groupId = Auth::getGroupId();
    if (($selectedAgentId > 0) || ($clearingId > 0)) {
      $this->highlightProcessor->addReferenceTexts($highlightEntries, $groupId);
    } else {
      $this->highlightProcessor->flattenHighlights($highlightEntries, array("K", "K "));
    }
    return $highlightEntries;
  }

  public function execute()
  {
    $openOutput = $this->OutputOpen();
    if ($openOutput instanceof RedirectResponse) {
      $response = $openOutput;
    } else {
      $response = $this->getResponse();
    }
    $response->prepare($this->getRequest());
    $response->send();
  }

  function OutputOpen()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return (0);
    }
    $uploadId = GetParm("upload", PARM_INTEGER);
    if (empty($uploadId)) {
      return;
    }

    $uploadTreeId = GetParm("item", PARM_INTEGER);
    if (empty($uploadTreeId)) {
      $parent = $this->uploadDao->getUploadParent($uploadId);
      if (!isset($parent)) {
        $this->invalidParm = true;
        return;
      }

      $item = $this->uploadDao->getNextItem($uploadId, $parent);
      if ($item === UploadDao::NOT_FOUND) {
        $this->invalidParm = true;
        return;
      }
      $uploadTreeId = $item->getId();
      return new RedirectResponse(Traceback_uri() . '?mod=' . $this->Name
        . Traceback_parm_keep(array("upload", "show")) . "&item=$uploadTreeId");
    }

    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $uploadEntry = $this->uploadDao->getUploadEntry($uploadTreeId, $uploadTreeTableName);
    if (Isdir($uploadEntry['ufile_mode']) || Iscontainer($uploadEntry['ufile_mode'])) {
      $parent = $this->uploadDao->getUploadParent($uploadId);
      if (!isset($parent)) {
        $this->invalidParm = true;
        return;
      }

      $item = $this->uploadDao->getNextItem($uploadId, $parent);
      if ($item === UploadDao::NOT_FOUND) {
        $this->invalidParm = true;
        return;
      }
      $uploadTreeId = $item->getId();
      return new RedirectResponse(Traceback_uri() . '?mod=' . $this->Name
        . Traceback_parm_keep(array("upload", "show")) . "&item=$uploadTreeId");
    }

    return parent::OutputOpen();
  }


  /**
   * @brief extends standard Output to handle empty uploads
   */
  function Output()
  {
    if ($this->invalidParm) {
      $this->vars['content'] = 'This upload contains no files!<br><a href="'
        . Traceback_uri() . '?mod=browse">Go back to browse view</a>';
      return $this->render("include/base.html.twig");
    }

    $uploadId = GetParm("upload", PARM_INTEGER);
    if (empty($uploadId)) {
      return new Response("", Response::HTTP_BAD_REQUEST);
    }
    $uploadTreeId = GetParm("item", PARM_INTEGER);
    if (empty($uploadTreeId)) {
      return new Response("", Response::HTTP_BAD_REQUEST);
    }

    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();

    $lastItem = GetParm("lastItem", PARM_INTEGER);

    if (!empty($lastItem)) {
      $currentUploadtreeId = $lastItem;
      if ($lastItem == $uploadTreeId) {
        $currentUploadtreeId = $uploadTreeId;
      }
      $this->updateLastItem($userId, $groupId, $lastItem, $currentUploadtreeId);
    }

    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($uploadTreeId, $uploadTreeTableName);

    $this->vars['micromenu'] = Dir2Browse('license', $uploadTreeId, NULL,
      $showBox = 0, "View", -1, '', '', $uploadTreeTableName);

    global $Plugins;
    /** @var ui_view $view */
    $view = &$Plugins[plugin_find_id("view")];

    $licenseId = GetParm("licenseId", PARM_INTEGER);
    $selectedAgentId = GetParm("agentId", PARM_INTEGER);
    $highlightId = GetParm("highlightId", PARM_INTEGER);
    $clearingId = GetParm("clearingId", PARM_INTEGER);

    $searchQuery = GetParm("search", PARM_STRING);
    $this->vars['searchQuery'] = $searchQuery;
    $this->vars['searchMatches'] = [];
    $this->vars['currentPage'] = GetParm("page", PARM_INTEGER) ?: 0;

    if ($clearingId !== null) {
      $highlightId = -1;
    } else if ($highlightId !== null) {
      $clearingId = -1;
    }

    $baseUri = Traceback_uri();
    $this->vars['baseuri'] = $baseUri;
    $this->vars['uri'] = $baseUri . "?mod=" . $this->Name . Traceback_parm_keep(array('upload', 'folder'));
    $this->vars['bulkHistoryHighlightUri'] = $this->vars['uri'];
    $this->vars['optionName'] = "skipFile";
    $this->vars['formName'] = "uiClearingForm";
    $this->vars['ajaxAction'] = "setNextPrev";
    $highlights = $this->getSelectedHighlighting($itemTreeBounds, $licenseId,
      $selectedAgentId, $highlightId, $clearingId, $uploadId);
    if (empty($searchQuery)) {
      $request = $this->getRequest();
      if ($request) {
        $searchQuery = $request->query->get('search', '');
      }
    }
    if (!empty($searchQuery)) {
      $searchQuery = trim($searchQuery);
      if (strlen($searchQuery) < 3) {
        $this->vars['searchMatches'] = [];
      } else {
        $item = GetParm("item", PARM_INTEGER);
        $filePath = RepPathItem($item);

        if ($filePath && file_exists($filePath)) {
          $this->vars['searchMatches'] = $this->getSearchMatches($filePath, $searchQuery, $this->searchBlockSize);
        } else {
          $this->vars['searchMatches'] = [];
        }
      }
    }

    $isSingleFile = !$itemTreeBounds->containsFiles();
    $hasWritePermission = $this->uploadDao->isEditable($uploadId, $groupId);

    $clearingDecisions = null;
    if ($isSingleFile || $hasWritePermission) {
      $clearingDecisions = $this->clearingDao->getFileClearings($itemTreeBounds, $groupId, false);
    }

    if ($isSingleFile && $hasWritePermission) {
      $this->vars['bulkUri'] = Traceback_uri() . "?mod=popup-license";
      $licenseArray = $this->licenseDao->getLicenseArray($groupId);
      list($addedResults, $removedResults) = $this->clearingDecisionEventProcessor->getCurrentClearings($itemTreeBounds, $groupId, LicenseMap::CONCLUSION);
      if (count($addedResults)+count($removedResults)>0) {
        array_unshift($licenseArray, array('id'=>0,'fullname'=>'','shortname'=>'------'));
      }
      /** @var ClearingResult $result */
      foreach ($removedResults as $result) {
        array_unshift($licenseArray, array( 'id'=>$result->getLicenseId() ,'fullname'=>$result->getLicenseFullName() ,'shortname'=>$result->getLicenseShortName()));
      }
      /** @var ClearingResult $result */
      foreach ($addedResults as $result) {
        array_unshift($licenseArray, array( 'id'=>$result->getLicenseId() ,'fullname'=>$result->getLicenseFullName() ,'shortname'=>$result->getLicenseShortName()));
      }
      $this->vars['licenseArray'] = $licenseArray;
    } elseif ($isSingleFile) {
      $this->vars['auditDenied'] = true;
    }

    $selectedClearingType = false;
    $selectedClearingScope = false;
    if (!empty($clearingDecisions)) {
      $selectedClearingType = $clearingDecisions[0]->getType();
      $selectedClearingScope = $clearingDecisions[0]->getScope();
    }
    $bulkHistory = $this->clearingDao->getBulkHistory($itemTreeBounds, $groupId);
    $hasKotobaFindings = $this->clearingDao->hasKotobaFindings($itemTreeBounds, $groupId);
    $kotobaHistory = array();
    if ($hasKotobaFindings) {
      $kotobaHistory = $this->clearingDao->getKotobaHistory($itemTreeBounds, $groupId);
    }

    $ModBack = GetParm("modback", PARM_STRING) ?: "license";
    list($pageMenu, $textView) = $view->getView(NULL, $ModBack, 0, "", $highlights, false, true);

    $this->vars['uploadId'] = $uploadId;
    $this->vars['itemId'] = $uploadTreeId;
    $this->vars['pageMenu'] = $pageMenu;
    $this->vars['textView'] = $textView;
    $this->vars['legendData'] = $this->highlightRenderer->getLegendData($selectedAgentId || $clearingId);
    $this->vars['clearingTypes'] = $this->decisionTypes->getMap();
    $this->vars['selectedClearingType'] = $selectedClearingType;
    $this->vars['selectedClearingScope'] = $selectedClearingScope;
    $this->vars['tmpClearingType'] = $this->clearingDao->isDecisionCheck($uploadTreeId, $groupId, DecisionTypes::WIP);
    $this->vars['bulkHistory'] = $bulkHistory;
    $this->vars['hasKotobaFindings'] = $hasKotobaFindings;
    $this->vars['kotobaHistory'] = $kotobaHistory;

    $noLicenseUploadTreeView = new UploadTreeProxy($uploadId,
      array(UploadTreeProxy::OPT_SKIP_THESE => "noLicense",
        UploadTreeProxy::OPT_GROUP_ID => $groupId),
      $uploadTreeTableName,
      'no_license_uploadtree' . $uploadId);
    $filesOfInterest = $noLicenseUploadTreeView->count();

    $nonClearedUploadTreeView = new UploadTreeProxy($uploadId,
      array(UploadTreeProxy::OPT_SKIP_THESE => "alreadyCleared",
        UploadTreeProxy::OPT_GROUP_ID => $groupId),
      $uploadTreeTableName,
      'already_cleared_uploadtree' . $uploadId);
    $filesToBeCleared = $nonClearedUploadTreeView->count();

    $filesAlreadyCleared = $filesOfInterest - $filesToBeCleared;
    $this->vars['message'] = _("Cleared").": $filesAlreadyCleared/$filesOfInterest";
    if ($filesOfInterest == 0) {
      $percentage = 100;
    } else {
      $percentage = ($filesAlreadyCleared / $filesOfInterest) * 100;
      $percentage = ($percentage > 11) ? $percentage : 11;
    }
    $this->vars['progressBar'] = "width:".$percentage."%";

    return $this->render("ui-clearing-view.html.twig");
  }

  /*
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $menuText="Licenses";
    $menuPosition = 58;
    $uri = $this->Name . Traceback_parm_keep(array("upload", "item", "show"));
    $tooltipText = _("Set the concluded licenses for this upload");
    $this->microMenu->insert(array(MicroMenu::VIEW, MicroMenu::VIEW_META), $menuText, $menuPosition, $this->Name, $uri, $tooltipText );

    if (GetParm("mod", PARM_STRING) != $this->Name) {
      menu_insert("Browse-Pfile::$menuText", 0, $this->Name, $tooltipText);
    }
    return 0;
  }

  /**
   * @param int $userId
   * @param int $groupId
   * @param int $lastItem
   * @param int $currentUploadtreeId
   * @return array
   */
  public function updateLastItem($userId, $groupId, $lastItem, $currentUploadtreeId)
  {
    $type = GetParm("clearingTypes", PARM_INTEGER);
    $global = GetParm("globalDecision", PARM_STRING) === "on" ? 1 : 0;
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($lastItem);
    $itemBounds = $this->uploadDao->getItemTreeBounds($lastItem, $uploadTreeTableName);
    if ($global) {
      $isDecisionWip = $this->clearingDao->isDecisionCheck($currentUploadtreeId, $groupId, DecisionTypes::WIP);
      $hasChangedClearingType = $this->clearingDao->isDecisionCheck($currentUploadtreeId, $groupId, '');
      if ($isDecisionWip) {
        $this->clearingDecisionEventProcessor->makeDecisionFromLastEvents($itemBounds, $userId, $groupId, $type, $global);
      } else if (empty($hasChangedClearingType['scope'])
             || ($hasChangedClearingType['decision_type'] != $type)
           ) {
        $this->clearingDecisionEventProcessor->makeDecisionFromLastEvents($itemBounds, $userId, $groupId, $type, $global);
      } else {
        return;
      }
    } else {
      $this->clearingDecisionEventProcessor->makeDecisionFromLastEvents($itemBounds, $userId, $groupId, $type, $global);
    }
  }

  /**
   * @param string $filePath
   * @param string $searchQuery
   * @param int $blockSize
   * @return array
   */
  private function getSearchMatches($filePath, $searchQuery, $blockSize = 81920)
  {
    $handle = fopen($filePath, "rb");
    if (!$handle) {
      return [];
    }

    $searchMatches = [];
    $searchLen = strlen($searchQuery);
    $currentFilePos = 0;
    $overlapBuffer = '';

    $pattern = '/' . preg_quote($searchQuery, '/') . '/i';

    while (!feof($handle)) {
      $chunk = fread($handle, $this->searchChunkSize);
      $haystack = $overlapBuffer . $chunk;

      if (preg_match_all($pattern, $haystack, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
          $posInHaystack = $match[1];
          $absolutePos = $currentFilePos - strlen($overlapBuffer) + $posInHaystack;

          if (empty($searchMatches) || end($searchMatches)['position'] !== $absolutePos) {
            $searchMatches[] = [
              'position' => $absolutePos,
              'page' => (int)floor($absolutePos / $blockSize)
            ];
          }
        }
      }

      $overlapBuffer = ($searchLen > 1) ? substr($haystack, -($searchLen - 1)) : '';
      $currentFilePos += strlen($chunk);
    }

    fclose($handle);
    return $searchMatches;
  }
}

$NewPlugin = new ClearingView;
