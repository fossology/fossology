#!/usr/bin/python -u

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
import os
import time
import re
import traceback
import cPickle as pickle
from optparse import OptionParser
import textwrap

try:
    import psyco
    psyco.full()
except:
    pass

import copyright_library as library
import libfosspython

# only read 2M of a file
READMAX = 200000

def main():
    usage  = """
%prog is used to automatically locate copyright statements, email addresses and URLs.

There are three (3) possible functions that can be performed:
 * MODEL CREATION    :: Create a model of copyright statements
                        using training data.
 * COMMAND LINE TEST :: Test a file from the command line.
 * AGENT TEST        :: Waits for commands from stdin.

+----------------+
| MODEL CREATION |
+----------------+
Due to the complex nature of copyright statements a simple regular expression will not suffice to locate the majority of copyright statements. We use a naive Bayes bi-gram model to locate copyright statements based on each bi-grams probability of being found in a copyright statement. This allows copyright unseen copyright statements to be classified correctly. This also allows use to simply maintain a set of training data instead of a complex set of regular expressions. This brings us to the need for the model creation phase. This phase creates the naive Bayes bi-gram model that will be used to locate copyright statements. Lets take a moment to look at the training data, because the format is a little strange.

1) Training data.
    The training data is stored in a text file where each line contains a single training example. Each training example is stored as follows.
    '''Each training example is wrapped with three quotes and the training example is escaped using the python repr() function.'''
2) Creating the model.
    To create a model the following command should be used.
        > %prog --model model --training examples
    Where `model' is the name of the model file being created, and `examples' is the file containing the training examples.

 ** Remember that a model file is required to locate copyright statements. 

+-------------------+
| COMMAND LINE TEST |
+-------------------+
To analyze a file from the command line you must first create a model, see MODEL CREATION.

To analyze files as parameters to %prog use the following command:
        > %prog --model model --analyze-from-command-line file1 ... fileN
    Where `model' is the saved model file and `file*' are the files to analyze.

A file containing the file paths to the file to be analyzed may be used instead of passing them over the command line. This requires that each line of the paths file contain the path to a single file to be analyzed. Use the following command to analyze a paths file.
        > %prog --model model --analyze-from-file paths_file
    Where `model' is the saved model file and `paths_file' is the file containing the paths to the files to be analyzed.

+------------+
| AGENT TEST |
+------------+
%prog has a special option to allow for scheduling with the Fossology
scheduler. When in default agent %prog will wait for a job id from the scheduler on stdin (see --runonpfiles to change the default behavior).

Use the --agent switch to place %prog into Fossology agent mode. There are four (4) options.

  --setup-database      Creates the tables for copyright analysis that the
                        fossology database needs.
  --drop                Drops the tables before creating them for copyright
                        analysis agent when used with --setup-database.
  --runonpfiles         Expect the scheduler to provide the pfiles on stdin.
                        Only available in agent mode.
  -i, --init            Creates a connection to the database and quits.

    """

    # Make sure our usage statement will fit in an 80 character wide terminal.
    usage = '\n'.join([textwrap.fill(line,79,subsequent_indent=re.findall('^\s*',line)[0]) for line in usage.splitlines()])
  
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
    optparser.add_option("--drop", action="store_true",
            help="Drops the tables before creating them for copyright analysis agent when used with --setup-database.")
    optparser.add_option("--agent", action="store_true",
            help="Starts up in agent mode. Files will be read from stdin.")
    optparser.add_option("--runonpfiles", action="store_true",
            help="Expect the scheduler to provide the pfiles on stdin. Only available in agent mode.")
    optparser.add_option("-i", "--init", action="store_true",
            help="Creates a connection to the database and quits.")
    optparser.add_option("-v", "--verbose", action="store_true",
            help="")
    optparser.add_option("--version", action="store_true",
            help="Print the version ids for the model and source.")

    (options, args) = optparser.parse_args()
    
    if options.init:
        db = None
        try:
            db = libfosspython.FossDB()
        except Exception, inst:
            print >> sys.stdout, 'ERROR: %s, in %s' % (inst[0], inst[1])
            return 1

        tr = table_check(db)
        if tr != 0:
            return tr

        return 0

    if options.setup_database:
        return(setup_database(options.drop))

    if not options.model:
        print >> sys.stdout, 'You must specify a model file for all phases of the algorithm.\n\n'
        optparser.print_usage()
        sys.exit(1)

    model = {}
    if options.training:
        training_data = [eval(line) for line in open(options.training).readlines()]
        model = library.create_model(training_data)
        pickle.dump(model, open(options.model,'w'))

    try:
        model = pickle.load(open(options.model))
    except:
        print >> sys.stdout, 'You must specify a training file to create a model.\n\n'
        optparser.print_usage()
        sys.exit(1)

    if options.version:
        print "Source hash: %s" % hex(abs(hash(open(sys.argv[0]).read())))
        print 'Model hash: %s' % (model['id'])
    
    if options.analyze_from_file:
        files = [line.rstrip() for line in open(options.analyze_from_file).readlines()]
        for file in files:
            text = open(file).read(READMAX)
            results = library.label_file(file,model,READMAX)
            print "%s :: " % (file)
            if len(results) == 0:
                print "No copyrights"
            for i in range(len(results)):
                print "\t[%d:%d:%s] %r" % (results[i][0], results[i][1], results[i][2], text[results[i][0]:results[i][1]])

    if options.analyze_from_command_line:
        files = args
        for file in files:
            text = open(file).read(READMAX)
            results = library.label_file(file,model,READMAX)
            print "%s :: " % (file)
            if len(results) == 0:
                print "No copyrights"
            for i in range(len(results)):
                print "\t[%d:%d:%s] %r" % (results[i][0], results[i][1], results[i][2], text[results[i][0]:results[i][1]])

    if options.agent:
         return(agent(model,options.runonpfiles))   

