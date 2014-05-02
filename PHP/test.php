<?php
require "GeoHash.php";
$test = new GeoHash\GeoHash([41.034542, 28.974672], 6);
echo $test->range();
echo "<br>";
echo $test->searchRange();

