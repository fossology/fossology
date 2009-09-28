<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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

class ui_download extends FO_Plugin
  {
  var $Name       = "download";
  var $Title      = "Download File";
  var $Version    = "1.0";
  var $Dependency = array("db");
  var $DBaccess   = PLUGIN_DB_DOWNLOAD;
  var $NoHeader   = 1;

  /***********************************************************
   RegisterMenus(): Customize submenus.
   ***********************************************************/
  function RegisterMenus()
    {
    menu_insert("Browse-Pfile::Download",0,$this->Name,"Download this file");
    } // RegisterMenus()

  /***********************************************************
   OutputOpen(): This function is called when user output is
   requested.  This function is responsible for assigning headers.
   The type of output depends on the metatype for the pfile.
   If the pfile is not defined, then use application/octet-stream.
   ***********************************************************/
  function OutputOpen($Type,$ToStdout)
    {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    $this->OutputType=$Type;
    $this->OutputToStdout=$ToStdout;

    global $Plugins;
    global $DB;
    $Item = GetParm("item",PARM_INTEGER);
    if (empty($Item))
	    {
	    $this->OutputType = "corrupt";
	    return;
	    }
    /* Added by vincent to implement when click donwload link, the file not in the repository, add a page to ask user if want to reunpack */
    /** Begin:  **/
    $Fin = NULL;
    if (empty($Fin))
      {
      $Fin = @fopen( RepPathItem($Item) ,"rb");
      if (empty($Fin))
	      {  
        $this->NoHeader = 0;
        switch($this->OutputType)
          {
          case "XML":
            $V = "<xml>\n";
          break;
          case "HTML":
            header('Content-type: text/html');
            header("Pragma: no-cache"); /* for IE cache control */
            header('Cache-Control: no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0'); /* prevent HTTP/1.1 caching */
            header('Expires: Expires: Thu, 19 Nov 1981 08:52:00 GMT'); /* mark it as expired (value from Apache default) */
            if ($this->NoHTML) { return; }
            $V = "";
            if (($this->NoMenu == 0) && ($this->Name != "menus"))
              {
              $Menu = &$Plugins[plugin_find_id("menus")];
              $Menu->OutputSet($Type,$ToStdout);
              }
            else { $Menu = NULL; }

            /* DOCTYPE is required for IE to use styles! (else: css menu breaks) */
            $V .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "xhtml1-frameset.dtd">' . "\n";
            // $V .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">' . "\n";
            // $V .= '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Loose//EN" "http://www.w3.org/TR/html4/loose.dtd">' . "\n";
            // $V .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "xhtml1-strict.dtd">' . "\n";

            $V .= "<html>\n";
            $V .= "<head>\n";
            $V .= "<meta name='description' content='The study of Open Source'>\n";
            if ($this->NoHeader == 0)
              {
              /** Known bug: DOCTYPE "should" be in the HEADER
              and the HEAD tags should come first.
              Also, IE will ignore <style>...</style> tags that are NOT
              in a <head>...</head> block.
              **/
              if (!empty($this->Title)) { $V .= "<title>" . htmlentities($this->Title) . "</title>\n"; }
              $V .= "<link rel='stylesheet' href='fossology.css'>\n";
              print $V; $V="";
              if (!empty($Menu)) { print $Menu->OutputCSS(); }
              $V .= "</head>\n";
  
              $V .= "<body class='text'>\n";
              print $V; $V="";
              if (!empty($Menu)) { $Menu->Output($this->Title); }
              }
          break;
          case "Text":
          break;
          default:
          break;
          }
        $this->OutputType = "corrupt";
     
        $P = &$Plugins[plugin_find_id("view")];
        $P->ShowView(NULL,"browse");
	      return;
	     }
      }
    /** END **/
    /* Get filename */
    /** By using pfile and ufile, we cut down the risk of users blindly
        guessing in order to download arbitrary files.
	NOTE: The user can still iterate through every possible pfile and
	ufile in order to find files.  And since the numbers are sequential,
	they can optimize their scan.
	However, it will still take plenty of queries to find most files.
	Later: This will check if the user has access permission to the ufile.
     **/
    $Sql = "SELECT * FROM uploadtree WHERE uploadtree_pk = $Item LIMIT 1;";
    $Results = $DB->Action($Sql);
    $Name = $Results[0]['ufile_name'];
    if (empty($Name))
	{
	$this->OutputType = "corrupt";
	return;
	}

    /* Get meta type */
    switch($this->OutputType)
      {
      case "XML":
	$V = "<xml>\n";
	break;
      case "HTML":
	$Meta = GetMimeType($Item);
	header("Content-Type: $Meta");
	// header('Content-Length: ' . $Results[0]['pfile_size']);
	header('Content-Disposition: attachment; filename="' . $Name . '"');
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print "$V";
    return;
    } // OutputOpen()

  /***********************************************************
   OutputClose(): This function is called when user output is
   completed.
   ***********************************************************/
  function OutputClose()
    {
    } // OutputClose()

  /***********************************************************
   Output(): This function is called when user output is
   requested.  This function is responsible for content.
   (OutputOpen and Output are separated so one plugin
   can call another plugin's Output.)
   This uses $OutputType.
   The $ToStdout flag is "1" if output should go to stdout, and
   0 if it should be returned as a string.  (Strings may be parsed
   and used by other plugins.)
   ***********************************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $Plugins;
    global $DB;
    $Item = GetParm("item",PARM_INTEGER);
    if (empty($Item)) { return; }
    switch($this->OutputType)
      {
      case "XML":
      case "HTML":
      case "Text":
	/* Regardless of the format, dump the file's contents */
	$Filename = RepPathItem($Item);
	if (empty($Filename)) return;
	if ($this->OutputToStdout) { readfile($Filename); }
	else { return($V); }
      default:
	break;
      }
    return;
    } // Output()

  };
$NewPlugin = new ui_download;
$NewPlugin->Initialize();
?>
