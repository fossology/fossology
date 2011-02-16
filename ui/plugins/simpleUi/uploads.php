<?php
/*
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
 */

/**
 * \brief
 *
 * @version "$Id $"
 * Created on Feb 11, 2011 by Mark Donohoe
 */

define("TITLE_uploads", _("Uploads"));

class uploads extends FO_Plugin
{
  public $Name = "uploads";
  public $Title = TITLE_uploads;
  public $version = "1.0";
  public $MenuList = "Uploads";
  //public $MenuTarget = "uploadajax";
  public $Dependency = array("db", "agent_unpack");
  public $DBaccess = PLUGIN_DB_UPLOAD;

  /**
   * AnalyzFile(): Analyze one uploaded file.
   *
   * @param string $FilePath the filepath to the file to analyze.
   * @return string $V, html to display the results.
   *
   */
  function AnalyzeFile($FilePath) {

    global $Plugins;
    global $AGENTDIR;

    $licenses = array();
    $licenseResult = "";
    /* move the temp file */
    $licenseResult = exec("$AGENTDIR/nomos $FilePath",$out,$rtn);
    $licenses = explode(' ',$out[0]);
    $last = end($licenses);
    return ($last);

  } // AnalyzeFile()

  /**
   * AnalyzeOne(): Analyze for copyrights, emails and url's in one uploaded file.
   *
   */
  function AnalyzeOne($Highlight)
  {

    global $Plugins;
    global $AGENTDIR;
    global $DATADIR;

    $ModBack = GetParm("modback",PARM_STRING);

    $V = "";
    $View = & $Plugins[plugin_find_id("view") ];
    $TempFile = $_FILES['licfile']['tmp_name'];
    $Sys = $AGENTDIR."/copyright -c $TempFile";
    $Fin = popen($Sys, "r");
    $colors = Array();
    $colors['statement'] = 0;
    $colors['email'] = 1;
    $colors['url'] = 2;
    $stuff = Array();
    $stuff['statement'] = Array();
    $stuff['email'] = Array();
    $stuff['url'] = Array();
    //$uri = Traceback_uri();
    //$toUploads = "<a href='$uri?mod=uploads>Back to Uploads</a>\n";
    //echo $toUploads;
    while (!feof($Fin))
    {
      $Line = fgets($Fin);
      if (strlen($Line) > 0)
      {
        //print $Line;
        $match = array();
        preg_match_all("/\t\[(?P<start>\d+)\:(?P<end>\d+)\:(?P<type>[A-Za-z]+)\] \'(?P<content>.+)\'/", $Line, $match);
        //print_r($match);
        if (!empty($match['start']))
        {
          $stuff[$match['type'][0]][] = $match['content'][0];
          $View->AddHighlight($match['start'][0], $match['end'][0], $colors[$match['type'][0]], '', $match['content'][0],-1);
        }
      }
    }
    pclose($Fin);
    if ($Highlight)
    {
      $Fin = fopen($TempFile, "r");
      if ($Fin)
      {
        $View->SortHighlightMenu();
        $View->ShowView($Fin,$ModBack, 1,1,NULL,True);
        fclose($Fin);
      }
      $uri = Traceback_uri();
      $toUploads = "<a href='$uri?mod=uploads>Back to Uploads</a>\n";
      echo $toUploads;
    }
    else
    {
      $text = _("Copyright Statments");
      $text1 = _("Emails");
      $text2 = _("URLs");
      $text3 = _("Total");
      print "<table width=100%>\n";
      print "<tr><td>$text:</td></tr>\n";
      print "<tr><td><hr></td></tr>\n";
      if (count($stuff['statement']) > 0)
      {
        foreach ($stuff['statement'] as $i)
        {
          print "<tr><td>$i</td></tr>\n";
        }
        print "<tr><td><hr></td></tr>\n";
      }
      print "<tr><td>$text3: ".count($stuff['statement'])."</td></tr>\n";
      print "</table>\n";

      print "<br><br>\n";

      print "<table width=100%>\n";
      print "<tr><td>$text1:</td></tr>\n";
      print "<tr><td><hr></td></tr>\n";
      if (count($stuff['email']) > 0)
      {
        foreach ($stuff['email'] as $i)
        {
          print "<tr><td>$i</td></tr>\n";
        }
        print "<tr><td><hr></td></tr>\n";
      }
      print "<tr><td>$text3: ".count($stuff['email'])."</td></tr>\n";
      print "</table>\n";

      print "<br><br>\n";

      print "<table width=100%>\n";
      print "<tr><td>$text2:</td></tr>\n";
      print "<tr><td><hr></td></tr>\n";
      if (count($stuff['url']) > 0)
      {
        foreach ($stuff['url'] as $i)
        {
          print "<tr><td>$i</td></tr>\n";
        }
        print "<tr><td><hr></td></tr>\n";
      }
      print "<tr><td>$text3: ".count($stuff['url'])."</td></tr>\n";
      print "</table>\n";
      echo "<br>\n";
      $uri = Traceback_uri();
      $toUploads = "<a href='$uri?mod=uploads>Back to Uploads</a>\n";
      echo $toUploads;
    }
    /* Clean up */
    return ($V);
  } // AnalyzeOne()

