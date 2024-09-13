<?php
session_start();

$baseUploadsDir = 'uploads/';

// Function to make filenames URL-compatible
function makeFilenameUrlSafe($filename) {
    return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '-', $filename));
}

// Set and remember folder name independently
if (isset($_POST['set_folder'])) {
    $_SESSION['upload_dir'] = makeFilenameUrlSafe($_POST['upload_dir']);
}

// Use folder from session or create a default one
$uploadDir = isset($_SESSION['upload_dir']) ? $baseUploadsDir . $_SESSION['upload_dir'] . '/' : null;
if ($uploadDir && !is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true); // Create directory if it doesn't exist
}

// Handle the "Save and Update All Tags" form submission
if (isset($_POST['save_all_tags']) && $uploadDir) {
    $fileNames = $_POST['filenames'];
    $tagsArray = $_POST['tags'];

    foreach ($fileNames as $index => $fileName) {
        $tagString = trim($tagsArray[$index]);
        if (!empty($tagString)) {
            // Process tags: Remove duplicates, empty values, and trim whitespaces
            $tags = array_unique(array_filter(array_map('trim', explode(',', $tagString))));

            // Save the clean tag string back into the .txt file
            if (!empty($tags)) {
                $tagFile = $uploadDir . pathinfo($fileName, PATHINFO_FILENAME) . '.txt';
                file_put_contents($tagFile, implode(', ', $tags));  // Create/overwrite .txt file with tags
            }
        }
    }
}

// Create ZIP for download based on current session folder
if (isset($_POST['download_zip']) && $uploadDir) {
    $zip = new ZipArchive();
    $zipFilename = $uploadDir . "images_and_tags.zip";

    if ($zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        foreach (glob($uploadDir . "*.{jpg,jpeg,png,gif,txt}", GLOB_BRACE) as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename=' . basename($zipFilename));
        header('Content-Length: ' . filesize($zipFilename));
        readfile($zipFilename);
        exit;
    }
}

// Handle image upload to the selected directory
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['new_images'])) {
    $files = $_FILES['new_images'];
    $fileCount = count($files['name']);

    for ($i = 0; $i < $fileCount; $i++) {
        $originalFilename = pathinfo($files['name'][$i], PATHINFO_FILENAME);
        $extension = pathinfo($files['name'][$i], PATHINFO_EXTENSION);

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            $safeFilename = makeFilenameUrlSafe($originalFilename);
            $newFilename = $safeFilename . '.' . $extension;

            // Append random number if file exists
            while (file_exists($uploadDir . $newFilename)) {
                $newFilename = $safeFilename . '-' . rand(1000, 9999) . '.' . $extension;
            }

            move_uploaded_file($files['tmp_name'][$i], $uploadDir . $newFilename);
        }
    }
}

// Load images and tags from the selected folder
$files = $uploadDir ? glob($uploadDir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE) : [];
$allTags = [];

if ($uploadDir && is_dir($uploadDir)) {
    foreach ($files as $file) {
        $tagFile = $uploadDir . pathinfo($file, PATHINFO_FILENAME) . '.txt';
        if (file_exists($tagFile)) {
            $imageTags = file_get_contents($tagFile);
            $imageTagsArray = array_unique(array_filter(array_map('trim', explode(',', $imageTags))));
            $allTags = array_merge($allTags, $imageTagsArray);
        }
    }

    $allTags = array_unique(array_map('trim', $allTags));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Tagging System</title>
    <style>
        body { margin: 0; padding: 0; display: flex; height: 100vh; }
        #left-panel { width: 25%; overflow-y: auto; padding: 10px; background-color: #f9f9f9; box-shadow: 2px 0 5px rgba(0,0,0,0.1); display: flex; flex-direction: column; justify-content: space-between; }
        #right-panel { width: 75%; overflow-y: auto; padding: 20px; }
        .thumbnail { width: 150px; height: 150px; cursor: pointer; }
        .image-block { display: inline-block; text-align: center; margin: 15px; vertical-align: top; }
        .image-block textarea { width: 150px; height: 150px; font-size: 14px; resize: none; }
        .tag-list { margin: 10px; padding: 5px; border: 1px solid #ccc; display: inline-block; }
        .tag { margin: 5px; padding: 5px; background-color: #e1e1e1; cursor: pointer; display: inline-block; }
        #image-container { display: flex; flex-wrap: wrap; }
        .selected { border: 2px solid blue; }
        input[type="file"] { margin: 20px; }
        #save-btn { background-color: darkblue; color: white; padding: 10px 20px; border: none; cursor: pointer; margin-top: 20px; }
        #save-btn:hover { background-color: navy; }
    </style>
</head>
<body>

<!-- Right Panel: Tag Images -->
<div id="right-panel">
    <form method="POST" id="save-all-tags-form">
        <!-- Save and Update All Tags Button on top of the image block -->
        <button id="save-btn" type="submit" name="save_all_tags">Save and Update All Tags</button>

        <input type="hidden" name="upload_dir" value="<?php echo isset($_SESSION['upload_dir']) ? htmlspecialchars($_SESSION['upload_dir']) : ''; ?>">
        <div id="image-container">
            <?php if ($files): ?>
                <?php foreach ($files as $file): ?>
                    <div class="image-block" onclick="toggleSelect(this)">
                        <img src="<?php echo $file; ?>" class="thumbnail" alt="Image Thumbnail" data-filename="<?php echo basename($file); ?>">
                        <textarea name="tags[]" placeholder="Add tags (comma separated)"><?php
                            $tagFile = $uploadDir . pathinfo($file, PATHINFO_FILENAME) . '.txt';
                            if (file_exists($tagFile)) {
                                echo file_get_contents($tagFile);
                            }
                        ?></textarea>
                        <input type="hidden" name="filenames[]" value="<?php echo basename($file); ?>">
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No images found in the selected folder.</p>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Left Panel: Last Used Tags and Upload Section -->
<div id="left-panel">
    <div>
        <!-- Previously Used Tags -->
        <h2>Previously Used Tags</h2>
        <div class="tag-list">
            <?php foreach ($allTags as $tag): ?>
                <span class="tag" onclick="addTagToCheckedImages('<?php echo $tag; ?>')"><?php echo $tag; ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <div>
        <!-- Folder Selection -->
        <h2>Select Folder</h2>
        <form method="POST">
            <input type="text" name="upload_dir" value="<?php echo isset($_SESSION['upload_dir']) ? htmlspecialchars($_SESSION['upload_dir']) : ''; ?>" placeholder="Folder name" required>
            <button type="submit" name="set_folder">Set Folder</button>
        </form>

        <!-- Image Upload -->
        <h2>Upload New Images</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="new_images[]" accept="image/*" multiple required>
            <button type="submit">Upload Images</button>
        </form>

        <!-- Download ZIP -->
        <form method="POST">
            <button type="submit" name="download_zip">Download All as ZIP</button>
        </form>
    </div>
</div>

<script>
// Toggling image selection by adding/removing the blue border
function toggleSelect(imageBlock) {
    let img = imageBlock.querySelector('img');
    img.classList.toggle('selected');
}

// Add tag to checked images
function addTagToCheckedImages(tag) {
    let selectedImages = document.querySelectorAll('.selected');

    selectedImages.forEach(image => {
        let parentBlock = image.closest('.image-block');
        let tagInput = parentBlock.querySelector('textarea');
        let currentTags = tagInput.value.split(',').map(t => t.trim()).filter(t => t !== '');

        if (!currentTags.includes(tag)) {
            currentTags.push(tag);
            tagInput.value = currentTags.join(', ');
        }
    });
}
</script>

</body>
</html>
