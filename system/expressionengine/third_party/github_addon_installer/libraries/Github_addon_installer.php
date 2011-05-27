<?php

class Github_addon_installer
{
	protected $curl_options = array(
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_FOLLOWLOCATION => TRUE,
		CURLOPT_SSL_VERIFYPEER => FALSE,//@TODO remove this
	);
	
	protected $temp_path;
	
	public function __construct()
	{
		$this->EE =& get_instance();
		
		$this->EE->load->helper('file');
		
		$this->temp_path = ($this->EE->config->item('github_addon_installer_temp_path')) ? $this->EE->config->item('github_addon_installer_temp_path') : realpath(dirname(__FILE__).'/../temp/').'/';//PATH_THIRD.'github_addon_installer/temp/';
	}
	
	public function addon_repo($params)
	{
		$this->EE->load->helper('array');
		
		$user = element('user', $params);
		$repo = element('repo', $params);
		$branch = (isset($params['branch'])) ? $params['branch'] : 'master';
		
		return new Github_addon_repo($user, $repo, $branch, $params);
	}
	
	public function fetch_raw()
	{
		$segments = func_get_args();
		
		return $this->curl('https://github.com/'.implode('/', $segments));
	}
	
	public function api_fetch_raw()
	{
		$segments = func_get_args();
		
		array_unshift($segments, 'api', 'v2', 'json');
		
		return call_user_func_array(array($this, 'fetch_raw'), $segments);
	}
	
	public function api_fetch_json()
	{
		$segments = func_get_args();
		
		$data = call_user_func_array(array($this, 'api_fetch_raw'), $segments);
		
		return ($data) ? json_decode($data) : FALSE;
	}
	
