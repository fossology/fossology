<?php

require_once("$MODDIR/lib/php/common-cli.php");

cli_Init();

print '
{ "licenses" : [
                 { "name": "Apache-2.0", "text" : "licText", "files" : [ "/a.txt", "/b.txt" ]},
                 { "name": "Apache-1.0", "text" : "lic3Text", "files" : [ "/c.txt" ]},
               ]
}';
