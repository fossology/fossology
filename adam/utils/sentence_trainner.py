#!/usr/bin/python

import re, sys, math
import unicodedata
from optparse import OptionParser
import parser
from maxent import MaxentModel

def train(files):
    model = parser.train_sentences(files,MaxentModel(),2,2)
    return model

def test(file,me):
    text = open(file).read()

    features = parser.features(text)
    sents = parser.sentences(features,me,2,2)
    byte_offsets = parser.sentence_byte_offsets(features,sents)

    n = len(byte_offsets)

    out = ''
    for i in range(n):
        out += '<SENTENCE>%s</SENTENCE>' % (text[byte_offsets[i][0]:byte_offsets[i][1]])

    out = re.sub('<SENTENCE></SENTENCE>','',out)

    print out

def main():
    usage = "usage: %prog [options] files"
    oparser = OptionParser(usage)
    oparser.add_option("-m", "--mode", type="string",
            help="test or train")
    oparser.add_option("-f", "--model_file", type="string",
            help="Model file to read/write.")
    oparser.add_option("-d", "--debug", action="store_true",
            help="Turn debug output on")

    (options, args) = oparser.parse_args()

    if options.mode == 'train':
        me = train(args)
        if options.model_file:
            me.save(options.model_file)
    if options.mode == 'test':
        if not options.model_file:
            print >> sys.stderr, 'Model file not provided!'
            parser.print_usage()
            sys.exit(1)
        me = MaxentModel()
        me.load(options.model_file)
        test(args[0],me)

if __name__ == "__main__":
    main()


