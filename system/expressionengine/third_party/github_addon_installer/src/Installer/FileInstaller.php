<?php

namespace eecli\GithubAddonInstaller\Installer;

use eecli\GithubAddonInstaller\Repo;

class FileInstaller extends Installer
{
    protected function copyFile(Repo $repo, $file, $destination)
    {
        $data = $repo->getBlob($file->sha);

        if (isset($data->content)) {
            $handle = fopen($destination, 'w');

            fwrite($handle, base64_decode($data->content));

            fclose($handle);
        }
    }
}
