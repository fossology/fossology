<?php
/***********************************************************
 index.php
 Copyright (C) 2007 Hewlett-Packard Development Company, L.P.

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

    // There's some fairly tricky logic here to help usability.
    //
    // If the URL has GET parameters, we don't want them to stay
    // visible in the browser because they won't track as the user
    // browses around.  If after they've browsed around for a while,
    // we don't want a reload to go back to the original URL either.
    // An ideal solution would also be safe against race conditions
    // between multiple tabs/windows
    //
    // If the URL has GET parameters, we store them in a cookie unique
    // to this window/tab ($fid), and "reload" this page by using an
    // auto-submitting form with the $fid hidden within it.
    // If such a form is detected, the parameters are collected from
    // the cookie and processed normally, and the cookie is essentially
    // blanked, so that a reload, which will reload the form/POST, will
    // just reload the top page and not  (???? just use a cookie all the time)

    $args = $_SERVER['QUERY_STRING'];

    // only needs to be relatively unique
    $fid = (time() ^ posix_getpid()) & 0xffff;

    if (!empty($args)) {
	setcookie('args-' . $fid, $args);
	$myname = basename($_SERVER['SCRIPT_NAME']);
	echo "<body onload='javascript:document.tmpform.submit();'>
	<form method=post name=tmpform action=$myname>
	    <input type=hidden name=fid value=$fid>
	</form>
	</body>";
	exit();
    }

    if (!empty($_POST['fid'])) {
        $fid = $_POST['fid'];
	$args = $_COOKIE['args-' . $fid];
	setcookie('args-' . $fid, '');
    }

    if (!empty($args)) $args = "?$args";

    if (empty($args)) $args = "?fid=$fid"; else $args .= "&fid=$fid";

    echo "
    <title>FOSSology Repo Viewer</title>
    <frameset rows='106,*' border=0>
	<frame name=topframe src='topframe.php'>
	<frameset cols='25%,*' border=5 onResize='if (navigator.family == \"nn4\") window.location.reload()'>
	<frame name=treeframe src='leftnav.php$args'>
	<frame name=basefrm src='rightframe.php$args'>
    <noframes>
      <h1> Your browser does not appear to support frames </h1>
    </noframes>
	</frameset>
    </frameset>
    ";
?>
