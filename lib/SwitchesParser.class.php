<?php
namespace crflags;
require_once 'AbstractSourceParser.class.php';

// report all errors
error_reporting(E_ALL);
// try to set the time execution limit to infinite
set_time_limit(0);
// fix timezone warning issue
if (!@ini_get('date.timezone')) {
	@date_default_timezone_set('Europe/Berlin');
}


/**
 * Parses switches from the chromium source files.
 *
 * @author		Jacob Groß (kurtextrem)
 * @copyright		2014 Jacob Groß
 * @license		"Do whatever you want"
 * @package		ChromiumFlags
 */
class SwitchesParser extends AbstractSourceParser {
	// Pre
	/** @see 	AbstractSourceParser::$urls */
	public $urls = array(
		'http://src.chromium.org/viewvc/chrome/trunk/src/chrome/common/chrome_switches.cc', // largest first to build the base constants
		'http://src.chromium.org/viewvc/chrome/trunk/src/content/public/common/content_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/base/base_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/apps/switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/ash/ash_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/ipc/ipc_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/chromeos/chromeos_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/components/data_reduction_proxy/common/data_reduction_proxy_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/components/autofill/core/common/autofill_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/components/translate/core/common/translate_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/components/password_manager/core/common/password_manager_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/components/policy/core/common/policy_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/components/precache/core/precache_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/components/signin/core/common/signin_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/ui/base/ui_base_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/content/shell/common/shell_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/components/nacl/common/nacl_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/ui/app_list/app_list_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/components/infobars/core/infobars_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/components/cloud_devices/common/cloud_devices_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/content/public/android/java/src/org/chromium/content/common/ContentSwitches.java',
		'http://src.chromium.org/viewvc/chrome/trunk/src/ui/events/event_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/cc/base/switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/ui/keyboard/keyboard_switches.cc'
	);

	// Mid parsing
	/** @var 	array 	Contains the array of the previous version */
	protected $oldFile;
	/** @var 	array 	 Contains previous comments */
	protected $preString = '';
	/** @var 	string 	Contains the original switch name (workaround for new line names) */
	protected $original = true;
	/** @var 	int 	Contains the current condition */
	protected $openCondition = null;

	// Output
	/** @see 	AbstractSourceParser::$publicOutputFile */
	public $publicOutputFile = '../flags.json';
	/** @see 	AbstractSourceParser::$output */
	protected $output = array('time' => null, 'switches' => array(), 'constants' => array(), 'urls' => array());
	protected $new = array();
	protected $deleted = array();


	/**
	 * Sets the life time of the cache and executes the parser.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 */
	public function __construct() {
		$this->cacheLife = 60 * 60 * 24;
		$this->execute();
	}

	/**
	 * Loads the old file and updates if necessary.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 */
	public function execute() {
		if ($this->development === 2 || $this->development === 3)
			@unlink($this->publicOutputFile);
		$this->oldFile = @file_get_contents($this->publicOutputFile);
		$this->output['time'] = $now = time();
		if (!$this->oldFile) {
			$this->update();
		} else {
			$this->oldFile = json_decode($this->oldFile, true);
			if ($now - $this->oldFile['time'] > $this->cacheLife || $this->development > 3 || $this->development === 1) {
				Header('Content-Type: text/plain');
				$this->update();
			} else {
				$this->output = $this->oldFile;
				$this->output();
			}
		}
	}

	/**
	 * Loops through the url and starts the parser.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 */
	public function update() {
		foreach($this->urls as $url) {
			$comment = $condition = '';
			if ($url === 'http://src.chromium.org/viewvc/chrome/trunk/src/chromeos/chromeos_switches.cc') {
				$comment = '<b>The switches below only work with Chrome OS</b>';
				$condition = 6;
			}
			$this->addSwitch('..' . substr($url, 47, -3), $url, $comment, $condition, 0);
			$this->parse($this->get($url));
		}
		$this->addDeletedSwitches();
		$this->addURLS();
		parent::update();
	}

