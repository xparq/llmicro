<?php
/* 
  A simplistic recursive descent LL parser for my own ad-hoc use.
  (Note, it's even tail-call optimizable, alas, PHP can't do that.)
*/

//---------------------------------------------------------------------------
// Throwaway utilities...
//---------------------------------------------------------------------------
/*
function dump($mixed)
{
	return "<pre style='margin:0 0 0 2em; background:#ffff90;'>" . print_r($mixed, true) . "</pre>";
}
function DBG($msg)
{	global $DBG;
	if (!$DBG) return;
	echo $msg ."<br>\n";
}
*/

//---------------------------------------------------------------------------
// Operator keywords...
// Can be extended by clients at will (in sync with the Parser::$OP map below).
define('_TERMINAL'	, '#');	// Implicit, internal operation, just for a more uniform match()!
define('_SEQ'	, ',');	// Default for just listing rules.
define('_OR'	, '|');
define('_SOME'	, '...');// 1 or more, not greedy; must be followed by exactly 1 rule
define('_MANY'	, '+');	// 1 or more, greedy; must be followed by exactly 1 rule
define('_ANY'	, '*');	// 0 or more; shortcut to [_OR [_MANY X] EMPTY]; must be followed by exactly 1 rule

//---------------------------------------------------------------------------
class Parser
{
	// Operator functions...
	// Populated later below, according to:
	//    $OP[_SOME-OP] = function($chunk, $rule) { ... return false or match-length; }
	// Can be customized by clients at will (in sync with the keyword list above).
	static $OP = [];

	// Atoms ("terminal token pattens") - metasyntactic sugar...
	// They could as well be used as literals, but too fancy regex literals 
	// might confuse the parser, so these nicely behaving patterns are just 
	// quarantined and named here...
	static $ATOM = [
		'EMPTY'	=> '/^()/',
		'SPACE'	=> '/^(\\s)/',
		'TAB'	=> '/^(\\t)/',
		'WHITESPACE' => '/^([\\s]+)/',
		'QUOTE'	=> '/^(\\")/',
		'LETTER'	=> '/^([\\w])/', // more like ALPHANUM or IDCHAR...
		'WORD'	=> '/^([\\w]+)/',
		'SLASH'		=> '~^(\\/)~',
		'REGEX_DELIM'	=> '~^(\\/)~',
	];

	// Instance data...
	public $loopguard = 256; // Just a nice round default. Override for your case!

	//-------------------------------------------------------------------
	static function atom($rule)	{ return isset(self::$ATOM[$rule]) ? self::$ATOM[$rule] : false; }
	static function op($rule)	{ return isset(self::$OP[$rule])   ? self::$OP[$rule]   : false; }
	static function term($rule)	{ return is_string($rule); }
	static function constr($rule)	{ return is_array($rule) && !empty($rule); }

	//-------------------------------------------------------------------
	static function match($seq, $rule)
	// $seq is the source text (a string).
	// $rule is a syntax (tree) rule
	// If $seq matches $rule, it returns the length of the match, otherwise false.
	{
		global $loopguard;

		if (!--$loopguard) {
			throw new Exception("--WTF? Infinite loop (in 'match()')!<br>\n");
		}

//DBG("match(): input '".$seq."' against rule: ".dump($rule));

		if (self::term($rule)) // Terminal rule: atom or literal pattern.
		{
//DBG(" --> terminal rule: ".dump($rule));
			$f = self::op(_TERMINAL);
		}
		else if (self::constr($rule))
		{
			// First item is the op. of the rule, or else _SEQ is assumed:
			if (!is_array($rule[0]) //!! Needed to silence Warning: Illegal offset type in isset or empty in parsing.php on line 52
				&& self::op($rule[0])) {
				$op = $rule[0];
				array_shift($rule); // eat it
			} else {
				$op = _SEQ;
			}

			$f = self::op($op);
//DBG(" --> complex rule: type '$op', for rule: "
//	.dump($rule)
//);
			if (!$f) {
				throw new Exception("--WTF? Unknown operator: '$op'!");
			}
		}
		else
		{
			throw new Exception("--WTF? Broken syntax: " . dump($rule));
		}

		return $f($seq, $rule);
	}
}

