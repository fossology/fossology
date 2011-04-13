<?php
/*
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
*/

/**
 * browsers
 * \brief definition of the browsers to use when testing.
 *
 * @version "$Id $"
 * 
 * Created on Oct 5, 2010
 */
  public static $browsers = array(
  array(
    'name' => "Firefox on randotest",
    'browser' => '*firefox /usr/local/firefox/firefox-bin',
    'host' => 'randotest.ostt',
    'port' => 4444,
    'timeout' => 50000)
  );
?>