	public function has_git()
	{
		static $has_git;
		
		if (is_null($has_git))
		{
			$command = 'git --version';
			
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
	
	public function build_user_manifest($user, $forks = FALSE)
	{
		$data = $this->api_fetch_json('repos', 'show', $user);
		
		$output = array();
		
		foreach ($data->repositories as $repo)
		{
			if ($forks === FALSE && $repo->fork)
			{
				continue;
			}
			
			$output[$repo->name] = array(
				'user' => $user,
				'repo' => $repo->name,
			);
		}
		
		ksort($output);
		
		$output = var_export($output, TRUE);
		
		$output = preg_replace('/=>[\s\r\n]+/', '=> ', $output);
		
		$output = str_replace(array('array (', '  '), array('array(', "\t"), $output);
		
		return $output;
	}
	
	protected function curl($url)
	{
		$ch = curl_init($url);
		
		curl_setopt_array($ch, $this->curl_options);
		
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
 * $repo = $this->EE->github_addon_installer->addon_repo($user, $repo, $branch);
 * $repo->fetch('/path/to/dir/');
 * 
 */
class Github_addon_repo
{
	protected $errors = array();
	
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
			$this->add_error(lang('incomplete_repo_definition'));
			return FALSE;
		}
		
		$data = $this->EE->github_addon_installer->api_fetch_json('repos', 'show', $this->user, $this->repo, 'branches');
		
		if ( ! isset($data->branches->{$this->branch}))
		{
			$this->add_error(sprintf(lang('branch_not_found'), $this->branch));
			return FALSE;
		}
		
		$this->sha = $data->branches->{$this->branch};
		
		/* @TODO use this later */
		/*
		$data = $this->EE->github_addon_installer->api_fetch_json('repos', 'show', $this->user, $this->repo, 'tags');
		
		$this->tags = (isset($data->tags)) ? (array) $data->tags : FALSE;
		*/
		
		$data = $this->EE->github_addon_installer->api_fetch_json('repos', 'show', $this->user, $this->repo);
		
		if (isset($data->repository))
		{
			foreach ((array) $data->repository as $property => $value)
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
			$this->add_error(lang('path_third_not_writable'));
			return FALSE;
		}
		
		$temp_dir = NULL;
		$files = array();
		
		if ($this->fetch_mode === 'git')
		{
			$command_prefix = 'cd '.$this->EE->github_addon_installer->temp_path().'; ';
			
			//clone
			shell_exec($command_prefix.'git clone git://github.com/'.$this->user.'/'.$this->repo.'.git '.$this->sha);
			
			if ($this->branch !== 'master')
			{
				shell_exec($command_prefix.'git checkout '.$this->branch);
			}
			
			$temp_dir = $this->sha;
		}
		//use unzip
		else if ($this->fetch_mode === 'zip')
		{
			$this->EE->load->library('unzip');
			
			if ( ! is_really_writable($this->EE->github_addon_installer->temp_path()))
			{
				$this->add_error('temp_dir_not_writable');
				return FALSE;
			}
			$this->EE->session->set_flashdata('temp_path', $this->EE->github_addon_installer->temp_path());
			
			$file_path = $this->EE->github_addon_installer->temp_path().$this->sha.'.zip';
			
			//this is how github packages the zipball
			$temp_dir = $this->user.'-'.$this->repo.'-'.substr($this->sha, 0, 7);
			
			write_file($file_path, $this->zipball(), FOPEN_WRITE_CREATE_DESTRUCTIVE);
			
			$this->EE->unzip->extract($file_path, $this->EE->github_addon_installer->temp_path());
		}
		//grab the files one by one
		else
		{
			$data = $this->EE->github_addon_installer->api_fetch_json('blob', 'all', $this->user, $this->repo, $this->sha);
			
			if (isset($data->blobs))
			{
				$files = array_flip((array) $data->blobs);
			}
			
		}
		$this->EE->session->set_flashdata('files', $files);
		
		if ( ! is_null($temp_dir))
		{
			foreach(get_filenames($this->EE->github_addon_installer->temp_path().'/'.$temp_dir, TRUE) as $full_filename)
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
				if (strncmp($filename, $this->fetch_params['addon_path'], strlen($this->fetch_params['addon_path'])) !== 0)
				{
					$proceed = TRUE;
					
					$filename = str_replace($this->fetch_params['addon_path'], '', $filename);
		
					if (isset($this->fetch_params['add_folder']))
					{
						$filename = $this->fetch_params['add_folder'].'/'.$filename;
					}
				}
			}
			
			if ($proceed === FALSE && isset($this->fetch_params['theme_path']))
			{
				if (strncmp($filename, $this->fetch_params['theme_path'], strlen($this->fetch_params['theme_path'])) !== 0)
				{
					$proceed = TRUE;
				
					$filename = str_replace($this->fetch_params['addon_path'], '', $filename);
					
					$path = PATH_THEMES.'third_party/';
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
				$this->add_error(sprintf(lang('cant_write_file'), $path.$filename));
				return FALSE;
			}

			if ($this->fetch_mode === 'zip' || $this->fetch_mode === 'git')
			{
				$this->EE->session->set_flashdata('rename_fromm['.$i.']', $this->EE->github_addon_installer->temp_path().$temp_dir.'/'.$full_filename);
				$this->EE->session->set_flashdata('rename_to['.$i.']', $path.$filename);
				@rename($this->EE->github_addon_installer->temp_path().$temp_dir.'/'.$full_filename, $path.$filename);
			}
			else //$this->fetch_mode === 'files'
			{
				$sha = $i;//for clarity's sake; files mode files are indexed by sha
				
				write_file($path.$filename, $this->fetch_file($sha), FOPEN_WRITE_CREATE_DESTRUCTIVE);
			}
		}
		
		//clean up the temp files, if any
		switch($this->fetch_mode)
		{
			case 'zip':
				@unlink($this->EE->github_addon_installer->temp_path().$this->sha.'.zip');
			case 'git':
				delete_files($this->EE->github_addon_installer->temp_path().$temp_dir, TRUE);
				@rmdir($this->EE->github_addon_installer->temp_path().$temp_dir);
		}
	
		return !! $this->errors;
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
		return $this->EE->github_addon_installer->fetch_raw($this->user, $this->repo, 'zipball', $this->branch);
	}
	
	public function tarball()
	{
		return $this->EE->github_addon_installer->fetch_raw($this->user, $this->repo, 'tarball', $this->branch);
	}
	
	public function sha()
	{
		return $this->sha;
	}
	
	protected function fetch_file($sha)
	{
		return $this->EE->github_addon_installer->api_fetch_raw('blob', 'show', $this->user, $this->repo, $sha);
	}
	
	public function errors()
	{
		return $this->errors;
	}
	
	protected function add_error($msg)
	{
		$this->errors[] = $msg;
	}
}