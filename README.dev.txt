Original note from the source:

	Only trying to match the leftmost portion of the input against 
	the current rule, ignoring any leftover text on the right, so a
	successful match may not be a "full" match.

	I.e. the first successful match wins, regardless of consuming all,
	or most of the text or only some left-side chunk or not; the priority 
	is satisfying the rules, not eating all the input.
	(A simple after-the-fact test for any leftover is enough to check
	for a full match anyway.)

	However, this doesn't guarantee an optimal match in case of an
	ambiguity. But life is short, and this is the first parser I ever
	wrote, so... Let's celebrate! :)

	[NOTE FROM THE FUTURE: What the hell did I mean by "optimal match"
	6 years ago? Not to be a party-pooper... just curious. I mean, I'm
	the one single person interested in this, asking the one single
	person who could possibly tell, and even would love to tell, esp.
	those interested... And there... Here we are, nowhere. ;-p ]

Looks like what I "invented" ;) here is only the most basic type of
non-deterministic(?) recursive descent parsers with backtracking.
I.e. the simplest of all. :)

(Note to self (as usual): Actually reading the literature wouldn't have
helped at all in this case. In fact, it would have hindered immensely. 
As this simplistic parser is closer to *pondering* than to any sort real 
or strong grasp of the theory, a) I fortunately didn't require it for 
this, and b) tying to learn it properly first would have been an obstacle 
I'd have never overcome.)
	[NOTE TO SELF FROM THE FUTURE: The previous note to self is a note
	to self *from the past*; only Git could tell when exactly from.
	Cheers from 2023. I.e. also from the never-ending past. This is
	the present, though. I mean not the prev. sentence, just this...]

These are some of the things I glanced at, after I wrote my little toy:

https://en.wikipedia.org/wiki/Recursive_descent_parser

https://en.wikipedia.org/wiki/LL_parser
	https://en.wikipedia.org/wiki/LL_parser#Conflicts

https://en.wikipedia.org/wiki/Simple_LR_parser
	https://en.wikipedia.org/wiki/LR_parser
	https://en.wikipedia.org/wiki/LALR_parser

https://en.wikipedia.org/wiki/Abstract_syntax_tree

https://en.wikipedia.org/wiki/Operator-precedence_parser

http://stackoverflow.com/questions/38796645/avoid-stackoverflow-in-recursive-algorithm-in-recursive-descent-parser
