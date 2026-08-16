<html>
<head>
	<meta charset="utf-8">

	<title>Virtual Keyboard</title>

	<!-- jQuery (required) & jQuery UI + theme (optional) -->
	<link href="docs/css/jquery-ui.min.css" rel="stylesheet">
	<!-- still using jQuery v2.2.4 because Bootstrap doesn't support v3+ -->
	<script src="docs/js/jquery-latest.min.js"></script>
	<script src="docs/js/jquery-ui.min.js"></script>
	<!-- <script src="docs/js/jquery-migrate-3.0.0.min.js"></script> -->

	<!-- keyboard widget css & script (required) -->
	<link href="css/keyboard.css" rel="stylesheet">
	<script src="js/jquery.keyboard.js"></script>



	<!-- demo only -->
	<link rel="stylesheet" href="docs/css/bootstrap.min.css">
	<link rel="stylesheet" href="docs/css/font-awesome.min.css">
	<link href="docs/css/demo.css" rel="stylesheet">
	<link href="docs/css/tipsy.css" rel="stylesheet">
	<link href="docs/css/prettify.css" rel="stylesheet">
	<script src="docs/js/bootstrap.min.js"></script>
	<script src="docs/js/demo.js"></script>
	<script src="docs/js/jquery.tipsy.min.js"></script>
	<script src="docs/js/prettify.js"></script> <!-- syntax highlighting -->
</head>

<body>

	<div class="block" id="autocomplete">
		<input id="text" type="text" placeholder=" Enter something...">
		<br>
	</div>
	<div class="block" id="autocomplete">
		<input id="wow" type="text" placeholder=" Enter something...">
		<br>
	</div>
		<div class="block" id="autocomplete">
		<input id="he" type="text" placeholder=" Enter something...">
		<br>
	</div>
	
	<script>// Autocomplete demo
var availableTags = ["ActionScript", "AppleScript", "Asp", "BASIC", "C", "C++", "Clojure",
	"COBOL", "ColdFusion", "Erlang", "Fortran", "Groovy", "Haskell", "Java", "JavaScript",
	"Lisp", "Perl", "PHP", "Python", "Ruby", "Scala", "Scheme" ];

$('#wow')
	.keyboard({ layout: 'qwerty' })
	.autocomplete({
		source: availableTags
	})
	// position options added after v1.23.4

	.addTyping();
	</script>
		<script>// Autocomplete demo
var availableTags = ["ActionScript", "AppleScript", "Asp", "BASIC", "C", "C++", "Clojure",
	"COBOL", "ColdFusion", "Erlang", "Fortran", "Groovy", "Haskell", "Java", "JavaScript",
	"Lisp", "Perl", "PHP", "Python", "Ruby", "Scala", "Scheme" ];

$('#he')
	.keyboard({ layout: 'qwerty' })
	.autocomplete({
		source: availableTags
	})
	// position options added after v1.23.4

	.addTyping();
	</script>
	<div id="footer"></div>


</body></html>
