<?php
session_start();

// Set default folder
if (!isset($_SESSION['folder'])) {
    $_SESSION['folder'] = 'default';
}

// Handle folder selection
if (isset($_POST['set_folder'])) {
    $folder_name = preg_replace('/[^A-Za-z0-9_-]/', '', $_POST['folder_name']);
    if (!is_dir('uploads/' . $folder_name)) {
        mkdir('uploads/' . $folder_name, 0777, true);
    }
    $_SESSION['folder'] = $folder_name;
}

// Set current folder
$current_folder = 'uploads/' . $_SESSION['folder'];
if (!is_dir($current_folder)) {
    mkdir($current_folder, 0777, true);
}

// Do not allow uploads in the default directory
$allow_upload = $_SESSION['folder'] !== 'default';

// Handle file uploads (images and txt files)
if ($allow_upload && isset($_FILES['files'])) {
    // Collect uploaded files grouped by original basename
    $uploaded_files = [];
    foreach ($_FILES['files']['name'] as $key => $name) {
        $tmp_name = $_FILES['files']['tmp_name'][$key];
        $error = $_FILES['files']['error'][$key];
        if ($error == UPLOAD_ERR_OK) {
            $name = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $name);
            $path_parts = pathinfo($name);
            $ext = strtolower($path_parts['extension']);
            $filename = $path_parts['filename'];
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'txt'])) {
                $uploaded_files[$filename][] = [
                    'tmp_name' => $tmp_name,
                    'extension' => $ext,
                ];
            }
        }
    }

    // Existing basenames to check for collisions
    $existing_files = scandir($current_folder);
    $existing_basenames = [];
    foreach ($existing_files as $file) {
        if (is_file($current_folder . '/' . $file)) {
            $existing_basenames[] = pathinfo($file, PATHINFO_FILENAME);
        }
    }

    // Process uploaded files
    foreach ($uploaded_files as $original_basename => $files) {
        $new_basename = $original_basename;
        // Check for name collisions
        while (in_array($new_basename, $existing_basenames)) {
            $rand = rand(1000, 9999);
            $new_basename = $original_basename . '_' . $rand;
        }
        // Add to existing basenames
        $existing_basenames[] = $new_basename;

        // Move files with the new basename
        foreach ($files as $file_info) {
            $tmp_name = $file_info['tmp_name'];
            $ext = $file_info['extension'];
            $new_name = $new_basename . '.' . $ext;
            move_uploaded_file($tmp_name, $current_folder . '/' . $new_name);

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                // For images, ensure tag file exists
                $tag_file = $current_folder . '/' . $new_basename . '.txt';
                if (!file_exists($tag_file)) {
                    file_put_contents($tag_file, '');
                }
            }
        }
    }
}

// Handle tag updates via Ajax
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'update_tags') {
        $image = basename($_POST['image']); // Sanitize
        $filename = pathinfo($image, PATHINFO_FILENAME);
        $tag_file = $current_folder . '/' . $filename . '.txt';
        $tags = $_POST['tags'];
        // Clean up the tags
        $tags_array = array_map('trim', explode(',', $tags));
        $tags_array = array_filter($tags_array); // Remove empty elements
        $clean_tags = implode(', ', $tags_array);
        file_put_contents($tag_file, $clean_tags);
        echo 'success';
        exit;
    }

    // Handle adding tag to selected images via Ajax
    if ($_POST['action'] == 'add_tag') {
        $tag = trim($_POST['tag']);
        $selected_images = $_POST['selected_images'];
        foreach ($selected_images as $image) {
            $image = basename($image); // Sanitize
            $filename = pathinfo($image, PATHINFO_FILENAME);
            $tag_file = $current_folder . '/' . $filename . '.txt';
            $tags = '';
            if (file_exists($tag_file)) {
                $tags = file_get_contents($tag_file);
            }
            $tags_array = array_map('trim', explode(',', $tags));
            if (!in_array($tag, $tags_array) && $tag != '') {
                $tags_array[] = $tag;
            }
            $tags_array = array_filter($tags_array); // Remove empty elements
            $clean_tags = implode(', ', $tags_array);
            file_put_contents($tag_file, $clean_tags);
        }
        echo 'success';
        exit;
    }

    // Handle deleting selected images via Ajax
    if ($_POST['action'] == 'delete_selected') {
        $selected_images = $_POST['selected_images'];
        if (count($selected_images) > 10) {
            echo 'You can delete a maximum of 10 images at a time.';
            exit;
        }
        $deleted_images = [];
        foreach ($selected_images as $image) {
            $image = basename($image); // Sanitize
            $filename = pathinfo($image, PATHINFO_FILENAME);
            $image_file = $current_folder . '/' . $image;
            $tag_file = $current_folder . '/' . $filename . '.txt';

            // Ensure the files exist and are within the current folder
            if (file_exists($image_file) && strpos(realpath($image_file), realpath($current_folder)) === 0) {
                unlink($image_file);
                $deleted_images[] = $image;
            }
            if (file_exists($tag_file) && strpos(realpath($tag_file), realpath($current_folder)) === 0) {
                unlink($tag_file);
            }
        }
        echo json_encode($deleted_images);
        exit;
    }
}

