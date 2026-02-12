#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Unit tests for metadata_parser module

Copyright (C) 2026 Fossology contributors
SPDX-License-Identifier: GPL-2.0-only
"""

import os
import sys
import unittest
import json
from pathlib import Path

# Add parent directory to path to import the parser
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from metadata_parser import MetadataParser, MavenParser, NpmParser, PipParser, GoModParser


class TestMetadataParser(unittest.TestCase):
    """Test cases for metadata file detection and parsing."""
    
    def setUp(self):
        """Set up test fixtures."""
        self.test_dir = Path(__file__).parent / 'sample_metadata'
        self.test_dir.mkdir(exist_ok=True)
    
    def test_detect_pom_xml(self):
        """Test detection of Maven pom.xml files."""
        file_type = MetadataParser.detect_file_type('pom.xml')
        self.assertEqual(file_type, 'maven')
    
    def test_detect_package_json(self):
        """Test detection of npm package.json files."""
        file_type = MetadataParser.detect_file_type('package.json')
        self.assertEqual(file_type, 'npm')
    
    def test_detect_unknown_file(self):
        """Test that unknown files return None."""
        file_type = MetadataParser.detect_file_type('unknown.txt')
        self.assertIsNone(file_type)
    
    def test_parse_simple_npm(self):
        """Test parsing a simple package.json file."""
        test_file = self.test_dir / 'test_package.json'
        
        # Create test file
        test_data = {
            'name': 'test-package',
            'version': '1.0.0',
            'dependencies': {
                'express': '^4.17.1',
                'lodash': '4.17.21'
            },
            'devDependencies': {
                'jest': '^27.0.0'
            }
        }
        
        with open(test_file, 'w') as f:
            json.dump(test_data, f)
        
        try:
            # Parse file
            result = MetadataParser.parse_file(str(test_file))
            
            self.assertIsNone(result['error'])
            self.assertEqual(result['file_type'], 'npm')
            self.assertEqual(len(result['dependencies']), 3)
            
            # Check dependency names
            dep_names = [d['name'] for d in result['dependencies']]
            self.assertIn('express', dep_names)
            self.assertIn('lodash', dep_names)
            self.assertIn('jest', dep_names)
            
        finally:
            # Clean up
            if test_file.exists():
                test_file.unlink()
    
    def test_parse_pip_requirements(self):
        """Test parsing a requirements.txt file."""
        test_file = self.test_dir / 'test_requirements.txt'
        
        # Create test file
        with open(test_file, 'w') as f:
            f.write('# Test requirements\n')
            f.write('django==4.2.0\n')
            f.write('requests>=2.28.0\n')
            f.write('numpy\n')
        
        try:
            result = MetadataParser.parse_file(str(test_file))
            
            self.assertIsNone(result['error'])
            self.assertEqual(result['file_type'], 'pip')
            self.assertEqual(len(result['dependencies']), 3)
            
            # Verify specific dependency
            django_deps = [d for d in result['dependencies'] if d['name'] == 'django']
            self.assertEqual(len(django_deps), 1)
            self.assertEqual(django_deps[0]['version'], '==4.2.0')
            
        finally:
            if test_file.exists():
                test_file.unlink()


class TestMavenParser(unittest.TestCase):
    """Test cases for Maven pom.xml parsing."""
    
    def setUp(self):
        self.test_dir = Path(__file__).parent / 'sample_metadata'
        self.test_dir.mkdir(exist_ok=True)
    
    def test_parse_basic_pom(self):
        """Test parsing a basic pom.xml file."""
        test_file = self.test_dir / 'test_pom.xml'
        
        # Create simple pom.xml
        pom_content = '''<?xml version="1.0" encoding="UTF-8"?>
<project xmlns="http://maven.apache.org/POM/4.0.0">
    <modelVersion>4.0.0</modelVersion>
    <groupId>com.example</groupId>
    <artifactId>test-project</artifactId>
    <version>1.0.0</version>
    
    <dependencies>
        <dependency>
            <groupId>junit</groupId>
            <artifactId>junit</artifactId>
            <version>4.13.2</version>
            <scope>test</scope>
        </dependency>
        <dependency>
            <groupId>com.google.guava</groupId>
            <artifactId>guava</artifactId>
            <version>31.1-jre</version>
        </dependency>
    </dependencies>
</project>'''
        
        with open(test_file, 'w') as f:
            f.write(pom_content)
        
        try:
            deps = MavenParser.parse(str(test_file))
            
            self.assertEqual(len(deps), 2)
            
            # Check first dependency
            junit_dep = next((d for d in deps if 'junit' in d.name), None)
            self.assertIsNotNone(junit_dep)
            self.assertEqual(junit_dep.version, '4.13.2')
            self.assertEqual(junit_dep.scope, 'test')
            
        finally:
            if test_file.exists():
                test_file.unlink()


if __name__ == '__main__':
    unittest.main()
