<?php
if(is_file($_REQUEST["song"])) {
	header('Access-Control-Allow-Origin: *');
	header('Content-Type: audio/mpeg');
	header('Cache-Control: no-cache');
	header('Content-length: '.filesize($_REQUEST["song"]));
	$song = explode("/", $_REQUEST["song"]);
	header('Content-Disposition: inline; filename="'.$song[1].'"');
	readfile($_REQUEST["song"]);
}
else
	exit("No File");