<?php
/***********************************************************
 * Copyright (C) 2008-2015 Hewlett-Packard Development Company, L.P.
 *               2014-2015 Siemens AG
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

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Proxy\ScanJobProxy;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \file ui-browse-license.php
 * \brief browse a directory to display all licenses in this directory
 */

class ui_file_browse extends DefaultPlugin
{
  const NAME = "fileBrowse";
  
  private $uploadtree_tablename = "";
  /** @var UploadDao */
  private $uploadDao;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var AgentDao */
  private $agentDao;
  /** @var LicenseMap */
  private $licenseProjector;
  /** @var array */
  protected $agentNames = array('nomos' => 'N', 'monk' => 'M', 'ninka' => 'Nk', 'reportImport' => 'I');
  
  public function __construct() {
    parent::__construct(self::NAME, array(
        self::TITLE => _("File Browser"),
        self::DEPENDENCIES => array("browse", "view"),
        self::PERMISSION => Auth::PERM_READ
    ));

    global $container;
    $this->uploadDao = $container->get('dao.upload');
    $this->licenseDao = $container->get('dao.license');
    $this->agentDao = $container->get('dao.agent');
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $text = _("File Browser");
    menu_insert("Browse-Pfile::File Browser", 20, 'fileBrowse', $text);

    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array("upload", "item", "show"));

    $Item = GetParm("item", PARM_INTEGER);
    $Upload = GetParm("upload", PARM_INTEGER);
    if (empty($Item) || empty($Upload))
      return;
    $viewLicenseURI = "view-license" . Traceback_parm_keep(array("show", "format", "page", "upload", "item"));
    $menuName = $this->Title;
    if (GetParm("mod", PARM_STRING) == self::NAME)
    {
      menu_insert("Browse::$menuName", 98);
      menu_insert("View::$menuName", 98);
      menu_insert("View-Meta::$menuName", 98); 
    }
    else
    {
      $text = _("File Browser");
      menu_insert("Browse::$menuName", 98, $URI, $text);
      menu_insert("View::$menuName", 98, $viewLicenseURI, $text);
      menu_insert("View-Meta::$menuName", 98, $viewLicenseURI, $text);
    }
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request) {
    $upload = intval($request->get("upload"));
    $groupId = Auth::getGroupId();
    if (!$this->uploadDao->isAccessible($upload, $groupId))
    {
      return $this->flushContent(_("Permission Denied"));
    }

    $item = intval($request->get("item"));

    $vars['baseuri'] = Traceback_uri();
    $vars['uploadId'] = $upload;
    $this->uploadtree_tablename = $this->uploadDao->getUploadtreeTableName($upload);
    if($request->get('show')=='quick')
    {
      $item = $this->uploadDao->getFatItemId($item,$upload,$this->uploadtree_tablename);
    }
    $vars['itemId'] = $item;    

    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($item, $this->uploadtree_tablename);
    $left = $itemTreeBounds->getLeft();
    if (empty($left))
    {
      return $this->flushContent(_("Job unpack/adj2nest hasn't completed."));
    }
    $histVars = $this->showUploadHist($itemTreeBounds);
    if(is_a($histVars, 'Symfony\\Component\\HttpFoundation\\RedirectResponse'))
    {
      return $histVars;
    }
    $vars = array_merge($vars, $histVars);
 
    $vars['micromenu'] = Dir2Browse($this->Name, $item, NULL, $showBox = 0, "Browse", -1, '', '', $this->uploadtree_tablename);

    $allLicensesPre = $this->licenseDao->getLicenseArray();
    $allLicenses = array();
    foreach ($allLicensesPre as $value)
    {
      $allLicenses[$value['shortname']] = array('rf_pk' => $value['id']);
    }
    $vars['scannerLicenses'] = $allLicenses;

    $vars['content'] = js_url();

    return $this->render("file-browse.html.twig",$this->mergeWithDefault($vars));
  }


  /**
   * \brief Given an $Uploadtree_pk, display:
   *   - The histogram for the directory BY LICENSE.
   *   - The file listing for the directory.
   */
  private function showUploadHist(ItemTreeBounds $itemTreeBounds)
  {
    $groupId = Auth::getGroupId();
    $selectedAgentId = GetParm('agentId', PARM_INTEGER);
    $tag_pk = GetParm("tag", PARM_INTEGER);

    $uploadId = $itemTreeBounds->getUploadId();
    $scannerAgents = array_keys($this->agentNames);
    $scanJobProxy = new ScanJobProxy($this->agentDao, $uploadId);
    $scannerVars = $scanJobProxy->createAgentStatus($scannerAgents);
    $agentMap = $scanJobProxy->getAgentMap();
    
    $vars = array('agentId' => $selectedAgentId,
                  'agentMap' => $agentMap,
                  'scanners'=>$scannerVars);

    $selectedAgentIds = empty($selectedAgentId) ? $scanJobProxy->getLatestSuccessfulAgentIds() : $selectedAgentId;

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
    if ($childCount == 0)
    {
      return new RedirectResponse("?mod=view-license" . Traceback_parm_keep(array("upload", "item")));
    }

    $vars['licenseUri'] = Traceback_uri() . "?mod=popup-license&rf=";
    $vars['bulkUri'] = Traceback_uri() . "?mod=popup-license";

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
    return $this->renderer->loadTemplate($templateName)->render($vars);
  }  
}

register_plugin(new ui_file_browse());
