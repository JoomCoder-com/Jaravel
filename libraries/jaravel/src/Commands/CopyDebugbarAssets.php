<?php

namespace Jaravel\Commands;


use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\File;

class CopyDebugbarAssets
{
/**
* Copy debugbar assets to the media directory
*
* @return bool Success or failure
*/
public static function execute()
{
// Source path (in vendor directory)
$sourcePath = JPATH_LIBRARIES . '/jaravel/vendor/maximebf/debugbar/src/DebugBar/Resources';

// Destination path
$destPath = JPATH_ROOT . '/media/jaravel/vendor/maximebf/debugbar/src/DebugBar/Resources';

// Ensure the destination directory exists
if (!is_dir($destPath)) {
Folder::create($destPath, 0755);
}

// Copy all files
try {
Folder::copy($sourcePath, $destPath);
return true;
} catch (\Exception $e) {
return false;
}
}
}