// Handle downloading of tags (for loading tags via AJAX)
if (isset($_GET['action']) && $_GET['action'] == 'get_tags') {
    $image = basename($_GET['image']); // Sanitize
    $filename = pathinfo($image, PATHINFO_FILENAME);
    $tag_file = $current_folder . '/' . $filename . '.txt';
    if (file_exists($tag_file)) {
        echo file_get_contents($tag_file);
    }
    exit;
}

// Handle download zip
if (isset($_GET['download_zip'])) {
    $zip = new ZipArchive();
    $zip_name = $current_folder . '.zip';
    if ($zip->open($zip_name, ZipArchive::CREATE) !== TRUE) {
        exit("Cannot open <$zip_name>\n");
    }
    $files = glob($current_folder . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            $zip->addFile($file, basename($file));
        }
    }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-disposition: attachment; filename=' . basename($zip_name));
    header('Content-Length: ' . filesize($zip_name));
    readfile($zip_name);
    unlink($zip_name); // Remove zip file after download
    exit;
}

// Get list of images
$images = glob($current_folder . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);

// Get list of previously used tags with counts
$tag_counts = [];
foreach ($images as $image) {
    $basename = basename($image);
    $filename = pathinfo($basename, PATHINFO_FILENAME);
    $tag_file = $current_folder . '/' . $filename . '.txt';
    if (file_exists($tag_file)) {
        $tags = file_get_contents($tag_file);
        $tags_array = array_map('trim', explode(',', $tags));
        foreach ($tags_array as $tag) {
            if ($tag !== '') {
                if (isset($tag_counts[$tag])) {
                    $tag_counts[$tag]++;
                } else {
                    $tag_counts[$tag] = 1;
                }
            }
        }
    }
}