def agent(model,runonpfiles=False):
    try:
        db = None
        try:
            db = libfosspython.FossDB()
        except Exception, inst:
            print >> sys.stderr, 'FATAL: %s, in %s' % (inst[0], inst[1])
            return -1

        tr = table_check(db)
        if tr != 0:
            return tr

        # try to open the repo.
        if libfosspython.repOpen() != 1:
            print >> sys.stderr, 'Something is broken.\n\tCould not open Repo.\n\tTried to open "%s".' % (libfosspython.repGetRepPath())
            return -1

        # create a heartbeat thread so the scheduler doesn't kill the agent.
        hb = libfosspython.Heartbeat(30.0) # print a heartbeat every 30 seconds
        hb.start()
        
        # get out agent id from the database
        agent_pk = db.getAgentKey('copyright', '1.0 source_hash(%s) model_hash(%s)' % (hex(hash(open(sys.argv[0]).read())), hex(hash(str(model)))), 'copyright agent')

        
        if runonpfiles:
            # if the scheduler is going to hand us files.
            line = 'start'
            while line:
                line = line.strip()
                re_str = "pfile_pk=\"([0-9]+)\" pfilename=\"([0-9a-fA-F]+\.[0-9a-fA-F]+\.[0-9]+)\""
                if re.match(re_str, line):
                    (pfile, file) = re.findall(re_str, line)[0]
                    pfile = int(pfile)
                    if analyze(pfile_pk, file, agent_pk, model, db) != 0:
                        print >> sys.stdout, 'ERROR: Could not process file.\n\tupload_pk = %s, pfile_pk = %s, pfilename = %s' % (upload_pk, row['pfile_pk'], row['pfilename'])
                    else:
                        hb.increment()
                elif re.match("quit", line):
                    hb.heartbeat()
                    print "BYE."
                    break
                elif re.match("start", line):
                    print "OK"
                    hb.restart()
                else:
                    print >> sys.stdout, 'ERROR: Unknown command:\n\t"%s".' % line

                try:
                    line = sys.stdin.readline()
                except:
                    exceptionType, exceptionValue, exceptionTraceback = sys.exc_info()
                    p = '\t'.join(traceback.format_exception(exceptionType, exceptionValue, exceptionTraceback))
                    print >> sys.std.err, "ERROR: An error occurred in the main agent loop.\n\tThe current command is: '%s'.\n\tPlease consult the provided traceback.\n\t%s\n" % (line,p)
                    line = "quit"

        else:
            while True:
                print "OK"
                hb.restart()
                # get the upload_pk from stdin.
                upload_pk = -1
                try:
                    line = sys.stdin.readline().rstrip()
                    if not line:
                        print "BYE."
                        break
                    if len(line) > 0:
                        upload_pk = int(line)
                except ValueError:
                    print >> sys.stdout, 'ERROR: Provided upload_pk is not a number: %r' % line
                    continue

                sql = '''SELECT pfile_pk, pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size 
                    AS pfilename FROM (SELECT distinct(pfile_fk) AS PF FROM uploadtree WHERE 
                    upload_fk='%d' and (ufile_mode&x'3C000000'::int)=0) as SS left outer join 
                    copyright on (PF=pfile_fk and agent_fk='%d') inner join pfile on 
                    (PF=pfile_pk) WHERE ct_pk IS null''' % (upload_pk, agent_pk)
                
                if db.access(sql) != 1:
                    error = db.errmsg()
                    raise Exception('Could not select job queue for copyright analysis. Database said: "%s".\n\tsql=%s' % (error, sql))

                rows = db.getrows()

                for row in rows:
                    if analyze(row['pfile_pk'], row['pfilename'], agent_pk, model, db) != 0:
                        print >> sys.stdout, 'ERROR: Could not process file.\n\tupload_pk = %s, pfile_pk = %s, pfilename = %s' % (upload_pk, row['pfile_pk'], row['pfilename'])
                    else:
                        hb.increment()

                hb.heartbeat()
    
    except:
        exceptionType, exceptionValue, exceptionTraceback = sys.exc_info()
        p = '\t'.join(traceback.format_exception(exceptionType, exceptionValue, exceptionTraceback))
        print >> sys.stdout, "FATAL: An error occurred in the main agent loop. Please consult the provided traceback.\n\t%s\n" % p
        hb.stop()
        hb.join()
        libfosspython.repClose()
      #finally:
        # if we really get here and there was another exception we cant print
        # the correct error so just return.
        #return 1

    hb.stop()
    hb.join()
    libfosspython.repClose()
    
    return 0

