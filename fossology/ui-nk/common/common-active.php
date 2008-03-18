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

/************************************************************
 These are common functions for performing active HTTP requests.
 (Used in place of AJAX or ActiveX.)
 ************************************************************/

/*************************************************
 ActiveHTTPscript(): Given a function name, create the
 JavaScript needed for doing the request.
 The JavaScript takes a URL and returns the data.
 The JavaScript is Asynchronous (no wait while the request goes on).
 The $RequestName is the JavaScript variable name to use.
 The javascript function is named "${RequestName}_Get"
 The javascript function "${RequestName}_Reply" must be defined for
 handling the reply.  (You will need to make this Javascript function.)
 References:
   http://www.w3schools.com/xml/xml_http.asp
 *************************************************/
function ActiveHTTPscript	($RequestName,$IncludeScriptTags=1)
{
  $HTML=null;

  if ($IncludeScriptTags)
    {
    $HTML="<script language='javascript'>\n<!--\n";
    }

  $HTML .= "var $RequestName=null;\n";
  /* Check for browser support. */
  $HTML .= "function ${RequestName}_Get(Url)\n";
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
  $HTML .= "  $RequestName.onreadystatechange=${RequestName}_Reply;\n";
  /***
   'true' means asynchronous request.
   Rather than waiting for the reply, the reply is
   managed by the onreadystatechange event handler.
   ***/
  $HTML .= "  $RequestName.open('GET',Url,true);\n";
  $HTML .= "  $RequestName.send(null);\n";
  $HTML .= "  }\n";
  $HTML .= "else\n";
  $HTML .= "  {\n";
  $HTML .= "  alert('Your browser does not support XMLHTTP.');\n";
  $HTML .= "  return;\n";
  $HTML .= "  }\n";
  $HTML .= "}\n";

  if ($IncludeScriptTags)
    {
    $HTML .= "\n// -->\n</script>\n";
    }

  return($HTML);
} // ActiveHTTPscript()

?>
