<?php
namespace BC_ZFLessCompiler\Compiler;

use Zend\Cache\StorageFactory as Cache;
use BC_ZFLessCompiler\Exception\LessCompilerException;

/**
 * LessCompiler
 * ===
 *
 * @author Patrick Langendoen <github-bradcrumb@patricklangendoen.nl>
 * @author Marc-Jan Barnhoorn <github-bradcrumb@marc-jan.nl>
 * @copyright 2014 (c), Patrick Langendoen & Marc-Jan Barnhoorn
 * @license http://opensource.org/licenses/GPL-3.0 GNU GENERAL PUBLIC LICENSE
 * 
 * @todo add php-functions to the lessc configuration
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
		'enabled' 			=> true,
    	// Always compile the Less files (ignores the enabled option)
        'autoRun'           => false,
        // Set the path for the LessPHP files by Leafo (https://github.com/leafo/lessphp)
        'path_to_leafo_lessphp' => null,
        // Import directory
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
    );

    protected static $_leafoLessPHPfile;

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
    protected static $_minVersionPHP = '5.3.3'; // Required by Leafo LessPHP

/**
* Minimum required Lessc.php version
*
* @var string
*/
    protected static $_minVersionLessc = 'v0.4.0';

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
 * @var \Zend\Cache\Storage\Adapter\AbstractAdapter
 */
    protected $_cache;

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
		$this->config = array_merge($this->config, $config);

		// The the enabled status of this module
		$this->enabled = $this->_getConfigurationValue('enabled', $this->enabled) || 
						 $this->_getConfigurationValue('autoRun', false);

		if ($this->enabled) {
			try {
                $cacheOption = $this->_getConfigurationValue('cache', null);

				$this->_verifyPHPVersion();
                $this->_verifyZFVersion();
				$this->_verifyLeafoLessPHP();
				$this->_setCache($cacheOption);
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
 * Check existance of LeafoPHP and it's version
 * 
 * @throws LessCompilerException when LeafoPHP can not be found or the version is not compatible
 * @return void
 */
	protected function _verifyLeafoLessPHP() {
		if (!($path = $this->_getConfigurationValue('path_to_leafo_lessphp', false))) {
			throw new LessCompilerException('Path to Leafo LessPHP has not been configured');
		}

		$file = 'lessc.inc.php';
		self::$_leafoLessPHPfile = $path . DIRECTORY_SEPARATOR . $file;

		if (!file_exists(self::$_leafoLessPHPfile)) {
			throw new LessCompilerException(sprintf('Leafo LessPHP file "%s" does not exist!', $file));	
		}

		if (!class_exists('lessc')) {
            require_once(self::$_leafoLessPHPfile);
        }

		if (\lessc::$VERSION < self::$_minVersionLessc) {
            throw new LessCompilerException(sprintf('Leafo LessPHP version %s or higher is required!', self::$_minVersionLessc));
        }
	}

/**
 * Returns the LeafoPHP file location
 * 
 * @return string
 */
	public static function getLeafoLessPHPFile() {
		return self::$_leafoLessPHPfile;
	}

/**
 * Sets the cache adapter
 * 
 * @param  array | \Zend\Cache\Storage\Adapter\AbstractAdapter $option
 * @return void
 */
    protected function _setCache($option = null) {
        if ($option instanceof \Zend\Cache\Storage\Adapter\AbstractAdapter) {
            $this->_cache = $option;
        } else {
            $cacheOptions = array(
                'name' => 'filesystem',
                'ttl'  => 3600,
                'namespace' => __NAMESPACE__,
            );

            $cacheOptions = array_merge($cacheOptions, (array)$option);

            $this->_cache = Cache::factory(array(
            	'adapter' => array(
            		'name' => $cacheOptions['name'],
            		'options' => array(
            			'ttl' => $cacheOptions['ttl'],
            			'namespace' => $cacheOptions['namespace']
            		),
            	))
            );
        }
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
 * Proxy method for generateCss
 * 
 * @return array
 */
    public function run(){
    	return $this->generateCss();
    }

/**
 * Generate the CSS files
 * 
 * @return array list of generated files
 */
    protected function generateCss() {
        $generatedFiles = array();

        if ($this->enabled) {
            foreach ($this->_lessFolders as $key => $lessFolder) {
                if (is_array($lessFolder)) {
                    $lessCode = '';
                    foreach ($lessFolder as $lFolder) {
                        $files = $this->getFilesFromDirectory($lFolder);

                        foreach ($files as $file) {
                            $lessCode .= file_get_contents($file) . PHP_EOL;
                        }
                    }

                    // Let's generate a temporaty Less file
                    if (strlen(trim($lessCode)) > 3) {
                        $tmpLessFile = str_ireplace('.css', '.less', $this->_cssFolders[$key]);
                        if (file_put_contents($tmpLessFile, $lessCode)) {
                            if ($this->_autoCompileLess($tmpLessFile, $this->_cssFolders[$key])) {
                                $generatedFiles[] = $this->_cssFolders[$key];
                            }
                            if (file_exists($tmpLessFile)) {
                                unlink($tmpLessFile);
                            }
                        } else {
                            throw new LessCompilerException(sprintf('Could not write temporary Less file "%s"', $tmpLessFile));
                        }
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
                        	$cssFile = str_ireplace('.less', '.css', $path);
                        	
                        	if ($this->_autoCompileLess($file->getRealPath(), $cssFile)) {
                        		$generatedFiles[] = $cssFile;
                        	}
                        }
                    }
                }
            }
        }

        return $generatedFiles;
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

/**
 * Auto compile all less files
 *
 * @param string $inputFile
 * @param string $outputFile
 * @return boolean
 */
    protected function _autoCompileLess($inputFile, $outputFile)
    {
        $cacheKey = md5(json_encode($inputFile) . $outputFile);

        /**
         * If the file has not been generated or the cache has not been set or has expired >> use the inputFile to (re)generate the file.
         * Otherwise get the cached item to verify against.
         *
         * Use this if you need to clear the cache while developping:
         * $this->_cache->clearByNamespace('lesscompiler');
         */
        if (!file_exists($outputFile)) {
        	$cache = $inputFile;
        } else {
        	$cache = $this->_cache->getItem($cacheKey, $success);
        	$cache = $success? unserialize($cache): $inputFile;
        }

        /**
         * Compile a new version of the current input file
         */
        $lesscExt = new LesscExt();
        $settings = $this->_getConfigurationValues(array(
        	'importDir',
        	'formatter',
        	'preserveComments',
        	'variables',
        	));

        if ($settings['importDir']) {
        	$lesscExt->setImportDir((array)$settings['importDir']);
        }

        $lesscExt->setFormatter($settings['formatter']);

        if (is_bool($settings['preserveComments'])) {
        	$lesscExt->setPreserveComments($settings['preserveComments']);
        }

        if ($settings['variables']) {
        	$lesscExt->setVariables($settings['variables']);
        }

        $newCache = $lesscExt->cachedCompile($cache, $this->_forceCompiling);

        if (true === $this->_forceCompiling ||
            !is_array($cache) ||
            $newCache["updated"] > $cache["updated"]) {

        	$this->_cache->setItem($cacheKey, serialize($newCache));
            file_put_contents($outputFile, $newCache['compiled']);

            return true;
        }

        return false;
    }
}
