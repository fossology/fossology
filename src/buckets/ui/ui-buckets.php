<?php
/*
 SPDX-FileCopyrightText: © 2010-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\UI\Component\MicroMenu;

/**
 * @class ui_buckets
 * Install buckets plugin to UI menu
 */
class ui_buckets extends FO_Plugin
{
  var $uploadtree_tablename = "";   /**< Upload tree on which the upload is listed */

  function __construct()
  {
    $this->Name       = "bucketbrowser";
    $this->Title      = _("Bucket Browser");
    $this->Dependency = array("browse","view");
    $this->DBaccess   = PLUGIN_DB_READ;
    $this->LoginFlag  = 0;
    parent::__construct();
  }

  /**
   * @brief Create and configure database tables
   * @see FO_Plugin::Install()
   */
  function Install()
  {
    global $PG_CONN;

    if (empty($PG_CONN)) {
      return(1);
    } /* No DB */

    /**
     * If there are no bucket pools defined,
     * then create a simple demo.
     * Note: that the bucketpool and two simple bucket definitions
     * are created but no user default bucket pools are set.
     * We don't want to automatically set this to be the
     * default bucket pool because this may not be appropiate for
     * the installation.  The user or system administrator will
     * have to set the default bucket pool in their account settings.
     */

    /* Check if there is already a bucket pool, if there is
     * then return because there is nothing to do.
    */
    $sql = "SELECT bucketpool_pk  FROM bucketpool limit 1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) > 0)
    {
      pg_free_result($result);
      return;
    }

    /* none exist so create the demo */
    $DemoPoolName = "GPL Demo bucket pool";
    $sql = "INSERT INTO bucketpool (bucketpool_name, version, active, description) VALUES ('$DemoPoolName', 1, 'Y', 'Demonstration of a very simple GPL/non-gpl bucket pool')";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    /* get the bucketpool_pk of the newly inserted record */
    $sql = "select bucketpool_pk from bucketpool
              where bucketpool_name='$DemoPoolName' limit 1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $bucketpool_pk = $row['bucketpool_pk'];
    pg_free_result($result);

    /* Insert the bucket_def records */
    $sql = "INSERT INTO bucketpool (bucketpool_name, version, active, description) VALUES ('$DemoPoolName', 1, 'Y', 'Demonstration of a very simple GPL/non-gpl bucket pool')";
    $Columns = "bucket_name, bucket_color, bucket_reportorder, bucket_evalorder, bucketpool_fk, bucket_type, bucket_regex, stopon, applies_to";
    $sql = "INSERT INTO bucket_def ($Columns) VALUES ('GPL Licenses (Demo)', 'orange', 50, 50, $bucketpool_pk, 3, '(affero|gpl)', 'N', 'f');
            INSERT INTO bucket_def ($Columns) VALUES ('non-gpl (Demo)', 'yellow', 50, 1000, $bucketpool_pk, 99, NULL, 'N', 'f')";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    return(0);
  } // Install()

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    // For all other menus, permit coming back here.
    $Item = GetParm("item",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    $bucketpool_pk = GetParm("bp",PARM_INTEGER);
    if (!empty($Item) && !empty($Upload))
    {
      $menuText = "Bucket";
      $menuPosition = 55;
      $URI = $this->Name . Traceback_parm_keep(array("format","page","upload","item","bp"));
      $tooltipText = _("Browse by buckets");
      $this->microMenu->insert(array(MicroMenu::VIEW_META, MicroMenu::VIEW), $menuText, $menuPosition, $this->getName(), $URI, $tooltipText);
      if (GetParm("mod",PARM_STRING) == $this->Name)
      {
        menu_insert("Browse::Bucket",0);
        menu_insert("Browse::[BREAK]",100);
      }
      else
      {
        menu_insert("Browse::Bucket",0,$URI,$tooltipText);
      }
    }
  } // RegisterMenus()


  /**
   * @brief This is called before the plugin is used.
   * It should assume that Install() was already run one time
   * (possibly years ago and not during this object's creation).
   *
   * @return boolean true on success, false on failure.
   * A failed initialize is not used by the system.
   * @note This function must NOT assume that other plugins are installed.
   * @see FO_Plugin::Initialize()
   */
  function Initialize()
  {
    global $_GET;

    if ($this->State != PLUGIN_STATE_INVALID) {
      return(1);
    } // don't re-run
    if ($this->Name !== "") // Name must be defined
    {
      global $Plugins;
      $this->State=PLUGIN_STATE_VALID;
      $Plugins[] = $this;
    }

    return($this->State == PLUGIN_STATE_VALID);
  } // Initialize()


