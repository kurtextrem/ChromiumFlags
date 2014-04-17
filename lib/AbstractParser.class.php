<?php
namespace crflags;
require_once 'IParser.class.php';

/**
 * Provides the default implementation for the Parser interface.
 *
 * @author		Jacob Groß (kurtextrem)
 * @copyright		2014 Jacob Groß
 * @license		"Do whatever you want"
 * @package		ChromiumFlags
 */
abstract class AbstractParser implements IParser {
	/**
	 * Development mode on
	 * 0: off
	 * 1: read from local file
	 * 2: rewrite cache from local
	 * 3: download to local file + 2
	 * 4: bypass time check, do everything like in 0
	 * 5: download to local file + 1
	 *
	 * @var int
	 */
	public $development = 0;
}
