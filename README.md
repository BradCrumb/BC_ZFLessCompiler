#ZendFramework2+ LessCompiler

A module for Zend Framework 2+ to easily compile all of your Less-files by using and extending the PHP Less compiler of http://leafo.net.

## Requirements

This module has the following requirements:

* PHP 5.3.0 or greater.
* ZendFramework 2.2 or greater
* Lessc by Leafo.net in the vendor map (default git clone will do) 0.3.9 or greater

## Installation

* Clone this repository into the module directory of your application or use a svn external
* Make your application aware of the module by add the module's name to the application.config.php file.
  ie.
  return array(
    // This should be an array of module namespaces used in the application.
    'modules' => array(
        'Application',
        'BC_ZFLessCompiler',
    ),
  );
* Unless you want to set some custom configuration you´re good to go now

## Configuration
Since ZF2 is no longer environment aware, so isn't this module.
In de module's configuration folder you'll find a file called 'lesscompiler.global.php.dist'.
To configure the LessCompiler to your needs you'll have the copy the file to the application's 
config/autoload folder en remove the .dist extension. 
Read the ZF documentation on how to use the naming conventions regarding configuration files.
ie. global could also be local.

An example of the options you can configure can be found in module.config.php

The options are
- enable (default:true)
	Should the module be enabled or disabled for the current environment

- autoRun (default: false)
	Always compile the Less files (ignores the enabled option)

- path_to_leafo_lessphp (default: vendor/lessphp)
	Set the path for the LessPHP files by Leafo (https://github.com/lessphp)
	Defaults to "lessphp" in the application's vendor map

- importDir (default: null)
	Import directory: please use realpath(...) to get a valid directory
	ie. realpath(getcwd() . '/less/inc/');

- sourceFolder (default: null)	 
	Where to look for Less files

- targetFolder (default: null)
	Where to put the generated css

- formatter (default: compressed)
	lessphp compatible formatter (see leafo.net/lessphp for the options)

- preserveComments (default: null)
	Preserve comments or remove them

- variables (default: array())
	Array of php variables (see leafo.net/lessphp for more info)

- cache (default: null)	
	Pass cache options as an array or pass even a complete 
    cache adapter which extends \Zend\Cache\Storage\Adapter\AbstractAdapter
    Configurable array options are the keys: name, ttl and namespace.
    Other array_keys will be ignored

## Documentation

The module will check for less-files to (re)compile automatically when:
 * autoRun is set to true in the configuration options
 * you supply a GET parameter "forceCompiling" and set "true" or 1
 * Cache-time expires

The module caches the compiled files with the help of Zend_Cache.
All Less-files should be placed in the `application/less` directory (to generate css-files in the default `public/css` directory) by default.

The default duration time for the cache is 4 hours.
After that time the cache expires and after a new request the module will check for updated or added less-files.

## License
GNU General Public License, version 3 (GPL-3.0)
http://opensource.org/licenses/GPL-3.0