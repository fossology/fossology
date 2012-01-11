<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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

define("TITLE_ui_view_info", _("View File Information"));

class ui_view_info extends FO_Plugin
{
  var $Name       = "view_info";
  var $Title      = TITLE_ui_view_info;
  var $Version    = "1.0";
  var $Dependency = array("browse");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;

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
    $Offset = $Page * $Max;

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
      $BytesH = Bytes2Human($Bytes);
      $Bytes = number_format($Bytes, 0, "", ",");
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

    $agent_status = AgentARSList('pkgagent_ars', $Upload);
    if (empty($agent_status))
    {
      $V .= _("No data available. Use Jobs > Agents to schedule a pkgagent scan.");
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

        $text = _("Package");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['pkg_name']) . "</td></tr>\n";
        $Count++;

        $text = _("Alias");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['pkg_alias']) . "</td></tr>\n";
        $Count++;
        $text = _("Architecture");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['pkg_arch']) . "</td></tr>\n";
        $Count++;
        $text = _("Version");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['version']) . "</td></tr>\n";
        $Count++;

        $text = _("License");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['license']) . "</td></tr>\n";
        $Count++;
        $text = _("Group");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['pkg_group']) . "</td></tr>\n";
        $Count++;
        $text = _("Packager");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['packager']) . "</td></tr>\n";
        $Count++;
        $text = _("Release");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['release']) . "</td></tr>\n";
        $Count++;
        $text = _("BuildDate");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['build_date']) . "</td></tr>\n";
        $Count++;
        $text = _("Vendor");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['vendor']) . "</td></tr>\n";
        $Count++;
        $text = _("URL");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['url']) . "</td></tr>\n";
        $Count++;
        $text = _("Summary");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['summary']) . "</td></tr>\n";
        $Count++;
        $text = _("Description");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['description']) . "</td></tr>\n";
        $Count++;
        $text = _("Source");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['source_rpm']) . "</td></tr>\n";
        $Count++;
      
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

        $text = _("Package");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['pkg_name']) . "</td></tr>\n";
        $Count++;

        $text = _("Architecture");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['pkg_arch']) . "</td></tr>\n";
        $Count++;
        $text = _("Version");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['version']) . "</td></tr>\n";
        $Count++;
        $text = _("Section");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['section']) . "</td></tr>\n";
        $Count++;
        $text = _("Priority");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['priority']) . "</td></tr>\n";
        $Count++;
        $text = _("Installed Size");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['installed_size']) . "</td></tr>\n";
        $Count++;
        $text = _("Maintainer");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['maintainer']) . "</td></tr>\n";
        $Count++;
        $text = _("Homepage");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['homepage']) . "</td></tr>\n";
        $Count++;
        $text = _("Source");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['source']) . "</td></tr>\n";
        $Count++;
        $text = _("Summary");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['summary']) . "</td></tr>\n";
        $Count++;
        $text = _("Description");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['description']) . "</td></tr>\n";
        $Count++;

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

      while ($R = pg_fetch_assoc($result) and !empty($R['pkg_pk']))
      {
        $Require = $R['pkg_pk'];

        $text = _("Format");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['format']) . "</td></tr>\n";
        $Count++;

        $text = _("Source");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['source']) . "</td></tr>\n";
        $Count++;
        $text = _("Binary");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['pkg_name']) . "</td></tr>\n";
        $Count++;
        $text = _("Architecture");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['pkg_arch']) . "</td></tr>\n";
        $Count++;
        $text = _("Version");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['version']) . "</td></tr>\n";
        $Count++;
        $text = _("Maintainer");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['maintainer']) . "</td></tr>\n";
        $Count++;
        $text = _("Uploaders");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['uploaders']) . "</td></tr>\n";
        $Count++;
        $text = _("Standards-Version");
        $V .= "<tr><td align='right'>$Count</td><td>$text";
        $V .= "</td><td>" . htmlentities($R['standards_version']) . "</td></tr>\n";
        $Count++;
      
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

    global $PG_CONN;
    /* Find lft and rgt bounds for this $Uploadtree_pk  */
    $sql = "SELECT lft,rgt,upload_fk FROM uploadtree WHERE uploadtree_pk = $Item;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1)
    {
      pg_free_result($result);
      $text = _("Invalid URL, nonexistant item");
      return "<h2>$text $Uploadtree_pk</h2>";
    }

    $row = pg_fetch_assoc($result);
    $lft = $row["lft"];
    $rgt = $row["rgt"];
    $upload_pk = $row["upload_fk"];
    pg_free_result($result);

    $sql = "SELECT * FROM uploadtree INNER JOIN (SELECT * FROM tag_file,tag,tag_ns WHERE tag_pk = tag_fk AND tag_ns_fk = tag_ns_pk) T ON uploadtree.pfile_fk = T.pfile_fk WHERE uploadtree.upload_fk = $upload_pk AND uploadtree.lft >= $lft AND uploadtree.rgt <= $rgt UNION SELECT * FROM uploadtree INNER JOIN (SELECT * FROM tag_uploadtree,tag,tag_ns WHERE tag_pk = tag_fk AND tag_ns_fk = tag_ns_pk) T ON uploadtree.uploadtree_pk = T.uploadtree_fk WHERE uploadtree.upload_fk = $upload_pk AND uploadtree.lft >= $lft AND uploadtree.rgt <= $rgt ORDER BY ufile_name";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) > 0)
    {
      $VT .= "<table border=1>\n";
      $text = _("FileName");
      $text1 = _("Tag Namespace");
      $text2 = _("Tag");
      $VT .= "<tr><th>$text</th><th>$text1</th><th>$text2</th><th></th></tr>\n";
      while ($row = pg_fetch_assoc($result))
      {
        $VT .= "<tr><td align='center'>" . $row['ufile_name'] . "</td><td align='center'>" . $row['tag_ns_name'] . "</td><td align='center'>" . $row['tag'] . "</td>";
        $perm = GetTaggingPerms($_SESSION['UserId'],$row['tag_ns_fk']);
        if ($perm > 0){
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

  /**
   * \brief This function is called when user output is
   * requested.  This function is responsible for content.
   * (OutputOpen and Output are separated so one plugin
   * can call another plugin's Output.)
   * This uses $OutputType.
   * The $ToStdout flag is "1" if output should go to stdout, and
   * 0 if it should be returned as a string.  (Strings may be parsed
   * and used by other plugins.)
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }

    $Folder = GetParm("folder",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);

    $V="";
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $V .= Dir2Browse("browse", $Item, NULL, 1, "View-Meta");  
        $V .= $this->ShowTagInfo($Upload, $Item);
        $V .= $this->ShowPackageinfo($Upload, $Item, 1);
        $V .= $this->ShowMetaView($Upload, $Item);
        $V .= $this->ShowSightings($Upload, $Item);
        $V .= $this->ShowView($Upload, $Item);
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
  }

};
$NewPlugin = new ui_view_info;
$NewPlugin->Initialize();
?>
