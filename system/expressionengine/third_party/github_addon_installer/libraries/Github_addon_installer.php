<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Github_addon_installer
{
	protected $curl_options = array(
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_FOLLOWLOCATION => TRUE,
		CURLOPT_SSL_VERIFYPEER => FALSE,//@TODO remove this
	);

	protected $basic_auth_username;
	protected $basic_auth_password;
	
	protected $temp_path;
	
	public function __construct()
	{
		$this->EE =& get_instance();
		
		$this->EE->load->helper('file');
		
		$this->temp_path = $this->EE->config->item('github_addon_installer_temp_path') ? $this->EE->config->item('github_addon_installer_temp_path') : realpath(dirname(__FILE__).'/../temp/').'/';//PATH_THIRD.'github_addon_installer/temp/';
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
		$this->EE->load->helper('array');
		
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
	
	/**
	 * Whether the system has git installed
	 * 
	 * @return bool
	 */
	public function has_git()
	{
		//@TODO make this actually work, always returns false for now
		return FALSE;
		
		static $has_git;
		
		if (is_null($has_git))
		{
			$command = 'git --version';
			
			//mac path is screwy when run from php
			if (strncasecmp(PHP_OS, 'darwin', 6) === 0)
			{
				$command = 'PATH=$PATH:/usr/local/bin;'.$command;
			}
			
			$command .= (strncasecmp(PHP_OS, 'win', 3) === 0) ? ' > NUL;' : ' > /dev/null 2>&1;';
			
			exec($command, $output, $return_value);
			
			//$has_git = (bool) $output;
			$has_git = $return_value === 0;
		}
		
		return $has_git;
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
		
		if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200)
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
 * $this->EE->load->library('github_addon_installer');
 * $repo = $this->EE->github_addon_installer->repo($user, $repo, $branch);
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
	 * @var string fetch mode, can be 'git', 'zip' or 'files'
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
		$this->EE =& get_instance();
		
		$this->user = $user;
		$this->repo = $repo;
		$this->branch = $branch;
		$this->fetch_params = $fetch_params;
		
		if ( ! $this->user || ! $this->repo || ! $this->branch)
		{
			throw new Exception(lang('incomplete_repo_definition'));
		}
		
		$data = $this->EE->github_addon_installer->api_fetch_json('repos', $this->user, $this->repo, 'branches');
		
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
		$data = $this->EE->github_addon_installer->api_fetch_json('repos', 'show', $this->user, $this->repo, 'tags');
		
		$this->tags = (isset($data->tags)) ? (array) $data->tags : FALSE;
		*/
		
		$data = $this->EE->github_addon_installer->api_fetch_json('repos', $this->user, $this->repo);
		
		if (is_object($data))
		{
			foreach ((array) $data as $property => $value)
			{
				$this->$property = $value;
			}
		}
		
		if ($this->EE->github_addon_installer->has_git())
		{
			$this->fetch_mode = 'git';
		}
		else if (extension_loaded('zlib'))
		{
			$this->fetch_mode = 'zip';
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
		
		if ($this->fetch_mode === 'git')
		{
			//clone
			shell_exec('git clone https://github.com/'.$this->user.'/'.$this->repo.'.git '.$this->EE->github_addon_installer->temp_path().$this->sha);
			
			if ($this->branch !== 'master')
			{
				shell_exec('cd '.$this->EE->github_addon_installer->temp_path().'; git checkout '.$this->branch);
			}
			
			$temp_dir = $this->sha;
		}
		//use unzip
		else if ($this->fetch_mode === 'zip')
		{
			$this->EE->load->library('unzip');
			
			if ( ! is_really_writable($this->EE->github_addon_installer->temp_path()))
			{
				throw new Exception('temp_dir_not_writable');
			}
			
			$file_path = $this->EE->github_addon_installer->temp_path().$this->sha.'.zip';
			
			//@TODO remove this conditional
			if ( ! file_exists($file_path))
			{
				write_file($file_path, $this->zipball(), FOPEN_WRITE_CREATE_DESTRUCTIVE);
				
				$this->EE->unzip->extract($file_path, $this->EE->github_addon_installer->temp_path());
			}
			
			//this is how github names the zipball, usually
			$short_sha = substr($this->sha, 0, 7);
			
			//the sha numbering is off, else is a somewhat messy fallback
			if (is_dir($this->EE->github_addon_installer->temp_path().$this->user.'-'.$this->repo.'-'.$short_sha))
			{
				$temp_dir = $this->user.'-'.$this->repo.'-'.$short_sha;
			}
			else
			{
				$this->EE->load->helper('directory');
				
				$temp_dir = NULL;
				
				foreach (directory_map($this->EE->github_addon_installer->temp_path(), 2) as $dirname => $contents)
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
			$data = $this->EE->github_addon_installer->api_fetch_json('repos', $this->user, $this->repo, 'git', 'trees', $this->sha, array('recursive' => TRUE));
			
			if (isset($data->tree))
			{
				$files = array();

				foreach ($data->tree as $file)
				{
					$files[$file->sha] = $file->path;
				}
			}
		}
		
		if ( ! is_null($temp_dir) && $filenames = get_filenames($this->EE->github_addon_installer->temp_path().'/'.$temp_dir, TRUE))
		{
			foreach($filenames as $full_filename)
			{
				$files[] = str_replace($this->EE->github_addon_installer->temp_path().$temp_dir.'/', '', $full_filename);
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

			if ( ! is_null($temp_dir))
			{
				@rename($this->EE->github_addon_installer->temp_path().$temp_dir.'/'.$full_filename, $path.$filename);
			}
			else //aka. $this->fetch_mode === 'files'
			{
				$sha = $i;//for clarity's sake; files mode files are indexed by sha

				$data = $this->EE->github_addon_installer->api_fetch_json('repos', $this->user, $this->repo, 'git', 'blobs', $sha);
				
				if (isset($data->content))
				{
					write_file($path.$filename, $data->content, FOPEN_WRITE_CREATE_DESTRUCTIVE);
				}
			}
		}
		
		//clean up the temp files, if any
		switch($this->fetch_mode)
		{
			default:
				break;
			case 'zip':
				@unlink($this->EE->github_addon_installer->temp_path().$this->sha.'.zip');
			case 'git':
				delete_files($this->EE->github_addon_installer->temp_path().$temp_dir, TRUE);
				@rmdir($this->EE->github_addon_installer->temp_path().$temp_dir);
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
		return $this->EE->github_addon_installer->fetch_raw('repos', $this->user, $this->repo, 'zipball', $this->branch);
	}
	
	public function tarball()
	{
		return $this->EE->github_addon_installer->fetch_raw('repos', $this->user, $this->repo, 'tarball', $this->branch);
	}
	
	public function sha()
	{
		return $this->sha;
	}
}