<?php

/*
Copyright (C) 2014, Siemens AG

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

class TestDbFactory
{

  public function setupTestDb($dbName=NULL)
  {
    date_default_timezone_set("UTC");
    if (!is_callable('pg_connect'))
    {
      throw new \Exception("php-psql not found");
    }
    $sub = chr(mt_rand(97, 122)) . chr(mt_rand(97, 122)) . chr(mt_rand(97, 122)) . chr(mt_rand(97, 122));
    if (!isset($dbName))
    {
      $dbName = "fosstestone";
    }
    $dbName = strtolower($dbName);
    $this->ensurePgPassFileEntry();

    $sys_conf = sys_get_temp_dir() . "/$dbName" . time() . $sub;
    if (!mkdir($sys_conf, $mode = 0755))
    {
      throw new \Exception("FATAL! Cannot create test repository at " . $sys_conf);
    }
    if (chmod($sys_conf, 0755) === FALSE)
    {
      echo "ERROR: Cannot set mode to 755 on " . $sys_conf . "\n" . __FILE__ . " at line " . __LINE__ . "\n";
    }
    $conf = "dbname=$dbName;\nhost=localhost;\nuser=fossy;\npassword=fossy;\n";
    if (file_put_contents($sys_conf . "/Db.conf", $conf) === FALSE)
    {
      throw new \Exception("FATAL! Could not create Db.conf file at " . $sys_conf);
    }

    exec($cmd = "psql -Ufossy -h localhost -lqtA | cut -f 1 -d '|' | grep -q '^$dbName\$'", $cmdOut, $cmdRtn);
    if ($cmdRtn == 0)
    {
      exec($cmd = "createlang -Ufossy -h localhost -l $dbName | grep -q plpgsql", $cmdOut, $cmdRtn);
      if ($cmdRtn != 0)
      {
        exec($cmd = "createlang -Ufossy -h localhost plpgsql $dbName", $cmdOut, $cmdRtn);
        if ($cmdRtn != 0)
          throw new \Exception("ERROR: failed to add plpgsql to $dbName database");
      }
    } else
    {
      $fosstestSql = file_get_contents(dirname(__FILE__) . '/../../lib/php/Test/fosstestinit.sql');
      $fossSql = str_replace('fosstest', $dbName, $fosstestSql);
      $pathSql = $sys_conf . '/dbinit.sql';
      file_put_contents($pathSql, $fossSql);
      exec($cmd = "psql -Ufossy -h localhost fossology < $pathSql", $cmdOut, $cmdRtn); //  2>&1
      if ($cmdRtn != 0)
      {
        throw new \Exception("ERROR: Database failed during configuration.");
      }
      unlink($pathSql);
    }

    return $sys_conf;
  }

  private function ensurePgPassFileEntry()
  {
    $userHome = getenv('HOME');
    $ipv4 = gethostbyname(gethostname());
    $fullHostName = gethostbyaddr(gethostbyname($ipv4));
    $contents = "$fullHostName:*:*:fossy:fossy\n";
    $pgpass = "$userHome/.pgpass";
    putenv("PGPASSFILE=$pgpass");
    $pg_pass_contents = file_exists($pgpass) ? file_get_contents($pgpass) : '';
    if (!preg_match('/\:fossy\:fossy/', $pg_pass_contents))
    {
      $pgpassHandle = fopen($pgpass, 'w');
      $howmany = fwrite($pgpassHandle, $contents);
      if ($howmany === FALSE)
      {
        throw new \Exception("FATAL! Could not write .pgpass file to $pgpassHandle");
      }
      fclose($pgpassHandle);
    }
    if (!chmod($pgpass, 0600))
    {
      echo "Warning! could not set $pgpass to 0600\n";
    }
  }

  public function purgeTestDb()
  {
    $sys_conf = getenv('SYSCONFDIR');
    $dbConfig = file_get_contents("$sys_conf/Db.conf");
    if (!preg_match("/dbname=([[:alnum:]]+);.*/", $dbConfig, $matches))
    {
      print "could not parse db name";
      exit(5);
    }
    $dbName = $matches[1];

    $existCmd = "psql -Ufossy -h localhost -l | grep -q " . $dbName;
    exec($existCmd, $existkOut, $existRtn);
    if ($existRtn != 0)
    {
      echo "NOTE: database " . $dbName . " does not exist, nothing to delete\n";
    } else
    {
      $dropCmd = "dropdb -Ufossy -h localhost " . $dbName;
      exec($dropCmd, $dropOut, $dropRtn);
      if ($dropRtn != 0)
      {
        echo("ERROR: failed to delete database " . $dbName);
      }
    }
    foreach (glob($sys_conf . "/*.*") as $filename)
    {
      unlink($filename);
    }
    rmdir($sys_conf);
  }
}