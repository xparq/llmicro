<?php
/* 
  A simplistic recursive descent LL parser for simple, ad-hoc tasks
*/


//---------------------------------------------------------------------------
// Operator keywords...
// Can be extended by clients at will (in sync with the Parser::$OP map below).
define('_TERMINAL', '#');// Implicit, internal operation, defined here only for a more uniform match()
define('_SEQ'	, ',');	// Default for just listing rules.
define('_OR'	, '|');
define('_MANY'	, '+');	// 1 or more (greedy); must be followed by exactly 1 rule
define('_ANY'	, '*');	// 0 or more (greedy); shortcut to [_OR [_MANY X] EMPTY]; must be followed by exactly 1 rule
//!!NOT IMPLEMENTED:
//define('_ONE_OR_NONE', '?');// 0 or 1; must be followed by exactly 1 rule

//---------------------------------------------------------------------------
class Parser
{
	const MAX_NESTING_LEVEL = 500;

	// Operator functions...
	// Populated later below, as:
	//    $OP[_SOME-OP] = function($chunk, $rule) { ... return false or match-length; }
	// Can be extended by clients at will (in sync with the keyword list above).
	static $OP = [];

	// Atoms ("terminal pattens") - just "metasyntactic sugar", as they could 
	// as well be just literal patterns. But fancy random regex literals would
	// confuse the parsing, so these "officially" nicely behaving ones are just 
	// quarantined and named here.
	// (BTW, a user pattern that's not anchored to the left is guaranteed to 
	// fail, as the relevant preg_match() call only returns the match length.
	// It could be extended though, but I'm not sure about multibyte support,
	// apart from my suspicion that mbstring *doesn't* have a position-capturing
	// preg_match (or any, for that matter, only ereg_...!).)
	// NOTE: PCRE is UNICODE-aware! --> http://pcre.org/original/doc/html/pcreunicode.html
	static $ATOM = [
		'EMPTY'      => '/^()/',
		'SPACE'      => '/^(\\s)/',
		'TAB'        => '/^(\\t)/',
		'QUOTE'      => '/^(\\")/',
		'APOSTROPHE' => "/^(')/",
		'SLASH'      => '~^(\\/)~',
		'IDCHAR'     => '/^([\\w])/', // [a-zA-Z0-9_], I guess
		'ID'         => '/^([\\w]+)/',
		'HEX'        => '/^([\\0-9a-fA-F])/',
		// UNICODE-safe:
		'DIGIT'      => '/^([\\p{N}])/u',
		'DIGITS'     => '/^([\\p{N}]+)/u',
		'LETTER'     => '/^([\\p{L}])/u',
		'LETTERS'    => '/^([\\p{L}]+)/u',
		'WHITESPACE' => '/^([\\p{Z}]+)/u',
	];

	//-------------------------------------------------------------------
	static function atom($rule)	{ return isset(self::$ATOM[$rule]) ? self::$ATOM[$rule] : false; }
	static function op($rule)	{ return isset(self::$OP[$rule])   ? self::$OP[$rule]   : false; }
	static function term($rule)	{ return is_string($rule); }
	static function constr($rule)	{ return is_array($rule) && !empty($rule); }


	//-------------------------------------------------------------------
	// Parsing context
	//
	public $text; // input
	public $text_length;
	public $loopguard;
	public $depth_reached;
	public $tries;

	//-------------------------------------------------------------------
	public function parse($text, $syntax, $maxnest = self::MAX_NESTING_LEVEL)
	{
		$this->text = $text;
		$this->text_length = mb_strlen($text);
		// diagnostics
		$this->loopguard = $this->depth_reached = $maxnest;
		$this->tries = 0;

		return $this->match(0, $syntax);
	}

	//-------------------------------------------------------------------
	public function match($pos, $rule)
	// $seq is the source text (a string).
	// $rule is a syntax (tree) rule
	// If $seq matches $rule, it returns the length of the match, otherwise false.
	{
		++$this->tries;
		if ($this->depth_reached > --$this->loopguard)
		    $this->depth_reached =   $this->loopguard;
		if (!$this->loopguard) {
			throw new Exception("--WTF? Infinite loop (in 'match()')!<br>\n");
		}


		if (self::term($rule)) // Terminal rule: atom or literal pattern.
		{
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
			if (!$f) {
				throw new Exception("--WTF? Unknown operator: '$op'!");
			}
		}
		else
		{
			throw new Exception("--WTF? Broken syntax: " . print_r($rule, true));
		}

		$res = $f($this, $pos, $rule);
		++$this->loopguard;
		return $res;
	}
}

//---------------------------------------------------------------------------
Parser::$OP[_TERMINAL] = function(Parser $p, $pos, $rule)
{
	$str = mb_substr($p->text, $pos);
	if (Parser::atom($rule)) // atom pattern?
	{	
		$m = [];
                if (preg_match(Parser::$ATOM[$rule], $str, $m)) {
			return mb_strlen($m[1]);
		} else	return false;

	}
	else if (!empty($rule) && $rule[0] == '/' && $rule[-1] == '/') // direct regex?
	{
                if (preg_match($rule, $str, $m)) {
			return mb_strlen($m[1]);
//			return $m[1];
		} else	return false;
	}
	else // literal non-pattern
	{
               	if (strcasecmp($str, $rule) >= 0) {
			return mb_strlen($rule);
		} else	return false;
	}
};

//---------------------------------------------------------------------------
Parser::$OP[_SEQ] = function(Parser $p, $pos, $rule)
{
	$len = 0;
	foreach ($rule as $r)
	{
		if (($l = $p->match($pos + $len, $r)) !== false) {
			$len += $l;
		} else	return false;
	}
	return $len;
};

//---------------------------------------------------------------------------
Parser::$OP[_OR] = function(Parser $p, $pos, $rule)
{
	foreach ($rule as $r)
	{
		if (($len = $p->match($pos, $r)) !== false) {
			return $len;
		} else {
			continue;
		}
	}
	return false;
};

//---------------------------------------------------------------------------
Parser::$OP[_ANY] = function(Parser $p, $pos, $rule)
{
	assert(count($rule) == 1);

	$len = 0;
	$r = $rule[0];
	do {
//$chunk = mb_substr($p->text, $pos + $len);
		if (($l = $p->match($pos + $len, $r)) === false) {
			break;
		} else {
			if ($l == 0) {
				throw new Exception("--WTF? Infinite loop (in _ANY)!");
			}
			$len += $l;
		}
	} while ($pos + $len < $p->text_length);

	return $len;
};

//---------------------------------------------------------------------------
Parser::$OP[_MANY] = function(Parser $p, $pos, $rule)
{
	assert(count($rule) == 1);

	$len = 0;
	$r = $rule[0];
	$at_least_one_match = false;
	do {
//$chunk = mb_substr($p->text, $pos + $len);
		if (($l = $p->match($pos + $len, $r)) === false) {
			break;
		} else {
			$at_least_one_match = true;
			// We'd get stuck in an infinite loop if not progressing! :-o
			if ($l == 0) {
				throw new Exception("--WTF? Infinite loop (in _MANY)!");
			}
			$len += $l;
		}
	} while ($pos + $len < $p->text_length);

	return $at_least_one_match ? $len : false;
};
