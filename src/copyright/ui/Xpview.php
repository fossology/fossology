<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG
 Author: Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @namespace Fossology::Agent::Copyright::UI
 * @brief Namespace for Copyright agent's UI components
 */
namespace Fossology\Agent\Copyright\UI;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Proxy\ScanJobProxy;
use Fossology\Lib\UI\Component\MicroMenu;
use Fossology\Lib\View\HighlightRenderer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ui_view;

/**
 * @class Xpview
 * @brief Default plugin
 */
class Xpview extends DefaultPlugin
{
  /** @var string */
  protected $optionName;
  /** @var string */
  protected $ajaxAction;
  /** @var string */
  protected $modBack;
  /** @var UploadDao */
  protected $uploadDao;
  /** @var CopyrightDao */
  protected $copyrightDao;
  /** @var AgentDao */
  protected $agentDao;
  /** @var HighlightRenderer */
  protected $highlightRenderer;
  /** @var DecisionTypes */
  protected $decisionTypes;
  /** @var string */
  protected $decisionTableName;
  /** @var string */
  protected $tableName;
  /** @var string */
  protected $agentName;
  /** @var  array */
  protected $highlightTypeToStringMap;
  /** @var  array */
  protected $typeToHighlightTypeMap;
  /** @var string */
  protected $skipOption;
  /** @var string */
  protected $xptext;

  function __construct($name, $params)
  {
    $mergedParams = array_merge($params, array(self::DEPENDENCIES=>array("browse", "view"),
                                       self::PERMISSION=> Auth::PERM_READ));

    parent::__construct($name, $mergedParams);
    $this->agentName = $this->tableName;

    $this->uploadDao = $this->getObject('dao.upload');
    $this->copyrightDao = $this->getObject('dao.copyright');
    $this->agentDao = $this->getObject('dao.agent');
    $this->highlightRenderer = $this->getObject('view.highlight_renderer');
    $this->decisionTypes = $this->getObject('decision.types');
  }

  /**
   * @copydoc Fossology::Lib::Plugin::DefaultPlugin::handle()
   * @see Fossology::Lib::Plugin::DefaultPlugin::handle()
   */
  protected function handle(Request $request)
  {
    $vars = array();
    $uploadId = intval($request->get('upload'));
    $uploadTreeId = intval($request->get('item'));
    if (empty($uploadTreeId) || empty($uploadId)) {
      $text = _("Empty Input");
      $vars['message']= "<h2>$text</h2>";
      return $this->responseBad($vars);
    }

    if (!$this->uploadDao->isAccessible($uploadId, Auth::getGroupId())) {
      $text = _("Permission Denied");
      $vars['message']= "<h2>$text</h2>";
      return $this->responseBad();
    }

    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $uploadEntry = $this->uploadDao->getUploadEntry($uploadTreeId, $uploadTreeTableName);
    if (Isdir($uploadEntry['ufile_mode']) || Iscontainer($uploadEntry['ufile_mode'])) {
      $parent = $this->uploadDao->getUploadParent($uploadEntry['upload_fk']);
      if (!isset($parent)) {
        return $this->responseBad();
      }

      $uploadTree = $this->uploadDao->getNextItem($uploadEntry['upload_fk'], $parent);
      if ($uploadTree === UploadDao::NOT_FOUND) {
        return $this->responseBad();
      }
      $uploadTreeId = $uploadTree->getId();

      return new RedirectResponse(Traceback_uri() . '?mod=' . $this->getName() . Traceback_parm_keep(array('show','upload')) . "&item=$uploadTreeId");
    }
    if (empty($uploadTreeId)) {
      return $this->responseBad('No item selected.');
    }

    $copyrightDecisionMap = $this->decisionTypes->getMap();
    $vars['micromenu'] = Dir2Browse($this->modBack, $uploadTreeId, NULL, $showBox = 0, "View", -1, '', '', $uploadTreeTableName);

    $lastItem = GetParm("lastItem", PARM_INTEGER);
    $changed= GetParm("changedSomething", PARM_STRING);
    $userId = Auth::getUserId();
    if (!empty($lastItem) && $changed =="true") {
      $lastUploadEntry = $this->uploadDao->getUploadEntry($lastItem, $uploadTreeTableName);
      $clearingType = GetParm('clearingTypes', PARM_INTEGER);
      $description = trim(GetParm('description', PARM_STRING));
      $textFinding = trim(GetParm('textFinding', PARM_STRING));
      $comment = trim(GetParm('comment', PARM_STRING));
      $decision_pk = GetParm('decision_pk', PARM_INTEGER);
      if (empty($clearingType) || empty($textFinding)) {
        if (empty($clearingType)) {
          $text = _("The clearing type cannot be empty. " .
            "Please choose a value and submit again.");
        } else {
          $text = _("The text finding cannot be empty. " .
            "Please enter a text and submit again.");
        }
        $vars['message']= "<strong>$text</strong>";
      } else {
        $this->copyrightDao->saveDecision($this->decisionTableName,
          $lastUploadEntry['pfile_fk'], $userId , $clearingType, $description,
          $textFinding, $comment, $decision_pk);
      }
    }

    $scanJobProxy = new ScanJobProxy($this->agentDao, $uploadId);
    $scanJobProxy->createAgentStatus(array($this->agentName));
    $selectedScanners = $scanJobProxy->getLatestSuccessfulAgentIds();
    $highlights = array();
    if (array_key_exists($this->agentName, $selectedScanners)) {
      $latestXpAgentId = $selectedScanners[$this->agentName];
      $highlights = $this->copyrightDao->getHighlights($uploadTreeId, $this->tableName, $latestXpAgentId, $this->typeToHighlightTypeMap);
    }

    if (count($highlights) < 1) {
      $vars['message'] = _("No ").$this->tableName ._(" data is available for this file.");
    }

    /* @var $view ui_view */
    $view = plugin_find("view");
    $theView = $view->getView(null, null, $showHeader=0, "", $highlights, false, true);
    list($pageMenu, $textView)  = $theView;

    $decisions = $this->copyrightDao->getDecisions($this->decisionTableName, $uploadEntry['pfile_fk']);

    $vars['agentName'] = $this->agentName;
    $vars['decisions'] = $decisions;
    $vars['decisionsTable'] = $this->decisionTableName;
    $vars['itemId'] = $uploadTreeId;
    $vars['uploadId'] = $uploadId;
    $vars['pfile'] = $uploadEntry['pfile_fk'];
    $vars['pageMenu'] = $pageMenu;
    $vars['textView'] = $textView;
    $vars['legendBox'] = $this->legendBox();
    $vars['uri'] = Traceback_uri() . "?mod=" . $this->Name;
    $vars['optionName'] = $this->optionName;
    $vars['formName'] = "CopyRightForm";
    $vars['ajaxAction'] = $this->ajaxAction;
    $vars['skipOption'] =$this->skipOption;
    $vars['clearingTypes'] = $copyrightDecisionMap;
    $vars['xptext'] = $this->xptext;

    $agentId = intval($request->get("agent"));
    $vars = array_merge($vars,$this->additionalVars($uploadId, $uploadTreeId, $agentId));
    return $this->render('ui-cp-view.html.twig',$this->mergeWithDefault($vars));
  }

