<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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
 ***********************************************************/

/**
 * \file common-plugin.php
 * \brief Core functions for user interface plugins
 **/

/**
 * \brief Global plugins array
 **/
$Plugins = array();

/**
 \brief Sort compare function.  Sorts by dependency
 relationship.  If a and b are at the same
 dependency level, then sort by the plugin level.

 \param Plugin a
 \param Plugin b

 \return -1, 0, 1 for plugin a being <, =, or > than b
 **/
function plugin_cmp($a,$b)
{
  /* Sort by plugin version only when the name is the same */
  $rc = strcmp($a->Name,$b->Name);
  if ($rc == 0)
  {
    /* Sort by plugin version (descending order) */
    $rc = strcmp($a->Version,$b->Version);
    if ($rc != 0) { return(-$rc); }
  }

  /* Sort by dependencies. */
  /* check if $a is a dependency for $b */
  // print "BEGIN Comparing $a->Name with $b->Name\n";
  foreach($a->Dependency as $val)
  {
    // print "Comparing $a->Name :: $val with $b->Name\n";
    if ($val == $b->Name) { return(1); }
  }
  /* check if $b is a dependency for $a */
  foreach($b->Dependency as $val)
  {
    // print "Comparing $b->Name :: $val with $a->Name\n";
    if ($val == $a->Name) { return(-1); }
  }
  // print "STILL Comparing $a->Name with $b->Name\n";

  /* If same dependencies, then sort by plugin level (highest comes first) */
  if ($a->PluginLevel != $b->PluginLevel)
  {
    if ($a->PluginLevel > $b->PluginLevel) { return(-1); }
    else { return(1); }
  }

  /* Nothing else to sort by -- sort by number of dependencies */
  $rc = count($a->Dependency) - count($b->Dependency);
  return($rc);
} // plugin_cmp()

/**
 * \brief Disable all plugins that have a level greater than the users permission level.
 *
 * \param $Level the users DBaccess level
 * \return void
 */
function plugin_disable($Level)
{
  global $Plugins;

  if(empty($Level)) return(0);

  /* Disable all plugins with >= $Level access */
  //echo "<pre>COMP: starting to disable plugins\n</pre>";
  $LoginFlag = empty($_SESSION['User']);
  $Max = count($Plugins);
  for ($i = 0;$i < $Max;$i++)
  {
    $P = & $Plugins[$i];
    if ($P->State == PLUGIN_STATE_INVALID)
    {
      //echo "<pre>COMP: Plugin $P->Name is in INVALID state\n</pre>";
      continue;
    }
    if (($P->DBaccess > $Level) || (empty($_SESSION['User']) && $P->LoginFlag)) {
      //echo "<pre>COMP: Going to disable $P->Name\n</pre>";
      //echo "<pre>COMP: disabling plugins with $P->DBaccess  >= $Level\n</pre>";
      $P->Destroy();
      $P->State = PLUGIN_STATE_INVALID;
    }
  }
} // plugin_disable

/**
 * \brief Sort the global $Plugins by dependencies.  This way plugins
 *        get loaded in the correct order.
 **/
function plugin_sort()
{
  global $Plugins;

  /* Ideally, I would like to use usort.  However, there are
   dependency issues.  Specifically: usort only works where there are
   direct comparisons.  It does not work with indirect dependencies.
   For example:
   A depends on B (A->B).
   B depends on C (B->C).
   If I just use usort, then C may be sorted AFTER A since there is
   no explicit link from A->C.  The array (B,A,C) is a possible usort
   return, and it is wrong.
   Before I can sort, we must fill out the dependency arrays.
   */

  /* for each plugin, store the dependencies in a matrix */
  $DepArray=array();
  for($i=0; $i < count($Plugins); $i++)
  {
    $P = &$Plugins[$i];
    if (empty($P->Dependency[0])) continue; // ignore no dependencies
    $DepArray[$P->Name] = array();
    $D = &$DepArray[$P->Name];
    for($j=0; $j < count($P->Dependency); $j++)
    {
      $D[$P->Dependency[$j]] = $P->PluginLevel;
    }
  }

  /* Now iterate through the array.
   This converts implied dependencies into direct dependencies. */
  foreach($DepArray as $A => $a)
  {
    $Aa = &$DepArray[$A];
    /* Find every element that depends on this element and merge the
     dependency lists */
    foreach($DepArray as $B => $b)
    {
      $Bb = $DepArray[$B];
      if (!empty($Bb[$A]))
      {
        /* merge in the entire list */
        $DepArray[$B] = array_merge($Aa,$Bb);
      }
    }
  }

  /* Finally: Put the direct dependencies back into the structures */
  for($i=0; $i < count($Plugins); $i++)
  {
    $P = &$Plugins[$i];
    if (empty($P->Dependency[0])) continue; // ignore no dependencies
    $P->Dependency = array_keys($DepArray[$P->Name]);
  }

  /* Now it is safe to sort */
  usort($Plugins,'plugin_cmp');
} // plugin_sort()

/**
 * \brief Given the official name of a plugin, find the index to it in the
 *        global $Plugins array.
 *        Only plugins in PLUGIN_STATE_READY are scanned.
 * \param $Name Plugin name
 * \return -1 if the plugin $Name is not found.
 **/
