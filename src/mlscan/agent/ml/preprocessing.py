"""
Enhanced Preprocessing Service
Handles text cleaning, normalization, and feature extraction (TF-IDF and BERT)
"""

import re
from sklearn.feature_extraction.text import TfidfVectorizer
from sentence_transformers import SentenceTransformer
import numpy as np

class Preprocessor:
    def __init__(self):
        self.tfidf_vectorizer = None
        self.bert_model = None
        self._initialize_models()
    
    def _initialize_models(self):
        """Initialize TF-IDF and BERT models"""
        try:
            # Initialize TF-IDF vectorizer
            self.tfidf_vectorizer = TfidfVectorizer(
                max_features=1000,
                ngram_range=(1, 3),
                stop_words='english'
            )
            
            # Initialize BERT model (lightweight version for speed)
            self.bert_model = SentenceTransformer('all-MiniLM-L6-v2')
            print("Preprocessing models initialized successfully")
        except Exception as e:
            print(f"Warning: Could not initialize preprocessing models: {e}")
    
    def clean_text(self, text: str) -> str:
        """
        Cleans the input text by removing comments, extra whitespace, and normalizing
        
        Args:
            text: Raw input text
            
        Returns:
            Cleaned text string
        """
        # Remove single-line comments (e.g., // or #)
        text = re.sub(r'//.*', '', text)
        text = re.sub(r'#(?!include).*', '', text)  # Keep #include for C/C++
        
        # Remove multi-line comments (/* ... */)
        text = re.sub(r'/\*[\s\S]*?\*/', '', text)
        
        # Remove HTML/XML tags
        text = re.sub(r'<[^>]+>', '', text)
        
        # Normalize whitespace
        text = re.sub(r'\s+', ' ', text).strip()
        
        # Remove excessive punctuation but keep important chars for license detection
        # Keep periods, hyphens, parentheses, quotes as they're common in licenses
        text = re.sub(r'[^\w\s\.\-\(\)\"\'\,\:\;]', '', text)
        
        return text
    
    def extract_license_header(self, text: str) -> str:
        """
        Extract potential license header from code files
        Looks for common license header patterns at the beginning of files
        
        Args:
            text: Full file content
            
        Returns:
            Extracted license header or full text if no header found
        """
        # Common license header patterns
        patterns = [
            r'(?:\/\*[\s\S]*?\*\/)',  # C-style block comments
            r'(?:\/\/.*\n)+',  # Multiple C++ style comments
            r'(?:#.*\n)+',  # Multiple Python/Shell comments
            r'(?:<!--[\s\S]*?-->)',  # HTML comments
        ]
        
        for pattern in patterns:
            match = re.search(pattern, text[:2000])  # Check first 2000 chars
            if match:
                return match.group(0)
        
        # If no header found, return first 1000 characters
        return text[:1000]
    
    def extract_tfidf_features(self, texts: list) -> np.ndarray:
        """
        Extract TF-IDF features from texts
        
        Args:
            texts: List of text strings
            
        Returns:
            TF-IDF feature matrix
        """
        if self.tfidf_vectorizer is None:
            return None
        
        try:
            # Fit and transform if not fitted, otherwise just transform
            if not hasattr(self.tfidf_vectorizer, 'vocabulary_'):
                return self.tfidf_vectorizer.fit_transform(texts).toarray()
            else:
                return self.tfidf_vectorizer.transform(texts).toarray()
        except Exception as e:
            print(f"TF-IDF extraction error: {e}")
            return None
    
    def extract_bert_features(self, text: str) -> np.ndarray:
        """
        Extract BERT embeddings from text
        
        Args:
            text: Input text string
            
        Returns:
            BERT embedding vector
        """
        if self.bert_model is None:
            return None
        
        try:
            embedding = self.bert_model.encode([text])[0]
            return embedding
        except Exception as e:
            print(f"BERT extraction error: {e}")
            return None
    
    def preprocess_for_detection(self, text: str) -> dict:
        """
        Complete preprocessing pipeline for license detection
        
        Args:
            text: Raw input text
            
        Returns:
            Dictionary containing cleaned text and features
        """
        # Extract license header if present
        header = self.extract_license_header(text)
        
        # Clean the text
        clean_text = self.clean_text(header)
        
        # Extract features
        result = {
            'original': text,
            'header': header,
            'clean': clean_text,
            'tfidf': None,
            'bert': None
        }
        
        # Extract BERT features (for single text)
        if self.bert_model:
            result['bert'] = self.extract_bert_features(clean_text)
        
        return result
