#!/usr/bin/python

import pickle, math, parser, license_test_model, re, vector

RE_LS = re.compile(r'<LICENSE_SECTION>(?P<text>.*?)</LICENSE_SECTION>',re.DOTALL)

def get_score(features,idf):
    score = []
    for i in xrange(len(features)):
        lp = features[i].inner(idf)
        score.append(lp)
    return score

idf = {}
D = 0.0
files = [file.strip() for file in open('training.txt').readlines()]

print "Finding words..."
for file in files:
    text = unicode(open(file).read(64000),errors='ignore')
    sections = ' '.join(RE_LS.findall(text))
    stems = parser.stemmed_words(sections)
    set_stems = set(stems)
    if len(set_stems)>0:
        D += 1.0
    for w in set_stems:
        idf[w] = idf.get(w,0.0) + 1.0
for w in idf:
    idf[w] = math.log(D/idf[w])
for w in idf:
    idf[w] = 0.0

words = idf.keys()
words.sort()
idf = vector.Vector(idf)

labels = []
features = []
print "Finding features..."
for file in files:
    text = unicode(open(file).read(64000),errors='ignore')
    s, l = license_test_model.labeled_stems(text)
    f = license_test_model.features(s,3,3)
    labels.extend(l)
    features.extend(f)
    
n = len(features)
for i in xrange(n):
    d = [v for k,v in features[i].items()]
    features[i] = vector.Vector(d)

print "Searching for word weights..."

old_correct = -1
new_correct = 0

while (old_correct != new_correct):
    word_scores = []
    w = len(words)
    for i in xrange(w):
        word_scores.append(0.0)
        idf.set(words[i],idf.get(words[i],0.0)+0.1)
        scores = get_score(features,idf)
        m = 0.0
        for j in xrange(n):
            if labels[j]==-1:
                m = max([m,scores[j]])
        for j in xrange(n):
            if (scores[j]>=m and labels[j]==1) or (scores[j]<m and labels[j]==-1):
                word_scores[i] += 1.0
        idf.set(words[i],idf.get(words[i],0.0)-0.1)
        print '%s: %s/%s.' % (words[i], word_scores[i],n)
    old_correct = new_correct
    new_correct = max(word_scores)
    idf.set(words[word_scores.index(new_correct)],idf.get(words[word_scores.index(new_correct)],0.0)+0.1)
    print '!'
    print new_correct