  /**
   * Given an $Uploadtree_pk, display: \n
   * (1) The histogram for the directory BY bucket. \n
   * (2) The file listing for the directory.
   * @param int $Uploadtree_pk
   * @param string $Uri
   * @return string
   */
  function ShowUploadHist($Uploadtree_pk,$Uri)
  {
    global $PG_CONN;

    $VF=""; // return values for file listing
    $VLic=""; // return values for output
    $V=""; // total return value
    $UniqueTagArray = array();
    global $Plugins;

    $ModLicView = &$Plugins[plugin_find_id("view-license")];

    /*******  Get Bucket names and counts  ******/
    /* Find lft and rgt bounds for this $Uploadtree_pk  */
    $sql = "SELECT lft,rgt,upload_fk FROM $this->uploadtree_tablename
              WHERE uploadtree_pk = $Uploadtree_pk";
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

    /* Get the ars_pk of the scan to display, also the select list  */
    $ars_pk = GetArrayVal("ars", $_GET);
    $BucketSelect = SelectBucketDataset($upload_pk, $ars_pk, "selectbdata",
                                        "onchange=\"addArsGo('newds','selectbdata');\"");
    if ($ars_pk == 0)
    {
      /* No bucket data for this upload */
      return $BucketSelect;
    }

    /* Get scan keys */
    $sql = "select agent_fk, nomosagent_fk, bucketpool_fk from bucket_ars where ars_pk=$ars_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $bucketagent_pk = $row["agent_fk"];
    $nomosagent_pk = $row["nomosagent_fk"];
    $bucketpool_pk = $row["bucketpool_fk"];
    pg_free_result($result);

    /* Create bucketDefArray as individual query this is MUCH faster
     than incorporating it with a join in the following queries.
    */
    $bucketDefArray = initBucketDefArray($bucketpool_pk);

    /*select all the buckets for entire tree for this bucketpool */
    $sql = "SELECT distinct(bucket_fk) as bucket_pk,
                   count(bucket_fk) as bucketcount, bucket_reportorder
              from bucket_file, bucket_def,
                  (SELECT distinct(pfile_fk) as PF from $this->uploadtree_tablename
                     where upload_fk=$upload_pk
                       and ((ufile_mode & (1<<28))=0)
                       and ((ufile_mode & (1<<29))=0)
                       and $this->uploadtree_tablename.lft BETWEEN $lft and $rgt) as SS
              where PF=pfile_fk and agent_fk=$bucketagent_pk
                    and bucket_file.nomosagent_fk=$nomosagent_pk
                    and bucket_pk=bucket_fk
                    and bucketpool_fk=$bucketpool_pk
              group by bucket_fk,bucket_reportorder
              order by bucket_reportorder asc";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $historows = pg_fetch_all($result);
    pg_free_result($result);

    /* Show dataset list */
    if (!empty($BucketSelect))
    {
      $action = Traceback_uri() . "?mod=bucketbrowser&upload=$upload_pk&item=$Uploadtree_pk";

      $VLic .= "<script type='text/javascript'>
function addArsGo(formid, selectid )
{
var selectobj = document.getElementById(selectid);
var ars_pk = selectobj.options[selectobj.selectedIndex].value;
document.getElementById(formid).action='$action'+'&ars='+ars_pk;
document.getElementById(formid).submit();
return;
}
</script>";

      /* form to select new dataset (ars_pk) */
      $VLic .= "<form action='$action' id='newds' method='POST'>\n";
      $VLic .= $BucketSelect;
      $VLic .= "</form>";
    }

    $sql = "select bucketpool_name, version from bucketpool where bucketpool_pk=$bucketpool_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $bucketpool_name = $row['bucketpool_name'];
    $bucketpool_version = $row['version'];
    pg_free_result($result);

    /* Write bucket histogram to $VLic  */
    $bucketcount = 0;
    $Uniquebucketcount = 0;
    $NoLicFound = 0;
    if (is_array($historows))
    {
      $text = _("Bucket Pool");
      $VLic .= "$text: $bucketpool_name v$bucketpool_version<br>";
      $VLic .= "<table border=1 width='100%'>\n";
      $text = _("Count");
      $VLic .= "<tr><th width='10%'>$text</th>";
      $text = _("Files");
      $VLic .= "<th width='10%'>$text</th>";
      $text = _("Bucket");
      $VLic .= "<th align='left'>$text</th></tr>\n";

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
        $text = _("Show");
        $VLic .= "?mod=list_bucket_files&bapk=$bucketagent_pk&item=$Uploadtree_pk&bpk=$bucket_pk&bp=$bucketpool_pk&napk=$nomosagent_pk" . "'>$text</a></td>";

        /*  Bucket name  */
        $VLic .= "<td align='left'>";
        $VLic .= "<a id='$bucket_pk' onclick='FileColor_Get(\"" . Traceback_uri() . "?mod=ajax_filebucket&bapk=$bucketagent_pk&item=$Uploadtree_pk&bucket_pk=$bucket_pk\")'";
        $VLic .= ">$bucket_name </a>";

        /* Allow users to tag an entire bucket */
/* Future, maybe v 2.1
        $TagHref = "<a href=" . Traceback_uri() . "?mod=bucketbrowser&upload=$upload_pk&item=$Uploadtree_pk&bapk=$bucketagent_pk&bpk=$bucket_pk&bp=$bucketpool_pk&napk=$nomosagent_pk&tagbucket=1>Tag</a>";
        $VLic .= " [$TagHref]";
*/

        $VLic .= "</td>";
        $VLic .= "</tr>\n";
        //      if ($row['bucket_name'] == "No Buckets Found") $NoLicFound =  $row['bucketcount'];
      }
      $VLic .= "</table>\n";
      $VLic .= "<p>\n";
      $text = _("Unique buckets");
      $VLic .= "$text: $Uniquebucketcount<br>\n";
    }


