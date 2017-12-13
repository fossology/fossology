<?php
/***********************************************************
 Copyright (C) 2008-2014 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015 Siemens AG

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
 ***********************************************************/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;

class ui_view_info extends FO_Plugin
{
  /** @var UploadDao */
  private $uploadDao;
  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    $this->Name       = "view_info";
    $this->Title      = _("View File Information");
    $this->Dependency = array("browse");
    $this->DBaccess   = PLUGIN_DB_READ;
    $this->LoginFlag  = 0;
    parent::__construct();
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
    $this->dbManager = $GLOBALS['container']->get('db.manager');
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $tooltipText = _("View file information");
    menu_insert("Browse-Pfile::Info",5,$this->Name,$tooltipText);
    // For the Browse menu, permit switching between detail and summary.
    $Parm = Traceback_parm_keep(array("upload","item","format"));
    $URI = $this->Name . $Parm;

    $menuPosition = 60;
    $menuText = "Info";
    if (GetParm("mod",PARM_STRING) == $this->Name)
    {
      menu_insert("View::[BREAK]", 61);
      menu_insert("View::[BREAK]", 50);
      menu_insert("View::{$menuText}", $menuPosition);
      menu_insert("View-Meta::[BREAK]", 61);
      menu_insert("View-Meta::[BREAK]", 50);
      menu_insert("View-Meta::{$menuText}", $menuPosition);

      menu_insert("Browse::Info",-3);
    }
    else
    {
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
  function ShowView($Upload, $Item, $ShowMenu=0)
  {
    $V = "";
    if (empty($Upload) || empty($Item)) { return; }

    $Page = GetParm("page",PARM_INTEGER);
    if (empty($Page)) { $Page=0; }

    /**********************************
     List File Info
     **********************************/
    if ($Page == 0)
    {
      $text = _("Repository Locator");
      $V .= "<H2>$text</H2>\n";
      $sql = "SELECT * FROM uploadtree
        INNER JOIN pfile ON uploadtree_pk = $1
        AND pfile_fk = pfile_pk
        LIMIT 1;";
      $R = $this->dbManager->getSingleRow($sql,array($Item),__METHOD__."GetFileDescribingRow");
      $V .= "<table border=1>\n";
      $text = _("Attribute");
      $text1 = _("Value");
      $V .= "<tr><th>$text</th><th>$text1</th></tr>\n";
      $Bytes = $R['pfile_size'];
      $BytesH = HumanSize($Bytes);
      $Bytes = number_format($Bytes, 0, "", ",").' B';
      if ($BytesH == $Bytes) { $BytesH = ""; }
      else { $BytesH = '(' . $BytesH . ')'; }
      $text = _("File Size");
      $V .= "<tr><td align='center'>$text</td><td align='right'>$Bytes $BytesH</td></tr>\n";
      $text = _("SHA1 Checksum");
      $V .= "<tr><td align='center'>$text</td><td align='right'>" . $R['pfile_sha1'] . "</td></tr>\n";
      $text = _("MD5 Checksum");
      $V .= "<tr><td align='center'>$text</td><td align='right'>" . $R['pfile_md5'] . "</td></tr>\n";
      $text = _("Repository ID");
      $V .= "<tr><td align='center'>$text</td><td align='right'>" . $R['pfile_sha1'] . "." . $R['pfile_md5'] . "." . $R['pfile_size'] . "</td></tr>\n";
      $text = _("Pfile ID");
      $V .= "<tr><td align='center'>$text</td><td align='right'>" . $R['pfile_fk'] . "</td></tr>\n";
      $V .= "</table>\n";
    }
    return($V);
  } // ShowView()

  /**
   * \brief Show Sightings, List the directory locations where this pfile is found
   */
  function ShowSightings($Upload, $Item)
  {
    $V = "";
    if (empty($Upload) || empty($Item)) { return; }

    $Page = GetParm("page",PARM_INTEGER);
    if (empty($Page)) { $Page=0; }
    $Max = 50;
    $Offset = $Page * $Max;

    /**********************************
     List the directory locations where this pfile is found
     **********************************/
    $text = _("Sightings");
    $V .= "<H2>$text</H2>\n";
    $sql = "SELECT * FROM pfile,uploadtree
        WHERE pfile_pk=pfile_fk
        AND pfile_pk IN
        (SELECT pfile_fk FROM uploadtree WHERE uploadtree_pk = $1)
        LIMIT $2 OFFSET $3";
    $this->dbManager->prepare(__METHOD__."getListOfFiles",$sql);
    $result = $this->dbManager->execute(__METHOD__."getListOfFiles",array($Item,$Max,$Offset));
    $Count = pg_num_rows($result);
    if (($Page > 0) || ($Count >= $Max))
    {
      $VM = "<P />\n" . MenuEndlessPage($Page, ($Count >= $Max)) . "<P />\n";
    }
    else { $VM = ""; }
    if ($Count > 0)
    {
      $V .= _("This exact file appears in the following locations:\n");
      $V .= $VM;
      $Offset++;
      $V .= Dir2FileList($result,"browse","view",$Offset);
      $V .= $VM;
    }
    else if ($Page > 0)
    {
      $V .= _("End of listing.\n");
    }
    else
    {
      $V .= _("This file does not appear in any other known location.\n");
    }
    pg_free_result($result);
    return($V);
  }//ShowSightings()

  /**
   * \brief Display the meta data associated with the file.
   */
  function ShowMetaView($Upload, $Item)
  {
    $V = "";
    $Count = 1;
    if (empty($Item) || empty($Upload))
    { return; }

    /**********************************
     Display meta data
     **********************************/

    $text = _("File Info");
    $V .= "<H2>$text</H2>\n";
    $V .= "<table border='1'>\n";
    $text = _("Item");
    $text1 = _("Meta Data");
    $text2 = _("Value");
    $V .= "<tr><th width='5%'>$text</th><th width='20%'>$text1</th><th>$text2</th></tr>\n";

    /* display mimetype */
    $sql = "SELECT * FROM uploadtree where uploadtree_pk = $1";
    $this->dbManager->prepare(__METHOD__."DisplayMimetype",$sql);
    $result = $this->dbManager->execute(__METHOD__."DisplayMimetype",array($Item));
    if (pg_num_rows($result))
    {
      $row = pg_fetch_assoc($result);

      if (!empty($row['mimetype_pk']))
      {
        $V .= "<tr><td align='right'>" . $Count++ . "</td><td>Unpacked file type";
        $V .= "</td><td>" . htmlentities($row['mimetype_name']) . "</td></tr>\n";
      }
    }
    else
    {
      // bad uploadtree_pk
      pg_free_result($result);
      $text = _("File does not exist in database");
      return $text;
    }
    $this->dbManager->freeResult($result);

    /* get mimetype */
    if (!empty($row['pfile_fk']))
    {
      $sql = "select mimetype_name from pfile, mimetype where pfile_pk = $1 and pfile_mimetypefk=mimetype_pk";
      $this->dbManager->prepare(__METHOD__."GetMimetype",$sql);
      $result = $this->dbManager->execute(__METHOD__."GetMimetype",array($row['pfile_fk']));
      if (pg_num_rows($result))
      {
        $pmRow = pg_fetch_assoc($result);
        $V .= "<tr><td align='right'>" . $Count++ . "</td><td>Unpacked file type";
        $V .= "</td><td>" . htmlentities($pmRow['mimetype_name']) . "</td></tr>\n";
      }
      $this->dbManager->freeResult($result);
    }

    /* display upload origin */
    $sql = "select * from upload where upload_pk=$1";
    $row = $this->dbManager->getSingleRow($sql,array($row['upload_fk']),__METHOD__."getUploadOrigin");
    if ($row)
    {

      /* upload source */
      if ($row['upload_mode'] & 1 << 2) $text = _("Added by URL: ");
      else if ($row['upload_mode'] & 1 << 3) $text = _("Added by file upload: ");
      else if ($row['upload_mode'] & 1 << 4) $text = _("Added from filesystem: ");
      $V .= "<tr><td align='right'>" . $Count++ . "</td><td>$text</td>";
      $V .= "<td>" . htmlentities($row['upload_origin']) . "</td></tr>\n";

      /* upload time */
      $text = _("Added to repo");
      $V .= "<tr><td align='right'>" . $Count++ . "</td><td>$text</td>";
      $ts = $row['upload_ts'];
      $V .= "<td>" . substr($ts, 0, strrpos($ts, '.')) . "</td></tr>\n";
    }
      /* display where it was uploaded from */

    /* display upload owner*/
    $sql = "SELECT user_name from users, upload  where user_pk = user_fk and upload_pk = $1";
    $row = $this->dbManager->getSingleRow($sql,array($Upload),__METHOD__."getUploadOwner");

    $text = _("Added by");
    $V .= "<tr><td align='right'>" . $Count++ . "</td><td>$text</td>";
    $V .= "<td>" . $row['user_name'] . "</td></tr>\n";

    $V .= "</table><br>\n";
    return($V);
  } // ShowMetaView()

  /**
   * \brief Display the package info associated with
   * the rpm/debian package.
   */
  function ShowPackageInfo($Upload, $Item, $ShowMenu=0)
  {
    $V = "";
    $Require = "";
    $MIMETYPE = "";
    $Count = 0;

    $rpm_info = array("Package"=>"pkg_name",
                      "Alias"=>"pkg_alias",
                      "Architecture"=>"pkg_arch",
                      "Version"=>"version",
                      "License"=>"license",
                      "Group"=>"pkg_group",
                      "Packager"=>"packager",
                      "Release"=>"release",
                      "BuildDate"=>"build_date",
                      "Vendor"=>"vendor",
                      "URL"=>"url",
                      "Summary"=>"summary",
                      "Description"=>"description",
                      "Source"=>"source_rpm");    

    $deb_binary_info = array("Package"=>"pkg_name",
                             "Architecture"=>"pkg_arch",
                             "Version"=>"version",
                             "Section"=>"section",
                             "Priority"=>"priority",
                             "Installed Size"=>"installed_size",
                             "Maintainer"=>"maintainer",
                             "Homepage"=>"homepage",
                             "Source"=>"source",
                             "Summary"=>"summary",
                             "Description"=>"description");

    $deb_source_info = array("Format"=>"format",
                             "Source"=>"source",
                             "Binary"=>"pkg_name",
                             "Architecture"=>"pkg_arch",
                             "Version"=>"version",
                             "Maintainer"=>"maintainer",
                             "Uploaders"=>"uploaders",
                             "Standards-Version"=>"standards_version");

    if (empty($Item) || empty($Upload)) { return; }

    /**********************************
     Check if pkgagent disabled
     ***********************************/
    $sql = "SELECT agent_enabled FROM agent WHERE agent_name ='pkgagent' order by agent_ts LIMIT 1;";
    $row = $this->dbManager->getSingleRow($sql,array(),__METHOD__."checkPkgagentDisabled");
    if (isset($row) && ($row['agent_enabled']== 'f')){return;}

    /**********************************
     Display package info
     **********************************/
    $text = _("Package Info");
    $V .= "<H2>$text</H2>\n";

    /* If pkgagent_ars table didn't exists, don't show the result. */
    $sql = "SELECT typlen  FROM pg_type where typname='pkgagent_ars' limit 1;";
    $this->dbManager->prepare(__METHOD__."displayPackageInfo",$sql);
    $result = $this->dbManager->execute(__METHOD__."displayPackageInfo",array());
    $numrows = pg_num_rows($result);
    $this->dbManager->freeResult($result);
    if ($numrows <= 0)
    {
      $V .= _("No data available. Use Jobs > Agents to schedule a pkgagent scan.");
      return($V);
    }

    /* If pkgagent_ars table didn't have record for this upload, don't show the result. */
    $agent_status = AgentARSList('pkgagent_ars', $Upload);
    if (empty($agent_status))
    {

      /** schedule pkgagent */
      $V .= ActiveHTTPscript("Schedule");
      $V .= "<script language='javascript'>\n";
      $V .= "function Schedule_Reply()\n";
      $V .= "  {\n";
      $V .= "  if ((Schedule.readyState==4) && (Schedule.status==200))\n";
      $V .= "    document.getElementById('msgdiv').innerHTML = Schedule.responseText;\n";
      $V .= "  }\n";
      $V .= "</script>\n";

      $V .= "<form name='formy' method='post'>\n";
      $V .= "<div id='msgdiv'>\n";
      $V .= _("No data available.");
      $V .= "<input type='button' name='scheduleAgent' value='Schedule Agent'";
      $V .= "onClick='Schedule_Get(\"" . Traceback_uri() . "?mod=schedule_agent&upload=$Upload&agent=agent_pkgagent \")'>\n";
      $V .= "</input>";
      $V .= "</div> \n";
      $V .= "</form>\n";

      return($V);
    }
    $sql = "SELECT mimetype_name
        FROM uploadtree
        INNER JOIN pfile ON uploadtree_pk = $1
        AND pfile_fk = pfile_pk
        INNER JOIN mimetype ON pfile_mimetypefk = mimetype_pk;";
    $this->dbManager->prepare(__METHOD__."getMimetypeName",$sql);
    $result = $this->dbManager->execute(__METHOD__."getMimetypeName",array($Item));
    while ($row = pg_fetch_assoc($result))
    {
      if (!empty($row['mimetype_name']))
      {
        $MIMETYPE = $row['mimetype_name'];
      }
    }
    $this->dbManager->freeResult($result);

    /** RPM Package Info **/
    if ($MIMETYPE == "application/x-rpm")
    {
      $sql = "SELECT *
                FROM pkg_rpm
                INNER JOIN uploadtree ON uploadtree_pk = $1
                AND uploadtree.pfile_fk = pkg_rpm.pfile_fk;";
      $R = $this->dbManager->getSingleRow($sql,array($Item),__METHOD__."getRPMPackageInfo");
      if((!empty($R['source_rpm']))and(trim($R['source_rpm']) != "(none)"))
      {
        $V .= _("RPM Binary Package");
      }
      else
      {
        $V .= _("RPM Source Package");
      }
      $Count=1;

      $V .= "<table border='1' name='pkginfo'>\n";
      $text = _("Item");
      $text1 = _("Type");
      $text2 = _("Value");
      $V .= "<tr><th width='5%'>$text</th><th width='20%'>$text1</th><th>$text2</th></tr>\n";

      if (!empty($R['pkg_pk']))
      {
        $Require = $R['pkg_pk'];
        foreach ($rpm_info as $key=>$value)
        {
          $text = _($key);
          $V .= "<tr><td align='right'>$Count</td><td>$text";
          $V .= "</td><td>" . htmlentities($R["$value"]) . "</td></tr>\n";
          $Count++;
        } 

        $sql = "SELECT * FROM pkg_rpm_req WHERE pkg_fk = $1;";
        $this->dbManager->prepare(__METHOD__."getPkg_rpm_req",$sql);
        $result = $this->dbManager->execute(__METHOD__."getPkg_rpm_req",array($Require));

        while ($R = pg_fetch_assoc($result) and !empty($R['req_pk']))
        {
          $text = _("Requires");
          $V .= "<tr><td align='right'>$Count</td><td>$text";
          $Val = htmlentities($R['req_value']);
          $Val = preg_replace("@((http|https|ftp)://[^{}<>&[:space:]]*)@i","<a href='\$1'>\$1</a>",$Val);
          $V .= "</td><td>$Val</td></tr>\n";
          $Count++;
        }
        $this->dbManager->freeResult($result);
      }
      $V .= "</table>\n";
    }
    else if ($MIMETYPE == "application/x-debian-package")
    {
      $V .= _("Debian Binary Package\n");

      $sql = "SELECT *
                FROM pkg_deb
                INNER JOIN uploadtree ON uploadtree_pk = $1
                AND uploadtree.pfile_fk = pkg_deb.pfile_fk;";
      $R = $this->dbManager->getSingleRow($sql,array($Item),__METHOD__."debianBinaryPackageInfo");
      $Count=1;

      $V .= "<table border='1'>\n";
      $text = _("Item");
      $text1 = _("Type");
      $text2 = _("Value");
      $V .= "<tr><th width='5%'>$text</th><th width='20%'>$text1</th><th>$text2</th></tr>\n";

      if ($R)
      {
        $Require = $R['pkg_pk'];
        foreach ($deb_binary_info as $key=>$value)
        {
          $text = _($key);
          $V .= "<tr><td align='right'>$Count</td><td>$text";
          $V .= "</td><td>" . htmlentities($R["$value"]) . "</td></tr>\n";
          $Count++;
        }
        pg_free_result($result);

        $sql = "SELECT * FROM pkg_deb_req WHERE pkg_fk = $1;";
        $this->dbManager->prepare(__METHOD__."getPkg_rpm_req",$sql);
        $result = $this->dbManager->execute(__METHOD__."getPkg_rpm_req",array($Require));

        while ($R = pg_fetch_assoc($result) and !empty($R['req_pk']))
        {
          $text = _("Depends");
          $V .= "<tr><td align='right'>$Count</td><td>$text";
          $Val = htmlentities($R['req_value']);
          $Val = preg_replace("@((http|https|ftp)://[^{}<>&[:space:]]*)@i","<a href='\$1'>\$1</a>",$Val);
          $V .= "</td><td>$Val</td></tr>\n";
          $Count++;
        }
        $this->dbManager->freeResult($result);
      }
      $V .= "</table>\n";
    }
    else if ($MIMETYPE == "application/x-debian-source")
    {
      $V .= _("Debian Source Package\n");

      $sql = "SELECT *
                FROM pkg_deb
                INNER JOIN uploadtree ON uploadtree_pk = $1
                AND uploadtree.pfile_fk = pkg_deb.pfile_fk;";
      $R = $this->dbManager->getSingleRow($sql,array($Item),__METHOD__."debianSourcePakcageInfo");
      $Count=1;

      $V .= "<table border='1'>\n";
      $text = _("Item");
      $text1 = _("Type");
      $text2 = _("Value");
      $V .= "<tr><th width='5%'>$text</th><th width='20%'>$text1</th><th>$text2</th></tr>\n";

      if ($R)
      {
        $Require = $R['pkg_pk'];
        foreach ($deb_source_info as $key=>$value)
        {
          $text = _($key);
          $V .= "<tr><td align='right'>$Count</td><td>$text";
          $V .= "</td><td>" . htmlentities($R["$value"]) . "</td></tr>\n";
          $Count++;
        }
        pg_free_result($result);

        $sql = "SELECT * FROM pkg_deb_req WHERE pkg_fk = $1;";
        $this->dbManager->prepare(__METHOD__."getPkg_rpm_req",$sql);
        $result = $this->dbManager->execute(__METHOD__."getPkg_rpm_req",array($Require));

        while ($R = pg_fetch_assoc($result) and !empty($R['req_pk']))
        {
          $text = _("Build-Depends");
          $V .= "<tr><td align='right'>$Count</td><td>$text";
          $Val = htmlentities($R['req_value']);
          $Val = preg_replace("@((http|https|ftp)://[^{}<>&[:space:]]*)@i","<a href='\$1'>\$1</a>",$Val);
          $V .= "</td><td>$Val</td></tr>\n";
          $Count++;
        }
        $this->dbManager->freeResult($result);
      }
      $V .= "</table>\n";
    }
    else
    {
       /* Not a package */
       return "";
    }
    return($V);
  } // ShowPackageInfo()


  /**
   * \brief Display the tag info data associated with the file.
   */
  function ShowTagInfo($Upload, $Item)
  {
    $VT = "";
    $text = _("Tag Info");
    $VT .= "<H2>$text</H2>\n";
    $groupId = Auth::getGroupId();
    $row = $this->uploadDao->getUploadEntry($Item);
    if (empty($row))
    {
      $text = _("Invalid URL, nonexistant item");
      return "<h2>$text $Item</h2>";
    }
    $lft = $row["lft"];
    $rgt = $row["rgt"];
    $upload_pk = $row["upload_fk"];

    if (empty($lft))
    {
      $text = _("Upload data is unavailable.  It needs to be unpacked.");
      return "<h2>$text uploadtree_pk: $Item</h2>";
    }
    $sql = "SELECT * FROM uploadtree INNER JOIN (SELECT * FROM tag_file,tag WHERE tag_pk = tag_fk) T
        ON uploadtree.pfile_fk = T.pfile_fk WHERE uploadtree.upload_fk = $1
        AND uploadtree.lft >= $2 AND uploadtree.rgt <= $3 UNION SELECT * FROM uploadtree INNER JOIN
        (SELECT * FROM tag_uploadtree,tag WHERE tag_pk = tag_fk) T ON uploadtree.uploadtree_pk = T.uploadtree_fk
        WHERE uploadtree.upload_fk = $1 AND uploadtree.lft >= $2 AND uploadtree.rgt <= $3 ORDER BY ufile_name";
    $this->dbManager->prepare(__METHOD__,$sql);
    $result = $this->dbManager->execute(__METHOD__,array($upload_pk, $lft,$rgt));
    if (pg_num_rows($result) > 0)
    {
      $VT .= "<table border=1>\n";
      $text = _("FileName");
      $text2 = _("Tag");
      $VT .= "<tr><th>$text</th><th>$text2</th><th></th></tr>\n";
      while ($row = pg_fetch_assoc($result))
      {
        $VT .= "<tr><td align='center'>" . $row['ufile_name'] . "</td><td align='center'>" . $row['tag'] . "</td>";
        if ($this->uploadDao->isAccessible($upload_pk, $groupId))
        {
          $VT .= "<td align='center'><a href='" . Traceback_uri() . "?mod=tag&action=edit&upload=$Upload&item=" . $row['uploadtree_pk'] . "&tag_file_pk=" . $row['tag_file_pk'] . "'>View</a></td></tr>\n";
        }else{
          $VT .= "<td align='center'></td></tr>\n";
        }
      }
      $VT .= "</table><p>\n";
    }
    $this->dbManager->freeResult($result);

    return $VT;
  }

  function ShowReportInfo($Upload)
  {
    $VT = "";
    $text = _("Report Info");
    $VT .= "<H2>$text</H2>\n";

    $row = $this->uploadDao->getReportInfo($Upload);
 
    if (!empty($row))
    {
      $reviewedBy = $row['ri_reviewed'];
      $reportRel = $row['ri_report_rel'];
      $community = $row['ri_community'];
      $component = $row['ri_component'];
      $version = $row['ri_version'];
      $relDate = $row['ri_release_date'];
      $sw360Link = $row['ri_sw360_link'];
      $footerNote = $row['ri_footer'];
      $generalAssesment = $row['ri_general_assesment'];
      $gaAdditional = $row['ri_ga_additional'];
      $gaRisk = $row['ri_ga_risk'];
      $gaSelectionList = explode(',', $row['ri_ga_checkbox_selection']);
    }

    $VT .= "<form action='' name='formReportInfo' method='post'>";
    $VT .= "<table border=1 width='50%' >\n";
    $text = _("Attribute");
    $text2 = _("Info");
    $VT .= "<tr><th>$text</th><th>$text2</th></tr>\n";
    $footer = "Copyright Text(report Footer)";
    $VT .= "<tr><td align='left'>" . $footer . "</td><td align='left'> <input type='Text' name='footerNote' style='width:99%' value='". $footerNote ."'></td>";
    $attrib1 = "Reviewed by (opt.)";
    $VT .= "<tr><td align='left'>" . $attrib1 . "</td><td align='left'> <input type='Text' name='reviewedBy' style='width:99%' value='". $reviewedBy ."'></td>";
    $attrib2 = "Report release date";
    $VT .= "<tr><td align='left'>" . $attrib2 . "</td><td align='left'><input type='Text' name='reportRel' style='width:99%' value='". $reportRel ."'></td>";
    $attrib3 = "Community";
    $VT .= "<tr><td align='left'>" . $attrib3 . "</td><td align='left'><input type='Text' name='community' style='width:99%' value='". $community . "'></td>";
    $attrib4 = "Component";
    $VT .= "<tr><td align='left'>" . $attrib4 . "</td><td align='left'><input type='Text' name='component' style='width:99%' value='". $component . "'></td>";
    $attrib5 = "Version";
    $VT .= "<tr><td align='left'>" . $attrib5 . "</td><td align='left'><input type='Text' name='version' style='width:99%' value='". $version . "'></td>";
    $attrib6 = "Release date";
    $VT .= "<tr><td align='left'>" . $attrib6 . "</td><td align='left'><input type='Text' name='relDate' style='width:99%' value='". $relDate . "'></td>";
    $attrib7 = "Mainline /SW360 Portal Link";
    $VT .= "<tr><td align='left'>" . $attrib7 . "</td><td align='left'><input type='Text' name='sw360Link' style='width:99%' value='" . $sw360Link . "'></td>";
    $attrib8 = "General assessment";
    $VT .= "<tr><td align='left'>" . $attrib8 . "</td><td align='left'><input type='Text' name='generalAssesment' style='width:99%' value='" . $generalAssesment . "'></td>";
    $attrib9 = "Source / binary integration notes";
    $nonCritical = "no critical files found, source code and binaries can be used as is";
    $critical = "critical files found, source code needs to be adapted and binaries possibly re-built";
    if(empty($gaSelectionList[0])) $gaSelectionList[0] = '';
    if(empty($gaSelectionList[1])) $gaSelectionList[1] = '';
    $VT .= "<tr><td align='left'>" . $attrib9 . "</td><td align='left'><input type='checkbox' name='nonCritical' $gaSelectionList[0]>$nonCritical</br><input type='checkbox' name='critical' $gaSelectionList[1]>$critical</td>";
    $attrib10 = "Dependency notes";
    $noDependency = "no dependencies found, neither in source code nor in binaries";
    $dependencySource = "dependencies found in source code (see obligations)";
    $dependencyBinary = "dependencies found in binaries (see obligations)";

    if(empty($gaSelectionList[2])) $gaSelectionList[2] = '';
    if(empty($gaSelectionList[3])) $gaSelectionList[3] = '';
    if(empty($gaSelectionList[4])) $gaSelectionList[4] = '';
    $VT .= "<tr><td align='left'>" . $attrib10 . "</td><td align='left'><input type='checkbox' name='noDependency' $gaSelectionList[2]>$noDependency</br><input type='checkbox' name='dependencySource' $gaSelectionList[3]>$dependencySource</br><input type='checkbox' name='dependencyBinary' $gaSelectionList[4]>$dependencyBinary</td>";
    $attrib11 = "Export restrictions by copyright owner";
    $noExportRestriction = "no export restrictions found";
    $exportRestriction = "export restrictions found (see obligations)";
    if(empty($gaSelectionList[5])) $gaSelectionList[5] = '';
    if(empty($gaSelectionList[6])) $gaSelectionList[6] = '';
    $VT .= "<tr><td align='left'>" . $attrib11 . "</td><td align='left'><input type='checkbox' name='noExportRestriction' $gaSelectionList[5]>$noExportRestriction </br><input type='checkbox' name='exportRestriction' $gaSelectionList[6]>$exportRestriction</td>";
    $attrib12 = "Restrictions for use (e.g. not for Nuclear Power) by copyright owner";
    $noRestriction = "no restrictions for use found";
    $restrictionForUse = "restrictions for use found (see obligations)";
    if(empty($gaSelectionList[7])) $gaSelectionList[7] = '';
    if(empty($gaSelectionList[8])) $gaSelectionList[8] = '';
    $VT .= "<tr><td align='left'>" . $attrib12 . "</td><td align='left'><input type='checkbox' name='noRestriction' $gaSelectionList[7]>$noRestriction</br><input type='checkbox' name='restrictionForUse' $gaSelectionList[8]>$restrictionForUse</td>";
    $attrib13 = "Additional notes";
    $VT .= "<tr><td align='left'>" . $attrib13 . "</td><td align='left'><input type='Text' name='gaAdditional' style='width:99%' value='" . $gaAdditional . "'></td>";
    $attrib14 = "General Risks (optional)";
    $VT .= "<tr><td align='left'>" . $attrib14 . "</td><td align='left'><input type='Text' name='gaRisk' style='width:99%' value='" . $gaRisk . "'></td>";
    $VT .= "<tr><td align='center' colspan='2' ><input type='submit' name='submitReportInfo' value='Submit' /></td></tr>";
    $VT .= "</table><p>\n";
    $VT .= "</form>";

    return $VT;
  }

  /**
    * @param array $checkBoxListParams
    * @return $cbSelectionList
   */

  protected function getCheckBoxSelectionList($checkBoxListParams)
  {
    foreach($checkBoxListParams as $checkBoxListParam)
    {
      $ret = GetParm($checkBoxListParam, PARM_STRING);
      if(empty($ret))
      {
        $cbList[] = "unchecked";
      }
      else
      {
        $cbList[] = "checked";
      }
    }
    $cbSelectionList = implode(",", $cbList);

    return $cbSelectionList;
  }

  public function Output()
  {
    $uploadId = GetParm("upload",PARM_INTEGER);
    if (!$this->uploadDao->isAccessible($uploadId, Auth::getGroupId())) return;

    $itemId = GetParm("item",PARM_INTEGER);
    $this->vars['micromenu'] = Dir2Browse("browse", $itemId, NULL, $showBox=0, "View-Meta");

    $submitReportInfo = GetParm("submitReportInfo", PARM_STRING);

    if(isset($submitReportInfo)){
      $reviewedBy = GetParm('reviewedBy', PARM_TEXT);
      $footerNote = GetParm('footerNote', PARM_TEXT);
      $reportRel = GetParm('reportRel', PARM_TEXT);
      $community = GetParm('community', PARM_TEXT);
      $component = GetParm('component', PARM_TEXT);
      $version = GetParm('version', PARM_TEXT);
      $relDate = GetParm('relDate', PARM_TEXT);
      $sw360Link = GetParm('sw360Link', PARM_TEXT);
      $generalAssesment = GetParm('generalAssesment', PARM_TEXT);
      $checkBoxListParams = array("nonCritical","critical","noDependency","dependencySource","dependencyBinary","noExportRestriction","exportRestriction","noRestriction","restrictionForUse");
      $cbSelectionList = $this->getCheckBoxSelectionList($checkBoxListParams);
      $gaAdditional = GetParm('gaAdditional', PARM_TEXT);
      $gaRisk = GetParm('gaRisk', PARM_TEXT);
      $sql = "UPDATE report_info SET ri_reviewed=$2, ri_footer=$3, ri_report_rel=$4, ri_community=$5, ri_component=$6,ri_version=$7, ri_release_date=$8, ri_sw360_link=$9, ri_general_assesment=$10, ri_ga_additional=$11,ri_ga_risk=$12,ri_ga_checkbox_selection=$13 WHERE upload_fk=$1;";
      $this->dbManager->prepare(__METHOD__."updateReportInfoData",$sql);
      $result = $this->dbManager->execute(__METHOD__."updateReportInfoData",array($uploadId, $reviewedBy, $footerNote, $reportRel, $community, $component, $version, $relDate, $sw360Link, $generalAssesment, $gaAdditional, $gaRisk, $cbSelectionList));
      $this->dbManager->freeResult($result);
    }

    $V="";
    $V .= $this->ShowReportInfo($uploadId);
    $V .= $this->ShowTagInfo($uploadId, $itemId);
    $V .= $this->ShowPackageinfo($uploadId, $itemId, 1);
    $V .= $this->ShowMetaView($uploadId, $itemId);
    $V .= $this->ShowSightings($uploadId, $itemId);
    $V .= $this->ShowView($uploadId, $itemId);

    return $V;
  }

}
$NewPlugin = new ui_view_info;
$NewPlugin->Initialize();
