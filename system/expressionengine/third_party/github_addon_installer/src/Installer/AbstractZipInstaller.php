<?php

namespace eecli\GithubAddonInstaller\Installer;

use eecli\GithubAddonInstaller\Repo;

abstract class AbstractZipInstaller extends Installer
{
    protected $tempDir;

    abstract public function unzip($filePath);

    public function install(Repo $repo, $addonName, $addonPath = null, $themePath = null, $addFolder = null)
    {
        $filePath = $this->tempPath.$repo->getSha().'.zip';

        if ( ! file_exists($filePath)) {
            $handle = fopen($filePath, 'w');

            fwrite($handle, $repo->getZipball());

            fclose($handle);

            $this->unzip($filePath);
        }

        //this is how github names the zipball, usually
        $shortSha = substr($repo->getSha(), 0, 7);

        $tempDir = $repo->getUser().'-'.$repo->getRepo().'-'.$shortSha;

        $files = array();

        //the sha numbering is off, else is a somewhat messy fallback
        if (! is_dir($this->tempPath.$tempDir)) {
            $tempDir = null;

            $iterator = new \DirectoryIterator($this->tempPath);

            foreach ($iterator as $file) {
                // does it start with this user/repo combo?
                if (! $file->isDot() && $file->isDir() && preg_match('/^'.preg_quote($repo->getUser()).'-'.preg_quote($repo->getRepo()).'-/', $file->getBasename())) {
                    $tempDir = $file->getBasename();
                    break;
                }
            }
        }

        if (is_null($tempDir)) {
            throw new \Exception(sprintf('Could not find unzipped contents for %s/%s in %s', $repo->getUser(), $repo->getRepo(), $this->tempPath));
        }

        $this->tempDir = $tempDir;

        parent::install($repo, $addonName, $addonPath, $themePath, $addFolder);

        unlink($this->tempPath.$repo->getSha().'.zip');
    }

    protected function copyFile(Repo $repo, $file, $destination)
    {
        rename($this->tempPath.$this->tempDir.'/'.$file->path, $destination);
    }
}
