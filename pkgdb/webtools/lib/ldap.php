<?php

/**
 * A general ldap query function
 * $attriblist and $action are the same as in ldap_querysea()
 * Filter:
 *    <filter> ::= ’(’ <filtercomp> ’)’
 *    <filtercomp> ::= <and> | <or> | <not> | <simple>
 *    <and> ::= ’&’ <filterlist>
 *    <or> ::= ’|’ <filterlist>
 *    <not> ::= ’!’ <filter>
 *    <filterlist> ::= <filter> | <filter> <filterlist>
 *    <simple> ::= <attributetype> <filtertype> <attributevalue>
 *    <filtertype> ::= ’=’ | ’~=’ | ’<=’ | ’>=’
 * Here are some example filter strings:
 *    "uid=john.doe@hp.com"
 *    "mail=john_doe@hp.com"
 *    "(& (ou=TSG ESS Linux&OpenSource Lab) (manager=*madden*))"
 * Retrieve data
 *    $name = $userdata[0]["cn"][0];  get first cn from first record returned
 * uid is sea, e.g. mary.doe.hp.com
 * attriblist is the array of attributes you want returned (in an assoc array)
 * For example:
 *     array( "co", "c", "uid", "cn", "hpbusinessregion")
 *
 * You can also overload $attriblist by setting it to a single value:
 *    value    action
 *    -------  ----------------------------
 *    all      return all values 
 * The only choice for "action is null or "print" (which will print the record)
 */
function ldap_query($ldap_server, $filter, $attriblist, $action="")
{
    $userdata = array();

    # connect to the server
    $link = ldap_connect($ldap_server) or die("unable to connect to LDAP server $ldap_server<p>");

    # bind the link
    ldap_bind($link);

    # perform the query
    // $all can be set to grab all the ED fields for this person
    if ($attriblist == "all") 
    {
        $result = ldap_search($link, 'o=hp.com', $filter);
    }
    else
    {
        $result = ldap_search($link, 'o=hp.com', $filter, $attriblist);
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
?>