//---------------------------------------------------------------------------
Parser::$OP[_TERMINAL] = function($str, $rule)
{
	assert(is_string($rule));

	if (Parser::atom($rule)) { // atom pattern
		$m = [];
//DBG(" -- match_term(): matching atom '$rule' (".Parser::$ATOM[$rule].") against input: '$str'");
                if (preg_match(Parser::$ATOM[$rule], $str, $m)) {
//DBG(" -- match_term(): MATCH! [$m[1]]");
                	return strlen($m[1]);
		} else {
			return false;
		}
	} else if (!empty($rule) && $rule[0] == '/' && $rule[-1] == '/') {
		// direct regex literal pattern
//DBG(" -- match_term(): matching direct regex '$rule' against input: '$str'");
                if (preg_match($rule, $str, $m)) {
//DBG(" -- match_term(): MATCH! [$m[1]]");
                	return strlen($m[1]);
//			return $m[1];
		} else {
			return false;
		}
	} else { // literal non-pattern
//DBG(" -- match_term(): matching literal '$rule' against input: '$str'");
               	if (strcasecmp($str, $rule) < 0) {
			return false;
		} else {
	               	return strlen($rule);
		}
	}
};

//---------------------------------------------------------------------------
Parser::$OP[_SEQ] = function($seq, $rule)
{
//DBG(" -- match_seq() ");
	assert(is_array($rule));

	$pos = 0;
	foreach ($rule as $r)
	{
		$chunk = substr($seq, $pos);
//DBG(" -- match_seq($seq, rule): matching chunk '$chunk' against rule: ".dump($rule));
		if (($len = Parser::match($chunk, $r)) === false) {
			return false;
		} else {
			$pos += $len;
		}
	}

	return $pos;
};

//---------------------------------------------------------------------------
Parser::$OP[_OR] = function($seq, $rule)
{
//DBG(" -- match_or() ");
	assert(is_array($rule));

	foreach ($rule as $r)
	{
//DBG(" -- match_or(): matching rule: ". dump($r));
		if (($pos = Parser::match($seq, $r)) !== false) {
//DBG(" -- match_or(): MATCH! (pos=$pos)");
			return $pos;
		} else {
//DBG(" -- match_or(): failed, skipping to next, if any...");
			continue;
		}
	}

//DBG(" -- match_or(): returning false");
	return false;
};

//---------------------------------------------------------------------------
Parser::$OP[_ANY] = function($seq, $rule)
{
//DBG(" -- match_any() ");
	assert(is_array($rule));
	assert(count($rule) == 1);

	$pos = 0;
	$r = $rule[0];
	do {
		$chunk = substr($seq, $pos);
//DBG(" -- match_any(): iteration for '". $chunk ."'");
		if (($len = Parser::match($chunk, $r)) === false) {
//DBG(" -- match_any(): received false!");
			break;
		} else {
//DBG(" -- match_any(): received len: $len");
			if ($len == 0) {
				throw new Exception("--WTF? Infinite loop (in _ANY)!");
			}
			$pos += $len;
		}
	} while (!empty($chunk));

//DBG(" -- match_any(): returning pos=$pos");
	return $pos;
};

//---------------------------------------------------------------------------
Parser::$OP[_SOME] = function($seq, $rule)
{
//DBG(" -- match_many() entered...");
	assert(is_array($rule));
	assert(count($rule) == 1);

	$pos = 0;
	$r = $rule[0];
	$at_least_one_match = false;
	do {
		$chunk = substr($seq, $pos);
//DBG(" -- match_many(): iteration for '". $chunk ."'");
		if (($len = Parser::match($chunk, $r)) === false) {
//DBG(" -- match_many(): received false!");
			break;
		} else {
//DBG(" -- match_many(): received len: $len");
			$at_least_one_match = true;
			$pos += $len;
			break;
		}
	} while (!empty($chunk));

//$res = $at_least_one_match ? $pos : false;
//if ($res == false) DBG(" -- match_many(): returning false");
//else               DBG(" -- match_many(): returning pos=$pos");
	return $at_least_one_match ? $pos : false;
};

//---------------------------------------------------------------------------
Parser::$OP[_MANY] = function($seq, $rule)
{
//DBG(" -- match_many_greedy() entered...");
	assert(is_array($rule));
	assert(count($rule) == 1);

	$pos = 0;
	$r = $rule[0];
	$at_least_one_match = false;
	do {
		$chunk = substr($seq, $pos);
//DBG(" -- match_many_greedy(): iteration for '". $chunk ."'");
		if (($len = Parser::match($chunk, $r)) === false) {
//DBG(" -- match_many_greedy(): received false!");
			break;
		} else {
//DBG(" -- match_many_greedy(): received len: $len");
			$at_least_one_match = true;
			// We'd get stuck in an infinite loop if not progressing! :-o
			if ($len == 0) {
				throw new Exception("--WTF? Infinite loop (in _MANY)!");
			}
			$pos += $len;
		}
	} while (!empty($chunk));

//$res = $at_least_one_match ? $pos : false;
//if ($res == false) DBG(" -- match_many_greedy(): returning false");
//else               DBG(" -- match_many_greedy(): returning pos=$pos");
	return $at_least_one_match ? $pos : false;
};
