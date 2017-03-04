<?php error_reporting(-1);

//$DBG = true;

require_once "test.php";

//---------------------------------------------------------------------------
define('_SAVE' , '_');	// Capture source for current rule. Usage: [_SAVE _OR X Y]
define('_SAVE_Q' , '_Q');
define('_SAVE_R' , '_R');
Parser::$OP[_SAVE] = function(Parser $p, $pos, $rule)
{
	$len = $p->match($pos, $rule);
	if ($len !== false) echo " [".mb_substr($p->text, $pos, $len) . "] ";
	return $len;
};
Parser::$OP[_SAVE_Q] = function(Parser $p, $pos, $rule)
{
	$len = $p->match($pos, $rule);
	if ($len !== false) echo " \"".mb_substr($p->text, $pos, $len) . "\" ";
	return $len;
};
Parser::$OP[_SAVE_R] = function(Parser $p, $pos, $rule)
{
	$len = $p->match($pos, $rule);
	if ($len !== false) echo " ~".mb_substr($p->text, $pos, $len) . "~ ";
	return $len;
};

//---------------------------------------------------------------------------
// Constructions...
//$word =	[_MANY, 'LETTER'];
//$word =	['WORD'];
//$optional_space = [_OR, 'SPACE', 'EMPTY'];
//$word_maybe_in_spaces =	[$optional_space, 'WORD', $optional_space];
/*
$wordlist = [_MANY,
		[_OR,
			'WORD',
			['WHITESPACE', 'WORD'],
		], 
            ];
*/
//---------------------------------------------------------------------------
//$WORD = 'LETTERS';
$WORD = '/^([^\\s\\"\\/]+)/';
//$REGEXLIKE = ['SLASH', [Parser::_ANY, [Parser::_OR, 'LETTERS', 'WHITESPACES']], [Parser::_ANY, 'SLASH']];
$REGEXLIKE = ['SLASH', [_SAVE_R, Parser::_ANY, [Parser::_OR, $WORD, 'WHITESPACES']], [Parser::_ANY, 'SLASH']];
//!!WHY IS THIS NOT DOING WHAT I THINK? :)
//!!$REGEXLIKE = ['SLASH', [Parser::_OR, 'LETTERS', 'WHITESPACES', 'EMPTY'], [Parser::_ANY, 'SLASH']];
//$QUOTED = ['QUOTE', [Parser::_ANY, [Parser::_OR, 'LETTERS', 'WHITESPACES']], [Parser::_ANY, 'QUOTE']];
$QUOTED = ['QUOTE', [_SAVE_Q, Parser::_ANY, [Parser::_OR, $WORD, 'WHITESPACES']], [Parser::_ANY, 'QUOTE']];
//!!WHY IS THIS NOT DOING WHAT I THINK? :)
//!!$QUOTED = ['QUOTE', [Parser::_OR, 'LETTERS', 'WHITESPACES', 'EMPTY']], [Parser::_ANY, 'QUOTE']];
$TERM = [Parser::_OR, [_SAVE, $WORD], $QUOTED, $REGEXLIKE];
//$TERM = [Parser::_OR, $WORD, $REGEXLIKE];
//$TERM = [Parser::_OR, $REGEXLIKE, $QUOTED];
$QUERY = $TERM;
$QUERY = [Parser::_MANY, [$TERM, [Parser::_ANY, 'WHITESPACES']]];
	
//$s = $word;
//$s = $word_maybe_in_spaces;
//$s = $wordlist;
$s = $WORD;
$s = $REGEXLIKE;
$s = $QUOTED;
$s = $TERM;
$s = $QUERY;

if (!empty($_GET)) {
	$syntaxname = $_GET['s'];
	if (!isset($$syntaxname)) { echo "-- Unknown syntax: '$syntaxname'!"; die; }
	$text = $_GET['t'];
	test($$syntaxname, $text);
	die;
}

//test($s, "a");

test($s, "");
test($s, " ");
test($s, "egy");
test($s, " egy ");
test($s, " egy  ");
test($s, "  x  ");
test($s, "  egy  ");

echo "PHRASE...<br>";

test($s, "egy ketto");
test($s, "egy ket ha");
test($s, " egy ket ");
test($s, "a ! b");

echo "REX...<br>";

test($s, "/");
test($s, "//");
test($s, "/x/");
test($s, " /x/");
test($s, "/x");
test($s, '/ket to/');
test($s, '/ketto/ "harom"');
test($s, '/k etto/ "h arom"');

echo "QUOTED...<br>";

test($s, '"');
test($s, '""');
test($s, '"x"');
test($s, ' "x"');
test($s, '"x');
test($s, '"ket to"');
test($s, '"ketto" "harom"');
test($s, '"k etto" "h arom"');

echo "MIXED...<br>";

test($s, "egy /ket to/");
test($s, "egy /ketto/ \"harom\"");
test($s, 'a "b" c');
test($s, 'a b "c');
test($s, '/k/ e');
test($s, 'e /k/');

test($s, '/k/ "q"');
test($s, '"q" /k/');

test($s, 'e /k/ e');
test($s, '/a/ b /c/');
test($s, '/a/ b /c/ d');
test($s, 'e /k/ "h"');
test($s, 'w /r/ w "q"');
test($s, 'w w /r r/ w w "q q"');

echo "REAL-WORLD EXAMPLES...<br>";

test($s, 'egy /k etto/ ket "h arom"');	// recursion depth 105 with array model, and 74 with the string model! :-o
test($s, 'qqqqqqq egy /k etto/ ddd"ddd dd"ddket "h arom"');

test($s, 'egy /ket/ "h /arom"');
test($s, 'egy /k etto ket "h arom');
test($s, 'egy ketto harom');
test($s, 'egy ketto harom negy');
test($s, 'egy ketto "ha rom" negy');

echo "ACCENTED...<br>";

test($s, 'egy /k ettő/ ket "h árom"');	// recursion depth 105 with array model, and 74 with the string model! :-o
test($s, 'qqqqqqq egy /k ettő/ ddd"ddd dd"ddket "h árom"');

test($s, 'egy /ket/ "h /árom"');
test($s, 'egy /k ettő ket "h árom');
test($s, 'egy kettő három');
test($s, 'egy kettő három négy');
test($s, 'öt négy "há rom" kettő "egy');
