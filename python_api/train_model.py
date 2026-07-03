import tensorflow as tf
from tensorflow.keras import layers, models
import os
import numpy as np
from PIL import Image
from config import (
    IMG_HEIGHT, IMG_WIDTH, BATCH_SIZE, EPOCHS, 
    DATA_DIR, MODEL_PATH as MODEL_SAVE_PATH, CLASS_NAMES
)

def create_model(num_classes):
    """Defines a simple CNN model."""
    model = models.Sequential([
        layers.Rescaling(1./255, input_shape=(IMG_HEIGHT, IMG_WIDTH, 3)),
        layers.Conv2D(32, (3, 3), activation='relu'),
        layers.MaxPooling2D((2, 2)),
        layers.Conv2D(64, (3, 3), activation='relu'),
        layers.MaxPooling2D((2, 2)),
        layers.Conv2D(128, (3, 3), activation='relu'),
        layers.MaxPooling2D((2, 2)),
        layers.Flatten(),
        layers.Dense(128, activation='relu'),
        layers.Dense(num_classes, activation='softmax')
    ])
    return model

def load_and_preprocess_image(path, label):
    """Loads and preprocesses a single image."""
    try:
        img = tf.io.read_file(path)
        img = tf.image.decode_image(img, channels=3, expand_animations=False)
        img = tf.image.resize(img, [IMG_HEIGHT, IMG_WIDTH])
        return img, label
    except Exception:
        return None, None

def train_model():
    print("Manually scanning for all image files...")
    all_files = []
    all_labels = []
    
    sorted_class_names = sorted(CLASS_NAMES)
    class_to_index = {name: i for i, name in enumerate(sorted_class_names)}

    for class_name in sorted_class_names:
        class_dir = os.path.join(DATA_DIR, class_name)
        if not os.path.isdir(class_dir):
            continue
        for fname in os.listdir(class_dir):
            if fname.lower().endswith(('.png', '.jpg', '.jpeg', '.bmp', '.gif', '.jfif')):
                all_files.append(os.path.join(class_dir, fname))
                all_labels.append(class_to_index[class_name])

    if not all_files:
        print("Error: No image files found in the training directory.")
        return

    print(f"--- Found {len(all_files)} total files to be used for training ---")
    for file_path in all_files:
        print(file_path)
    print("--- End of file list ---\n")

    path_ds = tf.data.Dataset.from_tensor_slices((all_files, all_labels))
    image_label_ds = path_ds.map(load_and_preprocess_image, num_parallel_calls=tf.data.AUTOTUNE)
    image_label_ds = image_label_ds.filter(lambda x, y: x is not None)
    
    num_classes = len(sorted_class_names)
    image_label_ds = image_label_ds.map(lambda x, y: (x, tf.one_hot(y, depth=num_classes)))

    train_ds = image_label_ds.cache().shuffle(buffer_size=len(all_files)).batch(BATCH_SIZE).prefetch(buffer_size=tf.data.AUTOTUNE)

    print(f"Creating model with {num_classes} classes...")
    model = create_model(num_classes)

    model.compile(optimizer='adam',
                  loss='categorical_crossentropy',
                  metrics=['accuracy'])

    model.summary()

    print("Starting model training...")
    history = model.fit(
        train_ds,
        epochs=EPOCHS
    )

    print(f"Saving model to {MODEL_SAVE_PATH}...")
    model.save(MODEL_SAVE_PATH)
    print("Model training complete and saved.")

if __name__ == '__main__':
    if not os.path.isdir(DATA_DIR):
        print(f"Error: Training data directory '{DATA_DIR}' not found.")
    else:
        train_model()
