import library
import lucene
import vector
import os
import math
import cPickle as pickle
from datetime import datetime
import random

use_fast_vector = True

class Database():
    length = 0 # number of sentences in the database
    vectors = [] # holds our vectors in a list
    sentences = [] # holds the text of the sentence from the template file
    _to_file = [] # a lookup table from sentence_id->template_file
    _to_position = [] # a lookup table from sentence_id->position in template file
    files = [] # file names
    analyzer = None # the lucene analyzer we are using
    keywords = [] # a list of stemmed words to look for
    fingerprints = {} # file to vector dictionary. the vectors hold the sentences that occured in the template file
    binary_lookup = {} # a lookup table for 'word'->sentences.
    leaders = []

    def __init__(self,files,analyzer,debug=False):
        # files is a list of files to use as templates
        # analyzer is the analyzer to use to stem/parse words from a file
        # if debug is True then you get text pooped to the screen:)

        self.files = files[:]
        n = len(self.files)

        self.analyzer = analyzer

        for i in xrange(n):
            if debug:
                print "Starting on file %s of %s..." % (i+1,n)
            f = self.files[i]
            name = os.path.basename(self.files[i])

            # determine if the file is an xml/html file. If so then remove tags
            if library.determinefiletype(f) == 'text/html':
                text = library.htmltotext(open(f).read())
            else:
                text = open(f).read()

            fp = [] 
            # fp holds vectors of similar sentences to template files. We can
            # then take dot products between templates files and target files.

            sentences = library.sentences(text)
            # sentences is a list of sentences that makeup the template
            for j in xrange(len(sentences)):
                text_array = library.tokensFromAnalysis(sentences[j],self.analyzer)
                # text_array is a list of processed words in order from the
                # sentence.

                # if the text in the sentence is below 4 words then insert it
                # at the beginning of the next sentence.
                if len(text_array)<4 and j<len(sentences)-1:
                    sentences[j+1] = "%s %s" % (sentences[j],sentences[j+1])
                    sentences[j] = ''
                    text_array = []
                    continue

                # add everything to the database
                self.sentences.append(sentences[j])
                fp.append(len(self.sentences)-1)
                self.vectors.append(vector.Vector(text_array,fast=use_fast_vector))
                for k in self.vectors[-1].text_count.keys():
                    self.binary_lookup[k] = self.binary_lookup.get(k,[])
                    self.binary_lookup[k].append(len(self.sentences)-1)
                self._to_file.append(name)
                self._to_position.append(j)
                self.length += 1
            # add the list of sentence ids to a vector and stick it into the
            # fingerprint dictionary
            self.fingerprints[name] = vector.Vector(fp,fast=use_fast_vector)

        # Add an empty vector for the Unknown sentences. We use an empty vector
        # so we dont match any existing sentences, which would be bad.
        self.fingerprints['Unknown'] = vector.Vector([],fast=use_fast_vector)
        
        # stem the list of words that define a license. This allows us to do a
        # quick lookup to see if a sentence has a license keyword.
        self.keywords = library.tokensFromAnalysis(' '.join(library.get_keywords()),self.analyzer)

        # calculate leaders
        all = range(self.length)
        l = [all.pop(all.index(i)) for i in random.sample(all,int(math.sqrt(self.length)))]
        self.leaders = [[i,[i]] for i in l]
        for i in all:
            m = 0.0
            k = 0
            for j in xrange(len(l)):
                dot = self.vectors[l[j]].dot(self.vectors[i])
                if dot>m:
                    m = dot
                    k = j
            self.leaders[k][1].append(i)

    # This method creates a copy of a database object.
    def copy(self):
        poop = Database([],None)
        poop.length = self.length
        poop.vectors = self.vectors[:]
        poop.sentences = self.sentences[:]
        poop._to_file = self._to_file[:]
        poop._to_position = self._to_position[:]
        poop.files = self.files[:]
        poop.analyzer = self.analyzer
        poop.fingerprints = self.fingerprints.copy()
        poop.binary_lookup = self.binary_lookup.copy()
        poop.keywords = self.keywords[:]
        poop.leaders = self.leaders[:]

        return poop

    # used for pickling. NOTICE that the analyzer is not saved!!!
    def __getstate__(self):
        return (self.length, self.vectors[:], self.sentences[:], self._to_file[:], self._to_position[:], self.files[:], self.fingerprints.copy(), self.binary_lookup.copy(), self.keywords[:], self.leaders[:])

    # used to unpickle a database object. analyzer is set to None!!!
    def __setstate__(self, state):
        self.length = state[0]
        self.vectors = state[1][:]
        self.sentences = state[2][:]
        self._to_file = state[3][:]
        self._to_position = state[4][:]
        self.files = state[5][:]
        self.fingerprints = state[6].copy()
        self.binary_lookup = state[7].copy()
        self.keywords = state[8][:]
        self.leaders = state[9][:]