// Sort tags descending by usage count
arsort($tag_counts);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Image Tagging</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; }
        .container { display: flex; height: 100vh; overflow: hidden; }
        .left { width: 25%; padding: 10px; box-sizing: border-box;
            display: flex; flex-direction: column; }
        .left-top { flex: 1; overflow-y: auto; }
        .left-bottom { margin-top: 10px; }
        .right { width: 75%; overflow-y: auto; padding: 10px;
            box-sizing: border-box; }
        .image-container { display: flex; flex-wrap: wrap; }
        .image-item {
            width: 150px;
            margin: 5px;
            position: relative;
            border: 2px solid transparent;
            cursor: pointer;
            display: inline-block;
            vertical-align: top;
        }
        .image-item.selected { border: 2px solid blue; }
        .image-wrapper {
            width: 150px;
            height: 150px;
            overflow: hidden;
        }
        .image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }
        .tags-input {
            width: 100%;
            box-sizing: border-box;
            height: 150px;
            resize: none;
            margin-top: 5px;
        }
        .tag { display: inline-block; background-color: #ddd;
            padding: 5px; margin: 2px; cursor: pointer; }
        .tag:hover { background-color: #ccc; }
        .button { padding: 10px; background-color: darkblue; color: white;
            border: none; cursor: pointer; margin: 5px 0; }
        .button:hover { background-color: blue; }
        h3 { margin-top: 0; }
        form { margin-bottom: 10px; }
        input[type="text"], input[type="file"] { width: 100%; margin-bottom: 5px; }
        .disabled { opacity: 0.5; pointer-events: none; }
        .error { color: red; }
    </style>
</head>
<body>

<div class="container">
    <div class="left">
        <div class="left-top">
            <h3>Previously Used Tags</h3>
            <button class="button" onclick="selectAllImages()">Select All</button>
            <button class="button" onclick="deselectAllImages()">Deselect All</button>
            <button class="button" onclick="deleteSelectedImages()">Delete Selected</button>
            <div id="previous-tags">
                <?php foreach ($tag_counts as $tag => $count): ?>
                    <span class="tag" data-tag="<?= htmlspecialchars($tag) ?>">
                        <?= htmlspecialchars($tag) ?> (<?= $count ?>)
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="left-bottom">
            <h3>Set Folder</h3>
            <form method="post">
                <input type="text" name="folder_name" placeholder="Folder Name"
                    value="<?= htmlspecialchars($_SESSION['folder']) ?>">
                <button type="submit" name="set_folder">Set Folder</button>
            </form>
            <?php if ($allow_upload): ?>
            <h3>Upload New Images</h3>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="files[]" multiple>
                <button type="submit">Upload Files</button>
            </form>
            <?php else: ?>
            <h3>Upload New Images</h3>
            <p class="error">Uploads are not allowed in the default folder. Please set a different folder.</p>
            <?php endif; ?>
            <button class="button" onclick="downloadZip()">
                Download All as ZIP</button>
        </div>
    </div>
    <div class="right">
        <div class="image-container">
            <?php foreach ($images as $image): ?>
                <?php
                $basename = basename($image);
                $filename = pathinfo($basename, PATHINFO_FILENAME);
                ?>
                <div class="image-item"
                    data-image="<?= htmlspecialchars($basename) ?>">
                    <div class="image-wrapper">
                        <img src="<?= htmlspecialchars($image) ?>" alt="">
                    </div>
                    <textarea class="tags-input"
                        placeholder="Tags (comma separated)"></textarea>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
var selectedImages = [];

document.addEventListener('DOMContentLoaded', function() {
    var imageItems = document.querySelectorAll('.image-item');
    imageItems.forEach(function(item) {
        var imageName = item.getAttribute('data-image');
        var textarea = item.querySelector('.tags-input');
        // Load tags
        loadTags(imageName, textarea);
        // Image click to select
        item.addEventListener('click', function(e) {
            if (e.target.tagName.toLowerCase() != 'textarea') {
                toggleSelection(item, imageName);
            }
        });
        // Auto-save tags when textarea loses focus
        textarea.addEventListener('blur', function() {
            saveTags(imageName, textarea.value);
        });
    });

    // Handle clicking on previously used tags
    var tags = document.querySelectorAll('.tag');
    tags.forEach(function(tag) {
        tag.addEventListener('click', function() {
            var tagText = tag.getAttribute('data-tag');
            if (selectedImages.length > 0) {
                addTagToSelectedImages(tagText);
            } else {
                alert('Please select images to add tag.');
            }
        });
    });
});

function toggleSelection(item, imageName) {
    item.classList.toggle('selected');
    var index = selectedImages.indexOf(imageName);
    if (index > -1) {
        selectedImages.splice(index, 1);
    } else {
        selectedImages.push(imageName);
    }
}

function selectAllImages() {
    selectedImages = [];
    var imageItems = document.querySelectorAll('.image-item');
    imageItems.forEach(function(item) {
        var imageName = item.getAttribute('data-image');
        if (!item.classList.contains('selected')) {
            item.classList.add('selected');
        }
        if (selectedImages.indexOf(imageName) == -1) {
            selectedImages.push(imageName);
        }
    });
}

function deselectAllImages() {
    selectedImages = [];
    var imageItems = document.querySelectorAll('.image-item');
    imageItems.forEach(function(item) {
        item.classList.remove('selected');
    });
}

function deleteSelectedImages() {
    if (selectedImages.length === 0) {
        alert('Please select images to delete.');
        return;
    }
    if (selectedImages.length > 10) {
        alert('You can delete a maximum of 10 images at a time.');
        return;
    }
    if (!confirm('Are you sure you want to delete the selected images?')) {
        return;
    }
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?= basename($_SERVER['PHP_SELF']); ?>', true);
    xhr.setRequestHeader('Content-type',
        'application/x-www-form-urlencoded');
    var data = 'action=delete_selected&selected_images[]='
        + selectedImages.map(encodeURIComponent).join('&selected_images[]=');
    xhr.onload = function() {
        if (xhr.status == 200) {
            try {
                var deletedImages = JSON.parse(xhr.responseText);
                deletedImages.forEach(function(imageName) {
                    var item = document.querySelector('.image-item[data-image="'
                        + imageName + '"]');
                    if (item) {
                        item.parentNode.removeChild(item);
                    }
                });
                selectedImages = [];
                alert('Selected images have been deleted.');
            } catch (e) {
                alert(xhr.responseText);
            }
        }
    };
    xhr.send(data);
}

function loadTags(imageName, textarea) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '<?= basename($_SERVER['PHP_SELF']); ?>?action=get_tags&image='
        + encodeURIComponent(imageName), true);
    xhr.onload = function() {
        if (xhr.status == 200) {
            textarea.value = xhr.responseText;
        }
    };
    xhr.send();
}

