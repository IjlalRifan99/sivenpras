<?php
echo "ZipArchive: " . (class_exists('ZipArchive') ? 'YES' : 'NO') . "\n";
echo "Temp Dir: " . sys_get_temp_dir() . "\n";
echo "Write Permission: " . (is_writable(sys_get_temp_dir()) ? 'YES' : 'NO') . "\n";
?>
