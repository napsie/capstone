import tensorflow as tf
from tensorflow.keras import layers, models
import os
from config import (
    IMG_HEIGHT, IMG_WIDTH, BATCH_SIZE, EPOCHS, 
    DATA_DIR, AUTOENCODER_MODEL_PATH
)

def create_autoencoder():
    # Encoder
    encoder_input = tf.keras.Input(shape=(IMG_HEIGHT, IMG_WIDTH, 3), name='encoder_input')
    x = layers.Conv2D(32, (3, 3), activation='relu', padding='same')(encoder_input)
    x = layers.MaxPooling2D((2, 2), padding='same')(x)
    x = layers.Conv2D(64, (3, 3), activation='relu', padding='same')(x)
    x = layers.MaxPooling2D((2, 2), padding='same')(x)
    x = layers.Conv2D(128, (3, 3), activation='relu', padding='same')(x)
    encoded = layers.MaxPooling2D((2, 2), padding='same')(x) # Latent representation

    # Decoder
    x = layers.Conv2D(128, (3, 3), activation='relu', padding='same')(encoded)
    x = layers.UpSampling2D((2, 2))(x)
    x = layers.Conv2D(64, (3, 3), activation='relu', padding='same')(x)
    x = layers.UpSampling2D((2, 2))(x)
    x = layers.Conv2D(32, (3, 3), activation='relu', padding='same')(x)
    x = layers.UpSampling2D((2, 2))(x)
    decoded = layers.Conv2D(3, (3, 3), activation='sigmoid', padding='same')(x) # Output: 3 channels for RGB, sigmoid for pixel values 0-1

    # Autoencoder model
    autoencoder = models.Model(encoder_input, decoded, name='autoencoder')
    return autoencoder

def load_and_preprocess_image(file_path):
    try:
        img = tf.io.read_file(file_path)
        # Use decode_image which is more general and can handle PNG, GIF, etc.
        img = tf.image.decode_image(img, channels=3, expand_animations=False)
        img = tf.image.resize(img, [IMG_HEIGHT, IMG_WIDTH])
        img.set_shape([IMG_HEIGHT, IMG_WIDTH, 3]) # Set shape for consistency
        img = img / 255.0 # Normalize to [0, 1]
        return img
    except tf.errors.InvalidArgumentError:
        # This can happen if the image is corrupted or not a valid image format.
        print(f"Warning: Could not decode image {file_path}. Skipping.")
        return None

def train_autoencoder():
    print("Loading training data for autoencoder (genuine IDs only)...")
    
    genuine_classes = ['PWD_ID', 'Senior_ID']
    all_image_paths = []

    for class_name in genuine_classes:
        class_dir = os.path.join(DATA_DIR, class_name)
        if not os.path.exists(class_dir):
            print(f"Warning: Genuine class directory '{class_dir}' not found. Skipping.")
            continue
        
        # Get all image paths for this genuine class
        class_image_paths = [os.path.join(class_dir, fname) 
                             for fname in os.listdir(class_dir) 
                             if fname.lower().endswith(('.png', '.jpg', '.jpeg', '.bmp', '.gif'))]
        all_image_paths.extend(class_image_paths)

    if not all_image_paths:
        print("Error: No genuine ID data found for autoencoder training. Please ensure 'PWD_ID' and 'Senior_ID' directories exist in your DATA_DIR and contain images.")
        return

    # Create a tf.data.Dataset from image paths
    list_ds = tf.data.Dataset.from_tensor_slices(all_image_paths)
    
    # Load and preprocess images
    train_ds = list_ds.map(load_and_preprocess_image, num_parallel_calls=tf.data.AUTOTUNE)
    # Filter out images that failed to load
    train_ds = train_ds.filter(lambda x: x is not None)
    
    # Shuffle and batch
    train_ds = train_ds.shuffle(buffer_size=len(all_image_paths)).batch(BATCH_SIZE).prefetch(tf.data.AUTOTUNE)

    print("Creating autoencoder model...")
    autoencoder = create_autoencoder()

    # Compile the autoencoder
    autoencoder.compile(optimizer='adam', loss='mse') # Mean Squared Error for reconstruction

    autoencoder.summary()

    print("Starting autoencoder training...")
    history = autoencoder.fit(
        train_ds,
        epochs=EPOCHS
    )

    print(f"Saving autoencoder model to {AUTOENCODER_MODEL_PATH}...")
    autoencoder.save(AUTOENCODER_MODEL_PATH)
    print("Autoencoder training complete and saved.")

if __name__ == '__main__':
    # Ensure the genuine ID directories exist
    all_exist = True
    genuine_classes = ['PWD_ID', 'Senior_ID'] # Define here for the check as well
    for class_name in genuine_classes:
        class_dir = os.path.join(DATA_DIR, class_name)
        if not os.path.exists(class_dir):
            print(f"Error: Expected genuine ID subdirectory '{class_dir}' not found.")
            all_exist = False
    
    if not all_exist:
        print(f"\nPlease create the following subdirectories inside '{DATA_DIR}' for autoencoder training:")
        for name in genuine_classes:
            print(f"- {DATA_DIR}/{name}")
        print("And place your genuine ID images accordingly before running this script.")
    else:
        train_autoencoder()
