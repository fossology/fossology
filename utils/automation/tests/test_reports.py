# SPDX-FileCopyrightText: 2026 Siemens AG
# SPDX-License-Identifier: GPL-2.0-only

import json
import os
import tempfile
import types
import unittest

from FoScanner.DashboardReport import DashboardReport
from FoScanner.Packages import Packages
from FoScanner.Scanners import ScanResultList
from FoScanner.SpdxReport import SpdxReport


def make_scanner(dependencies, unscanned):
  packages = Packages()
  packages.parent_package = {'name': 'demo', 'description': None,
                             'author': '', 'url': ''}
  packages.dependencies = dependencies
  packages.unscanned_dependencies = unscanned
  scanner = types.SimpleNamespace(
    get_scan_packages=lambda: packages,
    is_excluded_path=lambda path: False,
    results_are_allow_listed=lambda whole=False: [])
  cli_options = types.SimpleNamespace(allowlist={'licenses': ['MIT']},
                                      parser=None)
  return cli_options, scanner


def component(namespace, license_file=None):
  purl = f'pkg:maven/{namespace}/core@1.0'
  results = []
  if license_file:
    results = [ScanResultList('LICENSE', license_file, [{'license': 'MIT'}])]
  return purl, {'name': 'core', 'version': '1.0', 'purl': purl,
                'SCANNER_RESULTS': results, 'COPYRIGHT_RESULT': []}


class ReportTest(unittest.TestCase):
  def write_spdx(self, dependencies, unscanned):
    cli_options, scanner = make_scanner(dependencies, unscanned)
    report = SpdxReport(cli_options, scanner)
    report.finalize_document()
    with tempfile.TemporaryDirectory() as directory:
      path = os.path.join(directory, 'report.spdx.json')
      report.write_report(path)
      with open(path, encoding='utf-8') as stream:
        return json.load(stream)

  def test_same_name_dependencies_each_contain_their_files(self):
    with tempfile.TemporaryDirectory() as directory:
      license_file = os.path.join(directory, 'LICENSE')
      with open(license_file, 'w', encoding='utf-8') as stream:
        stream.write('MIT License\n')
      document = self.write_spdx(
        dict([component('org.a', license_file),
              component('com.b', license_file)]), {})
    dependency_ids = [p['SPDXID'] for p in document['packages']
                      if p['name'] == 'core']
    self.assertEqual(len(set(dependency_ids)), 2)
    for spdx_id in dependency_ids:
      contains = [r for r in document['relationships']
                  if r['spdxElementId'] == spdx_id
                  and r['relationshipType'] == 'CONTAINS']
      self.assertEqual(len(contains), 1, spdx_id)

  def test_dependency_with_files_without_license(self):
    with tempfile.TemporaryDirectory() as directory:
      files = []
      for name, text in [('a.txt', 'one\n'), ('b.txt', 'two\n'),
                         ('LICENSE', 'MIT License\n')]:
        files.append(os.path.join(directory, name))
        with open(files[-1], 'w', encoding='utf-8') as stream:
          stream.write(text)
      purl, dependency = component('org.a')
      dependency['SCANNER_RESULTS'] = [
        ScanResultList('a.txt', files[0], []),
        ScanResultList('b.txt', files[1], []),
        ScanResultList('LICENSE', files[2], [{'license': 'MIT'}])]
      document = self.write_spdx({purl: dependency}, {})
    package = next(p for p in document['packages'] if p['name'] == 'core')
    self.assertEqual(package['licenseConcluded'], 'MIT')
    concluded = {f['fileName']: f['licenseConcluded']
                 for f in document['files']}
    self.assertEqual(concluded, {'a.txt': 'NOASSERTION',
                                 'b.txt': 'NOASSERTION', 'LICENSE': 'MIT'})

  def test_parent_with_allow_listed_licenses(self):
    with tempfile.TemporaryDirectory() as directory:
      paths = []
      for name, text in [('LICENSE', 'MIT License\n'), ('a.c', 'int a;\n')]:
        paths.append(os.path.join(directory, name))
        with open(paths[-1], 'w', encoding='utf-8') as stream:
          stream.write(text)
      cli_options, scanner = make_scanner({}, {})
      scanner.get_scan_packages().parent_package['SCANNER_RESULTS'] = [
        ScanResultList('LICENSE', paths[0], [{'license': 'MIT'}]),
        ScanResultList('a.c', paths[1], [{'license': 'MIT'}])]
      report = SpdxReport(cli_options, scanner)
      report.finalize_document()
      report.write_report(os.path.join(directory, 'report.spdx.json'))
      with open(os.path.join(directory, 'report.spdx.json'),
                encoding='utf-8') as stream:
        document = json.load(stream)
    parent = next(p for p in document['packages'] if p['name'] == 'demo')
    self.assertEqual(parent['licenseConcluded'], 'MIT')

  def test_unscanned_dependency_is_listed_as_not_analyzed(self):
    purl, unscanned = component('com.b')
    document = self.write_spdx(dict([component('org.a')]), {purl: unscanned})
    packages = {p['SPDXID']: p for p in document['packages']
                if p['name'] == 'core'}
    self.assertEqual(len(packages), 2)
    not_analyzed = [p for p in packages.values()
                    if p.get('filesAnalyzed') is False]
    self.assertEqual(len(not_analyzed), 1)
    self.assertEqual(not_analyzed[0]['licenseConcluded'], 'NOASSERTION')
    self.assertIn('Not scanned', not_analyzed[0]['comment'])
    depends_on = {r['relatedSpdxElement'] for r in document['relationships']
                  if r['relationshipType'] == 'DEPENDS_ON'}
    self.assertEqual(depends_on, set(packages))

  def test_dashboard_counts_unscanned_dependencies(self):
    purl, unscanned = component('com.b')
    cli_options, scanner = make_scanner(dict([component('org.a')]),
                                        {purl: unscanned})
    dashboard = DashboardReport(cli_options, scanner)
    dashboard.finalize_document()
    self.assertIn('| Dependencies Not Scanned | 1 |', dashboard._markdown)
    self.assertIn(f'`{purl}`', dashboard._markdown)
    self.assertEqual(dashboard._stats['dependencies_not_scanned'], 1)
    self.assertEqual(dashboard._stats['dependencies'], 1)


if __name__ == '__main__':
  unittest.main()