	/**
	 * Adds the source urls to the output.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-17
	 */
	protected function addURLS() {
		$this->output['urls'] = $this->urls;
	}

	/**
	 * Adds a switch to the output.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 * @param  	string     	$name      	Name of the switch
	 * @param  	string     	$original  	Original name of the switch
	 * @param  	string     	$comment  	Comments of the switch
	 * @param  	mixed     	$condition 	Conditions
	 * @param  	boolean     	$new       	If new timestamp, else zero
	 * @param  	int    		$deleted   	If deleted timestamp, else zero
	 */
	protected function addSwitch($name, $original, $comment, $condition, $new, $deleted = 0) {
		$this->output['switches'][$name] = array(
		 	'original' => $original,
			'comment' => $comment,
			'condition' => $condition,
			'new' => $new,
			'deleted' => $deleted
		);
		if ($deleted) {
			$this->deleted[] = $name;
			$new = array('DELETED: ', $deleted);
		} elseif ($new) {
			$this->new[] = $name;
			$new = array('NEW: ', $new);
		}
		if (is_array($new) && $new[1] === $this->output['time'])
			echo $new[0] . $name . "\n";
	}

	/**
	 * Adds deleted switches from previous file.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 */
	protected function addDeletedSwitches() {
		$diff = array_diff_key($this->oldFile['switches'], $this->output['switches']);
		foreach ($diff as $key => $switch) {
			if ($switch['deleted'] === 0)
				$switch['deleted'] = $this->output['time'];
			if ($this->output['time'] - $switch['deleted'] < $this->cacheLife * 30 && strpos($switch['original'], 'viewvc') === false) // if switch was removed > 30 days ago drop it
				$this->addSwitch($key, $switch['original'], $switch['comment'], $switch['condition'], $switch['new'], $switch['deleted']);
		}
	}

	/**
	 * Parses a condition.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 * @param 	mixed		$condition 	The condition to parse.
	 */
	protected function parseCondition($condition) {
		if (strpos ($condition, '||') !== false || strpos ($condition, '&&') !== false) { // "real" condition
			preg_match_all('/(&&|\|\|)?[ ]?(!?defined\([^)]+\))/', $condition, $matches);
			$string = '';
			foreach($matches[2] as $pos => $match) {
				$string .= $matches[1][$pos] . ' ' . $this->parseCondition($match) . ' ';
			}
			return rtrim($string);
		}

		$flag = '';
		if (substr ($condition, 0, 7) == 'defined') {
			$const = substr ($condition, 8, -1);
		}

		if (substr ($condition, 0, 8) == '!defined') {
			$const = substr ($condition, 9, -1);
			$flag = '!';
		}

		$search = array_search($const, $this->output['constants']);
		$int = true;
		if ($search === false) {
			$search = '';
			$this->output['constants'][] = $const;
			if ($flag) {
				$search = $flag;
				$int = false;
			}
			$search .= array_search($const, $this->output['constants']);
		}
		if ($int)
			$search = intval($search);
		return $search;
	}

	/**
	 * If it is unclear what to call, this method should determine it.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 * @param  	array     		$spans 		Spans to check
	 */
	protected function handleDetermination($spans) {
		$this->call($spans, html_entity_decode($spans[0]->innertext));
	}

	/**
	 * If it is unclear what java handler to call, this method should determine it.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-25
	 * @param  	array     		$spans 		Spans to check
	 */
	protected function handleJavaDetermination($spans) {
		$this->call($spans, html_entity_decode($spans[1]->innertext));
	}

	/**
	 * Handles comments.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 * @param  	array     		$spans 		Spans to check
	 */
	protected function handleComment($spans) {
		$content = html_entity_decode($spans[0]->innertext);
		if (strpos($content, '------')) return $this->preString = ''; // workaround for the first lines in chrome_switches
		$this->preString .= preg_replace('~^// ~', '', $content) . ' ';
	}

