<?php
/*
 SPDX-FileCopyrightText: © 2019 Sandip Kumar Bhuyan <sandipbhuyan@gmail.com>
 Author: Sandip Kumar Bhuyan<sandipbhyan@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\SoftwareHeritage\UI;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use \Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Proxy\ScanJobProxy;
use Symfony\Component\HttpFoundation\RedirectResponse;
use \Symfony\Component\HttpFoundation\Request;
use Fossology\Lib\Dao\SoftwareHeritageDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;


class softwareHeritagePlugin extends DefaultPlugin
{
  const NAME = "sh-agent";  ///< UI mod name

  public $AgentName = "agent_softwareHeritage";

  private $uploadtree_tablename = "";

  /** @var UploadDao $uploadDao
   * UploadDao object
   */
  private $uploadDao;

  /**
   * @var DbManager $dbManeger
   * DbManeger object
   */
  private $dbManeger;

  /**
   * @var SoftwareHeritageDao $shDao
   * SoftwareHeritageDao object
   */
  private $shDao;

  /** @var AgentDao */
  private $agentDao;

  /** @var array */
  protected $agentNames = array('softwareHeritage' => 'SH');

  public function __construct()
  {
    parent::__construct(self::NAME, array(
      self::TITLE => _("Software Heritage details"),
      self::PERMISSION => Auth::PERM_READ,
      self::REQUIRES_LOGIN => false
    ));
    $this->Title = _("Software Heritage");
    $this->dbManeger = $this->container->get('db.manager');
    $this->uploadDao = $this->container->get('dao.upload');
    $this->shDao = $this->container->get('dao.softwareHeritage');
    $this->agentDao = $this->container->get('dao.agent');
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array("upload", "item", "show")) . "&flatten=yes";

    $Item = GetParm("item", PARM_INTEGER);
    $Upload = GetParm("upload", PARM_INTEGER);

    if (empty($Item) || empty($Upload)) {
      return;
    }
    $viewLicenseURI = "view-license" . Traceback_parm_keep(array("show", "format", "page", "upload", "item"));
    $menuName = $this->Title;
    if (GetParm("mod", PARM_STRING) == self::NAME) {
      menu_insert("Browse::$menuName", 101);
      menu_insert("View::$menuName", 101);
      menu_insert("View-Meta::$menuName", 101);
    } else {
      $text = _("Software Heritage");
      menu_insert("Browse::$menuName", 101, $URI, $text);
      menu_insert("View::$menuName", 101, $viewLicenseURI, $text);
      menu_insert("View-Meta::$menuName", 101, $viewLicenseURI, $text);
    }
  }

  public function handle(Request $request)
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
      $item = $this->uploadDao->getFatItemId($item,$upload,$this->uploadtree_tablename);
    }
    $vars['itemId'] = $item;
    $vars['micromenu'] = Dir2Browse($this->Name, $item, NULL, $showBox = 0, "Browse", -1,
    '', '', $this->uploadtree_tablename);
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($item, $this->uploadtree_tablename);
    $left = $itemTreeBounds->getLeft();
    if (empty($left)) {
      return $this->flushContent(_("Job unpack/adj2nest hasn't completed."));
    }
    $histVars = $this->showUploadHist($itemTreeBounds);
    if (is_a($histVars, 'Symfony\\Component\\HttpFoundation\\RedirectResponse')) {
      return $histVars;
    }
    $vars = array_merge($vars, $histVars);
    return $this->render("softwareHeritage.html.twig",$this->mergeWithDefault($vars));
  }

  /**
   * \brief Given an $Uploadtree_pk, display:
   *   - The histogram for the directory BY LICENSE.
   *   - The file listing for the directory.
   */
  private function showUploadHist(ItemTreeBounds $itemTreeBounds)
  {
    $groupId = Auth::getGroupId();
    $agentId = $this->agentDao->getCurrentAgentId("softwareHeritage");

    $uploadId = $itemTreeBounds->getUploadId();
    $scannerAgents = array_keys($this->agentNames);
    $scanJobProxy = new ScanJobProxy($this->agentDao, $uploadId);
    $scannerVars = $scanJobProxy->createAgentStatus($scannerAgents);
    $agentMap = $scanJobProxy->getAgentMap();

    $vars = array(
      'agentId' => $agentId,
      'agentMap' => $agentMap,
      'scanners'=>$scannerVars
    );

    $selectedAgentIds = empty($selectedAgentId) ? $scanJobProxy->getLatestSuccessfulAgentIds() : $agentId;

    $this->licenseProjector = new LicenseMap($this->getObject('db.manager'),$groupId,LicenseMap::CONCLUSION,true);
    $dirVars = $this->countFileListing($itemTreeBounds);
    $childCount = $dirVars['iTotalRecords'];
    /***************************************
     * Problem: $ChildCount can be zero if you have a container that does not
     * unpack to a directory.  For example:
     * file.gz extracts to archive.txt that contains a license.
     * Same problem seen with .pdf and .Z files.
     * Solution: if $ChildCount == 0, then just view the license!
     *
     * $ChildCount can also be zero if the directory is empty.
     * **************************************/
    if ($childCount == 0) {
      return new RedirectResponse("?mod=view-license" . Traceback_parm_keep(array("upload", "item")));
    }
    return array_merge($vars, $dirVars);
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @return array with keys 'isFlat','iTotalRecords','fileSwitch'
   */
  private function countFileListing(ItemTreeBounds $itemTreeBounds)
  {
    $isFlat = isset($_GET['flatten']);
    $vars['isFlat'] = $isFlat;
    $vars['iTotalRecords'] = $this->uploadDao->countNonArtifactDescendants($itemTreeBounds, $isFlat);
    $uri = Traceback_uri().'?mod='.$this->Name.Traceback_parm_keep(array('upload','folder','show','item'));
    $vars['fileSwitch'] = $isFlat ? $uri : $uri."&flatten=yes";
    return $vars;
  }

  /**
   * @param string $templateName
   * @param array $vars
   * @return string
   */
  public function renderString($templateName, $vars)
  {
    return $this->renderer->load($templateName)->render($vars);
  }
}
register_plugin(new softwareHeritagePlugin());
