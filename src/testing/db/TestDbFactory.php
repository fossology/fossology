<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
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
    if (empty($dbName))
    {
      $dbName = "fosstestone";
    } else {
      if ($dbName === "fossology") {
        throw new \Exception("cannot use production database for tests");
      }
    }
    $dbName = strtolower($dbName);
    $this->ensurePgPassFileEntry();

    $sys_conf = sys_get_temp_dir() . "/$dbName" . time() . $sub;
    if (!is_dir($sys_conf)) {
        if (!mkdir($sys_conf, 0755, true) && !is_dir($sys_conf)) {
            throw new \Exception("FATAL! Cannot create test repository at " . $sys_conf);
        }
    }
    if (chmod($sys_conf, 0755) === FALSE)
    {
      throw new \Exception("ERROR: Cannot set mode to 755 on " . $sys_conf . "\n" . __FILE__ . " at line " . __LINE__ . "\n");
    }
    $conf = "dbname=$dbName;\nhost=localhost;\nuser=fossy;\npassword=fossy;\n";
    if (file_put_contents($sys_conf . "/Db.conf", $conf) === FALSE)
    {
      throw new \Exception("FATAL! Could not create Db.conf file at " . $sys_conf);
    }

    exec($cmd = "psql -Ufossy -h localhost -lqtA | cut -f 1 -d '|' | grep -q '^$dbName\$'", $cmdOut, $cmdRtn);
    if ($cmdRtn == 0)
    {
      exec($cmd = "echo 'SELECT * FROM pg_language;' | psql -Ufossy -h localhost -t $dbName | grep -q plpgsql", $cmdOut, $cmdRtn);
      if ($cmdRtn != 0)
      {
        exec($cmd = "echo 'CREATE LANGUAGE plpgsql;' | psql -Ufossy -h localhost $dbName", $cmdOut, $cmdRtn);
        if ($cmdRtn != 0)
          throw new \Exception("ERROR: failed to add plpgsql to $dbName database");
      }
      exec($cmd = "echo 'SELECT * FROM pg_extension;' | psql -Ufossy -h localhost -t $dbName | grep -q uuid-ossp", $cmdOut, $cmdRtn);
      if ($cmdRtn != 0)
      {
        exec($cmd = "echo 'CREATE EXTENSION \"uuid-ossp\";' | psql -Ufossy -h localhost $dbName", $cmdOut, $cmdRtn);
        if ($cmdRtn != 0)
          throw new \Exception("ERROR: failed to add 'uuid-ossp' to $dbName database");
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

    $contents = "localhost:*:*:fossy:fossy\n";
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
      throw new \Exception("Warning! could not set $pgpass to 0600\n");
    }
  }

  public function getDbName($sys_conf)
  {
    $dbConfig = file_get_contents("$sys_conf/Db.conf");
    if (!preg_match("/dbname=([[:alnum:]]+);.*/", $dbConfig, $matches))
    {
      throw new \Exception("could not parse db name");
    }
    return $matches[1];
  }

  public function purgeTestDb($sys_conf=null)
  {
    if (empty($sys_conf)) {
      $sys_conf = getenv('SYSCONFDIR');
    }
    if (empty($sys_conf)) {
      throw new \Exception( "refusing to purge from /");
    }

    $dbName = $this->getDbName($sys_conf);
    if (empty($dbName)) {
      throw new \Exception( "cannot determine db to empty");
    }

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
        throw new \Exception("failed to delete database " . $dbName);
      }
    }
    foreach (glob($sys_conf . "/*.*") as $filename)
    {
      unlink($filename);
    }
    exec("rm -rf $sys_conf");
  }
}
