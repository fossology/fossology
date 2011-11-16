<?php
/***********************************************************
 Copyright (C) 2010-2011 Stefan Schroeder as a part of the FOSSOLOGY project.

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

$locale = "";

// We're interested only in language, not country.
// I do not bother to do a meticulous parsing of the
// accepted languages (RFC 2616), therefore we simply rip of the first
// two characters and hope that this is a known language
// according to ISO 639-1.  If not: Bad luck.
if (array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER))
{
  $browser_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
  $lang = substr($browser_lang,  0, 2);
}
else
  $lang = '';

// Set locale depending on language.
switch ($lang)
{
  // Add new languages below HERE!
  case ("de"): $locale = "de_DE"; break;
  // Add new languages above HERE!
  default: // Nothing to do for 'unknown locale'?
}

if (isSet($_GET["locale"])) $locale = $_GET["locale"];
putenv("LC_ALL=$locale");
setlocale(LC_ALL, $locale);
bindtextdomain("messages", "./locale");
textdomain("messages");
//print("Browser says: $browser_lang, Your language is $lang, your locale is $locale");
?>
