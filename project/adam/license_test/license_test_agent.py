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
import parser   # utils directory
import license_test_model as model # current direcory
# End of custom libraries

import sys
import os
import math
import re

# psycopg2 docs:
#   http://www.python.org/dev/peps/pep-0249/
#   http://initd.org/pub/software/psycopg/dbapi20programming.pdf
#   /usr/share/doc/python-psycopg2/doc/extensions.rst.gz
# apt-get install python-psycopg2
try: # if we dont have psycopg2 then try psycopg
    import psycopg2 as psycopg
except:
	import psycopg

import threading
from optparse import OptionParser

# set up heartbeat functionality.
timer = None

# show heartbeat every 60 secs
def heartbeat():
    print "Heartbeat"
    global timer
    timer.cancel()
    timer = threading.Timer(60,heartbeat)
    timer.start()

timer = threading.Timer(60,heartbeat)
timer.start()

try: # wrap with a try block so if something bad happens we can stop the heartbeat thread.

    # read params from somewhere.
    # assume that the file is something like key=value;\n
    param_text = open('license_test.conf').read()
    param_dict = dict(re.findall('(?P<key>.*)=(?P<value>.*);',param_text))
    
    if (param_dict.get('cache_file',False)):
        if os.path.isfile(param_dict['cache_file']):
            cache_file = param_dict['cache_file']
        else:
            sys.stderr.write('ERROR: parameter [cache_file] does not exist.\n')
            sys.exit(-1)
    else:
        sys.stderr.write('ERROR: [cache_file] parameter was not provided.\n')
        sys.exit(-1)

    if (param_dict.get('db_conf', False)):
        if os.path.isfile(param_dict['db_conf']):
            db_conf = param_dict['db_conf']
        else:
            sys.stderr.write('ERROR: parameter [db_conf] does not exist.\n')
            sys.exit(-1)
    else:
        sys.stderr.write('ERROR: [db_conf] parameter was not provided.\n')
        sys.exit(-1)
    
    if (param_dict.get('version', False)):
        version = param_dict['version']
    else:
        sys.stderr.write('ERROR: [version] parameter was not provided.\n')
        sys.exit(-1)

    # load the cached model
    lt_model = pickle.load(open(cache_file))

    # db connection
    connection = None
    DB_conf = dict(re.findall('(?P<key>.*)=(?P<value>.*);',open(db_conf).read()))
    try:
       	connection = psycopg.connect("dbname='%s' user='%s' host='%s' password='%s'" % (DB_conf['dbname'],DB_conf['user'],DB_conf['host'],DB_conf['password']))
    except:
        sys.stderr.write('ERROR: Could not connect to database.\n')
        sys.exit(-1)

    # TODO: add code for getting the agents id
    
    print 'Okay...' # we are ready for input    
    
    # this is were we read stdin for files to test. We read commands from the
    # command line in this format, """primary_ky, file_path\n""". If we dont get that then we continue to look for it from stdin.
    line = sys.stdin.readline().strip()
    while line:
        if line.strip() == 'quit':
            timer.cancel()
            sys.exit(0)
        if not re.findall('(?P<key>.*), (?P<path>.*)\n',line):
    	    sys.stderr.write('ERROR: unknown command: %s' % line)
            line = sys.stdin.readline().strip()
    	    continue
        (key, path) = re.findall('(?P<key>.*), (?P<path>.*)\n',line)[0]
        if os.path.isfile(path):
            score = lt_model.test_file(path)
            l = lt_model.smooth_score(score)
            is_license = sum(l)>0
            print "%s: %s" % (is_license,path)
    
            # write our info into the database...
            cursor = connection.cursor()
            cursor.execute('''SELECT id FROM temp''')
            rows = cursor.fetchall()
            print rows[-1]
        else:
            sys.stderr('ERROR: "%s" does not exist.' % path)

        line = sys.stdin.readline().strip()

except:
    timer.cancel()
    raise 
