<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

/*********************************
 Global array: don't touch!
 *********************************/
$Plugins = array();

/*****************************************
 plugin_cmp(): Compare two plugins for sorting.
 *****************************************/
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
  /** check if $a is a dependency for $b **/
  // print "BEGIN Comparing $a->Name with $b->Name\n";
  foreach($a->Dependency as $val)
    {
    // print "Comparing $a->Name :: $val with $b->Name\n";
    if ($val == $b->Name) { return(1); }
    }
  /** check if $b is a dependency for $a **/
  foreach($b->Dependency as $val)
    {
    // print "Comparing $b->Name :: $val with $a->Name\n";
    if ($val == $a->Name) { return(-1); }
    }
  // print "STILL Comparing $a->Name with $b->Name\n";

  /* Nothing else to sort by -- sort by number of dependencies */
  $rc = count($a->Dependency) - count($b->Dependency);
  return($rc);
} // plugin_cmp()

/*****************************************
 plugin_sort(): Given a loaded plugin list,
 sort the plugins by dependencies!
 *****************************************/
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
      $D[$P->Dependency[$j]] = 1;
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
  usort($Plugins,plugin_cmp);
} // plugin_sort()

/*****************************************
 plugin_find_id(): Given the official name of a plugin,
 find the index to it in the $Plugins array, or
 return -1 if it is not found.
 *****************************************/
function plugin_find_id($Name)
{
  global $Plugins;
  foreach ($Plugins as $key => $val)
    {
    if (!strcmp($val->Name,$Name))
	{
	if ($val->State != PLUGIN_STATE_READY) return(-1);
	return($key);
	}
    }
  return(-1);
} // plugin_find_id()

/*****************************************
 plugin_find_any_id(): Given the official name of a plugin,
 find the index to it in the $Plugins array, or
 return -1 if it is not found.
 Unlike plugin_find_id(), this ignores plugin state.
 *****************************************/
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

/*****************************************
 plugin_find(): Given the official name of a plugin,
 return the $Plugins object, or NULL.
 *****************************************/
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

/*****************************************
 plugin_find_any(): Given the official name of a plugin,
 return the $Plugins object, or NULL.
 *****************************************/
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

/*****************************************
 plugin_load(): Load every plugin!
 *****************************************/
function plugin_load($PlugDir)
{
  global $Plugins;

  /* Load everything found in the plugin directory */
  if ($Dir = opendir($PlugDir))
    {
    while (($File = readdir($Dir)) !== false)
	{
	if (substr($File,-4) === ".php")
	  {
	  // print "Loading $File\n";
	  include_once("$PlugDir/$File");
	  }
	}
    }
  closedir($Dir);

  /* Now activate the plugins */
  plugin_sort();
  $Count = count($Plugins);
  for($Key=0; $Key < $Count; $Key++)
    {
    $P = &$Plugins[$Key];
    if ($P->State == PLUGIN_STATE_VALID) { $P->PostInitialize(); }
    if ($P->State == PLUGIN_STATE_READY) { $P->RegisterMenus(); }
    }
} // plugin_load()

/*****************************************
 plugin_unload(): Unload every plugin!
 *****************************************/
function plugin_unload()
{
  global $Plugins;

  foreach($Plugins as $Key => $Val)
    {
    $P = &$Plugins[$Key];
    if ($P->State != PLUGIN_STATE_INVALID) { $P->Destroy(); }
    }
} // plugin_unload()

?>
