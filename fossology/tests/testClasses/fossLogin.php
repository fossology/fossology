<?php
require_once('testUtils.php');

abstract class fossLogin extends testUtils
{
  public $mybrowser;

  public function repoLogin($browser = NULL, $user, $password)
  {

    print "repoLogin is running\n";
    if (is_null($browser))
    {
      print "repoLogin setting browser\n";
      $browser = & new SimpleBrowser();
    }
    $this->setBrowser($browser);
    global $URL;
    global $USER;
    global $PASSWORD;
    $page = NULL;
    $cookieValue = NULL;

    $host = $this->getHost($URL);
    $this->assertTrue(is_object($browser));
    $browser->useCookies();
    $cookieValue = $browser->getCookieValue($host, '/', 'Login');
    // need to check $cookieValue for validity
    $browser->setCookie('Login', $cookieValue, $host);
    $this->assertTrue($browser->get("$URL?mod=auth&nopopup=1"));
    $this->assertTrue($browser->setField('username', $USER));
    $this->assertTrue($browser->setField('password', $PASSWORD));
    $this->assertTrue($browser->isSubmit('Login'));
    $this->assertTrue($browser->clickSubmit('Login'));
    $page = $browser->getContent();
    preg_match('/User Logged In/', $page, $matches);
    $this->assertTrue($matches, "Login PASSED");
    $browser->setCookie('Login', $cookieValue, $host);
    $page = $browser->getContent();
    $NumMatches = preg_match('/User Logged Out/', $page, $matches);
    $this->assertFalse($NumMatches, "User Logged out!, Login Failed! %s");
    return ($cookieValue);
  }
}
?>
