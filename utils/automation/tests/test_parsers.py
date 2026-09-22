# SPDX-FileCopyrightText: 2026 Shubham Padkonde <shubhampadkonde12@gmail.com>
# SPDX-License-Identifier: GPL-2.0-only

import io
import json
import os
import subprocess
import tarfile
import tempfile
import unittest
from unittest.mock import Mock, patch

from ScanDeps.Downloader import Downloader
from ScanDeps.Parsers import (DOWNLOAD_URL_KEY, ComposerParser, GitHubParser,
                              GoParser, MavenParser, Parser, PythonParser)


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


def make_parser(directory, purls):
  sbom = os.path.join(directory, 'bom.json')
  with open(sbom, 'w', encoding='utf-8') as stream:
    json.dump({'components': [
      {'name': p.split('/')[-1].split('@')[0], 'version': '1', 'purl': p}
      for p in purls]}, stream)
  parser = Parser(sbom)
  parser.classify_components(directory)
  return parser


class DependencyParsersTest(unittest.TestCase):
  def test_composer_matches_v_prefixed_release(self):
    purl = 'pkg:composer/acme/lib@1.2.3'
    with tempfile.TemporaryDirectory() as directory:
      parser = make_parser(directory, [purl])
      response = Mock()
      response.json.return_value = {'packages': {'acme/lib': [
        {'version': 'v1.2.3', 'dist': {'url': 'https://example.com/l.zip'},
         'source': {'url': 'https://example.com/l.git'}}]}}
      with patch('ScanDeps.Parsers.requests.get', return_value=response):
        ComposerParser().parse_components(parser)
      self.assertEqual(parser.parsed_components[purl][DOWNLOAD_URL_KEY],
                       'https://example.com/l.zip')

  def test_maven_prefers_sources_jar_and_falls_back_to_binary(self):
    purls = ['pkg:maven/org.acme/lib@1.0',
             'pkg:maven/org.acme/lib@1.0?classifier=javadoc']
    with tempfile.TemporaryDirectory() as directory:
      parser = make_parser(directory, purls)
      with patch('ScanDeps.Parsers.requests.head',
                 return_value=Mock(ok=True)):
        MavenParser().parse_components(parser)
      urls = [parser.parsed_components[p][DOWNLOAD_URL_KEY] for p in purls]
      self.assertTrue(urls[0].endswith('/lib-1.0-sources.jar'))
      self.assertTrue(urls[1].endswith('/lib-1.0-javadoc.jar'))
      with patch('ScanDeps.Parsers.requests.head',
                 return_value=Mock(ok=False)):
        MavenParser().parse_components(parser)
      self.assertTrue(
        parser.parsed_components[purls[0]][DOWNLOAD_URL_KEY].endswith(
          '/lib-1.0.jar'))

  def test_github_resolves_archive(self):
    purl = 'pkg:github/acme/lib@1.0'
    with tempfile.TemporaryDirectory() as directory:
      parser = make_parser(directory, [purl])
      GitHubParser().parse_components(parser)
      self.assertEqual(parser.parsed_components[purl][DOWNLOAD_URL_KEY],
                       'https://github.com/acme/lib/archive/1.0.tar.gz')

  def test_download_dir_cannot_escape_root(self):
    with tempfile.TemporaryDirectory() as directory:
      sbom = os.path.join(directory, 'bom.json')
      with open(sbom, 'w', encoding='utf-8') as stream:
        json.dump({'components': [
          {'name': '../../x', 'version': '..', 'purl': 'pkg:golang/a/b@1'}]},
          stream)
      parser = Parser(sbom)
      parser.classify_components(directory)
      path = parser.golang_components[0]['download_dir']
      self.assertEqual(os.path.dirname(os.path.dirname(path)),
                       os.path.join(directory, 'golang'))

  def test_go_proxy_url_escapes_uppercase(self):
    url = GoParser()._get_download_url('pkg:golang/github.com/Azure/go-sdk@v1.0.0')
    self.assertEqual(
      url, 'https://proxy.golang.org/github.com/!azure/go-sdk/@v/v1.0.0.zip')

  def test_debian_source_components_need_arch_source(self):
    purls = ['pkg:deb/debian/bash@5.2?arch=source',
             'pkg:deb/debian/bash@5.2?arch=amd64']
    with tempfile.TemporaryDirectory() as directory:
      parser = make_parser(directory, purls)
      self.assertEqual([c['purl'] for c in parser.debian_source_components],
                       [purls[0]])


def make_tar(path, members):
  with tarfile.open(path, 'w') as tar:
    for name, kind, data in members:
      info = tarfile.TarInfo(name)
      if kind == 'sym':
        info.type, info.linkname = tarfile.SYMTYPE, data
        tar.addfile(info)
      else:
        info.size = len(data)
        tar.addfile(info, io.BytesIO(data))


class DownloaderTest(unittest.TestCase):
  def extract(self, members):
    extract = Downloader()._Downloader__extract_tar
    with tempfile.TemporaryDirectory() as directory:
      archive = os.path.join(directory, 'a.tar')
      out = os.path.join(directory, 'out')
      os.mkdir(out)
      make_tar(archive, members)
      with tarfile.open(archive) as tar:
        extract(tar, out)
      return os.path.exists(os.path.join(out, 'pkg', 'a.txt'))

  def test_tar_extracts_regular_archive(self):
    self.assertTrue(self.extract([('pkg/a.txt', 'file', b'hi')]))

  def test_tar_rejects_unsafe_members(self):
    for member in [('../evil.txt', 'file', b'hi'),
                   ('pkg/link', 'sym', '../../etc/passwd')]:
      with self.subTest(member=member[0]):
        with self.assertRaises(tarfile.TarError):
          self.extract([member])

  def test_debian_source_download_survives_timeout(self):
    purl = 'pkg:deb/debian/bash@5.2?arch=source'
    with tempfile.TemporaryDirectory() as directory:
      parser = make_parser(directory, [purl])
      timeout = subprocess.TimeoutExpired('debsbom', 1)
      with patch('ScanDeps.Downloader.shutil.which', return_value='/bin/x'), \
           patch('ScanDeps.Downloader.subprocess.run', side_effect=timeout):
        result = Downloader().download_debian_sources(parser)
    self.assertEqual(result, '0 Debian source packages downloaded.')


if __name__ == '__main__':
  unittest.main()
