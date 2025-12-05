"""
Trie Tree Data Structure for Efficient Keyword Matching
Used by the Rule Engine for fast license keyword detection
"""

class TrieNode:
    def __init__(self):
        self.children = {}
        self.is_end_of_word = False
        self.license_ids = []  # Store license IDs that match this keyword

class Trie:
    def __init__(self):
        self.root = TrieNode()
    
    def insert(self, word: str, license_id: str):
        """
        Insert a keyword into the trie and associate it with a license ID
        
        Args:
            word: The keyword to insert (will be lowercased)
            license_id: The SPDX license identifier
        """
        node = self.root
        word = word.lower()
        
        for char in word:
            if char not in node.children:
                node.children[char] = TrieNode()
            node = node.children[char]
        
        node.is_end_of_word = True
        if license_id not in node.license_ids:
            node.license_ids.append(license_id)
    
    def search(self, word: str) -> list:
        """
        Search for a keyword in the trie
        
        Args:
            word: The keyword to search for
            
        Returns:
            List of license IDs associated with this keyword, or empty list if not found
        """
        node = self.root
        word = word.lower()
        
        for char in word:
            if char not in node.children:
                return []
            node = node.children[char]
        
        if node.is_end_of_word:
            return node.license_ids
        return []
    
    def search_text(self, text: str) -> dict:
        """
        Search for all keywords in a given text
        
        Args:
            text: The text to search in
            
        Returns:
            Dictionary mapping license_id to count of keyword matches
        """
        text = text.lower()
        matches = {}
        
        # Split text into words and search each
        words = text.split()
        for word in words:
            # Clean word of punctuation
            clean_word = ''.join(c for c in word if c.isalnum() or c.isspace())
            license_ids = self.search(clean_word)
            for license_id in license_ids:
                matches[license_id] = matches.get(license_id, 0) + 1
        
        # Also search for multi-word phrases by sliding window
        # This is a simplified approach - for production, consider more sophisticated methods
        for i in range(len(words)):
            for j in range(i + 1, min(i + 6, len(words) + 1)):  # Check up to 5-word phrases
                phrase = ' '.join(words[i:j])
                license_ids = self.search(phrase)
                for license_id in license_ids:
                    matches[license_id] = matches.get(license_id, 0) + 2  # Weight phrases higher
        
        return matches
