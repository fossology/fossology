<?php
/***********************************************************
 Copyright (C) 2012-2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015 Siemens AG
  
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

use Fossology\Lib\Plugin\AgentPlugin;

class Adj2nestAgentPlugin extends AgentPlugin
{
  public function __construct() {
    $this->Name = "agent_adj2nest";
    $this->Title = 'adj2nest';
    $this->AgentName = "adj2nest";

    parent::__construct();
  }

  function AgentHasResults($uploadId=0)
  {
    $dbManager = $GLOBALS['container']->get('db.manager');
    /* see if the latest nomos and bucket agents have scaned this upload for this bucketpool */
    $uploadtreeRec = $dbManager->getSingleRow("SELECT * FROM uploadtree where upload_fk=$1 and lft is not null",
            array($uploadId),__METHOD__.'.lftNotSet');
    if (empty($uploadtreeRec))
    {
      return 0;
    }
    $sql = "SELECT count(*) cnt FROM uploadtree a, uploadtree b
            WHERE a.upload_fk=$1 AND b.upload_fk=$1
              AND a.parent=b.parent and a.lft<b.lft
              AND (a.ufile_name>b.ufile_name AND a.ufile_mode&(1<<29)=b.ufile_mode&(1<<29)
                  OR (a.ufile_mode&(1<<29)) < (b.ufile_mode&(1<<29)) )";
    $wrongOrdered = $dbManager->getSingleRow($sql,array($uploadId),__METHOD__.'.lftWrong');
    return $wrongOrdered['cnt'] ? 2 : 1;
  }
}

register_plugin(new Adj2nestAgentPlugin());
