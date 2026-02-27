<?php
/*
 SPDX-FileCopyrightText: © 2010 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
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

$mailTo .= $others;