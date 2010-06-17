<?php
/***********************************************************
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

class ui_buckets extends FO_Plugin
{
  var $Name       = "bucketbrowser";
  var $Title      = "Bucket Browser";
  var $Version    = "1.0";
  var $Dependency = array("db","browse","view");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;
  var $UpdCache   = 0;

  /***********************************************************
   Install(): Create and configure database tables
   ***********************************************************/
  function Install()
  {
    global $DB;
    if (empty($DB)) { return(1); } /* No DB */

    return(0);
  } // Install()

  /***********************************************************
   RegisterMenus(): Customize submenus.
   ***********************************************************/
  function RegisterMenus()
  {
    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array("show","format","page","upload","item","bp"));
    $Item = GetParm("item",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    $bucketpool_pk = GetParm("bp",PARM_INTEGER);
    if (!empty($Item) && !empty($Upload))
    {
      if (GetParm("mod",PARM_STRING) == $this->Name)
      {
       menu_insert("Browse::Bucket Browser",1);
       //menu_insert("Browse::[BREAK]",100);
       //menu_insert("Browse::Clear",101,NULL,NULL,NULL,"<a href='javascript:LicColor(\"\",\"\",\"\",\"\");'>Clear</a>");
      }
      else
      {
       menu_insert("Browse::Bucket Browser",10,$URI,"Browse by buckets (categories)");
      }
    }
  } // RegisterMenus()


  /***********************************************************
   Initialize(): This is called before the plugin is used.
   It should assume that Install() was already run one time
   (possibly years ago and not during this object's creation).
   Returns true on success, false on failure.
   A failed initialize is not used by the system.
   NOTE: This function must NOT assume that other plugins are installed.
   ***********************************************************/
  function Initialize()
  {
    global $_GET;

    if ($this->State != PLUGIN_STATE_INVALID) { return(1); } // don't re-run
    if ($this->Name !== "") // Name must be defined
    {
      global $Plugins;
      $this->State=PLUGIN_STATE_VALID;
      array_push($Plugins,$this);
    }

    /* Remove "updcache" from the GET args and set $this->UpdCache
     * This way all the url's based on the input args won't be
     * polluted with updcache
     */
    if ($_GET['updcache'])
    {
      $this->UpdCache = $_GET['updcache'];
      $_SERVER['REQUEST_URI'] = preg_replace("/&updcache=[0-9]*/","",$_SERVER['REQUEST_URI']);
      unset($_GET['updcache']);
    }
    else
    {
      $this->UpdCache = 0;
    }
    return($this->State == PLUGIN_STATE_VALID);
  } // Initialize()


  /***********************************************************
   ShowUploadHist(): Given an $Uploadtree_pk, display:
   (1) The histogram for the directory BY bucket.
   (2) The file listing for the directory.
   ***********************************************************/
  function ShowUploadHist($Uploadtree_pk,$Uri)
  {
    global $PG_CONN;

    $VF=""; // return values for file listing
    $VLic=""; // return values for output
    $V=""; // total return value
    global $Plugins;
    global $DB;

    $ModLicView = &$Plugins[plugin_find_id("view-license")];

    /*******  Get Bucket names and counts  ******/
    /* Find lft and rgt bounds for this $Uploadtree_pk  */
    $sql = "SELECT lft,rgt,upload_fk FROM uploadtree 
              WHERE uploadtree_pk = $Uploadtree_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1)
    {
      pg_free_result($result);
      return "<h2>Invalid URL, nonexistant item $Uploadtree_pk</h2>";
    }
    $row = pg_fetch_assoc($result);
    $lft = $row["lft"];
    $rgt = $row["rgt"];
    $upload_pk = $row["upload_fk"];
    pg_free_result($result);

    $Agent_name = "buckets";
    $Agent_desc = "Bucket agent (categorizes files)";
    if (array_key_exists("agent_pk", $_POST))
      $bucketagent_pk = $_POST["agent_pk"];
    else
      $bucketagent_pk = GetAgentKey($Agent_name, $Agent_desc);

    /*  Get the counts for each bucket under this UploadtreePk, skip artifacts 
        Because buckets roll up, the counts will be high (a bucket will be counted
        for the container and everything under the container).
     */
   /* get the default_bucketpool_fk from the users record */
    $sql = "select default_bucketpool_fk from users where user_pk='$_SESSION[UserId]' limit 1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $bucketpool_pk = $row['default_bucketpool_fk'];
    pg_free_result($result);
    if (!$bucketpool_pk) 
    {
      if ($_SESSION['UserLevel'] < PLUGIN_DB_DOWNLOAD)
      {
        echo "<h3>Your FOSSology system administrator has not enabled read only users to use use the bucket view</h3>";
      }
      else
      {
        echo "<h3>You do not have a bucketpool in your account settings.  <br>";
        echo "Edit your user settings (Admin > Users > Account Settings) to select a default bucketpool.</h3>";
      }
      exit(1);
    }

    /* find latest bucket and nomos agent that has data */
    $AgentRec = AgentARSList("bucket_ars", $upload_pk, 0);
    if ($AgentRec === false)
    {
      $VLic .= "<h3>No data available.  Use Jobs > Agents to schedule a license scan.</h3>";
      return $VLic;
    }
    /* loop through $AgentRec to verify that the nomosagent_pk is enabled */
    $nomosagent_pk = 0;
    foreach ($AgentRec as $AgentRow)
    {
      $sql = "select agent_pk from agent where agent_pk=$AgentRow[nomosagent_fk] 
                    and agent_enabled=true";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      if (pg_num_rows($result) > 0) $nomosagent_pk = $AgentRow['nomosagent_fk'];
    }

    /* Create bucketDefArray as individual query this is MUCH faster
       than incorporating it with a join in the following queries.
     */
    $bucketDefArray = initBucketDefArray($bucketpool_pk);

    /*select all the buckets for entire tree */
    $sql = "SELECT distinct(bucket_fk) as bucket_pk, 
                   count(bucket_fk) as bucketcount
              from bucket_file, 
                  (SELECT distinct(pfile_fk) as PF from uploadtree 
                     where upload_fk=$upload_pk 
                       and ((ufile_mode & (1<<28))=0)
                       and uploadtree.lft BETWEEN $lft and $rgt) as SS
              where PF=pfile_fk and agent_fk=$bucketagent_pk 
                    and bucket_file.nomosagent_fk=$nomosagent_pk
              group by bucket_fk 
              order by bucketcount desc"; 
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $historows = pg_fetch_all($result);
      pg_free_result($result);

    /* Get agent list */
    $VLic .= "<form action='" . Traceback_uri()."?" . $_SERVER["QUERY_STRING"] . "' method='POST'>\n";

