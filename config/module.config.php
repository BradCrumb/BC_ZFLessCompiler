<?php
/**
 * Default LessCompiler module settings
 */
return array(
	'BC_ZFLessCompiler' => array(
		// Should the module be enabled or disabled for the current environment
		'enabled' 			=> true,
		// Always compile the Less files (ignores the enabled option)
		'autoRun'           => false,
		// Set the path for the LessPHP files by Leafo (https://github.com/lessphp)
        // Defaults to "modules/BC_ZFLessCompiler/vendor/leafo/lessphp" in the application's vendor map
        // You can run "php composer.phar install" from the module's directory in a terminal to install Leafo LessPHP
        'path_to_leafo_lessphp' =>  realpath(getcwd() . DIRECTORY_SEPARATOR . 'module/BC_ZFLessCompiler/vendor/leafo/lessphp'),
		// Import directory: please use realpath(...) to get a valid directory
		// ie. realpath(getcwd() . '/less/inc/');
		'importDir'			=> null,
		// Where to look for Less files
		'sourceFolder'      => null,
		// Where to put the generated css
		'targetFolder'      => null,
		// lessphp compatible formatter
		'formatter'         => 'compressed',
		// Preserve comments or remove them
		'preserveComments'  => null,
		// Pass variables from php to Less
		'variables'         => array(),
		// Pass cache options as an array or pass even a complete 
        // cache adapter which extends \Zend\Cache\Storage\Adapter\AbstractAdapter
        // Configurable array options are the keys: name, ttl and namespace.
        // Other array_keys will be ignored
        'cache'             => null,
	),
);