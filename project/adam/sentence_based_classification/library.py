#!/usr/bin/python

##
## library.py
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

import re
import math
from operator import itemgetter
import os
import htmllib
import formatter
import cStringIO
import cPickle
from maxent import MaxentModel

def POSTagger(sentences):
    model = './postagger/tagger.model'
    tag_dict = cPickle.load(open(model + '.tagdict'))
    me = MaxentModel()
    me.load(model)
    tagger = maxentpy.postagger.PosTagger(me, tag_dict, None)
    tags = []
    words = []
    for i in xrange(len(sentences)):
        word = sentences[i].split()
        tag = tagger.tag_sentence(word, 5)
        tags.append(tag)
        words.append(word)
    return words,tags

def determinefiletype(filename):
    return os.popen('file --mime-type \"%s\"' % filename,'r').read().strip('\n')

def htmltotext(html):
    html = re.sub('\<\!--','',html)
    html = re.sub('--\>','',html)
    f = cStringIO.StringIO()
    z = formatter.AbstractFormatter(formatter.DumbWriter(f))
    p = htmllib.HTMLParser(z)
    try:
        p.feed((html))
    except:
        return html
    p.close()
    sret = f.getvalue()
    sret = re.sub('\[[0-9]+\]','',sret)
    f.close()
    return sret

def sortdictionary(a,reverse=True):
    b = sorted(a.iteritems(), key=itemgetter(1), reverse=reverse)
    return b

def argmax(obj,pair=False):
    value = 0.0
    index = 0
    if type(obj)==type({}):
        keys = obj.keys()
        value = obj[keys[0]]
        index = keys[0]
        for i in xrange(len(keys)):
            if value<obj[keys[i]]:
                value = obj[keys[i]]
                index = keys[i]
    elif type(obj) == type([]):
        value = obj[0]
        index = 0
        for i in range(len(obj)):
            if value<obj[i]:
                value = obj[i]
                index = i
    if pair:
        return index,value
    else:
        return index

def argmin(obj,pair=False):
    value = 0.0
    index = 0
    if type(obj)==type({}):
        keys = obj.keys()
        value = obj[keys[0]]
        index = keys[0]
        for i in xrange(len(keys)):
            if value>obj[keys[i]]:
                value = obj[keys[i]]
                index = keys[i]
    elif type(obj) == type([]):
        value = obj[0]
        index = 0
        for i in range(len(obj)):
            if value>obj[i]:
                value = obj[i]
                index = i
    if pair:
        return index,value
    else:
        return index

def get_keywords():
    # keywords list {{{
    keywords = ['license','copyright','rights','reserved','source','rights','redistributed','reproduction','distribution','agreement','distributed','contributor','contributors','contributions','licensed','patent','patents','patented','licensable','infringed','infringe','contribution','terms','conditions','warranties','warranty','merchantability','licensees','liability','obligations','restrictions','author','authors','licensee','restricted','limitation','modified','derivative','copyrighted','legal','licensor','licenses','government','arbitration','advertising','endorse','promote','redistributions','redistribution','distribute','commercial','modifications','derived','attribution','definitions','modification','trademarks','trademark','trademarked','distinguishing','nuclear','governing','law','laws','licensors','copyleft','compliance','unenforceable','distributor','licensing','verbatim','redistribute','unmodified','provisions','misrepresented','herewith','hereunder','freedom','cooperation','sublicensing','noncommercially','distributions','infringements','contributing','nonexclusive','notice','distributing','fee',]
    # }}}
    return keywords

# def sentences(document):
#     t = document.decode('ascii','ignore').encode('ascii','ignore')
#     t = re.sub('\r',' ',t)
#     t = re.sub('\n',' (SLASH_RETURN) ',t)
#     t = re.sub(r' (SLASH_RETURN) (?P<white>\W*) (SLASH_RETURN) ',' (SLASH_RETURN) \g<white> (SLASH_RETURN) (ADDED_THIS). ',t)
# 
#     a = opennlp.SentenceDetector(t)
# 
#     b = []
# 
#     # Fix, append small sentences to next sentence.
#     for i in xrange(len(a)):
#         #for aa in a:
#         aa = a[i]
#         aa = re.sub(r'\(ADDED_THIS\). ','',aa)
#         aa = re.sub(r' *\(SLASH_RETURN\) *','\n',aa)
#         if len(aa)==0:
#             continue
#         if len(b)>0 and len(re.split('\ ',b[-1]))<3:
#             b[-1] = b[-1]+' '+aa
#         else:
#             b.append(aa)
# 
#     return b

# def tokensFromAnalysis(text,a=None):
#     if not a:
#         a = lucene.StandardAnalyzer()
#     b = [token.termText().encode('ascii','ignore') for token in a.tokenStream("contents", lucene.StringReader(text))]
#     return [t for t in b if len(t)>0]
