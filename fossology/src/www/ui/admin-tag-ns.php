<?php
/***********************************************************
 Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.

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

define("TITLE_admin_tag_ns", _("Manage Tag Namespace"));

class admin_tag_ns extends FO_Plugin
{
  var $Name       = "admin_tag_ns";
  var $Title      = TITLE_admin_tag_ns;
  var $MenuList = "Admin::Tag::Manage TagNS";
  var $Version = "1.3";
  var $Dependency = array();
  var $DBaccess = PLUGIN_DB_USERADMIN;

  /**
   * \brief Add a new Tag Namespace.
   */
  function CreateTagNS()
  {
    global $PG_CONN;

    $tag_ns_name = str_replace("'", "''", GetParm('tag_ns_name', PARM_TEXT));

    /* See if the tag namespace already exists */
    $sql = "SELECT * FROM tag_ns WHERE tag_ns_name = '$tag_ns_name';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1)
    {
      pg_free_result($result);

      $sql = "INSERT INTO tag_ns (tag_ns_name) VALUES ('$tag_ns_name');";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    }else{
      pg_free_result($result);
      $text = _("Tag Namespace already exists. Tag Namespace Not created.");
      return ($text);
    }

    /* Make sure it was added */
    $sql = "SELECT * FROM tag_ns WHERE tag_ns_name = '$tag_ns_name';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1)
    {
      pg_free_result($result);
      $text = _("Failed to create tag.");
      return ($text);
    }
    pg_free_result($result);
    return (NULL);
  }

  /**
   * \brief Edit exsit Tag Namespace.
   */
  function EditTagNS()
  {
    global $PG_CONN;

    $tag_ns_pk = GetParm('tag_ns_pk', PARM_INTEGER);
    $tag_ns_name = GetParm('tag_ns_name', PARM_TEXT);

    /* Update the tag table */
    $Val = str_replace("'", "''", $tag_ns_name);
    $sql = "UPDATE tag_ns SET tag_ns_name = '$Val' WHERE tag_ns_pk = $tag_ns_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    pg_exec("COMMIT;");
    return (NULL);
  }

  /**
   * \brief Delete exsit Tag Namespace.
   */
  function DeleteTagNS()
  {
    global $PG_CONN;

    $tag_ns_pk = GetParm('tag_ns_pk', PARM_INTEGER);

    $sql = "SELECT * FROM tag_ns_group WHERE tag_ns_fk = $tag_ns_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) > 0)
    {
      pg_free_result($result);
      $text = _("As there are group permissions related to this tag namespace, if you want to delete this tag namespace you should first delete permissions about this tag namespace! ");
      return ($text);
    }
    pg_free_result($result);

    pg_exec("BEGIN;");
    $sql = "DELETE FROM tag_file USING tag WHERE tag_pk = tag_fk AND tag_ns_fk = $tag_ns_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    $sql = "DELETE FROM tag WHERE tag_ns_fk = $tag_ns_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    $sql = "DELETE FROM tag_ns WHERE tag_ns_pk = $tag_ns_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
    pg_exec("COMMIT;");

    return (NULL);
  }
  /**
   * \brief Show all tag namespace about
   */
  function ShowExistTagNS()
  {
    global $PG_CONN;
    $VE = "";
    $VE = _("<h3>Current Tag Namespace:</h3>\n");
    $sql = "SELECT * FROM tag_ns;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) > 0)
    {
      $VE .= "<table border=1>\n";
      $text = _("Tag Namespace");
      $VE .= "<tr><th>$text</th><th></th></tr>\n";
      while ($row = pg_fetch_assoc($result))
      {
        $VE .= "<tr><td align='center'>" . $row['tag_ns_name'] . "</td><td align='center'><a href=" . Traceback_uri() . "?mod=admin_tag_ns&action=edit&tag_ns_pk=" . $row['tag_ns_pk'] . "&tag_ns_name=" . $row['tag_ns_name'] . ">Edit</a>|<a href='" . Traceback_uri() . "?mod=admin_tag_ns&action=delete&tag_ns_pk=" . $row['tag_ns_pk'] . "'>Delete</a></td></tr>\n";
      }
      $VE .= "</table><p>\n";
    }
    pg_free_result($result);

    return $VE;
  }

  /**
   * \brief Display the create tag namespace page.
   */
  function ShowCreateTagNSPage()
  {
    $VC = "";
    $VC .= _("<h3>Create Tag Namespace:</h3>\n");

    $VC.= "<form name='form' method='POST' action='" . Traceback_uri() ."?mod=admin_tag_ns'>\n";

    $text = _("Tag Namespace Name");
    $VC .= "$text: <input type='text' name='tag_ns_name' /> ";
    $text = _("Create");
    $VC .= "<input type='hidden' name='action' value='add'/>\n";
    $VC .= "<input type='submit' value='$text'>\n";
    $VC .= "</form>\n";

    return $VC;
  }

  /**
   * \brief Display the edit tag namespace page.
   */
  function ShowEditTagNSPage()
  {
    $VEd = "";
    $text = _("Create New Tag Namespace");
    $VEd .= "<h4><a href='" . Traceback_uri() . "?mod=admin_tag_ns'>$text</a><h4>";

    $VEd .= _("<h3>Edit Tag Namespace:</h3>\n");
    $tag_ns_pk = GetParm("tag_ns_pk",PARM_INTEGER);
    $tag_ns_name = GetParm('tag_ns_name', PARM_TEXT);

    $VEd.= "<form name='form' method='POST' action='" . Traceback_uri() ."?mod=admin_tag_ns'>\n";
    $text = _("Tag Namespace Name");
    $VEd .= "$text: <input type='text' name='tag_ns_name' value=\"$tag_ns_name\"/> ";
    $text = _("Edit");
    $VEd .= "<input type='hidden' name='action' value='update'/>\n";
    $VEd .= "<input type='hidden' name='tag_ns_pk' value='$tag_ns_pk'/>\n";
    $VEd .= "<input type='submit' value='$text'>\n";
    $VEd .= "</form>\n";

    return $VEd;
  }

  /**
   * \brief Display the tag namespace page.
   */
  function ShowTagNSPage($action)
  {
    $V = "";

    $V .=  $this->ShowExistTagNS();
    if ($action == 'edit')
    {
      $V .= $this->ShowEditTagNSPage();
    } else {
      /* Show create tag page */
      $V .= $this->ShowCreateTagNSPage();
    }
    return($V);
  }

  /**
   * \brief This function is called when user output is
   * requested.  This function is responsible for content.
   * (OutputOpen and Output are separated so one plugin
   * can call another plugin's Output.)
   * This uses $OutputType.
   * The $ToStdout flag is "1" if output should go to stdout, and
   * 0 if it should be returned as a string.  (Strings may be parsed
   * and used by other plugins.)
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    $action = GetParm('action', PARM_TEXT);
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        if ($action == 'add')
        {
          $rc = $this->CreateTagNS();
          if (!empty($rc))
          {
            $text = _("Create Tag Namespace Failed");
            $V .= displayMessage("$text: $rc");
          } else {
            $text = _("Create Tag Namespace Successful!");
            $V .= displayMessage($text);
          }
        }
        if ($action == 'update')
        {
          $rc = $this->EditTagNS();
          if (!empty($rc))
          {
            $text = _("Edit Tag Namespace Failed");
            $V .= displayMessage("$text: $rc");
          }else{
            $text = _("Edit Tag Namespace Successful!");
            $V .= displayMessage($text);
          }
        }
        if ($action == 'delete')
        {
          $rc = $this->DeleteTagNS();
          if (!empty($rc))
          {
            $text = _("Delete Tag Namespace Failed");
            $V .= displayMessage("$text: $rc");
          }else{
            $text = _("Delete Tag Namespace Successful!");
            $V .= displayMessage($text);
          }
        }
        $V .= $this->ShowTagNSPage($action);
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
$NewPlugin = new admin_tag_ns;
$NewPlugin->Initialize();
?>
