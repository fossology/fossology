<?php
/*
Copyright (C) 2014, Siemens AG
Author: Johannes Najjar

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

namespace Fossology\Lib\Data;


class ClearingDecisionData {


  /**
   * @var bool
   */
  protected $sameUpload;

  /**
   * @var bool
   */
  protected $sameFolder;

  /**
   * @var LicenseRef[]
   */
  protected $licenses;

  /**
   * @var int
   */
  protected $clearingId;

  /**
   * @var int
   */
  protected $uploadTreeId;

  /**
   * @var int
   */
  protected $pfileId;
  /**
   * @var string
   */
  protected $userName;
  /**
   * @var int
   */
  protected $userId;

  /**
   * @var string
   */
  protected $type;

  /**
   * @var string
   */
  protected $comment;

  /**
   * @var string
   */
  protected $reportinfo;

  /**
   * @var string
   */
  protected $scope;

  /**
   * @var DateTime
   */
  protected $date_added;
} 