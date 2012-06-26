#!/usr/bin/python -u

## 
## Copyright (C) 2012 Hewlett-Packard Development Company, L.P.
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

from xml.dom.minidom import getDOMImplementation
from xml.dom.minidom import parseString
from xml.dom import Node
from optparse import OptionParser
import subprocess
import functools
import signal
import shlex
import time
import re
import os

################################################################################
### utility ####################################################################
################################################################################
defsReplace = re.compile('{([^\s]*?)}')
defsSplit   = re.compile('(\w*):([0-9]*)')

class DefineError(Exception):
  def __init__(self, value):
    self.value = value
  def __str__(self):
    return repr(self.value)

class TimeoutError(Exception):
  pass

def timeout(func, maxRuntime):
  def timeout_handler(signum, frame):
    raise TimeoutException()
  
  signal.signal(signal.SIGALRM, timeout_handler)
  signal.alarm(maxRuntime * 60)
  
  try:
    func()
  except TimeoutException:
    return False
  return True

################################################################################
### class that handles running a test suite ####################################
################################################################################

class testsuite:
  
  def __init__(self, node):
    definitions = node.getElementsByTagName('definitions')[0].attributes
    
    self.name = node.getAttribute('name')
    
    self.defs = {}
    self.defs['pids'] = []
    
    # get variable definitions
    for i in xrange(definitions.length):
      self.defs[definitions.item(i).name] = self.substitute(definitions.item(i).value)
    
    self.setup   = []
    self.cleanup = []
    self.tests   = []
    self.subpro  = []
    
    # parse all actions that will be taken during the testing phase
    if len(node.getElementsByTagName('setup')) != 0:
      setup = node.getElementsByTagName('setup')[0]
      for action in [curr for curr in setup.childNodes if curr.nodeType == Node.ELEMENT_NODE]:
        self.setup.append(self.createAction(action))
    
    if len(node.getElementsByTagName('cleanup')) != 0:
      cleanup = node.getElementsByTagName('cleanup')[0]
      for action in [curr for curr in cleanup.childNodes if curr.nodeType == Node.ELEMENT_NODE]:
        self.cleanup.append(self.createAction(action))
    
    for test in node.getElementsByTagName('test'):
      newTest = (test.getAttribute('name'), [])
      for action in [curr for curr in test.childNodes if curr.nodeType == Node.ELEMENT_NODE]:
        newTest[1].append(self.createAction(action))
      self.tests.append(newTest) 
  
  def substitute(self, string):
    return defsReplace.sub(functools.partial(self.processVariable, self.defs), string)
  
  def processVariable(self, defines, match):
    name = match.group(1)
    
    if name[0] == '$':
      process = os.popen(name[1:], 'r')
      ret = process.read()
      process.close()
      return ret[:-1]
    
    arrayMatch = defsSplit.match(name)
    if arrayMatch:
      name  = arrayMatch.group(1)
      index = int(arrayMatch.group(2))
      
      if name not in defines:
        raise DefineError('"%s" not defined in testsuite "%s"' % (name, self.name))
      if not isinstance(defines[name], list):
        raise DefineError('"%s" is not a list in testsuite "%s"' % (name, self.name))
      if len(defines[name]) <= index:
        raise DefineError('"%d" is out of bounds for "%s.%s"' % (index, self.name, name))
      return defines[name][int(arrayMatch.group(2))]
    
    if name not in defines:
      raise DefineError('"%s" not defined in testsuite "%s"' % (name, self.name))
    return defines[name]
  
  ###############################
  # actions that tests can take #
  ###############################
  
  def createAction(self, node):
    if not hasattr(self, node.nodeName):
      raise DefineError('testsuite "%s" does not have an "%s" action' % (self.name, node.nodeName))
    attr = getattr(self, node.nodeName)
    return functools.partial(attr, node)
  
  def concurrently(self, node):
    command  = self.substitute(node.getAttribute('command'))
    params   = self.substitute(node.getAttribute('params'))
    
    cmd  ="%s %s" % (command, params)
    args = shlex.split(cmd)
    proc = subprocess.Popen(cmd, 0, shell = True)
    time.sleep(1)
    self.subpro.append(proc)
    self.defs['pids'].append(subprocess.check_output(['pidof', command])[:-1])
    
    return True
  
  def sequential(self, node):
    command  = self.substitute(node.getAttribute('command'))
    params   = self.substitute(node.getAttribute('params'))
    expected = self.substitute(node.getAttribute('result'))
    retval   = self.substitute(node.getAttribute('retval'))
    compare  = self.substitute(node.getAttribute('compare'))
    
    cmd  = "%s %s" % (command, params)
    args = shlex.split(cmd)
    proc = subprocess.Popen(args, 0, stdout = subprocess.PIPE)
    
    result = proc.stdout.readlines()
    if len(expected) != 0 and (len(result) != 1 or result[0].strip() != expected):
      return False
    
    proc.wait()
    
    if len(retval) != 0:
      return proc.returncode == int(retval)
    return True
  
  def sleep(self, node):
    duration = node.getAttribute('duration')
    time.sleep(int(duration))
    return True
    
  ################################
  # run tests and produce output #
  ################################
  
  def performTests(self, suiteNode, document, fname):
    failures     = 0
    tests        = 0
    totalasserts = 0
    
    for action in self.setup:
      while not action():
        time.sleep(5)
    for test in self.tests:
      assertions = 0
      testNode = document.createElement("testcase")
      
      testNode.setAttribute("name", test[0])
      testNode.setAttribute("class", test[0])
      testNode.setAttribute("file", fname)
      testNode.setAttribute("line", "0");
      
      starttime = time.time()
      for action in test[1]:
        assertions += 1
        if not action():
          failures += 1
      runtime = (time.time() - starttime)
      
      testNode.setAttribute("assertions", str(assertions))
      testNode.setAttribute("time", str(runtime))
      
      tests += 1
      totalasserts += assertions
      
      suiteNode.appendChild(testNode)
      
    for action in self.cleanup:
      action()
    
    for process in self.subpro:
      process.wait()
      
    suiteNode.setAttribute("failures", str(failures))
    suiteNode.setAttribute("tests", str(tests))
    suiteNode.setAttribute("assertions", str(totalasserts))
 
