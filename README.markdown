# GitHub Addon Installer for ExpressionEngine

Install and update free EE addons found on Github.

![GitHub Addon Installer Screenshot](http://f.cl.ly/items/0b1z031o2l3g2X221E1Z/Screen%20shot%202011-07-04%20at%2012.09.38%20PM.png)

## Installation

* Copy the system/expressionengine/third_party/github_addon_installer/ folder to system/expressionengine/third_party/
* Install the module
* Make sure your system/expressionengine/cache/ and system/expressionengine/third_party/ (or your user-defined third_party) directories are writable

## Note

This addon is not meant to be run on a production environment. You are encouraged to use this addon on local and/or staging environments only.

## Updating

This addon does not use traditional version releases, but rather rolling releases, as new addons are added to and removed from the manifest. You are encouraged to use Github Addon Installer to update itself.

## Requirements

* ExpressionEngine 2.6+
* PHP 5.2+
* *nix server (no Windows/IIS)

## Config

If you wish to change your temp dir location, you can add this to your EE config file:

    $config['github_addon_installer_temp_path'] = '/path/to/dir/'; # default is system/expressionengine/cache/github_addon_installer/

You can disable Github Addon Installer with a config item. This is useful for disabling in production environments.

    $config['github_addon_installer_disabled'] = $_SERVER['HTTP_HOST'] === 'my-production-site.com';

## Adding an Addon to the Manifest

The list of eligible addons is stored in github_addon_installer/config/manifest.js. This file contains a JSON object. To add something to this list, fork this project, add repos to the list, and submit a pull request. The key of your manifest entry should be the short name of your add-on.

Manifest entry examples:

If your repo directory structure is like:

    -my_addon
     -pi.my_addon.php

Manifest Entry:

    "my_addon":{
      "user":"username",
      "repo":"reponame"
    }

If your repo directory structure is like:

    -pi.my_addon.php

Manifest Entry:

    "my_addon":{
      "user":"username",
      "repo":"reponame",
      "add_folder":true
    }

If your repo directory structure is like:

    -system
     -expressionengine
      -third_party
       -my_addon
        -pi.my_addon.php

Manifest Entry:

    "my_addon":{
      "user":"username",
      "repo":"reponame",
      "addon_path":"system/expressionengine/third_party/"
    }

If your repo directory structure is like:

    -ee2
     -third_party
      -my_addon
       -pi.my_addon.php
    -themes
     -third_party
       -my_addon
        -my_addon.css

Manifest Entry:

    "my_addon":{
      "user":"username",
      "repo":"reponame",
      "addon_path":"ee2/third_party/",
      "theme_path":"themes/third_party/"
    }
