<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;
use Monolog\Handler\BrowserConsoleHandler;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * \class ui_download extends FO_Plugin
 * \brief downlad file(s)
 */
class ui_download extends FO_Plugin
{
  var $NoHTML = 1;

  function __construct()
  {
    $this->Name       = "download";
    $this->Title      = _("Download File");
    $this->Dependency = array();
    $this->DBaccess   = PLUGIN_DB_WRITE;
    parent::__construct();
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    global $SysConf;
    $text = _("Download this file");
    if (array_key_exists(Auth::USER_LEVEL, $_SESSION) &&
      $_SESSION[Auth::USER_LEVEL] >= $SysConf['SYSCONFIG']['SourceCodeDownloadRights']) {
      menu_insert("Browse-Pfile::Download",0,$this->Name,$text);
    }

  } // RegisterMenus()

  /**
   * \brief Called if there is no file.  User is queried if they want
   * to reunpack.
   */
  function CheckRestore($Item, $Filename)
  {
    global $Plugins;

    $this->NoHeader = 0;
    header('Content-type: text/html');
    header("Pragma: no-cache"); /* for IE cache control */
    header('Cache-Control: no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0'); /* prevent HTTP/1.1 caching */
    header('Expires: Expires: Thu, 19 Nov 1981 08:52:00 GMT'); /* mark it as expired (value from Apache default) */

    $V = "";
    if (($this->NoMenu == 0) && ($this->Name != "menus")) {
      $Menu = &$Plugins[plugin_find_id("menus")];
    } else {
      $Menu = null;
    }

    /* DOCTYPE is required for IE to use styles! (else: css menu breaks) */
    $V .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "xhtml1-frameset.dtd">' . "\n";

    $V .= "<html>\n";
    $V .= "<head>\n";
    $V .= "<meta name='description' content='The study of Open Source'>\n";
    if ($this->NoHeader == 0) {
      /** Known bug: DOCTYPE "should" be in the HEADER
       and the HEAD tags should come first.
       Also, IE will ignore <style>...</style> tags that are NOT
       in a <head>...</head>block.
       **/
      if (!empty($this->Title)) {
        $V .= "<title>" . htmlentities($this->Title) . "</title>\n";
      }
      $V .= "<link rel='stylesheet' href='css/fossology.css'>\n";
      if (!empty($Menu)) {
        print $Menu->OutputCSS();
      }
      $V .= "</head>\n";
      $V .= "<body class='text'>\n";
      print $V;
      if (! empty($Menu)) {
        $Menu->Output($this->Title);
      }
    }

    $P = &$Plugins[plugin_find_id("view")];
    $P->ShowView(null, "browse");
    exit();
  }

  function getResponse()
  {
    try {
      $output = $this->getPathAndName();
      list($Filename, $Name) = $output;
      $response = $this->downloadFile($Filename, $Name);
    }
    catch(Exception $e)
    {
      $this->vars['content'] = $e->getMessage();
      $response = $this->render($this->getTemplateName());
    }
    return $response;
  }

  /**
   * \brief This function is called when user output is
   * requested.  This function is responsible for content.
   */
  protected function getPathAndName()
  {
    if ($this->State != \PLUGIN_STATE_READY) {
      throw new Exception('Download plugin is not ready');
    }

    global $SysConf;
    global $container;
    /** @var DbManager $dbManager */
    $dbManager = $container->get('db.manager');
    if (!$dbManager->getDriver()) {
      throw new Exception("Missing database connection.");
    }

    $reportId = GetParm("report",PARM_INTEGER);
    $item = GetParm("item",PARM_INTEGER);
    $logJq = GetParm('log', PARM_INTEGER);

    if (!empty($reportId)) {
      $row = $dbManager->getSingleRow("SELECT * FROM reportgen WHERE job_fk = $1", array($reportId), "reportFileName");
      if ($row === false) {
        throw new Exception("Missing report");
      }
      $path = $row['filepath'];
      $filename = basename($path);
      $uploadId = $row['upload_fk'];
    } elseif (!empty($logJq)) {
      $sql = "SELECT jq_log, job_upload_fk FROM jobqueue LEFT JOIN job ON job.job_pk = jobqueue.jq_job_fk WHERE jobqueue.jq_pk =$1";
      $row = $dbManager->getSingleRow($sql, array($logJq), "jqLogFileName");
      if ($row === false) {
        throw new Exception("Missing report");
      }
      $path = $row['jq_log'];
      $filename = basename($path);
      $uploadId = $row['job_upload_fk'];
    } elseif (empty($item)) {
      throw new Exception("Invalid item parameter");
    } elseif ($_SESSION[Auth::USER_LEVEL] < $SysConf['SYSCONFIG']['SourceCodeDownloadRights']) {
      throw new Exception("User permissions not sufficient for source code download");
    } else {
      $path = RepPathItem($item);
      if (empty($path)) {
        throw new Exception("Invalid item parameter");
      }

      $fileHandle = @fopen( RepPathItem($item) ,"rb");
      /* note that CheckRestore() does not return. */
      if (empty($fileHandle)) {
        $this->CheckRestore($item, $path);
      }

      $row = $dbManager->getSingleRow("SELECT ufile_name, upload_fk FROM uploadtree WHERE uploadtree_pk = $1",array($item));
      if ($row===false) {
        throw new Exception("Missing item");
      }
      $filename = $row['ufile_name'];
      $uploadId = $row['upload_fk'];
    }

    /* @var $uploadDao UploadDao */
    $uploadDao = $GLOBALS['container']->get('dao.upload');
    if (!Auth::isAdmin() && !$uploadDao->isAccessible($uploadId, Auth::getGroupId())) {
      throw new Exception("No Permission: $uploadId");
    }
    if (!file_exists($path)) {
      throw new Exception("File does not exist");
    }
    if (!is_file($path)) {
      throw new Exception("Not a regular file");
    }
    return array($path, $filename);
  }

  /**
   * @global type $container
   * @param string $path
   * @param string $filename
   * @return BinaryFileResponse
   */
  protected function downloadFile($path, $filename)
  {
    global $container;
    $session = $container->get('session');
    $session->save();

    $filenameFallback = str_replace('%','_',$filename);
    $filenameFallback = str_replace('/','_',$filenameFallback);
    $filenameFallback = str_replace('\\','_',$filenameFallback);

    $response = new BinaryFileResponse($path);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename, $filenameFallback);
    $response->headers->set('Content-Type', $response->getFile()->getMimeType());

    $logger = $container->get("logger");
    $logger->pushHandler(new NullHandler(Logger::DEBUG));
    BrowserConsoleHandler::resetStatic();

    return $response;
  }

  /**
   * Get the file response for a given file path
   * @param $path
   * @param $name
   * @return BinaryFileResponse
   */
  public function getDownload($path, $name)
  {
    return $this->downloadFile($path, $name);
  }

  /**
   * Get the file response for a given report job id
   * @param int $jobId
   * @return BinaryFileResponse
   * @throws Exception
   */
  public function getReport($jobId)
  {
    $_GET['report'] = $jobId;
    list($Filename, $Name) = $this->getPathAndName();
    return $this->downloadFile($Filename, $Name);
  }
}

$NewPlugin = new ui_download();
$NewPlugin->Initialize();
