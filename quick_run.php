<?php
date_default_timezone_set("America/Chicago");
require 'Trader.php';
$ticker = "indu";
$from_date = strtotime("-10 months");
$to_date = strtotime("-1 day");
$open = array();
$high = array();
$low = array();
$close = array();
$volume = array();
$interval = "d"; // Can be "d", "w", or "m"
// To retrieve data from Yahoo finance URLs must be formatted like http://real-chart.finance.yahoo.com/table.csv?s=<ticker>&a=<number of month - 1 (from date)>&b=<# of the day (from date)>&c=<year (from date)>&d=<number of month - 1 (to date)>&e=<# of the day (to date)>&f=<year (to date)>&g=<interval (d/w/m)>&ignore=.csv
$url = "http://real-chart.finance.yahoo.com/table.csv?s=".$ticker."&a=".(date("n", $from_date) - 1)."&b=".date("j", $from_date)."&c=".date("Y", $from_date)."&d=".(date("n", $to_date) - 1)."&e=".date("j", $to_date)."&f=".date("Y", $to_date)."&g=".$interval."&ignore=.csv";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$data = curl_exec($ch);
if (curl_errno($ch)) {
  die(curl_error($ch));
} else {
  curl_close($ch);
}
$data = explode("\n", $data);
array_shift($data); // The first line of the returned file does not contain data
array_pop($data); // Take out the last data point
$data = array_reverse($data); // So the newest values are last in the array
foreach ($data as $data_point) {
   $data_point = explode(",", $data_point);
   array_push($open, floatval($data_point[1]));
   array_push($high, floatval($data_point[2]));
   array_push($low, floatval($data_point[3]));
   array_push($close, floatval($data_point[4]));
   array_push($volume, floatval($data_point[5]));
}
$open = array_slice($open, 1000);
$high = array_slice($high, 1000);
$low = array_slice($low, 1000);
$close = array_slice($close, 1000);
$volume = array_slice($volume, 1000);
$stock = new Trader($open, $high, $low, $close, $volume);
@$stock->signal(TRUE);
if (empty($stock->notes)) {
	echo "No recommendation can be made.";
} else {
	echo "Rating: " . round($stock->rating, 2);
	echo "\n\nNotes\n";
	foreach ($stock->notes as $note) {
	   echo "- " . $note . "\n";
	}
}
?>