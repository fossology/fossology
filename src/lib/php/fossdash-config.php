<?php
/*
 SPDX-FileCopyrightText: © Darshan Kansagara <kansagara.darshan97@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
 Author: Darshan Kansagara <kansagara.darshan97@gmail.com>
*/

/**
 * \file
 * \brief fossdash configuration functions.
 */

/**
 * \brief Initialize the fossdash configuration after bootstrap().
 *
 * This function also opens a database connection (global PG_CONN).
 *
 * fossdash configuration variables are in below place:
 *  - Database fossdashconfig table
 *
 *
 * \param string $sysconfdir   Path to SYSCONFDIR
 * \param[out] array &$SysConf Configuration variable array (updated by this function)
 *
 * If the fossdashconfig table doesn't exist then create it.
 * Write records for the core variables into fossdashconfig table.
 *
 */
function FossdashConfigInit($sysconfdir, &$SysConf)
{
  global $PG_CONN;

  /*
   * Connect to the database.  If the connection fails,
   * DBconnect() will print a failure message and exit.
   */
  $PG_CONN = DBconnect($sysconfdir);

  global $container;
  $postgresDriver = new \Fossology\Lib\Db\Driver\Postgres($PG_CONN);
  $container->get('db.manager')->setDriver($postgresDriver);

  /**************** read/create/populate the fossdashconfig table *********/
  /* create if fossdashconfig table if it doesn't exist */
  $newTable  = Create_fossdashconfig();

  /* populate it with core variables */
  Populate_fossdashconfig();

  /* populate the global $SysConf array with variable/value pairs */
  $sql = "SELECT variablename, conf_value FROM fossdashconfig;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);

  while ($row = pg_fetch_assoc($result)) {
    $SysConf['FOSSDASHCONFIG'][$row['variablename']] = $row['conf_value'];
  }
  pg_free_result($result);

  return;
}


/**
 * \brief Create the fossdashconfig table.
 *
 * \return 0 if table already exists.
 * 1 if it was created
 */
function Create_fossdashconfig()
{
  global $PG_CONN;

  /* If fossdashconfig exists, then we are done */
  $sql = "SELECT typlen  FROM pg_type WHERE typname='fossdashconfig' limit 1;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $numrows = pg_num_rows($result);
  pg_free_result($result);
  if ($numrows > 0) {
    return 0;
  }

  /* Create the fossdashconfig table */
  $sql = "
CREATE TABLE fossdashconfig (
    fossdashconfig_pk serial NOT NULL PRIMARY KEY,
    variablename character varying(30) NOT NULL UNIQUE,
    conf_value text,
    ui_label character varying(60) NOT NULL,
    vartype int NOT NULL,
    group_name character varying(20) NOT NULL,
    group_order int,
    description text NOT NULL,
    validation_function character varying(40) DEFAULT NULL,
    option_value character varying(40) DEFAULT NULL
);
";

  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  /* Document columns */
  $sql = "
COMMENT ON TABLE fossdashconfig IS 'System configuration values';
COMMENT ON COLUMN fossdashconfig.variablename IS 'Name of configuration variable';
COMMENT ON COLUMN fossdashconfig.conf_value IS 'value of config variable';
COMMENT ON COLUMN fossdashconfig.ui_label IS 'Label that appears on user interface to prompt for variable';
COMMENT ON COLUMN fossdashconfig.group_name IS 'Name of this variables group in the user interface';
COMMENT ON COLUMN fossdashconfig.group_order IS 'The order this variable appears in the user interface group';
COMMENT ON COLUMN fossdashconfig.description IS 'Description of variable to document how/where the variable value is used.';
COMMENT ON COLUMN fossdashconfig.validation_function IS 'Name of function to validate input. Not currently implemented.';
COMMENT ON COLUMN fossdashconfig.vartype IS 'variable type.  1=int, 2=text, 3=textarea, 4=password, 5=dropdown';
COMMENT ON COLUMN fossdashconfig.option_value IS 'If vartype is 5, provide options in format op1{val1}|op2{val2}|...';
    ";
  /* this is a non critical update */
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);
  return 1;
}


/**
 * \brief Populate the fossdashconfig table with core variables.
 */
