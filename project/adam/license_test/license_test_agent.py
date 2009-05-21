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
import os
import math
import re
import psycopg2
import threading
from optparse import OptionParser

# set up heartbeat functionality.
timer = None

def heartbeat():
    print "Heartbeat"
    global timer
    timer.cancel()
    timer = threading.Timer(6,heartbeat)
    timer.start()

timer = threading.Timer(60,heartbeat)
timer.start()

# default paramerters
lw = 1
rw = 1
pw = 0.5
smoothing = False
pos_files = 'pos.txt'
neg_files = 'neg.txt'

# read params from somewhere.
# assume that the file is something like key=value;\n
param_text = open('license_test.conf').read()
param_dict = dict(re.findall('(?P<key>.*)=(?P<value>.*);',param_text))

if (param_dict.get('lw',False)):
    if int(param_dict['lw'])>0:
        lw = int(param_dict['lw'])
    else:
        sys.stderr.write('ERROR: parameter [lw] was non integer or less than 1.\n')
        sys.exit(-1)

if (param_dict.get('rw',False)):
    if int(param_dict['rw'])>0:
        rw = int(param_dict['rw'])
    else:
        sys.stderr.write('ERROR: parameter [rw] was non integer or less than 1.\n')
        sys.exit(-1)

if (param_dict.get('pr',False)):
    if float(param_dict['pr'])>0.0 and float(param_dict['pr'])<1.0:
        pr = float(param_dict['pr'])
    else:
        sys.stderr.write('ERROR: parameter [pr] was non float or not in range (0,1).\n')
        sys.exit(-1)

if (param_dict.get('smoothing',False)):
    if param_dict['smoothing'] != 'True' and param_dict['smoothing'] != 'False':
        sys.stderr.write('ERROR: parameter [smoothing] was not True or False.\n')
        sys.exit(-1)
    else:
        if param_dict['smoothing'] == 'True':
            smoothing = True
        else:
            smoothing = False

if (param_dict.get('pos_files',False)):
    if os.path.isfile(param_dict['pos_files']):
        pos_files = param_dict['pos_files']
    else:
        sys.stderr.write('ERROR: parameter [pos_files] is not a file.\n')
        sys.exit(-1)

if (param_dict.get('neg_files',False)):
    if os.path.isfile(param_dict['neg_files']):
        neg_files = param_dict['neg_files']
    else:
        sys.stderr.write('ERROR: parameter [neg_files] is not a file.\n')
        sys.exit(-1)

# initialize the dictionaries that will hold the stats about licenses and non-licenses
pos_word_dict = {}   # P(word|license)
neg_word_dict = {}    # P(word|non-license)
pos_word_matrix = {} # P(word_i,word_i+1|license)
neg_word_matrix = {}  # P(word_i,word_i+1|non-license)

# Calculate P(*|license)
files = [line.strip() for line in open(pos_files)]
(pos_word_dict, pos_word_matrix) = model.train_word_dict(files,False)

# Calculate P(*|non-license)
files = [line.strip() for line in open(neg_files)]
(neg_word_dict, neg_word_matrix) = model.train_word_dict(files)

# smoothing makes the false negitive rate go down.
if smoothing:
    for file in files:
        model.reweight(file,pos_word_dict,pos_word_matrix,neg_word_dict,neg_word_matrix,pr,lw,rw)


# db connection
connection = None
DB_conf = {}
# read and parse the Db.conf file so know how to connect to the database.
# we are expecting a command like """conf=Db.conf;\n""". If we dont get this then we print an error and continue reading stdin for a command of that sort
line = sys.stdin.readline().strip()
while line:
    if line.strip() == 'quit':
        timer.cancel()
        sys.exit(0)
    if not re.findall('(?P<key>.*)=(?P<value>.*);',line):
        sys.stderr.write('ERROR: unknown command: %s' % line)
        continue
    (key, value) = re.findall('(?P<key>.*)=(?P<value>.*);',line)[0]

    if key == 'conf':
        if os.path.isfile(value):
            try:
                DB_conf = dict(re.findall('(?P<key>.*)=(?P<value>.*);',open(value).read()))

            except:
                sys.stderr.write('ERROR: conf=%s; connot open %s.\n' % (value,value))
                sys.exit(-1)
            try:
            	connection = psycopg2.connect("dbname='%s' user='%s' host='%s' password='%s'" % (DB_conf['dbname'],DB_conf['user'],DB_conf['host'],DB_conf['password']))
            	break
            except:
                sys.stderr.write('ERROR: Could not connect to database.\n')
                sys.exit(-1)

        else:
            sys.stderr.write('ERROR: conf=%s; %s is not a file.\n' % (value,value))
            sys.exit(-1)
    else:
        sys.stderr.write('ERROR: looking for conf=configure file path; found %s=%s;\n')
    line = sys.stdin.readline().strip()

# this is were we read stdin for files to test. We read commands from the command line in this format, """file=/media/disk/somefolder/file.txt;""". If we dont get that then we continue to look for it from stdin.
line = sys.stdin.readline().strip()
while line:
    if line.strip() == 'quit':
        timer.cancel()
        sys.exit(0)
    if not re.findall('(?P<key>.*)=(?P<value>.*);',line):
	    sys.stderr.write('ERROR: unknown command: %s' % line)
	    continue
    (key, value) = re.findall('(?P<key>.*)=(?P<value>.*);',line)[0]
    if key == 'file':
        score = model.test_file(value,pos_word_dict,pos_word_matrix,neg_word_dict,neg_word_matrix,pr,lw,rw)
        l = model.smooth_score(score)
        is_license = sum(l)>0
        print "%s: %s" % (is_license,file)

        # write our info into the database...
        cursor = connection.cursor()
        cursor.execute('''SELECT id FROM temp''')
        rows = cursor.fetchall()
        print rows[-1]
    line = sys.stdin.readline().strip()

