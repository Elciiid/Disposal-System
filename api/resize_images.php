<?php
function resizeImage($sourcePath, $targetPath, $newWidth, $newHeight) {
    list($width, $height) = getimagesize($sourcePath);
    $img = imagecreatefrompng($sourcePath);
    $tmp = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency
    imagealphablending($tmp, false);
    imagesavealpha($tmp, true);
    $transparent = imagecolorallocatealpha($tmp, 255, 255, 255, 127);
    imagefilledrectangle($tmp, 0, 0, $newWidth, $newHeight, $transparent);
    
    imagecopyresampled($tmp, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    imagepng($tmp, $targetPath, 9); // Max compression
}

$dir = 'c:/xampp/htdocs/production_waste/assets/img/products/';
$targets = ['bread.png', 'macaron.png', 'tart_shell.png', 'les_chouchous.png']; // Also shrinking others if they are too big

foreach ($targets as $f) {
    if (file_exists($dir.$f)) {
        echo "Resizing $f...\n";
        resizeImage($dir.$f, $dir.$f, 800, 800);
    }
}
?>
