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

class ui_view_info extends FO_Plugin
{
  /** @var UploadDao */
  private $uploadDao;
  
  function __construct()
  {
    $this->Name       = "view_info";
    $this->Title      = _("View File Information");
    $this->Dependency = array("browse");
    $this->DBaccess   = PLUGIN_DB_READ;
    $this->LoginFlag  = 0;
    parent::__construct();
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $text = _("View file information");
    menu_insert("Browse-Pfile::Info",5,$this->Name,$text);
    // For the Browse menu, permit switching between detail and summary.
    $Parm = Traceback_parm_keep(array("upload","item","format"));
    $URI = $this->Name . $Parm;
    if (GetParm("mod",PARM_STRING) == $this->Name)
    {
      menu_insert("View::Info",1);
      menu_insert("View-Meta::Info",1);
      menu_insert("Browse::Info",-3);
    }
    else
    {
      $text = _("View information about this file");
      menu_insert("View::Info",1,$URI,$text);
      menu_insert("View-Meta::Info",1,$URI,$text);
      menu_insert("Browse::Info",-3,$URI,$text);
    }
  } // RegisterMenus()

  /**
   * \brief Display the info data associated with the file.
   */
  function ShowView($Upload, $Item, $ShowMenu=0)
  {
    global $PG_CONN;
    $V = "";
    if (empty($Upload) || empty($Item)) { return; }

    $Page = GetParm("page",PARM_INTEGER);
    if (empty($Page)) { $Page=0; }
    $Max = 50;

    /**********************************
     List File Info
     **********************************/
    if ($Page == 0)
    {
      $text = _("Repository Locator");
      $V .= "<H2>$text</H2>\n";
      $sql = "SELECT * FROM uploadtree
        INNER JOIN pfile ON uploadtree_pk = $Item
        AND pfile_fk = pfile_pk
        LIMIT 1;";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $R = pg_fetch_assoc($result);
      pg_free_result($result);
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
    global $PG_CONN;
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
        (SELECT pfile_fk FROM uploadtree WHERE uploadtree_pk = $Item)
        LIMIT $Max OFFSET $Offset";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
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
    global $PG_CONN;
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
    $sql = "SELECT * FROM uploadtree where uploadtree_pk = $Item";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
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
    pg_free_result($result);

    /* get mimetype */
    if (!empty($row['pfile_fk']))
    {
      $sql = "select mimetype_name from pfile, mimetype where pfile_pk = $row[pfile_fk] and pfile_mimetypefk=mimetype_pk";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      if (pg_num_rows($result))
      {
        $pmRow = pg_fetch_assoc($result);
        $V .= "<tr><td align='right'>" . $Count++ . "</td><td>Unpacked file type";
        $V .= "</td><td>" . htmlentities($pmRow['mimetype_name']) . "</td></tr>\n";
      }
      pg_free_result($result);
    }

    /* display upload origin */
    $sql = "select * from upload where upload_pk='$row[upload_fk]'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result))
    {
      $row = pg_fetch_assoc($result);

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
    pg_free_result($result);
      /* display where it was uploaded from */

    /* display upload owner*/
    $sql = "SELECT user_name from users, upload  where user_pk = user_fk and upload_pk = '$Upload'";
    $result = pg_query($PG_CONN, $sql);
    $row = pg_fetch_assoc($result);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

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
    global $PG_CONN;
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
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    if (isset($row) && ($row['agent_enabled']== 'f')){return;}

    /**********************************
     Display package info
     **********************************/
    $text = _("Package Info");
    $V .= "<H2>$text</H2>\n";

    /* If pkgagent_ars table didn't exists, don't show the result. */
    $sql = "SELECT typlen  FROM pg_type where typname='pkgagent_ars' limit 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $numrows = pg_num_rows($result);
    pg_free_result($result);
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
        INNER JOIN pfile ON uploadtree_pk = $Item
        AND pfile_fk = pfile_pk
        INNER JOIN mimetype ON pfile_mimetypefk = mimetype_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    while ($row = pg_fetch_assoc($result))
    {
      if (!empty($row['mimetype_name']))
      {
        $MIMETYPE = $row['mimetype_name'];
      }
    }
    pg_free_result($result);

    /** RPM Package Info **/
    if ($MIMETYPE == "application/x-rpm")
    {
      $sql = "SELECT *
                FROM pkg_rpm
                INNER JOIN uploadtree ON uploadtree_pk = $Item
                AND uploadtree.pfile_fk = pkg_rpm.pfile_fk;";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);

      $R = pg_fetch_assoc($result);
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
        pg_free_result($result);

        $sql = "SELECT * FROM pkg_rpm_req WHERE pkg_fk = $Require;";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);

        while ($R = pg_fetch_assoc($result) and !empty($R['req_pk']))
        {
          $text = _("Requires");
          $V .= "<tr><td align='right'>$Count</td><td>$text";
          $Val = htmlentities($R['req_value']);
          $Val = preg_replace("@((http|https|ftp)://[^{}<>&[:space:]]*)@i","<a href='\$1'>\$1</a>",$Val);
          $V .= "</td><td>$Val</td></tr>\n";
          $Count++;
        }
        pg_free_result($result);
      }
      $V .= "</table>\n";
      $Count--;

    }
    else if ($MIMETYPE == "application/x-debian-package")
    {
      $V .= _("Debian Binary Package\n");

      $sql = "SELECT *
                FROM pkg_deb
                INNER JOIN uploadtree ON uploadtree_pk = $Item
                AND uploadtree.pfile_fk = pkg_deb.pfile_fk;";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $Count=1;

      $V .= "<table border='1'>\n";
      $text = _("Item");
      $text1 = _("Type");
      $text2 = _("Value");
      $V .= "<tr><th width='5%'>$text</th><th width='20%'>$text1</th><th>$text2</th></tr>\n";

      if (pg_num_rows($result))
      {
        $R = pg_fetch_assoc($result);
        $Require = $R['pkg_pk'];
        foreach ($deb_binary_info as $key=>$value)
        {
          $text = _($key);
          $V .= "<tr><td align='right'>$Count</td><td>$text";
          $V .= "</td><td>" . htmlentities($R["$value"]) . "</td></tr>\n";
          $Count++;
        }
        pg_free_result($result);

        $sql = "SELECT * FROM pkg_deb_req WHERE pkg_fk = $Require;";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);

        while ($R = pg_fetch_assoc($result) and !empty($R['req_pk']))
        {
          $text = _("Depends");
          $V .= "<tr><td align='right'>$Count</td><td>$text";
          $Val = htmlentities($R['req_value']);
          $Val = preg_replace("@((http|https|ftp)://[^{}<>&[:space:]]*)@i","<a href='\$1'>\$1</a>",$Val);
          $V .= "</td><td>$Val</td></tr>\n";
          $Count++;
        }
        pg_free_result($result);
      }
      $V .= "</table>\n";
      $Count--;
    }
    else if ($MIMETYPE == "application/x-debian-source")
    {
      $V .= _("Debian Source Package\n");

      $sql = "SELECT *
                FROM pkg_deb
                INNER JOIN uploadtree ON uploadtree_pk = $Item
                AND uploadtree.pfile_fk = pkg_deb.pfile_fk;";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $Count=1;

      $V .= "<table border='1'>\n";
      $text = _("Item");
      $text1 = _("Type");
      $text2 = _("Value");
      $V .= "<tr><th width='5%'>$text</th><th width='20%'>$text1</th><th>$text2</th></tr>\n";

      if (pg_num_rows($result))
      {
        $R = pg_fetch_assoc($result);
        $Require = $R['pkg_pk'];
        foreach ($deb_source_info as $key=>$value)
        {
          $text = _($key);
          $V .= "<tr><td align='right'>$Count</td><td>$text";
          $V .= "</td><td>" . htmlentities($R["$value"]) . "</td></tr>\n";
          $Count++;
        }
        pg_free_result($result);

        $sql = "SELECT * FROM pkg_deb_req WHERE pkg_fk = $Require;";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);

        while ($R = pg_fetch_assoc($result) and !empty($R['req_pk']))
        {
          $text = _("Build-Depends");
          $V .= "<tr><td align='right'>$Count</td><td>$text";
          $Val = htmlentities($R['req_value']);
          $Val = preg_replace("@((http|https|ftp)://[^{}<>&[:space:]]*)@i","<a href='\$1'>\$1</a>",$Val);
          $V .= "</td><td>$Val</td></tr>\n";
          $Count++;
        }
        pg_free_result($result);
      }
      $V .= "</table>\n";
      $Count--;
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
    global $PG_CONN;
    $sql = "SELECT * FROM uploadtree INNER JOIN (SELECT * FROM tag_file,tag WHERE tag_pk = tag_fk) T ON uploadtree.pfile_fk = T.pfile_fk WHERE uploadtree.upload_fk = $upload_pk AND uploadtree.lft >= $lft AND uploadtree.rgt <= $rgt UNION SELECT * FROM uploadtree INNER JOIN (SELECT * FROM tag_uploadtree,tag WHERE tag_pk = tag_fk) T ON uploadtree.uploadtree_pk = T.uploadtree_fk WHERE uploadtree.upload_fk = $upload_pk AND uploadtree.lft >= $lft AND uploadtree.rgt <= $rgt ORDER BY ufile_name";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
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
    pg_free_result($result);

    return $VT;
  }

  public function Output()
  {
    $uploadId = GetParm("upload",PARM_INTEGER);
    if (!$this->uploadDao->isAccessible($uploadId, Auth::getGroupId())) return;

    $itemId = GetParm("item",PARM_INTEGER);
    $this->vars['micromenu'] = Dir2Browse("browse", $itemId, NULL, $showBox=0, "View-Meta");
    
    $V="";
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
