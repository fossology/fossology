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

import sys
import re
import traceback
import copyright_library as library
import cPickle as pickle
from optparse import OptionParser

try:
    import psyco
    psyco.full()
except:
    pass


def main():
    #         ------------------------------------------------------------
    usage  = "%prog is used to automatically locate copyright statements. \n"
    usage += " There are three (3) possible functioning modes that %prog \n"
    usage += " can enter:\n\n"
    usage += "    MODEL CREATION    :: Create a model of copyright statements \n"
    usage += "                         using training data.\n"
    usage += "    COMMAND LINE TEST :: Test a file from the command line.\n"
    usage += "    AGENT TEST        :: Waits for commands from stdin.\n\n"
    usage += "  +----------------+\n"
    usage += "  | MODEL CREATION |\n"
    usage += "  +----------------+\n"
    usage += "  To analyze any files a model is REQUIRED. To create a model a set of labeled file must used; a basic set is provided in the data directory. First create a file listing the paths of the training files. Making sure that each training file is on its own line in the file. Next run the following command:\n"
    usage += "    %prog --model model.dat --training training_files\n"
    usage += "  This will create a model file called 'model.dat' from the training file provided in 'training_files'.\n"
    usage += "  +-------------------+\n"
    usage += "  | COMMAND LINE TEST |\n"
    usage += "  +-------------------+\n"
    usage += "  To analyze a file from the command line you must first create a model, see MODEL CREATION.\n"
    usage += "  There are two options for passing the file to be analyzed to %prog. The first uses a text file that lists the paths of the files with one path per line. The second option is to pass the files over the command line. For the first option use the following command:\n"
    usage += "    %prog --model model.dat --analyze-from-file test_files\n"

    
    usage += "  \n"
  
    optparser = OptionParser(usage)
    optparser.add_option("-m", "--model", type="string",
            help="Path to the model file.")
    optparser.add_option("-t", "--training", type="string",
            help="List of training files.")
    optparser.add_option("-f", "--analyze-from-file", type="string",
            help="Path to the files to analyze.")
    optparser.add_option("-c", "--analyze-from-command-line", action="store_true",
            help="File to be analyzed will be passed over the command line.")
    optparser.add_option("--setup-database", action="store_true",
            help="Creates the tables for copyright analysis that the fossology database needs.")
    optparser.add_option("--agent", action="store_true",
            help="Starts up in agent mode. Files will be read from stdin.")
    optparser.add_option("-i", "--init", action="store_true",
            help="Creates a connection to the database and quits.")
    optparser.add_option("-v", "--verbose", action="store_true",
            help="")

    (options, args) = optparser.parse_args()
    
    if options.init:
        db = None
        try:
            db = libfosspython.FossDB()
        except:
            print >> sys.stderr, 'ERROR: Something is broken. Could not connect to database.'
            return 1
        return 0

    if options.setup_database:
        return(setup_database())

    if not options.model:
        print >> sys.stderr, 'You must specify a model file for all phases of the algorithm.\n\n'
        optparser.print_usage()
        sys.exit(1)

    model = {}
    if options.training:
        files = [line.rstrip() for line in open(options.training).readlines()]
        model = library.create_model(files)
        pickle.dump(model, open(options.model,'w'))

    try:
        model = pickle.load(open(options.model))
    except:
        print >> sys.stderr, 'You must specify a training file to create a model.\n\n'
        optparser.print_usage()
        sys.exit(1)

    if options.analyze_from_file:
        files = [line.rstrip() for line in open(options.analyze_from_file).readlines()]
        for file in files:
            results = library.label_file(file,model)
            print "%s :: " % (file)
            if len(results) == 0:
                print "No copyrights"
            for i in range(len(results)):
                print "\t[%d:%d]" % (results[i][0], results[i][1])

    if options.analyze_from_command_line:
        files = args
        for file in files:
            results = library.label_file(file,model)
            print "%s :: " % (file)
            if len(results) == 0:
                print "No copyrights"
            for i in range(len(results)):
                print "\t[%d:%d]" % (results[i][0], results[i][1])

    if options.agent:
         return(agent(model))   

