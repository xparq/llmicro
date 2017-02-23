<?php error_reporting(-1);

$t1 = microtime(true);

define('MAX_RECURSION_DEPTH', 150);

require "parser.php";

//---------------------------------------------------------------------------
function test($syntax, $text)
{
	echo("<hr><pre>Testing: \"$text\"...</pre>");

	global $loopguard;
	$loopguard = MAX_RECURSION_DEPTH;

//	$src = tokenize($text);
	$src = $text;
	$res = match($src, $syntax);
	if ($res !== false) {
		echo("<p style='color:green;'><b>MATCHED: '"
				. substr($text, 0, $res) ."'"
				."</b></p>\n");
	} else {
		echo("<p style='color:red;'>FAILED.</p>");
	}
	echo "<p>Recursion depth: " . (MAX_RECURSION_DEPTH - $loopguard) . "</p>";
}

//---------------------------------------------------------------------------
//main()
$DBG = true;
$DBG = false;

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
//$WORD = 'WORD';
$WORD = '/^([^\\s\\"\\/]+)/';
$REGEXLIKE = ['REGEX_DELIM', [_ANY, [_OR, 'LETTER', 'WHITESPACE']], [_ANY, 'REGEX_DELIM']];
//$REGEXLIKE = ['REGEX_DELIM', [_MANY, [_OR, 'LETTER', 'WHITESPACE']], 'REGEX_DELIM'];
$QUOTED = ['QUOTE', [_ANY, [_OR, 'LETTER', 'WHITESPACE']], [_ANY, 'QUOTE']];
//$QUOTED = ['QUOTE', [_MANY, [_OR, 'LETTER', 'WHITESPACE']], 'QUOTE'];
$TERM = [_SAVE, _OR, $REGEXLIKE, $WORD, $QUOTED];
//$TERM = [_OR, $WORD, $REGEXLIKE];
//$TERM = [_OR, $REGEXLIKE, $QUOTED];
//$QUERY = $wordlist;
$QUERY = [_MANY, [$TERM, [_ANY, 'WHITESPACE']]];
	
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

/*
*/
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

echo "PHRASE...<br>";

test($s, "egy /ket to/");
test($s, "egy /ketto/ \"harom\"");
test($s, "a ! b");
test($s, 'a b');
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

echo "Timing: " . (microtime(true) - $t1);
