"""
BERT-based License Classifier
Uses sentence-transformers for generating embeddings and classification
"""

from sentence_transformers import SentenceTransformer
from sklearn.metrics.pairwise import cosine_similarity
import numpy as np
from typing import List, Tuple
import os
import json

class BERTClassifier:
    def __init__(self, model_name: str = 'all-MiniLM-L6-v2'):
        """
        Initialize BERT classifier with a pre-trained model
        
        Args:
            model_name: Name of the sentence-transformer model to use
        """
        self.model = SentenceTransformer(model_name)
        self.license_embeddings = {}
        self.license_texts = {}
        
    def train(self, texts: List[str], labels: List[str]):
        """
        Train the classifier by creating embeddings for known license texts
        
        Args:
            texts: List of license text samples
            labels: Corresponding license labels
        """
        # Group texts by license
        license_groups = {}
        for text, label in zip(texts, labels):
            if label not in license_groups:
                license_groups[label] = []
            license_groups[label].append(text)
        
        # Create average embeddings for each license
        for license_id, license_texts in license_groups.items():
            embeddings = self.model.encode(license_texts)
            # Store average embedding
            self.license_embeddings[license_id] = np.mean(embeddings, axis=0)
            self.license_texts[license_id] = license_texts
        
        print(f"BERT Classifier trained on {len(self.license_embeddings)} licenses")
    
    def predict(self, text: str, top_k: int = 3) -> List[Tuple[str, float]]:
        """
        Predict license for given text
        
        Args:
            text: Input text to classify
            top_k: Number of top predictions to return
            
        Returns:
            List of tuples (license_id, confidence_score)
        """
        if not self.license_embeddings:
            return []
        
        # Generate embedding for input text
        text_embedding = self.model.encode([text])[0]
        
        # Calculate similarity with all known licenses
        similarities = []
        for license_id, license_embedding in self.license_embeddings.items():
            similarity = cosine_similarity(
                text_embedding.reshape(1, -1),
                license_embedding.reshape(1, -1)
            )[0][0]
            similarities.append((license_id, float(similarity)))
        
        # Sort by similarity and return top_k
        similarities.sort(key=lambda x: x[1], reverse=True)
        return similarities[:top_k]
    
    def save(self, path: str):
        """Save license embeddings to disk"""
        data = {
            'embeddings': {k: v.tolist() for k, v in self.license_embeddings.items()},
            'texts': self.license_texts
        }
        with open(path, 'w') as f:
            json.dump(data, f)
    
    def load(self, path: str):
        """Load license embeddings from disk"""
        if not os.path.exists(path):
            return False
        
        with open(path, 'r') as f:
            data = json.load(f)
        
        self.license_embeddings = {k: np.array(v) for k, v in data['embeddings'].items()}
        self.license_texts = data['texts']
        return True
