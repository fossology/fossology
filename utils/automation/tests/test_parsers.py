# SPDX-FileCopyrightText: 2026 Shubham Padkonde <shubhampadkonde12@gmail.com>
# SPDX-License-Identifier: GPL-2.0-only

import json
import os
import tempfile
import unittest
from unittest.mock import Mock, patch

from ScanDeps.Parsers import DOWNLOAD_URL_KEY, Parser, PythonParser


class PythonParserTest(unittest.TestCase):
  def test_null_project_urls_is_accepted(self):
    components = [
      {'name': name, 'version': '1.0', 'purl': f'pkg:pypi/{name}@1.0'}
      for name in ['example-one', 'example-two']
    ]
    with tempfile.TemporaryDirectory() as directory:
      sbom = os.path.join(directory, 'bom.json')
      with open(sbom, 'w', encoding='utf-8') as stream:
        json.dump({'components': components}, stream)
      parser = Parser(sbom)
      parser.classify_components(directory)
      responses = []
      for name in ['example-one', 'example-two']:
        response = Mock()
        response.json.return_value = {
          'info': {'project_urls': None},
          'urls': [{'packagetype': 'sdist',
                    'url': f'https://example.com/{name}.tar.gz'}],
        }
        responses.append(response)
      with patch('ScanDeps.Parsers.requests.get', side_effect=responses):
        with self.assertNoLogs(level='ERROR'):
          PythonParser().parse_components(parser)
      for component in parser.python_components:
        name = component['name']
        self.assertEqual(component[DOWNLOAD_URL_KEY],
                         f'https://example.com/{name}.tar.gz')
        self.assertNotIn('vcs_url', component)
        self.assertNotIn('homepage_url', component)

  def test_project_urls_preserves_source_and_homepage(self):
    with tempfile.TemporaryDirectory() as directory:
      sbom = os.path.join(directory, 'bom.json')
      with open(sbom, 'w', encoding='utf-8') as stream:
        json.dump({'components': [{'name': 'example', 'version': '1.0',
                                  'purl': 'pkg:pypi/example@1.0'}]}, stream)
      parser = Parser(sbom)
      parser.classify_components(directory)
      response = Mock()
      response.json.return_value = {
        'info': {
          'project_urls': {
            'Source': 'https://example.com/source',
            'Homepage': 'https://example.com',
          },
        },
        'urls': [{'packagetype': 'sdist',
                  'url': 'https://example.com/example.tar.gz'}],
      }
      with patch('ScanDeps.Parsers.requests.get', return_value=response):
        PythonParser().parse_components(parser)
      component = parser.python_components[0]
      self.assertEqual(component['vcs_url'], 'https://example.com/source')
      self.assertEqual(component['homepage_url'], 'https://example.com')


if __name__ == '__main__':
  unittest.main()
