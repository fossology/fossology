#!/usr/bin/env python3
"""
SPDX-FileCopyrightText: © Fossology contributors
SPDX-License-Identifier: GPL-2.0-only

Integration tests for mlscan_runner
"""

import unittest
import json
import os
import sys
import tempfile

# Add ml directory to path
ml_dir = os.path.join(os.path.dirname(__file__), '../../agent/ml')
sys.path.insert(0, ml_dir)

from mlscan_runner import scan_file

class TestMLScanRunner(unittest.TestCase):
    """Test mlscan_runner integration"""
    
    def setUp(self):
        """Create temporary files for testing"""
        self.test_dir = tempfile.mkdtemp()
        
        # Create test license file
        self.test_file = os.path.join(self.test_dir, 'test_license.txt')
        with open(self.test_file, 'w') as f:
            f.write("""
MIT License

Copyright (c) 2024 Test Author

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.
""")
        
        self.output_file = os.path.join(self.test_dir, 'output.json')
    
    def tearDown(self):
        """Clean up temporary files"""
        import shutil
        if os.path.exists(self.test_dir):
            shutil.rmtree(self.test_dir)
    
    def test_scan_file(self):
        """Test scanning a file"""
        exit_code = scan_file(self.test_file, self.output_file)
        
        # Should succeed
        self.assertEqual(exit_code, 0)
        
        # Output file should exist
        self.assertTrue(os.path.exists(self.output_file))
        
        # Parse output
        with open(self.output_file, 'r') as f:
            result = json.load(f)
        
        # Check structure
        self.assertIn('file', result)
        self.assertIn('licenses', result)
        self.assertIn('conflict_detected', result)
        self.assertIn('message', result)
        
        # Should detect MIT
        self.assertGreater(len(result['licenses']), 0)
        
        # Check license structure
        for license in result['licenses']:
            self.assertIn('license_name', license)
            self.assertIn('confidence', license)
            self.assertIn('method', license)
            
            # Confidence should be valid
            self.assertGreaterEqual(license['confidence'], 0.0)
            self.assertLessEqual(license['confidence'], 1.0)
    
    def test_empty_file(self):
        """Test scanning empty file"""
        empty_file = os.path.join(self.test_dir, 'empty.txt')
        with open(empty_file, 'w') as f:
            f.write('')
        
        exit_code = scan_file(empty_file, self.output_file)
        self.assertEqual(exit_code, 0)
        
        with open(self.output_file, 'r') as f:
            result = json.load(f)
        
        # Should have no licenses
        self.assertEqual(len(result['licenses']), 0)

if __name__ == '__main__':
    unittest.main(verbosity=2)
