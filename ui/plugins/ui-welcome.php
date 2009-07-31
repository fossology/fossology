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
	  $Browse = "<a href='" . Traceback_uri() . "?mod=browse'>Browse</a>";
	  }
	else { $Browse = "Browse"; }
	if (plugin_find_id("search_file") >= 0)
	  {
	  $Search = "<a href='" . Traceback_uri() . "?mod=search_file'>Search</a>";
	  }
	else { $Search = "Search"; }
	if (empty($_SESSION['User']) && (plugin_find_id("auth") >= 0))
	  {
	  $Login = "<a href='" . Traceback_uri() . "?mod=auth'>Login</a>";
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
          <img src='images/right-point-bullet.gif'>Extract MIME type and meta data information</font></p>
        <p>&nbsp;</p>
      </blockquote></td>
    <td><img src='images/white.png'></td>
  </tr>
</table>

<table width='100%' border='0'>
  <tr> 
    <td><img src='images/logo2.png' align='right'></td>
    <td valign='top'>
      <h3><font  color='#CC0000' face='Verdana, Arial, Helvetica, sans-serif'>FOSSology's Graphical User Interface</font></h3>
      <blockquote> 
        <p> <font face='Arial, Helvetica, sans-serif'>This website is an interface 
          into the FOSSology project. With it, you can:<br>
          <br>
          <img src='images/right-point-bullet.gif'>Upload files 
          to analyze.<br>
          <img src='images/right-point-bullet.gif'>Unpack and store the data within the files for analysis. <br>
          <img src='images/right-point-bullet.gif'>Invoke specialized agents to scan and analyze the files.  <br>
          <img src='images/right-point-bullet.gif'>Store and display the analyzed results. </font><br>
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
          <strong><em>$Browse: </em></strong>If you don't know where to start, 
          try browsing the currently uploaded projects. <br>
          <strong><em>$Search:</em></strong> Look through the uploaded projects 
          for specific files. <br>
          <strong><em>$Login:</em></strong> If you log in, you can access additional 
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
          <em><strong>Software Repository</strong></em> - Stores files downloaded 
          for analysis.<br>
          <em><strong>Database</strong></em> - Stores user accounts, file information, 
          and analysis results.<br>
          <em><strong>Agents</strong></em> - Perform analysis of files and data 
          found in the Software Repository and Database.<br>
          <em><strong>Scheduler</strong></em> - Runs the agents, making efficient 
          use of available resources.<br>
          <em><strong>Web GUI</strong></em> &shy; - Provides user access to FOSSology.<br>
          <em><strong>Command line utilities</strong></em> &shy; - Provides scripting access to FOSSology.</font>
        </p>
      </blockquote></td>
    <td><img src='images/fossology-flow4.png'> </td>
  </tr>
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
		  
		  <!--  <font face='Arial, Helvetica, sans-serif'><em><strong>Help tab</strong></em> 
              - Select this website's Help tab for software-related help and tips.
              </font><br>-->
            
			  <font face='Arial, Helvetica, sans-serif'><em><strong><a href='http://fossology.org/'>FOSSology 
              web site</a></strong></em> - Where you can find more information and get help on FOSSology.<br>
              <em><strong><a href='https://fossbazaar.org/'>FOSSbazaar web site</a></strong></em></font> 
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
