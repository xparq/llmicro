<?php
/* 
  A simplistic recursive descent LL parser for my own ad-hoc use.
  (Note, it could be made tail-call-optimizable, giving up on the
  current $OP[] function map. Alas, PHP can't do it yet, so no point...)
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

	// I hate these to be static, but didn't want to turn everything
	// into object scope just because of these diag. variables!
	static $loopguard;
	static $depth_reached;
	static $tries;

	//-------------------------------------------------------------------
	static function atom($rule)	{ return isset(self::$ATOM[$rule]) ? self::$ATOM[$rule] : false; }
	static function op($rule)	{ return isset(self::$OP[$rule])   ? self::$OP[$rule]   : false; }
	static function term($rule)	{ return is_string($rule); }
	static function constr($rule)	{ return is_array($rule) && !empty($rule); }

	//-------------------------------------------------------------------
	static function parse($text, $syntax, $maxnest = self::MAX_NESTING_LEVEL)
	{
		self::$loopguard = self::$depth_reached = $maxnest;
		self::$tries = 0;
		return self::match($text, $syntax);
	}
		
	//-------------------------------------------------------------------
	static function match($seq, $rule)
	// $seq is the source text (a string).
	// $rule is a syntax (tree) rule
	// If $seq matches $rule, it returns the length of the match, otherwise false.
	{
		++self::$tries;
		if (self::$depth_reached > --self::$loopguard)
		    self::$depth_reached =   self::$loopguard;
		if (!self::$loopguard) {
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

		$res = $f($seq, $rule);
		++self::$loopguard;
		return $res;
	}
}

//---------------------------------------------------------------------------
Parser::$OP[_TERMINAL] = function($str, $rule)
{
	if (Parser::atom($rule)) // atom pattern?
	{	
		$m = [];
                if (preg_match(Parser::$ATOM[$rule], $str, $m)) {
			return mb_strlen($m[1]);
		} else	return false;

	}
	else if (!empty($rule) && $rule[0] == '/' && $rule[-1] == '/') // "literal" regex pattern?
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
Parser::$OP[_SEQ] = function($seq, $rule)
{
	$pos = 0;
	foreach ($rule as $r)
	{
		if (($len = Parser::match(mb_substr($seq, $pos), $r)) !== false) {
			$pos += $len;
		} else	return false;
	}
	return $pos;
};

//---------------------------------------------------------------------------
Parser::$OP[_OR] = function($seq, $rule)
{
	foreach ($rule as $r)
	{
		if (($pos = Parser::match($seq, $r)) !== false) {
			return $pos;
		} else {
			continue;
		}
	}
	return false;
};

//---------------------------------------------------------------------------
Parser::$OP[_ANY] = function($seq, $rule)
{
	assert(count($rule) == 1);

	$pos = 0;
	$r = $rule[0];
	do {
		$chunk = mb_substr($seq, $pos);
		if (($len = Parser::match($chunk, $r)) === false) {
			break;
		} else {
			if ($len == 0) {
				throw new Exception("--WTF? Infinite loop (in _ANY)!");
			}
			$pos += $len;
		}
	} while (!empty($chunk));

	return $pos;
};

//---------------------------------------------------------------------------
Parser::$OP[_MANY] = function($seq, $rule)
{
	assert(count($rule) == 1);

	$pos = 0;
	$r = $rule[0];
	$at_least_one_match = false;
	do {
		$chunk = mb_substr($seq, $pos);
		if (($len = Parser::match($chunk, $r)) === false) {
			break;
		} else {
			$at_least_one_match = true;
			// We'd get stuck in an infinite loop if not progressing! :-o
			if ($len == 0) {
				throw new Exception("--WTF? Infinite loop (in _MANY)!");
			}
			$pos += $len;
		}
	} while (!empty($chunk));

	return $at_least_one_match ? $pos : false;
};
