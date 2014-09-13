<?php

namespace eecli\GithubAddonInstaller;

class Repo
{
    /**
     * @var string GitHub user
     */
    protected $user;

    /**
     * @var string GitHub repo
     */
    protected $repo;

    /**
     * @var string GitHub branch
     */
    protected $branch;

    /**
     * @var string The sha hash of the current branch
     */
    protected $sha;

    /**
     * @var array ignore files
     */
    protected $ignore = array(
        'readme',
        'docs',
        '.gitignore',
        'changelog',
        'license',
    );

    /**
     * GitHub default repo properties
     */
    public $url;
    public $has_issues;
    public $homepage;
    public $watchers;
    public $source;
    public $parent;
    public $has_downloads;
    public $created_at;
    public $forks;
    public $fork;
    public $has_wiki;
    public $private;
    public $pushed_at;
    public $name;
    public $description;
    public $owner;
    public $open_issues;

    public function __construct(Api $api, $user, $repo, $branch = 'master')
    {
        if (! $user) {
            throw new \Exception('You must set a Github user.');
        }

        if (! $repo) {
            throw new \Exception('You must set a Github repo.');
        }

        if (! $branch) {
            throw new \Exception('You must set a git branch.');
        }

        $this->api = $api;

        $this->user = $user;
        $this->repo = $repo;
        $this->branch = $branch;

        $data = $this->api->fetchJson('repos', $this->user, $this->repo, 'branches');

        if (empty($data)) {
            throw new \Exception(sprintf('Repo %s not found', 'https://github.com/'.$this->user.'/'.$this->repo, $this->repo));
        }

        $branch = null;

        foreach ($data as $row) {
            if ($row->name === $this->branch) {
                $branch = $row;
                break;
            }
        }

        if (! $branch) {
            throw new \Exception(sprintf('Branch %s not found', 'https://github.com/'.$this->user.'/'.$this->repo.'/tree/'.$this->branch, $this->branch));
        }

        $this->sha = $branch->commit->sha;

        /* @TODO use this later */
        /*
        $data = $this->api->fetchJson('repos', 'show', $this->user, $this->repo, 'tags');

        $this->tags = (isset($data->tags)) ? (array) $data->tags : FALSE;
        */

        $data = $this->api->fetchJson('repos', $this->user, $this->repo);

        if (is_object($data)) {
            foreach ((array) $data as $property => $value) {
                $this->$property = $value;
            }
        }
    }

    public function getFiles()
    {
        $files = array();

        $data = $this->api->fetchJson('repos', $this->user, $this->repo, 'git', 'trees', $this->sha, array('recursive' => true));

        if (isset($data->tree)) {
            foreach ($data->tree as $file) {
                if ($file->type !== 'tree') {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    public function getZipball()
    {
        return $this->api->fetchProgress('repos', $this->user, $this->repo, 'zipball', $this->branch);
    }

    public function getBlob($sha)
    {
        return $this->api->fetchJson('repos', $this->user, $this->repo, 'git', 'blobs', $sha);
    }

    public function getTrees($recursive = false)
    {
        return $this->api->fetchJson('repos', $this->user, $this->repo, 'git', 'trees', $this->sha, array('recursive' => $recursive));
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getRepo()
    {
        return $this->repo;
    }

    public function getBranch()
    {
        return $this->branch;
    }

    public function getSha()
    {
        return $this->sha;
    }

    public function isFileIgnored($filename)
    {
        foreach ($this->ignore as $ignore) {
            if (strncasecmp($filename, $ignore, strlen($ignore)) === 0) {
                return true;
            }
        }

        return false;
    }
}