    /*******    File Listing     ************/
    /* Get ALL the items under this Uploadtree_pk */
    $Children = GetNonArtifactChildren($Uploadtree_pk, $this->uploadtree_tablename);

    if (count($Children) == 0)
    {
      $sql = "SELECT * FROM $this->uploadtree_tablename WHERE uploadtree_pk = '$Uploadtree_pk'";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $row = pg_fetch_assoc($result);
      pg_free_result($result);
      if (empty($row) || (IsDir($row['ufile_mode']))) {
        return;
      }
      // $ModLicView = &$Plugins[plugin_find_id("view-license")];
      // return($ModLicView->Output() );
    }
    $ChildCount=0;
    $Childbucketcount=0;

    /* Countd disabled until we know we need them
     $NumSrcPackages = 0;
    $NumBinPackages = 0;
    $NumBinNoSrcPackages = 0;
    */

    /* get mimetypes for packages */
    $MimetypeArray = GetPkgMimetypes();

    $VF .= "<table border=0>";
    foreach($Children as $C)
    {
      if (empty($C)) {
        continue;
      }

      /* update package counts */
      /* This is an expensive count.  Comment out until we know we really need it
       IncrSrcBinCounts($C, $MimetypeArray, $NumSrcPackages, $NumBinPackages, $NumBinNoSrcPackages);
      */

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
        $uploadtree_pk = DirGetNonArtifact($C['uploadtree_pk'], $this->uploadtree_tablename);
        $tmpuri = "?mod=" . $this->Name . Traceback_parm_keep(array("upload","folder","ars"));
        $LicUri = "$tmpuri&item=" . $uploadtree_pk;
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
      if ($IsDir) {
        $VF .= "/";
      }
      if ($HasBold) {
        $VF .= "</b>";
      }
      if ($HasHref) {
        $VF .= "</a>";
      }

      /* print buckets */
      $VF .= "<br>";
      $VF .= "<span style='position:relative;left:1em'>";
      /* get color coded string of bucket names */
      $VF .= GetFileBuckets_string($nomosagent_pk, $bucketagent_pk, $C['uploadtree_pk'],
      $bucketDefArray, ",", True);
      $VF .= "</span>";
      $VF .= "</td><td valign='top'>";

      /* display item links */
        $VF .= FileListLinks($C['upload_fk'], $C['uploadtree_pk'], $nomosagent_pk, $C['pfile_fk'], True, $UniqueTagArray, $this->uploadtree_tablename);
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
    /* Counts disabled above until we know we need these
     $VLic .= "<ul>";
    $text = _("source packages");
    $VLic .= "<li> $NumSrcPackages $text";
    $text = _("binary packages");
    $VLic .= "<li> $NumBinPackages $text";
    $text = _("binary packages with no source package");
    $VLic .= "<li> $NumBinNoSrcPackages $text";
    $VLic .= "</ul>";
    */

    /* Combine VF and VLic */
    $V .= "<table border=0 width='100%'>\n";
    $V .= "<tr><td valign='top' width='50%'>$VLic</td><td valign='top'>$VF</td></tr>\n";
    $V .= "</table>\n";
    $V .= "<hr />\n";

    return($V);
  } // ShowUploadHist()