################################################################################
### MAIN #######################################################################
################################################################################

def main():
  
  usage = "usage: %prog [options]"
  parser = OptionParser(usage = usage)
  parser.add_option("-t", "--tests",   dest = "testfile",   help = "The xml file to pull the tests from")
  parser.add_option("-r", "--results", dest = "resultfile", help = "The file to output the junit xml to" )
  
  (options, args) = parser.parse_args()
  
  testFile = open(options.testfile)
  dom = parseString(testFile.read())
  dir = os.getcwd()
  
  os.chdir('../..')
  
  setupNode   = dom.firstChild.getElementsByTagName('setup')[0]
  cleanupNode = dom.firstChild.getElementsByTagName('cleanup')[0]
  
  resultsDoc = getDOMImplementation().createDocument(None, "testsuites", None)
  top_output = resultsDoc.documentElement
  
  maxRuntime = int(dom.firstChild.getAttribute("timeout"))
  
  for suite in dom.firstChild.getElementsByTagName('testsuite'):
    suiteNode = resultsDoc.createElement("testsuite")
    errors = 0
    
    suiteNode.setAttribute("name", suite.nodeName)
    suiteNode.setAttribute("file", testFile.name)
    suiteNode.setAttribute("fullPackage", suite.getAttribute("fullPackage"))
    suiteNode.setAttribute("tests", "0")
    suiteNode.setAttribute("assertions", "0")
    suiteNode.setAttribute("failures", "0");
    suiteNode.setAttribute("errors", "0")
    suiteNode.setAttribute("time", "0")
    
    try:
      curr = testsuite(suite)
      
      setup   = [curr.createAction(node) for node in setupNode.childNodes   if node.nodeType == Node.ELEMENT_NODE]
      cleanup = [curr.createAction(node) for node in cleanupNode.childNodes if node.nodeType == Node.ELEMENT_NODE]
      
      curr.setup   = setup + curr.setup
      curr.cleanup = cleanup + curr.cleanup
      
      starttime = time.time()
      if not timeout(functools.partial(curr.performTests, suiteNode, resultsDoc, testFile.name), maxRuntime):
        errors += 1
      runtime = (time.time() - starttime)
      
      suiteNode.setAttribute("time", str(runtime))
      
    except DefineError as detail:
      print 'DefineError:', detail.value
      errors += 1
    
    finally:
      suiteNode.setAttribute("errors", str(errors))
      top_output.appendChild(suiteNode)
  
  os.chdir(dir);
  
  output = open(options.resultfile, 'w')
  resultsDoc.writexml(output, "", "  ", "\n")
  output.close()
  
  os.chdir(dir)

if __name__ == "__main__":
  main()
