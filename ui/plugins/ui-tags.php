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

define("TITLE_ui_tag", _("Tag"));

class ui_tag extends FO_Plugin
  {
  var $Name       = "tag";
  var $Title      = TITLE_ui_tag;
  var $Version    = "1.0";

  /***********************************************************
   RegisterMenus(): Customize submenus.
   ***********************************************************/
  function RegisterMenus()
    {
/****** Permission comments: if user don't have read or high permission, can't see tag menu. ******/
    $text = _("Tag files or containers");
    menu_insert("Browse-Pfile::Tag",0,$this->Name,$text);
    } // RegisterMenus()

  /***********************************************************
   CreateTag(): Add a new Tag.
   ***********************************************************/
  function CreateTag()
  {
    global $PG_CONN; 

    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);

    if (empty($Item) || empty($Upload))
        { return; }

    $tag_ns_pk = GetParm('tag_ns_pk', PARM_INTEGER);
    $tag_name = GetParm('tag_name', PARM_TEXT);
    $tag_notes = GetParm('tag_notes', PARM_TEXT);
    $tag_file = GetParm('tag_file', PARM_TEXT);
    $tag_package = GetParm('tag_package', PARM_TEXT);
    $tag_container = GetParm('tag_container', PARM_TEXT);

    /* Debug
    print "<pre>";
    print "Create Tag: TagNameSpace is:$tag_ns_pk\n";
    print "Create Tag: TagName is:$tag_name\n";
    print "Create Tag: TagNotes is:$tag_notes\n";
    print "Create Tag: TagFile is:$tag_file\n";
    print "Create Tag: TagPackage is:$tag_package\n";
    print "Create Tag: TagContainer is:$tag_container\n";
    print "Upload: $Upload\n";
    print "Item: $Item\n";
    print "</pre>";
    */

    if (empty($tag_name))
    {
      $text = _("TagName must be specified. Tag Not created.");
      return ($text);
    }
    /* Need select tag file/package/container */
    if (empty($tag_file) && empty($tag_package) && empty($tag_container))
    {
      $text = _("Need to select one option(file/pacakge/container) to create tag.");
      return ($text);
    }
    /* See if the tag already exists */
    $sql = "SELECT * FROM tag WHERE tag = '$tag_name'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1)
    {
      pg_free_result($result);

      $sql = "INSERT INTO tag (tag,tag_ns_fk,tag_desc) VALUES ('$tag_name', $tag_ns_pk, '$tag_notes');";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    }else{
      $text = _("TagName already exists. Tag Not created.");
      return ($text);
    }

    /* Make sure it was added */
    $sql = "SELECT * FROM tag WHERE tag = '$tag_name' LIMIT 1;";
    $result = pg_query($PG_CONN, $sql);
    if (pg_num_rows($result) < 1) 
    {
      pg_free_result($result);
      $text = _("Failed to create tag.");
      return ($text);
    }
    
    $row = pg_fetch_assoc($result);
    $tag_pk = $row["tag_pk"];
    pg_free_result($result);

    $pfileArray = array();
    $i = 0;

    if (empty($tag_package) && empty($tag_container) && !empty($tag_file))
    {
      /* Get pfile_fk from uploadtree_pk */
      $sql = "SELECT pfile_fk FROM uploadtree
              WHERE uploadtree_pk = $Item LIMIT 1";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $row = pg_fetch_assoc($result);
      while ($row = pg_fetch_assoc($result))
      {
        $pfileArray[$i] = $row['pfile_fk'];
        $i++;
      }
      pg_free_result($result);
    } 

    if (!empty($tag_package))
    {
      /* GetPkgMimetypes */
      $MimetypeArray = GetPkgMimetypes();
      $sql = "SELECT distinct pfile.pfile_pk FROM uploadtree, pfile WHERE uploadtree.pfile_fk = pfile.pfile_pk AND (pfile.pfile_mimetypefk = $MimetypeArray[0] OR pfile.pfile_mimetypefk = $MimetypeArray[1] OR pfile.pfile_mimetypefk = $MimetypeArray[2]) AND uploadtree.upload_fk = $Upload AND uploadtree.lft >= (SELECT lft FROM uploadtree WHERE uploadtree_pk = $Item) AND uploadtree.rgt <= (SELECT rgt FROM uploadtree WHERE uploadtree_pk = $Item);";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);      
      while ($row = pg_fetch_assoc($result))
      {
        $pfileArray[$i] = $row['pfile_pk'];
        $i++;
      }
      pg_free_result($result); 
    }
    if (!empty($tag_container))
    {
      $sql = "SELECT distinct pfile_fk FROM uploadtree WHERE upload_fk = $Upload AND lft >= (SELECT lft FROM uploadtree WHERE uploadtree_pk = $Item) AND rgt <= (SELECT rgt FROM uploadtree WHERE uploadtree_pk = $Item) AND ((ufile_mode & (1<<28))=0) AND pfile_fk!=0;";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      while ($row = pg_fetch_assoc($result))
      {
        $pfileArray[$i] = $row['pfile_fk'];
        $i++;
      }
      pg_free_result($result);
    }
    
    echo sizeof($pfileArray);

    foreach($pfileArray as $pfile)
    {
      $sql = "SELECT tag_file_pk FROM tag_file WHERE tag_fk = $tag_pk AND pfile_fk = $pfile;";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      if (pg_num_rows($result) < 1)
      {
        pg_free_result($result);
        /* Add record to tag_file table */
        $sql = "INSERT INTO tag_file (tag_fk,pfile_fk,tag_file_date,tag_file_text) VALUES ($tag_pk, $pfile, now(), '$tag_notes');";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        pg_free_result($result);
      }
    }
    return (NULL);
  }

  /***********************************************************
   ShowTaggingPage(): Display the tagging page.
   ***********************************************************/
  function ShowTaggingPage($ShowMenu=0,$ShowHeader=0)
  {
    global $PG_CONN;
    $V = "";
    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);

    if (empty($Item) || empty($Upload))
        { return; }

    /**********************************
     Display micro header
     **********************************/
    if ($ShowHeader)
      {
      $V .= Dir2Browse("browse",$Item,NULL,1,"Browse");
      } // if ShowHeader
 
    /* Get ufile_name from uploadtree_pk */
    $sql = "SELECT ufile_name, ufile_mode FROM uploadtree
              WHERE uploadtree_pk = $Item";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $ufile_name = $row["ufile_name"];
    $ufile_mode = $row["ufile_mode"];
    pg_free_result($result);

    /* Create AJAX javascript */
    $V .= ActiveHTTPscript("Tags");
    $V .= "<script language='javascript'>\n";
    $V .= "function creatediv(_parent,_element,_id,_css){\n";
    $V .= "     var newObj = document.createElement(_element);\n";
    $V .= "     if(_id && _id!=\"\")newObj.id=_id;\n";
    $V .= "     if(_css && _css!=\"\"){\n";
    $V .= "             newObj.setAttribute(\"style\",_css);\n";
    $V .= "             newObj.style.cssText = _css;\n";
    $V .= "     }\n";
    $V .= "     if(_parent && _parent!=\"\"){\n";
    $V .= "             var theObj=getobj(_parent);\n";
    $V .= "             var parent = theObj.parentNode;\n";
    $V .= "             if(parent.lastChild == theObj){\n";
    $V .= "                     theObj.appendChild(newObj);\n";
    $V .= "             }\n";
    $V .= "             else{\n";
    $V .= "                     theObj.insertBefore(newObj, theObj.nextSibling);\n";
    $V .= "             }\n";
    $V .= "     }\n";
    $V .= "     else        document.body.appendChild(newObj);\n";
    $V .= "}\n";
    $V .= "function getobj(o){\n";
    $V .= "     return document.getElementById(o);\n";
    $V .= "}\n";
    $V .= "function Tags_Reply()\n";
    $V .= "{\n";
    $V .= "  if ((Tags.readyState==4) && (Tags.status==200))\n";
    $V .= "  {\n";
    $V .= "    var list = Tags.responseText;\n";
    $V .= "    var text_list = list.split(\",\")\n";
    $V .= "    var inputid = getobj(\"tag_name\");\n";
    $V .= "    if (!getobj(inputid+\"mydiv\") && list!=\"\"){\n";
    $V .= "        var divcss=\"width:240px;font-size:12px;position:absolute;left:\"+(inputid.offsetLeft+0)+\"px;top:\"+(inputid.offsetTop+23)+\"px;border:1px solid\";\n";
    $V .= "        creatediv(\"\",\"div\",inputid+\"mydiv\",divcss);\n";
    $V .= "        for (var i=0;i<text_list.length-1;i++){\n";
    $V .= "            creatediv(inputid+\"mydiv\",\"li\",inputid+\"li\"+i,\"color:#f00;background:#fff;float:left;list-style-type:none;padding:9px;margin:0;CURSOR:pointer\");\n";
    $V .= "            getobj(inputid+\"li\"+i).innerHTML=text_list[i];\n";
    $V .= "            getobj(inputid+\"li\"+i).onmouseover=function(){this.style.background=\"#eee\";}\n";
    $V .= "            getobj(inputid+\"li\"+i).onmouseout=function(){this.style.background=\"#fff\"}\n";
    $V .= "            getobj(inputid+\"li\"+i).onclick=function(){\n";
    $V .= "                                                        inputid.value=this.innerHTML;\n";
    $V .= "                                                        document.body.removeChild(getobj(inputid+\"mydiv\"));\n";
    $V .= "                                                       }\n"; 
    $V .= "        }\n";
    $V .= "    }\n";
    $V .= "  }\n";
    $V .= "}\n;";
    $V .= "</script>\n";
    
    $V.= "<form name='form' method='POST'>\n";
    /* Get TagName Space Name */
    $tag_ns = DB2KeyValArray("tag_ns", "tag_ns_pk", "tag_ns_name","");

    $text = _("Tag");
    $V .= "<p><font color='blue'>$text: $ufile_name</font></p>";
    $select = Array2SingleSelect($tag_ns, "tag_ns_pk", "");
    $text = _("Namespace");
    $V .= "<p>$text:$select</p>";
    $V .= "<p>";
    $text = _("Tag");
    $V .= "$text: <input type='text' id='tag_name' name='tag_name' autocomplete='off' onclick='Tags_Get(\"". Traceback_uri() . "?mod=tag_get&uploadtree_pk=$Item\")'/> ";

    /****** Permission comments: if user don't have add or high permission, can't see this check box ******/    
    $V .= "<input type='checkbox' name='tag_add' value='1'/>";
    $V .= _("Check to confirm this is a new tag.");
    $V .= "</p>";
    $V .= _("<p>Notes:</p>");
    $V .= "<p><textarea rows='10' cols='80' name='tag_notes'></textarea></p>";
    $text = _("Tag this files.");
    $V .= "<p><input type='checkbox' name='tag_file' value='1' checked/>$text</p>";
    if (Iscontainer($ufile_mode))
    {
      $text = _("Tag all packages (source and binary) in this container tree.");
      $V .= "<p><input type='checkbox' name='tag_package' value='1'/> $text</p>";
      $text = _("Tag every file in this container tree.");
      $V .= "<p><input type='checkbox' name='tag_container' value='1'/> $text</p>";
    }
    $text = _("Submit");
    $V.= "<input type='submit' value='$text'>\n";
    $V.= "</form>\n";

    return($V);
  }
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
    switch($this->OutputType)
      {
      case "XML":
        break;
      case "HTML":
        $Add = GetParm('tag_add', PARM_TEXT);
        if (!empty($Add))
        {
          $rc = $this->CreateTag();
	  if (!empty($rc))
          {
            $text = _("Create Tag Failed");
            $V .= displayMessage("$text: $rc");
          }else{
            $text = _("Create Tag Successful!");
            $V .= displayMessage($text);
          }
        }
        $V .= $this->ShowTaggingPage(1,1);
        break;
      case "Text":
        break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
    } // Output()

  };
$NewPlugin = new ui_tag;
$NewPlugin->Initialize();
?>
