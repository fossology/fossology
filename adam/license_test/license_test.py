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
from optparse import OptionParser

usage = "usage: %prog -y yes_files -n no_files [ -h ] test_files..."
optparser = OptionParser(usage)
optparser.add_option("-y", "--yes", type="string",
        help="A text file with the paths to the yes training files.")
optparser.add_option("-n", "--no", type="string",
        help="A text file with the paths to the no training files.")
optparser.add_option("--lw", type="int",
        help="Left window plus 1. Must be > 0.")
optparser.add_option("--rw", type="int",
        help="Right window plus 1. Must be > 0")
optparser.add_option("--pr", type="float",
        help="Prior probability of seeing a yes file. Must be between 0 and 1")
# optparser.add_option("-l", "--highlight", action="store_true",
#         help="Highlight the license, and output an html file to stdout.")

(options, args) = optparser.parse_args()

if not options.yes:
    print "You must specify a set of yes files to train on."
    optparser.print_usage()
    sys.exit(1)

# learning parameters
lw = 1 # window size starting at word i to the left
if options.lw:
    if options.lw<1:
        print 'The value of the left window must be greater than zero.'
        optparser.print_usage()
        sys.exit(1)
    lw = options.lw
rw = 1 # window size starting at word i to the right
if options.rw:
    if options.rw<1:
        print 'The value of the right window must be greater than zero.'
        optparser.print_usage()
        sys.exit(1)
    rw = options.rw
pr = 0.5 # prior probability of seeing a license
if options.pr:
    if options.pr<1 and options.pr>0:
        pr = options.pr
    else:
        print 'The value of the prior probability must be between 0 and 1. (Non-inclusive).'
        optparser.print_usage()
        sys.exit(1)

# We can output a highlighted license if we want.
#if options.highlight:
#    highlight = True
#else:
#    highlight = False

yes_word_dict = {}   # P(word|license)
no_word_dict = {}    # P(word|non-license)
yes_word_matrix = {} # P(word_i,word_i+1|license)
no_word_matrix = {}  # P(word_i,word_i+1|non-license)

# Calculate P(*|license)
files = [line.strip() for line in open(options.yes)]
(yes_word_dict, yes_word_matrix) = model.train_word_dict(files,False)

# Calculate P(*|non-license)
files = [line.strip() for line in open(options.no)]
(no_word_dict, no_word_matrix) = model.train_word_dict(files)

trues = 0
for file in files:
    model.reweight(file,yes_word_dict,yes_word_matrix,no_word_dict,no_word_matrix,pr,lw,rw)


for file in args:
    score = model.test_file(file,yes_word_dict,yes_word_matrix,no_word_dict,no_word_matrix,pr,lw,rw)
    l = model.smooth_score(score)

    print "%s: %s" % (sum(l)>0,file)
    if sum(l)>0:
        trues += 1
print '# of Trues: %d of %d.' % (trues, len(args))
