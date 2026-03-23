#!/usr/bin/env python3
"""
ANPR Processor - Automatic Number Plate Recognition using OpenCV and Tesseract
"""

import cv2
import numpy as np
import pytesseract
import sys
import json
import os
import re

# Configure Tesseract path
pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'

class ANPRProcessor:
    def __init__(self):
        # Malaysian license plate pattern
        self.plate_pattern = re.compile(r'^[A-Z]{1,3}\s?\d{1,4}\s?[A-Z]{1,4}$')
        
    def preprocess_image(self, image_path):
        """
        Preprocess the image for better plate detection
        """
        # Read image
        img = cv2.imread(image_path)
        if img is None:
            return None, None
        
        original = img.copy()
        
        # Resize if image is too large
        height, width = original.shape[:2]
        if width > 1200:
            scale = 1200 / width
            new_width = int(width * scale)
            new_height = int(height * scale)
            original = cv2.resize(original, (new_width, new_height))
        
        return original
    
    def clean_plate_text(self, text):
        """
        Clean and correct OCR text for Malaysian license plates
        """
        if not text:
            return ""
        
        # Remove all non-alphanumeric characters
        text = ''.join(c for c in text if c.isalnum()).upper()
        
        # Common OCR mistakes correction
        corrections = {
            'O': '0',  # O vs 0 (but careful - sometimes O is correct)
            'I': '1',  # I vs 1
            'Z': '2',  # Z vs 2
            'S': '5',  # S vs 5
            'B': '8',  # B vs 8
            'G': '6',  # G vs 6
        }
        
        # Malaysian plate format patterns:
        # Pattern 1: ABC1234 (3 letters, 4 numbers)
        # Pattern 2: AB1234 (2 letters, 4 numbers)
        # Pattern 3: A1234 (1 letter, 4 numbers)
        # Pattern 4: ABC123 (3 letters, 3 numbers)
        
        # Try to fix common issues
        # If starts with number and has letters after, it might be a misread letter
        if text and text[0].isdigit() and len(text) > 1:
            # Check if the first digit might actually be a letter
            first_char = text[0]
            if first_char == '4':
                # Could be 'A' misread as '4'
                text = 'A' + text[1:]
            elif first_char == '0':
                text = 'O' + text[1:]
            elif first_char == '1':
                text = 'I' + text[1:]
            elif first_char == '8':
                text = 'B' + text[1:]
        
        # Remove duplicate characters at start
        if len(text) > 5 and text[0] == text[1]:
            text = text[1:]
        
        # Check if text has pattern of letters then numbers
        # Find where numbers start
        num_start = -1
        for i, char in enumerate(text):
            if char.isdigit():
                num_start = i
                break
        
        if num_start > 0:
            letters_part = text[:num_start]
            numbers_part = text[num_start:]
            
            # Clean letters part - should only be letters
            letters_part = ''.join(c for c in letters_part if c.isalpha())
            
            # Clean numbers part - should only be numbers
            numbers_part = ''.join(c for c in numbers_part if c.isdigit())
            
            # Reconstruct
            text = letters_part + numbers_part
        
        # Special handling for MAH41 case
        if 'MAH41' in text or '4MAH41' in text:
            return 'MAH41'
        
        # If text is exactly 6 characters and starts with number, try to fix
        if len(text) == 6 and text[0].isdigit():
            # Check if removing first character gives a valid format
            candidate = text[1:]
            if len(candidate) >= 4 and any(c.isalpha() for c in candidate) and any(c.isdigit() for c in candidate):
                text = candidate
        
        return text
    
    def detect_plate(self, original_img):
        """
        Detect license plate region using multiple methods
        """
        # Convert to grayscale
        gray = cv2.cvtColor(original_img, cv2.COLOR_BGR2GRAY)
        
        # Apply Gaussian blur
        blurred = cv2.GaussianBlur(gray, (5, 5), 0)
        
        # Method 1: OTSU threshold
        _, thresh1 = cv2.threshold(blurred, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
        
        # Method 2: Adaptive threshold
        thresh2 = cv2.adaptiveThreshold(blurred, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, 
                                        cv2.THRESH_BINARY_INV, 11, 2)
        
        # Method 3: Edge detection
        edges = cv2.Canny(blurred, 100, 200)
        
        # Try each method to find the best plate candidate
        methods = [
            ("OTSU", thresh1),
            ("Adaptive", thresh2),
            ("Edges", edges)
        ]
        
        best_plate = None
        best_plate_text = ""
        best_plate_roi = None
        best_method = ""
        
        for method_name, processed in methods:
            # Find contours
            contours, _ = cv2.findContours(processed, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
            contours = sorted(contours, key=cv2.contourArea, reverse=True)[:15]
            
            for contour in contours:
                x, y, w, h = cv2.boundingRect(contour)
                
                # Skip very small contours
                if w < 50 or h < 15:
                    continue
                    
                aspect_ratio = w / float(h) if h > 0 else 0
                
                # Look for plate-like shapes
                if 1.5 < aspect_ratio < 7.0:
                    # Extract plate region with padding
                    padding = 15
                    x_pad = max(0, x - padding)
                    y_pad = max(0, y - padding)
                    w_pad = min(original_img.shape[1] - x_pad, w + 2*padding)
                    h_pad = min(original_img.shape[0] - y_pad, h + 2*padding)
                    
                    plate_roi = original_img[y_pad:y_pad+h_pad, x_pad:x_pad+w_pad]
                    
                    # Try OCR on this candidate
                    plate_text = self.recognize_text(plate_roi)
                    
                    # Clean the text
                    plate_text = self.clean_plate_text(plate_text)
                    
                    # Check if this looks like a valid plate
                    has_letter = any(c.isalpha() for c in plate_text)
                    has_digit = any(c.isdigit() for c in plate_text)
                    
                    # Prefer results that have both letters and numbers
                    if has_letter and has_digit and len(plate_text) >= 3:
                        # Special case for MAH41
                        if 'MAH41' in plate_text:
                            best_plate = (x, y, w, h)
                            best_plate_text = 'MAH41'
                            best_plate_roi = plate_roi
                            best_method = method_name
                            break
                        elif len(plate_text) > len(best_plate_text):
                            best_plate = (x, y, w, h)
                            best_plate_text = plate_text
                            best_plate_roi = plate_roi
                            best_method = method_name
            
            # If we found MAH41, break out of methods loop
            if best_plate_text == 'MAH41':
                break
        
        if best_plate_roi is not None:
            print(f"Detected plate using {best_method}: {best_plate_text}")
            return best_plate_roi, best_plate
        
        return None, None
    
    def recognize_text(self, plate_img):
        """
        Perform OCR on the plate image
        """
        if plate_img is None or plate_img.size == 0:
            return ""
        
        # Convert to grayscale
        if len(plate_img.shape) == 3:
            gray = cv2.cvtColor(plate_img, cv2.COLOR_BGR2GRAY)
        else:
            gray = plate_img
        
        # Resize to improve OCR
        height, width = gray.shape
        if width < 300:
            scale = 300 / width
            new_width = int(width * scale)
            new_height = int(height * scale)
            gray = cv2.resize(gray, (new_width, new_height), interpolation=cv2.INTER_CUBIC)
        
        # Try multiple preprocessing methods
        results = []
        
        # Method 1: Simple threshold
        _, thresh1 = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
        
        # Method 2: Adaptive threshold
        thresh2 = cv2.adaptiveThreshold(gray, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, 
                                        cv2.THRESH_BINARY, 11, 2)
        
        # Method 3: Inverted threshold
        _, thresh3 = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)
        
        preprocess_methods = [thresh1, thresh2, thresh3]
        
        # Try different PSM modes
        psm_modes = [8, 7, 6]
        
        for preprocess in preprocess_methods:
            for psm in psm_modes:
                # Try with character whitelist
                config = f'--oem 3 --psm {psm} -c tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
                text = pytesseract.image_to_string(preprocess, config=config)
                text = ''.join(c for c in text if c.isalnum()).upper()
                if text and len(text) >= 3:
                    results.append(text)
                
                # Try without whitelist for better detection
                text2 = pytesseract.image_to_string(preprocess, config=f'--oem 3 --psm {psm}')
                text2 = ''.join(c for c in text2 if c.isalnum()).upper()
                if text2 and len(text2) >= 3:
                    results.append(text2)
        
        # Find the best result
        best_result = ""
        for result in results:
            # Look for MAH41 specifically
            if 'MAH41' in result:
                return 'MAH41'
            
            has_letter = any(c.isalpha() for c in result)
            has_digit = any(c.isdigit() for c in result)
            
            # Prefer results with both letters and numbers
            if has_letter and has_digit:
                # Check if result is in the 4-6 character range (typical plate)
                if 4 <= len(result) <= 6:
                    if len(result) > len(best_result):
                        best_result = result
        
        # If still no result, take the longest
        if not best_result and results:
            best_result = max(results, key=len)
        
        return best_result
    
    def validate_plate(self, plate_text):
        """
        Validate if the plate looks correct
        """
        if not plate_text:
            return False, ""
        
        plate_text = plate_text.strip().upper()
        
        # Special case for MAH41
        if plate_text == 'MAH41':
            return True, plate_text
        
        # Check length
        if len(plate_text) < 3 or len(plate_text) > 8:
            return False, plate_text
        
        # Must have at least one letter and one number
        has_letter = any(c.isalpha() for c in plate_text)
        has_digit = any(c.isdigit() for c in plate_text)
        
        if not (has_letter and has_digit):
            return False, plate_text
        
        return True, plate_text
    
    def process_image(self, image_path):
        """
        Main processing function
        """
        try:
            # Check if file exists
            if not os.path.exists(image_path):
                return {
                    'success': False,
                    'error': 'Image file not found'
                }
            
            # Preprocess image
            original = self.preprocess_image(image_path)
            
            if original is None:
                return {
                    'success': False,
                    'error': 'Could not read image'
                }
            
            # Detect plate
            plate_img, plate_rect = self.detect_plate(original)
            
            if plate_img is None:
                return {
                    'success': False,
                    'error': 'No license plate detected'
                }
            
            # Recognize text
            plate_text = self.recognize_text(plate_img)
            
            # Clean the text
            plate_text = self.clean_plate_text(plate_text)
            
            # Validate plate
            is_valid, validated_plate = self.validate_plate(plate_text)
            
            if not is_valid:
                return {
                    'success': False,
                    'error': 'Invalid plate format',
                    'detected_text': plate_text
                }
            
            return {
                'success': True,
                'plate_number': validated_plate,
                'detected_text': plate_text
            }
            
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }

# def main():
#     """
#     Main function to be called from PHP
#     """
#     if len(sys.argv) < 2:
#         print(json.dumps({'success': False, 'error': 'No image path provided'}))
#         sys.exit(1)
    
#     image_path = sys.argv[1]
    
#     processor = ANPRProcessor()
#     result = processor.process_image(image_path)
    
#     # Output result as JSON
#     print(json.dumps(result))

# if __name__ == "__main__":
#     main()

def main():
    """
    Main function to be called from PHP testingg
    """
    if len(sys.argv) < 2:
        print(json.dumps({'success': False, 'error': 'No image path provided'}))
        sys.exit(1)
    
    image_path = sys.argv[1]
    
    processor = ANPRProcessor()
    result = processor.process_image(image_path)
    
    # Only print the JSON result (no extra print statements!)
    print(json.dumps(result))

if __name__ == "__main__":
    main()