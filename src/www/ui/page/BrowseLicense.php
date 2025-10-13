<?php
/*
 SPDX-FileCopyrightText: © 2008-2015 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\BusinessRules\ClearingDecisionFilter;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\TreeDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\ClearingDecision;
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

class BrowseLicense extends DefaultPlugin
{
  const NAME = "license";

  private $uploadtree_tablename = "";
  /** @var UploadDao */
  private $uploadDao;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var AgentDao */
  private $agentDao;
  /** @var ClearingDecisionFilter */
  private $clearingFilter;
  /** @var LicenseMap */
  private $licenseProjector;
  /** @var array */
  protected $agentNames = AgentRef::AGENT_LIST;

  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("License Browser"),
        self::DEPENDENCIES => array("browse", "view"),
        self::PERMISSION => Auth::PERM_READ,
        self::REQUIRES_LOGIN => false
    ));

    global $container;
    $this->uploadDao = $container->get('dao.upload');
    $this->licenseDao = $container->get('dao.license');
    $this->clearingDao = $container->get('dao.clearing');
    $this->agentDao = $container->get('dao.agent');
    $this->clearingFilter = $container->get('businessrules.clearing_decision_filter');
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array("upload", "item"));

    $Item = GetParm("item", PARM_INTEGER);
    $Upload = GetParm("upload", PARM_INTEGER);
    if (empty($Item) || empty($Upload)) {
      return;
    }
    $viewLicenseURI = $this->NAME . Traceback_parm_keep(array("show", "format", "page", "upload", "item"));
    $menuName = $this->Title;

    $uploadTreeTable = $this->uploadDao->getUploadtreeTableName($Upload);
    $itemBounds = $this->uploadDao->getItemTreeBounds($Item, $uploadTreeTable);
    if (! $itemBounds->containsFiles()) {
      global $container;
      /**
       * @var TreeDao $treeDao Tree dao object
       */
      $treeDao = $container->get('dao.tree');
      $parent = $treeDao->getParentOfItem($itemBounds);
      $viewLicenseURI = $this->NAME . Traceback_parm_keep(array("show",
        "format", "page", "upload")) . "&item=$parent";
    }
    if (GetParm("mod", PARM_STRING) == $this->NAME) {
      menu_insert("Browse::$menuName", 99);
      menu_insert("View::$menuName", 99);
      menu_insert("View-Meta::$menuName", 99);
    } else {
      $text = _("license histogram");
      menu_insert("Browse::$menuName", 99, $URI, $text);
      menu_insert("View::$menuName", 99, $viewLicenseURI, $text);
      menu_insert("View-Meta::$menuName", 99, $viewLicenseURI, $text);
    }
  }

  /**
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
    $this->uploadtree_tablename = $this->uploadDao->getUploadtreeTableName($upload);
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($item, $this->uploadtree_tablename);
    $left = $itemTreeBounds->getLeft();
    if (empty($left)) {
      return $this->flushContent(_("Job unpack/adj2nest hasn't completed."));
    }

    $vars['baseuri'] = Traceback_uri();
    $vars['uploadId'] = $upload;
    if ($request->get('show')=='quick') {
      $item = $this->uploadDao->getFatItemId($item,$upload,$this->uploadtree_tablename);
    }
    $vars['itemId'] = $item;

    $vars['micromenu'] = Dir2Browse($this->Name, $item, NULL, $showBox = 0, "Browse",
      -1, '', '', $this->uploadtree_tablename);
    $vars['licenseArray'] = $this->licenseDao->getLicenseArray();

    $histVars = $this->showUploadHist($itemTreeBounds);
    if (is_a($histVars, 'Symfony\\Component\\HttpFoundation\\RedirectResponse')) {
      return $histVars;
    }
    $vars = array_merge($vars, $histVars);

    $vars['content'] = js_url();

    return $this->render("browse.html.twig",$this->mergeWithDefault($vars));
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

    $vars = array('agentId' => GetParm('agentId', PARM_INTEGER),
                  'agentShowURI' => Traceback_uri() . '?mod=' . Traceback_parm(),
                  'agentMap' => $agentMap,
                  'scanners'=>$scannerVars);

    $selectedAgentIds = empty($selectedAgentId) ? $scanJobProxy->getLatestSuccessfulAgentIds() : $selectedAgentId;

    if (!empty($agentMap)) {
      $licVars = $this->createLicenseHistogram($itemTreeBounds->getItemId(),
        $tag_pk, $itemTreeBounds, $selectedAgentIds, $groupId);
      $vars = array_merge($vars, $licVars);
    }

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
   * @param $uploadTreeId
   * @param $tagId
   * @param ItemTreeBounds $itemTreeBounds
   * @param int|int[] $agentIds
   * @param ClearingDecision []
   * @return array
   */
  public function createLicenseHistogram($uploadTreeId, $tagId, ItemTreeBounds $itemTreeBounds, $agentIds, $groupId)
  {
    $fileCount = $this->uploadDao->countPlainFiles($itemTreeBounds);
    $licenseHistogram = $this->licenseDao->getLicenseHistogram($itemTreeBounds, $agentIds);
    $editedLicensesHist = $this->clearingDao->getClearedLicenseIdAndMultiplicities($itemTreeBounds, $groupId);
    $mainLicenseIds = $this->clearingDao->getMainLicenseIds($itemTreeBounds->getUploadId(), $groupId);

    $agentId = GetParm('agentId', PARM_INTEGER);
    $licListUri = Traceback_uri()."?mod=license_list_files&item=$uploadTreeId";
    if ($tagId) {
      $licListUri .= "&tag=$tagId";
    }
    if ($agentId) {
      $licListUri .= "&agentId=$agentId";
    }

    /* Write license histogram to $VLic  */
    list($tableData, $totalScannerLicenseCount, $editedTotalLicenseCount)
        = $this->createLicenseHistogramJSarray($licenseHistogram, $editedLicensesHist, $licListUri, $mainLicenseIds);

    $uniqueLicenseCount = count($tableData);
    $scannerUniqueLicenseCount = count( $licenseHistogram );
    $editedUniqueLicenseCount = count($editedLicensesHist);
    $noScannerLicenseFoundCount = array_key_exists(LicenseDao::NO_LICENSE_FOUND, $licenseHistogram)
            ? $licenseHistogram[LicenseDao::NO_LICENSE_FOUND]['count'] : 0;
    $editedNoLicenseFoundCount = array_key_exists(LicenseDao::NO_LICENSE_FOUND, $editedLicensesHist)
            ? $editedLicensesHist[LicenseDao::NO_LICENSE_FOUND]['count'] : 0;

    return array('tableDataJson'=>json_encode($tableData),
        'uniqueLicenseCount'=>$uniqueLicenseCount,
        'fileCount'=>$fileCount,
        'scannerUniqueLicenseCount'=>$scannerUniqueLicenseCount,
        'editedUniqueLicenseCount'=>$editedUniqueLicenseCount,
        'scannerLicenseCount'=> $totalScannerLicenseCount,
        'editedLicenseCount'=> $editedTotalLicenseCount-$editedNoLicenseFoundCount,
        'noScannerLicenseFoundCount'=>$noScannerLicenseFoundCount,
        'editedNoLicenseFoundCount'=>$editedNoLicenseFoundCount,
        'scannerLicenses'=>$licenseHistogram,
        'editedLicenses'=>$editedLicensesHist
        );
  }

  /**
   * @param array $scannerLics
   * @param array $editedLics
   * @param string
   * @return array
   * @todo convert to template
   */
  protected function createLicenseHistogramJSarray($scannerLics, $editedLics, $licListUri, $mainLicenseIds)
  {
    $allScannerLicenseNames = array_keys($scannerLics);
    $allEditedLicenseNames = array_keys($editedLics);

    $allLicNames = array_unique(array_merge($allScannerLicenseNames, $allEditedLicenseNames));
    $realLicNames = array_diff($allLicNames, array(LicenseDao::NO_LICENSE_FOUND));

    $totalScannerLicenseCount = 0;
    $editedTotalLicenseCount = 0;

    $tableData = array();
    foreach ($realLicNames as $licenseShortName) {
      $count = 0;
      if (array_key_exists($licenseShortName, $scannerLics)) {
        $count = $scannerLics[$licenseShortName]['unique'];
        $rfId = $scannerLics[$licenseShortName]['rf_pk'];
      } else {
        $rfId = $editedLics[$licenseShortName]['rf_pk'];
      }
      $editedCount = array_key_exists($licenseShortName, $editedLics) ? $editedLics[$licenseShortName]['count'] : 0;

      $totalScannerLicenseCount += $count;
      $editedTotalLicenseCount += $editedCount;

      $scannerCountLink = ($count > 0) ? "<a href='$licListUri&lic=" .
          urlencode($licenseShortName) . "'>$count</a>": "0";
      $editedLink = ($editedCount > 0) ? $editedCount : "0";

      $isMain = in_array($rfId, $mainLicenseIds);
      $tableData[] = array($scannerCountLink, $editedLink, array($licenseShortName,$rfId,$isMain));
    }

    return array($tableData, $totalScannerLicenseCount, $editedTotalLicenseCount);
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

  /**
   * Get the upload histogram generated
   * @param ItemTreeBounds $itemTreeBounds
   * @return array
   */
  public function getUploadHist($itemTreeBounds)
  {
    return $this->showUploadHist($itemTreeBounds);
  }
}

register_plugin(new BrowseLicense());
