<?php

namespace eecli\GithubAddonInstaller;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressHelper;
use Symfony\Component\Console\Helper\ProgressBar;

class Api
{
    protected $curlOptions = array(
        CURLOPT_USERAGENT      => 'EE Github Addon Installer',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,//@TODO remove this
    );

    protected $basicAuthUsername;

    protected $basicAuthPassword;

    protected $output;

    protected $progress;

    protected $progressBar;

    public function setBasicAuth($username, $password)
    {
        $this->basicAuthUsername = $username;
        $this->basicAuthPassword = $password;

        return $this;
    }

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function setProgressHelper(ProgressHelper $progress)
    {
        $this->progress = $progress;
    }

    public function setProgressBar(ProgressBar $progressBar)
    {
        $this->progressBar = $progressBar;
    }

    protected function buildUrl(array $segments, array $queryString = null)
    {
        $url = 'https://api.github.com/'.implode('/', $segments);

        if ($queryString) {
            $url .= '?'.http_build_query($queryString);
        }

        return $url;
    }

    /**
     * Fetch raw data from a github url
     *
     * @param  string $segment,... unlimited number of segments
     * @param  array  $params      an optional array of query string params
     * @return mixed
     */
    public function fetch()
    {
        $segments = func_get_args();

        $queryString = is_array(end($segments)) ? array_pop($segments) : null;

        $url = $this->buildUrl($segments, $queryString);

        return $this->curl($url);
    }

    public function fetchProgress()
    {
        $segments = func_get_args();

        $queryString = is_array(end($segments)) ? array_pop($segments) : null;

        $url = $this->buildUrl($segments, $queryString);

        $progress = $this->progress ? $this->progress : $this->progressBar;

        if ($progress && $this->output) {
            $output = $this->output;
            $current = 0;

            if ($progress instanceof ProgressBar) {
                $progress->setMaxSteps(100);
                $progress->start();
            } else {
                $progress->start($output, 100);
            }

            $return = $this->curl($url, function($downloadSize, $downloadedSize) use ($progress, $output) {
                $current = $downloadSize !== 0 ? round($downloadedSize / $downloadSize * 100) : 0;

                if ($progress instanceof ProgressBar) {
                    $progress->setProgress($current);
                } else {
                    $progress->setCurrent($current);
                }
            });

            $progress->finish();

            return $return;
        }

        return $this->curl($url);
    }

    /**
     * Fetch json data from the github v3 api
     *
     * @param  string     $segment,... unlimited number of segments
     * @param  array      $params      an optional array of query string params
     * @return mixed|null
     */
    public function fetchJson()
    {
        $segments = func_get_args();

        $queryString = is_array(end($segments)) ? array_pop($segments) : null;

        $url = $this->buildUrl($segments, $queryString);

        $data = $this->curl($url);

        return ($data) ? @json_decode($data) : null;
    }

    protected function curl($url, \Closure $progressCallback = null)
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, $this->curlOptions);

        if ($this->basicAuthUsername && $this->basicAuthPassword) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->basicAuthUsername.':'.$this->basicAuthPassword);
        }

        if (is_callable($progressCallback)) {
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, $progressCallback);
        }

        $data = curl_exec($ch);

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($status === 403) {
            $json = json_decode($data);

            throw new \Exception($json->message);
        } elseif ($status !== 200) {
            $data = false;
        }

        curl_close($ch);

        return $data;
    }
}
