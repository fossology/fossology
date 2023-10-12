<?php
/*
 SPDX-FileCopyrightText: © 2008-2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only
*/

/**
 * \file
 * \brief These are common functions for performing active HTTP requests.
 * (Used in place of AJAX or ActiveX.)
 */

/** \brief Load a new url
 *
 * The new url is url+val
 * e.g. js_url(this.value, "http:me?val=")
 **/
function js_url()
{
  $script = "
    <script type=\"text/javascript\">
      function js_url(val, url)
      {
        window.location.assign(url+val);
      }
    </script>
  ";

  return $script;
}


/**
 * \brief Display a message.
 *
 * This is used to convey the results of button push like upload, submit,
 * analyze, create, etc.
 *
 * \param string $Message The message to display
 * \param string $keep A safe text string NOT run through htmlentities
 *
 * \return The html to display (with embeded javascript)
 */
function displayMessage($Message, $keep = null)
{

  $HTML = null;
  $HTML .= "\n<div id='dmessage'>";
  $text = _("Close");
  $HTML .= "<button name='eraseme' value='close' class='btn btn-default btn-sm' onclick='rmMsg()'> $text</button>\n";
  $HTML .= $Message;
  $HTML .= $keep . "\n</p>";
  $HTML .= "  <hr>\n";
  $HTML .= "</div>\n";
  $HTML .= "<script type='text/javascript'>\n" .
           "function rmMsg(){\n" .
           "  var div = document.getElementById('dmessage');\n" .
           "  var parent = div.parentNode;\n" .
           "  parent.removeChild(div);\n" .
           "}\n" .
           "</script>\n";
  return($HTML);
}

/**
 * \brief Given a function name, create the
 * JavaScript needed for doing the request.
 *
 * The JavaScript takes a URL and returns the data.
 * The JavaScript is Asynchronous (no wait while the request goes on).
 *
 * \param string $RequestName
 * \parblock The JavaScript variable name to use.
 *
 * The javascript function is named "${RequestName}_Get"\n
 * The javascript function "${RequestName}_Reply" must be defined for
 * handling the reply.  (You will need to make this Javascript function.)
 *
 * The javascript variable "${RequestName}.status" contains the
 * reply's HTTP return code (200 means "OK") and "${RequestName}.readyState"
 * is the handle's state (4 = "loaded").
 * \endparblock
 * \param boolean $IncludeScriptTags If will append the javascript tag. Default value is 1.
 * empty on no, other on yes
 *
 * \return The html (with embeded javascript)
 *
 * \see References: http://www.w3schools.com/xml/xml_http.asp
 */
function ActiveHTTPscript($RequestName,$IncludeScriptTags=1)
{
  $HTML="";

  if ($IncludeScriptTags) {
    $HTML="<script language='javascript'>\n<!--\n";
  }

  $HTML .= "var $RequestName=null;\n";
  /* Check for browser support. */
  $HTML .= "function {$RequestName}_Get(Url)\n";
  $HTML .= "{\n";
  $HTML .= "if (window.XMLHttpRequest)\n";
  $HTML .= "  {\n";
  $HTML .= "  $RequestName=new XMLHttpRequest();\n";
  $HTML .= "  }\n";
  /* Check for IE5 and IE6 */
  $HTML .= "else if (window.ActiveXObject)\n";
  $HTML .= "  {\n";
  $HTML .= "  $RequestName=new ActiveXObject('Microsoft.XMLHTTP');\n";
  $HTML .= "  }\n";

  $HTML .= "if ($RequestName!=null)\n";
  $HTML .= "  {\n";
  $HTML .= "  $RequestName.onreadystatechange={$RequestName}_Reply;\n";
  /*
   'true' means asynchronous request.
  Rather than waiting for the reply, the reply is
  managed by the onreadystatechange event handler.
  */
  $HTML .= "  $RequestName.open('GET',Url,true);\n";
  $HTML .= "  $RequestName.send(null);\n";
  $HTML .= "  }\n";
  $HTML .= "else\n";
  $HTML .= "  {\n";
  $HTML .= "  alert('Your browser does not support XMLHTTP.');\n";
  $HTML .= "  return;\n";
  $HTML .= "  }\n";
  $HTML .= "}\n";

  if ($IncludeScriptTags) {
    $HTML .= "\n// -->\n</script>\n";
  }

  return($HTML);
}
