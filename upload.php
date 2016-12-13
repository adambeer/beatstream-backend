<?
include("server_settings.php"); 
include("dbConnect.php");
$sql = new MysqliDb($server["host"], $server["user"], $server["pass"], $server["db"]);

$cmd=$_REQUEST["cmd"];
if($cmd == "Upload") {
	require_once('getid3/getid3.php');
	$upload = $_FILES["songUpload"];

	if(move_uploaded_file($upload["tmp_name"], "songs/".$upload["name"])) {
		$getID3 = new getID3;
		$ThisFileInfo = $getID3->analyze("songs/".$upload["name"]);
		
		if($ThisFileInfo["tags"]["id3v2"]["title"][0] != "")
			$ins = array("song_name"=>$ThisFileInfo["tags"]["id3v2"]["title"][0], "song_artist"=>$ThisFileInfo["tags"]["id3v2"]["artist"][0], "song_album"=>$ThisFileInfo["tags"]["id3v2"]["album"][0], "song_genre"=>$ThisFileInfo["tags"]["id3v2"]["genre"][0], "song_file"=>"songs/".$upload["name"]);
		if($ThisFileInfo["tags"]["quicktime"]["title"][0] != "")
			$ins = array("song_name"=>$ThisFileInfo["tags"]["quicktime"]["title"][0], "song_artist"=>$ThisFileInfo["tags"]["quicktime"]["artist"][0], "song_album"=>$ThisFileInfo["tags"]["quicktime"]["album"][0], "song_genre"=>$ThisFileInfo["tags"]["quicktime"]["genre"][0], "song_file"=>"songs/".$upload["name"]);
			
		if($sql->insert("songs", $ins))
			echo "Successfully added song!";
		else
			echo $sql->getLastError();
	}
	else
		echo "Error uploading ".$upload["name"]."!";
}
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Music Uploader</title>
</head>

<body>
<form action="upload.php" method="post" enctype="multipart/form-data">
File: <input type="file" name="songUpload" /><br />
<input type="submit" name="cmd" value="Upload" />
</form>
</body>
</html>