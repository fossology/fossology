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
   * \brief Create Tag without tagging anything
   *
   * \return null for success or error text
   */
  function CreateTag()
  {
    $tag_name = GetParm('tag_name', PARM_TEXT);
    $tag_desc = GetParm('tag_desc', PARM_TEXT);
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

    global $container;
    /** @var DbManager $dbManager */
    $dbManager = $container->get('db.manager');

    /* See if the tag already exists */
    $sql = "SELECT * FROM tag WHERE tag = $1";
    $result = $dbManager->getSingleRow($sql, array($tag_name), __METHOD__ . ".checkExist");

    if (empty($result)) {
      $insertTagStmt = __FUNCTION__ . ".insertTag";
      $dbManager->prepare($insertTagStmt, "INSERT INTO tag (tag,tag_desc) VALUES ($1, $2);");
      $dbManager->execute($insertTagStmt, array($tag_name, $tag_desc));
    }

    /* Make sure it was added */
    $sql = "SELECT * FROM tag WHERE tag = $1 LIMIT 1;";
    $row = $dbManager->getSingleRow($sql, array($tag_name), __METHOD__ . ".checkAdded");

    if (empty($row)) {
      $text = _("Failed to create tag.");
      return ($text);
    }

    return (null);
  }

  /**
   * \brief Show all tags
   */
  function ShowExistTags()
  {
    global $container;
    /** @var DbManager $dbManager */
    $dbManager = $container->get('db.manager');

    $VE = _("<h3>Current Tags:</h3>\n");
    $sql = "SELECT tag_pk, tag, tag_desc FROM tag ORDER BY tag_pk desc;";
    $rows = $dbManager->getRows($sql, array(), __METHOD__);

    if (count($rows) > 0) {
      $VE .= "<table border=1>\n";
      $text1 = _("Tag pk");
      $text2 = _("Tag");
      $text3 = _("Tag Description");
      $VE .= "<tr><th>$text1</th><th>$text2</th><th>$text3</th></tr>\n";
      foreach ($rows as $row) {
        $VE .= "<tr><td align='center'>" . $row['tag_pk'] .
          "</td><td align='center'>" . htmlspecialchars($row['tag']) .
          "</td><td align='center'>" . htmlspecialchars($row['tag_desc']) .
          "</td>";
      }
      $VE .= "</table><p>\n";
    }
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