  function uploadFile($Folder, $TempFile, $Name)
  {
    //echo "<pre>AUP: in upload\n</pre>";

    /* See if the folder looks valid */
    if (empty($Folder)) {
      $text = _("Invalid folder");
      return ($text);
    }
    if (empty($Name)) {
      $Name = basename(@$_FILES['getfile']['name']);
    }
    $originName = @$_FILES['getfile']['name'];
    $ShortName = basename($Name);
    if (empty($ShortName)) {
      $ShortName = $Name;
    }
    /* Create an upload record. */
    $Mode = (1 << 3); // code for "it came from web upload"
    $uploadpk = JobAddUpload($ShortName, $originName, $Desc, $Mode, $Folder);
    if (empty($uploadpk)) {
      $text = _("Failed to insert upload record");
      return ($text);
    }
    /* move the temp file */
    //echo "<pre>uploadfile: renaming uploaded file\n</pre>";
    if (!move_uploaded_file($TempFile, "$TempFile-uploaded")) {
      $text = _("Could not save uploaded file");
      return ($text);
    }
    $UploadedFile = "$TempFile" . "-uploaded";
    //echo "<pre>uploadfile: \$UploadedFile is:$UploadedFile\n</pre>";
    if (!chmod($UploadedFile, 0660)) {
      $text = _("ERROR! could not update permissions on downloaded file");
      return ($text);
    }

    /* Run wget_agent locally to import the file. */

    global $LIBEXECDIR;

    $Prog = "$LIBEXECDIR/agents/wget_agent -g fossy -k $uploadpk '$UploadedFile'";
    $wgetLast = exec($Prog,$wgetOut,$wgetRtn);
    unlink($UploadedFile);

    global $Plugins;

    $Unpack = &$Plugins[plugin_find_id("agent_unpack") ];

    $jobqueuepk = NULL;
    $Unpack->AgentAdd($uploadpk, array($jobqueuepk));
    userDefaultAgents($uploadpk);

    if($wgetRtn == 0) {
      $text = _("The file");
      $text1 = _("has been uploaded. It is");
      $Url = Traceback_uri() . "?mod=showjobs&history=1&upload=$uploadpk";
      $Msg = "$text $Name $text1 ";
      $keep = '<a href=' . $Url . '>upload #' . $uploadpk . "</a>.\n";
      print displayMessage($Msg,$keep);
      return (NULL);
    }
    else {
      return($wgetOut[0]);
    }
    return(NULL);
  } // uploadFile


  /**
   * \brief uploadSrv: process the upload from server request, scheduling
   * agents as needed.
   */

