#!/usr/bin/python

import psyco
psyco.full()

import sys, os, time, library
import cPickle
from datetime import datetime
import re
import vector, database
from xml.sax.saxutils import escape
import htmlentitydefs
import traceback
from optparse import OptionParser
import maxent

# fix funky characters so we can print our data into a nice xml formatted
# document
def htmlentities(u):
    result = []
    for c in u:
        if ord(c) < 128:
		if ord(c) > 31:
            		result.append(c)
        else:
            result.append('&%s;' % htmlentitydefs.codepoint2name[ord(c)])
    return ''.join(result)

# converts crazy unicode stuff before converting funky characters
def escape2(str):
	s = str.decode('ascii','ignore')
	s2 = escape(s)
	s3 = htmlentities(s2)
	return s3

def main ():
    # Create a help message so Bob doesn't send me 50 emails asking how to use
    # this script.

    usage = "usage: %prog [options] -D database -T template_file -o output_dir -f test_files"
    parser = OptionParser(usage)
    parser.add_option("-D", "--database", type="string",
            help="If the -T flag is spesified then a database cache file will be saved at the location specified. Otherwise, loads a pre-cached database model")
    parser.add_option("-T", "--templates", type="string",
            help="Path to a \\n delimetated file with paths to license template files")
    parser.add_option("-o", "--output", type="string",
            help="Output directory")
    parser.add_option("-f", "--files", type="string",
            help="Path to a \\n delimetated file with paths to test files")
    parser.add_option("-d", "--debug", action="store_true",
            help="Turn debug output on")

    (options, args) = parser.parse_args()

    # load sentence model.
    sentence_model = maxent.MaxentModel()
    sentence_model.load('../models/SentenceModel.dat')

    debug_on = False
    if options.debug:
        debug_on = True

    if not options.output and options.files:
        print >> sys.stderr, 'Output directory not provided.'
        parser.print_usage()
        sys.exit(1)

    if options.output and not os.path.isdir(options.output):
        print >> sys.stderr, 'Output directory does not exist.'
        parser.print_usage()
        sys.exit(1)

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
    
    if not options.files:
        return

    links = ''
    
    argv = [line.rstrip() for line in open(options.files)]
    argv.sort()
    
    start = datetime.now()
    
    for i in xrange(len(argv)):
        try:
            f = argv[i]
            name = os.path.basename(f)
    
            print 'Processing %s...' % name
    
            # BOBG: this is where you should start
            # this is where everything happends.
            # look in database.py for more code...
            sentences,matches,unique_hits,cover,maximum,hits,score,fp = database.calculate_matches(DB,f,debug=debug_on,thresh=0.7)
            
            # create and xml file for the output
            links += '<a href=\"%s.xml\">%s</a>\n<br>\n' % (i,name)
            xml = '<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n'
            xml += '<?xml-stylesheet type=\"text/xsl\" href=\"default.xsl\"?>\n'
            xml += '<analysis>\n'
            xml += '<name>%s</name>\n' % escape2(name)
            xml += '<path>%s</path>\n' % escape2(f)
            xml += '<statistics>\n'
            links += '<span style="font-size:6pt">\n'
            for lic,scr in library.sortdictionary(score):
                xml += '<license>\n<name>%s</name>\n<rank>%2.1f</rank>\n</license>\n' % (escape2(lic),(scr*100.0))
                links += ' &nbsp;&nbsp; | &nbsp;&nbsp; %s (%2.1f%%) \n<br>\n' % (escape2(lic),(scr*100.0))
            links += '</span>\n'
            xml += '</statistics>\n'
            xml += '<breakdown>\n'
            for j in xrange(len(sentences)):
                s = sentences[j]
                xml += '<sentence>\n'
                xml += '<position>%s</position>\n' % (j)
                xml += '<text>%s</text>\n' % escape2(s)
                xml += '<matches>\n'
                for k in hits[j]:
                    xml += '<license>\n'
                    xml += '<rank>%1.2f</rank>\n' % (matches[j][k][1])
                    xml += '<name>%s</name>\n' % escape2(k)
                    if k=='Unknown':
                        xml += '<position>%s</position>\n' % (0)
                        xml += '<text>%s</text>\n' % escape2('Text not found in corpus.')
                    else:
                        xml += '<position>%s</position>\n' % (DB._to_position[matches[j][k][0]])
                        xml += '<text>%s</text>\n' % escape2(DB.sentences[matches[j][k][0]])
                    xml += '</license>\n'
                xml += '</matches>\n'
                xml += '</sentence>\n'
            xml += '</breakdown>\n'
            xml += '</analysis>\n'
            open('%s/%s.xml' % (options.output,i),'w').write(xml)
        except Exception, e:
            exceptionType, exceptionValue, exceptionTraceback = sys.exc_info()
            p = repr(traceback.format_exception(exceptionType, exceptionValue, exceptionTraceback))
            print p
    
    index = '<html><head><title>Report Index</title></head><body>%s</body></html>' %links
    
    open('%s/index.html' % (options.output), 'w').write(index)
    open('%s/default.xsl' % (options.output), 'w').write(open('default.xsl').read())
    
    end = datetime.now()
    print "Finished: ", end-start


if __name__ == "__main__":
    main()