def analyze(pfile_pk, filename, agent_pk, model, db):
    pfile = -1
    try:
        pfile = int(pfile_pk)
    except ValueError:
        print >> sys.stdout, 'ERROR: Provided pfile_pk is not a number: %r' % line
        return -1

    path = libfosspython.repMkPath('files', filename)
    if (not os.path.exists(path)):
        print >> sys.stdout, 'ERROR: File not found. path=%s' % (path)
        return -1
    offsets = library.label_file(path,model,READMAX)
    text = open(path).read(READMAX)
    if len(offsets) == 0:
        sql = """INSERT INTO copyright (agent_fk, pfile_fk, copy_startbyte, copy_endbyte, content, hash, type)
                 VALUES (%d, %d, NULL, NULL, NULL, NULL, 'statement')""" % (agent_pk, pfile)
        result = db.access(sql)
        if result != 0:
            print >> sys.stdout, "ERROR: DB Access error, returned %d.\nERROR: DB STATUS: %s\nERROR: DB ERRMSG: %s\nERROR: sql=%s" % (result, db.status(), db.errmsg(), sql)
            return -1
    else:
        for i in range(len(offsets)):
            str = text[offsets[i][0]:offsets[i][1]]
            if type(str) == type(u''): # we have a unicode object
                str = str.decode('ascii', 'ignore')
            pd = library.parsetext(str)
            str = re.escape(' '.join([token[1] for token in pd]))
            sql = """INSERT INTO copyright (agent_fk, pfile_fk, copy_startbyte, copy_endbyte, content, hash, type)
                     VALUES (%d, %d, %d, %d, E'%s', E'%s', '%s')""" % (agent_pk, pfile, offsets[i][0], offsets[i][1],
                        str, hex(abs(hash(str))),
                        offsets[i][2])
            result = db.access(sql)
            if result != 0:
                print >> sys.stdout, "ERROR: DB Access error, returned %d.\nERROR: DB STATUS: %s\nERROR: DB ERRMSG: %s\nERROR: sql=%s" % (result, db.status(), db.errmsg(), sql)
                return -1

    return 0

def table_check(db):
    sql = 'SELECT ct_pk FROM copyright LIMIT 1'
    if db.access(sql) != 1:
        error = db.errmsg()
        if error == 'relation "copyright" does not exist':
            print >> sys.stdout, 'WARNING: Could not find copyright table. Will try to setup automatically. If you continue to have trouble try using %s --setup-database' % sys.argv[0]
            return setup_database()

        print >> sys.stdout, 'ERROR: Could not select table copyright. Database said: "%s"\nERROR: sql=%s' % (error, sql)
        return -1
    return 0

