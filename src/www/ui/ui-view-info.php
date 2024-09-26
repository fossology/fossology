<?php
/*
 SPDX-FileCopyrightText: © 2008-2014 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015, 2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;

class ui_view_info extends FO_Plugin
{
  /**
   * @var UploadDao
   */
  private $uploadDao;
  /**
   * @var DbManager
   */
  private $dbManager;
  /**
   * @var UserDao $userDao
   * User DAO to use
   */
  private $userDao;

  function __construct()
  {
    $this->Name    = "view_info";
    $this->Title   = _("View File Information");
    $this->Dependency = array("browse");
    $this->DBaccess  = PLUGIN_DB_READ;
    $this->LoginFlag = 0;
    parent::__construct();
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
    $this->dbManager = $GLOBALS['container']->get('db.manager');
    $this->userDao = $GLOBALS['container']->get('dao.user');
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $tooltipText = _("View file information");
    menu_insert("Browse-Pfile::Info", 5, $this->Name, $tooltipText);
    // For the Browse menu, permit switching between detail and summary.
    $Parm = Traceback_parm_keep(array("upload","item","format"));
    $URI = $this->Name . $Parm;

    $menuPosition = 60;
    $menuText = "Info";
    if (GetParm("mod", PARM_STRING) == $this->Name) {
      menu_insert("View::[BREAK]", 61);
      menu_insert("View::[BREAK]", 50);
      menu_insert("View::{$menuText}", $menuPosition);
      menu_insert("View-Meta::[BREAK]", 61);
      menu_insert("View-Meta::[BREAK]", 50);
      menu_insert("View-Meta::{$menuText}", $menuPosition);

      menu_insert("Browse::Info", -3);
    } else {
      $tooltipText = _("View information about this file");
      menu_insert("View::[BREAK]", 61);
      menu_insert("View::[BREAK]", 50);
      menu_insert("View::{$menuText}", $menuPosition, $URI, $tooltipText);
      menu_insert("View-Meta::[BREAK]", 61);
      menu_insert("View-Meta::[BREAK]", 50);
      menu_insert("View-Meta::{$menuText}", $menuPosition, $URI, $tooltipText);

      menu_insert("Browse::Info", -3, $URI, $tooltipText);
    }
  } // RegisterMenus()

  /**
   * \brief Display the info data associated with the file.
   */
  function ShowView($Upload, $Item, $ShowMenu = 0)
  {
    $vars = [];
    if (empty($Upload) || empty($Item)) {
      return;
    }

    $Page = GetParm("page", PARM_INTEGER);
    if (empty($Page)) {
      $Page = 0;
    }
    $vars['repoLocPage'] = $Page;

    /**
     * ********************************
     * List File Info
     * ********************************
     */
    if ($Page == 0) {
      $sql = "SELECT * FROM uploadtree
    INNER JOIN pfile ON uploadtree_pk = $1
    AND pfile_fk = pfile_pk
    LIMIT 1;";
      $row = $this->dbManager->getSingleRow(
        $sql,
        array($Item),
        __METHOD__ . "GetFileDescribingRow"
      );
      $bytes = $row['pfile_size'];
      $bytesH = HumanSize($bytes);
      $bytes = number_format($bytes, 0, "", ",") . ' B';
      if ($bytesH == $bytes) {
        $bytesH = "";
      } else {
        $bytesH = '(' . $bytesH . ')';
      }
      $vars['sizeInBytes'] = $bytes;
      $vars['sizeInMB'] = $bytesH;
      $vars['fileSha1'] = $row['pfile_sha1'];
      $vars['fileMd5'] = $row['pfile_md5'];
      $vars['fileSha256'] = $row['pfile_sha256'];
      $vars['fileSize'] = $row['pfile_size'];
      $vars['filePfileId'] = $row['pfile_fk'];
    }
    return $vars;
  } // ShowView()

  /**
   * \brief Show Sightings, List the directory locations where this pfile is found
   */
  function ShowSightings($Upload, $Item)
  {
    $v = "";
    if (empty($Upload) || empty($Item)) {
      return $vars;
    }

    $page = GetParm("page", PARM_INTEGER);
    if (empty($page)) {
      $page = 0;
    }
    $MAX = 50;
    $offset = $page * $MAX;
    /**********************************
     List the directory locations where this pfile is found
     **********************************/
    $sql = "SELECT * FROM pfile,uploadtree
    WHERE pfile_pk=pfile_fk
    AND pfile_pk IN
    (SELECT pfile_fk FROM uploadtree WHERE uploadtree_pk = $1)
    LIMIT $2 OFFSET $3";
    $this->dbManager->prepare(__METHOD__ . "getListOfFiles", $sql);
    $result = $this->dbManager->execute(__METHOD__ . "getListOfFiles", array($Item,$MAX,$offset));
    $count = pg_num_rows($result);
    if (($page > 0) || ($count >= $MAX)) {
      $vMenu = "<p>\n" . MenuEndlessPage($page, ($count >= $MAX)) . "</p>\n";
    } else {
      $vMenu = "";
    }
    if ($count > 0) {
      $v .= _("This exact file appears in the following locations:\n");
      $v .= $vMenu;
      $offset++;
      $v .= Dir2FileList($result, "browse", "view", $offset);
      $v .= $vMenu;
    } elseif ($page > 0) {
      $v .= _("End of listing.\n");
    } else {
      $v .= _("This file does not appear in any other known location.\n");
    }
    pg_free_result($result);

    $vars = [];
    $vars['sightingsContent'] = $v;
    return $vars;
  } //ShowSightings()



  /**
   * \brief Display the meta data associated with the file.
   */
  function ShowMetaView($Upload, $Item)
  {
    $groupId = Auth::getGroupId();
    $vars = [];
    if (empty($Item) || empty($Upload)) {
      return $vars;
    }

    /* display mimetype */
    $sql = "SELECT * FROM uploadtree where uploadtree_pk = $1";
    $this->dbManager->prepare(__METHOD__ . "DisplayMimetype", $sql);
    $result = $this->dbManager->execute(__METHOD__ . "DisplayMimetype", array($Item));
    if (pg_num_rows($result)) {
      $vars['fileInfo'] = 1;
      $row = pg_fetch_assoc($result);

      if (! empty($row['mimetype_pk'])) {
        $vars['displayMimeTypeName'] = $row['mimetype_name'];
      }
    } else {
      // bad uploadtree_pk
      $vars['fileInfo'] = 0;
      return $vars;
    }
    $this->dbManager->freeResult($result);

    /* get mimetype */
    if (! empty($row['pfile_fk'])) {
      $sql = "select mimetype_name from pfile, mimetype where pfile_pk = $1 and pfile_mimetypefk=mimetype_pk";
      $this->dbManager->prepare(__METHOD__ . "GetMimetype", $sql);
      $result = $this->dbManager->execute(
        __METHOD__ . "GetMimetype",
        array($row['pfile_fk'])
      );
      if (pg_num_rows($result)) {
        $pmRow = pg_fetch_assoc($result);
        $vars['getMimeTypeName'] = $pmRow['mimetype_name'];
      }
      $this->dbManager->freeResult($result);
    }
      $pmRow = [];
      // Check if ScanOSS is enabled
      $sql = "SELECT agent_enabled FROM agent WHERE agent_name ='scanoss' ORDER BY agent_ts LIMIT 1;";
      $row = $this->dbManager->getSingleRow($sql, [],
        __METHOD__ . "checkScanOss");
      if (!empty($row) && $row["agent_enabled"] == 't') {
        $sql = "SELECT s.purl, s.matchtype, s.lineranges, s.url, s.filepath " . 
               "FROM scanoss_fileinfo s, uploadtree u where u.uploadtree_pk = $1 and  s.pfile_fk = u.pfile_fk;";
        $pmRow = $this->dbManager->getSingleRow($sql, [$Item],
          __METHOD__ . "GetFileMatchInfo");
      }
      if (!empty($pmRow)) {
        $vars['purl'] = $pmRow['purl'];
        $vars['matchType'] = $pmRow['matchtype'];
        $vars['lineRange'] = $pmRow['lineranges'];
        $vars['url'] = $pmRow['url'];
        $vars['path'] = $pmRow['filepath'];
        $vars['scanossInfo'] = 1;
      } else {
        $vars['scanossInfo'] = 0;
      }
    /* display upload origin */
    $sql = "select * from upload where upload_pk=$1";
    $row = $this->dbManager->getSingleRow(
      $sql,
      array($row['upload_fk']),
      __METHOD__ . "getUploadOrigin"
    );
    if ($row) {
      /* upload source */
      if ($row['upload_mode'] & 1 << 2) {
        $text = _("Added by URL");
      } elseif ($row['upload_mode'] & 1 << 3) {
        $text = _("Added by file upload");
      } elseif ($row['upload_mode'] & 1 << 4) {
        $text = _("Added from filesystem");
      }
      $vars['fileUploadOriginInfo'] = $text;
      $vars['fileUploadOrigin'] = $row['upload_origin'];

      /* upload time */
      $ts = $row['upload_ts'];
      $vars['fileUploadDate'] = substr($ts, 0, strrpos($ts, '.'));
    }
    /* display where it was uploaded from */

    /* display upload owner*/
    $sql = "SELECT user_name from users, upload where user_pk = user_fk and upload_pk = $1";
    $row = $this->dbManager->getSingleRow($sql, array($Upload), __METHOD__ . "getUploadOwner");

    $vars['fileUploadUser'] = $row['user_name'];
    $userId = $this->uploadDao->getAssignee($Upload, $groupId);
    $vars['fileAssigneeUser'] = ($userId == 1) ? 'Unassigned' : $this->userDao->getUserName($userId);
    $assignedDate = $this->uploadDao->getAssigneeDate($Upload);
    $vars['fileAssigneeUserOn'] = empty($assignedDate) ? 'NA' : substr($assignedDate, 0, strrpos($assignedDate, '.'));
    $vars['fileClearingDuration'] = $this->uploadDao->getClearingDuration($Upload)[0];

    return $vars;
  } // ShowMetaView()

  /**
   * \brief Display the package info associated with
   * the rpm/debian package.
   */
  function ShowPackageInfo($Upload, $Item, $ShowMenu = 0)
  {
    $vars = [];
    $Require = "";
    $MIMETYPE = "";
    $Count = 0;

    $rpm_info = array("Package" => "pkg_name",
           "Alias" => "pkg_alias",
           "Architecture" => "pkg_arch",
           "Version" => "version",
           "License" => "license",
           "Group" => "pkg_group",
           "Packager" => "packager",
           "Release" => "release",
           "BuildDate" => "build_date",
           "Vendor" => "vendor",
           "URL" => "url",
           "Summary" => "summary",
           "Description" => "description",
           "Source" => "source_rpm");

    $deb_binary_info = array("Package" => "pkg_name",
               "Architecture" => "pkg_arch",
               "Version" => "version",
               "Section" => "section",
               "Priority" => "priority",
               "Installed Size" => "installed_size",
               "Maintainer" => "maintainer",
               "Homepage" => "homepage",
               "Source" => "source",
               "Summary" => "summary",
               "Description" => "description");

    $deb_source_info = array("Format" => "format",
               "Source" => "source",
               "Binary" => "pkg_name",
               "Architecture" => "pkg_arch",
               "Version" => "version",
               "Maintainer" => "maintainer",
               "Uploaders" => "uploaders",
               "Standards-Version" => "standards_version");

    if (empty($Item) || empty($Upload)) {
      return $vars;
    }

    /**********************************
     Check if pkgagent disabled
     ***********************************/
    $sql = "SELECT agent_enabled FROM agent WHERE agent_name ='pkgagent' order by agent_ts LIMIT 1;";
    $row = $this->dbManager->getSingleRow($sql, array(), __METHOD__ . "checkPkgagentDisabled");
    if (isset($row) && ($row['agent_enabled'] == 'f')) {
      return $vars;
    }

    /* If pkgagent_ars table didn't exists, don't show the result. */
    $sql = "SELECT typlen FROM pg_type where typname='pkgagent_ars' limit 1;";
    $this->dbManager->prepare(__METHOD__ . "displayPackageInfo", $sql);
    $result = $this->dbManager->execute(__METHOD__ . "displayPackageInfo", array());
    $numrows = pg_num_rows($result);
    $this->dbManager->freeResult($result);
    if ($numrows <= 0) {
      $vars['packageAgentNA'] = 1;
      return $vars;
    }

    /* If pkgagent_ars table didn't have record for this upload, don't show the result. */
    $agent_status = AgentARSList('pkgagent_ars', $Upload);
    if (empty($agent_status)) {
      $vars['packageAgentStatus'] = 1;
      $vars['trackback_uri'] = Traceback_uri() .
      "?mod=schedule_agent&upload=$Upload&agent=agent_pkgagent";
      return ($vars);
    }
    $sql = "SELECT mimetype_name
    FROM uploadtree
    INNER JOIN pfile ON uploadtree_pk = $1
    AND pfile_fk = pfile_pk
    INNER JOIN mimetype ON pfile_mimetypefk = mimetype_pk;";
    $this->dbManager->prepare(__METHOD__ . "getMimetypeName", $sql);
    $result = $this->dbManager->execute(__METHOD__ . "getMimetypeName", array($Item));
    while ($row = pg_fetch_assoc($result)) {
      if (! empty($row['mimetype_name'])) {
        $MIMETYPE = $row['mimetype_name'];
      }
    }
    $this->dbManager->freeResult($result);

    /**
    * RPM Package Info
    **/
    if ($MIMETYPE == "application/x-rpm") {
      $sql = "SELECT *
        FROM pkg_rpm
        INNER JOIN uploadtree ON uploadtree_pk = $1
        AND uploadtree.pfile_fk = pkg_rpm.pfile_fk;";
      $R = $this->dbManager->getSingleRow($sql, array($Item), __METHOD__ . "getRPMPackageInfo");
      if ((! empty($R['source_rpm'])) and (trim($R['source_rpm']) != "(none)")) {
        $vars['packageType'] = _("RPM Binary Package");
      } else {
        $vars['packageType'] = _("RPM Source Package");
      }
      $Count = 1;

      if (! empty($R['pkg_pk'])) {
        $Require = $R['pkg_pk'];
        foreach ($rpm_info as $key => $value) {
          $entry = [];
          $entry['count'] = $Count;
          $entry['type'] = _($key);
          $entry['value'] = htmlentities($R["$value"]);
          $Count++;
          $vars['packageEntries'][] = $entry;
        }

        $sql = "SELECT * FROM pkg_rpm_req WHERE pkg_fk = $1;";
        $this->dbManager->prepare(__METHOD__ . "getPkg_rpm_req", $sql);
        $result = $this->dbManager->execute(__METHOD__ . "getPkg_rpm_req", array($Require));

        while ($R = pg_fetch_assoc($result) and ! empty($R['req_pk'])) {
          $entry = [];
          $entry['count'] = $Count;
          $entry['type'] = _("Requires");
          $entry['value'] = htmlentities($R['req_value']);
          $Count++;
          $vars['packageRequires'][] = $entry;
        }
        $this->dbManager->freeResult($result);
      }
    } elseif ($MIMETYPE == "application/x-debian-package") {
      $vars['packageType'] = _("Debian Binary Package\n");

      $sql = "SELECT *
        FROM pkg_deb
        INNER JOIN uploadtree ON uploadtree_pk = $1
        AND uploadtree.pfile_fk = pkg_deb.pfile_fk;";
      $R = $this->dbManager->getSingleRow($sql, array($Item), __METHOD__ . "debianBinaryPackageInfo");
      $Count = 1;

      if ($R) {
        $Require = $R['pkg_pk'];
        foreach ($deb_binary_info as $key => $value) {
          $entry = [];
          $entry['count'] = $Count;
          $entry['type'] = _($key);
          $entry['value'] = htmlentities($R["$value"]);
          $Count++;
          $vars['packageEntries'][] = $entry;
        }
        pg_free_result($result);

        $sql = "SELECT * FROM pkg_deb_req WHERE pkg_fk = $1;";
        $this->dbManager->prepare(__METHOD__ . "getPkg_rpm_req", $sql);
        $result = $this->dbManager->execute(__METHOD__ . "getPkg_rpm_req", array($Require));

        while ($R = pg_fetch_assoc($result) and ! empty($R['req_pk'])) {
          $entry = [];
          $entry['count'] = $Count;
          $entry['type'] = _("Depends");
          $entry['value'] = htmlentities($R['req_value']);
          $Count++;
          $vars['packageRequires'][] = $entry;
        }
        $this->dbManager->freeResult($result);
      }
      $V .= "</table>\n";
    } elseif ($MIMETYPE == "application/x-debian-source") {
      $vars['packageType'] = _("Debian Source Package\n");

      $sql = "SELECT *
        FROM pkg_deb
        INNER JOIN uploadtree ON uploadtree_pk = $1
        AND uploadtree.pfile_fk = pkg_deb.pfile_fk;";
      $R = $this->dbManager->getSingleRow($sql, array($Item), __METHOD__ . "debianSourcePakcageInfo");
      $Count = 1;

      if ($R) {
        $Require = $R['pkg_pk'];
        foreach ($deb_source_info as $key => $value) {
          $entry = [];
          $entry['count'] = $Count;
          $entry['type'] = _($key);
          $entry['value'] = htmlentities($R["$value"]);
          $Count++;
          $vars['packageEntries'][] = $entry;
        }
        pg_free_result($result);

        $sql = "SELECT * FROM pkg_deb_req WHERE pkg_fk = $1;";
        $this->dbManager->prepare(__METHOD__ . "getPkg_rpm_req", $sql);
        $result = $this->dbManager->execute(__METHOD__ . "getPkg_rpm_req", array($Require));

        while ($R = pg_fetch_assoc($result) and ! empty($R['req_pk'])) {
          $entry = [];
          $entry['count'] = $Count;
          $entry['type'] = _("Build-Depends");
          $entry['value'] = htmlentities($R['req_value']);
          $Count++;
          $vars['packageRequires'][] = $entry;
        }
        $this->dbManager->freeResult($result);
      }
    } else {
      /* Not a package */
      $vars['packageType'] = "";
    }
    return $vars;
  } // ShowPackageInfo()


  /**
   * \brief Display the tag info data associated with the file.
   */
  function ShowTagInfo($Upload, $Item)
  {
    $vars = [];
    $groupId = Auth::getGroupId();
    $row = $this->uploadDao->getUploadEntry($Item);
    if (empty($row)) {
      $vars['tagInvalid'] = 1;
      return $vars;
    }
    $lft = $row["lft"];
    $rgt = $row["rgt"];
    $upload_pk = $row["upload_fk"];

    if (empty($lft)) {
      $vars['tagInvalid'] = 2;
      return $vars;
    }
    $sql = "SELECT * FROM uploadtree INNER JOIN (SELECT * FROM tag_file,tag WHERE tag_pk = tag_fk) T
    ON uploadtree.pfile_fk = T.pfile_fk WHERE uploadtree.upload_fk = $1
    AND uploadtree.lft >= $2 AND uploadtree.rgt <= $3 UNION SELECT * FROM uploadtree INNER JOIN
    (SELECT * FROM tag_uploadtree,tag WHERE tag_pk = tag_fk) T ON uploadtree.uploadtree_pk = T.uploadtree_fk
    WHERE uploadtree.upload_fk = $1 AND uploadtree.lft >= $2 AND uploadtree.rgt <= $3 ORDER BY ufile_name";
    $this->dbManager->prepare(__METHOD__, $sql);
    $result = $this->dbManager->execute(__METHOD__, array($upload_pk, $lft,$rgt));
    if (pg_num_rows($result) > 0) {
      while ($row = pg_fetch_assoc($result)) {
        $entry = [];
        $entry['ufile_name'] = $row['ufile_name'];
        $entry['tag'] = $row['tag'];
        if ($this->uploadDao->isAccessible($upload_pk, $groupId)) {
          $entry['url'] = Traceback_uri() .
          "?mod=tag&action=edit&upload=$Upload&item=" . $row['uploadtree_pk'] .
          "&tag_file_pk=" . $row['tag_file_pk'];
        } else {
          $entry['url'] = "";
        }
        $vars['tagsEntries'][] = $entry;
      }
    }
    $this->dbManager->freeResult($result);

    return $vars;
  }

  /**
   * @brief  Get the info regarding reused package
   * @param  int $uploadId Get the reused package for this upload
   * @returns List of twig variables
   */
  function showReuseInfo($uploadId)
  {
    $vars = [];
    $reusedInfo = $this->uploadDao->getReusedUpload(
      $uploadId,
      Auth::getGroupId()
    );
    foreach ($reusedInfo as $row) {
      $entry = [];
      $reuseUploadFk = $row['reused_upload_fk'];
      $reuseGroupFk = $row['reused_group_fk'];
      $reusedUpload = $this->uploadDao->getUpload($reuseUploadFk);
      $reuseMode = array();
      if ($row['reuse_mode'] & UploadDao::REUSE_ENHANCED) {
        $reuseMode[] = "Enhanced";
      } else {
        $reuseMode[] = "Normal";
      }
      if ($row['reuse_mode'] & UploadDao::REUSE_MAIN) {
        $reuseMode[] = "Main license";
      }
      if ($row['reuse_mode'] & UploadDao::REUSE_CONF) {
        $reuseMode[] = "Report configuration settings";
      }
      $entry['name'] = $reusedUpload->getFilename();
      $entry['url'] = Traceback_uri() .
      "?mod=license&upload=$reuseUploadFk&item=" .
      $this->uploadDao->getUploadParent($reuseUploadFk);
      $entry['group'] = $this->userDao->getGroupNameById($reuseGroupFk) .
      " ($reuseGroupFk)";
      $entry['sha1'] = $this->uploadDao->getUploadHashes($reuseUploadFk)['sha1'];
      $entry['mode'] = implode(", ", $reuseMode) . " reuse";

      $vars['reusedPackageList'][] = $entry;
    }
    return $vars;
  }

  public function Output()
  {
    $uploadId = GetParm("upload", PARM_INTEGER);
    if (!$this->uploadDao->isAccessible($uploadId, Auth::getGroupId())) {
      return;
    }

    $itemId = GetParm("item", PARM_INTEGER);
    $this->vars['micromenu'] = Dir2Browse("browse", $itemId, null, $showBox = 0, "View-Meta");

    $this->vars += $this->ShowTagInfo($uploadId, $itemId);
    $this->vars += $this->ShowPackageinfo($uploadId, $itemId, 1);
    $this->vars += $this->ShowMetaView($uploadId, $itemId);
    $this->vars += $this->ShowSightings($uploadId, $itemId);
    $this->vars += $this->ShowView($uploadId, $itemId);
    $this->vars += $this->showReuseInfo($uploadId);
  }

  public function getTemplateName()
  {
    return "ui-view-info.html.twig";
  }
}
$NewPlugin = new ui_view_info();
$NewPlugin->Initialize();