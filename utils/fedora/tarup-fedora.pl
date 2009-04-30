#!/usr/bin/perl -w
#/***********************************************************
# tarup-fedora.pl
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# version 2 as published by the Free Software Foundation.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
# ***********************************************************/
#
# tar up the fedora packages for loading into fossology.
#
# This is just a throw away hack, so we hard code things.
# well I thought so... might rewrite in php, but then again... may just
# spiff up for production.... Float it past the team...
#
# NOTE: this program is currently coded to run on Fawkes.rags.
#
#       Best if run as sudo, certain internaltional files are not readable.

#
# todo: put in processing of parameters and parameter checks.

use strict;
use warnings;
use Getopt::Std;

#
# Parmeters: -i input-path packages are assumed to be in that path
#            -o output-path path were tar'ed packages will be stored.
#               make sure there is at least 12GB for tar storage.

my $usage = "tarup-fedora [-h] -i <input-path> -o <output-path>\n";
my $debug = 0;

our ($opt_d, $opt_h, $opt_i, $opt_o );
getopts('dhi:o:');
if ( defined($opt_h) )
{
   print $usage;
   exit(0);
}
if ( !defined($opt_i) )
{
   print "Error, must supply an input path\n";
   print $usage;
   exit(1);
}
if ( !defined($opt_o) )
{
   print "Error, must supply an output path\n";
   print $usage;
   exit(1);
}
if (defined($opt_d))
{
   $debug = 1;    
}

my $in_path  = $opt_i;
my $out_path = $opt_o;

chdir $in_path or die "Can't chdir to $in_path: $!\n";

my @list = `ls`;

foreach my $pkg (@list)
{    
   chomp($pkg);    # like trim
   my $where = `pwd`;
   print "Top of LOOP: Current working dir is:$where" if ($debug);

# package can end up being a file or some other cruft in the devel directory.
# if you can't cd into it, it's not a package and we skip it.

   if ( !( chdir($pkg) ) )
   {
      print("Can't chdir to $pkg: $! Skipping....\n");
      next;
   }

   $where = `pwd` if ($debug);
   print "After chdir: Current working dir is:$where" if ($debug);

   # check for dead packages, and skip them....
   my @filelist = `ls`;
   my $dead = 0;
   my $spec = 0;
   foreach my $file (@filelist)
   {
      chomp $file;
      if ( $file =~ m/dead\.package/ )
      {
         print "$pkg is a Dead Package, Skipping...\n";
         $dead++;
         last;
      }
      elsif ( $file =~ m/\.spec$/ )
      {
         $spec++;
         last;
      }
      if ($spec > 0)
      {
         print "$pkg has no spec file, Skipping";
      }
   }
   print "after dead check, chdir'ing\n" if($debug);
   chdir('..') or die("Can't chdir to ..: $!\n");
   $where = `pwd`;
   #rint "Current working dir is:$where";
   if ($dead || $spec)
   {
      $dead = 0;
      $spec = 0;
      next;
   }
   my $tar_name;
   $tar_name = "$pkg" . '.tar.bz2';
   my $tpath .= "$out_path" . "$tar_name";
   print("will do:\ntar cjf $tpath $pkg\n");
   #my $rtn = system("tar cjf $out_path $pkg");
   #if ( $rtn != 0 )
   #{
   #   print "ERROR: make tar did not exit zero: was: $rtn\n";
   #}
   $tpath = "";
   $tar_name = "";
}