# this function does all the work. A lot happeneds in here.
def calculate_matches(db,filename,thresh = 0.9,debug = False):
    # db is the database object.
    # filename is filename of the target file.
    # thresh is a threshold for matching sentences
    # if debug is set to true then you get lots of text pooped to the screen

    if debug:
        tic = datetime.now()
        print "Starting on %s ..." % (filename)
    # determine if we have an xml/html file. If so remove tags
    if library.determinefiletype(filename) == 'text/html':
        text = library.htmltotext(open(filename).read())
    else:
        text = open(filename).read()

    # split the file into sentneces
    sentences = library.sentences(text)
    
    # matches is a list of dictionaries, one for each sentence.
    matches = []
    # lc_sent is a list of indicators. If the indicator is True then this has a
    # license like word in it.
    lc_sent = []

    # a dictionary that holds all the unique template files we found matches in
    unique_hits = {}

    for j in xrange(len(sentences)):

        text_array = library.tokensFromAnalysis(sentences[j],db.analyzer)
        if len(text_array)<4 and j<len(sentences)-1:
            sentences[j+1] = "%s %s" % (sentences[j],sentences[j+1])
            sentences[j] = ''
            text_array = []
        matches.append({})
        lc_sent.append(False)
        if sum([(token in text_array) for token in db.keywords])>0:
            lc_sent[-1] = True
        v = vector.Vector(text_array,fast=use_fast_vector)
        
        # fast binary dot product. 
        # un = list(set(text_array))
        # b = {}
        # for aa in xrange(len(un)):
        #     a = un[aa]
        #     for c in db.binary_lookup.get(a,[]):
        #         b[c] = b.get(c,0.0) + 1.0
        # for a in b.keys():
        #     b[a] = b.get(a,0.0)/math.sqrt(len(un)*len(db.vectors[a].text_count.keys()))
        # for index,value in library.sortdictionary(b):
        #     if value<thresh:
        #         break
        #     matches[-1][db._to_file[index]] = (index,value)
        #     unique_hits[db._to_file[index]] = unique_hits.get(db._to_file[index],0.0)+1.0
        # normal dot product
        # for i in b:
        # for i in xrange(db.length):
        #     dot = v.dot(db.vectors[i])
        #     if dot>thresh:
        #         matches[-1][db._to_file[i]] = (i,dot)
        #         unique_hits[db._to_file[i]] = unique_hits.get(db._to_file[i],0.0)+1.0
        
        m = 0.0
        k = 0
        for i in xrange(len(db.leaders)):
            l = db.leaders[i][0]
            dot = v.dot(db.vectors[l])
            if dot>m:
                m = dot
                k = i
                
        #for i in xrange(db.length):
        for i in db.leaders[k][1]:
            dot = v.dot(db.vectors[i])
            if dot>thresh:
                matches[-1][db._to_file[i]] = (i,dot)
                unique_hits[db._to_file[i]] = unique_hits.get(db._to_file[i],0.0)+1.0

    
    # this is where we determine which templates are the best matches. For each
    # template we want to know how many continuous sentences we matched.
    cover = {}
    for k in unique_hits.keys():
        cover[k] = [0.0 for i in range(len(matches))]
        for i in range(len(matches)):
            if matches[i].get(k,False):
                # use similarity to determine longest match instead of any
                # matching sentence in the template
                cover[k][i] = cover[k][i-1]+matches[i][k][1]+(0.000001*unique_hits.get(k,0.0))
                # add one if we had a matching sentence
                # cover[k][i] = cover[k][i-1]+1.0+(0.000001*unique_hits.get(k,0.0))


    # determine the max continuous match for each sentence
    maximum = [0.0 for i in range(len(matches))]
    for i in range(len(matches)):
        for k in unique_hits.keys():
            maximum[i] = max([maximum[i],cover[k][i]])

    # create a vector of matches sentences for each template.
    fp = {}
    for k in unique_hits.keys():
        l = {}
        for i in xrange(len(matches)):
            if matches[i].get(k,False):
                # use similarity in dot product
                l[matches[i].get(k)[0]] = matches[i].get(k)[1]
                # use a 1 for a binary dot product
                #l[matches[i].get(k)[0]] = 1
        fp[k] = vector.Vector(l,fast=use_fast_vector)

    # hits is going to hold the template file that matched each sentence
    hits = []
    for i in xrange(len(matches)):
        hits.append([])
        if len(matches[i])==0:
            # if we didn't find a match for the sentence then we should check
            # to see if it matched any license keywords. If so then we should
            # mark it as an Unknown license.
            if lc_sent[i]:
                matches[i]['Unknown'] = (-1, 1.0)
                hits[i].append('Unknown')
            continue
        # choose the max match based on continuous sentences matched
        for k in matches[i].keys():
            if maximum[i] == cover[k][i]:
                hits[i].append(k)
        # to break ties we will take the dot product between the target vector
        # and the template vector and use the largest.
        m = {}
        for j in xrange(len(hits[i])):
            k = hits[i][j]
            t = fp[k].dot(db.fingerprints[k])
            m[t] = m.get(t,[])
            m[t].append(k)

        hits[i] = m[max(m)]

    # Scoring is done here...
    # hits holds a list of licenses that best matched a sentence. hits[i] is a
    # list holding the best matches.
    # db.fingerprints[k] is a vector that holds the sentences ids so we can
    # compare the similarity of template and a license. k is the template name
    # fp[k] is the vector that holds the sentence ids for license k that
    # matched. To determine the similarity between the targets matched
    # sentences againest a template you can do:
    # fp[k].dot(db.fingerprints[k])
    # unique_hits is a set of licenses that had sentences within __thresh__(0.7
    # by default) similarity.

    M = {}
    N = {}
    S = {}
    C = {}
    for i in xrange(len(hits)):
        for j in xrange(len(hits[i])):
            M[hits[i][j]] = M.get(hits[i][j],0.0) + 1.0
            N[hits[i][j]] = float(len(db.fingerprints[hits[i][j]].text_array))
            S[hits[i][j]] = S.get(hits[i][j],0.0) + matches[i][hits[i][j]][1]
    N['Unknown'] = float(len(sentences))

    score = {}

    if debug:
        print M
        print N
        print S

    for k in M:
        score[k] = (M[k]/N[k] * S[k]/M[k])

    if debug:
        print "Finished %s containing %s sentence in %s seconds." % (filename,len(sentences), datetime.now()-tic)
    
    return sentences,matches,unique_hits,cover,maximum,hits,score,fp

def save(db,path):
    # Pickles the database.

    pickle.dump(db,open(path,'w'))

def load(path,analyzer):
    # Unpickles a database object. Must provide the same analyzer as the
    # database was created with. This is a sticky point.

    # TODO: remove dependency on lucene snowball analyzer.

    db = pickle.load(open(path))
    db.analyzer = analyzer
    
    return db
