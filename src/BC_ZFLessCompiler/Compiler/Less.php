<?php
namespace BC_ZFLessCompiler\Compiler;

use BC_ZFLessCompiler\Exception\LessCompilerException;

/**
 * LessCompiler
 * ===
 *
 * @author Patrick Langendoen <github-bradcrumb@patricklangendoen.nl>
 * @author Marc-Jan Barnhoorn <github-bradcrumb@marc-jan.nl>
 * @copyright 2014 (c), Patrick Langendoen & Marc-Jan Barnhoorn
 * @license http://opensource.org/licenses/GPL-3.0 GNU GENERAL PUBLIC LICENSE
 */
class Less {

/**
 * Configuration for the compiler
 * Also supplied here so this is the ultimate default configuration
 *
 * @var array
 */
	protected $config = array(
		// Should the module be enabled or disabled for the current environment
		'enabled' => true,
		// Always compile the Less files (ignores the enabled option)
		'autoRun' => false,
		// Set the path for the LessPHP files (https://github.com/oyejorge/less.php)
		'pathToLessphp' => null,
		// Where to look for Less files
		'sourceFolder' => null,
		// Where to put the generated css
		'targetFolder' => null,
		// Use cache?
		'useCache' => true,
		// Cache directory, is useCache is true it defaults to the target Folder  and subdirectory 'cache'
		'cacheDirectory' => null,
		// Global (without key) and sourcefolder (with same key as in sourceFolders array) specific variables
		'variables' => array(
			//array(/* global variables for all sourcefolder-keys */),
			//'default' => array(/* variables for "default" sourcefolder only */)
		),
		// Global (without key) and sourcefolder (with same key as in sourceFolders array) specific import directories
		'importDirs' => array(
			//array(/* global import directory for all sourcefolder-keys */),
			//'default' => array(/* importdirs for "default" sourcefolder only */)
		),
		// LessPHP options
		'options' => array(
			'compress' => true,
			'sourceMap' => false,
			'sourceMapToFile' => false,
		),
	);

/**
 * name / location of the Less class file
 *
 * @var string
 */
	protected static $_lessPHPfile;

/**
 * Minimum required Zend Framework version
 *
 * @var string
 */
	protected static $_minVersionZF = '2.2';

/**
* Minimum required PHP version
*
* @var string
*/
	protected static $_minVersionPHP = '5.3.3'; // Required by LessPHP

/**
 * Minimum required LessPHP version;
 *
 * @see http://lessphp.gpeasy.com/
 * @var string
 */
	protected static $_minVersionLessc = '1.7.0.1';

/**
 * Contains the indexed folders consisting of less-files
 *
 * @var array
 */
	protected $_lessFolders;

/**
 * Contains the folders with processed css files
 *
 * @var array
 */
	protected $_cssFolders;

/**
 * General import directory
 *
 * @var string
 */
	protected $_importDir;

/**
 * General variables
 *
 * @var array
 */
	protected $_variables;

/**
 * Status whether component is enabled or disabled
 *
 * @var boolean
 */
	public $enabled = true;

/**
 * Force the compiler to always compile all files
 *
 * @var boolean
 */
	protected $_forceCompiling = false;

/**
 * Query param to be used to force the compilation of Less files
 *
 * @constant
 */
	const QUERY_PARAM_ENFORCEMENT = 'forceCompiling';

/**
 * Class constructor
 *
 * @param array $config
 */
	public function __construct(array $config = null) {
		// Set the configuration option from all merged configuration files
		$this->config = array_replace_recursive($this->config, $config);

		$this->_importDir = array_shift($this->config['importDirs']);
		$this->_variables = array_shift($this->config['variables']);

		// The the enabled status of this module
		$this->enabled = $this->_getConfigurationValue('enabled', $this->enabled) ||
						 $this->_getConfigurationValue('autoRun', false);

		if ($this->enabled) {
			try {
				$this->_verifyPHPVersion();
				$this->_verifyZFVersion();
				$this->_verifyLessPHP();
				$this->_setFolders();
			}
			catch(LessCompilerException $e) {
				// We throw a new LessCompilerException so the application which uses this module gets to decide what to do next
				throw new LessCompilerException('An error regarding the LessCompiler has occurred: ' . $e->getMessage());
			}
		}
	}

/**
 * Return the configuration value for a specific key
 *
 * @param  string $key
 * @param  $defaultReturnValue
 * @return unknown
 */
	protected function _getConfigurationValue($key, $defaultReturnValue = null) {
		return array_key_exists($key, $this->config)? $this->config[$key]: $defaultReturnValue;
	}

