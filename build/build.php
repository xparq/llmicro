<?php
	file_put_contents('out/parser.php', 
		implode(array_filter(file('parser.php'), function($l) {
				return !(
						preg_match('/DBG\(.+/', $l)
					||	preg_match('~\/\/[\\s]+assert\(.+~', $l)
					);
				})
		)
	);
