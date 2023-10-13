<?php
/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

define("TITLE_ACME_REVIEW", _("ACME Review"));

/**
 * \brief Sort project by count desc
 * \todo  Since this is just a prototype, there is no acme_ars table
 *        This means that you can get incomplete data for an upload that has pfiles
 *        shared with another upload.  You MUST run fo_antelink.php BEFORE
 *        running this plugin on an upload!  If this happens you need to do a
 *          delete from acme_upload where upload_fk=NNNN;
 *        Then run fo_antelink.php followed by this plugin.
 */
function proj_cmp($rowa, $rowb)
{
  $key1 = $rowb['count'] - $rowa['count'];
  if ($key1) {
    return $key1;
  }

  // secondary key - project_name ascending
  return (strnatcasecmp($rowa['project_name'], $rowb['project_name']));
}

class acme_review extends FO_Plugin
{
  var $Name       = "acme_review";
  var $Title      = TITLE_ACME_REVIEW;
  var $Version    = "1.0";
  var $MenuList   = "";
  var $MenuOrder  = 110;
  var $Dependency = array("browse", "view");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;
  var $NoHTML  = 1;  // prevent the http header from being written in case we have to download a file

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    if (GetParm("mod", PARM_STRING) == $this->Name) {
      $detail = GetParm("detail",PARM_INTEGER);
      if ($detail) {
        $text = _("ACME High Level Review");
        $URI = $this->Name . Traceback_parm_keep(array( "page", "upload", "folic")) . "&detail=0";
      } else {
        $text = _("ACME Low Level Review");
        $URI = $this->Name . Traceback_parm_keep(array( "page", "upload", "folic")) . "&detail=1";
      }

      // micro menu when in acme_review
      menu_insert("acme::$text", 1, $URI, $text);
    } else {
      // micro menu item when not in acme_review
      $text2 = _("ACME Review");
      $URI = $this->Name . Traceback_parm_keep(array( "page", "upload")) . "&detail=0";
      menu_insert("Browse::$text2", 1, $URI, $text2);
    }
  } // RegisterMenus()


  /**
   * \brief Find all the acme projects in a hierarchy, starting with $uploadtree_pk.
   *        Once you get an acme hit, don't look further down the hierarch.
   * \param $uploadtreeRow Array containing uploadtree.uploadtree_pk, pfile_fk, lft, rgt
   * \param $acme_project_array, key is acme_project_pk, value is the row array
   */
  function FindACMEProjects($uploadtreeRow, &$acme_project_array)
  {
    global $PG_CONN;

    /* See if there is already an acme project for this pfile */
    $sql = "select acme_project_fk from acme_pfile where pfile_fk='$uploadtreeRow[pfile_fk]' limit 1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) > 0) {
      /* found a project */
      $acme_pfileRow = pg_fetch_assoc($result);

      // retrieve the acme_project record to go with the found acme_project_fk
      $sql = "select * from acme_project where acme_project_pk='$acme_pfileRow[acme_project_fk]'";
      $projresult = pg_query($PG_CONN, $sql);
      DBCheckResult($projresult, $sql, __FILE__, __LINE__);
      if (pg_num_rows($projresult) > 0) {
        $acme_project_array[$acme_pfileRow['acme_project_fk']] = pg_fetch_assoc($projresult);
        $acme_project_array[$acme_pfileRow['acme_project_fk']]['count'] = ($uploadtreeRow['rgt'] - $uploadtreeRow['lft']);
      }
      return;
    } else {
      /* check each child */
      $sql = "select uploadtree_pk, pfile_fk, lft, rgt from uploadtree where parent= $uploadtreeRow[uploadtree_pk]";
      $childrenResult = pg_query($PG_CONN, $sql);
      DBCheckResult($childrenResult, $sql, __FILE__, __LINE__);
      while ($child = pg_fetch_assoc($childrenResult)) {
        $this->FindACMEProjects($child, $acme_project_array);
      }
      pg_free_result($childrenResult);
    }
    pg_free_result($result);

    return;
  } // FindACMEProjects()


  /**
   * \brief Given an upload , return all the unique projects found.
   * \param $upload_pk
   * \param $MinCount unused
   * \return array of acme_project records, including count
   */
  function GetProjectArray0($upload_pk, $nomosAgentpk, $MinCount=1)
  {
    global $PG_CONN;
    $acme_project_array = array();  // acme project array to return

    $sql = "select uploadtree_pk, pfile_fk, lft, rgt from uploadtree where upload_fk='$upload_pk' and parent is null";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $this->FindACMEProjects($row, $acme_project_array);
    pg_free_result($result);

    return $acme_project_array;
  }


  /**
   * \brief Given an upload , return all the unique projects found.
   * \param $upload_pk
   * \param $MinCount minimum file count to be included in returned array, default 1
   * \return array of acme_project records, including count
   */
  function GetProjectArray1($upload_pk, $nomosAgentpk, $MinCount=1)
  {
    global $PG_CONN;
    $acme_project_array = array();  // acme project array to return
    $idx = 0;

    $sql = "select distinct(acme_project_fk) as acme_project_fk, count(acme_project_fk) as filecount from acme_pfile
            right join uploadtree on uploadtree.pfile_fk=acme_pfile.pfile_fk where upload_fk=$upload_pk
            group by acme_project_fk order by filecount desc";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    while ($row = pg_fetch_assoc($result)) {
      if ($row['filecount'] < $MinCount) {
        break;
      }

      // retrieve the acme_project record to go with the found acme_project_fk
      $sql = "select * from acme_project where acme_project_pk='$row[acme_project_fk]'";
      $projresult = pg_query($PG_CONN, $sql);
      DBCheckResult($projresult, $sql, __FILE__, __LINE__);
      if (pg_num_rows($projresult) > 0) {
        $acme_project_array[$idx] = pg_fetch_assoc($projresult);
        $acme_project_array[$idx]['count'] = $row['filecount'];
        $idx++;
      }
    }

    return($acme_project_array);
  } // GetProjectArray1()


  /**
   * \brief create the HTML to display the form showing the found projects
   * \param $acme_project_array
   * \param $upload_pk
   * \return HTML to display results
   */
  function HTMLForm($acme_project_array, $upload_pk)
  {
    $Outbuf = "";
    $uploadtreeRec = GetSingleRec("uploadtree", "where upload_fk=$upload_pk and parent is null");
    $Outbuf .= Dir2Browse($this->Name,$uploadtreeRec['uploadtree_pk'], NULL, 1, "acme");
    $Outbuf .= "<p>";

    $URI = "?mod=" . $this->Name . Traceback_parm_keep(array( "page", "upload", "folic", "detail"));
    $Outbuf .= "<form action='" . $URI . "' method='POST'>\n";

    $Outbuf .= "<table border=1>";
    $Outbuf .= "<tr>";
    $text = _('Include');
    $Outbuf .= "<th>$text</th>";
    $text = _('Project');
    $Outbuf .= "<th>$text</th>";
    $text = _('Files');
    $Outbuf .= "<th>$text</th>";
    $text = _('URL');
    $Outbuf .= "<th>$text</th>";
    $text = _('Description');
    $Outbuf .= "<th>$text</th>";
    $text = _('License');
    $Outbuf .= "<th>$text</th>";
    $text = _('Version');
    $Outbuf .= "<th>$text</th>";
    $Outbuf .= "</tr>";

    /* For alternating row background colors */
    $RowStyle1 = "style='background-color:lavender'";
    $RowStyle2 = "style='background-color:lightyellow'";
    $ColorSpanRows = 1;  // Alternate background color every $ColorSpanRows
    $RowNum = 0;

    if (empty($acme_project_array)) {
      $acme_project_array = array();
    }
    foreach ($acme_project_array as $project) {
      /* Set alternating row background color - repeats every $ColorSpanRows rows */
      $RowStyle = (($RowNum++ % (2*$ColorSpanRows))<$ColorSpanRows) ? $RowStyle1 : $RowStyle2;

      $Outbuf .= "<tr $RowStyle>";
      $Checked = $project['include'] == 't' ? "checked=\"checked\"" : '' ;
      $Outbuf .= "<td><input type='checkbox' name='includeproj[$project[acme_project_pk]]' $Checked></td>\n";
      $Outbuf .= "<td>$project[project_name]</td>";
      $ProjectListURL = Traceback_uri() . "?mod=" . $this->Name . "&acme_project=$project[acme_project_pk]&upload=$upload_pk";
      $Outbuf .= "<td><a href='$ProjectListURL'>$project[count]</a></td>";
      $Outbuf .= "<td><a href='$project[url]'>$project[url]</a></td>";
      $Outbuf .= "<td>" . htmlentities($project['description'], ENT_HTML5 | ENT_QUOTES) . "</td>";
      $Outbuf .= "<td>$project[licenses]</td>";
      $Outbuf .= "<td>$project[version]</td>";
      $Outbuf .= "</tr>";
    }
    $Outbuf .= "</table>";

    $Outbuf .= "$RowNum rows found<br>";

    $text = _("Save and Generate SPDX file");
    $Outbuf .= "<p><input type='submit' value='$text' name='spdxbtn'>\n";
    $text = _("Save");
    $Outbuf .= "&nbsp;&nbsp;&nbsp;<input type='submit' value='$text' name='savebtn'>\n";
    $Outbuf .= "</form>\n";
    /*******  END Input form  *******/

    return $Outbuf;
  }


  /**
   * \brief Populate the acme_upload table for this upload
   * \param $acme_project_array
   * \param $upload_pk
   * \param $detail   0=high level view, 1=low level view
   * \return HTML to display results
   */
  function Populate_acme_upload($acme_project_array, $upload_pk, $detail)
  {
    global $PG_CONN;

    foreach ($acme_project_array as $project) {
      $sql = "insert into acme_upload (upload_fk, acme_project_fk, include, detail, count) values ($upload_pk, $project[acme_project_pk], true, $detail, $project[count])";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    }
  }


  /**
   * \brief Write and return the SPDX file as a string
   * \param $acme_project_array
   * \return SPDX file as string
   */
  function GenerateSPDX($acme_project_array)
  {
    global $SysConf;

    $spdx = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
    $spdx .='<rdf:RDF' . "\n";
    $spdx .= '    xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"' . "\n";
    $spdx .= '    xmlns="http://spdx.org/rdf/terms#"' . "\n";
    $spdx .= '    xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">' . "\n";
    $spdx .= '    <SpdxDocument rdf:about="http://www.spdx.org/tools#SPDXANALYSIS">' . "\n";
    $spdx .= '      <specVersion>SPDX-1.1</specVersion>' . "\n";
    $spdx .= '      <dataLicense rdf:about="http://spdx.org/licenses/PDDL-1.0" />' . "\n";
    $spdx .= '      <CreationInfo>' . "\n";
    $spdx .= " <creator>Tool: FOSSology v " . $SysConf['BUILD']['VERSION'] . " svn " . $SysConf['BUILD']['COMMIT_HASH'] . "</creator>\n";
    $spdx .= "<created>" . date('c') . "</created>\n";   // date-time in ISO 8601 format
    $spdx .= '</CreationInfo>' . "\n";

    $in_encoding = iconv_get_encoding("input_encoding");
    foreach ($acme_project_array as $project) {
      //debugprint($project, "Project");
      $spdx .= "<Package>\n";
      $spdx .= '<name>' . str_replace("&", " and ", strip_tags($project['project_name'])) . '</name>' . "\n";
      $spdx .= "<versionInfo>$project[version]</versionInfo>\n";
      $spdx .= "<licenseDeclared>$project[licenses]</licenseDeclared>\n";
      $spdx .= "<sourceInfo>ProjectURL: $project[url]</sourceInfo>\n";
      $spdx .= '<description>' . str_replace("&", " and ", strip_tags($project['description'])) . '</description>' . "\n";
      $spdx .= "</Package>\n";
    }
    /*
    <packageSupplier>Organization: FSF (info@fsf.com)</packageSupplier>
    <packageOriginator>Organization: FSF (info@fsf.com)</packageOriginator>
    <packageDdownloadLocation>http://ftp.gnu.org/gnu/coreutils/</packageDdownloadLocation>
    <packageFileName>coreutils-8.12.tar.gz</packageFileName>
    <sourceInfo>
                mechanism: git
                repository: git://git.sv.gnu.org/coreutils
                branch: master
                tag: v8.12
    </sourceInfo>
    </Package>
    */

    $spdx .= "  </SpdxDocument> </rdf:RDF>\n";
    return $spdx;
  }


  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    global $Plugins;
    global $PG_CONN;

    //phpinfo();
    $CriteriaCount = 0;
    $V="";
    $GETvars="";
    $upload_pk = GetParm("upload",PARM_INTEGER);
    $detail = GetParm("detail",PARM_INTEGER);
    $detail = empty($detail) ? 0 : 1;
    $folic = GetParm("folic",PARM_INTEGER);
    $savebtn = GetParm("savebtn",PARM_RAW);
    $spdxbtn = GetParm("spdxbtn",PARM_RAW);

    $agent_pk = LatestAgentpk($upload_pk, "nomos_ars");
    if (empty($agent_pk)) {
      echo "Missing fossology license data.  Run a license scan on this upload.<br>";
      exit;
    }
    $uploadtree_tablename = GetUploadtreeTableName($upload_pk);

    // Check if we have data in the acme_upload table, if not then load it
    $acme_uploadRec = GetSingleRec("acme_upload", "where upload_fk=$upload_pk  and detail=$detail");
    if (empty($acme_uploadRec)) {
      // populate acme_upload
      $MinCount = 1;
      $nomosAgentpk = LatestAgentpk($upload_pk, "nomos_ars");
      $acme_project_array = $this->GetProjectArray1($upload_pk, $nomosAgentpk, $MinCount);  // low level
      $this->Populate_acme_upload($acme_project_array, $upload_pk, 1);
      $acme_project_array = $this->GetProjectArray0($upload_pk, $nomosAgentpk, $MinCount); // high level
      $this->Populate_acme_upload($acme_project_array, $upload_pk, 0);
    }

    $sql = "select * from acme_upload, acme_project where acme_project_pk=acme_project_fk and detail=$detail and upload_fk=$upload_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $acme_project_array = pg_fetch_all($result);
    $acme_project_array_orig = $acme_project_array;  // save the original state so we know which records to update

    /* If the save or spdx buttons were clicked, update $acme_project_array and save the data in the acme_upload table */
    if (! empty($savebtn) or ! empty($spdxbtn)) {
      /* First set all projects include to false */
      foreach ($acme_project_array as &$project) {
        $project['include'] = 'f';
      }
      /* Now turn on projects include to match form */
      if (array_key_exists('includeproj', $_POST)) {
        $includeArray = $_POST['includeproj'];
        foreach ($acme_project_array as &$project) {
          if (array_key_exists($project['acme_project_fk'], $includeArray)) {
            $project['include'] = "t";
          }
        }
      }

      /* Finally, update the db with any changed include states */
      $NumRecs = count($acme_project_array);
      for ($i = 0; $i < $NumRecs; $i ++) {
        $project = $acme_project_array[$i];
        $project_orig = $acme_project_array_orig[$i];
        if ($project['include'] != $project_orig['include']) {
          $include = $project['include'] ? "true" : "false";
          $sql = "update acme_upload set include='$include' where acme_upload_pk='$project[acme_upload_pk]'";
          $result = pg_query($PG_CONN, $sql);
          DBCheckResult($result, $sql, __FILE__, __LINE__);
          pg_free_result($result);
        }
      }
    }

    /* aggregate the fossology licenses for each pfile and each acme_project */
    if ($folic) {
      foreach ($acme_project_array as &$project) {
        $sql = "select uploadtree_pk from acme_pfile, uploadtree where acme_project_fk=$project[acme_project_fk]
                and acme_pfile.pfile_fk=uploadtree.pfile_fk and uploadtree.upload_fk=$upload_pk";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $LicArray = array();
        $ItemLicArray = array();
        while ($acme_pfileRow = pg_fetch_assoc($result)) {
          $LicArray = GetFileLicenses($agent_pk, '', $acme_pfileRow['uploadtree_pk'], $uploadtree_tablename);
          foreach ($LicArray as $key => $license) {
            $ItemLicArray[$key] = $license;
          }
        }
        $project['licenses'] = '';
        foreach ($ItemLicArray as $license) {
          if ($license == "No_license_found") {
            continue;
          }
          if (! empty($project['licenses'])) {
            $project['licenses'] .= ", ";
          }
          $project['licenses'] .= $license;
        }
      }
    }

    /* sort $acme_project_array by count desc */
    if (! empty($acme_project_array)) {
      usort($acme_project_array, 'proj_cmp');
    }

    /* generate and download spdx file */
    if (! empty($spdxbtn)) {
      $spdxfile = $this->GenerateSPDX($acme_project_array);
      $rv = DownloadString2File($spdxfile, "SPDX.rdf file", "xml");
      if ($rv !== true) {
        echo $rv;
      }
    }

    switch ($this->OutputType) {
      case "HTML":
        $this->NoHeader = 0;
        $this->OutputOpen("HTML", 1);
        $V .= $this->HTMLForm($acme_project_array, $upload_pk);
        break;
      default:
        break;
    }
    if (! $this->OutputToStdout) {
      return ($V);
    }
    print($V);
    return;
  } // Output()
}
//return;  // prevent anyone from seeing this plugin
$NewPlugin = new acme_review();
$NewPlugin->Initialize();

