#!/usr/bin/python

import re
import sys
import library

def calc_bigram_prob(bigram_hash, word1, word2, word3, default = 0.0):
    p = bigram_hash.get('%s %s %s' % (word1, word2, word3),0.0)
    return p + default

def norm_bigram_hash(bigram_hash):
    n = sum([bigram_hash[k] for k in bigram_hash.keys()])
    for k in bigram_hash.keys():
        bigram_hash[k] = bigram_hash[k] / n
    return n

def create_bigram_hash(tokens, bigram_hash = {}):
    for i in range(1,len(tokens)-1):
        bigram = '%s %s %s' % (tokens[i-1], tokens[i], tokens[i+1])
        bigram_hash[bigram] = bigram_hash.get(bigram,0.0) + 1.0
    return bigram_hash

def len_matrix(matrix):
    return sum([sum(matrix[k].values()) for k in matrix.keys()])

def create_count_matrix(matrix, tokens):
    t = tokens
    for i in range(len(t)-1):
        matrix[t[i]] = matrix.get(tokens[i],{})
        matrix[t[i]][t[i+1]] = matrix[t[i]].get(t[i+1],0) + 1.0

def normalize_matrix(matrix):
    n = len_matrix(matrix)
    for k in matrix.keys():
        for j in matrix[k].keys():
            matrix[k][j] = matrix[k][j] / n
    return 1.0 / n

def calc_matrix_prob(matrix, word1, word2, default = 0):
    w1 = matrix.get(word1,{})
    if len(w1)>0:
        w2 = w1.get(word2,default)
        s = sum([w1[k] for k in w1.keys()])
        return w2
    else:
        return default

def summarize_word(word):
    w = re.sub('[A-Z]+','A',word)
    w = re.sub('[a-z]+','a',w)
    w = re.sub('[0-9]+','0',w)

    return 'XXX%sXXX' % w


def main():

    files = [line.rstrip() for line in open(sys.argv[1]).readlines()]

    bigram_hash = {}
    P_inside = {}
    P_outside = {}
    for file in files:
        text = open(file).read()
        stuff = library.parsetext(text)
        tokens = stuff['tokens']
        inside = False
        for t in tokens:
            if t == 'XXXstartXXX':
                inside = True
                continue
            if t == 'XXXendXXX':
                inside = False
                continue
            if inside:
                P_inside[t] = P_inside.get(t,0.0) + 1.0
            else:
                P_outside[t] = P_outside.get(t,0.0) + 1.0
    
        tokens.insert(0,'XXXdocstartXXX')
        tokens.insert(0,'XXXdocstartXXX')
        tokens.append('XXXdocendXXX')
        tokens.append('XXXdocendXXX')
    
        bigram_hash = create_bigram_hash(tokens,bigram_hash)
    
    norm = 1.0 / sum([P_inside[k] for k in P_inside.keys()])
    for k in P_inside.keys():
        P_inside[k] = norm*P_inside[k]
    norm = 1.0 / sum([P_outside[k] for k in P_outside.keys()])
    for k in P_outside.keys():
        P_outside[k] = norm*P_outside[k]
    norm = norm_bigram_hash(bigram_hash)
    
    for file in sys.argv[2:]:
        print "%s:" % file

        text = open(file).read()
        
        stuff = library.parsetext(text)
        tokens = stuff['tokens']
        n = len(tokens)
        tokens.insert(0,'XXXdocstartXXX')
        tokens.insert(0,'XXXdocstartXXX')
        tokens.append('XXXdocendXXX')
        tokens.append('XXXdocendXXX')
        
        starts = []
        ends = []
        for i in range(2,n+2):
            v = tokens[i-2]
            w = tokens[i-1]
            x = tokens[i+0]
            y = tokens[i+1]
            z = tokens[i+2]
            s = 'XXXstartXXX'
            e = 'XXXendXXX'
        
            P_v_w_x = calc_bigram_prob(bigram_hash, v, w, x, norm)
            P_v_w_s = calc_bigram_prob(bigram_hash, v, w, s, norm)
            P_e_y_z = calc_bigram_prob(bigram_hash, e, y, z, norm)
            P_x_y_z = calc_bigram_prob(bigram_hash, x, y, z, norm)
            if re.match('^[^A-Z]',x):
                P_e_y_z += 0.2*P_outside.get(x,0.0)
                P_x_y_z += 0.8*P_inside.get(x,0.0)
        
            if (x in ['the', 'and']):
                starts.append(False)
                ends.append(False)
                continue
        
            starts.append(P_v_w_s > P_v_w_x)
            ends.append(P_e_y_z > P_x_y_z)
        
        tokens = library.replace_placeholders(tokens,stuff)
        i = 0
        inside = False
        while (i < len(starts)):
            if starts[i] and not inside:
                print "\t... %s *" % " ".join(tokens[i:i+2]),
                inside = True
            if inside:
                print "%s" % tokens[i+2],
            if ends[i] and inside:
                print ""
                inside = False
            i += 1
    
if __name__ == '__main__':
    main()