def agent(model):
    try:
        db = None
        try:
            db = libfosspython.FossDB()
        except:
            print >> sys.stderr, 'ERROR: Something is broken. Could not connect to database.'
            return 1

        if libfosspython.repOpen() != 1:
            print >> sys.stderr, 'ERROR: Something is broken. Could not open Repo.'
            return 1

        agent_pk = db.getAgentKey('copyright', '1', 'copyright agent')
        
        count = 0

        line = 'start'
        while line:
            line = line.strip()
            re_str = "pfile_pk=\"([0-9]+)\" pfilename=\"([0-9a-fA-F]+\.[0-9a-fA-F]+\.[0-9]+)\""
            if re.match(re_str, line):
                (pfile, file) = re.findall(re_str, line)[0]
                pfile = int(pfile)
                path = libfosspython.repMkPath('files', file)
                offsets = library.label_file(path,model)
                if len(offsets) == 0:
                    result = db.access("INSERT INTO copyright_test (agent_fk, pfile_fk, copy_startbyte, copy_endbyte)"
                        "VALUES (%d, %d, NULL, NULL);" % (agent_pk, pfile))
                else:
                    for i in range(len(offsets)):
                        result = db.access("INSERT INTO copyright_test (agent_fk, pfile_fk, copy_startbyte, copy_endbyte)"
                            "VALUES (%d, %d, %d, %d);" % (agent_pk, pfile, offsets[i][0], offsets[i][1]))
                # update the heartbeat count
                count += 1
                #libfosspython.updateHeartbeat(count)
                sys.stdout.write("OK\n")
                sys.stdout.flush()
                sys.stdout.write("ItemsProcessed %ld\n" % count)
                sys.stdout.flush()
            elif re.match("quit", line):
                print "BYE."
                break
            elif re.match("start", line):
                sys.stdout.write("OK\n")
                sys.stdout.flush()
                #libfosspython.initHeartbeat()
                count = 0
            #elif re.match("again", line):
                #libfosspython.initHeartbeat()
                #libfosspython.updateHeartbeat(count)

            try:
                line = sys.stdin.readline()
            except:
                line = "quit"
                exceptionType, exceptionValue, exceptionTraceback = sys.exc_info()
                p = repr(traceback.format_exception(exceptionType, exceptionValue, exceptionTraceback))
                sys.stderr.write("ERROR:\n%s\n" % p)
                sys.stderr.flush()

    except:
        exceptionType, exceptionValue, exceptionTraceback = sys.exc_info()
        p = repr(traceback.format_exception(exceptionType, exceptionValue, exceptionTraceback))
        sys.stderr.write("ERROR:\n%s\n" % p)
        sys.stderr.flush()
        return 1

    libfosspython.repClose()
    
    return 0

def setup_database():
    db = None
    try:
        db = libfosspython.FossDB()
    except:
        print >> sys.stderr, 'ERROR: Something is broken. Could not connect to database.'
        sys.exit(1)

    result = db.access("SELECT * FROM copyright_agent_fk_seq LIMIT 1;")
    if (result == 0):
        result = db.access("CREATE SEQUENCE copyright_agent_fk_seq "
            "START WITH 1 "
            "INCREMENT BY 1 "
            "NO MAXVALUE "
            "NO MINVALUE "
            "CACHE 1;")
        if result != 0:
            print >> sys.stderr, "ERROR: Couldn't create copyright_agent_fk_seq."
            sys.exit(1)

        result = db.access("ALTER TABLE public.copyright_agent_fk_seq OWNER TO fossy;")
        if result != 0:
            print >> sys.stderr, "ERROR: Couldn't alter copyright_agent_fk_seq."
            sys.exit(1)
    else:
        print >> sys.stdout, 'WARNING: Table copyright_agent_fk_seq already exists. Skipping.'

    result = db.access("SELECT * FROM copyright_ct_pk_seq LIMIT 1;")
    if (result == 0):
        result = db.access("CREATE SEQUENCE copyright_ct_pk_seq "
            "START WITH 1 "
            "INCREMENT BY 1 "
            "NO MAXVALUE "
            "NO MINVALUE "
            "CACHE 1;")
        if result != 0:
            print >> sys.stderr, "ERROR: Couldn't create copyright_ct_pk_seq."
            sys.exit(1)

        result = db.access("ALTER TABLE public.copyright_ct_pk_seq OWNER TO fossy;")
        if result != 0:
            print >> sys.stderr, "ERROR: Couldn't alter copyright_ct_pk_seq."
            sys.exit(1)
    else:
        print >> sys.stdout, 'WARNING: Table copyright_ct_pk_seq already exists. Skipping.'

    result = db.access("SELECT * FROM copyright_pfile_fk_seq LIMIT 1;")
    if (result == 0):
        result = db.access("CREATE SEQUENCE copyright_pfile_fk_seq "
            "START WITH 1 "
            "INCREMENT BY 1 "
            "NO MAXVALUE "
            "NO MINVALUE "
            "CACHE 1;")
        if result != 0:
            print >> sys.stderr, "ERROR: Couldn't create copyright_pfile_fk_seq."
            sys.exit(1)

        result = db.access("ALTER TABLE public.copyright_pfile_fk_seq OWNER TO fossy;")
        if result != 0:
            print >> sys.stderr, "ERROR: Couldn't alter copyright_pfile_fk_seq."
            sys.exit(1)
    else:
        print >> sys.stdout, 'WARNING: Table copyright_pfile_fk_seq already exists. Skipping.'

    result = db.access("SELECT * FROM copyright LIMIT 1;")
    if (result == 0):
        result = db.access("CREATE TABLE copyright ( "
            "ct_pk bigint DEFAULT nextval('copyright_ct_pk_seq'::regclass) NOT NULL, "
            "agent_fk bigint DEFAULT nextval('copyright_agent_fk_seq'::regclass) NOT NULL, "
            "pfile_fk bigint DEFAULT nextval('copyright_pfile_fk_seq'::regclass) NOT NULL, "
            "copy_startbyte integer, "
            "copy_endbyte integer);")
        if result != 0:
            print >> sys.stderr, "ERROR: Couldn't create license table."
            sys.exit(1)

        result = db.access("ALTER TABLE public.copyright OWNER TO fossy;")
        if result != 0:
            print >> sys.stderr, "ERROR: Couldn't alter copyright table."
            sys.exit(1)
    else:
        print >> sys.stdout, 'WARNING: Table copyright already exists. Skipping.'

if __name__ == '__main__':