function Populate_fossdashconfig()
{
  global $PG_CONN;

  $columns = array("variablename", "conf_value", "ui_label", "vartype", "group_name",
    "group_order", "description", "validation_function", "option_value");
  $valueArray = array();

  $variable = "FossdashEnableDisable";
  $FossdashEnableDisablePrompt = _('Enable/Disable Fossdash');
  $FossdashEnableDisableDesc = _('Start(Enable) or stop(Disable) the Fossdash');
  $valueArray[$variable] = array("'$variable'", "'0'", "'$FossdashEnableDisablePrompt'",
    strval(CONFIG_TYPE_DROP), "'FossDashAPI'", "1", "'$FossdashEnableDisableDesc'", "null", "'Disable{0}|Enable{1}'");

  $variable = "FossDashReportingAPIUrl";
  $fossdashApiUrlPrompt = _('FossDash Endpoint URL');
  $URLValid = "check_fossdash_url";
  $fossdashApiUrlDesc = _('Set the FossDash service endpoint. Disabled if empty.
                           <br>e.g. for Source Code : <i>"http://localhost:8086/write?db=fossology_db"</i> OR for Docker Setup : <i>"http://influxdb:8086/write?db=fossology_db"</i>.');
  $valueArray[$variable] = array("'$variable'", "null", "'$fossdashApiUrlPrompt'",
    strval(CONFIG_TYPE_TEXT), "'FossDashAPI'", "2", "'$fossdashApiUrlDesc'", "'$URLValid'", "null");

  $variable = "FossdashMetricConfig";
  $FossdashMetricConfigPrompt = _('Fossdash metric-reporting config');
  $FossdashMetricConfigValid = "check_fossdash_config";
  $FossdashMetricConfigDesc = _('Modify the fossdash reporting metrics config. Leave empty to use default one.
                                <br>e.g. Reporting config file <a target="_blank" href="https://github.com/darshank15/GSoC_2020_FOSSOlogy/wiki/Configuration-for-Fossdash-metric-reporting">Here</a>.
                                <br>To add new query_metric : 1.Add query_metric name in <b>QUERIES_NAME</b> list. 2.Add same query_metric name and its corresponding DB_query under the <b>QUERY</b>');
  $valueArray[$variable] = array("'$variable'", "null", "'$FossdashMetricConfigPrompt'",
    strval(CONFIG_TYPE_TEXTAREA), "'FossDashAPI'", "3", "'$FossdashMetricConfigDesc'", "'$FossdashMetricConfigValid'", "null");

  $variable = "FossDashScriptCronSchedule";
  $FossDashScriptCronSchedulePromt = _('cron job to run script');
  $cronIntervalCheck= "check_cron_job_inteval";
  $FossDashScriptCronScheduleDesc = _('Set the cron job of publishing script file for pushing data to time series db.');
  $valueArray[$variable] = array("'$variable'", "'* * * * *'", "'$FossDashScriptCronSchedulePromt'",
    strval(CONFIG_TYPE_TEXT), "'FossDashAPI'", "4", "'$FossDashScriptCronScheduleDesc'", "'$cronIntervalCheck'", "null");

  $variable = "FossologyInstanceName";
  $FossologyInstanceNamePrompt = _('Fosslogy instance name');
  $instanceNameValid = "check_fossology_instance_name";
  $FossologyInstanceNameDesc = _('Set the fossology instance name, leave empty to use autogenerated UUID value.
                                  <br>e.g. Instance name formate = <b>[a-zA-Z0-9_-]+ </b>.');
  $valueArray[$variable] = array("'$variable'", "null", "'$FossologyInstanceNamePrompt'",
    strval(CONFIG_TYPE_TEXT), "'FossDashAPI'", "5", "'$FossologyInstanceNameDesc'", "'$instanceNameValid'", "null");

  $variable = "FossdashReportedCleaning";
  $FossdashReportingCleaningPrompt = _('Fossdash reported files cleaning');
  $FossdashReportingCleaningValid = "check_fossdash_cleaning";
  $FossdashReportingCleaningDesc = _('number of days for which the successfully pushed metrics are archived. Older data will be deleted. Leave empty to disable cleanup');
  $valueArray[$variable] = array("'$variable'", "null", "'$FossdashReportingCleaningPrompt'",
    strval(CONFIG_TYPE_TEXT), "'FossDashAPI'", "6", "'$FossdashReportingCleaningDesc'", "'$FossdashReportingCleaningValid'", "null");

  $variable = "AuthType";
  $AuthTypePrompt = _('Auth_type for InfluxDB');
  $AuthTypeDesc = _('Select authentication type for an InfluxDB');
  $valueArray[$variable] = array("'$variable'", "'0'", "'$AuthTypePrompt'",
    strval(CONFIG_TYPE_DROP), "'FossDashAPI'", "7", "'$AuthTypeDesc'", "null", "'Token_based{0}|Uname_pass{1}'");

  $variable = "InfluxDBUser";
  $InfluxDBUserPrompt = _('InlfuxDB User');
  $InfluxDBUserValid = "check_username";
  $InfluxDBUserDesc = _('Set the username for InfluxDB.');
  $valueArray[$variable] = array("'$variable'", "null", "'$InfluxDBUserPrompt'",
    strval(CONFIG_TYPE_TEXT), "'FossDashAPI'", "8", "'$InfluxDBUserDesc'", "'$InfluxDBUserValid'", "null");

  $variable = "InfluxDBUserPassword";
  $InfluxDBUserPasswordPrompt = _('InlfuxDB Password');
  $InfluxDBUserPasswordValid = "check_password";
  $InfluxDBUserPasswordDesc = _('Set the password for Influx user. Password must atleast of lenght=3');
  $valueArray[$variable] = array("'$variable'", "null", "'$InfluxDBUserPasswordPrompt'",
    strval(CONFIG_TYPE_PASSWORD), "'FossDashAPI'", "9", "'$InfluxDBUserPasswordDesc'", "'$InfluxDBUserPasswordValid'", "null");

  $variable = "InfluxDBToken";
  $InfluxDBTokenPrompt = _('InlfuxDB Encoded Token');
  $InfluxDBTokenDesc = _('Please Enter encoded token for InfluxDB Authentication.
                          <br>Check out the steps for <a target="_blank" href="https://github.com/darshank15/GSoC_2020_FOSSOlogy/wiki/Steps-to-generate-InfluxDB-token">Token Generation</a>.');
  $valueArray[$variable] = array("'$variable'", "null", "'$InfluxDBTokenPrompt'",
    strval(CONFIG_TYPE_TEXTAREA), "'FossDashAPI'", "10", "'$InfluxDBTokenDesc'", "null", "null");

  /* Doing all the rows as a single insert will fail if any row is a dupe.
   So insert each one individually so that new variables get added.
  */
  foreach ($valueArray as $variable => $values) {
    /*
     * Check if the variable already exists. Insert it if it does not.
     * This is better than an insert ignoring duplicates, because that
     * generates a postresql log message.
     */
    $VarRec = GetSingleRec("fossdashconfig", "WHERE variablename='$variable'");
    if (empty($VarRec)) {
      $sql = "INSERT INTO fossdashconfig (" . implode(",", $columns) . ") VALUES (" .
        implode(",", $values) . ");";
    } else { // Values exist, update them
      $updateString = [];
      foreach ($columns as $index => $column) {
        if ($index != 0 && $index != 1) { // Skip variablename and conf_value
          $updateString[] = $column . "=" . $values[$index];
        }
      }
      $sql = "UPDATE fossdashconfig SET " . implode(",", $updateString) .
        " WHERE variablename='$variable';";
    }
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
    unset($VarRec);
  }

}


/**
 * \brief Check if the fossdash url is valid
 * \param string $url The url which will be checked
 * \return 1: the url is valid, 0: invalid
 */
function check_fossdash_url($url)
{
  if (filter_var($url, FILTER_VALIDATE_URL) && preg_match("#^((http)|(https)|(ftp)|(www)|(localhost))://(.*)#", $url) == 1) {
    return 1;
  } else {
    return 0;
  }
}

/**
 * \brief Check if the cron job schedule interval is valid
 * \param string $cron_interval cron job interval
 * \return 1: yes , 0: no
 */
function check_cron_job_inteval($cron_interval)
{
  $cron_regex = "#^((@(annually|yearly|monthly|weekly|daily|hourly|reboot))|(@every (\d+(ns|us|µs|ms|s|m|h))+)|((((\d+,)+\d+|(\d+(\/|-)\d+)|\d+|\*|\*\/\d+) ?){5}))$#";
  return preg_match($cron_regex, $cron_interval);
}


/**
 * \brief Check if the fossology instance name is valid
 * \param string $instance_name fossology instance name
 * \return 1: yes , 0: no
 */
function check_fossology_instance_name($instance_name)
{
  $instance_UUID_regex = "#^([a-zA-Z0-9_-]+)$#";
  return preg_match($instance_UUID_regex, $instance_name);
}

/**
 * \brief Check if cleaning_days is valid or not
 * \param string $cleaning_days Number of days after which successfully pushed metrics are cleaned up
 * \return 1: yes , 0: no
 */
function check_fossdash_cleaning($cleaning_days)
{
  $numeric_day_regex = "#^[0-9]*$#";
  return preg_match($numeric_day_regex, $cleaning_days);
}

/**
 * \brief Check if given uname is valid or not
 * \param string $uname username for influxDB
 * \return 1: yes , 0: no
 */
function check_username($uname)
{
  $uname_regex = "#^[A-Za-z0-9]+(?:[ _-][A-Za-z0-9]+)*$#";
  return preg_match($uname_regex, $uname);
}

/**
 * \brief Check if given password is valid or not
 * \param string $uname password for influxDB
 * \return 1: yes , 0: no
 */
function check_password($password)
{
  $password_regex = "#^(?=.*[A-Za-z])[A-Za-z\d]{3,}$#";
  return preg_match($password_regex, $password);
}

/**
 * \brief Check if given config string does not contains any DB update or drop related commands
 * \param string $config_str config for fossdash metrics
 * \return 1: yes , 0: no
 */
function check_fossdash_config($config_str)
{
  $lower_config_str = strtolower($config_str);
  $db_update_command_list = array("drop", "insert", "update", "alter", "truncate", "delete");
  foreach ($db_update_command_list as $cmd) {
    if (strpos($lower_config_str,$cmd) !== false) {
      return 0;
    }
  }
  return 1;
}
