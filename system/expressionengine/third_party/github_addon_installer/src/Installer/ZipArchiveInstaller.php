<?php

namespace eecli\GithubAddonInstaller\Installer;

use ZipArchive;

class ZipArchiveInstaller extends AbstractZipInstaller
{
    public function unzip($filePath)
    {
        $zip = new ZipArchive();

        $zip->open($filePath);

        $zip->extractTo($this->tempPath);

        $zip->close();

        unset($zip);
    }
}
