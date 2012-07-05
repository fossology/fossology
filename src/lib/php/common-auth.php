<?php
/***********************************************************
 Copyright (C) 2011-2012 Hewlett-Packard Development Company, L.P.

 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 ***********************************************************/

/**
 * \file common-auth.php
 * \brief This file contains common authentication funtion
 */


/**
 * \brief Check if SiteMinder is enabled.
 * \return -1 if not enabled, or the users SEA if enabled
 */
function siteminder_check() {
  if (isset($_SERVER['HTTP_SMUNIVERSALID'])){
    $SEA = $_SERVER['HTTP_SMUNIVERSALID'];
    return $SEA;
  }
  return(-1);
} // siteminder_check()
