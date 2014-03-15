<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Github_addon_installer
{
	protected $curl_options = array(
		CURLOPT_USERAGENT      => 'EE Github Addon Installer',
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_FOLLOWLOCATION => TRUE,
		CURLOPT_SSL_VERIFYPEER => FALSE,//@TODO remove this
	);

	protected $basic_auth_username;
	protected $basic_auth_password;

	protected $temp_path;

	public function __construct()
	{
		ee()->load->helper('file');

		if ($temp_path = ee()->config->item('github_addon_installer_temp_path'))
		{
			$this->temp_path = $temp_path;
		}
		else
		{
			$this->temp_path = APPPATH.'cache/github_addon_installer/';

			if ( ! is_dir($this->temp_path))
			{
				mkdir($this->temp_path);
			}
		}
	}

	public function set_basic_auth($username, $password)
	{
		$this->basic_auth_username = $username;
		$this->basic_auth_password = $password;
		return $this;
	}

	/**
	 * Repo
	 *
	 * creates a new instance of Github_addon_repo object
	 *
	 * @param array $params user, repo, branch, etc.
	 *
	 * @return Github_addon_repo
	 */
	public function repo($params)
	{
		ee()->load->helper('array');

		$user = element('user', $params);
		$repo = element('repo', $params);
		$branch = (isset($params['branch'])) ? $params['branch'] : 'master';

		return new Github_addon_repo($user, $repo, $branch, $params);
	}

	/**
	 * Fetch raw data from a github url
	 *
	 * @param string $segment,... unlimited number of segments
	 * @param array $params an optional array of query string params
	 * @return mixed
	 */
	public function fetch_raw()
	{
		$segments = func_get_args();

		$query_string = is_array(end($segments)) ? '?'.http_build_query(array_pop($segments)) : '';

		return $this->curl('https://api.github.com/'.implode('/', $segments).$query_string);
	}

	/**
	 * Fetch raw data from the github v3 api
	 *
	 * @param string $segment,... unlimited number of segments
	 * @return mixed
	 */
	public function api_fetch_raw()
	{
		$segments = func_get_args();

		return call_user_func_array(array($this, 'fetch_raw'), $segments);
	}

	/**
	 * Fetch json data from the github v3 api
	 *
	 * @param string $segment,... unlimited number of segments
	 * @return array|false
	 */
	public function api_fetch_json()
	{
		$segments = func_get_args();

		$data = call_user_func_array(array($this, 'api_fetch_raw'), $segments);

		return ($data) ? json_decode($data) : FALSE;
	}

	public function temp_path()
	{
		return $this->temp_path;
	}

	protected function curl($url)
	{
		$ch = curl_init($url);

		curl_setopt_array($ch, $this->curl_options);

		if ($this->basic_auth_username && $this->basic_auth_password)
		{
			curl_setopt($ch, CURLOPT_USERPWD, $this->basic_auth_username.':'.$this->basic_auth_password);
		}

		$data = curl_exec($ch);

		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ($status === 403)
		{
			$json = json_decode($data);

			throw new Exception($json->message);
		}
		elseif ($status !== 200)
		{
			$data = FALSE;
		}

		curl_close($ch);

		return $data;
	}
}

/**
 * This class is here and not in it's own file b/c
 * it's not meant to be instantiated by CI
 *
 * example
 *
 * ee()->load->library('github_addon_installer');
 * $repo = ee()->github_addon_installer->repo($user, $repo, $branch);
 * $repo->fetch('/path/to/dir/');
 *
 */
