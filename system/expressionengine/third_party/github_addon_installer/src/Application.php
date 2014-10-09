<?php

namespace eecli\GithubAddonInstaller;

use eecli\GithubAddonInstaller\Installer\Installer;
use eecli\GithubAddonInstaller\Api;
use eecli\GithubAddonInstaller\Repo;

class Application
{
    protected $installer;

    protected $api;

    protected $manifest;

    protected $output;

    protected $progress;

    public function __construct($thirdPartyPath, $themePath, $tempPath)
    {
        $manifestPath = __DIR__.'/../config/manifest.js';

        if (! is_readable($manifestPath)) {
            throw new \Exception('Could not find a readable Github Addon Installer manifest.');
        }

        $manifestContents = file_get_contents($manifestPath);

        if ($manifestContents === false) {
            throw new \Exception('Could not load the Github Addon Installer manifest.');
        }

        $this->manifest = json_decode($manifestContents, true);

        if (! $this->manifest) {
            throw new \Exception('Could not load the Github Addon Installer manifest.');
        }

        ksort($this->manifest);

        $this->api = new Api();

        $this->installer = Installer::create($thirdPartyPath, $themePath, $tempPath);
    }

    public function getApi()
    {
        return $this->api;
    }

    public function getInstaller()
    {
        return $this->installer;
    }

    public function getManifest()
    {
        return $this->manifest;
    }

    public function getRepo($addon, $branch = null)
    {
        if (! isset($this->manifest[$addon])) {
            throw new \Exception('Addon not found in manifest.');
        }

        if (! $branch) {
            $branch = isset($this->manifest[$addon]['branch']) ? isset($this->manifest[$addon]['branch']) : 'master';
        }

        return new Repo(
            $this->api,
            $this->manifest[$addon]['user'],
            $this->manifest[$addon]['repo'],
            $branch
        );
    }

    public function installAddon($addon, $branch = null)
    {
        return $this->installer->install(
            $this->getRepo($addon, $branch),
            $addon,
            isset($this->manifest[$addon]['addon_path']) ? $this->manifest[$addon]['addon_path'] : null,
            isset($this->manifest[$addon]['theme_path']) ? $this->manifest[$addon]['theme_path'] : null,
            isset($this->manifest[$addon]['add_folder']) ? $this->manifest[$addon]['add_folder'] : false
        );
    }
}
