<?php
/*
 SPDX-FileCopyrightText: © 2026 Contribution for GSoC

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Ajax;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Proxy\UploadTreeProxy;
use Fossology\Lib\Util\FileIncluderExtractor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ajax handler for file dependencies browser
 */
class AjaxFileDependencies extends DefaultPlugin
{
  const NAME = "ajax_file_dependencies";

  private $uploadtree_tablename = "";
  /** @var UploadDao */
  private $uploadDao;
  /** @var FileIncluderExtractor */
  private $includerExtractor;

  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Ajax: File Dependencies"),
        self::DEPENDENCIES => array("file_dependencies"),
        self::PERMISSION => Auth::PERM_READ,
        self::REQUIRES_LOGIN => false
    ));

    $this->uploadDao = $this->getObject('dao.upload');
    $this->includerExtractor = new FileIncluderExtractor();
  }

  /**
   * Handle Ajax request
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $upload = intval($request->get("upload"));
    $groupId = Auth::getGroupId();
    
    if (!$this->uploadDao->isAccessible($upload, $groupId)) {
      throw new \Exception("Permission Denied");
    }

    $item = intval($request->get("item"));
    $this->uploadtree_tablename = $this->uploadDao->getUploadtreeTableName($upload);
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($item, $this->uploadtree_tablename);
    $left = $itemTreeBounds->getLeft();
    
    if (empty($left)) {
       throw new \Exception("Job unpack/adj2nest hasn't completed.");
    }

    $vars = $this->createFileListing($itemTreeBounds, $request);

    return new JsonResponse(array(
            'sEcho' => intval($request->get('sEcho')),
            'aaData' => $vars['fileData'],
            'iTotalRecords' => intval($request->get('totalRecords')),
            'iTotalDisplayRecords' => $vars['iTotalDisplayRecords']
          ) );
  }

  /**
   * Create file listing with dependencies
   * @param ItemTreeBounds $itemTreeBounds
   * @param Request $request
   * @return array
   */
  private function createFileListing($itemTreeBounds, $request)
  {
    $uploadId = $itemTreeBounds->getUploadId();
    $isFlat = isset($_GET['flatten']);

    if ($isFlat) {
      $options = array(UploadTreeProxy::OPT_RANGE => $itemTreeBounds);
    } else {
      $options = array(UploadTreeProxy::OPT_REALPARENT => $itemTreeBounds->getItemId());
    }

    // Parse search parameters from DataTables
    $searchMap = array();
    foreach (explode(' ', $request->get('sSearch')) as $pair) {
      $a = explode(':', $pair);
      if (count($a) == 1) {
        $searchMap['head'] = $pair;
      } else {
        $searchMap[$a[0]] = $a[1];
      }
    }

    if (array_key_exists('ext', $searchMap) && strlen($searchMap['ext'])>=1) {
      $options[UploadTreeProxy::OPT_EXT] = $searchMap['ext'];
    }
    if (array_key_exists('head', $searchMap) && strlen($searchMap['head'])>=1) {
      $options[UploadTreeProxy::OPT_HEAD] = $searchMap['head'];
    }

    $descendantView = new UploadTreeProxy($uploadId, $options, $itemTreeBounds->getUploadTreeTableName(), 'uberItems');

    $vars['iTotalDisplayRecords'] = $descendantView->count();

    $columnNamesInDatabase = array($isFlat ? 'ufile_name' : 'lft');
    $defaultOrder = array(array(0, "asc"));
    $orderString = $this->getObject('utils.data_tables_utility')->getSortingString($_GET, $columnNamesInDatabase, $defaultOrder);

    $offset = GetParm('iDisplayStart', PARM_INTEGER);
    $limit = GetParm('iDisplayLength', PARM_INTEGER);
    
    if ($offset) {
      $orderString .= " OFFSET $offset";
    }
    if ($limit) {
      $orderString .= " LIMIT $limit";
    }

    // Get files from database
    $sql = $descendantView->getDbViewQuery()." $orderString";
    $dbManager = $this->getObject('db.manager');

    $dbManager->prepare($stmt=__METHOD__.$orderString, $sql);
    $res = $dbManager->execute($stmt, $descendantView->getParams());
    $descendants = $dbManager->fetchAll($res);
    $dbManager->freeResult($res);

    if (empty($descendants)) {
      $vars['fileData'] = array();
      return $vars;
    }

    // Process each file and extract its dependencies
    $tableData = array();
    foreach ($descendants as $child) {
      if (empty($child)) {
        continue;
      }
      $tableData[] = $this->createFileDataRow($child, $uploadId, $isFlat);
    }

    $vars['fileData'] = $tableData;
    return $vars;
  }

  /**
   * Create a single row of file data with dependencies
   * @param array $child
   * @param int $uploadId
   * @param boolean $isFlat
   * @return array
   */
  private function createFileDataRow($child, $uploadId, $isFlat)
  {
    $fileId = $child['pfile_fk'];
    $childUploadTreeId = $child['uploadtree_pk'];
    $rawFileName = $child['ufile_name'];
    $fileName = htmlspecialchars($rawFileName);

    // Check if this is a directory or actual file
    $isContainer = Iscontainer($child['ufile_mode']);

    if ($isContainer) {
      $linkUri = Traceback_uri()."?mod=file_dependencies&upload=$uploadId&item=$childUploadTreeId";
      $fileName = "<a href='$linkUri'><span style='color: darkblue'> <b>$fileName</b> </span></a>";
      $dependencies = "<i>directory</i>";
    } else {
      // Parse the actual file to find dependencies
      if (!empty($fileId)) {
        $dependencies = $this->extractFileDependencies($fileId, $rawFileName);
      } else {
        $dependencies = "";
      }
    }

    return array($fileName, $dependencies);
  }

  /**
   * Extract dependencies from a file
   * @param int $pfileId
   * @param string $filename
   * @return string HTML formatted dependencies
   */
  private function extractFileDependencies($pfileId, $filename)
  {
    // Get file content from repository
    $dbManager = $this->getObject('db.manager');
    
    $sql = "SELECT pfile_sha1, pfile_size FROM pfile WHERE pfile_pk = $1";
    $result = $dbManager->getSingleRow($sql, array($pfileId), __METHOD__);
    
    if (empty($result)) {
      return "";
    }

    $pfileSha1 = $result['pfile_sha1'];
    $pfileSize = $result['pfile_size'];
    
    // Only process files under 1MB to avoid performance issues
    if ($pfileSize > 1048576) {
      return "<i>file too large</i>";
    }

    // Construct repository path from SHA1 hash
    // FOSSology stores files as: /srv/fossology/repository/localhost/gold/XX/YY/ZZ/HASH.*
    // Files have timestamp extensions like: HASH.sha1.12345.67890
    $hash = $pfileSha1;
    $path1 = substr($hash, 0, 2);
    $path2 = substr($hash, 2, 2);
    $path3 = substr($hash, 4, 2);
    
    $repoPath = '/srv/fossology/repository/localhost/gold';
    $dirPath = "$repoPath/$path1/$path2/$path3";
    
    // Use glob to find file with any extensions
    $pattern = "$dirPath/$hash*";
    $files = glob($pattern);
    
    if (empty($files)) {
      return "";
    }
    
    $content = $files[0]; // Use first match

    
    // Read file content - files are gzip compressed even without extension
    $fileContent = @file_get_contents("compress.zlib://$content");
    
    if ($fileContent === false) {
      return "";
    }

    // Use our extractor
    $includers = $this->includerExtractor->extractIncluders($fileContent, $filename);

    if (empty($includers)) {
      return "<i>none found</i>";
    }

    // Format as HTML list
    $output = [];
    foreach ($includers as $inc) {
      $type = htmlspecialchars($inc['type']);
      $value = htmlspecialchars($inc['value']);
      $line = $inc['line'];
      
      $output[] = "<span title='Line $line'><code>$value</code> ($type)</span>";
    }

    return implode(", ", $output);
  }
}

register_plugin(new AjaxFileDependencies());