function saveTags(imageName, tags) {
    var cleanTagsStr = cleanTags(tags);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?= basename($_SERVER['PHP_SELF']); ?>', true);
    xhr.setRequestHeader('Content-type',
        'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status == 200) {
            updatePreviouslyUsedTags(cleanTagsStr);
        }
    };
    xhr.send('action=update_tags&image='
        + encodeURIComponent(imageName)
        + '&tags=' + encodeURIComponent(cleanTagsStr));
}

function cleanTags(tags) {
    var tagsArray = tags.split(',').map(function(t) {
        return t.trim();
    }).filter(function(t) {
        return t !== '';
    });
    return tagsArray.join(', ');
}

function updatePreviouslyUsedTags(newTags) {
    var tagsArray = [];
    if (typeof newTags === 'string') {
        tagsArray = newTags.split(',').map(function(t) {
            return t.trim();
        });
    } else if (Array.isArray(newTags)) {
        tagsArray = newTags;
    } else {
        tagsArray = [newTags];
    }
    var previousTagsContainer = document.getElementById('previous-tags');
    var previousTags = previousTagsContainer.querySelectorAll('.tag');
    var existingTags = {};
    previousTags.forEach(function(tag) {
        var tagText = tag.getAttribute('data-tag');
        existingTags[tagText] = tag;
    });
    tagsArray.forEach(function(tag) {
        if (tag != '') {
            if (!existingTags[tag]) {
                var span = document.createElement('span');
                span.className = 'tag';
                span.setAttribute('data-tag', tag);
                span.textContent = tag + ' (1)';
                span.addEventListener('click', function() {
                    if (selectedImages.length > 0) {
                        addTagToSelectedImages(tag);
                    } else {
                        alert('Please select images to add tag.');
                    }
                });
                previousTagsContainer.insertBefore(span, previousTagsContainer.firstChild);
            } else {
                // Increment the count
                var currentCount = parseInt(existingTags[tag].textContent.match(/\((\d+)\)$/)[1]);
                currentCount++;
                existingTags[tag].textContent = tag + ' (' + currentCount + ')';
                // Move the tag to the top
                previousTagsContainer.insertBefore(existingTags[tag], previousTagsContainer.firstChild);
            }
        }
    });
}

function addTagToSelectedImages(tag) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?= basename($_SERVER['PHP_SELF']); ?>', true);
    xhr.setRequestHeader('Content-type',
        'application/x-www-form-urlencoded');
    var data = 'action=add_tag&tag=' + encodeURIComponent(tag)
        + '&selected_images[]='
        + selectedImages.map(encodeURIComponent)
            .join('&selected_images[]=');
    xhr.onload = function() {
        if (xhr.status == 200) {
            selectedImages.forEach(function(imageName) {
                var item = document.querySelector('.image-item[data-image="'
                    + imageName + '"]');
                var textarea = item.querySelector('.tags-input');
                var tags = textarea.value.split(',').map(function(t) {
                    return t.trim();
                }).filter(function(t) {
                    return t !== '';
                });
                if (tags.indexOf(tag) == -1) {
                    tags.push(tag);
                    var cleanTagsStr = tags.join(', ');
                    textarea.value = cleanTagsStr;
                    updatePreviouslyUsedTags(tag);
                }
            });
        }
    };
    xhr.send(data);
}

function downloadZip() {
    window.location.href = '<?= basename($_SERVER['PHP_SELF']); ?>?download_zip=1';
}
</script>

</body>
</html>
