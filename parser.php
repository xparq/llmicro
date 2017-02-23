<?php error_reporting(-1);
/*
NOTES:
	Only trying to match the leftmost portion of the input against 
	the current rule, ignoring any leftover text on the right, so a
	successful match may not be a "full" match.

	I.e. the first successful match wins, regardless of consuming all,
	or most of the text or only some left-side chunk or not; the priority 
	is satisfying the rules, not eating all the input.

	A simple test after-the-fact for any residual text left is enough 
	to check for a full match anyway.

	However, this doesn't quarantee an optimal match in case of an 
	ambiguity. But life is short, and this is the first parser I ever
	wrote, so... Let's celebrate! :)
*/

// Operator keywords...
// Can be customized by clients at will (in sync with the $OP map below).
define('_TERMINAL'	, '#');	// Implicit, internal operation, just for a more uniform match()!
define('_SEQ'	, ',');	// Default for just listing rules.
define('_OR'	, '|');
define('_SOME'	, '...');// 1 or more, not greedy; must be followed by exactly 1 rule
define('_MANY'	, '+');	// 1 or more, greedy; must be followed by exactly 1 rule
define('_ANY'	, '*');	// 0 or more; shortcut to [_OR [_MANY X] EMPTY]; must be followed by exactly 1 rule
// "Userland" demo:
define('_SAVE'	, '_');	// Capture source for current rule. Usage: [_SAVE _OR X Y]

// Operator functions...
// Populated later below, according to:
//    $OP[_SOME-OP] = function($chunk, $rule) { ... return false or match-length; }
// Can be customized by clients at will (in sync with the keyword list above).
$OP = [];

// Atoms ("terminal token pattens") - metasyntactic sugar...
// They could as well be used as literals, but too fancy regex literals 
// might confuse the parser, so these nicely behaving patterns are just 
// quarantined and named here...
$ATOM['EMPTY'] = '/^()/';
$ATOM['SPACE'] = '/^(\\s)/';
$ATOM['TAB'] = '/^(\\t)/';
$ATOM['WHITESPACE'] = '/^([\\s]+)/';
$ATOM['QUOTE'] = '/^(\\")/';
$ATOM['LETTER'] = '/^([\\w])/'; // more like ALPHANUM or IDCHAR...
$ATOM['WORD'] = '/^([\\w]+)/';
$ATOM['SLASH'] = '~^(\\/)~';
$ATOM['REGEX_DELIM'] = $ATOM['SLASH'];

//---------------------------------------------------------------------------
function tokenize($str)
{
//	return preg_split('//', $str, null, PREG_SPLIT_NO_EMPTY);
	return $str;
}

function stringize($seq)
{
	//return implode($seq);
	return $seq;
}

function dump($mixed)
{
	return "<pre style='margin:0 0 0 2em; background:#ffff90;'>" . print_r($mixed, true) . "</pre>";
}

function DBG($msg)
{	global $DBG;
	if (!$DBG) return;
	echo $msg ."<br>\n";
}

//---------------------------------------------------------------------------
function atom($rule)	{ global $ATOM; return isset($ATOM[$rule]) ? $ATOM[$rule] : false; }
function op($rule)	{ global $OP;   return isset($OP[$rule])   ? $OP[$rule]   : false; }
function term($rule)	{ return is_string($rule); }
function constr($rule)	{ return is_array($rule) && !empty($rule); }


//---------------------------------------------------------------------------
$OP[_TERMINAL]	= function($seq, $rule)
{
	global $ATOM;
//	assert(is_array($seq));
	assert(is_string($seq));
	assert(is_string($rule));

//	$str = stringize($seq);
	$str = $seq;

	if (atom($rule)) { // atom pattern
		$m = [];
DBG(" -- match_term(): matching atom '$rule' ($ATOM[$rule]) against input: '$str'");
                if (preg_match($ATOM[$rule], $str, $m)) {
DBG(" -- match_term(): MATCH! [$m[1]]");
                	return strlen($m[1]);
		} else {
			return false;
		}
	} else if (!empty($rule) && $rule[0] == '/' && $rule[-1] == '/') {
		// Direct regex literals in the syntax!
DBG(" -- match_term(): matching direct regex '$rule' against input: '$str'");
                if (preg_match($rule, $str, $m)) {
DBG(" -- match_term(): MATCH! [$m[1]]");
                	return strlen($m[1]);
//			return $m[1];
		} else {
			return false;
		}
	} else { // literal
DBG(" -- match_term(): matching literal '$rule' against input: '$str'");
               	if (strcasecmp($str, $rule) < 0) {
			return false;
		} else {
	               	return strlen($rule);
		}
	}
};

$OP[_SEQ]	= function($seq, $rule)
{
DBG(" -- match_seq() ");
	assert(is_string($seq));
	assert(is_array($rule));

	$pos = 0;
	foreach ($rule as $r)
	{
		$chunk = substr($seq, $pos);
		if (($len = match($chunk, $r)) === false) {
			return false;
		} else {
			$pos += $len;
		}
	}

	return $pos;
};