	protected function _getConfigurationValues(array $keys) {
		$return = array();

		foreach ($keys as $key) {
			$return[$key] = $this->_getConfigurationValue($key);
		}

		return $return;
	}

/**
 * Set the enforcing of Less files compilation.
 * This setting will force the enabled property of the module to true when enforcement is set to true.
 *
 * @param boolean $enabled
 */
	public function setEnforcement($enabled = false) {
		$this->_forceCompiling = !in_array($enabled, array(0, '0', 'false', 'no'));
		$this->enabled = $this->_forceCompiling || $this->enabled;
	}

/**
 * Check the PHP version
 *
 * @throws LessCompilerException when PHP version is not compatible
 * @return void
 */
	protected function _verifyPHPVersion() {
		if (PHP_VERSION < self::$_minVersionPHP) {
			throw new LessCompilerException(sprintf('PHP version %s or higher is required!', self::$_minVersionPHP));
		}
	}

/**
 * Check the Zend Framework version
 *
 * @throws LessCompilerException when Zend Framework version is not compatible
 * @return void
 */
	protected function _verifyZFVersion() {
		if (\Zend\Version\Version::compareVersion(self::$_minVersionZF) > 0) {
			throw new LessCompilerException(sprintf('You currently use Zend Framework %s, but %s is at least required',
				/* 1 */ \Zend\Version\Version::VERSION,
				/* 2 */ self::$_minVersionZF
				));
		}
	}

/**
 * Check existance of LessHP and it's version
 *
 * @throws LessCompilerException when LeafoPHP can not be found or the version is not compatible
 * @return void
 */
	protected function _verifyLessPHP() {
		if (!($path = $this->_getConfigurationValue('pathToLessphp', false))) {
			throw new LessCompilerException('Path to LessPHP vendor module has not been configured');
		}

		$file = 'Less.php';
		self::$_lessPHPfile = $path . DIRECTORY_SEPARATOR . $file;

		if (!file_exists(self::$_lessPHPfile)) {
			throw new LessCompilerException(sprintf('Less.php file "%s" does not exist!', $file));
		}

		require_once($path . DIRECTORY_SEPARATOR  . 'Version.php');

		if (\Less_Version::version < self::$_minVersionLessc) {
			throw new LessCompilerException(sprintf('LessPHP version %s or higher is required!', self::$_minVersionLessc));
		}
	}

/**
 * Returns the LeafoPHP file location
 *
 * @return string
 */
	public static function getLessPHPFile() {
		return self::$_lessPHPfile;
	}

/**
 * @todo: process Zend modules? Or is this something that is up to the user?
 *
 * @throws LessCompilerException
 * @return void
 */
	protected function _setFolders() {
		$sourceFolder = $this->_getConfigurationValue('sourceFolder', false);
		$targetFolder = $this->_getConfigurationValue('targetFolder', false);

		if ($sourceFolder && $targetFolder) {
			if (is_array($sourceFolder)) {
				if (!array_key_exists('default', $sourceFolder)) {
					throw new LessCompilerException('Please supply a default sourcefolder [key "default" must exist! or it should be set as a string]');
				}

				if (!is_array($targetFolder)) {
					throw new LessCompilerException('When you supply a sourcefolders array, you should also supply a targetfolders array with corresponding keys');
				}

				foreach ($sourceFolder as $fIndex => $fValue) {
					// Only add the Less folder to source-folders if it exists.
					// Also the target-folder with the same key should be set and exist.
					if (is_string($fValue) && is_dir($fValue) && array_key_exists($fIndex, $targetFolder) && is_dir($targetFolder[$fIndex])) {
						$this->_lessFolders[$fIndex] = realpath($fValue) . DIRECTORY_SEPARATOR;
						$this->_cssFolders[$fIndex] = realpath($targetFolder[$fIndex]) . DIRECTORY_SEPARATOR;
					} elseif (is_array($fValue) && $fValue) {
						if (substr($targetFolder[$fIndex],-4) != '.css') {
							throw new LessCompilerException('When you supply an array of sourcefolders for a specific key, the targetfolder should be a string which represents a css filename');
						}

						$path = str_replace(basename($targetFolder[$fIndex]), null, $targetFolder[$fIndex]);
						$this->_cssFolders[$fIndex] = realpath($path) . DIRECTORY_SEPARATOR . basename($targetFolder[$fIndex]);

						foreach ($fValue as $subFolderValue) {
							if (is_string($subFolderValue) && is_dir($subFolderValue)) {
								$this->_lessFolders[$fIndex][] = realpath($subFolderValue) . DIRECTORY_SEPARATOR;
							}
						}
					}
				}
			} elseif (is_string($sourceFolder) && is_dir($sourceFolder) &&
					  is_string($targetFolder) && is_dir($targetFolder)) {
				 $this->_lessFolders['default'] = realpath($sourceFolder) . DIRECTORY_SEPARATOR;
				 $this->_cssFolders['default'] = realpath($targetFolder) . DIRECTORY_SEPARATOR;
			}
		} else {
			$this->_lessFolders['default'] = getcwd()  . DIRECTORY_SEPARATOR . 'less' . DIRECTORY_SEPARATOR;
			$this->_cssFolders['default'] = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR;
		}

		foreach ($this->_cssFolders as $folder) {
			if (is_dir($folder) && !is_writable($folder)) {
				throw new LessCompilerException(sprintf('"%s" is not writable!', $folder));
			}
		}
	}

/**
 * Run the compiler if enabled
 *
 * @return array
 */
	public function run(){
		if ($this->enabled) {
			require_once(self::$_lessPHPfile);

			if (!$this->_forceCompiling) {
				$useCache = (bool)$this->config['useCache'];
			}

			$options = $this->config['options'];

			$sourceMapToFile = (bool)$options['sourceMapToFile'];
			$options['sourceMapToFile'];
			unset($options['sourceMapToFile']);

			if ($useCache) {
				$cacheDir = $this->_getConfigurationValue('cacheDirectory');
				if ($cacheDir && (!is_dir($cacheDir) || !is_writable($cacheDir))) {
					throw new LessCompilerException('When you supply a Less Caching directory, it should exist and be writable!');
				}
			}

			foreach ($this->_lessFolders as $key => $lessFolder) {
				$parser = new \Less_Parser($options);

				// set the global variables
				$parser->ModifyVars($this->_variables);

				// set the global import directories
				$parser->SetImportDirs($this->_importDir);

				if (is_array($lessFolder)) {
					if (!is_string($this->_cssFolders[$key])) {
						throw new LessCompilerException('Target should be a single stylesheet file!');
					}

					$path = $this->_cssFolders[$key];

					$extraOptions = array();
					if ($options['sourceMap']) {
						$extraOptions['sourceMapWriteTo']	= str_ireplace('.css', '.map', $path);
						$extraOptions['sourceMapURL']		= str_ireplace(array('.css', $_SERVER['DOCUMENT_ROOT']), array('.map', null), $path);
					}

					if ($useCache) {
						// Cache directory, is useCache is true it defaults to the target Folder  and subdirectory 'cache'
						if (!$cacheDir) {
							$cacheDir = dirname($path) . DIRECTORY_SEPARATOR . 'cache';
							if (!is_dir($cacheDir)) {
								mkdir($cacheDir, 0777);
							}
						}

						$extraOptions['cache_dir'] = $cacheDir;
					}

					Cache::$cache_dir = $extraOptions['cache_dir'];
					if ($extraOptions) {
						$options = array_replace_recursive($options, $extraOptions);
					}

					$importDirs = (isset($this->config['importDirs'][$key]))?
						array_merge($this->_importDir, $this->config['importDirs'][$key]):
						$this->_importDir;

					$variables = (isset($this->config['variables'][$key]))?
						array_merge($this->_variables, $this->config['variables'][$key]):
						$this->_variables;

					$lessFiles = array();
					foreach ($lessFolder as $lessDir) {
						$files = $this->getFilesFromDirectory($lessDir);

						foreach ($files as $file) {
							if (!$file->isDir()) {
								$lessFiles[$file->getPathName()] = null;
							}
						}
					}

					$css_file_name = Cache::Check($lessFiles, $options, $useCache, $importDirs, $variables);

					if (is_string($css_file_name)) {
						copy($extraOptions['cache_dir'] . DIRECTORY_SEPARATOR . $css_file_name, $path);
					}
				} else {
					$files = $this->getFilesFromDirectory($lessFolder);

					foreach ($files as $file) {
						$path = str_ireplace(
									rtrim($lessFolder, DIRECTORY_SEPARATOR),
									null,
									rtrim($file->getRealPath(), DIRECTORY_SEPARATOR)
								);

						$path = rtrim($this->_cssFolders[$key], DIRECTORY_SEPARATOR) . $path;

						if ($file->isDir()) {
							if (!is_dir($path)) {
								mkdir($path, 0777);
							}
						} else {
							$extraOptions = array();
							if ($options['sourceMap']) {
								$extraOptions['sourceMapWriteTo']	= str_ireplace('.less', '.map', $path);
								$extraOptions['sourceMapURL']		= str_ireplace(array('.less', $_SERVER['DOCUMENT_ROOT']), array('.map', null), $path);
    								$extraOptions['sourceMapBasepath']	= dirname(dirname($path));
    							}

    							if ($useCache) {
								if (!$cacheDir) {
									$cacheDir = dirname($path) . DIRECTORY_SEPARATOR . 'cache';
									if (!is_dir($cacheDir)) {
										mkdir($cacheDir, 0777);
									}
								}

								$extraOptions['cache_dir'] = $cacheDir;
    							}

							Cache::$cache_dir = $extraOptions['cache_dir'];
							if ($extraOptions) {
								$options = array_replace_recursive($options, $extraOptions);
							}

							$importDirs = (isset($this->config['importDirs'][$key]))?
								array_merge($this->_importDir, $this->config['importDirs'][$key]):
								$this->_importDir;

							$variables = (isset($this->config['variables'][$key]))?
								array_merge($this->_variables, $this->config['variables'][$key]):
								$this->_variables;

							$css_file_name = Cache::Check(array($file->getRealPath() => null), $options, $useCache, $importDirs, $variables);

							if (is_string($css_file_name)) {
								copy($extraOptions['cache_dir'] . DIRECTORY_SEPARATOR . $css_file_name, str_ireplace('.less', '.css', $path));
							}
						}
					}
				}
			}


		}
	}

/**
 * Return array of files wihtin a given directory
 *
 * @param  string $directory
 * @return array
 */
	protected function getFilesFromDirectory($directory) {
		return new \RecursiveIteratorIterator(
						new \RecursiveRegexIterator(
							new \RecursiveDirectoryIterator(
								$directory, \FilesystemIterator::SKIP_DOTS
							),
							'/^(?!.*(\/inc|\.txt|\.cvs|\.svn|\.git)).*$/', \RecursiveRegexIterator::MATCH
						),
						\RecursiveIteratorIterator::SELF_FIRST
					);
	}
}
