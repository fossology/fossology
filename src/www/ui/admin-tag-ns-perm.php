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

define("TITLE_admin_tag_ns_perm", _("Assign Tag Namespace Permission"));

class admin_tag_ns_perm extends FO_Plugin
{
  var $Name       = "admin_tag_ns_perm";
  var $Title      = TITLE_admin_tag_ns_perm;
  var $MenuList = "Admin::Tag::Assign TagNS Permission";
  var $Version = "1.3";
  var $Dependency = array();
  var $DBaccess = PLUGIN_DB_USERADMIN;

  /**
   * \brief Delete exsit Tag Namespace Permission.
   */
  function DeleteTagPerms()
  {
    global $PG_CONN;

    $tag_ns_group_pk = GetParm('tag_ns_group_pk', PARM_INTEGER);
    if (empty($tag_ns_group_pk))
    {
      $text = _("Can't find tag_ns_group_pk!");
      return ($text);
    }

    $sql = "DELETE FROM tag_ns_group WHERE tag_ns_group_pk = $tag_ns_group_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
    return (NULL);
  }

  /**
   * \brief Add Tag Namespace Permission.
   */
  function AddTagPerms()
  {
    global $PG_CONN;

    $tag_ns_fk = GetParm('tag_ns_fk', PARM_INTEGER);
    $group_fk = GetParm('group_fk', PARM_INTEGER);
    $tag_ns_perm = GetParm('tag_ns_perm', PARM_INTEGER);

    $sql = "INSERT INTO tag_ns_group (tag_ns_fk,group_fk,tag_ns_perm) VALUES ($tag_ns_fk,$group_fk,$tag_ns_perm);";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
    return (NULL);
  }



  /**
   * \brief Display the ajax page.
   */
  function ShowAjaxPage()
  {
    $VA = "";
    $VA .= ActiveHTTPscript("Perms");
    $VA.= "\n<script language='javascript'>\n";
    $VA .= "function Perms_Reply()\n";
    $VA .= "{\n";
    $VA .= "  if ((Perms.readyState==4) && (Perms.status==200))\n";
    $VA .= "  {\n";
    $VA .= "    document.getElementById('perms').innerHTML = Perms.responseText;\n";
    $VA .= "  }\n";
    $VA .= "}\n;";
    $VA .= "</script>\n";

    return $VA;
  }

  /**
   * \brief Display the page.
   */
  function ShowAssignPermPage()
  {
    global $PG_CONN;
    $V = "";

    $tag_ns_pk = GetParm("tag_ns_pk",PARM_INTEGER);

    /* Get the list of tag namespace */
    $sql = "SELECT * FROM tag_ns;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    $V.= "<h4>\n";
    $V.= _("Select the tag namespace to assign permission: ");
    $V.= "</h4>\n";
    $V.= "<select name='tag_ns_pk' onclick='Perms_Get(\"" . Traceback_uri() . "?mod=perm_get&tag_ns_pk=\"+this.value)' onchange='Perms_Get(\"" . Traceback_uri() . "?mod=perm_get&tag_ns_pk=\"+this.value)'>\n";
    while($row = pg_fetch_assoc($result)){
      $Selected = "";
      if ($tag_ns_pk == $row['tag_ns_pk']) {
        $Selected = "selected";
      }
      $V.= "<option $Selected value='" . $row['tag_ns_pk'] . "'>";
      $V.= htmlentities($row['tag_ns_name']);
      $V.= "</option>\n";
    }
    pg_free_result($result);
    $V.= "</select>\n";
    $V.= "<div id='perms'></div>\n";

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
        if ($action == 'delete')
        {
          $rc = $this->DeleteTagPerms();
          if (!empty($rc))
          {
            $text = _("Delete Tag Namespace Permission Failed");
            $V .= displayMessage("$text: $rc");
          } else {
            $text = _("Delete Tag Namespace Permission Successful!");
            $V .= displayMessage($text);
          }
        }
        if ($action == 'add')
        {
          $rc = $this->AddTagPerms();
          if (!empty($rc))
          {
            $text = _("Add Tag Namespace Permission Failed");
            $V .= displayMessage("$text: $rc");
          } else {
            $text = _("Add Tag Namespace Permission Successful!");
            $V .= displayMessage($text);
          }
        }
        $V .= $this->ShowAjaxPage();
        $V .= $this->ShowAssignPermPage();
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
$NewPlugin = new admin_tag_ns_perm;
$NewPlugin->Initialize();
?>
