<?php
/*
 SPDX-FileCopyrightText: © 2026 Contribution for GSoC

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browse file dependencies/includers
 */
class ui_file_dependencies extends DefaultPlugin
{
  const NAME = "file_dependencies";

  private $uploadtree_tablename = "";
  /** @var UploadDao */
  private $uploadDao;

  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("File Dependencies"),
        self::DEPENDENCIES => array("browse", "view"),
        self::PERMISSION => Auth::PERM_READ,
        self::REQUIRES_LOGIN => false
    ));

    global $container;
    $this->uploadDao = $container->get('dao.upload');
  }

  /**
   * Register menu entries
   */
  function RegisterMenus()
  {
    $text = _("Dependencies");
    menu_insert("Browse-Pfile::Dependencies", 30, self::NAME, $text);

    // Alright, let's add this to navigation if we have upload and item params
    $URI = $this->getName() . Traceback_parm_keep(array("upload", "item", "show"));

    $Item = GetParm("item", PARM_INTEGER);
    $Upload = GetParm("upload", PARM_INTEGER);
    
    if (empty($Item) || empty($Upload)) {
      return;
    }

    $viewURI = $this->getName() . Traceback_parm_keep(array("show", "format", "page", "upload", "item"));
    $menuName = $this->Title;

    if (GetParm("mod", PARM_STRING) == self::NAME) {
      menu_insert("Browse::$menuName", 95);
      menu_insert("View::$menuName", 95);
      menu_insert("View-Meta::$menuName", 95);
    } else {
      menu_insert("Browse::$menuName", 95, $URI, $text);
      menu_insert("View::$menuName", 95, $viewURI, $text);
      menu_insert("View-Meta::$menuName", 95, $viewURI, $text);
    }
  }

  /**
   * Handle the request
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $upload = intval($request->get("upload"));
    $groupId = Auth::getGroupId();
    
    if (!$this->uploadDao->isAccessible($upload, $groupId)) {
      return $this->flushContent(_("Permission Denied"));
    }

    $item = intval($request->get("item"));
    
    $vars['baseuri'] = Traceback_uri();
    $vars['uploadId'] = $upload;
    $this->uploadtree_tablename = $this->uploadDao->getUploadtreeTableName($upload);
    
    if ($request->get('show')=='quick') {
      $item = $this->uploadDao->getFatItemId($item, $upload, $this->uploadtree_tablename);
    }
    
    $vars['itemId'] = $item;

    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($item, $this->uploadtree_tablename);
    $left = $itemTreeBounds->getLeft();
    
    if (empty($left)) {
      return $this->flushContent(_("Job unpack/adj2nest hasn't completed."));
    }

    $vars['micromenu'] = Dir2Browse($this->getName(), $item, NULL, $showBox = 0, "Browse", -1, '', '', $this->uploadtree_tablename);

    // Count files in directory
    $isFlat = isset($_GET['flatten']);
    $vars['isFlat'] = $isFlat;
    $vars['iTotalRecords'] = $this->uploadDao->countNonArtifactDescendants($itemTreeBounds, $isFlat);
    
    $uri = Traceback_uri().'?mod='.$this->getName().Traceback_parm_keep(array('upload','folder','show','item'));
    $vars['fileSwitch'] = $isFlat ? $uri : $uri."&flatten=yes";

    $vars['content'] = js_url();

    return $this->render("file-dependencies.html.twig", $this->mergeWithDefault($vars));
  }

  /**
   * Render template string
   * @param string $templateName
   * @param array $vars
   * @return string
   */
  public function renderString($templateName, $vars)
  {
    return $this->renderer->load($templateName)->render($vars);
  }
}

register_plugin(new ui_file_dependencies());
