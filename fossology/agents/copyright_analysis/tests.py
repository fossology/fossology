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

import copyright_library as library
import time
import random
from optparse import OptionParser
import sys

def test1():
    """
    This test determines if the naive Bayes classifier can correctly classify
    data that it has already seen.

    Please note that naive Bayes is a linear classifier so there may be data
    that it will not be able to correctly classify even though it has been
    trained on the same data.

    Returns a tuple giving the number of test passed, the number of
    tests failed and a string holding all log messages, i.e. 
        (3, 4, 'Failed Test [1]: Divide by 0.').
    """

    test = 1
    trainingdata = [
        '''A B <s>C D E F</s> G H I J''',
        '''G H C E F D A B I J''',
        '''I J C F D E G H A B''',
        '''a b <s>C D E f</s> I J G H''',
        ]
    testdata = [
        ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'],
        ['G', 'H', 'C', 'E', 'F', 'D', 'A', 'B', 'I', 'J'],
        ['I', 'J', 'C', 'F', 'D', 'E', 'G', 'H', 'A', 'B'],
        ['a', 'b', 'C', 'D', 'E', 'f', 'I', 'J', 'G', 'H'],
        ]
    correct_output = [
            ['O', 'O', 'B', 'I', 'I', 'I', 'O', 'O', 'O', 'O'],
            ['O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O'],
            ['O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O'],
            ['O', 'O', 'B', 'I', 'I', 'I', 'O', 'O', 'O', 'O'],
        ]
    model = library.create_model(trainingdata)
    
    n = len(testdata)
    passed = 0
    log = "Test%d started at %s.\n" % (test, time.ctime())
    for i in range(n):
        try:
            out = library.label_nb(model['P(F|C)'], testdata[i])
            if len(out) != len(correct_output[i]):
                log += "Test%d [%d] Failed.\n" % (tet, i)
                log += "\tOutput from library.label_nb() was the incorrect length.\n"
                log += "\tGot '%s' instead of '%s'.\n" % (str(out), 
                        str(correct_output[i]))
                continue
            if out != correct_output[i]:
                log += "Test%d [%d] Failed.\n" % (test, i)
                log += "\tOutput from library.label_nb() was incorrect.\n"
                log += "\tGot '%s' instead of '%s'.\n" % (str(out), 
                        str(correct_output[i]))
                continue
        except Exception, e:
            log += "Test%d [%d] Failed.\n" % (test, i)
            log += "\tRecieved the following exception:\n"
            exceptionType, exceptionValue, exceptionTraceback = sys.exc_info()
            p = '\t'.join(traceback.format_exception(exceptionType, 
                exceptionValue, exceptionTraceback))
            log += "\t%s\n" % p
            continue
        passed += 1

    log += "Test%d finished at %s.\n" % (test, time.ctime())
    log += "Test%d passed %d tests out of %d.\n" % (test, passed, n)

    return (passed, n, log)

def test2():
    """
    This test determines if the naive Bayes classifier can correctly classify
    data that it has not seen before.

    This test will fail if our algorithm for smoothing tokens that we have
    not seen yet is broken. Since 'C' is the trigger for the start of a
    statement the probability of 'C unknown' should be very high.

    Returns a tuple giving the number of test passed, the number of
    tests failed and a string holding all log messages, i.e. 
        (3, 4, 'Failed Test[1]: Divide by 0.').
    """
    
    test = 2
    trainingdata = [
        '''A B <s>C D E F</s> G H I J''',
        '''G H <s>C E F D</s> A B I J''',
        '''I J <s>C F D E</s> G H A B''',
        '''a b <s>C D E f</s> I J G H''',
        ]
    testdata = [
        ['A', 'B', 'C', 'x', 'E', 'F', 'G', 'H', 'I', 'J'],
        ['G', 'H', 'C', 'y', 'F', 'D', 'A', 'B', 'I', 'J'],
        ['I', 'J', 'C', 'D', 'z', 'E', 'G', 'H', 'A', 'B'],
        ['a', 'b', 'C', 'D', 'E', 'w', 'I', 'J', 'G', 'H'],
        ]
    correct_output = [
            ['O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O'],
            ['O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O'],
            ['O', 'O', 'B', 'I', 'I', 'I', 'O', 'O', 'O', 'O'],
            ['O', 'O', 'B', 'I', 'I', 'I', 'O', 'O', 'O', 'O'],
        ]
    model = library.create_model(trainingdata)
    
    n = len(testdata)
    passed = 0
    log = "Test%d started at %s.\n" % (test, time.ctime())
    for i in range(n):
        try:
            out = library.label_nb(model['P(F|C)'], testdata[i])
            if len(out) != len(correct_output[i]):
                log += "Test%d [%d] Failed.\n" % (tet, i)
                log += "\tOutput from library.label_nb() was the incorrect length.\n"
                log += "\tGot '%s' instead of '%s'.\n" % (str(out), 
                        str(correct_output[i]))
                continue
            if out != correct_output[i]:
                log += "Test%d [%d] Failed.\n" % (test, i)
                log += "\tOutput from library.label_nb() was incorrect.\n"
                log += "\tGot '%s' instead of '%s'.\n" % (str(out), 
                        str(correct_output[i]))
                continue
        except Exception, e:
            log += "Test%d [%d] Failed.\n" % (test, i)
            log += "\tRecieved the following exception:\n"
            exceptionType, exceptionValue, exceptionTraceback = sys.exc_info()
            p = '\t'.join(traceback.format_exception(exceptionType, 
                exceptionValue, exceptionTraceback))
            log += "\t%s\n" % p
            continue
        passed += 1

    log += "Test%d finished at %s.\n" % (test, time.ctime())
    log += "Test%d passed %d tests out of %d.\n" % (test, passed, n)

    return (passed, n, log)