  /**
   *
   * Function: uploadSrv()
   *
   * \brief Process the upload from server request.  Call the upload by the
   * Name passed in or by the filename if no name is supplied.
   *
   * @param int $FolderPk folder fk to load into
   * @param string $SourceFiles files to upload, file, tar, directory, etc...
   * @param string $GroupNames flag for indicating if group names were requested.
   *        passed on as -A option to cp2foss.
   * @param string $Name optional Name for the upload
   *
   * @return NULL on success, string on failure.
   */
  function uploadSrv($FolderPk, $SourceFiles, $GroupNames, $Name)
  {

    global $LIBEXECDIR;
    global $DB;
    global $Plugins;

    $FolderPath = FolderGetName($FolderPk);
    $CMD = "";
    if ($GroupNames == "1")
    {
      $CMD.= " -A";
    }
    $FolderPath = str_replace('`', '\`', $FolderPath);
    $FolderPath = str_replace('$', '\$', $FolderPath);
    $CMD.= " -f \"$FolderPath\"";
    if (!empty($Name))
    {
      $Name = str_replace('`', '\`', $Name);
      $Name = str_replace('$', '\$', $Name);
      $CMD.= " -n \"$Name\"";
    }
    else
    {
      $Name = $SourceFiles;
    }

    // get the default agents selected by the user, as simple screen does not
    // have user choices shown.
    $userName = $_SESSION['User'];
    $SQL = "SELECT user_name, user_agent_list FROM users WHERE
            user_name='$userName';";
    $uList = $DB->Action($SQL);

    // Ulist can be empty if the user does not have the correct permissions
    // or has not selected any default/preferred agents or sql failed.
    if(empty($uList))
    {
      return;       // nothing to schedule or sql failed....

    }
    $alist = $uList[0]['user_agent_list'];
    $agentList = " -q " . $alist;
    $CMD .= $agentList;

    $SourceFiles = str_replace('`', '\`', $SourceFiles);
    $SourceFiles = str_replace('$', '\$', $SourceFiles);
    $SourceFiles = str_replace('|', '\|', $SourceFiles);
    $SourceFiles = str_replace(' ', '\ ', $SourceFiles);
    $SourceFiles = str_replace("\t", "\\\t", $SourceFiles);
    $CMD.= " $SourceFiles";
    $jq_args = trim($CMD);
    /* Add the job to the queue */
    // create the job
    $ShortName = basename($Name);
    if (empty($ShortName)) {
      $ShortName = $Name;
    }
    echo "<pre>UPSRV: name is:$Name\nShortName is:$ShortName\n</pre>";
    // Create an upload record.
    $jobq = NULL;
    $Mode = (1 << 3); // code for "it came from web upload"
    $uploadpk = JobAddUpload($ShortName, $SourceFiles, $Desc, $Mode, $FolderPk);
    $jobq = JobAddJob($uploadpk, 'fosscp_agent', 0);
    if (empty($jobq))
    {
      $text = _("Failed to create job record");
      return ($text);
    }

    /* Check for email notification and adjust jq_args as needed */
    if (CheckEnotification())
    {
      if(empty($_SESSION['UserEmail']))
      {
        $Email = 'fossy@localhost';
      }
      else
      {
        $Email = $_SESSION['UserEmail'];
      }
      /*
       * Put -w webServer -e <addr> in the front as the upload is last
       * part of jq_args.
       */
      $jq_args = " -W {$_SERVER['SERVER_NAME']} -e $Email " . "$jq_args";
    }
    // put the job in the jobqueue
    $jq_type = 'fosscp_agent';
    $jobqueue_pk = JobQueueAdd($jobq, $jq_type, $jq_args, "no", NULL, NULL, 0);

    if (empty($jobqueue_pk))
    {
      $text = _("Failed to place fosscp_agent in job queue");
      return ($text);
    }
    $Url = Traceback_uri() . "?mod=showjobs&history=1&upload=$uploadpk";
    $msg = "The upload for $SourceFiles has been scheduled. ";
    $keep = "It is <a href='$Url'>upload #" . $uploadpk . "</a>.\n";
    print displayMessage($msg,$keep);
    return (NULL);
  } // uploadSrv()

  /**
   * \brief uploadUrl(): Process the upload from URL request.
   *
   * @return NULL on success, string on failure.
   */

