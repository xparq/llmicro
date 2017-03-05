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
	if ($len !== false) echo " [".substr($p->text, $pos, $len) . "] ";
	return $len;
};
Parser::$OP[_SAVE_Q] = function(Parser $p, $pos, $rule)
{
	$len = $p->match($pos, $rule);
	if ($len !== false) echo " \"".substr($p->text, $pos, $len) . "\" ";
	return $len;
};
Parser::$OP[_SAVE_R] = function(Parser $p, $pos, $rule)
{
	$len = $p->match($pos, $rule);
	if ($len !== false) echo " ~".substr($p->text, $pos, $len) . "~ ";
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
$WORD = '/[^\\s\\"\\/]+/';
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

echo "<h1>PHRASE...</h1>";

test($s, "egy kettő");
test($s, "egy két há");
test($s, " egy két ");
test($s, "a ! b");

echo "<h1>REX...</h1>";

test($s, "/");
test($s, "//");
test($s, "/x/");
test($s, " /x/");
test($s, "/x");
test($s, '/ket tő/');
test($s, '/kettő/ "három"');
test($s, '/k ettő/ "h árom"');

echo "<h1>QUOTED...</h1>";

test($s, '"');
test($s, '""');
test($s, '"x"');
test($s, ' "x"');
test($s, '"x');
test($s, '"ket tő"');
test($s, '"kettő" "három"');
test($s, '"k ettő" "h árom"');

echo "<h1>MIXED...</h1>";

test($s, "egy /ket tő/");
test($s, "egy /kettő/ \"három\"");
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

echo "<h1>REAL-WORLD EXAMPLES...</h1>";

test($s, 'egy /k ettő/ két "h árom"');	// recursion depth 105 with array model, and 74 with the string model! :-o
test($s, 'qqqqqqq egy /k ettő/ ddd"ddd dd"ddkét "h árom"');

test($s, 'egy /két/ "h /árom"');
test($s, 'egy /k ettő két "h árom');
test($s, 'egy kettő három');
test($s, 'egy kettő három négy');
test($s, 'egy kettő "há rom" négy');

echo "<h1>MORE ACCENTED...</h1>";

test($s, 'négy /k ettő/ két "h árom"');	// recursion depth 105 with array model, and 74 with the string model! :-o
test($s, 'ŐqqqqŰ egy /k ettő/ ddd"ddd dd"ddkét "h árom"');

test($s, 'négy /két/ "h /árom"');
test($s, 'négy /k ettő ket "h árom');
test($s, 'négy kettő három');
test($s, 'négy kettő három négy');
test($s, 'öt négy "há rom" kettő "egy');
