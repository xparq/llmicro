﻿<?php

$INFILE = "parser.php";
$OUTFILE = "out/parser.php";
$TESTFILE = "test.php";

// Syntax-check:

        system("php -l $INFILE", $res);
	if ($res != 0) exit($res);
        system("php -l $TESTFILE", $res);
	if ($res != 0) exit($res);
	

// Filter the source:

	file_put_contents($OUTFILE, 
		implode(array_filter(file($INFILE), function($l) {
				return !(
						preg_match('/DBG\(.+/', $l)
					||	preg_match('~\/\/[\\s]+assert\(.+~', $l)
					);
				})
		)
	);
