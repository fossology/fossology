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


/*
 * Future enhancement, mail to addresses can be passed in.  
 * The default is mary and markd?
 * 
 * For now just edit the string below.
 */

global $mailTo;

// default mailing list
$mailTo = "mark.donohoe@hp.com mary.laser@hp.com ";

$others = "bob.gobeille@hp.com dong.ma@hp.com alex.dav.norton@hp.com  yao-bin.shi@hp.com";

// the whole team, comment out as needed
$mailTo = $mailTo . $others;
?>