#!/usr/bin/python -u
"""
  Functional testing module 
  
  Module uses a simple xml file to describe a set of functional tests. This was
  originally written for the FOSSology scheduler and as such is tailored to
  testing that piece of software.
 
  ==============================================================================
  Copyright (C) 2012 Hewlett-Packard Development Company, L.P.
 
  This program is free software; you can redistribute it and/or
  modify it under the terms of the GNU General Public License
  version 2 as published by the Free Software Foundation.
 
  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License along
  with this program; if not, write to the Free Software Foundation, Inc.,
  51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
  ==============================================================================
"""

from xml.dom.minidom import getDOMImplementation
from xml.dom.minidom import parseString
from xml.dom import Node
from optparse import OptionParser
import ConfigParser
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
defsReplace = re.compile('{(.*?)}')
defsSplit   = re.compile('([^\s]+):([^\s]+)')

class DefineError(Exception):
  """ Error class used for missing definitions in the xml file """
  def __init__(self, value):
    self.value = value
  def __str__(self):
    return repr(self.value)

class TimeoutError(Exception):
  """ Error class used when a test suite takes too long to run """
  pass

def timeout(func, maxRuntime):
  """
  @brief Allows the caller to set a max runtime for a particular function call.
  
  @param func        the function that will have the max runtime
  @param maxRuntime  the max amount of time alloted to the function in minutes
  
  Returns a Boolean, True indicating that the function finished, False otherwise
  """
  
  def timeout_handler(signum, frame):
    raise TimeoutError()
  
  signal.signal(signal.SIGALRM, timeout_handler)
  signal.alarm(maxRuntime * 60)
  
  try:
    func()
  except TimeoutError:
    return False
  return True

################################################################################
### class that handles running a test suite ####################################
################################################################################

