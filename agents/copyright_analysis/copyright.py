#!/usr/bin/python

## 
## Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
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
            help="List of training data.")
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
    
    print "Source hash: %s" % hex(abs(hash(open(sys.argv[0]).read())))
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
        training_data = [eval(line) for line in open(options.training).readlines()]
        model = library.create_model(training_data)
        pickle.dump(model, open(options.model,'w'))

    try:
        model = pickle.load(open(options.model))
        print 'Model hash: %s' % (model['id'])
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

        agent_pk = db.getAgentKey('copyright', '1.0 source_hash(%s) model_hash(%s)' % (hex(hash(open(sys.argv[0]).read())), hex(hash(str(model)))), 'copyright agent')
        
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
                text = open(path).read()
                if len(offsets) == 0:
                    result = db.access("INSERT INTO copyright (agent_fk, pfile_fk, copy_startbyte, copy_endbyte, content, hash, type) "
                        "VALUES (%d, %d, NULL, NULL, NULL, NULL, 'statement');" % (agent_pk, pfile))
                    if result != 0:
                        print >> sys.stderr, "ERROR: DB Access error,\n%s" % db.status()
                else:
                    for i in range(len(offsets)):
                        result = db.access("INSERT INTO copyright (agent_fk, pfile_fk, copy_startbyte, copy_endbyte, content, hash, type) "
                            "VALUES (%d, %d, %d, %d, E'%s', E'%s', '%s');" % (agent_pk, pfile, offsets[i][0], offsets[i][1], re.escape(text[offsets[i][0]:offsets[i][1]]), hex(abs(hash(re.escape(text[offsets[i][0]:offsets[i][1]])))), offsets[i][2]))
                        if result != 0:
                            print >> sys.stderr, "ERROR: DB Access error,\n%s" % db.status()
                count += 1
                sys.stdout.write("OK\n")
                sys.stdout.flush()
                sys.stdout.write("ItemsProcessed %ld\n" % 1)
                sys.stdout.flush()
            elif re.match("quit", line):
                print "BYE."
                break
            elif re.match("start", line):
                sys.stdout.write("OK\n")
                sys.stdout.flush()
                count = 0

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

    result = db.access("CREATE SEQUENCE copyright_ct_pk_seq "
        "START WITH 1 "
        "INCREMENT BY 1 "
        "NO MAXVALUE "
        "NO MINVALUE "
        "CACHE 1;")
    if result != 0:
        print >> sys.stderr, "ERROR: Couldn't create copyright_agent_fk_seq."

    result = db.access("ALTER TABLE public.copyright_agent_fk_seq OWNER TO fossy;")
    if result != 0:
        print >> sys.stderr, "ERROR: Couldn't alter copyright_agent_fk_seq."

    result = db.access("CREATE TYPE copyright_type AS ENUM ('statement', 'email', 'url');")

    result = db.access("CREATE TABLE copyright ( "
        "ct_pk bigint DEFAULT nextval('copyright_ct_pk_seq'::regclass) NOT NULL, "
        "agent_fk bigint NOT NULL, "
        "pfile_fk bigint NOT NULL, "
        "content text, "
        "hash text, "
        "type copyright_type NOT NULL, "
        "copy_startbyte integer, "
        "copy_endbyte integer);")
    if result != 0:
        print >> sys.stderr, "ERROR: Couldn't create license table."

    result = db.access("ALTER TABLE public.copyright OWNER TO fossy;")
    if result != 0:
        print >> sys.stderr, "ERROR: Couldn't alter copyright table."

    result = db.access("ALTER TABLE ONLY copyright ADD CONSTRAINT "
            "copyright_pkey PRIMARY KEY (ct_pk);")
    if result != 0:
        print >> sys.stderr, "ERROR: Couldn't add constraint 'pfile' to copyright table."
    
    result = db.access("ALTER TABLE ONLY copyright ADD CONSTRAINT "
            "pfile_fk FOREIGN KEY (pfile_fk) REFERENCES pfile(pfile_pk);")
    if result != 0:
        print >> sys.stderr, "ERROR: Couldn't add constraint 'pfile' to copyright table."
    
    result = db.access("ALTER TABLE ONLY copyright ADD CONSTRAINT "
            "agent_fk FOREIGN KEY (agent_fk) REFERENCES agent(agent_pk);")
    if result != 0:
        print >> sys.stderr, "ERROR: Couldn't add constraint 'pfile' to copyright table."

if __name__ == '__main__':
