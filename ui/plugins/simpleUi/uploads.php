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
  
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return;
    }
    $Buttons = "";
    switch ($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":

        $formName = GetParm('fileupload', PARM_TEXT); // may be null
        echo "<pre>UP: GetParm returned:$formName\n</pre>";
        if($formName == 'fileupload')
        {
          // If this is a POST, then process the request.
          $Folder = GetParm('folder', PARM_INTEGER);
          $Name = GetParm('name', PARM_TEXT); // may be null
          //echo "<pre> UF: values from form are:name:$Name, folder:$folder\n</pre>";
          if (file_exists(@$_FILES['getfile']['tmp_name']) && !empty($Folder))
          {
            echo "<pre> UF: Calling aUpload!\n</pre>";
            $uf = @$_FILES['getfile']['tmp_name'];
            //echo "<pre> UF: with F:$Folder N:$Name uf:$uf\n</pre>";
            $rc = $this->uploadFile($Folder, @$_FILES['getfile']['tmp_name'], $Name);
            if (empty($rc))
            {
              $Parm = GetParm("Check_" . $Name,PARM_INTEGER);
              echo "<pre>CA: GetParm returned:$Parm\n for $Name\n</pre>";
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
        $choice .= ActiveHTTPscript("UploadUrl");
        $choice .= "<script language='javascript'>\n
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