class Github_addon_repo
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
	 * @var array An array of files in the current branch, sha => filename
	 */
	protected $files;

	/**
	 * @var string fetch mode, can be 'zlib' or 'files'
	 */
	protected $fetch_mode;

	/**
	 * @var array fetch options
	 */
	protected $fetch_params;

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

	public function __construct($user, $repo, $branch = 'master', $fetch_params = array())
	{
		$this->user = $user;
		$this->repo = $repo;
		$this->branch = $branch;
		$this->fetch_params = $fetch_params;

		if ( ! $this->user || ! $this->repo || ! $this->branch)
		{
			throw new Exception(lang('incomplete_repo_definition'));
		}

		$data = ee()->github_addon_installer->api_fetch_json('repos', $this->user, $this->repo, 'branches');

		if (empty($data))
		{
			throw new Exception(sprintf(lang('repo_not_found'), 'https://github.com/'.$this->user.'/'.$this->repo, $this->repo));
		}

		$branch = NULL;

		foreach ($data as $row)
		{
			if ($row->name === $this->branch)
			{
				$branch = $row;

				break;
			}
		}

		if ( ! $branch)
		{
			throw new Exception(sprintf(lang('branch_not_found'), 'https://github.com/'.$this->user.'/'.$this->repo.'/tree/'.$this->branch, $this->branch));
		}

		$this->sha = $branch->commit->sha;

		/* @TODO use this later */
		/*
		$data = ee()->github_addon_installer->api_fetch_json('repos', 'show', $this->user, $this->repo, 'tags');

		$this->tags = (isset($data->tags)) ? (array) $data->tags : FALSE;
		*/

		$data = ee()->github_addon_installer->api_fetch_json('repos', $this->user, $this->repo);

		if (is_object($data))
		{
			foreach ((array) $data as $property => $value)
			{
				$this->$property = $value;
			}
		}

		if (extension_loaded('zlib'))
		{
			$this->fetch_mode = 'zlib';
		}
		else
		{
			$this->fetch_mode = 'files';
		}
	}

	public function install()
	{
		if ( ! is_really_writable(PATH_THIRD))
		{
			throw new Exception(lang('path_third_not_writable'));
		}

		$temp_dir = NULL;
		$files = array();

		//use unzip
		if ($this->fetch_mode === 'zlib')
		{
			ee()->load->library('unzip');

			if ( ! is_really_writable(ee()->github_addon_installer->temp_path()))
			{
				throw new Exception('temp_dir_not_writable: '.ee()->github_addon_installer->temp_path());
			}

			$file_path = ee()->github_addon_installer->temp_path().$this->sha.'.zip';

			//@TODO remove this conditional
			if ( ! file_exists($file_path))
			{
				write_file($file_path, $this->zipball(), FOPEN_WRITE_CREATE_DESTRUCTIVE);

				ee()->unzip->extract($file_path, ee()->github_addon_installer->temp_path());
			}

			//this is how github names the zipball, usually
			$short_sha = substr($this->sha, 0, 7);

			//the sha numbering is off, else is a somewhat messy fallback
			if (is_dir(ee()->github_addon_installer->temp_path().$this->user.'-'.$this->repo.'-'.$short_sha))
			{
				$temp_dir = $this->user.'-'.$this->repo.'-'.$short_sha;
			}
			else
			{
				ee()->load->helper('directory');

				$temp_dir = NULL;

				foreach (directory_map(ee()->github_addon_installer->temp_path(), 2) as $dirname => $contents)
				{
					//it's not a dir, move on
					if (is_array($contents) && preg_match('/^'.preg_quote($this->user).'-'.preg_quote($this->repo).'-/', $dirname))
					{
						$temp_dir = $dirname;
					}
				}
			}
		}
		//grab the files one by one
		else
		{
			$data = ee()->github_addon_installer->api_fetch_json('repos', $this->user, $this->repo, 'git', 'trees', $this->sha, array('recursive' => TRUE));

			if (isset($data->tree))
			{
				$files = array();

				foreach ($data->tree as $file)
				{
					if ($file->type !== 'tree')
					{
						$files[$file->sha] = $file->path;
					}
				}
			}
		}

		if ( ! is_null($temp_dir) && $filenames = get_filenames(ee()->github_addon_installer->temp_path().'/'.$temp_dir, TRUE))
		{
			foreach($filenames as $full_filename)
			{
				$files[] = str_replace(ee()->github_addon_installer->temp_path().$temp_dir.'/', '', $full_filename);
			}
		}

		foreach ($files as $i => $full_filename)
		{
			$path = PATH_THIRD;

			$filename = $full_filename;

			$proceed = FALSE;

			if (isset($this->fetch_params['addon_path']))
			{
				if (strncmp($filename, $this->fetch_params['addon_path'], strlen($this->fetch_params['addon_path'])) === 0)
				{
					$proceed = TRUE;

					$filename = str_replace($this->fetch_params['addon_path'], '', $filename);

					if (isset($this->fetch_params['add_folder']))
					{
						$filename = (is_bool($this->fetch_params['add_folder'])) ? $this->fetch_params['name'].'/'.$filename : $this->fetch_params['add_folder'].'/'.$filename;
					}
				}
			}

			if ($proceed === FALSE && isset($this->fetch_params['theme_path']))
			{
				if (strncmp($filename, $this->fetch_params['theme_path'], strlen($this->fetch_params['theme_path'])) === 0)
				{
					$proceed = TRUE;

					$filename = str_replace($this->fetch_params['theme_path'], '', $filename);

					$path = PATH_THIRD_THEMES;
				}
			}

			//@TODO think about this
			if ( ! isset($this->fetch_params['addon_path']) && $proceed === FALSE)
			{
				$_proceed = TRUE;

				foreach ($this->ignore as $ignore)
				{
					if (strncasecmp($filename, $ignore, strlen($ignore)) === 0)
					{
						$_proceed = FALSE;
						break;
					}
				}

				if ($_proceed)
				{
					$proceed = TRUE;

					if (isset($this->fetch_params['add_folder']))
					{
						$filename = (is_bool($this->fetch_params['add_folder'])) ? $this->fetch_params['name'].'/'.$filename : $this->fetch_params['add_folder'].'/'.$filename;
					}
				}
			}

			if ($proceed === FALSE)
			{
				continue;
			}

			if (strpos($filename, '/') !== FALSE)
			{
				$path = realpath($path);

				$dirs = explode('/', $filename);

				$path .= '/';

				$filename = array_pop($dirs);

				foreach ($dirs as $dir)
				{
					if ( ! is_dir($path.$dir))
					{
						@mkdir($path.$dir, 0777);
					}

					$path .= $dir.'/';
				}
			}

			if ( ! is_really_writable($path) || (file_exists($path.$filename) && ! is_really_writable($path.$filename)))
			{
				throw new Exception(sprintf(lang('cant_write_file'), $path.$filename));
			}

			if ($this->fetch_mode === 'zlib')
			{
				@rename(ee()->github_addon_installer->temp_path().$temp_dir.'/'.$full_filename, $path.$filename);
			}
			else //aka. $this->fetch_mode === 'files'
			{
				$sha = $i;//for clarity's sake; files mode files are indexed by sha

				$data = ee()->github_addon_installer->api_fetch_json('repos', $this->user, $this->repo, 'git', 'blobs', $sha);

				if (isset($data->content))
				{
					write_file($path.$filename, base64_decode($data->content), FOPEN_WRITE_CREATE_DESTRUCTIVE);
				}
			}
		}

		//clean up the temp files, if any
		switch($this->fetch_mode)
		{
			default:
				break;
			case 'zlib':
				@unlink(ee()->github_addon_installer->temp_path().$this->sha.'.zip');
		}
	}

	protected function parse_filename(&$path, &$filename)
	{
		if (strpos($filename, '/') === FALSE)
		{
			return;
		}

		$path = realpath($path);

		$dirs = explode('/', $filename);

		$path .= '/';

		$filename = array_pop($dirs);

		foreach ($dirs as $dir)
		{
			if ( ! is_dir($path.$dir))
			{
				@mkdir($path.$dir);
				@chmod($path.$dir, 0777);
			}

			$path .= $dir.'/';
		}
	}

	public function zipball()
	{
		return ee()->github_addon_installer->fetch_raw('repos', $this->user, $this->repo, 'zipball', $this->branch);
	}

	public function tarball()
	{
		return ee()->github_addon_installer->fetch_raw('repos', $this->user, $this->repo, 'tarball', $this->branch);
	}

	public function sha()
	{
		return $this->sha;
	}
}