  function uploadUrl($Folder, $GetURL, $Desc, $Name)
  {

    if (empty($Folder))
    {
      $text = _("Invalid folder");
      return ($text);
    }
    if (empty($GetURL))
    {
      $text = _("Invalid URL");
      return ($text);
    }
    /* See if the URL looks valid */
    if (preg_match("@^((http)|(https)|(ftp))://([[:alnum:]]+)@i", $GetURL) != 1)
    {
      $text = _("Invalid URL");
      return ("$text: " . htmlentities($GetURL));
    }
    if (preg_match("@[[:space:]]@", $GetURL) != 0)
    {
      $text = _("Invalid URL (no spaces permitted)");
      return ("$text: " . htmlentities($GetURL));
    }
    if (empty($Name))
    {
      $Name = basename($GetURL);
    }
    $ShortName = basename($Name);
    if (empty($ShortName))
    {
      $ShortName = $Name;
    }
    /* Create an upload record. */
    $Mode = (1 << 2); // code for "it came from wget"
    $uploadpk = JobAddUpload($ShortName, $GetURL, $Desc, $Mode, $Folder);
    if (empty($uploadpk))
    {
      $text = _("Failed to insert upload record");
      return ($text);
    }
    /* Prepare the job: job "wget" */
    $jobpk = JobAddJob($uploadpk, "wget");
    if (empty($jobpk) || ($jobpk < 0))
    {
      $text = _("Failed to insert job record");
      return ($text);
    }
    /* Prepare the job: job "wget" has jobqueue item "wget" */
    /** 2nd parameter is obsolete **/
    $jobqueuepk = JobQueueAdd($jobpk, "wget", "$uploadpk - $GetURL", "no", NULL, NULL);
    if (empty($jobqueuepk))
    {
      $text = _("Failed to insert task 'wget' into job queue");
      return ($text);
    }
    global $Plugins;
    $Unpack = & $Plugins[plugin_find_id("agent_unpack") ];
    $Unpack->AgentAdd($uploadpk, array($jobqueuepk));

    userDefaultAgents($uploadpk);

    $Url = Traceback_uri() . "?mod=showjobs&history=1&upload=$uploadpk";
    $text = _("The upload");
    $text1 = _("has been scheduled. It is");
    $msg = "$text $Name $text1 ";
    $keep =  "<a href='$Url'>upload #" . $uploadpk . "</a>.\n";
    print displayMessage($msg,$keep);
    return (NULL);
  } // uploadUrl()

  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return;
    }
    $results = "";
    switch ($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $formName = GetParm('uploadform', PARM_TEXT); // may be null
        //echo "<pre>formName from get is:$formName\n</pre>";
        if($formName == 'fileupload')
        {
          // If this is a POST, then process the request.
          $Folder = GetParm('folder', PARM_INTEGER);
          $Name = GetParm('name', PARM_TEXT); // may be null
          if (file_exists(@$_FILES['getfile']['tmp_name']) && !empty($Folder))
          {
            $uf = @$_FILES['getfile']['tmp_name'];
            $rc = $this->uploadFile($Folder, @$_FILES['getfile']['tmp_name'], $Name);
            if (empty($rc))
            {
              // reset form fields
              $GetURL = NULL;
              $Desc = NULL;
              $Name = NULL;
            }
            else
            {
              $text = _("Upload failed for file");
              $V.= displayMessage("$text {$_FILES[getfile][name]}: $rc");
            }
          }
        }
        else if($formName == 'urlupload')
        {
          /* If this is a POST, then process the request. */
          $Folder = GetParm('folder', PARM_INTEGER);
          $GetURL = GetParm('geturl', PARM_TEXT);
          $Name = GetParm('name', PARM_TEXT); // may be null
          if (!empty($GetURL) && !empty($Folder))
          {
            $rc = $this->uploadUrl($Folder, $GetURL, $Desc, $Name);
            if (empty($rc))
            {
              /* Need to refresh the screen */
              $GetURL = NULL;
              $Desc = NULL;
              $Name = NULL;
            }
            else
            {
              $text = _("Upload failed for");
              $results .= displayMessage("$text $GetURL: $rc");
            }
          }
        }
        else if($formName == 'srvupload')
        {
          /* If this is a POST, then process the request. */
          $SourceFiles = GetParm('sourcefiles', PARM_STRING);
          $GroupNames = GetParm('groupnames', PARM_INTEGER);
          $FolderPk = GetParm('folder', PARM_INTEGER);
          $Name = GetParm('name', PARM_STRING); // may be null
          if (!empty($SourceFiles) && !empty($FolderPk))
          {
            $rc = $this->uploadSrv($FolderPk, $SourceFiles, $GroupNames, $Name);
            if (empty($rc))
            {
              // clear form fileds
              $SourceFiles = NULL;
              $GroupNames  = NULL;
              $FolderPk    = NULL;
              $Desc        = NULL;
              $Name        = NULL;
            }
            else
            {
              $text = _("Upload failed for");
              $results .= displayMessage("$text $SourceFiles: $rc");
            }
          }
        }
        else if($formName == 'oneShotNomos')
        {
          /* Ignore php Notice is array keys don't exist */
          $errlev = error_reporting(E_ERROR | E_WARNING | E_PARSE);

          $tmp_name = $_FILES['licfile']['tmp_name'];
          error_reporting($errlev);

          /* For REST API:
           wget -qO - --post-file=myfile.c http://myserv.com/?mod=agent_nomos_once
           */
          if ($this->NoHTML && file_exists($tmp_name))
          {
            echo "<pre>ajax-oneShotNomos: in NoHTML\n</pre>";
            echo $this->AnalyzeFile($tmp_name);
            echo "\n";
            unlink($tmp_name);
            return;
          }

          if (file_exists($tmp_name))
          {
            $text = _("A one shot license analysis shows the following license(s) in file");
            $keep = "<strong>$text </strong><em>{$_FILES['licfile']['name']}:</em> ";
            $keep .= "<strong>" . $this->AnalyzeFile($tmp_name) . "</strong><br>";
            print displayMessage(NULL,$keep);
            $uri = Traceback_uri();
            $toUploads = "<a href='$uri?mod=uploads>Back to Uploads</a>\n";
            $_FILES['licfile'] = NULL;
            echo $toUploads;

            if (!empty($_FILES['licfile']['unlink_flag']))
            {
              echo "<pre>Unlinking file!\n</pre>";
              unlink($tmp_name);
            }
            return;
          }
        }

        else if($formName == 'oneShotCopyright')
        {
          /* If this is a POST, then process the request. */
          $Highlight = GetParm('highlight', PARM_INTEGER); // may be null
          /* You can also specify the file by uploadtree_pk as 'item' */
          $Item = GetParm('item', PARM_INTEGER); // may be null
          if (file_exists(@$_FILES['licfile']['tmp_name']))
          {
            if ($_FILES['licfile']['size'] <= 1024 * 1024 * 10)
            {
              /* Size is not too big.  */
              print $this->AnalyzeOne($Highlight) . "\n";
              $uri = Traceback_uri();
              $toUploads = "<a href='$uri?mod=uploads>Back to Uploads</a>\n";
            }
            if (!empty($_FILES['licfile']['unlink_flag']))
            {
              unlink($_FILES['licfile']['tmp_name']);
            }
            return;
          }
        }


        $Url = Traceback_uri();
        $intro .= _("FOSSology has many options for importing and uploading files for analysis.\n");
        $intro .= _("The options vary based on <i>where</i> the data to upload is located.\n");
        $intro .= _("The data may be located:\n");
        $intro .= "<ul>\n";
        $text = _("On your browser system");
        $intro .= "<li><b>$text</b>.\n";
        $text = _("Use the");
        $text1 = _("Upload File");
        $text2 = _("option to select and upload the file.");
        $intro .= "$text <a href='${Uri}?mod=ajax_fileUpload'>$text1</a> $text2\n";
        $intro .= _("While this can be very convenient (particularly if the file is not readily accessible online),\n");
        $intro .= _("uploading via your web browser can be slow for large files,\n");
        $intro .= _("and files larger than 650 Megabytes may not be uploadable.\n");
        $intro .= "<P />\n";
        $text = _("On a remote server");
        $intro .= "<li><b>$text</b>.\n";
        $text = _("Use the");
        $text1 = _("Upload from URL");
        $text2 = _("option to specify a remote server.");
        $intro .= "$text <a href='${Uri}?mod=upload_url'>$text1</a> $text2\n";
        $intro .= _("This is the most flexible option, but the URL must denote a publicly accessible HTTP, HTTPS, or FTP location.\n");
        $intro .= _("URLs that require authentication or human interactions cannot be downloaded through this automated system.\n");
        $intro .= "<P />\n";
        $choice .= $intro;
        //$choice .= "<br>\n";
        $choice .= "<form name='uploads' enctype='multipart/form-data' method='post'>\n";
        $choice .= "<input type='checkbox' name='Check_upload_file' value='file' onclick='UploadFile_Get(\"" .Traceback_uri() . "?mod=ajax_fileUpload\")' />Upload a File from your computer<br />\n";
        $choice .= "<input type='checkbox' name='Check_upload_url' value='url' onclick='UploadUrl_Get(\"" .Traceback_uri() . "?mod=ajax_urlUpload\")' />Upload from a URL on the intra or internet<br />\n";
        $choice .= "<input type='checkbox' name='Check_Opts' value='opts' onclick='UploadOpts_Get(\"" .Traceback_uri() . "?mod=ajax_optsForm\")' />More Options<br />\n";

        $choice .= "\n<div>\n
                   <hr>
                   <p id='fileform'></p>
                   </div>";
        /* Create the AJAX (Active HTTP) javascript for doing the replys
         * and showing the response.
         */
        $choice .= ActiveHTTPscript("UploadFile");
        $choice .= "<script language='javascript'>\n
        function UploadFile_Reply()
        {
          if ((UploadFile.readyState==4) && (UploadFile.status==200))
          {\n
            /* Remove all options */
            document.getElementById('fileform').innerHTML = UploadFile.responseText;\n
            /* Add new options */
          }
        }
        </script>\n";

        // URL's
        $choiceUrl .= ActiveHTTPscript("UploadUrl");
        $choiceUrl .= "<script language='javascript'>\n
        function UploadUrl_Reply()
        {
          if ((UploadUrl.readyState==4) && (UploadUrl.status==200))
          {\n
            /* Remove all options */
            document.getElementById('fileform').innerHTML = UploadUrl.responseText;\n
            /* Add new options */
          }
        }
        </script>\n";
        $choice .= $choiceUrl;

        // More Options

        $options .= ActiveHTTPscript("UploadOpts");
        $options .= "<script language='javascript'>\n
        function UploadOpts_Reply()
        {
          if ((UploadOpts.readyState==4) && (UploadOpts.status==200))
          {\n
            /* Remove all options */
            document.getElementById('fileform').innerHTML = UploadOpts.responseText;\n
            /* Add new options */
          }
        }
        </script>\n";
        $choice .= $options;

        // upload from server
        $uploadSrv .= ActiveHTTPscript("UploadSrv");
        $uploadSrv .= "<script language='javascript'>\n
        function UploadSrv_Reply()
        {
          if ((UploadSrv.readyState==4) && (UploadSrv.status==200))
          {\n
            /* Remove all options */
            document.getElementById('optsform').innerHTML = UploadSrv.responseText;\n
            /* Add new options */
          }
        }
        </script>\n";
        $choice .= $uploadSrv;

        // One Shot License
        $uploadOsN .= ActiveHTTPscript("UploadOsN");
        $uploadOsN .= "<script language='javascript'>\n
        function UploadOsN_Reply()
        {
          if ((UploadOsN.readyState==4) && (UploadOsN.status==200))
          {\n
            document.getElementById('optsform').innerHTML = UploadOsN.responseText;\n
          }
        }
        </script>\n";
        $choice .= $uploadOsN;

        // One Shot Copyright
        $uploadCopy .= ActiveHTTPscript("UploadCopyR");
        $uploadCopy .= "<script language='javascript'>\n
        function UploadCopyR_Reply()
        {
          if ((UploadCopyR.readyState==4) && (UploadCopyR.status==200))
          {\n
            document.getElementById('optsform').innerHTML = UploadCopyR.responseText;\n
          }
        }
        </script>\n";
        $choice .= $uploadCopy;

        $choice .= "</form>";
        break;
  case "Text":
    break;
  default:
    break;
}
if (!$this->OutputToStdout)
{
  return ($choice);
}
print ("$choice");
return;

}
};
$NewPlugin = new uploads;

?>