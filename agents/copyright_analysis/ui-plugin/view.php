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

class copyright_view extends FO_Plugin
{
    var $Name       = "copyrightview";
    var $Title      = "View Copyright/Email/Url Analysis";
    var $Version    = "1.0";
    var $Dependency = array("db","browse","view");
    var $DBaccess   = PLUGIN_DB_READ;
    var $LoginFlag  = 0;
    var $NoMenu     = 0;

  /***********************************************************
   RegisterMenus(): Customize submenus.
  ***********************************************************/
    function RegisterMenus()
    {
        // For all other menus, permit coming back here.
        $URI = $this->Name . Traceback_parm_keep(array("show","format","page","upload","item"));
        $Item = GetParm("item",PARM_INTEGER);
        $Upload = GetParm("upload",PARM_INTEGER);
        if (!empty($Item) && !empty($Upload))
        {
            if (GetParm("mod",PARM_STRING) == $this->Name)
            {
                menu_insert("View::View Copyright/Email/Url",1);
                menu_insert("View-Meta::View Copyright/Email/Url",1);
            }
            else
            {
                menu_insert("View::View Copyright/Email/Url",1,$URI,"View Copyright/Email/Url info");
                menu_insert("View-Meta::View Copyright/Email/Url",1,$URI,"View Copyright/Email/Url info");
            }
        }
        $Lic = GetParm("lic",PARM_INTEGER);
        if (!empty($Lic)) { $this->NoMenu = 1; }
    } // RegisterMenus()

  /***********************************************************
   Output(): This function is called when user output is
   requested.  This function is responsible for content.
   The $ToStdout flag is "1" if output should go to stdout, and
   0 if it should be returned as a string.  (Strings may be parsed
   and used by other plugins.)
  ***********************************************************/
    function Output()
    {
        global $PG_CONN;
        if ($this->State != PLUGIN_STATE_READY) { return; }
            $V="";
        global $Plugins;
        global $DB;
        $View = &$Plugins[plugin_find_id("view")];
        $Item = GetParm("item",PARM_INTEGER);
        $Upload = GetParm("upload", PARM_INTEGER);

        $ModBack = GetParm("modback",PARM_STRING);
        if (empty($ModBack)) { $ModBack='copyrighthist'; }

        $pfile = 0;

        $sql = "SELECT * FROM uploadtree WHERE uploadtree_pk = ".$Item.";";
        $result = $DB->Action($sql);
        if ($result && !empty($result[0]['pfile_fk'])) {
            $pfile = $result[0]['pfile_fk'];
        } else {
            print "Could not locate the corresponding pfile.";
        }

        $sql = "SELECT * FROM copyright WHERE copy_startbyte IS NOT NULL
            and pfile_fk=".$pfile.";";
        $result = $DB->Action($sql);
        $colors = Array();
        $colors['statement'] = 0;
        $colors['email'] = 1;
        $colors['url'] = 2;
        if ($result && !empty($result[0]['copy_startbyte'])) {
            foreach ($result as $row) {
                $View->AddHighlight($row['copy_startbyte'], $row['copy_endbyte'], $colors[$row['type']], '', $row['content'],-1, 
                    Traceback_uri()."?mod=copyrightlist&agent=".$row['agent_fk']."&item=$Item&hash=" . $row['hash'] . "&type=" . $row['type']);
            }
        
            $View->SortHighlightMenu();
        }
        
        $View->ShowView(NULL,$ModBack, 1,1,NULL,True);
        return;
    } // Output()

};
$NewPlugin = new copyright_view;
$NewPlugin->Initialize();
?>
