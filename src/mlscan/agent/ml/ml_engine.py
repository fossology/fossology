"""
Enhanced ML Engine with TF-IDF + Logistic Regression and BERT ensemble
"""

import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.pipeline import make_pipeline
import os
from schemas import LicenseResult
from bert_classifier import BERTClassifier
from typing import List
import pickle

class MLEngine:
    def __init__(self, data_path: str = "../../data/training_data.csv"):
        self.tfidf_model = None
        self.bert_classifier = None
        self.model_path = "../../data/ml_model.pkl"
        self.bert_path = "data/bert_embeddings.json"
        
        # Try to load existing models, otherwise train new ones
        if not self._load_models():
            self._train_model(data_path)
    
    def _train_model(self, data_path: str):
        """Train both TF-IDF and BERT models"""
        if not os.path.exists(data_path):
            print(f"Warning: Training data not found at {data_path}")
            return
        
        try:
            df = pd.read_csv(data_path)
            print(f"Training ML models on {len(df)} samples...")
            
            # Train TF-IDF + Logistic Regression
            self.tfidf_model = make_pipeline(
                TfidfVectorizer(max_features=500, ngram_range=(1, 3)),
                LogisticRegression(max_iter=1000, multi_class='multinomial')
            )
            self.tfidf_model.fit(df['text'], df['label'])
            print("TF-IDF model trained successfully")
            
            # Train BERT classifier
            self.bert_classifier = BERTClassifier()
            self.bert_classifier.train(df['text'].tolist(), df['label'].tolist())
            print("BERT classifier trained successfully")
            
            # Save models
            self._save_models()
            
        except Exception as e:
            print(f"Error training ML model: {e}")
    
    def _save_models(self):
        """Save trained models to disk"""
        try:
            # Save TF-IDF model
            with open(self.model_path, 'wb') as f:
                pickle.dump(self.tfidf_model, f)
            
            # Save BERT embeddings
            if self.bert_classifier:
                self.bert_classifier.save(self.bert_path)
            
            print("Models saved successfully")
        except Exception as e:
            print(f"Error saving models: {e}")
    
    def _load_models(self) -> bool:
        """Load trained models from disk"""
        try:
            # Load TF-IDF model
            if os.path.exists(self.model_path):
                with open(self.model_path, 'rb') as f:
                    self.tfidf_model = pickle.load(f)
                print("TF-IDF model loaded from disk")
            else:
                return False
            
            # Load BERT classifier
            if os.path.exists(self.bert_path):
                self.bert_classifier = BERTClassifier()
                if self.bert_classifier.load(self.bert_path):
                    print("BERT classifier loaded from disk")
                else:
                    return False
            else:
                return False
            
            return True
        except Exception as e:
            print(f"Error loading models: {e}")
            return False
    
    def predict(self, text: str) -> List[LicenseResult]:
        """
        Predict license using ensemble of TF-IDF and BERT models
        
        Args:
            text: Input text to classify
            
        Returns:
            List of LicenseResult objects sorted by confidence
        """
        results = []
        
        # TF-IDF predictions
        tfidf_results = self._predict_tfidf(text)
        
        # BERT predictions
        bert_results = self._predict_bert(text)
        
        # Ensemble: combine predictions with weighted average
        # TF-IDF weight: 0.4, BERT weight: 0.6 (BERT is generally more accurate)
        combined = {}
        
        for result in tfidf_results:
            combined[result.license_name] = {
                'tfidf': result.confidence,
                'bert': 0.0
            }
        
        for result in bert_results:
            if result.license_name not in combined:
                combined[result.license_name] = {'tfidf': 0.0, 'bert': 0.0}
            combined[result.license_name]['bert'] = result.confidence
        
        # Calculate weighted average
        for license_name, scores in combined.items():
            ensemble_confidence = (0.4 * scores['tfidf']) + (0.6 * scores['bert'])
            
            if ensemble_confidence > 0.2:  # Threshold for inclusion
                results.append(LicenseResult(
                    license_name=license_name,
                    confidence=float(ensemble_confidence),
                    method='ml'
                ))
        
        # Sort by confidence
        results.sort(key=lambda x: x.confidence, reverse=True)
        return results
    
    def _predict_tfidf(self, text: str) -> List[LicenseResult]:
        """Predict using TF-IDF model"""
        if not self.tfidf_model:
            return []
        
        try:
            probs = self.tfidf_model.predict_proba([text])[0]
            classes = self.tfidf_model.classes_
            
            results = []
            for i, prob in enumerate(probs):
                if prob > 0.1:  # Low threshold for ensemble
                    results.append(LicenseResult(
                        license_name=classes[i],
                        confidence=float(prob),
                        method='ml-tfidf'
                    ))
            
            return results
        except Exception as e:
            print(f"TF-IDF prediction error: {e}")
            return []
    
    def _predict_bert(self, text: str) -> List[LicenseResult]:
        """Predict using BERT classifier"""
        if not self.bert_classifier:
            return []
        
        try:
            predictions = self.bert_classifier.predict(text, top_k=5)
            
            results = []
            for license_name, confidence in predictions:
                if confidence > 0.1:  # Low threshold for ensemble
                    results.append(LicenseResult(
                        license_name=license_name,
                        confidence=confidence,
                        method='ml-bert'
                    ))
            
            return results
        except Exception as e:
            print(f"BERT prediction error: {e}")
            return []
