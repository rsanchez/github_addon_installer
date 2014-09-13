<?php

namespace eecli\GithubAddonInstaller\Installer;

use eecli\GithubAddonInstaller\Repo;

abstract class Installer
{
    protected $thirdPartyPath;

    protected $themePath;

    protected $tempPath;

    public function __construct($thirdPartyPath, $themePath, $tempPath)
    {
        $this->thirdPartyPath = $thirdPartyPath;

        $this->themePath = $themePath;

        $this->tempPath = $tempPath;
    }

    public static function create($thirdPartyPath, $themePath, $tempPath)
    {
        if (extension_loaded('zip')) {
            return new ZipArchiveInstaller($thirdPartyPath, $themePath, $tempPath);
        } elseif (shell_exec('which unzip')) {
            return new SystemUnzipInstaller($thirdPartyPath, $themePath, $tempPath);
        }

        return new FileInstaller($thirdPartyPath, $themePath, $tempPath);
    }

    abstract protected function copyFile(Repo $repo, $file, $destination);

    public function install(Repo $repo, $addonName, $addonPath = null, $themePath = null, $addFolder = false)
    {
        foreach ($repo->getFiles() as $file) {
            $path = $this->thirdPartyPath;

            $destination = $file->path;

            $proceed = false;

            if ($addonPath) {
                if (strncmp($destination, $addonPath, strlen($addonPath)) === 0) {
                    $proceed = true;

                    $destination = str_replace($addonPath, '', $destination);

                    if ($addFolder) {
                        $destination = is_bool($addFolder) ? $addonName.'/'.$destination : $addFolder.'/'.$destination;
                    }
                }
            }

            if ($proceed === false && $themePath) {
                if (strncmp($destination, $themePath, strlen($themePath)) === 0) {
                    $proceed = true;

                    $destination = str_replace($themePath, '', $destination);

                    $path = $this->themePath;
                }
            }

            if (! $addonPath && $proceed === false && ! $repo->isFileIgnored($destination)) {
                $proceed = true;

                if ($addFolder) {
                    $destination = is_bool($addFolder) ? $addonName.'/'.$destination : $addFolder.'/'.$destination;
                }
            }

            if ($proceed === false) {
                continue;
            }

            if (strpos($destination, '/') !== false) {
                $path = realpath($path);

                $dirs = explode('/', $destination);

                $path .= '/';

                $destination = array_pop($dirs);

                foreach ($dirs as $dir) {
                    if (! is_dir($path.$dir)) {
                        @mkdir($path.$dir, 0777);
                    }

                    $path .= $dir.'/';
                }
            }

            $destination = $path.$destination;

            if (! is_writable($path) || (file_exists($destination) && ! is_writable($destination))) {
                throw new \Exception(sprintf('Cannot write file %s', $destination));
            }

            $this->copyFile($repo, $file, $destination);
        }
    }
}
