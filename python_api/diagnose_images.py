import os
from PIL import Image
from config import DATA_DIR

def diagnose_images():
    """
    Scans all image files in the training data directory and reports any
    files that cannot be opened or processed by the Pillow library.
    """
    print(f"Starting diagnosis of images in: {DATA_DIR}\n")
    bad_files = []
    total_files = 0

    # Walk through all subdirectories and files
    for subdir, _, files in os.walk(DATA_DIR):
        for file in files:
            file_path = os.path.join(subdir, file)
            total_files += 1
            try:
                # Attempt to open the image file
                with Image.open(file_path) as img:
                    # Attempt to load the image data to catch truncated files
                    img.load()
                    # Optional: Check if it can be converted to RGB
                    img.convert('RGB')
            except Exception as e:
                # If any error occurs, flag the file as bad
                print(f"[BAD] Could not process file: {file_path}")
                print(f"      Reason: {e}\n")
                bad_files.append(file_path)

    print("--- Diagnosis Complete ---")
    if not bad_files:
        print(f"Success! All {total_files} files were processed correctly.")
    else:
        print(f"Found {len(bad_files)} problematic file(s) out of {total_files} total files:")
        for path in bad_files:
            print(f"- {path}")
        print("\nPlease review, fix, or remove these files, as they are likely causing the training script to skip them.")

if __name__ == '__main__':
    diagnose_images()
