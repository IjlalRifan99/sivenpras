<?php
echo "ZipArchive Available: ";
if (class_exists('ZipArchive')) {
    echo "✓ YES";
} else {
    echo "✗ NO";
}
?>
