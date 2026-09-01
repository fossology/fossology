<?php
/*
 SPDX-FileCopyrightText: © 2013-2015 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file admin_tag.php
 * \brief Create tag without tagging anything
 */

define("TITLE_ADMIN_TAG", _("Create Tag"));

class admin_tag extends FO_Plugin
{
  function __construct()
  {
    $this->Name     = "admin_tag";
    $this->Title    = TITLE_ADMIN_TAG;
    $this->MenuList = "Admin::Tag::Create Tag";
    $this->Version  = "1.3";
    $this->DBaccess = PLUGIN_DB_ADMIN;
    parent::__construct();
  }

  /**
   * \brief Check whether a tag with the given name already exists
   *
   * \param string $tag_name
   * \return boolean true if a tag with this name exists
   */
  function TagExists($tag_name)
  {
    global $PG_CONN;

    $sql = "SELECT tag_pk FROM tag WHERE tag = '" . pg_escape_string($tag_name) . "'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $exists = pg_num_rows($result) > 0;
    pg_free_result($result);
    return $exists;
  }

  /**
   * \brief Get the tag_pk of a tag by name
   *
   * \param string $tag_name
   * \return integer|null pk of the tag, or null if no such tag exists
   */
  function GetTagId($tag_name)
  {
    global $PG_CONN;

    $sql = "SELECT tag_pk FROM tag WHERE tag = '" . pg_escape_string($tag_name) . "' LIMIT 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $tagPk = null;
    if (pg_num_rows($result) > 0) {
      $row = pg_fetch_assoc($result);
      $tagPk = intval($row['tag_pk']);
    }
    pg_free_result($result);
    return $tagPk;
  }

  /**
   * \brief Create Tag without tagging anything
   *
   * \param array $tag_array Optional array with tag_name/tag_desc, used
   *        when calling this method outside of the plugin's own POST
   *        handling (e.g. from the REST API). Falls back to request
   *        parameters when not given.
   *
   * \return null for success or error text
   */
  function CreateTag($tag_array = null)
  {
    global $PG_CONN;

    if (isset($tag_array)) {
      $tag_name = $tag_array['tag_name'];
      $tag_desc = $tag_array['tag_desc'];
    } else {
      $tag_name = GetParm('tag_name', PARM_TEXT);
      $tag_desc = GetParm('tag_desc', PARM_TEXT);
    }
    if (empty($tag_name)) {
      $text = _("TagName must be specified. Tag Not created.");
      return ($text);
    }
    if (! preg_match('/^[A-Za-z0-9_~\-!@#\$%\^\*\.\(\)]+$/i', $tag_name)) {
      $text = _(
        "A Tag is only allowed to contain characters from <b>" .
        htmlentities("A-Za-z0-9_~-!@#$%^*.()") . "</b>. Tag Not created.");
      return ($text);
    }

    /* See if the tag already exists */
    $sql = "SELECT * FROM tag WHERE tag = '".pg_escape_string($tag_name)."'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1) {
      pg_free_result($result);

      $sql = "INSERT INTO tag (tag,tag_desc) VALUES ('" .
        pg_escape_string($tag_name) . "', '" . pg_escape_string($tag_desc) .
        "');";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
    }
    pg_free_result($result);

    /* Make sure it was added */
    $sql = "SELECT * FROM tag WHERE tag = '".pg_escape_string($tag_name)."' LIMIT 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1) {
      pg_free_result($result);
      $text = _("Failed to create tag.");
      return ($text);
    }
    pg_free_result($result);

    return (null);
  }

  /**
   * \brief Show all tags
   */
  function ShowExistTags()
  {
    global $PG_CONN;
    $VE = _("<h3>Current Tags:</h3>\n");
    $sql = "SELECT tag_pk, tag, tag_desc FROM tag ORDER BY tag_pk desc;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) > 0) {
      $VE .= "<table border=1>\n";
      $text1 = _("Tag pk");
      $text2 = _("Tag");
      $text3 = _("Tag Description");
      $VE .= "<tr><th>$text1</th><th>$text2</th><th>$text3</th></tr>\n";
      while ($row = pg_fetch_assoc($result)) {
        $VE .= "<tr><td align='center'>" . $row['tag_pk'] .
          "</td><td align='center'>" . htmlspecialchars($row['tag']) .
          "</td><td align='center'>" . htmlspecialchars($row['tag_desc']) .
          "</td>";
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
    $VC = _("<h3>Create Tag:</h3>\n");
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


  public function Output()
  {
    $V="";
    $action = GetParm('action', PARM_TEXT);

    if ($action == 'add') {
      $rc = $this->CreateTag();
      if (!empty($rc)) {
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
