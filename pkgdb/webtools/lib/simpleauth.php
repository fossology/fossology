<?php

// simpleauth()
//   Perform user authentication and authorization
//
// returns an assoc array of user authorization data:
//    name   {name}
//    email  {email addr}
// assumes a db connection
function simpleauth($sess_id, $resource_name)
{
    global $sess_max_minutes;
    global $ssl_port, $login_url;
    global $mysql_error_code;

    $ipaddr = $_SERVER['REMOTE_ADDR'];

    // see if the user is already authenticated
    $sql = "select sess_login from spas_sessions where sess_appid='$sess_id' 
            and sess_ip='$ipaddr' 
            and DATE_ADD(sess_logintime, INTERVAL $sess_max_minutes MINUTE)>=now()";
    $result= mysql_query($sql) 
              or die("simpleauth ($sql)".mysql_error());
    if ($result and (mysql_num_rows($result) > 0))
    {
        // user is already authenticated, so get authorization
        $sess_login = mysql_result($result, 0);
        $userdata = get_authorization($sess_login, $resource_name);
        
        mysql_free_result($result);
        return $userdata;
    }

    // user needs to authenticate
    // save the application session data and redirect to login server
    $protocol =  ($_SERVER["SERVER_PORT"] == $ssl_port)? "https":"http";
    $rtnurl = $protocol . "://" . $_SERVER["HTTP_HOST"] 
               . $_SERVER["REQUEST_URI"];
//               . dirname($_SERVER["REQUEST_URI"]);
    $sql = "insert into spas_sessions (sess_appid, sess_loginrtnurl)
            values (\"$sess_id\", \"$rtnurl\")";
    $result= mysql_query($sql);

    // ignore dup entries that can be caused by retrying failed logins
    if ((!$result) && (mysql_errno() != $mysql_error_code["ER_DUP_ENTRY"]))
              die("simpleauth error ($sql): ".mysql_error());
    header("Location: $login_url?sess=$sess_id");
}

function  get_authorization($sess_login, $resource_name)
{
   // stub
   $userdata = array("uid"=>$sess_login);
   return $userdata;
}


function logout()
{
}


// garbage collection on spas_sessions table
function session_garbage_collection()
{
    global $sess_max_minutes;

    $sql = "delete from spas_sessions where
            DATE_ADD(sess_logintime, INTERVAL $sess_max_minutes MINUTE) < now()";
    $success = mysql_query($sql)
                   or die("session_garbage_collection sql error($sql): ".mysql_error());
}


// ldap authentication of  uid/passwd
// This is only used by the login script
// returns assoc array of user data from ldap
//    if user is authenticated, 
//      userarray[uid] = {hp SEA, eg. john.doe@hp.com}
//    if error
//      userarray[err] = {error message}
function ldap_auth($uid, $passwd)
{
    global $testing;
    global $ldap_server, $ldap_port, $ldap_secureserver, $ldap_secureport;

    $userdata = array();

    // for testing
    if ($testing) 
    {
        $userdata["uid"]= $uid;
        return $userdata;
    }


    # connect to the server
    $link = ldap_connect($ldap_server) or die("unable to connect to LDAP server $ldap_server<p>");

    # bind the link
    ldap_bind($link);

    # perform the query
    // $all can be set to grab all the ED fields for this person
    if (!isset($all)) {
        $attributes = array('rfc822mailbox','department','givenname','sn','st','telephonenumber','co');
        $result = ldap_search($link, 'o=hp.com', "uid=$uid", $attributes);
    } else {
        $result = ldap_search($link, 'o=hp.com', "uid=$uid");
    }

    # test result of query
    if ($result == 0) 
    {
       $userdata["err"] = "Unknown user";
       return $userdata;
    }

    # get the query as an array
    $array = ldap_get_entries($link, $result);
    $dn = $array[0]['dn'];
    
    if ($array['count'] == 0)
    {
       $userdata["err"] = "User $uid not found. ";
       return $userdata;
    }

    # connect to the server
    $link = ldap_connect($ldap_secureserver) or die("unable to connect to LDAP server $ldap_secureserver<p>");
    
    // bind with a good dn and badd passwd will result in a warning from ldap_bind
    // this is to silence that warning
    $disp_err = ini_get('display_errors');
    ini_set('display_errors', false);

    if (ldap_bind($link, "$dn", "$passwd"))
    {
       //$username = $array[0]['cn']; no - see authtest.php
       //$userid   = $array[0]['uid'];
       $userdata["uid"] = $uid;
    }

    ini_set('display_errors', $disp_err);

    ldap_close($link);
    return $userdata;
}

// uid is sea, e.g. mary.doe.hp.com
// attriblist is the array of attributes you want returned (in an assoc array)
// For example:
//     array( "co", "c", "uid", "cn", "hpbusinessregion")
//
// You can also overload $attriblist by setting it to a single value:
//    value    action
//    -------  ----------------------------
//    all      return all values 
// The only choice for "action is null or "print" (which will print the record)

function ldap_query($uid, $attriblist, $action="")
{
    global $ldap_server, $ldap_port, $ldap_secureserver, $ldap_secureport;

    $userdata = array();

    # connect to the server
    $link = ldap_connect($ldap_server) or die("unable to connect to LDAP server $ldap_server<p>");

    # bind the link
    ldap_bind($link);

    # perform the query
    // $all can be set to grab all the ED fields for this person
    if ($attriblist == "all") 
    {
        $result = ldap_search($link, 'o=hp.com', "uid=$uid");
    }
    else
    {
        $result = ldap_search($link, 'o=hp.com', "uid=$uid", $attriblist);
    } 

    # test result of query
    if ($result == 0) 
    {
       $userdata["err"] = "Unknown user";
       return $userdata;
    }

    # get the query as an array
    $userdata = ldap_get_entries($link, $result);
    
    if ($userdata['count'] == 0)
    {
       $userdata["err"] = "User $uid not found. ";
       return $userdata;
    }

    if ($action == "print")
    {
        echo "<hr>";
        print "count: $userdata[count]<p>";
        echo "<br><pre>";
        for ($i=0; $i<$userdata["count"]; $i++)
	{
            print_r ($userdata[$i]);
	}
        echo "</pre><hr>";
    }

    ldap_close($link);
    return $userdata;
}


// return the current session record from spas_sessions
function get_spas_session()
{
    $sess_id = session_id();
    // see if the user is already authenticated
    $sql = "select * from spas_sessions where sess_appid='$sess_id'";
    $result = mysql_query($sql) or
                  die("get_spas_session() mysql error ($sql): ".mysql_error());

    $row = mysql_fetch_assoc($result);
    return($row);
}
?>