  /**
   * @brief Tag a bucket
   * @param int $upload_pk
   * @param int $uploadtree_pk
   * @param int $bucketagent_pk
   * @param int $bucket_pk
   * @param int $bucketpool_pk
   * @param int $nomosagent_pk
   **/
  function TagBucket($upload_pk, $uploadtree_pk, $bucketagent_pk, $bucket_pk, $bucketpool_pk, $nomosagent_pk)
  {
  }  // TagBucket()


  /**
   * @brief This function returns the scheduler status.
   * @see FO_Plugin::Output()
   */
  public function Output()
  {
    $uTime = microtime(true);
    $V="";
    $Upload = GetParm("upload",PARM_INTEGER);
    /** @var UploadDao $uploadDao */
    $uploadDao = $GLOBALS['container']->get('dao.upload');
    if ( !$uploadDao->isAccessible($Upload, Auth::getGroupId()) )
    {
      $text = _("Permission Denied");
      return "<h2>$text</h2>";
    }

    $Item = GetParm("item",PARM_INTEGER);
    if ( !$Item)
    {
     return _('No item selected');
    }
    $updcache = GetParm("updcache",PARM_INTEGER);
    $tagbucket = GetParm("tagbucket",PARM_INTEGER);

    $this->uploadtree_tablename = GetUploadtreeTableName($Upload);

    /* Remove "updcache" from the GET args and set $this->UpdCache
     * This way all the url's based on the input args won't be
     * polluted with updcache
     * Use Traceback_parm_keep to ensure that all parameters are in order
     */
    $CacheKey = "?mod=" . $this->Name . Traceback_parm_keep(array("upload","item","folder","ars")) ;
    if ($updcache)
    {
      $_SERVER['REQUEST_URI'] = preg_replace("/&updcache=[0-9]*/","",$_SERVER['REQUEST_URI']);
      unset($_GET['updcache']);
      $V = ReportCachePurgeByKey($CacheKey);
    }
    else
    {
      $V = ReportCacheGet($CacheKey);
    }

    if (!empty($tagbucket))
    {
      $bucketagent_pk = GetParm("bapk",PARM_INTEGER);
      $bucket_pk = GetParm("bpk",PARM_INTEGER);
      $bucketpool_pk = GetParm("bp",PARM_INTEGER);
      $nomosagent_pk = GetParm("napk",PARM_INTEGER);
      $this->TagBucket($Upload, $Item, $bucketagent_pk, $bucket_pk, $bucketpool_pk, $nomosagent_pk);
    }

    $Cached = !empty($V);
    if(!$Cached)
    {
      $V .= "<font class='text'>\n";

      $Children = GetNonArtifactChildren($Item, $this->uploadtree_tablename);
      if (count($Children) == 0) // no children, display View-Meta micromenu
        $V .= Dir2Browse($this->Name,$Item,NULL,1,"View-Meta", -1, '', '', $this->uploadtree_tablename) . "<P />\n";
      else // has children, display Browse micormenu
        $V .= Dir2Browse($this->Name,$Item,NULL,1,"Browse", -1, '', '', $this->uploadtree_tablename) . "<P />\n";

      if (!empty($Upload))
      {
        $Uri = preg_replace("/&item=([0-9]*)/","",Traceback());
        $V .= $this->ShowUploadHist($Item,$Uri);
      }
      $V .= "</font>\n";
      $text = _("Loading...");
    }

    $Time = microtime(true) - $uTime;  // convert usecs to secs
    $text = _("Elapsed time: %.2f seconds");
    $V .= sprintf( "<p><small>$text</small>", $Time);

    if ($Cached){
      $text = _("cached");
      $text1 = _("Update");
      echo " <i>$text</i>   <a href=\"$_SERVER[REQUEST_URI]&updcache=1\"> $text1 </a>";
    }
    else if ($Time > 0.5)
    {
      ReportCachePut($CacheKey, $V);
    }
    return $V;
  }

}

$NewPlugin = new ui_buckets;
$NewPlugin->Initialize();
