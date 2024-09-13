# CommunityTagger
Collaborative tagging of images with a one-file WebUI for written in php and javascript.
# Why?
Invite friends to upload images and tag them together, to build high quality datasets for lora/hypernetwork training in stable diffusion. 
# Installation
1. Requirements: webserver, php, php-zip
2. Download the index.php file and copy it to your webserver
3. Create a "uploads" folder and give www-data write access to it (apache2)
4. Open the index.php in your webbrowser
# Usage
First upload your image files (jpg,jpeg,png,gif) to the uploads folder and the uploaded images will be shown in the WebUI.

There will be a text-window next to every image where you can type in the tags for this image.

A matching .txt file will be created with the same filename as the image file, so that f.e. stable diffusion can read the tags easily.

To tag multiple images with already used tags, first click on the images so that they get a blue border, and second click on the tags you want to apply to these images.

After tagging just click the download button to download a zip file with all image and txt files.

DON NOT forget to save and update after adding tags into the text fields!!!!
