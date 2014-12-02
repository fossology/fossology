<?php
/*
 Copyright (C) 2014, Siemens AG
 Author: Daniele Fognini, Johannes Najjar

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
 */

namespace Fossology\UI\Ajax;

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AjaxBulkHistory extends DefaultPlugin
{
  const NAME = "bulk-history";
  /** @var UploadDao */
  private $uploadDao;
  /** @var ClearingDao */
  private $clearingDao;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::PERMISSION => self::PERM_READ
    ));

    $this->uploadDao = $this->getObject('dao.upload');
    $this->clearingDao = $this->getObject('dao.clearing');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $uploadId = intval($request->get('upload'));
    if (empty($uploadId))
    {
      return;
    }
    $uploadTreeId = intval($request->get('item'));
    if (empty($uploadTreeId))
    {
      return;
    }
    $onlyTried = !$request->get('all');

    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    /** @var ItemTreeBounds */
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($uploadTreeId, $uploadTreeTableName);
    //TODO pass group
    $bulkHistory = $this->clearingDao->getBulkHistory($itemTreeBounds, $onlyTried);
    return $this->render("bulk-history.html.twig",$this->mergeWithDefault(array('bulkHistory'=>$bulkHistory)));
  }
}

register_plugin(new AjaxBulkHistory());