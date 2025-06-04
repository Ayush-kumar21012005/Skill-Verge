"""
AI Interview Analysis Engine for SkillVerge
This module handles AI-powered interview evaluation using NLP and sentiment analysis
"""

import json
import re
import nltk
from textblob import TextBlob
from datetime import datetime
import speech_recognition as sr
import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
import logging

# Download required NLTK data
try:
    nltk.download('punkt', quiet=True)
    nltk.download('vader_lexicon', quiet=True)
    nltk.download('stopwords', quiet=True)
except:
    pass

class InterviewAnalyzer:
    def __init__(self):
        self.domain_keywords = {
            'Software Development': [
                'programming', 'coding', 'algorithm', 'data structure', 'object oriented',
                'database', 'sql', 'api', 'framework', 'testing', 'debugging', 'version control',
                'git', 'agile', 'scrum', 'javascript', 'python', 'java', 'react', 'node'
            ],
            'Data Science': [
                'machine learning', 'statistics', 'python', 'r', 'pandas', 'numpy',
                'visualization', 'model', 'algorithm', 'regression', 'classification',
                'clustering', 'neural network', 'deep learning', 'feature engineering',
                'cross validation', 'overfitting', 'bias', 'variance'
            ],
            'Digital Marketing': [
                'seo', 'sem', 'social media', 'content marketing', 'email marketing',
                'analytics', 'conversion', 'roi', 'campaign', 'brand', 'audience',
                'engagement', 'lead generation', 'funnel', 'customer journey'
            ],
            'Finance': [
                'financial analysis', 'accounting', 'investment', 'portfolio', 'risk',
                'valuation', 'cash flow', 'balance sheet', 'income statement',
                'npv', 'irr', 'derivatives', 'equity', 'debt', 'market analysis'
            ]
        }
        
        self.vectorizer = TfidfVectorizer(stop_words='english', max_features=1000)
        
    def transcribe_audio(self, audio_file_path):
        """
        Transcribe audio file to text using speech recognition
        """
        try:
            recognizer = sr.Recognizer()
            with sr.AudioFile(audio_file_path) as source:
                audio = recognizer.record(source)
                text = recognizer.recognize_google(audio)
                return text
        except Exception as e:
            logging.error(f"Audio transcription error: {e}")
            return ""
    
    def analyze_technical_accuracy(self, response_text, domain, expected_keywords=None):
        """
        Analyze technical accuracy of the response based on domain-specific keywords
        """
        if not response_text:
            return 0.0
            
        response_lower = response_text.lower()
        domain_keywords = self.domain_keywords.get(domain, [])
        
        # Count relevant keywords
        keyword_matches = sum(1 for keyword in domain_keywords if keyword in response_lower)
        keyword_score = min(keyword_matches / max(len(domain_keywords) * 0.3, 1), 1.0)
        
        # Analyze response completeness
        word_count = len(response_text.split())
        completeness_score = min(word_count / 50, 1.0)  # Expect at least 50 words for full score
        
        # Check for technical depth
        technical_indicators = ['because', 'therefore', 'however', 'for example', 'such as', 'in contrast']
        depth_score = sum(1 for indicator in technical_indicators if indicator in response_lower) / len(technical_indicators)
        
        # Weighted average
        technical_score = (keyword_score * 0.5 + completeness_score * 0.3 + depth_score * 0.2) * 10
        return min(technical_score, 10.0)
    
    def analyze_communication_clarity(self, response_text):
        """
        Analyze communication clarity using NLP techniques
        """
        if not response_text:
            return 0.0
            
        # Grammar and readability analysis
        blob = TextBlob(response_text)
        
        # Sentence structure analysis
        sentences = blob.sentences
        avg_sentence_length = np.mean([len(str(sentence).split()) for sentence in sentences])
        
        # Optimal sentence length is 15-20 words
        sentence_score = 1.0 - abs(avg_sentence_length - 17.5) / 17.5
        sentence_score = max(0, min(sentence_score, 1.0))
        
        # Vocabulary diversity
        words = response_text.lower().split()
        unique_words = set(words)
        vocabulary_diversity = len(unique_words) / len(words) if words else 0
        
        # Coherence check (simple version)
        coherence_indicators = ['first', 'second', 'then', 'next', 'finally', 'in conclusion', 'moreover', 'furthermore']
        coherence_score = min(sum(1 for indicator in coherence_indicators if indicator in response_text.lower()) / 3, 1.0)
        
        # Filler words penalty
        filler_words = ['um', 'uh', 'like', 'you know', 'basically', 'actually']
        filler_count = sum(response_text.lower().count(filler) for filler in filler_words)
        filler_penalty = min(filler_count / 10, 0.3)  # Max 30% penalty
        
        # Calculate final score
        clarity_score = (sentence_score * 0.3 + vocabulary_diversity * 0.3 + coherence_score * 0.4) - filler_penalty
        return max(clarity_score * 10, 0.0)
    
    def analyze_confidence_level(self, response_text, audio_features=None):
        """
        Analyze confidence level from text and audio features
        """
        if not response_text:
            return 0.0
            
        # Text-based confidence indicators
        confidence_words = ['confident', 'sure', 'certain', 'definitely', 'absolutely', 'clearly']
        uncertainty_words = ['maybe', 'perhaps', 'might', 'possibly', 'not sure', 'i think', 'probably']
        
        confidence_count = sum(1 for word in confidence_words if word in response_text.lower())
        uncertainty_count = sum(1 for word in uncertainty_words if word in response_text.lower())
        
        # Sentiment analysis
        blob = TextBlob(response_text)
        sentiment_score = (blob.sentiment.polarity + 1) / 2  # Normalize to 0-1
        
        # Response length as confidence indicator
        word_count = len(response_text.split())
        length_confidence = min(word_count / 30, 1.0)  # Longer responses often indicate confidence
        
        # Calculate confidence score
        text_confidence = (confidence_count - uncertainty_count * 0.5) / max(len(response_text.split()) / 10, 1)
        text_confidence = max(0, min(text_confidence, 1.0))
        
        # Combine factors
        confidence_score = (text_confidence * 0.4 + sentiment_score * 0.3 + length_confidence * 0.3) * 10
        return min(confidence_score, 10.0)
    
    def generate_feedback(self, scores, domain, responses):
        """
        Generate detailed feedback based on analysis scores
        """
        technical_score = scores['technical']
        communication_score = scores['communication']
        confidence_score = scores['confidence']
        overall_score = scores['overall']
        
        feedback = {
            'overall_feedback': '',
            'technical_feedback': '',
            'communication_feedback': '',
            'confidence_feedback': '',
            'strengths': [],
            'improvements': []
        }
        
        # Overall feedback
        if overall_score >= 8:
            feedback['overall_feedback'] = "Excellent performance! You demonstrated strong knowledge and communication skills."
        elif overall_score >= 6:
            feedback['overall_feedback'] = "Good performance with room for improvement in some areas."
        elif overall_score >= 4:
            feedback['overall_feedback'] = "Average performance. Focus on strengthening your technical knowledge and communication."
        else:
            feedback['overall_feedback'] = "Needs significant improvement. Consider more practice and preparation."
        
        # Technical feedback
        if technical_score >= 7:
            feedback['technical_feedback'] = f"Strong technical knowledge in {domain}. Good use of relevant terminology."
            feedback['strengths'].append("Strong technical foundation")
        else:
            feedback['technical_feedback'] = f"Technical knowledge needs improvement. Study more {domain} concepts and practice explaining them clearly."
            feedback['improvements'].append("Strengthen technical knowledge")
        
        # Communication feedback
        if communication_score >= 7:
            feedback['communication_feedback'] = "Clear and articulate communication. Good sentence structure and vocabulary."
            feedback['strengths'].append("Clear communication")
        else:
            feedback['communication_feedback'] = "Work on communication clarity. Practice explaining concepts more simply and avoid filler words."
            feedback['improvements'].append("Improve communication clarity")
        
        # Confidence feedback
        if confidence_score >= 7:
            feedback['confidence_feedback'] = "Confident delivery with good conviction in your answers."
            feedback['strengths'].append("Confident presentation")
        else:
            feedback['confidence_feedback'] = "Work on building confidence. Practice more and use definitive language."
            feedback['improvements'].append("Build confidence in delivery")
        
        return feedback
    
    def analyze_interview(self, interview_data):
        """
        Main method to analyze complete interview
        """
        domain = interview_data['domain']
        questions = interview_data['questions']
        audio_files = interview_data.get('audio_files', [])
        
        all_responses = []
        question_scores = []
        
        # Process each question and response
        for i, question in enumerate(questions):
            # Transcribe audio if available
            if i < len(audio_files) and audio_files[i]:
                response_text = self.transcribe_audio(audio_files[i])
            else:
                response_text = interview_data.get('responses', {}).get(str(i), '')
            
            all_responses.append(response_text)
            
            # Analyze individual response
            technical_score = self.analyze_technical_accuracy(response_text, domain)
            communication_score = self.analyze_communication_clarity(response_text)
            confidence_score = self.analyze_confidence_level(response_text)
            
            question_score = {
                'question': question,
                'response': response_text,
                'technical_score': technical_score,
                'communication_score': communication_score,
                'confidence_score': confidence_score
            }
            question_scores.append(question_score)
        
        # Calculate overall scores
        if question_scores:
            avg_technical = np.mean([q['technical_score'] for q in question_scores])
            avg_communication = np.mean([q['communication_score'] for q in question_scores])
            avg_confidence = np.mean([q['confidence_score'] for q in question_scores])
            overall_score = (avg_technical + avg_communication + avg_confidence) / 3
        else:
            avg_technical = avg_communication = avg_confidence = overall_score = 0
        
        scores = {
            'technical': round(avg_technical, 1),
            'communication': round(avg_communication, 1),
            'confidence': round(avg_confidence, 1),
            'overall': round(overall_score, 1)
        }
        
        # Generate feedback
        feedback = self.generate_feedback(scores, domain, all_responses)
        
        return {
            'scores': scores,
            'feedback': feedback,
            'question_analysis': question_scores,
            'analysis_timestamp': datetime.now().isoformat()
        }

def main():
    """
    Main function for testing the analyzer
    """
    # Sample interview data
    sample_data = {
        'domain': 'Software Development',
        'questions': [
            'What is object-oriented programming?',
            'Explain the difference between SQL and NoSQL databases.',
            'How do you handle version control in your projects?'
        ],
        'responses': {
            '0': 'Object-oriented programming is a programming paradigm that uses objects and classes. It includes concepts like inheritance, encapsulation, and polymorphism.',
            '1': 'SQL databases are relational and use structured query language, while NoSQL databases are non-relational and can handle unstructured data.',
            '2': 'I use Git for version control. I create branches for features, commit regularly, and merge back to main branch after testing.'
        }
    }
    
    analyzer = InterviewAnalyzer()
    results = analyzer.analyze_interview(sample_data)
    
    print("Interview Analysis Results:")
    print(json.dumps(results, indent=2))

if __name__ == "__main__":
    main()
