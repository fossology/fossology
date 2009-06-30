#!/usr/bin/python

##
## Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
##
## This program is free software; you can redistribute it and/or
## modify it under the terms of the GNU General Public License
## version 2 as published by the Free Software Foundation.
##
## This program is distributed in the hope that it will be useful,
## but WITHOUT ANY WARRANTY; without even the implied warranty of
## MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
## GNU General Public License for more details.
##
## You should have received a copy of the GNU General Public License along
## with this program; if not, write to the Free Software Foundation, Inc.,
## 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
##

# This script uses the distribution of words in license files to determine if
# a test file contains a license. This script requires two sets of data to
# learn from. The first is a set of license documents in text format. These
# should only contain license texts (no source code). The second is a set of
# files other than license, i.e. source code. These non license examples should
# not contain license text. These two data sets allow for the learning of the
# distribution of words in licenses and non-licenses.

# Import custom libraries. You should set $PYTHONPATH or make sure that python
# can find these libraries.
import parser
import license_test_model as model
# End of custom libraries

import sys
import math
import re
import os
import pickle
from optparse import OptionParser

usage = "usage: %prog -p pos_files -n neg_files [ -h ] test_files..."
optparser = OptionParser(usage)
optparser.add_option("-p", "--positive", type="string",
        help="A text file with the paths to the positive training files.")
optparser.add_option("-n", "--negative", type="string",
        help="A text file with the paths to the negative training files.")
optparser.add_option("-f", "--training_files", type="string",
        help="A test file with the paths to the training files.")
optparser.add_option("--lw", type="int",
        help="Left window plus 1. Must be > 0.")
optparser.add_option("--rw", type="int",
        help="Right window plus 1. Must be > 0")
optparser.add_option("--pr", type="float",
        help="Prior probability of seeing a yes file. Must be between 0 and 1")
optparser.add_option("--no-smoothing", action="store_false", dest="smoothing",
        help="Prevent smoothing of the positive and negative weights.")
optparser.add_option("--cache", type="string",
        help="Location of the cache file.")

(options, args) = optparser.parse_args()

if options.cache and os.path.isfile(options.cache):
    print "Loading cached model. Ignoring all other parameters."
    lt_model = pickle.load(open(options.cache))
else:
    # default parameters
    lw = 3        # left window
    rw = 3        # right window
    pr = 0.2      # probability of finding a license in a random window
    smoothing = False
    files = []
    
    if not options.training_files:
        print "You must specify a set of files to train on."
        optparser.print_usage()
        sys.exit(1)
    else:
        files = [line.strip() for line in open(options.training_files)]
    
    # learning parameters
    # window size starting at word i to the left
    if options.lw:
        if options.lw<1:
            print 'The value of the left window must be greater than zero.'
            optparser.print_usage()
            sys.exit(1)
        lw = options.lw
    # window size starting at word i to the right
    if options.rw:
        if options.rw<1:
            print 'The value of the right window must be greater than zero.'
            optparser.print_usage()
            sys.exit(1)
        rw = options.rw
    # prior probability of seeing a license
    if options.pr:
        if options.pr<1 and options.pr>0:
            pr = options.pr
        else:
            print 'The value of the prior probability must be between 0 and 1. (Non-inclusive).'
            optparser.print_usage()
            sys.exit(1)
    
    if options.smoothing == False:
        smoothing = False
    
    lt_model = model.LicenseTestModel(files, pr, lw, rw, smoothing)
    lt_model.train()
    
    if options.cache:
        pickle.dump(lt_model,open(options.cache,'w'))

for file in args:
    text = unicode(open(file).read(64000),errors='ignore')
    is_license, l, license_offsets = lt_model.test_text(text)

    print "%s: %s" % (is_license,file)
    for i in xrange(len(license_offsets)):
        print "\t[%d, %d:%d] %s" % (i,license_offsets[i][0], license_offsets[i][1], text[license_offsets[i][0]:license_offsets[i][1]])
