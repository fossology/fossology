<?php

require_once("$MODDIR/lib/php/common-cli.php");

cli_Init();

print '
{ "statements" : [
                 { "name": "Copyright Siemens", "text" : "we wrote this stuff", "files" : [ "/a.txt", "/b.txt" ]},
                 { "name": "Copyright 2001-2014", "text" : "they wrote this other", "files" : [ "/c.txt", "d/file.c" ]},
               ]
}';