def crossvalidation(data_file, folds=10):
    """
    Performs cross validation on set of data and returns the results.

    This is a randomized algorithms so you should NOT use if to 
    regression testing.

    data_file should be a file with each line containing a triple quoted 
    canonical string representation, i.e. call repr on the string.
    The text to be hi-lighted should be wrapped in <s>...</s> tags.

    Outputs a dictionary that holds specific statistics about the performance
    of the classifier.
    """
    
    if folds <= 1:
        raise Exception("Number of folds is too small. A value greater than 1 is required.")

    training_data = [eval(line) for line in open(data_file).readlines()]
    N = len(training_data)
    if N < folds:
        raise Exception("Number of folds is greater than number of data points.")

    # The approximate number of data items in each fold
    n = int(round(N/float(folds)))

    # shuffle the training data so we dont have any funky correlation issues
    # with its ordering.
    random.shuffle(training_data)

    parsed_data = [library.parsetext(text) for text in training_data]
    tokens = [[parsed_data[i]['tokens'][j][0] for j in xrange(len(parsed_data[i]['tokens']))] for i in xrange(N)]
    bio_data = [library.tokens_to_BIO(tokens[i]) for i in xrange(N)]

    fold_index = []
    for i in range(folds):
        fold_index.append(range(i*n,min([n+n*i,N])))

    accuracy = 0.0
    B_count = 0
    B_correct = 0
    I_count = 0
    I_correct = 0
    O_count = 0
    O_correct = 0

    for i in range(folds):
        testing = fold_index[i]
        training = list(set(testing).symmetric_difference(set(range(N))))

        testing_data = [bio_data[d] for d in testing]
        training_data = [bio_data[d] for d in training]

        PFC = library.train_nb(training_data)

        passed = 0
        for test in testing_data:
            tokens = test[0]
            labels = test[1]

            out = library.label_nb(PFC, tokens)

            if out == labels:
                passed += 1

            for l in range(len(labels)):
                if labels[l] == 'B':
                    B_count += 1
                    if out[l] == 'B':
                        B_correct += 1
                elif labels[l] == 'I':
                    I_count += 1
                    if out[l] == 'I':
                        I_correct += 1
                elif labels[l] == 'O':
                    O_count += 1
                    if out[l] == 'O':
                        O_correct += 1
        
        accuracy += passed/float(len(testing))

    raw_accuracy = accuracy/float(folds)
    B_accuracy = float(B_correct)/float(B_count)
    I_accuracy = float(I_correct)/float(I_count)
    O_accuracy = float(O_correct)/float(O_count)

    return {'raw accuracy':raw_accuracy, 'B accuracy':B_accuracy, 'I accuracy':I_accuracy, 'O accuracy':O_accuracy,}

def main():
    """
    Used when run as a stand alone script.
    """
    
    usage = """
    %prog is used to test the functionality of the internal classifier
    algorithm. To run a test use --test #. Where # is the test number.

    For cross validation test please use --test X.

    If no options are provided all tests will be performed.
    """

    optparser = OptionParser(usage)
    optparser.add_option("-t", "--test", type="string",
            help="Test number.")
    
    (options, args) = optparser.parse_args()
    
    # list of valid tests
    tests = ['1', '2', 'X',]
    # use eval() to run the correct test.
    test_cmds = [
        """test1()[2]""",
        """test2()[2]""",
        """crossvalidation('data.txt')""",
            ]

    if not options.test:
        options.test = 'ALL'

    if options.test == 'ALL':
        for t in tests:
            print eval(test_cmds[tests.index(t)])
    elif option.test not in tests:
        print >> sys.stderr, "No test '%s' exists in the test set. Please select from the tests:" % options.test
        print >> sys.stderr, "\t%s." % (', '.join(tests))
    else:
        print eval(test_cmds[tests.index(options.test)])

    return 0

if __name__ == '__main__':
    sys.exit(main())

