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

import threading
import psyco
psyco.full()

import sys, os, time, library, math
import cPickle
from datetime import datetime
import re
import vector, database
from xml.sax.saxutils import escape
import htmlentitydefs
import traceback
from optparse import OptionParser
import maxent

# set up heartbeat functionality.
timer = None
HeartbeatValue = -1
LastHeartbeatValue = -1
HBItemsProcessed = 0

# Heartbeat(): Update heartbeat counter and items processed
def Heartbeat(NewItemsProcessed):
    global HeartbeatValue
    global HBItemsProcessed
    HeartbeatValue += 1
    HBItemsProcessed = NewItemsProcessed

# ShowHeartbeat(): Given an alarm signal, display a heartbeat.
def ShowHeartbeat():
    global timer
    global HeartbeatValue
    global LastHeartbeatValue
    global HBItemsProcessed
    if ((HeartbeatValue == -1) or (HeartbeatValue != LastHeartbeatValue)):
        print "Items Processed %ld" % HBItemsProcessed
        LastHeartbeatValue = HeartbeatValue
        print "Heartbeat"
    timer.cancel()
    timer = threading.Timer(60,ShowHeartbeat)
    timer.start()

def main():
    global timer
    try:
        timer = threading.Timer(60,ShowHeartbeat)
        timer.start()
        
        usage = "usage: %prog [options] -S sentence_model -D database -T template_file"
        parser = OptionParser(usage)
        parser.add_option("-S", "--sentence_model", type="string",
                help="path of the sentence model file")
        parser.add_option("-D", "--database", type="string",
                help="If the -T flag is spesified then a database cache file will be saved at the location specified. Otherwise, loads a pre-cached database model")
        parser.add_option("-T", "--templates", type="string",
                help="Path to a \\n delimetated file with paths to license template files")
        parser.add_option("-d", "--debug", action="store_true",
                help="Turn debug output on")

        (options, args) = parser.parse_args()

        # load sentence model.
        if not options.sentence_model:
            print >> sys.stderr, 'Sentence Model path not provided.'
            parser.print_usage()
            sys.exit(1)
        sentence_model = maxent.MaxentModel()
        sentence_model.load(options.sentence_model)

        debug_on = False
        if options.debug:
            debug_on = True

        if options.database and options.templates:
            print 'Creating Database...'
            tic = datetime.now()
            files = [line.rstrip() for line in open(options.templates)]
            DB = database.Database(files,sentence_model=sentence_model,debug=debug_on)
            database.save(DB,options.database)
            toc = datetime.now()-tic
            print 'Database created in %s seconds.' % toc
        elif options.database:
            print 'Loading Database...'
            tic = datetime.now()
            DB = database.load(options.database,sentence_model)
            toc = datetime.now()-tic
            print 'Loaded Database in %s seconds.' % toc
        else:
            print >> sys.stderr, 'Templates or database files not specified.'
            parser.print_usage()
            sys.exit(1)
        
    except:
        timer.cancel()
        exceptionType, exceptionValue, exceptionTraceback = sys.exc_info()
        p = repr(traceback.format_exception(exceptionType, exceptionValue, exceptionTraceback))
        sys.stderr.write("%s\n" % p)
        
    
    start = datetime.now()
    
    line = sys.stdin.readline().strip()
    while line:
        try:
            if line == 'quit':
                timer.cancel()
                sys.exit(0)
            values = re.split(',',line)
            if len(values)!=2:
                sys.stderr.write('ERROR: unknown command: %s\n' % line)
                line = sys.stdin.readline().strip()
                continue
            if not os.path.isfile(values[1]):
                sys.stderr.write('ERROR: unknown file: %s\n' % values[1])
                line = sys.stdin.readline().strip()
                continue

            pk = values[0]
            f = values[1]
            name = os.path.basename(f)
    
            # BOBG: this is where you should start
            # this is where everything happends.
            # look in database.py for more code...
            sentences,byte_offsets,matches,unique_hits,cover,maximum,hits,score,fp = database.calculate_matches(DB,f,debug=debug_on,thresh=0.7)
            
            for j in xrange(len(sentences)):
                for k in hits[j]:
                    if k=='Unknown':
                        print "Unknown: 100 [%d, %d], [0, 0]" % (byte_offsets[j][0],byte_offsets[j][1])
                    else:
                        print "%s: %d [%d, %d], [%d, %d]" % (k, int(round(matches[j][k][1]*100.0)), DB.byte_offsets[matches[j][k][0]][0], DB.byte_offsets[matches[j][k][0]][1], byte_offsets[j][0],byte_offsets[j][1])
        
            line = sys.stdin.readline().strip()

        except Exception, e:
            timer.cancel()
            exceptionType, exceptionValue, exceptionTraceback = sys.exc_info()
            p = repr(traceback.format_exception(exceptionType, exceptionValue, exceptionTraceback))
            sys.stderr.write("%s\n" % p)
            line = sys.stdin.readline().strip()
    

    end = datetime.now()
    print "Finished: ", end-start


if __name__ == "__main__":
    main()

