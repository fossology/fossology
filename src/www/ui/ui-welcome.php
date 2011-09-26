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

define("TITLE_ui_welcome", _("Getting Started with FOSSology"));

class ui_welcome extends FO_Plugin
{
  var $Name       = "Getting Started";
  var $Title      = TITLE_ui_welcome;
  var $Version    = "1.0";
  var $MenuList   = "Help::Getting Started";
  var $DBaccess   = PLUGIN_DB_NONE;
  var $LoginFlag  = 0;

  /**
   * \brief Generate the text for this plugin.
   */
  function Output()
  {
    $SiteURI = Traceback_uri();

    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        if (empty($_SESSION['User']) && (plugin_find_id("auth") >= 0))
        {
          $text = _("Login");
          $Login = "<a href='$SiteURI?mod=auth'>$text</a>";
        }
        else { $Login = _("Login"); }
        $text1 = _("The FOSSology Toolset");
        $text11 = _("FOSSology is a framework for software analysis tools. The current FOSSology tools can:");
        $text12 = _("Identify licenses in software");
        $text13 = _("Allow browsing uploaded file hierarchies");
        $text14 = _("Extract MIME type and meta data information");
        $text2 = _("FOSSology's Graphical User Interface");
        $text21 = _("This website is an interface into the FOSSology project. With it, you can:");
        $text22 = _("Upload files to analyze.");
        $text23 = _("Unpack and store the data within the files for analysis. ");
        $text24 = _("Invoke specialized agents to scan and analyze the files.  ");
        $text25 = _("Store and display the analyzed results. ");
        $text3 = _("How to Begin");
        $text31 = _("The menu at the top contains all the primary capabilities of FOSSology. Most functions require you to log in before they can be accessed. The following functions are available without logging in:");
        $text32 = _("If you don't know where to start, try browsing the currently uploaded projects. ");
        $text33 = _("Look through the uploaded projects for specific files. ");
        $text34 = _("If you log in, you can access additional capabilities. Depending on your account's access rights,");
        $text35 = _("you may be able to upload files, schedule analysis tasks, or even add new users.");
        $text4 = _("Inside FOSSology");
        $text41 = _("Some parts of FOSSology helpful to know about are:");
        $text42 = _("Software Repository");
        $text43 = _("- Stores files downloaded for analysis.");
        $text44 = _("Database");
        $text45 = _("- Stores user accounts, file information, and analysis results.");
        $text46 = _("Agents");
        $text47 = _("- Perform analysis of files and data found in the Software Repository and Database.");
        $text48 = _("Scheduler");
        $text49 = _("- Runs the agents, making efficient  use of available resources.");
        $text410 = _("Web GUI");
        $text411 = _("- Provides user access to FOSSology.");
        $text412 = _("Command line utilities");
        $text413 = _("- Provides scripting access to FOSSology.");
        $text5 = _("Need Some Help?");
        $text51 = _("Now that you've been introduced to Fossology, try exploring it!");
        $text52 = _("The following resources will provide additional help and information:");
        $text53 = _("Help tab");
        $text54 = _("- Select this website's Help tab for software-related help and tips.");
        $text55 = _("FOSSology web site");
        $text56 = _("- Where you can find more information and get help on FOSSology.");
        $text57 = _("FOSSbazaar web site");
        $text58 = _("- A community website with information on Open Source Governance.");

        $V .= "
<table width='100%' border='0'>
  <tr> 
    <td valign='top'> <h3><font color='#CC0000' face='Verdana, Arial, Helvetica, sans-serif'>$text1</font></h3>
      <blockquote> 
        <p><font face='Arial, Helvetica, sans-serif'>$text11 <br>
          <br>
          <img src='${SiteURI}images/right-point-bullet.gif'>$text12<br>
          <img src='${SiteURI}images/right-point-bullet.gif'>$text13<br>
          <img src='${SiteURI}images/right-point-bullet.gif'>$text14</font></p>
        <p>&nbsp;</p>
      </blockquote></td>
    <td><img src='${SiteURI}images/white.png'></td>
  </tr>
</table>

<table width='100%' border='0'>
  <tr> 
    <td><img src='${SiteURI}images/logo2.png' align='right'></td>
    <td valign='top'>
      <h3><font  color='#CC0000' face='Verdana, Arial, Helvetica, sans-serif'>$text2</font></h3>
      <blockquote> 
        <p> <font face='Arial, Helvetica, sans-serif'>$text21<br>
          <br>
          <img src='${SiteURI}images/right-point-bullet.gif'>$text22<br>
          <img src='${SiteURI}images/right-point-bullet.gif'>$text23<br>
          <img src='${SiteURI}images/right-point-bullet.gif'>$text24<br>
          <img src='${SiteURI}images/right-point-bullet.gif'>$text25</font><br>
        </p>
      </blockquote></td>
  </tr>
</table>

<table width='100%' border='0'>
  <tr> 
    <td valign='top'> <h3><font  color='#CC0000' face='Verdana, Arial, Helvetica, sans-serif'>$text3</font></h3>
      <blockquote> 
        <p><font face='Arial, Helvetica, sans-serif'> $text31<br>
          <br>
          <strong><em>$Login:</em></strong> $text34<br>
          $text35</font></p>
      </blockquote></td>
  </tr>
</table>

<table width='100%' border='0'>
  <tr> 
    <td valign='top'> <h3><font  color='#CC0000' face='Verdana, Arial, Helvetica, sans-serif'>$text4</font></h3>
      <blockquote> 
        <p><font face='Arial, Helvetica, sans-serif'>$text41<br>
          <br>
          <em><strong>$text42</strong></em> $text43<br>
          <em><strong>$text44</strong></em> $text45<br>
          <em><strong>$text46</strong></em> $text47<br>
          <em><strong>$text48</strong></em> $text49<br>
          <em><strong>$text410</strong></em> &shy; $text411<br>
          <em><strong>$text412</strong></em> &shy; $text413<br>
        </p>
      </blockquote></td>
    <td><img src='${SiteURI}images/fossology-flow4.png'></td>
  </tr>
</table>

<table width='100%' border='0'>
  <tr> 
    <td valign='top'> <h3><font  color='#CC0000' face='Verdana, Arial, Helvetica, sans-serif'><img src='${SiteURI}images/white.png' align='left'>$text5</font></h3>
      <blockquote> 
        <blockquote> 
          <p><font face='Arial, Helvetica, sans-serif'>$text51<br>
          $text52
            </font></p>
          <blockquote>
		  <!--  <font face='Arial, Helvetica, sans-serif'><em><strong>$text53</strong></em>$text54
              </font><br>-->
            
			  <font face='Arial, Helvetica, sans-serif'><em><strong><a href='http://fossology.org/'>$text55</a></strong></em> $text56<br>
              <em><strong><a href='https://fossbazaar.org/'>$text57</a></strong></em></font> 
              <font face='Arial, Helvetica, sans-serif'> $text58</font>
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
