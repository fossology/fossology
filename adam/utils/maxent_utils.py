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

import parser

import re, unicodedata
from maxent import MaxentModel
from Stemmer import Stemmer
import math
import stopwords

def get_context(features,index,left,right):
    n = len(features)
    a = max(0,index-left)
    b = min(n,index+right+1)
    window = left+right+1
    d = features[a:b]
    if len(d)<window:
        if a == 0:
            p = 0
        else:
            p = window
        while len(d)<window:
            d.insert(p,{'token':'__BLANK__'})
    context = []
    for i in xrange(window):
        for k in d[i]:
            context.append('%s_%d=%r' % (k,i-left,d[i][k]))
    return context

def sentences(features,model,left,right):
    sents = []
    n = len(features)
    for i in xrange(n):
        if not features[i].get('word',True) and not features[i].get('white',True):
            E = model.eval(get_context(features,i,left,right),'E')
            if E>0.5:
                sents.append(i)
    return sents

def train_sentences(files,model,left,right):
    file_features = {}
    file_sentences = {}
    for file in files:
        text = open(file).read()

        sentences = parser.RE_SENTENCE.findall(text)
        for i in range(1, len(sentences)):
            extra = parser.RE_START_NONWORD.match(sentences[i])
            if extra:
                sentences[i-1] = "%s%s" % (sentences[i-1],sentences[i][extra.start():extra.end()])
                sentences[i] = sentences[i][extra.end():]
        if len(sentences)>0:
            file_features[file] = []
            file_sentences[file] = []
        for s in sentences:
            f = parser.features(''.join(s))
            file_sentences[file].append((len(f)-1)+(len(file_features[file])))
            file_features[file].extend(f)

    context_dict = {}

    for file in files:
        n = len(file_features[file])
        for i in xrange(n):
            if not file_features[file][i].get('word',True) and not file_features[file][i].get('white',True):
                context = get_context(file_features[file],i,left,right)
                label = 'I'
                if i in file_sentences[file]:
                    label = 'E'
                key = ' '.join(context)+' '+label
                context_dict[key] = context_dict.get(key,[context,label,0])
                context_dict[key][2] += 1
                if context_dict.get(' '.join(context)+' I',False) and context_dict.get(' '.join(context)+' E',False):
                    print "Conflicting features in file %s with context:\n\n%s" % (file,context)


    model.begin_add_event()
    for k in context_dict:
        model.add_event(context_dict[k][0],context_dict[k][1],context_dict[k][2])
    model.end_add_event()
    model.train(1000, 'lbfgs')

    correct = 0
    for k in context_dict:
        E = model.eval(context_dict[k][0],'E')
        if (E>0.5 and context_dict[k][1] == 'E') or (E<0.5 and context_dict[k][1] == 'I'):
            correct += 1
        else:
            print "Missed: %s" % k

    print "Classification was %d/%d." % (correct, len(context_dict))

    return model

def sentence_word_arrays(features,sents):
    pass

def sentence_stem_arrays(features,sents):
    n = len(features)
    stems = [[]]
    for i in xrange(n):
        t = features[i]['stem']
        if len(t)>0:
            stems[-1].append(t)
        if i in sents:
            stems.append([])

    return stems

def sentence_byte_offsets(features,sents):
    n = len(features)
    byte_offsets = [[0,0]]
    for i in xrange(n):
        byte_offsets[-1][1] += features[i]['length']
        if i in sents:
            byte_offsets.append([byte_offsets[-1][1],byte_offsets[-1][1]])

    return byte_offsets

