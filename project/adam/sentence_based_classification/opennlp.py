#!/usr/bin/python


import sys, os
import re

#PATH = '/home/adam/Work/lucense/'
PATH = os.getcwd()

def classpath():
    os.environ['CLASSPATH'] = PATH+'/opennlp/lib/'
    os.environ['CLASSPATH'] += ':'+PATH+'/opennlp/lib/jwnl-1.3.3.jar'
    os.environ['CLASSPATH'] += ':'+PATH+'/opennlp/lib/maxent-2.4.0.jar'
    os.environ['CLASSPATH'] += ':'+PATH+'/opennlp/lib/opennlp-tools-1.3.0.jar'
    os.environ['CLASSPATH'] += ':'+PATH+'/opennlp/lib/trove.jar'

classpath()

def SentenceDetector(text):
    fin,fout = os.popen2('java opennlp.tools.lang.english.SentenceDetector %s/opennlp/models/EnglishSD.bin.gz' % (PATH))
    fin.write(text)
    fin.close()
    
    lines = fout.read().split('\n')
    return lines

def Tokenizer(text):
    fin,fout = os.popen2('java opennlp.tools.lang.english.Tokenizer opennlp/models/EnglishTok.bin.gz')
    fin.write(text)
    fin.close()
    
    lines = fout.read().split('\n')
    return lines

def POSTagger(text):
    fin,fout = os.popen2('java opennlp.tools.lang.english.PosTagger -d opennlp/models/parser/tagdict opennlp/models/parser/tag.bin.gz')
    fin.write(text)
    fin.close()
    
    lines = fout.read().split('\n')
    return lines

def TreebankChunker(text):
    fin,fout = os.popen2('java opennlp.tools.lang.english.TreebankChunker opennlp/models/parser/chunk.bin.gz')
    fin.write(text)
    fin.close()
    
    lines = fout.read().split('\n')
    return lines