$OP[_OR]	= function($seq, $rule)
{
DBG(" -- match_or() ");
	assert(is_string($seq));
	assert(is_array($rule));

	foreach ($rule as $r)
	{
DBG(" -- match_or(): matching rule: ". dump($r));
		if (($pos = match($seq, $r)) !== false) {
DBG(" -- match_or(): MATCH! (pos=$pos)");
			return $pos;
		} else {
DBG(" -- match_or(): failed, skipping to next, if any...");
			continue;
		}
	}

DBG(" -- match_or(): returning false");
	return false;
};


$OP[_ANY]	= function($seq, $rule)
{
DBG(" -- match_any() ");
	assert(is_string($seq));
	assert(is_array($rule));
	assert(count($rule) == 1);

	$pos = 0;
	$r = $rule[0];
	do {
		$chunk = substr($seq, $pos);
DBG(" -- match_any(): iteration for '".stringize($chunk) ."'");
		if (($len = match($chunk, $r)) === false) {
DBG(" -- match_any(): received false!");
			break;
		} else {
DBG(" -- match_any(): received len: $len");
			if ($len == 0) {
				throw new Exception("--WTF? Infinite loop (in _ANY)!");
			}
			$pos += $len;
		}
	} while (!empty($chunk));

DBG(" -- match_any(): returning pos=$pos");
	return $pos;
};


//---------------------------------------------------------------------------
$OP[_SOME]	= function($seq, $rule)
{
DBG(" -- match_many() entered...");
	assert(is_string($seq));
	assert(is_array($rule));
	assert(count($rule) == 1);

	$pos = 0;
	$r = $rule[0];
	$at_least_one_match = false;
	do {
		$chunk = substr($seq, $pos);
DBG(" -- match_many(): iteration for '".stringize($chunk) ."'");
		if (($len = match($chunk, $r)) === false) {
DBG(" -- match_many(): received false!");
			break;
		} else {
DBG(" -- match_many(): received len: $len");
			$at_least_one_match = true;
			$pos += $len;
			break;
		}
	} while (!empty($chunk));

$res = $at_least_one_match ? $pos : false;
if ($res == false) DBG(" -- match_many(): returning false");
else               DBG(" -- match_many(): returning pos=$pos");
	return $at_least_one_match ? $pos : false;
};

//---------------------------------------------------------------------------
$OP[_MANY]	= function($seq, $rule)
{
DBG(" -- match_many_greedy() entered...");
	assert(is_string($seq));
	assert(is_array($rule));
	assert(count($rule) == 1);

	$pos = 0;
	$r = $rule[0];
	$at_least_one_match = false;
	do {
		$chunk = substr($seq, $pos);
DBG(" -- match_many_greedy(): iteration for '".stringize($chunk) ."'");
		if (($len = match($chunk, $r)) === false) {
DBG(" -- match_many_greedy(): received false!");
			break;
		} else {
DBG(" -- match_many_greedy(): received len: $len");
			$at_least_one_match = true;
			// We'd get stuck in an infinite loop if not progressing! :-o
			if ($len == 0) {
				throw new Exception("--WTF? Infinite loop (in _MANY)!");
			}
			$pos += $len;
		}
	} while (!empty($chunk));

$res = $at_least_one_match ? $pos : false;
if ($res == false) DBG(" -- match_many_greedy(): returning false");
else               DBG(" -- match_many_greedy(): returning pos=$pos");
	return $at_least_one_match ? $pos : false;
};

//---------------------------------------------------------------------------
// "Userland" demo:
$OP[_SAVE]	= function($seq, $rule)
{
	$res = match($seq, $rule);
echo " [".substr(stringize($seq), 0, $res) . "] ";
	return $res;
};

//---------------------------------------------------------------------------
function match($seq, $rule)
// $seq is a SEQUENCE of syntaxt constructs.
// If $seq matches $rule, it returns the position true, otherwise false.
{
	global $ATOM, $OP, $loopguard;

	if (!--$loopguard) {
		throw new Exception("--WTF? Infinite loop (in 'match()')!<br>\n");
	}

DBG("match(): input '".stringize($seq)."' against rule: ".dump($rule));
	
	if (term($rule)) // Terminal rule: atom or literal pattern.
	{
DBG(" --> terminal rule: ".dump($rule));
		$f = op(_TERMINAL);
	}
	else if (constr($rule))
	{
		// First item is the op. of the rule, or else _SEQ is assumed:
		if (!is_array($rule[0]) //!! Needed to silence Warning: Illegal offset type in isset or empty in parsing.php on line 52
			&& op($rule[0])) {
			$op = $rule[0];
			array_shift($rule); // eat it
		} else {
			$op = _SEQ;
		}

		$f = op($op);
DBG(" --> complex rule: type '$op'"
//	.dump($rule)
);
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
