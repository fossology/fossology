<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.

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

define("TITLE_ui_download", _("Download File"));

/**
 * \class ui_download extends FO_Plugin
 * \brief downlad file(s)
 */
class ui_download extends FO_Plugin
{
  var $Name       = "download";
  var $Title      = TITLE_ui_download;
  var $Version    = "1.0";
  var $Dependency = array();
  var $DBaccess   = PLUGIN_DB_WRITE;
  var $NoHTML     = 1;

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $text = _("Download this file");
    menu_insert("Browse-Pfile::Download",0,$this->Name,$text);
  } // RegisterMenus()

  /**
   * \brief Called if there is no file.  User is queried if they want
   * to reunpack.
   */
  function CheckRestore($Item, $Filename)
  {
    global $Plugins;

    $this->NoHeader = 0;
    header('Content-type: text/html');
    header("Pragma: no-cache"); /* for IE cache control */
    header('Cache-Control: no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0'); /* prevent HTTP/1.1 caching */
    header('Expires: Expires: Thu, 19 Nov 1981 08:52:00 GMT'); /* mark it as expired (value from Apache default) */

    $V = "";
    if (($this->NoMenu == 0) && ($this->Name != "menus"))
    {
      $Menu = &$Plugins[plugin_find_id("menus")];
    }
    else { $Menu = NULL; }

    /* DOCTYPE is required for IE to use styles! (else: css menu breaks) */
    $V .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "xhtml1-frameset.dtd">' . "\n";

    $V .= "<html>\n";
    $V .= "<head>\n";
    $V .= "<meta name='description' content='The study of Open Source'>\n";
    if ($this->NoHeader == 0)
    {
      /** Known bug: DOCTYPE "should" be in the HEADER
       and the HEAD tags should come first.
       Also, IE will ignore <style>...</style> tags that are NOT
       in a <head>...</head>block.
       **/
      if (!empty($this->Title)) $V .= "<title>" . htmlentities($this->Title) . "</title>\n";
      $V .= "<link rel='stylesheet' href='css/fossology.css'>\n";
      if (!empty($Menu)) print $Menu->OutputCSS();
      $V .= "</head>\n";
      $V .= "<body class='text'>\n";
      print $V;
      if (!empty($Menu)) { $Menu->Output($this->Title); }
    }
     
    $P = &$Plugins[plugin_find_id("view")];
    $P->ShowView(NULL,"browse");
    exit;
  } // CheckRestore()


  /**
   * \brief This function is called when user output is
   * requested.  This function is responsible for content.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    global $Plugins;
    global $PG_CONN;

    if (!$PG_CONN)
    {
      DBconnect();
      if (!$PG_CONN)
      {
        $text = _("Missing database connection.");
        echo "<h2>$text</h2>";
        return;
      }
    }

    $Item = GetParm("item",PARM_INTEGER);

    $text = _("Invalid item parameter");
    if (empty($Item))
    {
      echo "<h2>$text</h2>";
      return;
    }

    $Filename = RepPathItem($Item);
    if (empty($Filename))
    {
      echo "<h2>$text: $Filename</h2>";
      return;
    }

    $Fin = @fopen( RepPathItem($Item) ,"rb");
    /* note that CheckRestore() does not return. */
    if (empty($Fin)) $this->CheckRestore($Item, $Filename);

    $sql = "SELECT ufile_name, upload_fk FROM uploadtree WHERE uploadtree_pk = $Item LIMIT 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    if (pg_num_rows($result) != 1)
    {
      $text = _("Missing item");
      echo "<h2>$text: $Item</h2>";
      pg_free_result($result);
      return;
    }
    $Upload = $row['upload_fk'];
    $UploadPerm = GetUploadPerm($Upload);
    if ($UploadPerm < PERM_WRITE)
    {
      $text = _("No Permission");
      echo "<h2>$text: $Item</h2>";
      return;
    }

    $Name = $row['ufile_name'];
    pg_free_result($result);

    if (($rv = DownloadFile($Filename, $Name)) !== True)
    {
      $text = _("Download failed");
      echo "<h2>$text</h2>$Filename<br>$rv";
    }
  } // Output()

};
$NewPlugin = new ui_download;
$NewPlugin->Initialize();
?>
