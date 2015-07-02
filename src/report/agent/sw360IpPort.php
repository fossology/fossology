<?php

class Sw360IpAndPort
{
  
  function getSw360Ip()
  {
    global $SysConf;
    return($SysConf['SYSCONFIG']["Sw360ServerIpAddress"]);
  }

  function getSw360Port()
  {
    global $SysConf;
    return($SysConf['SYSCONFIG']["Sw360ServerPortAddress"]);
  }
}


