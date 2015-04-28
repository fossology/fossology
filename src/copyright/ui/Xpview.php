<?php
/*
 Copyright (C) 2014-2015, Siemens AG
 Author: Johannes Najjar

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

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\View\HighlightRenderer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

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
  /** @var HighlightRenderer */
  protected $highlightRenderer;
  /** @var DecisionTypes */
  protected $decisionTypes;
  /** @var string */
  protected $decisionTableName;
  /** @var string */
  protected $tableName;
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
    
    parent::__construct($name,$mergedParams);
    
    $this->uploadDao = $this->getObject('dao.upload');
    $this->copyrightDao = $this->getObject('dao.copyright');
    $this->highlightRenderer = $this->getObject('view.highlight_renderer');
    $this->decisionTypes = $this->getObject('decision.types');
    
  }
  

  protected function handle(Request $request)
  {
    $vars = array();
    $uploadId = intval($request->get('upload'));
    $uploadTreeId = intval($request->get('item'));
    if (empty($uploadTreeId) || empty($uploadId))
    {
      $text = _("Empty Input");
      $vars['message']= "<h2>$text</h2>";
      return $this->responseBad($vars);
    }

    $permission = GetUploadPerm($uploadId);
    if($permission < Auth::PERM_READ ) {
      $text = _("Permission Denied");
      $vars['message']= "<h2>$text</h2>";
      return $this->responseBad();
    }

    $uploadTreeTableName = GetUploadtreeTableName($uploadId);
    $uploadEntry = $this->uploadDao->getUploadEntry($uploadTreeId, $uploadTreeTableName);
    if (Isdir($uploadEntry['ufile_mode']) || Iscontainer($uploadEntry['ufile_mode']))
    {
      $parent = $this->uploadDao->getUploadParent($uploadEntry['upload_fk']);
      if (!isset($parent))
      {
        return $this->responseBad();
      }

      $uploadTreeId = $this->uploadDao->getNextItem($uploadEntry['upload_fk'], $parent);
      if ($uploadTreeId === UploadDao::NOT_FOUND)
      {
        return $this->responseBad();
      }

      return new RedirectResponse(Traceback_uri() . '?mod=' . $this->Name . Traceback_parm_keep(array("show")) . "&item=$uploadTreeId");
    }
    if (empty($uploadTreeId))
    {
      return $this->responseBad('No item selected.');
    }

    $copyrightDecisionMap = $this->decisionTypes->getMap();
    $vars['micromenu'] = Dir2Browse($this->modBack, $uploadTreeId, NULL, $showBox = 0, "Clearing", -1, '', '', $uploadTreeTableName);

    $lastItem = GetParm("lastItem", PARM_INTEGER);
    $changed= GetParm("changedSomething", PARM_STRING);
    $userId = Auth::getUserId();
    if (!empty($lastItem) && $changed =="true")
    {
      $lastUploadEntry = $this->uploadDao->getUploadEntry($lastItem, $uploadTreeTableName);
      $clearingType = $_POST['clearingTypes'];
      $description = $_POST['description'];
      $textFinding = $_POST['textFinding'];
      $comment = $_POST['comment'];
      $this->copyrightDao->saveDecision($this->decisionTableName ,$lastUploadEntry['pfile_fk'], $userId , $clearingType,
          $description, $textFinding, $comment);
    }

    $highlights = $this->copyrightDao->getHighlights($uploadTreeId, $this->tableName, $this->typeToHighlightTypeMap);

    if (count($highlights) < 1)
    {
      $vars['message'] = _("No ").$this->tableName ._(" data is available for this file.");
    }

    /** @var ui_view $view */
    $view = plugin_find("view");
    $theView = $view->getView(null, null, $showHeader=0, "", $highlights, false, true);
    list($pageMenu, $textView)  = $theView;

    list($description,$textFinding,$comment, $decisionType)=$this->copyrightDao->getDecision($this->decisionTableName ,$uploadEntry['pfile_fk']);
    $vars['description'] = $description;
    $vars['textFinding'] = $textFinding;
    $vars['comment'] = $comment;

    $vars['itemId'] = $uploadTreeId;
    $vars['uploadId'] = $uploadId;
    $vars['pageMenu'] = $pageMenu;
    $vars['textView'] = $textView;
    $vars['legendBox'] = $this->legendBox();
    $vars['uri'] = Traceback_uri() . "?mod=" . $this->Name;
    $vars['optionName'] = $this->optionName;
    $vars['formName'] = "CopyRightForm";
    $vars['ajaxAction'] = $this->ajaxAction;
    $vars['skipOption'] =$this->skipOption;
    $vars['selectedClearingType'] = $decisionType;
    $vars['clearingTypes'] = $copyrightDecisionMap;
    $vars['xptext'] = $this->xptext;
    
    return $this->render('ui-cp-view.html.twig',$this->mergeWithDefault($vars));
  }
  
  /**
   * @overwrite
   */
  protected function mergeWithDefault($vars)
  {
    $allVars = array_merge($this->getDefaultVars(), $vars);
    $allVars['styles'] .= "<link rel='stylesheet' href='css/highlights.css'>\n";
    return $allVars;
  }

  
  private function responseBad($vars=array())
  {
    $vars['content'] = 'This upload contains no files!<br><a href="' . Traceback_uri() . '?mod=browse">Go back to browse view</a>';
    return $this->render("include/base.html.twig",$vars);
  }
  
  
  /**
   * @return string rendered legend box
   */
  function legendBox()
  {
    $output = _("file text");
    foreach ($this->highlightTypeToStringMap as $colorKey => $txt)
    {
      $output .= '<br/>' . $this->highlightRenderer->createStartSpan($colorKey, $txt) . $txt . '</span>';
    }
    return $output;
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    // For this menu, I prefer having this in one place
    $text = _("Set the concluded licenses for this upload");
    menu_insert("Clearing::Licenses", 36, "view-license" . Traceback_parm_keep(array("upload", "item", "show")), $text);

    $text = _("View Copyright/Email/Url info");
    menu_insert("Clearing::Copyright", 35, "copyright-view" . Traceback_parm_keep(array("show", "format", "page", "upload", "item")), $text);

    $text = _("View Patent info");
    menu_insert("Clearing::IP", 34, "ip-view" . Traceback_parm_keep(array("show", "format", "page", "upload", "item")), $text);

    $text = _("View Export Control and Customs info");
    menu_insert("Clearing::ECC", 33, "ecc-view" . Traceback_parm_keep(array("show", "format", "page", "upload", "item")), $text);

    $text = _("View file information");
    menu_insert("Clearing::Info", 3, "view_info" . Traceback_parm_keep(array("upload", "item", "format")), $text);

    $text = _("Browse by buckets");
    menu_insert("Clearing::Bucket Browser", 4, "bucketbrowser" . Traceback_parm_keep(array("format", "page", "upload", "item", "bp"), $text));
    $text = _("Copyright/Email/URL One-shot, real-time analysis");
    menu_insert("Clearing::One-Shot Copyright/Email/URL",2, "agent_copyright_once", $text);
    $text = _("Nomos One-shot, real-time license analysis");
    menu_insert("Clearing::One-Shot License", 2, "agent_nomos_once" . Traceback_parm_keep(array("format", "item")), $text);

    menu_insert("Clearing::[BREAK]", 6);

    /** @var ui_view $view */
    $view = plugin_find("view");
    $itemId = GetParm("item", PARM_INTEGER);
    $textFormat = $view->getFormatParameter($itemId);
    $pageNumber = GetParm("page", PARM_INTEGER);
    $view->addFormatMenuEntries($textFormat, $pageNumber, "Clearing");

    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array("show", "format", "page", "upload", "item"));
    $Upload = GetParm("upload", PARM_INTEGER);
    if (!empty($itemId) && !empty($Upload))
    {
      if (GetParm("mod", PARM_STRING) == $this->Name)
      {
        menu_insert("View::View Copyright/Email/Url", 1);
        menu_insert("View-Meta::View Copyright/Email/Url", 1);
      } else
      {
        $text = _("View Copyright/Email/Url info");
        menu_insert("View::Copyright/Email/Url", 1, $URI, $text);
        menu_insert("View-Meta::Copyright/Email/Url", 1, $URI, $text);
      }
    }
    $Lic = GetParm("lic", PARM_INTEGER);
    if (!empty($Lic))
    {
      $this->NoMenu = 1;
    }
  }
} 