def drop_database():
    db = None
    try:
        db = libfosspython.FossDB()
    except Exception, inst:
        print >> sys.stdout, 'ERROR: %s, in %s' % (inst[0], inst[1])
        sys.exit(-1)

    sql = "DROP TABLE copyright CASCADE"
    result = db.access(sql)
    if result != 0:
        error = db.errmsg()
        if error != 'table "copyright" does not exist':
            print >> sys.stdout, "ERROR: Could not drop copyright. Database said: '%s'\nERROR: sql=%s" % (error, sql)
    sql = "DROP SEQUENCE copyright_ct_pk_seq CASCADE"
    result = db.access(sql)
    if result != 0:
        error = db.errmsg()
        if error != 'sequence "copyright_ct_pk_seq" does not exist':
            print >> sys.stdout, "ERROR: Could not drop copyright_ct_pk_seq. Database said: '%s'\nERROR: sql=%s" % (error, sql)
    
    return 0

def setup_database(drop=False):
    db = None
    try:
        db = libfosspython.FossDB()
    except Exception, inst:
        print >> sys.stdout, 'ERROR: %s, in %s' % (inst[0], inst[1])
        sys.exit(1)

    if drop:
        drop_database()

    #
    exists = False
    
    sql = """CREATE SEQUENCE copyright_ct_pk_seq
             START WITH 1 INCREMENT BY 1 NO MAXVALUE NO MINVALUE
             CACHE 1"""
    result = db.access(sql)
    if result != 0:
        error = db.errmsg()
        if error != 'relation "copyright_ct_pk_seq" already exists':
            print >> sys.stdout, "ERROR: Could not create copyright_ct_pk_seq. Database said: '%s'\nERROR: sql=%s" % (error, sql)
            return -1
        else:
            exists = True
    
    if not exists:
        sql = "ALTER TABLE public.copyright_ct_pk_seq OWNER TO fossy"
        result = db.access(sql)
        if result != 0:
            error = db.errmsg()
            print >> sys.stdout, "ERROR: Could not alter copyright_ct_pk_seq. Database said: '%s'\nERROR: sql=%s" % (error, sql)
            return -1

    exists = False
    sql = """CREATE TABLE copyright (
             ct_pk bigint PRIMARY KEY DEFAULT nextval('copyright_ct_pk_seq'::regclass),
             agent_fk bigint NOT NULL,
             pfile_fk bigint NOT NULL,
             content text,
             hash text,
             type text CHECK (type in ('statement', 'email', 'url')),
             copy_startbyte integer,
             copy_endbyte integer)"""
    result = db.access(sql)
    if result != 0:
        error = db.errmsg()
        if error != 'relation "copyright" already exists':
            print >> sys.stdout, "ERROR: Could not create table copyright. Database said: '%s'\nERROR: sql=%s" % error
            return -1
        else:
            exists = True

    if not exists:
        sql = "CREATE INDEX copyright_pfile_fk_index ON copyright USING BTREE (pfile_fk)"
        result = db.access(sql)
        if result != 0:
            error = db.errmsg()
            print >> sys.stdout, "ERROR: Could not create index for pfile_fk. Database said: '%s'\nERROR: sql=%s" % (error, sql)
            return -1

        sql = "CREATE INDEX copyright_agent_fk_index ON copyright USING BTREE (agent_fk)"
        result = db.access(sql)
        if result != 0:
            error = db.errmsg()
            print >> sys.stdout, "ERROR: Could not create index for agent_fk. Database said: '%s'\nERROR: sql=%s" % (error, sql)
            return -1

        sql = "ALTER TABLE public.copyright OWNER TO fossy"
        result = db.access(sql)
        if result != 0:
            error = db.errmsg()
            print >> sys.stdout, "ERROR: Could not alter copyright. Database said: '%s'\nERROR: sql=%s" % (error, sql)
            return -1
        
        sql = "ALTER TABLE ONLY copyright ADD CONSTRAINT pfile_fk FOREIGN KEY (pfile_fk) REFERENCES pfile(pfile_pk)" 
        result = db.access(sql)
        if result != 0:
            error = db.errmsg()
            print >> sys.stdout, "ERROR: Could not alter copyright. Database said: '%s'\nERROR: sql=%s" % (error, sql)
            return -1
        
        sql = "ALTER TABLE ONLY copyright ADD CONSTRAINT agent_fk FOREIGN KEY (agent_fk) REFERENCES agent(agent_pk)"
        result = db.access(sql)
        if result != 0:
            error = db.errmsg()
            print >> sys.stdout, "ERROR: Could not alter copyright. Database said: '%s'\nERROR: sql=%s" % (error, sql)
            return -1

    return 0

if __name__ == '__main__':
    sys.exit(main())
