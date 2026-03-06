<?php
/*
 SPDX-FileCopyrightText: © 2010-2011 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;


class ui_tag extends FO_Plugin
{
  /** @var UploadDao */
  private $uploadDao;

  function __construct()
  {
    $this->Name     = "tag";
    $this->Title    = _("Tag");
    $this->DBaccess = PLUGIN_DB_WRITE;
    parent::__construct();
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $text = _("Tag files or containers");
    menu_insert("Browse-Pfile::Tag",0,$this->Name,$text);
  } // RegisterMenus()

  /**
   * \brief Add a new Tag.
   */
  function CreateTag($tag_array)
  {
    global $PG_CONN;

    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item", PARM_INTEGER);

    if (empty($Item) || empty($Upload)) {
      return;
    }

    if (isset($tag_array)) {
      $tag_name = $tag_array["tag_name"];
      $tag_notes = $tag_array["tag_notes"];
      $tag_file = $tag_array["tag_file"];
      $tag_package = $tag_array["tag_package"];
      $tag_container = $tag_array["tag_container"];
      $tag_desc = $tag_array["tag_desc"];
      $tag_dir = $tag_array["tag_dir"];
    } else {
      $tag_name = GetParm('tag_name', PARM_TEXT);
      $tag_notes = GetParm('tag_notes', PARM_TEXT);
      $tag_file = GetParm('tag_file', PARM_TEXT);
      $tag_package = GetParm('tag_package', PARM_TEXT);
      $tag_container = GetParm('tag_container', PARM_TEXT);
      $tag_desc = GetParm('tag_desc', PARM_TEXT);
      $tag_dir = GetParm('tag_dir', PARM_TEXT);
    }

    if (empty($tag_name)) {
      $text = _("TagName must be specified. Tag Not created.");
      return ($text);
    }
    /* Need select tag file/package/container */
    if (empty($tag_dir) && empty($tag_file) && empty($tag_package) &&
      empty($tag_container)) {
      $text = _(
        "Need to select one option (dir/file/package/container) to create tag.");
      return ($text);
    }

    global $container;
    /** @var DbManager $dbManager */
    $dbManager = $container->get('db.manager');

    /* See if the tag already exists */
    $sql = "SELECT * FROM tag WHERE tag = $1";
    $result = $dbManager->getSingleRow($sql, array($tag_name), __METHOD__ . ".checkExist");

    if (empty($result)) {
      $insertTagStmt = __FUNCTION__ . ".insertTag";
      $dbManager->prepare($insertTagStmt, "INSERT INTO tag (tag,tag_desc) VALUES ($1, $2);");
      $dbManager->freeResult($dbManager->execute($insertTagStmt, array($tag_name, $tag_desc)));
    }

    /* Make sure it was added */
    $sql = "SELECT * FROM tag WHERE tag = $1 LIMIT 1;";
    $row = $dbManager->getSingleRow($sql, array($tag_name), __METHOD__ . ".checkAdded");

    if (empty($row)) {
      $text = _("Failed to create tag.");
      return ($text);
    }

    $tag_pk = $row["tag_pk"];

    $pfileArray = array();
    $i = 0;

    if (! empty($tag_file)) {
      /* Get pfile_fk from uploadtree_pk */
      $sql = "SELECT pfile_fk FROM uploadtree
              WHERE uploadtree_pk = $1 LIMIT 1";
      $row = $dbManager->getSingleRow($sql, array($Item), __METHOD__ . ".getPfile");
      if (!empty($row)) {
        $pfileArray[$i] = $row['pfile_fk'];
        $i ++;
      }
    }

    if (! empty($tag_package)) {
      /* GetPkgMimetypes */
      $MimetypeArray = GetPkgMimetypes();
      $sql = "SELECT distinct pfile.pfile_pk FROM uploadtree, pfile WHERE uploadtree.pfile_fk = pfile.pfile_pk AND (pfile.pfile_mimetypefk = $1 OR pfile.pfile_mimetypefk = $2 OR pfile.pfile_mimetypefk = $3) AND uploadtree.upload_fk = $4 AND uploadtree.lft >= (SELECT lft FROM uploadtree WHERE uploadtree_pk = $5) AND uploadtree.rgt <= (SELECT rgt FROM uploadtree WHERE uploadtree_pk = $6);";

      $rows = $dbManager->getRows($sql, array($MimetypeArray[0], $MimetypeArray[1], $MimetypeArray[2], $Upload, $Item, $Item), __METHOD__ . ".getPackagePfiles");
      foreach ($rows as $row) {
        $pfileArray[$i] = $row['pfile_pk'];
        $i ++;
      }
    }
    if (! empty($tag_container)) {
      $sql = "SELECT distinct pfile_fk FROM uploadtree WHERE upload_fk = $1 AND lft >= (SELECT lft FROM uploadtree WHERE uploadtree_pk = $2) AND rgt <= (SELECT rgt FROM uploadtree WHERE uploadtree_pk = $3) AND ((ufile_mode & (1<<28))=0) AND pfile_fk!=0;";
      $rows = $dbManager->getRows($sql, array($Upload, $Item, $Item), __METHOD__ . ".getContainerPfiles");
      foreach ($rows as $row) {
        $pfileArray[$i] = $row['pfile_fk'];
        $i ++;
      }
    }

    if (! empty($tag_dir)) {
      $sql = "SELECT tag_uploadtree_pk FROM tag_uploadtree WHERE tag_fk = $1 AND uploadtree_fk = $2;";
      $row = $dbManager->getSingleRow($sql, array($tag_pk, $Item), __METHOD__ . ".checkDirTagExist");
      if (empty($row)) {
        /* Add record to tag_uploadtree table */
        $insertTagUploadTreeStmt = __FUNCTION__ . ".insertTagUploadTree";
        $dbManager->prepare($insertTagUploadTreeStmt, "INSERT INTO tag_uploadtree (tag_fk,uploadtree_fk,tag_uploadtree_date,tag_uploadtree_text) VALUES ($1, $2, now(), $3);");
        $dbManager->freeResult($dbManager->execute($insertTagUploadTreeStmt, array($tag_pk, $Item, $tag_notes)));
      } else {
        $text = _("This Tag already associated with this Directory!");
        return ($text);
      }
    } else {
      foreach ($pfileArray as $pfile) {
        $sql = "SELECT tag_file_pk FROM tag_file WHERE tag_fk = $1 AND pfile_fk = $2;";
        $row = $dbManager->getSingleRow($sql, array($tag_pk, $pfile), __METHOD__ . ".checkFileTagExist");
        if (empty($row)) {
          /* Add record to tag_file table */
          $insertTagFileStmt = __FUNCTION__ . ".insertTagFile";
          $dbManager->prepare($insertTagFileStmt, "INSERT INTO tag_file (tag_fk,pfile_fk,tag_file_date,tag_file_text) VALUES ($1, $2, now(), $3);");
          $dbManager->freeResult($dbManager->execute($insertTagFileStmt, array($tag_pk, $pfile, $tag_notes)));
        } else {
          $text = _("This Tag already associated with this File!");
          return ($text);
        }
      }
    }
    return (null);
  }

  /**
   * \brief Edit exsit Tag.
   */
  function EditTag()
  {
    global $PG_CONN;

    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);

    if (empty($Item) || empty($Upload)) {
      return;
    }

    $tag_pk = GetParm('tag_pk', PARM_INTEGER);
    $tag_file_pk = GetParm('tag_file_pk', PARM_INTEGER);
    $tag_name = GetParm('tag_name', PARM_TEXT);
    $tag_notes = GetParm('tag_notes', PARM_TEXT);
    $tag_file = GetParm('tag_file', PARM_TEXT);
    $tag_package = GetParm('tag_package', PARM_TEXT);
    $tag_container = GetParm('tag_container', PARM_TEXT);
    $tag_desc = GetParm('tag_desc', PARM_TEXT);
    $tag_dir = GetParm('tag_dir', PARM_TEXT);

    if (empty($tag_name)) {
      $text = _("TagName must be specified. Tag Not Updated.");
      return ($text);
    } else {
      global $container;
      /** @var DbManager $dbManager */
      $dbManager = $container->get('db.manager');
      /* Check if tag_name has changed and if the new name is already in use */
      $sql = "SELECT tag FROM tag WHERE tag_pk = $1;";
      $row = $dbManager->getSingleRow($sql, array($tag_pk), __METHOD__ . ".getTagName");

      /* Is Tag name changed */
      if ($row['tag'] <> $tag_name) {
        $sql = "SELECT * FROM tag WHERE tag = $1";
        $result = $dbManager->getSingleRow($sql, array($tag_name), __METHOD__ . ".checkNewNameExist");
        /* Is new Tag name defined in name space */
        if (!empty($result)) {
          /* Delete old tag association */
          $this->DeleteTag();
          /* Existing tag values cannot be changed at this phase. */
          /* Create new tag association, do not delete old notes! */

          $tag_data = array("tag_pk" => $result['tag_pk'], "tag_name" => $result['tag'], "tag_desc" => $result['tag_desc'],
                            "tag_notes" => $tag_notes, "tag_file" => $tag_file, "tag_package" => $tag_package,
                            "tag_container" => $tag_container, "tag_dir" => $tag_dir);
          $this->CreateTag($tag_data);
          return (null);
        }
      }
    }
    /* Update the tag table */
    $updateTagStmt = __FUNCTION__ . ".updateTag";
    $dbManager->prepare($updateTagStmt, "UPDATE tag SET tag = $1, tag_desc = $2 WHERE tag_pk = $3;");
    $dbManager->freeResult($dbManager->execute($updateTagStmt, array($tag_name, $tag_desc, $tag_pk)));

    if (! empty($tag_dir)) {
      $sql = "UPDATE tag_uploadtree SET tag_uploadtree_date = now(), tag_uploadtree_text = $1 WHERE tag_uploadtree_pk = $2;";

      $updateTagUploadTreeStmt = __FUNCTION__ . ".updateTagUploadTree";
      $dbManager->prepare($updateTagUploadTreeStmt, $sql);
      $dbManager->freeResult($dbManager->execute($updateTagUploadTreeStmt, array($tag_notes, $tag_file_pk)));
    } else {
      $sql = "UPDATE tag_file SET tag_file_date = now(), tag_file_text = $1 WHERE tag_file_pk = $2;";

      $updateTagFileStmt = __FUNCTION__ . ".updateTagFile";
      $dbManager->prepare($updateTagFileStmt, $sql);
      $dbManager->freeResult($dbManager->execute($updateTagFileStmt, array($tag_notes, $tag_file_pk)));
    }
    return (null);
  }

  /**
   * \brief Delete exsit Tag.
   */
  function DeleteTag()
  {
    global $PG_CONN;

    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);

    if (empty($Item) || empty($Upload)) {
      return;
    }
    $tag_file_pk = GetParm('tag_file_pk', PARM_INTEGER);

    global $container;
    /** @var DbManager $dbManager */
    $dbManager = $container->get('db.manager');

    $sql = "SELECT ufile_name, ufile_mode FROM uploadtree
              WHERE uploadtree_pk = $1";
    $row = $dbManager->getSingleRow($sql, array($Item), __METHOD__ . ".getUfileMode");
    $ufile_mode = $row["ufile_mode"];

    if (Isdir($ufile_mode)) {
      $deleteTagStmt = __FUNCTION__ . ".deleteTagUploadTree";
      $dbManager->prepare($deleteTagStmt, "DELETE FROM tag_uploadtree WHERE tag_uploadtree_pk = $1");
      $dbManager->freeResult($dbManager->execute($deleteTagStmt, array($tag_file_pk)));
    } else {
      $deleteTagStmt = __FUNCTION__ . ".deleteTagFile";
      $dbManager->prepare($deleteTagStmt, "DELETE FROM tag_file WHERE tag_file_pk = $1");
      $dbManager->freeResult($dbManager->execute($deleteTagStmt, array($tag_file_pk)));
    }
  }

  /**
   * \brief Show all tags about
   *
   * \param  $Uploadtree_pk - uploadtree id
   */
  function ShowExistTags($Upload,$Uploadtree_pk)
  {
    global $container;
    /** @var DbManager $dbManager */
    $dbManager = $container->get('db.manager');

    $VE = "";
    $VE = _("<h3>Current Tags:</h3>\n");
    $sql = "SELECT t.tag_pk, t.tag, t.tag_desc, tf.tag_file_pk, tf.tag_file_date, tf.tag_file_text FROM tag t JOIN tag_file tf ON t.tag_pk = tf.tag_fk JOIN uploadtree ut ON tf.pfile_fk = ut.pfile_fk WHERE ut.uploadtree_pk = $1 UNION SELECT t.tag_pk, t.tag, t.tag_desc, tut.tag_uploadtree_pk AS tag_file_pk, tut.tag_uploadtree_date AS tag_file_date, tut.tag_uploadtree_text AS tag_file_text FROM tag t JOIN tag_uploadtree tut ON t.tag_pk = tut.tag_fk WHERE tut.uploadtree_fk = $2;";

    $rows = $dbManager->getRows($sql, array($Uploadtree_pk, $Uploadtree_pk), __METHOD__ . ".getBoundTags");

    if (!empty($rows)) {
      $VE .= "<table border=1>\n";
      $text1 = _("Tag");
      $text2 = _("Tag Description");
      $text3 = _("Tag Date");
      $VE .= "<tr><th>$text1</th><th>$text2</th><th>$text3</th><th></th></tr>\n";
      foreach ($rows as $row) {
        $VE .= "<tr><td align='center'>" . htmlspecialchars($row['tag']) . "</td><td align='center'>" . htmlspecialchars($row['tag_desc']) . "</td><td align='center'>" . substr($row['tag_file_date'],0,19) . "</td>";
        if ($this->uploadDao->isEditable($Upload, Auth::getGroupId())) {
          $VE .= "<td align='center'><a href='" . Traceback_uri() . "?mod=tag&action=edit&upload=$Upload&item=$Uploadtree_pk&tag_file_pk=" . $row['tag_file_pk'] . "'>View/Edit</a>|<a href='" . Traceback_uri() . "?mod=tag&action=delete&upload=$Upload&item=$Uploadtree_pk&tag_file_pk=" . $row['tag_file_pk'] . "'>Delete</a></td></tr>\n";
        } else {
          $nopermtext = _("No permission to edit tag.");
          $VE .= "<td align='center'>$nopermtext</td></tr>\n";
        }
      }
      $VE .= "</table><p>\n";
    }

    return $VE;
  }

  /**
   * \brief Display the ajax page.
   */
  function ShowAjaxPage()
  {
    $VA = "";
    /* Create AJAX javascript */
    $VA .= ActiveHTTPscript("Tags");
    $VA .= "<script language='javascript'>\n";
    $VA .= "var swtemp=0,objtemp;\n";
    $VA .= "function mouseout(o){\n";
    $VA .= "     o.style.display = \"none\";\n";
    $VA .= "     swtemp = 0;\n";
    $VA .= "}\n";
    $VA .= "function removediv(inputid){\n";
    $VA .= "     getobj(inputid+\"mydiv\").style.display=\"none\";\n";
    $VA .= "}\n";
    $VA .= "function creatediv(_parent,_element,_id,_css){\n";
    $VA .= "     var newObj = document.createElement(_element);\n";
    $VA .= "     if(_id && _id!=\"\")newObj.id=_id;\n";
    $VA .= "     if(_css && _css!=\"\"){\n";
    $VA .= "             newObj.setAttribute(\"style\",_css);\n";
    $VA .= "             newObj.style.cssText = _css;\n";
    $VA .= "     }\n";
    $VA .= "     if(_parent && _parent!=\"\"){\n";
    $VA .= "             var theObj=getobj(_parent);\n";
    $VA .= "             var parent = theObj.parentNode;\n";
    $VA .= "             if(parent.lastChild == theObj){\n";
    $VA .= "                     theObj.appendChild(newObj);\n";
    $VA .= "             }\n";
    $VA .= "             else{\n";
    $VA .= "                     theObj.insertBefore(newObj, theObj.nextSibling);\n";
    $VA .= "             }\n";
    $VA .= "     }\n";
    $VA .= "     else        document.body.appendChild(newObj);\n";
    $VA .= "}\n";
    $VA .= "function getobj(o){\n";
    $VA .= "     return document.getElementById(o);\n";
    $VA .= "}\n";
    $VA .= "function Tags_Reply()\n";
    $VA .= "{\n";
    $VA .= "  if ((Tags.readyState==4) && (Tags.status==200))\n";
    $VA .= "  {\n";
    $VA .= "    var list = Tags.responseText;\n";
    $VA .= "    var text_list = list.split(\",\")\n";
    $VA .= "    var inputid = getobj(\"tag_name\");\n";
    $VA .= "    if (swtemp==1){getobj(objtemp+\"mydiv\").style.display=\"none\";}\n";
    $VA .= "    if (!getobj(inputid+\"mydiv\") && list!=\"\"){\n";
    $VA .= "        var divcss=\"width:240px;font-size:12px;position:absolute;left:\"+(inputid.offsetLeft+0)+\"px;top:\"+(inputid.offsetTop+23)+\"px;border:1px solid\";\n";
    $VA .= "        creatediv(\"\",\"div\",inputid+\"mydiv\",divcss);\n";
    $VA .= "        for (var i=0;i<text_list.length-1;i++){\n";
    $VA .= "            creatediv(inputid+\"mydiv\",\"li\",inputid+\"li\"+i,\"color:#000;background:#fff;list-style-type:none;padding:9px;margin:0;CURSOR:pointer\");\n";
    $VA .= "            getobj(inputid+\"li\"+i).innerHTML=text_list[i];\n";
    $VA .= "            getobj(inputid+\"li\"+i).onmouseover=function(){this.style.background=\"#eee\";}\n";
    $VA .= "            getobj(inputid+\"li\"+i).onmouseout=function(){this.style.background=\"#fff\"}\n";
    $VA .= "            getobj(inputid+\"li\"+i).onclick=function(){\n";
    $VA .= "                                                        inputid.value=this.innerHTML;\n";
    $VA .= "                                                        removediv(inputid);\n";
    $VA .= "                                                       }\n";
    $VA .= "        }\n";
    $VA .= "    }\n";
    $VA .= "    var newdiv=getobj(inputid+\"mydiv\");\n";
    //$VA .= "    newdiv.onclick=function(){removediv(inputid);}\n";
    $VA .= "    document.body.onclick = function(){removediv(inputid);}\n";
    $VA .= "    newdiv.onblur=function(){mouseout(this);}\n";
    $VA .= "    newdiv.style.display=\"block\";\n";
    $VA .= "    swtemp=1;\n";
    $VA .= "    objtemp=inputid;\n";
    $VA .= "    newdiv.focus();\n";
    $VA .= "  }\n";
    $VA .= "}\n;";
    $VA .= "</script>\n";

    return $VA;
  }

  /**
   * \brief Display the create tag page.
   */
  function ShowCreateTagPage($Upload,$Item)
  {
    global $container;
    /** @var DbManager $dbManager */
    $dbManager = $container->get('db.manager');
    $VC = "";
    $VC .= _("<h3>Create Tag:</h3>\n");

    /* Get ufile_name from uploadtree_pk */
    $sql = "SELECT ufile_name, ufile_mode FROM uploadtree
              WHERE uploadtree_pk = $1";
    $row = $dbManager->getSingleRow($sql, array($Item), __METHOD__ . ".getUfileInfo");
    $ufile_name = $row["ufile_name"];
    $ufile_mode = $row["ufile_mode"];

    $VC.= "<form name='form' method='POST' action='" . Traceback_uri() ."?mod=tag&upload=$Upload&item=$Item'>\n";

    $VC .= "<p>";
    $text = _("Tag");
    $VC .= "$text: <input type='text' id='tag_name' name='tag_name' maxlength='32' utocomplete='off' onclick='Tags_Get(\"". Traceback_uri() . "?mod=tag_get&uploadtree_pk=$Item\")'/> ";

    /****** Permission comments: if user don't have add or high permission, can't see this check box ******/
    //$VC .= "<input type='checkbox' name='tag_add' value='1'/>";
    //$VC .= _("Check to confirm this is a new tag.");
    $VC .= "</p>";
    $text = _("Tag description:");
    $VC .= "<p>$text <input type='text' name='tag_desc'/></p>";
    $VC .= _("<p>Notes:</p>");
    $VC .= "<p><textarea rows='10' cols='80' name='tag_notes'></textarea></p>";

    if (Isdir($ufile_mode)) {
      $VC .= "<p><input type='hidden' name='tag_dir' value='1'/></p>";
    } else if (Iscontainer($ufile_mode)) {
      /* Recursively tagging UI part comment out */
      /*
       $text = _("Tag this files only.");
       $VC .= "<p><input type='checkbox' name='tag_file' value='1' checked/>$text</p>";
       $text = _("Tag all packages (source and binary) in this container tree.");
       $VC .= "<p><input type='checkbox' name='tag_package' value='1'/> $text</p>";
       $text = _("Tag every file in this container tree.");
       $VC .= "<p><input type='checkbox' name='tag_container' value='1'/> $text</p>";
       */
      $VC .= "<p><input type='hidden' name='tag_file' value='1'/></p>";
    } else {
      $VC .= "<p><input type='hidden' name='tag_file' value='1'/></p>";
    }
    $text = _("Create");
    $VC .= "<input type='hidden' name='action' value='add'/>\n";
    $VC .= "<input type='submit' value='$text'>\n";
    $VC .= "</form>\n";

    return $VC;
  }

  /**
   * \brief Display the edit tag page.
   */
  function ShowEditTagPage($Upload,$Item)
  {
    global $container;
    /** @var DbManager $dbManager */
    $dbManager = $container->get('db.manager');
    $VEd = "";
    $text = _("Create New Tag");
    $VEd .= "<h4><a href='" . Traceback_uri() . "?mod=tag&upload=$Upload&item=$Item'>$text</a><h4>";

    $VEd .= _("<h3>Edit Tag:</h3>\n");
    $tag_file_pk = GetParm("tag_file_pk",PARM_INTEGER);

    /* Get ufile_name from uploadtree_pk */
    $sql = "SELECT ufile_name, ufile_mode FROM uploadtree
              WHERE uploadtree_pk = $1";
    $row = $dbManager->getSingleRow($sql, array($Item), __METHOD__ . ".getUfileInfo");
    $ufile_name = $row["ufile_name"];
    $ufile_mode = $row["ufile_mode"];

    /* Get all information about $tag_file_pk (tag_file/tag_uploadtree table)*/
    if (Isdir($ufile_mode)) {
      $sql = "SELECT tag_pk, tag_uploadtree_text, tag, tag_desc FROM tag_uploadtree, tag WHERE tag_uploadtree_pk=$1 AND tag_uploadtree.tag_fk = tag.tag_pk";
    } else {
      $sql = "SELECT tag_pk, tag_file_text, tag, tag_desc FROM tag_file, tag WHERE tag_file_pk=$1 AND tag_file.tag_fk = tag.tag_pk";
    }
    $row = $dbManager->getSingleRow($sql, array($tag_file_pk), __METHOD__ . ".getTagInfo");
    $tag_pk = $row['tag_pk'];
    $tag = $row['tag'];
    if (Isdir($ufile_mode)) {
      $tag_notes = $row['tag_uploadtree_text'];
    } else {
      $tag_notes = $row['tag_file_text'];
    }
    $tag_desc = $row['tag_desc'];

    $VEd.= "<form name='form' method='POST' action='" . Traceback_uri() ."?mod=tag&upload=$Upload&item=$Item'>\n";
    $VEd .= "<p>";
    $text = _("Tag");
    $VEd .= "$text: <input type='text' id='tag_name' name='tag_name' autocomplete='off' onclick='Tags_Get(\"". Traceback_uri() . "?mod=tag_get&uploadtree_pk=$Item\")' value=\"" . htmlspecialchars($tag) . "\"/> ";
    $text = _("Tag description:");
    $VEd .= "<p>$text <input type='text' name='tag_desc' value=\"" . htmlspecialchars($tag_desc) . "\"/></p>";
    $VEd .= _("<p>Notes:</p>");
    $VEd .= "<p><textarea rows='10' cols='80' name='tag_notes'>" . htmlspecialchars($tag_notes) . "</textarea></p>";

    if (Isdir($ufile_mode)) {
      $VEd .= "<p><input type='hidden' name='tag_dir' value='1'/></p>";
    } else if (Iscontainer($ufile_mode)) {
      /*
       $text = _("Tag this files only.");
       $VEd .= "<p><input type='checkbox' name='tag_file' value='1' checked/>$text</p>";
       $text = _("Tag all packages (source and binary) in this container tree.");
       $VEd .= "<p><input type='checkbox' name='tag_package' value='1'/> $text</p>";
       $text = _("Tag every file in this container tree.");
       $VEd .= "<p><input type='checkbox' name='tag_container' value='1'/> $text</p>";
       */
      $VEd .= "<p><input type='hidden' name='tag_file' value='1'/></p>";
    } else {
      $VEd .= "<p><input type='hidden' name='tag_file' value='1'/></p>";
    }
    $text = _("Save");
    $VEd .= "<input type='hidden' name='action' value='update'/>\n";
    $VEd .= "<input type='hidden' name='tag_pk' value='$tag_pk'/>\n";
    $VEd .= "<input type='hidden' name='tag_file_pk' value='$tag_file_pk'/>\n";
    $VEd .= "<input type='submit' value='$text'>\n";
    $VEd .= "</form>\n";

    return $VEd;
  }

  /**
   * \brief Display the delete tag page.
   */
  function ShowDeleteTagPage($Upload,$Item)
  {
    global $container;
    /** @var DbManager $dbManager */
    $dbManager = $container->get('db.manager');
    $VD = "";
    $VD .= _("<h3>Delete Tag:</h3>\n");

    /* Get ufile_name from uploadtree_pk */
    $sql = "SELECT ufile_name, ufile_mode FROM uploadtree
              WHERE uploadtree_pk = $1";
    $row = $dbManager->getSingleRow($sql, array($Item), __METHOD__ . ".getUfileInfo");
    $ufile_name = $row["ufile_name"];
    $ufile_mode = $row["ufile_mode"];

    $sql = "SELECT tag_pk, tag, tag_file_pk, tag_file_date, tag_file_text FROM tag, tag_file, uploadtree WHERE tag.tag_pk = tag_file.tag_fk AND tag_file.pfile_fk = uploadtree.pfile_fk AND uploadtree.uploadtree_pk = $1;";
    $rows = $dbManager->getRows($sql, array($Item), __METHOD__ . ".getTags");

    if (!empty($rows)) {
      $VD .= "<form name='form' method='POST' action='" . Traceback_uri() ."?mod=tag&upload=$Upload&item=$Item'>\n";
      $VD .= "<select multiple size='10' name='tag_file_pk[]'>\n";
      foreach ($rows as $row) {
        $VD .= "<option value='" . $row['tag_file_pk'] . "'>" . "-" . htmlspecialchars($row['tag']) . "</option>\n";
      }
      $VD .= "</select>\n";
      if (Iscontainer($ufile_mode)) {
        $text = _("Delete Tag only for this file.");
        $VD .= "<p><input type='checkbox' name='tag_file' value='1' checked/>$text</p>";
        $text = _("Delete Tag for all packages (source and binary) in this container tree.");
        $VD .= "<p><input type='checkbox' name='tag_package' value='1'/>$text</p>";
        //$text = _("Delete Tag for every file in this container tree.");
        //$VD .= "<p><input type='checkbox' name='tag_container' value='1'/> $text</p>";
      } else {
        $VD .= "<p><input type='hidden' name='tag_file' value='1'/></p>";
      }
      $text = _("Delete");
      $VD .= "<input type='hidden' name='action' value='delete'/>\n";
      $VD .= "<input type='submit' value='$text'>\n";
      $VD .= "</form>\n";
    }

    return ($VD);
  }

  /**
   * \brief Display the tagging page.
   * @param string $action
   * @param int $ShowHeader
   */
  function ShowTaggingPage($action,$ShowHeader=0)
  {
    $V = "";
    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);

    if (empty($Item) || empty($Upload)) {
      return;
    }

    /**********************************
     Display micro header
     **********************************/
    if ($ShowHeader) {
      $V .= Dir2Browse("browse",$Item,NULL,1,"Browse");
    }

    $V .=  $this->ShowExistTags($Upload,$Item);
    $V .= $this->ShowAjaxPage();

    if ($action == 'edit') {
      $V .= $this->ShowEditTagPage($Upload,$Item);
    } else {
        /* Show create tag page */
      if ($this->uploadDao->isEditable($Upload, Auth::getGroupId())) {
        $V .= $this->ShowCreateTagPage($Upload, $Item);
      } else {
        $nopermtext = _("You do not have permission to tag this upload.");
        $V .= $nopermtext;
      }
    }
    return($V);
  }


  public function Output()
  {
    $V="";
    $action = GetParm('action', PARM_TEXT);

    if ($action == 'add') {
      $rc = $this->CreateTag(null);
      if (! empty($rc)) {
        $text = _("Create Tag Failed");
        $this->vars['message'] = "$text: $rc";
      } else {
        $this->vars['message'] = _("Create Tag Successful!");
      }
    }
    if ($action == 'update') {
      $rc = $this->EditTag();
      if (! empty($rc)) {
        $text = _("Edit Tag Failed");
        $this->vars['message'] = "$text: $rc";
      } else {
        $this->vars['message'] = _("Edit Tag Successful!");
      }
    }
    if ($action == 'delete') {
      $rc = $this->DeleteTag();
      if (! empty($rc)) {
        $text = _("Delete Tag Failed");
        $this->vars['message'] = "$text: $rc";
      } else {
        $this->vars['message'] = _("Delete Tag Successful!");
      }
    }
    $V .= $this->ShowTaggingPage($action,1);

    return $V;
  }
}

$NewPlugin = new ui_tag();
$NewPlugin->Initialize();
