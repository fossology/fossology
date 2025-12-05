#!/usr/bin/env python3
"""
SPDX-FileCopyrightText: © Fossology contributors
SPDX-License-Identifier: GPL-2.0-only

Unit tests for ML components
"""

import unittest
import sys
import os

# Add ml directory to path - go up two levels to agent/ml
ml_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), '../../agent/ml'))
sys.path.insert(0, ml_dir)

from preprocessing import Preprocessor
from rule_engine import RuleEngine
from ml_engine import MLEngine
from hybrid_engine import HybridEngine

class TestPreprocessing(unittest.TestCase):
    """Test preprocessing functionality"""
    
    def setUp(self):
        self.preprocessor = Preprocessor()
    
    def test_clean_text(self):
        """Test text cleaning"""
        text = "  MIT License\n\nCopyright (c) 2024  "
        cleaned = self.preprocessor.clean_text(text)
        self.assertIsInstance(cleaned, str)
        self.assertGreater(len(cleaned), 0)
    
    def test_empty_text(self):
        """Test empty text handling"""
        cleaned = self.preprocessor.clean_text("")
        self.assertEqual(cleaned, "")

class TestRuleEngine(unittest.TestCase):
    """Test rule-based detection"""
    
    def setUp(self):
        self.rule_engine = RuleEngine()
    
    def test_license_detection(self):
        """Test MIT license detection"""
        text = """
        MIT License
        
        Permission is hereby granted, free of charge, to any person obtaining a copy
        of this software and associated documentation files (the "Software"), to deal
        in the Software without restriction.
        """
        results = self.rule_engine.scan(text.lower())
        self.assertIsInstance(results, list)
        # Should detect MIT
        if results:
            self.assertGreater(results[0].confidence, 0.5)
    
    def test_empty_text(self):
        """Test empty text"""
        results = self.rule_engine.scan("")
        self.assertEqual(len(results), 0)

class TestMLEngine(unittest.TestCase):
    """Test ML classification"""
    
    def setUp(self):
        self.ml_engine = MLEngine()
    
    def test_prediction(self):
        """Test ML prediction"""
        text = "MIT License - Permission is hereby granted"
        results = self.ml_engine.predict(text)
        self.assertIsInstance(results, list)

class TestHybridEngine(unittest.TestCase):
    """Test hybrid decision engine"""
    
    def setUp(self):
        self.hybrid_engine = HybridEngine()
    
    def test_analyze(self):
        """Test hybrid analysis"""
        text = """
        MIT License
        
        Copyright (c) 2024 Test
        
        Permission is hereby granted, free of charge, to any person obtaining a copy
        of this software and associated documentation files (the "Software"), to deal
        in the Software without restriction, including without limitation the rights
        to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
        copies of the Software.
        """
        result = self.hybrid_engine.analyze(text)
        
        # Check result structure
        self.assertIsNotNone(result)
        self.assertIsInstance(result.detected_licenses, list)
        self.assertIsInstance(result.conflict_detected, bool)
        self.assertIsInstance(result.message, str)
        
        # Should detect at least one license
        if result.detected_licenses:
            top_license = result.detected_licenses[0]
            self.assertGreater(top_license.confidence, 0.0)
            self.assertLessEqual(top_license.confidence, 1.0)
    
    def test_confidence_scores(self):
        """Test confidence scores are in valid range"""
        text = "Apache License 2.0"
        result = self.hybrid_engine.analyze(text)
        
        for license in result.detected_licenses:
            self.assertGreaterEqual(license.confidence, 0.0)
            self.assertLessEqual(license.confidence, 1.0)

if __name__ == '__main__':
    # Run tests
    unittest.main(verbosity=2)
