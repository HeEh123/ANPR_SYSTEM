import cv2
import pytesseract
import sys

# Configure Tesseract path
pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'

def simple_plate_recognition(image_path):
    # Read image
    img = cv2.imread(image_path)
    if img is None:
        print(f"Error: Cannot read image at {image_path}")
        return
    
    print(f"Image loaded successfully. Size: {img.shape}")
    
    # Convert to grayscale
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    
    # Simple threshold
    _, thresh = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    
    # Try OCR on the whole image first
    custom_config = r'--oem 3 --psm 8 -c tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
    text = pytesseract.image_to_string(gray, config=custom_config)
    print(f"OCR on full image: {text}")
    
    # Try with thresholded image
    text2 = pytesseract.image_to_string(thresh, config=custom_config)
    print(f"OCR on thresholded image: {text2}")
    
    # Save processed image to see what's happening
    cv2.imwrite('debug_gray.jpg', gray)
    cv2.imwrite('debug_thresh.jpg', thresh)
    print("Debug images saved: debug_gray.jpg and debug_thresh.jpg")

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Please provide image path")
    else:
        simple_plate_recognition(sys.argv[1])