function plugin_find_id($Name)
{
  global $Plugins;

  foreach ($Plugins as $key => $val) {
    if (empty($val)) continue;
    if (!strcmp($val->Name,$Name)) {
      if ($val->State != PLUGIN_STATE_READY) {
        return(-1);
      }
      return($key);
    }
  }
  return(-1);
} // plugin_find_id()

/**
 * \brief Given the official name of a plugin, find the index to it in the
 *        global $Plugins array.
 *
 *        Note that Unlike plugin_find_id(), this ignores plugin state.
 * \param $Name Plugin name
 * \return -1 if it is not found.
 **/
function plugin_find_any_id($Name)
{
  global $Plugins;
  foreach ($Plugins as $key => $val)
  {
    if (!strcmp($val->Name,$Name))
    {
      return($key);
    }
  }
  return(-1);
} // plugin_find_any_id()

/**
 * \brief Given the official name of a plugin, return the $Plugins object.
 *        Only plugins in PLUGIN_STATE_READY are scanned.
 * \return NULL if the plugin name isn't found.
 **/
function plugin_find($Name)
{
  global $Plugins;
  foreach ($Plugins as $key => $val)
  {
    if (!strcmp($val->Name,$Name))
    {
      if ($val->State != PLUGIN_STATE_READY) return(-1);
      $P = &$Plugins[$key];
      return($P);
    }
  }
  return NULL;
} // plugin_find()

/**
 * \brief Given the official name of a plugin, return the $Plugins object.
 *        All plugins are scanned regardless of state.
 * \return NULL if the plugin name isn't found.
 **/
function plugin_find_any($Name)
{
  global $Plugins;
  foreach ($Plugins as $key => $val)
  {
    if (!strcmp($val->Name,$Name))
    {
      $P = &$Plugins[$key];
      return($P);
    }
  }
  return NULL;
} // plugin_find_any()

/**
 * \brief Initialize every plugin in the global $Plugins array.
 *        plugin_sort() is called followed by the plugin
 *        PostInitialize() if PLUGIN_STATE_VALID,
 *        and RegisterMenus() if PLUGIN_STATE_READY.
 **/
function plugin_init()
{
  global $Plugins;
  /* Now activate the plugins */
  plugin_sort();
  $Count = count($Plugins);
  for($Key=0; $Key < $Count; $Key++)
  {
    $P = &$Plugins[$Key];
    if ($P->State == PLUGIN_STATE_VALID) { $P->PostInitialize(); }
    if ($P->State == PLUGIN_STATE_READY) { $P->RegisterMenus(); }
  }
} // plugin_init()

/**
 * \brief Call the Install method for every plugin
 *
 * @param boolean $Verbose
 *
 * @return false=success error string on error.
 */
function plugin_install($Verbose)
{
  global $Plugins;

  $Max = count($Plugins);
  $FailMsg = NULL;
  if($Verbose)
  {
    print "  Installing plugins\n";
    flush();
  }
  for ($i = 0;$i < $Max;$i++) {
    $P = & $Plugins[$i];
    /* call Install method for ALL plugins */
    $State = $P->Install();
    if ($State != 0) {
      $FailMsg = "FAILED: " . $P->Name . " failed to install.\n";
      return($FailMsg);
    }
  }
  return(FALSE);
} // InstallPlugins()
/**
 * \brief Load every module ui found in mods-enabled
 *
 * \param $CallInit 1 = call plugin_init(), else ignored.
 **/
function plugin_load($CallInit=1)
{
  global $Plugins;
  global $SYSCONFDIR;

  $ModsEnabledDir = "$SYSCONFDIR/mods-enabled";

  /* Open $ModsEnabledDir and include all the php files found in the ui/ subdirectory */

  if ((is_dir($ModsEnabledDir)) and ($EnabledDir = opendir($ModsEnabledDir)))
  {
    while (($ModDir = readdir($EnabledDir)) !== false)
    {
      $ModDirPath = "$ModsEnabledDir/$ModDir/ui";
      if (is_dir($ModDirPath) and ($Dir = opendir($ModDirPath)))
      {
        while (($File = readdir($Dir)) !== false)
        {
          if (substr($File,-4) === ".php" and !strstr($File, 'ndex.php')) // ignore index.php
          {
            /* Load php found in the ui directory */
            include_once("$ModDirPath/$File");
          }
        }
      }
    }
  }
  closedir($EnabledDir);
  closedir($Dir);
  if ($CallInit == 1) { plugin_init(); }
} // plugin_load()

/**
 * \brief Unload every plugin by calling its Destroy().
 **/
function plugin_unload()
{
  global $Plugins;

  foreach($Plugins as $Key => $Val)
  {
    /* The plugin stucture's last entry is -1 bogus class, which will
     * cause the $P->Destroy to fail below. */
    if($Key == -1) {
      break;
    }
    if (empty($Val)) { continue; }
    $P = &$Plugins[$Key];
    if ($P->State != PLUGIN_STATE_INVALID) { $P->Destroy(); }
  }
} // plugin_unload()

?>
