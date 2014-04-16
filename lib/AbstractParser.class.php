<?php
namespace crflags;
require_once 'IParser.class.php';

abstract class AbstractParser implements IParser {
	/**
	 * Development mode on
	 * 0: off
	 * 1: download to local file
	 * 2: rewrite cache from local
	 * 3: download to local file + 2
	 * @var int
	 */
	public $development = 0;
}
