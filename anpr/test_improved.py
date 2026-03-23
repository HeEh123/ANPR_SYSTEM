import cv2
import pytesseract
import numpy as np
import sys

# Configure Tesseract path
pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'

def find_license_plate(image_path):
    # Read image
    img = cv2.imread(image_path)
    if img is None:
        print("Error: Cannot read image")
        return
    
    print(f"Image size: {img.shape}")
    
    # Resize if too large (makes processing faster)
    height, width = img.shape[:2]
    if width > 1000:
        scale = 1000 / width
        new_width = int(width * scale)
        new_height = int(height * scale)
        img = cv2.resize(img, (new_width, new_height))
        print(f"Resized to: {img.shape}")
    
    # Convert to grayscale
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    
    # Apply different preprocessing methods
    methods = []
    
    # Method 1: Simple threshold
    _, thresh1 = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    methods.append(("OTSU Threshold", thresh1))
    
    # Method 2: Adaptive threshold
    thresh2 = cv2.adaptiveThreshold(gray, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY_INV, 11, 2)
    methods.append(("Adaptive Threshold", thresh2))
    
    # Method 3: Edge detection
    edges = cv2.Canny(gray, 100, 200)
    methods.append(("Edge Detection", edges))
    
    # Method 4: Morphological operations
    kernel = np.ones((3,3), np.uint8)
    morph = cv2.morphologyEx(thresh1, cv2.MORPH_CLOSE, kernel)
    methods.append(("Morphological", morph))
    
    # Try to find plate contours
    for method_name, processed in methods:
        print(f"\n--- Trying {method_name} ---")
        
        # Save debug image
        cv2.imwrite(f'debug_{method_name.replace(" ", "_")}.jpg', processed)
        
        # Find contours
        contours, _ = cv2.findContours(processed, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
        
        # Sort by area
        contours = sorted(contours, key=cv2.contourArea, reverse=True)[:10]
        
        plate_found = False
        for i, contour in enumerate(contours):
            x, y, w, h = cv2.boundingRect(contour)
            aspect_ratio = w / float(h) if h > 0 else 0
            
            # Look for plate-like shapes
            if w > 100 and h > 30 and 1.5 < aspect_ratio < 6.0:
                print(f"  Found potential plate {i+1}: area={cv2.contourArea(contour)}, aspect={aspect_ratio:.2f}, size={w}x{h}")
                
                # Extract plate region
                padding = 10
                x = max(0, x - padding)
                y = max(0, y - padding)
                w = min(img.shape[1] - x, w + 2*padding)
                h = min(img.shape[0] - y, h + 2*padding)
                
                plate_roi = img[y:y+h, x:x+w]
                cv2.imwrite(f'plate_candidate_{i+1}.jpg', plate_roi)
                
                # Try OCR on the plate region
                plate_gray = cv2.cvtColor(plate_roi, cv2.COLOR_BGR2GRAY)
                _, plate_thresh = cv2.threshold(plate_gray, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
                
                custom_config = r'--oem 3 --psm 8 -c tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
                text = pytesseract.image_to_string(plate_thresh, config=custom_config)
                text = ''.join(c for c in text if c.isalnum()).upper()
                
                print(f"  OCR Result: {text}")
                
                if len(text) >= 4:  # Valid plate should have at least 4 characters
                    print(f"  ✓ Valid plate detected: {text}")
                    plate_found = True
                    break
        
        if plate_found:
            break
    
    print("\n--- Done ---")

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Please provide image path")
    else:
        find_license_plate(sys.argv[1])