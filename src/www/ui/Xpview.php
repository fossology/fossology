<?php
/*
 Copyright (C) 2014, Siemens AG
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
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Highlight;
use Fossology\Lib\View\HighlightRenderer;

/***********************************************************
 * Copyright (C) 2014 Siemens AG
 * Author: J.Najjar
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

class Xpview extends FO_Plugin
{
  /** @var  string */
  protected $optionName;
  /** @var  string */
  protected $ajaxAction;
  /** @var  string */
  protected $modBack;
  /** @var UploadDao */
  protected $uploadDao;
  /** @var CopyrightDao */
  protected $copyrightDao;
  /** @var HighlightRenderer */
  protected $highlightRenderer;
  /** bool */
  protected $invalidParm = false;
  /** array */
  protected $uploadEntry;
  /** @var DecisionTypes */
  protected $decisionTypes;
  /**  @var string */
  protected $decisionTableName;
  /**  @var string */
  protected $tableName;
  /** @var  array */
  protected $hightlightTypeToStringMap;
  /** @var  array */
  protected $typeToHighlightTypeMap;
  /**  @var string */
  protected $skipOption;

  function __construct()
  {
    $this->Dependency = array("browse", "view");
    $this->DBaccess = PLUGIN_DB_READ;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;
    parent::__construct();

    global $container;
    $this->uploadDao = $container->get('dao.upload');
    $this->copyrightDao = $container->get('dao.copyright');
    $this->highlightRenderer = $container->get('view.highlight_renderer');
    $this->decisionTypes = $container->get('decision.types');
  }

  function OutputOpen()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      $this->invalidParm = true;
      return (0);
    }
    $uploadId = GetParm("upload", PARM_INTEGER);
    $uploadTreeId = GetParm("item", PARM_INTEGER);
    if (empty($uploadTreeId) || empty($uploadId))
    {
      $this->invalidParm = true;
      return;
    }

    $permission = GetUploadPerm($uploadId);
    if($permission < PERM_READ ) {
      $text = _("Permission Denied");
      $this->vars['message']= "<h2>$text<h2>";
      $this->invalidParm =true;
      return;
    }

    $uploadTreeTableName = GetUploadtreeTableName($uploadId);
    $this->uploadEntry = $this->uploadDao->getUploadEntry($uploadTreeId, $uploadTreeTableName);
    if (Isdir($this->uploadEntry['ufile_mode']) || Iscontainer($this->uploadEntry['ufile_mode']))
    {
      $parent = $this->uploadDao->getUploadParent($this->uploadEntry['upload_fk']);
      if (!isset($parent))
      {
        $this->invalidParm = true;
        return;
      }

      $uploadTreeId = $this->uploadDao->getNextItem($this->uploadEntry['upload_fk'], $parent);
      if ($uploadTreeId === UploadDao::NOT_FOUND)
      {
        $this->invalidParm = true;
        return;
      }

      header('Location: ' . Traceback_uri() . '?mod=' . $this->Name . Traceback_parm_keep(array("show")) . "&item=$uploadTreeId");
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


  protected function htmlContent()
  {
    $uploadTreeId = GetParm("item", PARM_INTEGER);
    if (empty($uploadTreeId))
    {
      return;
    }

    $uploadId = $this->uploadEntry['upload_fk'];
    $uploadTreeTableName = $this->uploadEntry['tablename'];

    $copyrightDecisionMap = $this->decisionTypes->getMap();
    $this->vars['micromenu'] = Dir2Browse($this->modBack, $uploadTreeId, NULL, $showBox = 0, "Clearing", -1, '', '', $uploadTreeTableName);

    $lastItem = GetParm("lastItem", PARM_INTEGER);
    $changed= GetParm("changedSomething", PARM_STRING);
    global $SysConf;
    $userId = $SysConf['auth']['UserId'];
    if (!empty($lastItem) && $changed =="true"  )
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
      $this->vars['message'] = _("No ").$this->tableName ._(" data is available for this file.");
    }

    global $Plugins;
    /** @var ui_view $view */
    $view = &$Plugins[plugin_find_id("view")];
    $theView = $view->getView(null, null, $showHeader=0, "", $highlights, false, true);
    list($pageMenu, $textView)  = $theView;

    list($description,$textFinding,$comment, $decisionType)=$this->copyrightDao->getDecision($this->decisionTableName ,$this->uploadEntry['pfile_fk']);
    $this->vars['description'] =$description;
    $this->vars['textFinding'] =$textFinding;
    $this->vars['comment'] =$comment;

    $this->vars['itemId'] = $uploadTreeId;
    $this->vars['uploadId'] = $uploadId;
    $this->vars['pageMenu'] = $pageMenu;
    $this->vars['textView'] = $textView;
    $this->vars['legendBox'] = $this->legendBox();
    $this->vars['uri'] = Traceback_uri() . "?mod=" . $this->Name;
    $this->vars['optionName'] = $this->optionName;
    $this->vars['formName'] = "CopyRightForm";
    $this->vars['ajaxAction'] = $this->ajaxAction;
    $this->vars['skipOption'] =$this->skipOption;
    $this->vars['selectedClearingType'] = $decisionType;
    $this->vars['clearingTypes'] =$copyrightDecisionMap ;
  }

  /**
   * @return string rendered legend box
   */
  function legendBox()
  {
    $colorMapping = $this->highlightRenderer->colorMapping;

    $output = '';
    $output .= _("file text");

    foreach ($this->hightlightTypeToStringMap
             as $colorKey => $txt)
    {
      $output .= '<br/>' . $this->highlightRenderer->createStyle($colorKey, $txt, $colorMapping) . $txt . '</span>';
    }
    return $output;
  }

  function getTemplateName()
  {
    return 'ui-cp-view.html.twig';
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

    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array("show", "format", "page", "upload", "item"));
    $Item = GetParm("item", PARM_INTEGER);
    $Upload = GetParm("upload", PARM_INTEGER);
    if (!empty($Item) && !empty($Upload))
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
  } // RegisterMenus()
} 