/* FUTURE advanced interface for selecting agents, don't display unless there are >1 choice
    $AgentSelect = AgentSelect($Agent_name, $upload_pk, "bucket_file", true, "agent_pk", $bucketagent_pk);
    $VLic .= $AgentSelect;
    $VLic .= "<input type='submit' value='Go'>";
*/

    /* Write bucket histogram to $VLic  */
    $bucketcount = 0;
    $Uniquebucketcount = 0;
    $NoLicFound = 0;
    $VLic .= "<table border=1 width='100%'>\n";
    $VLic .= "<tr><th width='10%'>Count</th>";
    $VLic .= "<th width='10%'>Files</th>";
    $VLic .= "<th>Bucket</th></tr>\n";

    foreach($historows as $bucketrow)
    {
      $Uniquebucketcount++;
      $bucket_pk = $bucketrow['bucket_pk'];
      $bucketcount = $bucketrow['bucketcount'];
      $bucket_name = $bucketDefArray[$bucket_pk]['bucket_name'];
      $bucket_color = $bucketDefArray[$bucket_pk]['bucket_color'];

      /*  Count  */
      $VLic .= "<tr><td align='right' style='background-color:$bucket_color'>$bucketcount</td>";

      /*  Show  */
      $VLic .= "<td align='center'><a href='";
      $VLic .= Traceback_uri();
      $VLic .= "?mod=list_bucket_files&bapk=$bucketagent_pk&item=$Uploadtree_pk&bpk=$bucket_pk&bp=$bucketpool_pk&napk=$nomosagent_pk" . "'>Show</a></td>";

      /*  Bucket name  */
      $VLic .= "<td align='left'>";
      $VLic .= "<a id='$bucket_pk' onclick='FileColor_Get(\"" . Traceback_uri() . "?mod=ajax_filebucket&bapk=$bucketagent_pk&item=$Uploadtree_pk&bucket_pk=$bucket_pk\")'";
      $VLic .= ">$bucket_name </a>";
      $VLic .= "</td>";
      $VLic .= "</tr>\n";
//      if ($row['bucket_name'] == "No Buckets Found") $NoLicFound =  $row['bucketcount'];
    }
    $VLic .= "</table>\n";
    $VLic .= "<p>\n";
    $VLic .= "Unique buckets: $Uniquebucketcount<br>\n";


    /*******    File Listing     ************/
    /* Get ALL the items under this Uploadtree_pk */
    $Children = GetNonArtifactChildren($Uploadtree_pk);

    if (!is_array($Children))
    {
      $Results = $DB->Action("SELECT * FROM uploadtree WHERE uploadtree_pk = '$Uploadtree_pk'");
      if (empty($Results) || (IsDir($Results[0]['ufile_mode']))) { return; }
      $ModLicView = &$Plugins[plugin_find_id("view-license")];
      return($ModLicView->Output() );
    }
    $ChildCount=0;
    $Childbucketcount=0;
    $NumSrcPackages = 0;
    $NumBinPackages = 0;
    $NumBinNoSrcPackages = 0;

    /* get mimetypes for packages */
    $MimetypeArray = GetPkgMimetypes(); 

    $VF .= "<table border=0>";
    foreach($Children as $C)
    {
      if (empty($C)) { continue; }

      /* update package counts */
      IncrSrcBinCounts($C, $MimetypeArray, $NumSrcPackages, $NumBinPackages, $NumBinNoSrcPackages);

      $IsDir = Isdir($C['ufile_mode']);
      $IsContainer = Iscontainer($C['ufile_mode']);

      /* Determine the hyperlink for non-containers to view-license  */
      if (!empty($C['pfile_fk']) && !empty($ModLicView))
      {
        $LinkUri = Traceback_uri();
        $LinkUri .= "?mod=view-license&napk=$nomosagent_pk&bapk=$bucketagent_pk&upload=$upload_pk&item=$C[uploadtree_pk]";
      }
      else
      {
        $LinkUri = NULL;
      }

      /* Determine link for containers */
      if (Iscontainer($C['ufile_mode']))
      {
        $uploadtree_pk = DirGetNonArtifact($C['uploadtree_pk']);
        $LicUri = "$Uri&item=" . $uploadtree_pk;
      }
      else
      {
        $LicUri = NULL;
      }

      /* Populate the output ($VF) - file list */
      /* id of each element is its uploadtree_pk */
      $VF .= "<tr><td id='$C[uploadtree_pk]' align='left'>";
      $HasHref=0;
      $HasBold=0;
      if ($IsContainer)
      {
        $VF .= "<a href='$LicUri'>"; $HasHref=1;
        $VF .= "<b>"; $HasBold=1;
      }
      else if (!empty($LinkUri)) 
      {
        $VF .= "<a href='$LinkUri'>"; $HasHref=1;
      }
      $VF .= $C['ufile_name'];
      if ($IsDir) { $VF .= "/"; };
      if ($HasBold) { $VF .= "</b>"; }
      if ($HasHref) { $VF .= "</a>"; }

      /* print buckets */
      $BuckArray = GetFileBuckets($nomosagent_pk, $bucketagent_pk, $C['uploadtree_pk']);
      $VF .= "<br>";
      $VF .= "<span style='position:relative;left:1em'>";
      /* get color coded string of bucket names */
      $VF .= GetFileBuckets_string($nomosagent_pk, $bucketagent_pk, $C['uploadtree_pk'],
                 $BuckArray, $bucketDefArray, ",", True);
      $VF .= "</span>";
      $VF .= "</td><td valign='top'>";

      /* display file links if this is really a file */
      if (!empty($C['pfile_fk']))
        $VF .= FileListLinks($C['upload_fk'], $C['uploadtree_pk'], $nomosagent_pk);
      $VF .= "</td>";
      $VF .= "</tr>\n";

      $ChildCount++;
    }
    $VF .= "</table>\n";

    $V .= ActiveHTTPscript("FileColor");

    /* Add javascript for color highlighting 
       This is the response script needed by ActiveHTTPscript 
       responseText is bucket_pk',' followed by a comma seperated list of uploadtree_pk's */
    $script = "
      <script type=\"text/javascript\" charset=\"utf-8\">
        var Lastutpks='';   /* save last list of uploadtree_pk's */
        var Lastbupk='';   /* save last bucket_pk */
        var color = '#4bfe78';
        function FileColor_Reply()
        {
          if ((FileColor.readyState==4) && (FileColor.status==200))
          {
            /* remove previous highlighting */
            var numpks = Lastutpks.length;
            if (numpks > 0) document.getElementById(Lastbupk).style.backgroundColor='white';
            while (numpks)
            {
              document.getElementById(Lastutpks[--numpks]).style.backgroundColor='white';
            }

            utpklist = FileColor.responseText.split(',');
            Lastbupk = utpklist.shift();
            numpks = utpklist.length;
            Lastutpks = utpklist;

            /* apply new highlighting */
            elt = document.getElementById(Lastbupk);
            if (elt != null) elt.style.backgroundColor=color;
            while (numpks)
            {
              document.getElementById(utpklist[--numpks]).style.backgroundColor=color;
            }
          }
          return;
        }
      </script>
    ";
    $V .= $script;

    /* Display source, binary, and binary missing source package counts */
    $VLic .= "<ul>";
    $VLic .= "<li> $NumSrcPackages source packages";
    $VLic .= "<li> $NumBinPackages binary packages";
    $VLic .= "<li> $NumBinNoSrcPackages binary packages with no source package";
    $VLic .= "</ul>";

    /* Combine VF and VLic */
    $V .= "<table border=0 width='100%'>\n";
    $V .= "<tr><td valign='top' width='50%'>$VLic</td><td valign='top'>$VF</td></tr>\n";
    $V .= "</table>\n";
    $V .= "<hr />\n";

    return($V);
  } // ShowUploadHist()

  /***********************************************************
   Output(): This function returns the scheduler status.
   ***********************************************************/
  function Output()
  {
    $uTime = microtime(true);
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    $V="";
    $Folder = GetParm("folder",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);
    $updcache = GetParm("updcache",PARM_INTEGER);
    if ($updcache)
    {
      $this->UpdCache = $_GET['updcache'];
    }
    else
    {
      $this->UpdCache = 0;
    }

    switch(GetParm("show",PARM_STRING))
    {
    case 'detail':
      $Show='detail';
      break;
    case 'summary':
    default:
      $Show='summary';
    }

    /* Use Traceback_parm_keep to ensure that all parameters are in order */
    $CacheKey = "?mod=" . $this->Name . Traceback_parm_keep(array("upload","item","folder")) . "&show=$Show";
    if ($this->UpdCache != 0)
    {
      $V = "";
      $Err = ReportCachePurgeByKey($CacheKey);
    }
    else
      $V = ReportCacheGet($CacheKey);

    if (empty($V) )  // no cache exists
    {
      switch($this->OutputType)
      {
      case "XML":
        break;
      case "HTML":
        $V .= "<font class='text'>\n";

        /************************/
        /* Show the folder path */
        /************************/
        $V .= Dir2Browse($this->Name,$Item,NULL,1,"Browse") . "<P />\n";

        if (!empty($Upload))
        {
          $Uri = preg_replace("/&item=([0-9]*)/","",Traceback());
          $V .= $this->ShowUploadHist($Item,$Uri);
        }
        $V .= "</font>\n";
/*$V .= "<div id='ajax_waiting'><img src='images/ajax-loader.gif'>Loading...</div>"; */
        break;
      case "Text":
        break;
      default:
      }

      $Cached = false;
    }
    else
      $Cached = true;

    if (!$this->OutputToStdout) { return($V); }
    print "$V";
    $Time = microtime(true) - $uTime;  // convert usecs to secs
    printf( "<small>Elapsed time: %.2f seconds</small>", $Time);

    if ($Cached) 
      echo " <i>cached</i>   <a href=\"$_SERVER[REQUEST_URI]&updcache=1\"> Update </a>";
    else
    {
      /*  Cache Report if this took longer than 1/2 second*/
      if ($Time > 0.5) ReportCachePut($CacheKey, $V);
    }

    return;
  }

};

$NewPlugin = new ui_buckets;
$NewPlugin->Initialize();

?>
