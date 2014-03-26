<?php
namespace LessCompiler\Compiler;

use LessCompiler\Compiler\Less as LessCompiler;

require_once(LessCompiler::getLeafoLessPHPFile());

/**
 * LessExt
 * ===
 *
 * Extended class of the original LessPHP lessc.
 * With some specific adjustments
 *
 * @author Patrick Langendoen <github-bradcrumb@patricklangendoen.nl>
 * @author Marc-Jan Barnhoorn <github-bradcrumb@marc-jan.nl>
 * @copyright 2014 (c), Patrick Langendoen & Marc-Jan Barnhoorn
 * @license http://opensource.org/licenses/GPL-3.0 GNU GENERAL PUBLIC LICENSE
 * @todo add php-functions to the lessc configuration
 */
class LesscExt extends \lessc {
/**
 * Check if a (re)compile is needed
 * 
 * @param  array|string $in
 * @param  boolean $force
 * @return array or null
 */
    public function cachedCompile($in, $force = false) {
        // asume no root
        $root = null;

        if (is_string($in)) {
            $root = $in;
        } elseif (is_array($in) && isset($in['root'])) {
            if ($force || !isset($in['files'])) {
                // If we are forcing a recompile or if for some reason the
                // structure does not contain any file information we should
                // specify the root to trigger a rebuild.
                $root = $in['root'];
            } elseif (isset($in['files'])) {
                $in['files'] = json_decode($in['files']);
                foreach ($in['files'] as $fname => $ftime) {
                    if (!file_exists($fname) || filemtime($fname) > $ftime) {
                        // One of the files we knew about previously has changed
                        // so we should look at our incoming root again.
                        $root = $in['root'];
                        break;
                    }
                }
            }
        } else {
            return null;
        }

        if ($root !== null) {
            // If we have a root value which means we should rebuild.
            return array(
                'root' => $root,
                'compiled' => $this->compileFile($root),
                'files' => json_encode($this->allParsedFiles()),
                'variables' => json_encode($this->registeredVars),
                'functions' => json_encode($this->libFunctions),
                'formatter' => $this->formatterName,
                'comments' => $this->preserveComments,
                'importDirs' => json_encode((array)$this->importDir),
                'updated' => time(),
                );
        } else {
            // No changes, pass back the structure
            // we were given initially.
            return $in;
        }
    }
}