<?php
/*
 SPDX-FileCopyrightText: © 2008-2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only
*/

use Fossology\Lib\Plugin\Plugin;

/**
 * \file
 * \brief Core functions for user interface plugins
 **/

/**
 * \brief Global plugins array
 **/
global $Plugins;
$Plugins = array();

/**
 * @brief Sort compare function.
 *
 * Sorts by dependency relationship.  If a and b are at the same
 * dependency level, then sort by the plugin level.
 *
 * @param Plugin a
 * @param Plugin b
 *
 * @return int -1, 0, 1 for plugin a being <, =, or > than b
 */
function plugin_cmp($a, $b)
{
  /* Sort by plugin version only when the name is the same */
  if (0 == strcmp($a->Name, $b->Name)) {
    /* Sort by plugin version (descending order) */
    $rc = strcmp($a->Version, $b->Version);
    if ($rc != 0) {
      return (- $rc);
    }
  }

  /* Sort by dependencies. */
  /* check if $a is a dependency for $b */
  // print "BEGIN Comparing $a->Name with $b->Name\n";
  foreach ($a->Dependency as $val) {
    // print "Comparing $a->Name :: $val with $b->Name\n";
    if ($val == $b->Name) {
      return (1);
    }
  }
  /* check if $b is a dependency for $a */
  foreach ($b->Dependency as $val) {
    // print "Comparing $b->Name :: $val with $a->Name\n";
    if ($val == $a->Name) {
      return (- 1);
    }
  }
  // print "STILL Comparing $a->Name with $b->Name\n";

  /* If same dependencies, then sort by plugin level (highest comes first) */
  if ($a->PluginLevel > $b->PluginLevel) {
    return (- 1);
  } elseif ($a->PluginLevel < $b->PluginLevel) {
    return (1);
  }

  /* Nothing else to sort by -- sort by number of dependencies */
  $rc = count($a->Dependency) - count($b->Dependency);
  return ($rc);
} // plugin_cmp()

/**
 * \brief Disable all plugins that have a level greater than the users permission level.
 *
 * \param int $Level The user's DBaccess level
 * \return void
 */
