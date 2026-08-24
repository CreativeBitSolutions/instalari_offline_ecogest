<?php 

$myBuffer="Z,1,______,_,__;1;";







file_put_contents('cm/bon.txt', $myBuffer);


// Quick check to verify that the file exists

// Force the download
header("Content-disposition: attachment; filename=bon.txt");
header("Content-type: text/plain");
readfile("cm/bon.txt");

			
$files = [
    'cm/bon.txt'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        unlink($file);
    } else {
        // File not found.
    }
}

?>