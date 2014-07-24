<?php
/**
* Default LessCompiler module settings
*/

$composerPath = (is_dir(__DIR__ . '/../../../oyejorge/less.php/lib/Less/'))? __DIR__ . '/../../../oyejorge/less.php/lib/Less/' : null;

return array(
	'BC_ZFLessCompiler' => array(
		// Should the module be enabled or disabled for the current environment
		'enabled' => true,
		// Always compile the Less files (ignores the enabled option)
		'autoRun' => false,
		// Set the path for the LessPHP module
		'pathToLessphp' => $composerPath,
		// Where to look for Less files
		'sourceFolder' => null,
		// Where to put the generated css
		'targetFolder' => null,
		// Use cache?
		'useCache' => true,
		// Global (without key) and sourcefolder (with same key as in sourceFolders array) specific variables
		'cacheDirectory' => null,
		'variables' => array(
			/* global variables for all sourcefolder-keys */
			array(

			),
			/* variables for "default" sourcefolder only */
			// 'default' => array(
			// 	'textColor' => 'brown',
			// ),
		),
		// Global (without key) and sourcefolder (with same key as in sourceFolders array) specific import directories
		'importDirs' => array(
			//array(/* global import directory for all sourcefolder-keys */),
			//'default' => array(/* importdirs for "default" sourcefolder only */) => null // null >> don't forget this!
		),
		// LessPHP options
		'options'   => array(
			'compress' => true,
			'sourceMap' => false,
			'sourceMapToFile' => false,
			'outputSourceFiles' => false /* not working well */,
		),
	),
);
