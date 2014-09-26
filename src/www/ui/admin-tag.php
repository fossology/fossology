<?php
/***********************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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

/**
 * \file admin_tag.php
 * \brief Create tag without tagging anything
 */

define("TITLE_admin_tag", _("Creat Tag"));

class admin_tag extends FO_Plugin
{
  function __construct()
  {
    $this->Name       = "admin_tag";
    $this->Title      = TITLE_admin_tag;
    $this->MenuList = "Admin::Tag::Create Tag";
    $this->Version = "1.3";
    $this->DBaccess = PLUGIN_DB_ADMIN;
    parent::__construct();
  }

  /**
   * \brief Create Tag without tagging anything
   * 
   * \return null for success or error text
   */
  function CreateTag()
  {
    global $PG_CONN;

    $tag_name = GetParm('tag_name', PARM_TEXT);
    $tag_desc = GetParm('tag_desc', PARM_TEXT);
    if (empty($tag_name))
    {
      $text = _("TagName must be specified. Tag Not created.");
      return ($text);
    }

    /* See if the tag already exists */
    $sql = "SELECT * FROM tag WHERE tag = '$tag_name'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1)
    {
      pg_free_result($result);

      $Val = str_replace("'", "''", $tag_name);
      $Val1 = str_replace("'", "''", $tag_desc);
      $sql = "INSERT INTO tag (tag,tag_desc) VALUES ('$Val', '$Val1');";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    }else{
      pg_free_result($result);
    }
    
    /* Make sure it was added */
    $sql = "SELECT * FROM tag WHERE tag = '$tag_name' LIMIT 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1)
    {
      pg_free_result($result);
      $text = _("Failed to create tag.");
      return ($text);
    }

    $row = pg_fetch_assoc($result);
    $tag_pk = $row["tag_pk"];
    pg_free_result($result);

    return (NULL);
  }

  /**
   * \brief Show all tags
   */
  function ShowExistTags()
  {
    global $PG_CONN;
    $VE = "";
    $VE = _("<h3>Current Tags:</h3>\n");
    $sql = "SELECT tag_pk, tag, tag_desc FROM tag ORDER BY tag_pk desc;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) > 0)
    {
      $VE .= "<table border=1>\n";
      $text1 = _("Tag pk");
      $text2 = _("Tag");
      $text3 = _("Tag Description");
      $VE .= "<tr><th>$text1</th><th>$text2</th><th>$text3</th></tr>\n";
      while ($row = pg_fetch_assoc($result))
      {
        $VE .= "<tr><td align='center'>" . $row['tag_pk'] . "</td><td align='center'>" . $row['tag'] . "</td><td align='center'>" . $row['tag_desc'] . "</td>";
      }
      $VE .= "</table><p>\n";
    }
    pg_free_result($result);
    return $VE;
  }

  /**
   * \brief Display the create tag page.
   */
  function ShowCreateTagPage()
  {
    $VC = "";
    $VC .= _("<h3>Create Tag:</h3>\n");
    $VC.= "<form name='form' method='POST' action='" . Traceback_uri() ."?mod=admin_tag'>\n";
    $VC .= "<p>";
    $text = _("Tag");
    $VC .= "$text: <input type='text' id='tag_name' name='tag_name' maxlength='32' utocomplete='off'/> ";
    $VC .= "</p>";
    $text = _("Tag description:");
    $VC .= "<p>$text <input type='text' name='tag_desc'/></p>";
    $text = _("Create");
    $VC .= "<input type='hidden' name='action' value='add'/>\n";
    $VC .= "<input type='submit' value='$text'>\n";
    $VC .= "</form>\n";
    return $VC;
  }

  
  protected function htmlContent()
  {
    $V="";
    $action = GetParm('action', PARM_TEXT);

    if ($action == 'add')
    {
      $rc = $this->CreateTag();
      if (!empty($rc))
      {
        $text = _("Create Tag Failed");
        $V .= displayMessage("$text: $rc");
      } else {
        $text = _("Create Tag Successful!");
        $V .= displayMessage($text);
      }
    }
    $V .= $this->ShowCreateTagPage();
    $V .= $this->ShowExistTags();
    return $V;
  }

}
$NewPlugin = new admin_tag;
$NewPlugin->Initialize();
