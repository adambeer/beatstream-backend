<?php header('Access-Control-Allow-Origin: *'); ?>
<?
include("server_settings.php"); 
include("dbConnect.php");
include("utilities.php"); 
$sql = new MysqliDb($server["host"], $server["user"], $server["pass"], $server["db"]);

$cmd = $_REQUEST["cmd"];
$currSong = $_REQUEST["cSong"];

if($cmd == "testConnect") {
	exit(json_encode(array("status"=>"success")));
}

else if($cmd == "connect") {
	$sql->where("id", 1);
	$session = $sql->getOne("session");
	
	// Generate a random playlist
	$num = $sql->rawQueryOne("SELECT COUNT(*) FROM songs");
	
	$selNum = 0;
	if($num > $playlists["max_length"])
		$selNum = $playlists["max_length"];
	else
		$selNum = $num;
	
	$randSongs = $sql->rawQuery("SELECT * FROM songs ORDER BY RAND() LIMIT ".$selNum);
	$returnedSongs = array("status"=>"success");
	foreach($randSongs as $s) {
		$returnedSongs["songs"][] = array("song"=>$s["song_file"], "title"=>$s["song_name"], "artwork"=>addslashes($s["song_album_art"]), "artist"=>$s["song_artist"], "album"=>$s["song_album"], "id"=>$s["song_id"]);
	}
	exit(json_encode($returnedSongs));
}

else if($cmd == "search") {
	$search = $_REQUEST["search"];
	$by = $_REQUEST["by"];
	
	if($by == "artist")
		$ch = curl_init("http://ws.audioscrobbler.com/2.0/?method=artist.gettoptracks&autocorrect=1&limit=100&artist=".$search."&api_key=".$lastfm_API_key."&format=json");
	if($by == "song") {
		$ch = curl_init("http://ws.audioscrobbler.com/2.0/?method=track.search&track=".$search."&api_key=".$lastfm_API_key."&format=json");
	}
	
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	$data = curl_exec($ch);
	curl_close($ch);
	$return = json_decode($data, true);

	$returnedSongs = array("status"=>"success");
	if($by == "artist") {
		if(count($return["toptracks"]["track"]) > 0)
		foreach($return["toptracks"]["track"] as $s) {
			$returnedSongs["songs"][] = array("song"=>$s["song_file"], "title"=>$s["name"], "artwork"=>$s["image"][3]['#text'], "artist"=>$s["artist"]["name"], "album"=>"");
		}
	}
	else if($by == "song") {
		if(count($return["results"]["trackmatches"]["track"]) > 0)
		foreach($return["results"]["trackmatches"]["track"] as $s) {
			$returnedSongs["songs"][] = array("song"=>$s["song_file"], "title"=>$s["name"], "artwork"=>$s["image"][3]['#text'], "artist"=>$s["artist"], "album"=>"");
		}
	}
	exit(json_encode($returnedSongs));
}

else if($cmd == "download") {
	
	// First check to see if we have any songs that match in the database
	$sql->where("song_name", "%".$_REQUEST["song"]."%", "LIKE");
	$sql->where("song_artist", "%".$_REQUEST["artist"]."%", "LIKE");
	$searchSongs = $sql->get("songs");
	
	if(count($searchSongs) > 0)
		exit(json_encode(array("status"=>"downloaded", "song"=>array("song"=>$searchSongs[0]["song_file"], "title"=>$searchSongs[0]["song_name"], "artwork"=>$searchSongs[0]["song_album_art"], "artist"=>$searchSongs[0]["song_artist"], "album"=>"", "id"=>$searchSongs[0]["song_id"]))));
	
	// No matching songs found, lets try and find it on the interwebs
	if(!empty($_REQUEST["song"]) && !empty($_REQUEST["artist"])) {
		require_once("downloader.php");
		$dloader = new musicDownloader($sql);
		$res = $dloader->getSong($_REQUEST["song"], $_REQUEST["artist"]);
		if($res["status"] == "success") {
			exit(json_encode($res));
		}
		else {
			exit(json_encode(array("status"=>"fail", "reason"=>$res["reason"])));
		}
	}
	else
		exit(json_encode(array("status"=>"fail", "reason"=>"Missing song or artist.")));
}

else if($cmd == "resource") {
	$songID = $_REQUEST["id"];
	
	if(!empty($songID)) {
		$sql->where("song_id", $songID);
		$song = $sql->getOne("songs");
		
		if(!empty($song)) {
			$ignoreSources = explode(",", $song["song_source"]);
			
			require_once("downloader.php");
			$dloader = new musicDownloader($sql);
			$res = $dloader->reSourceSong($song, $ignoreSources);
			if($res["status"] == "success") {
				exit(json_encode($res));
			}
			else {
				exit(json_encode(array("status"=>"fail", "reason"=>$res["reason"])));
			}
		}
		else
			exit(json_encode(array("status"=>"fail", "reason"=>"Song ID wasnt found in the database.")));
	}
	else
		exit(json_encode(array("status"=>"fail", "reason"=>"Song ID wasnt set.")));
}

else if($cmd == "delete") {
	$songID = $_REQUEST["id"];
	
	if(!empty($songID)) {
		$sql->where("song_id", $songID);
		$song = $sql->getOne("songs");
		
		if(!empty($song)) {
			
			// First remove the database entry
			$sql->where("song_id", $songID);
			if($sql->delete("songs")) {
			
				// Next remove the album art if its on the server
				if($song["song_album_art"] != "" && is_file($song["song_album_art"]))
					unlink($song["song_album_art"]);
				
				// Remove the mp3 file
				if(is_file($song["song_file"])) {
					if(unlink($song["song_file"]))
						exit(json_encode(array("status"=>"success")));
					else
						exit(json_encode(array("status"=>"fail", "reason"=>"Failed to remove song file.")));
				}
				
				// As a backup, we can succeed here even if the song file doesnt exist
				exit(json_encode(array("status"=>"success")));
			}
			else
				exit(json_encode(array("status"=>"fail", "reason"=>"Failed to remove song from database.")));
		}
		else
			exit(json_encode(array("status"=>"fail", "reason"=>"Song ID wasnt found in the database.")));
	}
	else
		exit(json_encode(array("status"=>"fail", "reason"=>"Song ID wasnt set.")));
}

else if($cmd == "editSong") {
	$songID = $_REQUEST["id"];
	$title = $_REQUEST["title"];
	$artist = $_REQUEST["artist"];
	$album = $_REQUEST["album"];
	$genre = $_REQUEST["genre"];
	
	// The song title is a required field
	if(empty($title))
		exit(json_encode(array("status"=>"fail", "reason"=>"Song title is required.")));
	
	if(!empty($songID)) {
		
		$upd = array("song_name"=>$title, "song_artist"=>$artist, "song_album"=>$album, "song_genre"=>$genre);
		// Update the song
		$sql->where("song_id", $songID);
		if($sql->update("songs", $upd)) {
			exit(json_encode(array("status"=>"success")));
		}
		else
			exit(json_encode(array("status"=>"fail", "reason"=>"Song failed to update in the database.")));
	}
	else
		exit(json_encode(array("status"=>"fail", "reason"=>"Song ID wasnt set.")));
}