class testsuite:
  """
  The testsuite class is used to deserialize a test suite from the xml file,
  run the tests and report the results to another xml document.
  
  name     the name of the test suite
  defs     a map of strings to values used to do variables replacement
  setup    list of Actions that will be taken before running the tests
  cleanup  list of Actions that will be taken after running the tests
  tests    list of Actions that are the actual tests
  subpro   list of processes that are running concurrently with the tests
  """
  
  def __init__(self, node):
    """
    Constructor for the testsuite class. This will deserialize the testsuite
    from the xml file that describes all the tests. For each element in the
    setup, and cleanup and action will be created. For each element under each
    <test></test> tag an action will be created.
    
    This will also grab the definitions of variables for the self.defs map. The
    variable substitution will be performed when the definition is loaded from
    the file.
    
    Returns nothing
    """
    
    defNode = node.getElementsByTagName('definitions')[0]
    definitions = defNode.attributes
    
    self.name = node.getAttribute('name')
    
    self.defs = {}
    self.defs['pids'] = {}
    
    # get variable definitions
    for i in xrange(definitions.length):
      if definitions.item(i).name not in self.defs:
        self.defs[definitions.item(i).name] = self.substitute(definitions.item(i).value, defNode)
    
    self.setup   = []
    self.cleanup = []
    self.tests   = []
    self.subpro  = []
    
    # parse all actions that will be taken during the setup phase
    if len(node.getElementsByTagName('setup')) != 0:
      setup = node.getElementsByTagName('setup')[0]
      for action in [curr for curr in setup.childNodes if curr.nodeType == Node.ELEMENT_NODE]:
        self.setup.append(self.createAction(action))
    
    # parse all actions that will be taken during the cleanup phase
    if len(node.getElementsByTagName('cleanup')) != 0:
      cleanup = node.getElementsByTagName('cleanup')[0]
      for action in [curr for curr in cleanup.childNodes if curr.nodeType == Node.ELEMENT_NODE]:
        self.cleanup.append(self.createAction(action))
    
    # parse all actions that will be taken during the testing phase
    for test in node.getElementsByTagName('test'):
      newTest = (test.getAttribute('name'), [])
      for action in [curr for curr in test.childNodes if curr.nodeType == Node.ELEMENT_NODE]:
        newTest[1].append(self.createAction(action))
      self.tests.append(newTest) 
  
  def substitute(self, string, node = None):
    """
    Simple function to make calling processVariable a lot cleaner
    
    Returns the string with the variables correctly substituted
    """
    return defsReplace.sub(functools.partial(self.processVariable, node), string)
  
  def processVariable(self, node, match):
    """
    Function passed to the regular expression library to replace variables in a
    string from the xml file.
    
    The regular expression used is "{([^\s]*?)}". This will match anything that
    doesn't contain any whitespace and falls between two curly braces. For
    example "{hello}" will match, but "{hello goodbye}" and "hello" will not.
    
    Any variable name that starts with a "$" has a special meaning. The text
    following the "$" will be used as a shell command and executed. The
    "{$text}" will be replaced with the output of the shell command. For example
    "{$pwd}" will be replaced with the output of the shell command "pwd".
    
    If a variable has a ":" in it, anything that follows the ":" will be used
    to index into the associative array in the definitions map. For example
    "{pids:0}" will access the element that is mapped to the string "0" in the
    associative array that is as the mapped to the string "pids" in the defs
    map.
    
    Returns the replacement string
    """
    name = match.group(1)
    
    # variable begins with $, replace with output of shell command
    if name[0] == '$':
      process = os.popen(name[1:], 'r')
      ret = process.read()
      process.close()
      return ret[:-1]
    
    # variable contains a ":", access defs[name] as an associative array or dictionary
    arrayMatch = defsSplit.match(name)
    if arrayMatch:
      name  = arrayMatch.group(1)
      index = arrayMatch.group(2)
      
      if not isinstance(self.defs[name], dict):
        raise DefineError('"%s" is not a dictionary in testsuite "%s"' % (name, self.name))
      if name not in self.defs:
        if node and node.hasAttribute(name):
          self.defs[name] = self.substitute(node.getAttribute(name))
        else:
          raise DefineError('"%s" not defined in testsuite "%s"' % (name, self.name))
      if index not in self.defs[name]:
        raise DefineError('"%s" is out of bounds for "%s.%s"' % (index, self.name, name))
      return self.defs[name][arrayMatch.group(2)]
    
    # this is a simply definition access, check validity and return the result
    if name not in self.defs:
      if node and node.hasAttribute(name):
        self.defs[name] = self.substitute(node.getAttribute(name), node)
      else:
        raise DefineError('"%s" not defined in testsuite "%s"' % (name, self.name))
    return self.defs[name]
  
  def failure(self, doc, dest, type, value):
    """
    Puts a failure node into an the results document
    
    Return nothing
    """
    fail = doc.createElement('failure')
    fail.setAttribute('type', type)
    
    text = doc.createTextNode(value)
    fail.appendChild(text)
    
    dest.appendChild(fail)
  
  ###############################
  # actions that tests can take #
  ###############################
  
  def createAction(self, node):
    """
    Creates an action given a particular test suite and xml node. This uses
    simply python reflection to get the method of the testsuite class that has
    the same name as the xml node tag. The action is a functor that can be
    called later be another part of the test harness.
    
    To write a new type of action write a function with the signature:
      actionName(self, source_node, xml_document, destination_node)
    
    * The source_node is the xml node that described the action, this node
      should describe everything that is necessary for the action to be
      performed. This is passed to the action when the action is created.
    * The xml_document is the document that the test results are being written
      to. This is passed to the action when it is called, not during creation.
    * The destination_node is the node in the results xml document that this
      particular action should be writing its results to. This is passed in when
      the action is called, not during creation.
    
    The action should return True if it correctly executed, and False if it
    failed. A failing action has different meanings during different parts of
    the code. During setup, a failing action indicates that the setup is not
    ready to proceed. Failing actions during setup will be called repeatedly
    once every five seconds until the return True. Failing actions during
    testing indicate a failing test. The failure will be reported to results
    document, but the action should still call the failure method to inticate
    in the results document why the failure happened. During cleanup what an
    action returns is ignored.
    
    Returns the new action
    """
    if not hasattr(self, node.nodeName):
      raise DefineError('testsuite "%s" does not have an "%s" action' % (self.name, node.nodeName))
    attr = getattr(self, node.nodeName)
    return functools.partial(attr, node)
  
  def concurrently(self, node, doc, dest):
    """
    Action
    
    Attributes:
      command [required]: the name of the process that will be executed
      params  [required]: the command line parameters passed to the command
    
    This executes a shell command concurrently with the testing harness. This
    starts the process, sleeps for a second and then checks the pid of the
    process. The pid will be appended to the list of pid's in the definitions
    map. This action cannot fail as it does not check any of the results of the
    process that was created.
    
    Returns True
    """
    command  = self.substitute(node.getAttribute('command'))
    params   = self.substitute(node.getAttribute('params'))
    
    cmd  ="%s %s" % (command, params)
    proc = subprocess.Popen(cmd, 0, shell = True)
    time.sleep(1)
    self.subpro.append(proc)
    pidproc = subprocess.Popen('pidof {0}'.format(command), 0, shell = True, stdout = subprocess.PIPE)
    self.defs['pids'][str(len(self.defs['pids']))] = pidproc.stdout.read()[:-1]
    pidproc.wait()
    
    return True
  
  def sequential(self, node, doc, dest):
    """
    Action
    
    Attributes:
      command [required]: the name of the process that will be executed
      params  [required]: the command line parameters passed to the command
      result  [optional]: what the process should print to stdout
      retval  [optional]: what the exit value of the process should be
    
    This executes a shell command synchronously with the testing harness. This
    starts the process, grabs anything written to stdout by the process and the
    return value of the process. If the results and retval attributes are
    provided, these are compared with what the process printed/returned. If
    the results or return value do not match, this will return False.
    
    Returns True if the results and return value match those provided
    """
    command  = self.substitute(node.getAttribute('command'))
    params   = self.substitute(node.getAttribute('params'))
    expected = self.substitute(node.getAttribute('result'))
    retval   = self.substitute(node.getAttribute('retval'))
    
    cmd  = "%s %s" % (command, params)
    proc = subprocess.Popen(cmd, 0, shell = True, stdout = subprocess.PIPE, stderr = subprocess.PIPE)
    
    result = proc.stdout.readlines()
    if len(result) != 0 and len(expected) != 0 and result[0].strip() != expected:
      if dest and doc:
        self.failure(doc, dest, "ResultMismatch",
            "expected: '{0}' != result: '{1}'".format(expected, result[0].strip()))
      return False
    
    proc.wait()
    
    if len(retval) != 0:
      return proc.returncode == int(retval)
    return True
  
  def sleep(self, node, doc, dest):
    """
    Action
    
    Attributes:
      duration [require]: how long the test harness should sleep for
    
    This action simply pauses execution of the test harness for duration
    seconds. This action cannot fail and will always return True.
    
    Returns True
    """
    duration = node.getAttribute('duration')
    time.sleep(int(duration))
    return True
  
  def loadConf(self, node, doc, dest):
    dir = self.substitute(node.getAttribute('directory'))
    
    config = ConfigParser.ConfigParser()
    config.readfp(open(dir + "/fossology.conf"))
    
    self.defs["FOSSOLOGY"] = {}
    self.defs["BUILD"] = {}
    
    self.defs["FOSSOLOGY"]["port"]  = config.get("FOSSOLOGY", "port")
    self.defs["FOSSOLOGY"]["path"]  = config.get("FOSSOLOGY", "path")
    self.defs["FOSSOLOGY"]["depth"] = config.get("FOSSOLOGY", "depth")
    
    config.readfp(open(dir + "/VERSION"))
    
    self.defs["BUILD"]["VERSION"] = config.get("BUILD", "VERSION")
    self.defs["BUILD"]["SVN_REV"] = config.get("BUILD", "SVN_REV")
    self.defs["BUILD"]["BUILD_DATE"] = config.get("BUILD", "BUILD_DATE")
    
    return True
  
  def upload(self, node, doc, dest):
    """
    Action
    
    Attributes:
      file [required]: the file that will be uploaded to the fossology database
    
    This action uploads a new file into the fossology test(hopefully) database
    so that an agent can work with it. This will place the upload_pk for the
    file in the self.sefs map under the name ['upload_pk'][index] where the
    index is the current number of elements in the ['upload_pk'] mapping. So the
    upload_pk's for the files should showup in the order they were uploaded.
    
    Returns True if and only if cp2foss succeeded
    """
    file = self.substitute(node.getAttribute('file'))
    
    cmd = self.substitute('{pwd}/cli/cp2foss -c {config} --user {user} --password {pass} ' + file)
    proc = subprocess.Popen(cmd, 0, shell = True, stdout = subprocess.PIPE, stderr = subprocess.PIPE)
    proc.wait()
    
    if proc.returncode != 0:
      return False
    
    result = proc.stdout.readlines()
    if 'upload_pk' not in self.defs:
      self.defs['upload_pk'] = {}
    self.defs['upload_pk'][str(len(self.defs['upload_pk']))] = re.search(r'\d+', result[-1]).group(0)
    
    return True
  
  def schedule(self, node ,doc, dest):
    """
    Action
    
    Attributes:
      upload [require]: the index of the upload in the ['upload_pk'] mapping
      agents [optional]: comma seperated list of agent to schedule. If this is
          not specified, all agents will be scheduled
    
    This action will schedule agents to run on a particular upload.
    
    Returns True if and only if fossjobs succeeded
    """
    upload = self.substitute(node.getAttribute('upload'))
    agents = self.substitute(node.getAttribute('agents'))
    
    if not agents:
      agents = ""
    
    cmd = self.substitute('{pwd}/cli/fossjobs -c {config} --user {user} --password {pass} -U ' + upload + ' -A ' + agents)
    proc = subprocess.Popen(cmd, 0, shell = True, stdout = subprocess.PIPE, stderr = subprocess.PIPE)
    proc.wait()
    
    print proc.returncode
    if proc.returncode != 0:
      return False
    return True
    
  
  ################################
  # run tests and produce output #
  ################################
  
  def performTests(self, suiteNode, document, fname):
    """
    Runs the tests and writes the output to the results document.
    
    Returns nothing
    """
    failures     = 0
    tests        = 0
    totalasserts = 0

    for action in self.setup:
      while not action(None, None):
        print ".",
        time.sleep(5)
    print " startup finished ",
    for test in self.tests:
      assertions = 0
      testNode = document.createElement("testcase")
      
      testNode.setAttribute("class", test[0])
      testNode.setAttribute("name", test[0])
      
      starttime = time.time()
      for action in test[1]:
        assertions += 1
        print ".",
        if not action(document, testNode):
          failures += 1
      runtime = (time.time() - starttime)
      
      testNode.setAttribute("assertions", str(assertions))
      testNode.setAttribute("time", str(runtime))
      
      tests += 1
      totalasserts += assertions
      
      suiteNode.appendChild(testNode)
    
    print " cleanup"
    for action in self.cleanup:
      action(None, None)
    
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
  parser.add_option("-t", "--tests",    dest = "testfile",   help = "The xml file to pull the tests from")
  parser.add_option("-r", "--results",  dest = "resultfile", help = "The file to output the junit xml to" )
  parser.add_option("-s", "--specific", dest = "specific",   help = "Only run the test with this particular name")
  
  (options, args) = parser.parse_args()
  
  testFile = open(options.testfile)
  dom = parseString(testFile.read())
  dir = os.getcwd()
  
  os.chdir('../../..')
  
  setupNode   = dom.firstChild.getElementsByTagName('setup')[0]
  cleanupNode = dom.firstChild.getElementsByTagName('cleanup')[0]
  
  resultsDoc = getDOMImplementation().createDocument(None, "testsuites", None)
  top_output = resultsDoc.documentElement
  
  maxRuntime = int(dom.firstChild.getAttribute("timeout"))
  
  for suite in dom.firstChild.getElementsByTagName('testsuite'):
    if not options.specific or (suite.getAttribute("name") == options.specific) and not suite.hasAttribute("disable"):
      suiteNode = resultsDoc.createElement("testsuite")
      errors = 0
      
      suiteNode.setAttribute("name", suite.getAttribute("name"))
      suiteNode.setAttribute("errors", "0")
      suiteNode.setAttribute("time", "0")
      
      try:
        curr = testsuite(suite)
        
        setup   = [curr.createAction(node) for node in setupNode.childNodes   if node.nodeType == Node.ELEMENT_NODE]
        cleanup = [curr.createAction(node) for node in cleanupNode.childNodes if node.nodeType == Node.ELEMENT_NODE]
        
        curr.setup   = setup + curr.setup
        curr.cleanup = cleanup + curr.cleanup
        
        starttime = time.time()
        print "Running: {0}".format(suite.getAttribute("name")),
        if not timeout(functools.partial(curr.performTests, suiteNode, resultsDoc, testFile.name), maxRuntime):
          errors += 1
          errorNode = resultsDoc.createElement("error")
          errorNode.setAttribute("type", "TimeOut")
          errorNode.appendChild(resultsDoc.createTextNode("Test suite took too long to run."))
          suiteNode.appendChild(errorNode)
        runtime = (time.time() - starttime)
        
        suiteNode.setAttribute("time", str(runtime))
        
      except DefineError as detail:
        errors += 1
        errorNode = resultsDoc.createElement("error")
        errorNode.setAttribute("type", "DefinitionError")
        errorNode.appendChild(resultsDoc.createTextNode("DefineError: {0}".format(detail.value)))
        suiteNode.appendChild(errorNode)
      
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