function plugin_disable($Level)
{
  /** @var Plugin[] $Plugins */
  global $Plugins;

  /* Disable all plugins with >= $Level access */
  //echo "<pre>COMP: starting to disable plugins\n</pre>";
  $LoginFlag = empty($_SESSION['User']);
  foreach ($Plugins as $pluginName => &$P) {
    if ($P->State == PLUGIN_STATE_INVALID) {
      // echo "<pre>COMP: Plugin $P->Name is in INVALID state\n</pre>";
      continue;
    }
    if ($P->DBaccess > $Level) {
      // echo "<pre>COMP: Going to disable $P->Name\n</pre>";
      // echo "<pre>COMP: disabling plugins with $P->DBaccess >=
      // $Level\n</pre>";
      $P->unInstall();
      unset($Plugins[$pluginName]);
    }
    unset($P);
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
  $DepArray = array();
  foreach ($Plugins as &$P) {
    if (empty($P->Dependency[0])) {
      continue; // ignore no dependencies
    }
    $DepArray[$P->Name] = array();
    $D = &$DepArray[$P->Name];
    for ($j = 0; $j < count($P->Dependency); $j ++) {
      $D[$P->Dependency[$j]] = $P->PluginLevel;
    }
    unset($P);
  }

  /* Now iterate through the array.
   This converts implied dependencies into direct dependencies. */
  foreach ($DepArray as $A => $a) {
    $Aa = &$DepArray[$A];
    /*
     * Find every element that depends on this element and merge the
     * dependency lists
     */
    foreach ($DepArray as $B => $b) {
      $Bb = $DepArray[$B];
      if (! empty($Bb[$A])) {
        /* merge in the entire list */
        $DepArray[$B] = array_merge($Aa, $Bb);
      }
    }
  }

  /* Finally: Put the direct dependencies back into the structures */
  foreach ($Plugins as &$P) {
    if (empty($P->Dependency[0])) {
      continue; // ignore no dependencies
    }
    $P->Dependency = array_keys($DepArray[$P->Name]);
    unset($P);
  }

  /* Now it is safe to sort */
  uasort($Plugins, 'plugin_cmp');
} // plugin_sort()

/**
 * \brief Given the official name of a plugin, find the index to it in the
 *        global $Plugins array.
 *
 * \note Only plugins in PLUGIN_STATE_READY are scanned.
 * \param $Name Plugin name
 * \return -1 if the plugin $Name is not found.
 **/
function plugin_find_id($pluginName)
{
  /** \todo has to be removed */
  /** @var Plugin[] $Plugins */
  global $Plugins;

  if (array_key_exists($pluginName, $Plugins)) {
    $plugin = $Plugins[$pluginName];
    return $plugin->State === PLUGIN_STATE_READY ? $pluginName : - 1;
  }

  return -1;
}

/**
 * @brief Given the official name of a plugin, return the $Plugins object.
 *
 * Only plugins in PLUGIN_STATE_READY are scanned.
 *
 * @param string $pluginName Name of the required plugin
 * @return Plugin|NULL The plugin or NULL if the plugin name isn't found.
 */
function plugin_find($pluginName)
{
  global $Plugins;
  return array_key_exists($pluginName, $Plugins) ? $Plugins[$pluginName] : null;
}

/**
 * \brief Initialize every plugin in the global $Plugins array.
 *
 * plugin_sort() is called followed by the plugin
 * PostInitialize() if PLUGIN_STATE_VALID,
 * and RegisterMenus() if PLUGIN_STATE_READY.
 **/
function plugin_preinstall()
{
  /** @var Plugin[] $Plugins */
  global $Plugins;

  plugin_sort();

  foreach (array_keys($Plugins) as $pluginName) {
    if (array_key_exists($pluginName, $Plugins)) {
      $Plugins[$pluginName]->preInstall();
    }
  }
}

/**
 * \brief Call the Install method for every plugin
 */
function plugin_postinstall()
{
  /** @var Plugin[] $Plugins */
  global $Plugins;

  foreach ($Plugins as &$plugin) {
    $plugin->postInstall();
  }
}

/**
 * \brief Load every module ui found in mods-enabled
 **/
function plugin_load()
{
  global $SYSCONFDIR;

  $ModsEnabledDir = "$SYSCONFDIR/mods-enabled";

  /* Open $ModsEnabledDir and include all the php files found in the ui/ subdirectory */

  if (is_dir($ModsEnabledDir)) {
    foreach (glob("$ModsEnabledDir/*") as $ModDirPath) {
      foreach (array(
        "/ui",
        ""
      ) as $subdir) {
        $targetPath = $ModDirPath . $subdir;

        if (is_dir($targetPath)) {
          foreach (glob("$targetPath/*.php") as $phpFile) {
            if (! strstr($phpFile, 'ndex.php')) {
              include_once ("$phpFile");
            }
          }
          break;
        }
      }
    }
  }
}

/**
 * \brief Unload every plugin by calling its Destroy().
 **/
function plugin_unload()
{
  /** @var Plugin[] $Plugins */
  global $Plugins;

  foreach ($Plugins as $key => $plugin) {
    if ($key == - 1) {
      break;
    }
    if (empty($plugin)) {
      continue;
    }

    $plugin->unInstall();
  }
} // plugin_unload()

/**
 * Register a new plugin to the global Plugins array.
 * @param Plugin $plugin Plugin to be added
 * @throws \Exception If plugin has no name or is already registered
 */
function register_plugin(Plugin $plugin)
{
  /** @var Plugin[] $Plugins */
  global $Plugins;

  $name = $plugin->getName();

  if (empty($name)) {
    throw new \Exception("cannot create module without name");
  }

  if (array_key_exists($name, $Plugins)) {
    throw new \Exception("duplicate definition of plugin with name $name");
  }

  $Plugins[$name] = $plugin;
}

/**
 * Used to convert plugin to string representation by __toString()
 * @param array  $vars      Associative array of variable name => value
 * @param string $classname Name of the class of the object being represented
 * @return string String representation of the object
 */
function getStringRepresentation($vars, $classname)
{
  $output = $classname . " {\n";
  foreach ($vars as $name => $value) {
    if (! is_object($value)) {
      $representation = print_r($value, true);
      $lines = explode("\n", $representation);
      $lines = array_map(function ($line){
        return "      " . $line;
      }, $lines);
      $representation = trim(implode("\n", $lines));

      $output .= "   $name: " . $representation . "\n";
    }
  }
  $output .= "}\n";
  return $output;
}
