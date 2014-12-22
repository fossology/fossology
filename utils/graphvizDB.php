<?php
/* 
 * Copyright (C) 2014, Siemens AG
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

$Schema = array();
include_once __DIR__.'/../src/www/ui/core-schema.dat';
echo "// dot -Tps outputOfThisFile -o output.ps \ngraph DB { \n graph [rankdir=\"LR\"];";
foreach($Schema['TABLE'] as $tablename=>$column){
  echo "\n$tablename [shape=record,style=rounded,label=\"$tablename";
  foreach($column as $columnname=>$cont){
    echo "|<$columnname>$columnname";
  }
  echo '"];';
}

// $Schema["CONSTRAINT"]["rf_fkfk"] = "ALTER TABLE \"license_map\" ADD CONSTRAINT \"rf_fkfk\" FOREIGN KEY (\"rf_fk\") REFERENCES \"license_ref\" (\"rf_pk\") ON UPDATE NO ACTION ON DELETE NO ACTION;";
foreach($Schema['CONSTRAINT'] as $query){
  $pattern = "/ALTER TABLE \"([_a-z]*)\" ADD CONSTRAINT \"[_a-z]*\" FOREIGN KEY \(\"([_a-z]*)\"\) REFERENCES \"([_a-z]*)\" \(\"([_a-z]*)\"\)/";
  $matches = array();
  preg_match($pattern, $query, $match);
    if (count($match) < 5)
    {
      continue;
    }
    echo "\n$match[1]:$match[2] -- $match[3]:$match[4];";
}

echo "\n}";