	/**
	 * Handles conditions, hands it over to parseConditions and sets the open condition.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 * @param  	array     		$spans 		Spans to check
	 */
	protected function handleCondition($spans) {
		$content = html_entity_decode($spans[0]->innertext);
		// workaround for #ifndef NDEBUG
		$content = preg_replace('/#ifndef (.+)/', '#if !defined($1)', $content);
		// workaround for the future
		$content = preg_replace('/#ifdef (.+)/', '#if defined($1)', $content);

		$content = str_replace('#if ', '', $content);
		$this->openCondition = $this->parseCondition($content);
	}

	/**
	 * Handles constants (switches) and adds them together with its comments to the output. (Clears the comment buffer preString)
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 * @param  	array     		$spans 		Spans to check
	 */
	protected function handleConstant($spans) {
		if (!isset($spans[5]->innertext) || !$this->original) return $this->original = $spans[2]; // when the constant name is in the next line
		$name = str_replace('"', '', html_entity_decode($spans[5]->innertext));
		if (strlen($name) < 3) { // for values like ProfilerTimingDisabledValue from base_switches
			$name = $spans[2] . ': "' . $name . '"';
		}

		$new = !isset($this->oldFile['switches'][$name]) ? $this->output['time'] : $this->oldFile['switches'][$name]['new'];
		$new = $this->output['time'] - $new < $this->cacheLife* 10 ? $new : 0;
		$this->addSwitch($name, html_entity_decode($spans[2]->innertext), $this->preString, $this->openCondition, $new); // only new if added in the past 10 days
		$this->preString = '';
	}

	/**
	 * Handles java constants (switches) and adds them together with its comments to the output. (Clears the comment buffer preString)
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 * @param  	array     		$spans 		Spans to check
	 */
	protected function handleJavaConstant($spans) {
		if (!isset($spans[6]->innertext) || !$this->original) return $this->original = $spans[4]; // when the constant name is in the next line
		$spans[5] = $spans[6];
		$spans[2] = $spans[4];
		$this->handleConstant($spans);
	}

	/**
	 * Handles constant names.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 * @param  	array     		$spans 		Spans to check
	 */
	protected function handleConstantName($spans) {
		$spans[2] = $this->original;
		$spans[5] = $spans[0];
		$this->handleConstant($spans);
	}

	/**
	 * Handles else conditions, reverses the open condition.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 * @param  	array     		$spans 		Spans to check
	 */
	protected function handleElseCondition($spans) {
		$this->openCondition = preg_replace('/([ ]?)(\d+)/', '$1!$2', $this->openCondition);
		$this->openCondition = str_replace('!', '', $this->openCondition);
	}

	/**
	 * Handles end conditions, closes the opened condition.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 * @param  	array     		$spans 		Spans to check
	 */
	protected function handleEndCondition($spans) {
		$this->openCondition = null;
	}

	/**
	 * Handles 'extern' constants.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-17
	 * @param  	array     		$spans 		Spans to check
	 */
	protected function handleExternConstant($spans) {
		$this->preString = '';
		$this->original = false;
	}


	/**
	 * Handles namespace, clears the comment buffer to prevent file comments from showing up as switch comment.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 * @param  	array     		$spans 		Spans to check
	 */
	protected function handleNamespace ($spans) {
		$this->preString = '';
	}

	/**
	 * Handles (java) namespace, clears the comment buffer to prevent file comments from showing up as switch comment.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-25
	 * @param  	array     		$spans 		Spans to check
	 */
	protected function handleJavaNamespace ($spans) {
		$this->preString = '';
	}

	/**
	 * Handles includes, clears the comment buffer to prevent file comments from showing up as switch comment.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 * @param  	array     		$spans 		Spans to check
	 */
	protected function handleInclude ($spans) {
		$this->preString = '';
	}

	/**
	 * Handles 'package' (java), clears the comment buffer to prevent file comments from showing up as switch comment.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-25
	 * @param  	array     		$spans 		Spans to check
	 */
	protected function handlePackage ($spans) {
		$this->preString = '';
	}
}

// Start the parser
new SwitchesParser();
