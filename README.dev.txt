Original note from the source:

	Only trying to match the leftmost portion of the input against 
	the current rule, ignoring any leftover text on the right, so a
	successful match may not be a "full" match.

	I.e. the first successful match wins, regardless of consuming all,
	or most of the text or only some left-side chunk or not; the priority 
	is satisfying the rules, not eating all the input.
	(A simple test after-the-fact for any residual text left is enough 
	to check for a full match anyway.)

	However, this doesn't quarantee an optimal match in case of an 
	ambiguity. But life is short, and this is the first parser I ever
	wrote, so... Let's celebrate! :)

Looks like what I "invented" ;) here is only the most basic type of
non-deterministic(?) recursive descent parser with backtracking. 
I.e. the simplest of all. :)

(Note to self (as usual): Actually reading the literature wouldn't have
helped at all in this case. In fact, it would have hindered immensely. 
As this simplistic parser is closer to *pondering* than to any sort real 
or strong grasp of the theory, a) I fortunately didn't require it for 
this, and b) tying to learn it properly first would have been an obstacle 
I'd have never overcome.)

https://en.wikipedia.org/wiki/Recursive_descent_parser

https://en.wikipedia.org/wiki/LL_parser
	https://en.wikipedia.org/wiki/LL_parser#Conflicts

https://en.wikipedia.org/wiki/Simple_LR_parser
	https://en.wikipedia.org/wiki/LR_parser
	https://en.wikipedia.org/wiki/LALR_parser

https://en.wikipedia.org/wiki/Abstract_syntax_tree

https://en.wikipedia.org/wiki/Operator-precedence_parser

http://stackoverflow.com/questions/38796645/avoid-stackoverflow-in-recursive-algorithm-in-recursive-descent-parser
