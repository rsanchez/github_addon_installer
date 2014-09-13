<?php

namespace eecli\GithubAddonInstaller\Installer;

class SystemUnzipInstaller extends AbstractZipInstaller
{
    public function unzip($filePath)
    {
        system(sprintf('unzip -o -d %s %s', $this->tempPath, $filePath));
    }
}
