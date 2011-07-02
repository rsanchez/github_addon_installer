# GitHub Addon Installer for ExpressionEngine #



## Installation

* Copy the system/expressionengine/third_party/github_addon_installer/ folder to system/expressionengine/third_party/
* Install the module
* Make sure your system/expressionengine/third_party/ & github_addon_installer/temp/ directories are writable

## Requirements

* ExpressionEngine 2.1.3+
* PHP 5.2+
* *nix server (no Windows/IIS)

## Config

If you wish to change your temp dir location, you can add this to your EE config file:

	$config['github_addon_installer_temp_path'] = '/path/to/dir/';

## Adding an Addon to the Manifest

The list of eligible addons is stored in github_addon_installer/config/manifest.js. This file contains a JSON object. To add something to this list, fork this project, add repos to the list, and submit a pull request. The key of your manifest entry should be the short name of your add-on.

Manifest entry examples:

If your repo directory structure is like:

	my_addon/

Manifest Entry:

	"my_addon":{
		"user":"username",
		"repo":"reponame"
	}

If your repo directory structure is like:

	pi.my_addon.php

Manifest Entry:

	"my_addon":{
		"user":"username",
		"repo":"reponame",
		"add_folder":true
	}

If your repo directory structure is like:

	system/expressionengine/third_party/my_addon/

Manifest Entry:

	"my_addon":{
		"user":"username",
		"repo":"reponame",
		"addon_path":"system/expressionengine/third_party/"
	}

If your repo directory structure is like:

	ee2/third_party/my_addon/
	themes/third_party/my_addon/

Manifest Entry:

	"my_addon":{
		"user":"username",
		"repo":"reponame",
		"addon_path":"ee2/third_party/",
		"theme_path":"themes/third_party/"
	}
