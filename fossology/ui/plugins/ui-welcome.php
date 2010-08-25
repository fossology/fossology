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

class ui_welcome extends FO_Plugin
  {
  var $Name       = "Getting Started";
  var $Title      = "Getting Started with FOSSology";
  var $Version    = "1.0";
  var $MenuList   = "Help::Getting Started";
  var $DBaccess   = PLUGIN_DB_NONE;
  var $LoginFlag  = 0;

  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";

    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	if (plugin_find_id("browse") >= 0)
	  {
$text = _("Browse");
	  $Browse = "<a href='" . Traceback_uri() . "?mod=browse'>$text</a>";
	  }
	else { $Browse = "Browse"; }
	if (plugin_find_id("search_file") >= 0)
	  {
$text = _("Search");
	  $Search = "<a href='" . Traceback_uri() . "?mod=search_file'>$text</a>";
	  }
	else { $Search = "Search"; }
	if (empty($_SESSION['User']) && (plugin_find_id("auth") >= 0))
	  {
$text = _("Login");
	  $Login = "<a href='" . Traceback_uri() . "?mod=auth'>$text</a>";
	  }
	else { $Login = "Login"; }

	$V .= "
<table width='100%' border='0'>
  <tr> 
    <td valign='top'> <h3><font color='#CC0000' face='Verdana, Arial, Helvetica, sans-serif'>The 
        FOSSology Toolset</font></h3>
      <blockquote> 
        <p><font face='Arial, Helvetica, sans-serif'>FOSSology is a framework 
          for software analysis tools. The current FOSSology tools can: <br>
          <br>
          <img src='images/right-point-bullet.gif'>Identify licenses in software<br>
          <img src='images/right-point-bullet.gif'>Allow browsing uploaded file hierarchies<br>
$text = _("Extract MIME type and meta data information");
$text1 = _("
");
          <img src='images/right-point-bullet.gif'>$text</font></p>$text1";
$text = _("&nbsp;");
$text1 = _("
");
        <p>$text</p>$text1      </blockquote></td>
    <td><img src='images/white.png'></td>
  </tr>
</table>

<table width='100%' border='0'>
  <tr> 
    <td><img src='images/logo2.png' align='right'></td>
    <td valign='top'>
$text = _("FOSSology's Graphical User Interface");
$text1 = _("
");
      <h3><font  color='#CC0000' face='Verdana, Arial, Helvetica, sans-serif'>$text</font></h3>$text1";
      <blockquote> 
        <p> <font face='Arial, Helvetica, sans-serif'>This website is an interface 
          into the FOSSology project. With it, you can:<br>
          <br>
          <img src='images/right-point-bullet.gif'>Upload files 
          to analyze.<br>
          <img src='images/right-point-bullet.gif'>Unpack and store the data within the files for analysis. <br>
          <img src='images/right-point-bullet.gif'>Invoke specialized agents to scan and analyze the files.  <br>
$text = _("Store and display the analyzed results. ");
$text1 = _("
");
          <img src='images/right-point-bullet.gif'>$text</font><br>$text1";
        </p>
      </blockquote></td>
  </tr>
</table>

<table width='100%' border='0'>
  <tr> 
    <td valign='top'> <h3><font  color='#CC0000' face='Verdana, Arial, Helvetica, sans-serif'>How 
        to Begin</font></h3>
      <blockquote> 
        <p><font face='Arial, Helvetica, sans-serif'> The menu at the top contains 
          all the primary capabilities of FOSSology. Most functions require you 
          to log in before they can be accessed. The following functions are available 
          without logging in:<br>
          <br>
$text = _("$Browse: ");
$text1 = _("If you don't know where to start, 
");
          <strong><em>$text</em></strong>$text1";
          try browsing the currently uploaded projects. <br>
$text = _("$Search:");
$text1 = _(" Look through the uploaded projects 
");
          <strong><em>$text</em></strong>$text1";
          for specific files. <br>
$text = _("$Login:");
$text1 = _(" If you log in, you can access additional 
");
          <strong><em>$text</em></strong>$text1";
          capabilities. Depending on your account's access rights,<br>
          you may be able to upload files, schedule analysis tasks, or even add 
          new users.</font></p>
      </blockquote></td>
  </tr>
</table>

<table width='100%' border='0'>
  <tr> 
    <td valign='top'> <h3><font  color='#CC0000' face='Verdana, Arial, Helvetica, sans-serif'>Inside 
        FOSSology</font></h3>
      <blockquote> 
        <p><font face='Arial, Helvetica, sans-serif'>Some parts of FOSSology helpful 
          to know about are:<br>
          <br>
$text = _("Software Repository");
$text1 = _(" - Stores files downloaded 
");
          <em><strong>$text</strong></em>$text1";
          for analysis.<br>
$text = _("Database");
$text1 = _(" - Stores user accounts, file information, 
");
          <em><strong>$text</strong></em>$text1";
          and analysis results.<br>
$text = _("Agents");
$text1 = _(" - Perform analysis of files and data 
");
          <em><strong>$text</strong></em>$text1";
          found in the Software Repository and Database.<br>
$text = _("Scheduler");
$text1 = _(" - Runs the agents, making efficient 
");
          <em><strong>$text</strong></em>$text1";
          use of available resources.<br>
$text = _("Web GUI");
$text1 = _(" &shy");
          <em><strong>$text</strong></em>$text1";
$text = _("Command line utilities");
$text1 = _(" &shy");
          <em><strong>$text</strong></em>$text1";
        </p>
      </blockquote></td>
$text = _(" ");
$text1 = _("
");
    <td><img src='images/fossology-flow4.png'>$text</td>$text1  </tr>
</table>

<table width='100%' border='0'>
  <tr> 
    <td valign='top'> <h3><font  color='#CC0000' face='Verdana, Arial, Helvetica, sans-serif'><img src='images/white.png' align='left'> 
        Need Some Help?</font></h3>
      <blockquote> 
        <blockquote> 
          <p><font face='Arial, Helvetica, sans-serif'>Now that you've been introduced 
            to Fossology, try exploring it!<br>
            The following resources will provide additional help and information: 
            </font></p>
          <blockquote>
		  
$text = _("Help tab");
$text1 = _(" 
");
		  <!--  <font face='Arial, Helvetica, sans-serif'><em><strong>$text</strong></em>$text1";
              - Select this website's Help tab for software-related help and tips.
              </font><br>-->
            
			  <font face='Arial, Helvetica, sans-serif'><em><strong><a href='http://fossology.org/'>FOSSology 
              web site</a></strong></em> - Where you can find more information and get help on FOSSology.<br>
$text = _("FOSSbazaar web site");
$text1 = _("");
              <em><strong><a href='https://fossbazaar.org/'>$text</a></strong>$text1</em></font> 
";
              <font face='Arial, Helvetica, sans-serif'> - A community website 
              with information on Open Source Governance.</font>
          </blockquote>
        </blockquote>
      </blockquote></td>
  </tr>
</table>
";
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print($V);
    return;
    }

  };
$NewPlugin = new ui_welcome;
$NewPlugin->Initialize();
?>
