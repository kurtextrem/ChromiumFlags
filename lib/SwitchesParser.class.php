<?php
namespace crflags;
require_once 'AbstractSourceParser.class.php';
error_reporting(E_ALL);
set_time_limit(0);

class SwitchesParser extends AbstractSourceParser {
	// Pre
	public $urls = array(
		'http://src.chromium.org/viewvc/chrome/trunk/src/chrome/common/chrome_switches.cc', // largest first to build the base constants
		'http://src.chromium.org/viewvc/chrome/trunk/src/base/base_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/content/public/common/content_switches.cc',
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
		'http://src.chromium.org/viewvc/chrome/trunk/src/components/signin/core/common/signin_switches.cc'
	);

	// Mid parsing
	protected $oldFile;
	protected $preString = '';
	protected $original = '';
	protected $openCondition = null;

	// Output
	public $publicOutputFile = '../flags.json';
	protected $output = array('time' => null, 'switches' => array(), 'constants' => array());

	public function __construct() {
		$this->cacheLife = 60 * 60 * 24;
		$this->execute();
	}

	public function execute() {
		if ($this->development === 2 || $this->development === 3)
			@unlink($this->publicOutputFile);
		$this->oldFile = @file_get_contents($this->publicOutputFile);
		$this->output['time'] = $now = time();
		if (!$this->oldFile) {
			$this->update();
		} else {
			$this->oldFile = json_decode($this->oldFile, true);
			if ($now - $this->oldFile['time'] > $this->cacheLife) {
				$this->update();
			} else {
				$this->output = $this->oldFile;
			}
		}

		$this->output();
	}

	public function update() {
		foreach($this->urls as $url) {
			$comment = $condition = '';
			if ($url === 'http://src.chromium.org/viewvc/chrome/trunk/src/chromeos/chromeos_switches.cc') {
				$comment = '<b>The switches below only work with Chrome OS</b>';
				$condition = 6;
			}
			$this->addSwitch('..' . substr($url, 47, -3), $url, $comment, $condition, false, false);
			$this->parse($this->get($url));
		}
		$this->addDeletedSwitches();
		parent::update();
	}

	protected function addSwitch($name, $original, $comment, $condition, $new, $deleted = false) {
		$this->output['switches'][$name] = array(
		 	'original' => $original,
			'comment' => $comment,
			'condition' => $condition,
			'new' => $new,
			'deleted' => $deleted
		);
	}

	protected function addDeletedSwitches() {
		$diff = array_diff_key($this->output['switches'], $this->oldFile['switches']);
		foreach ($diff as $key => $switch) {
			if ($this->output['time'] - $switch['deleted'] < $this->cacheLife * 30) // if switch was removed > 30 days ago drop it
				$this->addSwitch($key, $switch['original'], $switch['comment'], $switch['condition'], false, $switch['deleted']);
		}
	}

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

	protected function handleDetermination($spans) {
		$this->call($spans, html_entity_decode($spans[0]->innertext));
	}

	protected function handleComment($spans) {
		$content = html_entity_decode($spans[0]->innertext);
		if (strpos($content, '------')) return $this->preString = ''; // workaround for the first lines in chrome_switches
		$this->preString .= preg_replace('~^// ~', '', $content) . ' ';
	}

	protected function handleCondition($spans) {
		$content = html_entity_decode($spans[0]->innertext);
		// workaround for #ifndef NDEBUG
		$content = preg_replace('/#ifndef (.+)/', '#if !defined($1)', $content);
		// workaround for the future
		$content = preg_replace('/#ifdef (.+)/', '#if defined($1)', $content);

		$content = str_replace('#if ', '', $content);
		$this->openCondition = $this->parseCondition($content);
	}

	protected function handleConstant($spans) {
		if (!isset($spans[5]->innertext)) return $this->original = $spans[2];
		$name = str_replace('"', '', html_entity_decode($spans[5]->innertext));
		if (strlen($name) < 3) { // for values like ProfilerTimingDisabledValue from base_switches
			$name = $spans[2] . ': "' . $name . '"';
		}

		$this->addSwitch($name, html_entity_decode($spans[2]->innertext), $this->preString, $this->openCondition,  !isset($this->oldFile['switches'][$name]));
		$this->preString = '';
	}

	protected function handleConstantName($spans) {
		$spans[2] = $this->original;
		$spans[5] = $spans[0];
		$this->handleConstant($spans);
		$this->original = '';
	}

	protected function handleElseCondition($spans) {
		$this->openCondition = preg_replace('/([ ]?)(\d+)/', '$1!$2', $this->openCondition);
		$this->openCondition = str_replace('!', '', $this->openCondition);
	}

	protected function handleEndCondition($spans) {
		$this->openCondition = null;
	}

	protected function handleNamespace ($spans) {
		$this->preString = '';
	}

	protected function handleInclude ($spans) {
		$this->preString = '';
	}
}

new SwitchesParser();
