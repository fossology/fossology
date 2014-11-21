<?php
/*
 Copyright (C) 2014, Siemens AG
 Author: Daniele Fognini, Johannes Najjar

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
use Fossology\Lib\BusinessRules\ClearingDecisionFilter;
use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\Dao\AgentsDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\View\HighlightProcessor;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\View\HighlightRenderer;
use Monolog\Logger;

define("TITLE_clearingView", _("Change concluded License "));

class ClearingView extends FO_Plugin
{
  /** @var UploadDao */
  private $uploadDao;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var AgentsDao */
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

  function __construct()
  {
    $this->Name = "view-license";
    $this->Title = TITLE_clearingView;
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->Dependency = array("view");
    $this->LoginFlag = 0;
    $this->NoMenu = 0;
    parent::__construct();

    global $container;
    $this->licenseDao = $container->get('dao.license');
    $this->uploadDao = $container->get('dao.upload');
    $this->clearingDao = $container->get('dao.clearing');
    $this->agentsDao = $container->get('dao.agents');
    $this->logger = $container->get("logger");

    $this->highlightDao = $container->get("dao.highlight");
    $this->highlightRenderer = $container->get("view.highlight_renderer");
    $this->highlightProcessor = $container->get("view.highlight_processor");

    $this->decisionTypes = $container->get('decision.types');

    $this->clearingDecisionEventProcessor = $container->get('businessrules.clearing_decision_processor');
    $this->clearingDecisionFilter = $container->get('businessrules.clearing_decision_filter');
  }


  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param $licenseId
   * @param $selectedAgentId
   * @param $highlightId
   * @return array
   */
  private function getSelectedHighlighting(ItemTreeBounds $itemTreeBounds, $licenseId, $selectedAgentId, $highlightId, $clearingId)
  {
    $highlightEntries = $this->highlightDao->getHighlightEntries($itemTreeBounds, $licenseId, $selectedAgentId, $highlightId, $clearingId);
    if (($selectedAgentId > 0) || ($clearingId > 0))
    {
      $this->highlightProcessor->addReferenceTexts($highlightEntries);
    } else
    {
      $this->highlightProcessor->flattenHighlights($highlightEntries, array("K", "K "));
    }
    return $highlightEntries;
  }


  function OutputOpen()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return (0);
    }
    $uploadId = GetParm("upload", PARM_INTEGER);
    if (empty($uploadId))
    {
      return;
    }

    $uploadTreeId = GetParm("item", PARM_INTEGER);
    if (empty($uploadTreeId))
    {
      $parent = $this->uploadDao->getUploadParent($uploadId);
      if (!isset($parent))
      {
        $this->invalidParm = true;
        return;
      }

      $item = $this->uploadDao->getNextItem($uploadId, $parent);
      if ($item === UploadDao::NOT_FOUND)
      {
        $this->invalidParm = true;
        return;
      }
      $uploadTreeId = $item->getId();
      header('Location: ' . Traceback_uri() . '?mod=' . $this->Name . Traceback_parm_keep(array("upload", "show")) . "&item=$uploadTreeId");
    }

    $uploadTreeTableName = GetUploadtreeTableName($uploadId);
    $uploadEntry = $this->uploadDao->getUploadEntry($uploadTreeId, $uploadTreeTableName);
    if (Isdir($uploadEntry['ufile_mode']) || Iscontainer($uploadEntry['ufile_mode']))
    {
      $parent = $this->uploadDao->getUploadParent($uploadId);
      if (!isset($parent))
      {
        $this->invalidParm = true;
        return;
      }

      $item = $this->uploadDao->getNextItem($uploadId, $parent);
      if ($item === UploadDao::NOT_FOUND)
      {
        $this->invalidParm = true;
        return;
      }
      $uploadTreeId = $item->getId();
      header('Location: ' . Traceback_uri() . '?mod=' . $this->Name . Traceback_parm_keep(array("upload", "show")) . "&item=$uploadTreeId");
    }

    return parent::OutputOpen();
  }


  /**
   * @brief extends standard Output to handle empty uploads
   */
  function Output()
  {
    if ($this->invalidParm)
    {
      $this->vars['content'] = 'This upload contains no files!<br><a href="' . Traceback_uri() . '?mod=browse">Go back to browse view</a>';
      return $this->renderTemplate("include/base.html.twig");
    }
    parent::Output();
  }

  /**
   * \brief display the license changing page
   */
  protected function htmlContent()
  {
    $uploadId = GetParm("upload", PARM_INTEGER);
    if (empty($uploadId))
    {
      return;
    }
    $uploadTreeId = GetParm("item", PARM_INTEGER);
    if (empty($uploadTreeId))
    {
      return;
    }

    global $SysConf;
    $userId = $SysConf['auth']['UserId'];

    $lastItem = GetParm("lastItem", PARM_INTEGER);

    if (!empty($lastItem))
    {
      $this->updateLastItem($userId, $lastItem);
    }

    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($uploadTreeId, $uploadTreeTableName);

    $this->vars['micromenu'] = Dir2Browse('license', $uploadTreeId, NULL, $showBox = 0, "Clearing", -1, '', '', $uploadTreeTableName);

    global $Plugins;
    /** @var ui_view $view */
    $view = &$Plugins[plugin_find_id("view")];

    $licenseId = GetParm("licenseId", PARM_INTEGER);
    $selectedAgentId = GetParm("agentId", PARM_INTEGER);
    $highlightId = GetParm("highlightId", PARM_INTEGER);
    $clearingId = GetParm("clearingId", PARM_INTEGER);

    if ($clearingId !== null)
    {
      $highlightId = -1;
    } else if ($highlightId !== null)
    {
      $clearingId = -1;
    }

    $baseUri = Traceback_uri();
    $this->vars['baseuri'] = $baseUri;
    $this->vars['uri'] = $baseUri . "?mod=" . $this->Name . Traceback_parm_keep(array('upload', 'folder'));
    $this->vars['bulkHistoryHighlightUri'] = $this->vars['uri'];
    $this->vars['optionName'] = "skipFile";
    $this->vars['formName'] = "uiClearingForm";
    $this->vars['ajaxAction'] = "setNextPrev";
    $highlights = $this->getSelectedHighlighting($itemTreeBounds, $licenseId, $selectedAgentId, $highlightId, $clearingId);

    $permission = GetUploadPerm($uploadId);

    $isSingleFile = !$itemTreeBounds->containsFiles();
    $hasWritePermission = $permission >= PERM_WRITE;

    $clearingDecisions = null;
    if ($isSingleFile || $hasWritePermission)
    {
      $clearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds);
      $clearingDecisions = $this->clearingDecisionFilter->filterRelevantClearingDecisions($clearingDecisions);
    }

    if ($isSingleFile)
    {
      if ($permission >= PERM_WRITE)
      {
        $this->vars['bulkUri'] = Traceback_uri() . "?mod=popup-license";
        $this->vars['licenseArray'] = $this->licenseDao->getLicenseArray($uploadId);
      } else
      {
        $this->vars['auditDenied'] = true;
      }
    }

    $clearingHistory = array();
    $selectedClearingType = false;
    if ($hasWritePermission)
    {
      $clearingHistory = $this->getClearingHistory($clearingDecisions);
    }
    if (count($clearingHistory) > 0)
    {
      $selectedClearingType = $this->decisionTypes->getTypeByName($clearingHistory[0]['type']);
    }

    $bulkHistory = $this->clearingDao->getBulkHistory($itemTreeBounds);

    $ModBack = GetParm("modback", PARM_STRING) ?: "license";
    list($pageMenu, $textView) = $view->getView(NULL, $ModBack, 0, "", $highlights, false, true);

    $this->vars['uploadId'] = $uploadId;
    $this->vars['itemId'] = $uploadTreeId;
    $this->vars['pageMenu'] = $pageMenu;
    $this->vars['textView'] = $textView;
    $this->vars['legendData'] = $this->highlightRenderer->getLegendData(($selectedAgentId > 0 && $licenseId > 0) || ($clearingId > 0));
    $this->vars['clearingTypes'] = $this->decisionTypes->getMap();
    $this->vars['selectedClearingType'] = $selectedClearingType;
    $this->vars['tmpClearingType'] = $selectedClearingType ? $this->clearingDao->isDecisionWip($uploadTreeId, $userId) : FALSE;
    $this->vars['clearingHistory'] = $clearingHistory;
    $this->vars['bulkHistory'] = $bulkHistory;
  }


  /**
   * @param ClearingDecision[] $clearingDecWithLicenses
   * @return array
   */
  private function getClearingHistory($clearingDecWithLicenses)
  {
    $table = array();
    $scope = new DecisionScopes();
    foreach ($clearingDecWithLicenses as $clearingDecision)
    {
      $licenseNames = array();
      foreach ($clearingDecision->getPositiveLicenses() as $lic)
      {
        $licenseShortName = $lic->getShortName();
        $licenseNames[$lic->getShortName()] = $licenseShortName;
      }
      foreach ($clearingDecision->getNegativeLicenses() as $lic)
      {
        $licenseShortName = $lic->getShortName();
        $licenseNames[$lic->getShortName()] = "<span style=\"color:red\">" . $licenseShortName . "</span>";
      }
      ksort($licenseNames, SORT_STRING);
      $row = array(
          'date' => $clearingDecision->getDateAdded(),
          'username' => $clearingDecision->getUserName(),
          'scope' => $scope->getTypeName($clearingDecision->getScope()),
          'type' => $this->decisionTypes->getTypeName($clearingDecision->getType()),
          'licenses' => implode(", ", $licenseNames));
      $table[] = $row;
    }
    return $table;
  }

  public function getTemplateName()
  {
    return "ui-clearing-view.html.twig";
  }

  /*
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $text = _("Set the concluded licenses for this upload");
    menu_insert("Browse-Pfile::Clearing", 0, $this->Name, $text);
    menu_insert("View::Audit", 35, $this->Name . Traceback_parm_keep(array("upload", "item", "show")), $text);
    return 0;
  }

  /**
   * @param $userId
   * @param $lastItem
   * @return array
   */
  protected function updateLastItem($userId, $lastItem)
  {
    $type = GetParm("clearingTypes", PARM_INTEGER);
    $global = GetParm("globalDecision", PARM_STRING) === "on";

    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($lastItem);
    $itemBounds = $this->uploadDao->getItemTreeBounds($lastItem, $uploadTreeTableName);

    $this->clearingDecisionEventProcessor->makeDecisionFromLastEvents($itemBounds, $userId, $type, $global);
  }
}

$NewPlugin = new ClearingView;