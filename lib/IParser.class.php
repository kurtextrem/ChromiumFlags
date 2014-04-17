<?php
namespace crflags;

/**
 * All parsers should implement this.
 * A parser parses files.
 *
 * @author		Jacob Groß (kurtextrem)
 * @copyright		2014 Jacob Groß
 * @license		"Do whatever you want"
 * @package		ChromiumFlags
 */
interface IParser {
	/**
	 * Execute the parser.
	 */
	public function execute();
}