  /**
   * @brief Get additional variables for a give item
   * @param int $uploadId
   * @param int $uploadTreeId
   * @param int $agentId
   * @return array
   * @todo Not implemented
   */
  protected function additionalVars($uploadId, $uploadTreeId, $agentId)
  {
    return array();
  }

  /**
   * @copydoc Fossology::Lib::Plugin::DefaultPlugin::mergeWithDefault()
   * @see Fossology::Lib::Plugin::DefaultPlugin::mergeWithDefault()
   */
  protected function mergeWithDefault($vars)
  {
    $allVars = array_merge($this->getDefaultVars(), $vars);
    $allVars['styles'] .= "<link rel='stylesheet' href='css/highlights.css'>\n";
    return $allVars;
  }

  /**
   * @brief Call on bad uploads
   * @param array $vars Extra vars to load by template
   * @return Response
   */
  private function responseBad($vars=array())
  {
    $vars['content'] = 'This upload contains no files!<br><a href="' . Traceback_uri() . '?mod=browse">Go back to browse view</a>';
    return $this->render("include/base.html.twig",$vars);
  }

  /**
   * @brief Create legend box
   * @return string rendered legend box
   */
  function legendBox()
  {
    $output = _("file text");
    foreach ($this->highlightTypeToStringMap as $colorKey => $txt) {
      $output .= '<br/>' . $this->highlightRenderer->createStartSpan($colorKey, $txt) . $txt . '</span>';
    }
    return $output;
  }

  /**
   * @copydoc Fossology::Lib::Plugin::DefaultPlugin::RegisterMenus()
   * @see Fossology::Lib::Plugin::DefaultPlugin::RegisterMenus()
   */
  function RegisterMenus()
  {
    $tooltipText = _("Copyright/Email/Url/Author");
    menu_insert("Browse-Pfile::Copyright/Email/Url", 0, 'copyright-view', $tooltipText);

    $itemId = GetParm("item", PARM_INTEGER);
    $textFormat = $this->microMenu->getFormatParameter($itemId);
    $pageNumber = GetParm("page", PARM_INTEGER);
    $this->microMenu->addFormatMenuEntries($textFormat, $pageNumber);

    // For all other menus, permit coming back here.
    $uploadId = GetParm("upload", PARM_INTEGER);
    if (!empty($itemId) && !empty($uploadId)) {
      $menuText = "Copyright/Email/Url/Author";
      $menuPosition = 57;
      $tooltipText = _("View Copyright/Email/Url/Author info");
      $URI = $this->getName() . Traceback_parm_keep(array("show", "format", "page", "upload", "item"));
      $this->microMenu->insert(MicroMenu::TARGET_DEFAULT, $menuText, $menuPosition, $this->getName(), $URI, $tooltipText);
    }
    $licId = GetParm("lic", PARM_INTEGER);
    if (!empty($licId)) {
      $this->NoMenu = 1;
    }
  }
}
