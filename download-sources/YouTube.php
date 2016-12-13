<?
class YouTubeSource {
	
	public $sql;
	public $reSourcing = false;
	
	public function __construct($sql) {
		$this->sql = $sql;
	}
	
	public function getMusicFile($songName, $artist) {
		
		// Call set_include_path() as needed to point to your client library.
		require_once 'google-api/vendor/autoload.php';
		require_once 'google-api/src/Google/Service/YouTube.php';
		
		/*
		* Set $DEVELOPER_KEY to the "API key" value from the "Access" tab of the
		* Google Developers Console <https://console.developers.google.com/>
		* Please ensure that you have enabled the YouTube Data API for your project.
		*/
		$DEVELOPER_KEY = $youtube_API_key;
		
		$client = new Google_Client();
		$client->setDeveloperKey($DEVELOPER_KEY);
		
		// Define an object that will be used to make all API requests.
		$youtube = new Google_Service_YouTube($client);
		
		try {
			// Call the search.list method to retrieve results matching the specified
			// query term.
			$searchResponse = $youtube->search->listSearch('id,snippet', array(
			'q' => $songName." ".$artist,
			'maxResults' => 25,
			'type' => 'video'
			));
		
			$videos = array();
		
			// Add each result to the appropriate list, and then display the lists of
			// matching videos, channels, and playlists.
			foreach ($searchResponse['items'] as $searchResult) {
				switch ($searchResult['id']['kind']) {
					case 'youtube#video':
					$videos[] = array("id"=>$searchResult['id']['videoId'], "title"=>strtolower($searchResult['snippet']['title']));
					break;
				}
			}
		}
		catch (Google_Service_Exception $e) {
			$htmlBody .= sprintf('<p>A service error occurred: <code>%s</code></p>',
			htmlspecialchars($e->getMessage()));
		}
		catch (Google_Exception $e) {
			$htmlBody .= sprintf('<p>An client error occurred: <code>%s</code></p>',
			htmlspecialchars($e->getMessage()));
		}
		
		// We need to go through the videos and pick the more relevent one fo audio extraction
		$selectedVideo;
		if(count($videos) > 0) {
			foreach($videos as $v) {
				// The title MUST contain both the artist and the song name to be considered
				if (strpos($v["title"], strtolower($songName)) === false || strpos($v["title"], strtolower($artist)) === false){
					continue;
				}
				
				// In reverse-overwriting order of most likely canidates
				if (strpos($v["title"], strtolower($songName)) !== false || strpos($v["title"], strtolower($artist)) !== false) {
					$selectedVideo = $v;
					break;
				}/*
				if (strpos($v["title"], 'lyrics') !== false || strpos($v["title"], 'lyric') !== false) {
					$selectedVideo = $v;
					break;
				}
				if (strpos($v["title"], 'audio') !== false) {
					$selectedVideo = $v;
					break;
				}*/
			}
		}

		// Next initialize mp3 conversion and get the link to download the file
		if(!is_null($selectedVideo)) {
			$ch = curl_init("https://www.youtubeinmp3.com/fetch/?format=json&video=http://youtube.com/watch?v=".$selectedVideo["id"]);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$data = curl_exec($ch);
			curl_close($ch);
			$json = json_decode($data);
		
			// Get the mp3 file data
			$ch = curl_init($json->link);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			$data = curl_exec($ch);
			curl_close($ch);
			
			// Save the mp3 data into file format on the server
			$songFilename = preg_replace( "/[^a-z0-9:\- ]/i", "", $songName." ".$artist); // Remove unwanted characters from the song name
			$songFilenameReal = $songFilename." ".time();
			if(file_put_contents("songs/".$songFilenameReal.".mp3", $data)) {
				
				require_once('getid3/getid3.php');
				$getID3 = new getID3;
				$ThisFileInfo = $getID3->analyze("songs/".$songFilenameReal.".mp3");
				$ins = array("song_file"=>"songs/".$songFilenameReal.".mp3", "song_source"=>$selectedVideo["id"], "song_plays"=>1);
				
				if($ThisFileInfo["tags"]["id3v2"]["album"][0] != "" && strpos($ThisFileInfo["tags"]["id3v2"]["album"][0], "youtube") === false)
					$ins["song_album"] = $ThisFileInfo["tags"]["id3v2"]["album"][0];
				if($ThisFileInfo["tags"]["id3v2"]["genre"][0] != "" && strpos($ThisFileInfo["tags"]["id3v2"]["genre"][0], "youtube") === false)
					$ins["song_genre"] = $ThisFileInfo["tags"]["id3v2"]["genre"][0];
				if($ThisFileInfo["tags"]["id3v2"]["artist"][0] != "" && strpos($ThisFileInfo["tags"]["id3v2"]["artist"][0], "youtube") === false)
					$ins["song_artist"] = $ThisFileInfo["tags"]["id3v2"]["artist"][0];
				else
					$ins["song_artist"] = $artist;
				if($ThisFileInfo["tags"]["id3v2"]["title"][0] != "" && strpos($ThisFileInfo["tags"]["id3v2"]["title"][0], "youtube"))
					$ins["song_name"] = $ThisFileInfo["tags"]["id3v2"]["title"][0];
				else
					$ins["song_name"] = $songName;
					
				if(isset($ThisFileInfo['comments']['picture'][0])) {
					if($ThisFileInfo["tags"]["id3v2"]["album"][0] != "" && strpos($ThisFileInfo["tags"]["id3v2"]["album"][0], "youtube") === false)
						$filename = $artist. " ".$ThisFileInfo["tags"]["id3v2"]["album"][0];
					else
						$filename = $songFilename;
					
					$ins["song_album_art"] = base64_to_jpeg('data:'.$ThisFileInfo['comments']['picture'][0]['image_mime'].';charset=utf-8;base64,'.base64_encode($ThisFileInfo['comments']['picture'][0]['data']), "art/".$filename.".jpg");
				}
				
				if($this->sql->insert("songs", $ins)) {
					return $ret = array("status"=>"success",
					"song"=>array("song"=>"songs/".$songFilenameReal.".mp3", "id"=>$this->sql->getInsertId()));
				}
				else {
					// The song failed to insert into the DB, remove the mp3 and the album art if it exists
					if(file_exists("art/".$filename.".jpg"))
						unlink("art/".$filename.".jpg");
					unlink("songs/".$songFilenameReal.".mp3");
					return array("status"=>"fail", "reason"=>"Database insert failed: ".$sql->getLastError());
				}
			}
			else {
				unlink("songs/".$songFilenameReal.".mp3");
				return array("status"=>"fail", "reason"=>"Failed to save song to server.");
			}
		}
		else {
			return array("status"=>"fail", "reason"=>"Failed to find suitable source.");
		}
	}
	
	public function reSourceMusicFile($song, $exclude) {
		
		// Call set_include_path() as needed to point to your client library.
		require_once 'google-api/vendor/autoload.php';
		require_once 'google-api/src/Google/Service/YouTube.php';
		
		/*
		* Set $DEVELOPER_KEY to the "API key" value from the "Access" tab of the
		* Google Developers Console <https://console.developers.google.com/>
		* Please ensure that you have enabled the YouTube Data API for your project.
		*/
		$DEVELOPER_KEY = 'AIzaSyDoqbH3EhyE9iwnxuPA33u4-A-CqZNf9rg';
		
		$client = new Google_Client();
		$client->setDeveloperKey($DEVELOPER_KEY);
		
		// Define an object that will be used to make all API requests.
		$youtube = new Google_Service_YouTube($client);
		
		try {
			// Call the search.list method to retrieve results matching the specified
			// query term.
			$searchResponse = $youtube->search->listSearch('id,snippet', array(
			'q' => $song["song_name"]." ".$song["song_artist"],
			'maxResults' => 25,
			'type' => 'video'
			));
		
			$videos = array();
		
			// Add each result to the appropriate list, and then display the lists of
			// matching videos, channels, and playlists.
			foreach ($searchResponse['items'] as $searchResult) {
				switch ($searchResult['id']['kind']) {
					case 'youtube#video':
					if(in_array($searchResult['id']['videoId'], $exclude))
						continue;
					else
						$videos[] = array("id"=>$searchResult['id']['videoId'], "title"=>strtolower($searchResult['snippet']['title']));
					break;
				}
			}
		}
		catch (Google_Service_Exception $e) {
			$htmlBody .= sprintf('<p>A service error occurred: <code>%s</code></p>',
			htmlspecialchars($e->getMessage()));
		}
		catch (Google_Exception $e) {
			$htmlBody .= sprintf('<p>An client error occurred: <code>%s</code></p>',
			htmlspecialchars($e->getMessage()));
		}
		
		// We need to go through the videos and pick the more relevent one fo audio extraction
		$selectedVideo;
		if(count($videos) > 0) {
			foreach($videos as $v) {
				// The title MUST contain both the artist and the song name to be considered
				if (strpos($v["title"], strtolower($song["song_name"])) === false || strpos($v["title"], strtolower($song["song_artist"])) === false){
					continue;
				}
				
				// In reverse-overwriting order of most likely canidates
				if (strpos($v["title"], strtolower($song["song_name"])) !== false || strpos($v["title"], strtolower($song["song_artist"])) !== false) {
					$selectedVideo = $v;
					break;
				}/*
				if (strpos($v["title"], 'lyrics') !== false || strpos($v["title"], 'lyric') !== false) {
					$selectedVideo = $v;
					break;
				}
				if (strpos($v["title"], 'audio') !== false) {
					$selectedVideo = $v;
					break;
				}*/
			}
		}

		// Next initialize mp3 conversion and get the link to download the file
		if(!is_null($selectedVideo)) {
			$ch = curl_init("https://www.youtubeinmp3.com/fetch/?format=json&video=http://youtube.com/watch?v=".$selectedVideo["id"]);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$data = curl_exec($ch);
			curl_close($ch);
			$json = json_decode($data);
		
			// Get the mp3 file data
			$ch = curl_init($json->link);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			$data = curl_exec($ch);
			curl_close($ch);
			
			// Save the mp3 data into file format on the server
			$songFilename = preg_replace( "/[^a-z0-9:\- ]/i", "", $song["song_name"]." ".$song["song_artist"]); // Remove unwanted characters from the song name
			$songFilenameReal = $songFilename." ".time();
			if(file_put_contents("songs/".$songFilenameReal.".mp3", $data)) {
				
				$exclude[] = $selectedVideo["id"];
				$newSources = $exclude;
				$upd = array("song_file"=>"songs/".$songFilenameReal.".mp3", "song_source"=>implode(",", $newSources), "song_plays"=>"song_plays+1");
				  
				$this->sql->where("song_id", $song["song_id"]);
				if($this->sql->update("songs", $upd)) {
					unlink($song["song_file"]); // Remove the old song if were successful
					return $ret = array("status"=>"success", "song"=>array("song"=>"songs/".$songFilenameReal.".mp3", "id"=>$song["song_id"]));
				}
				else {
					return array("status"=>"fail", "reason"=>"Database insert failed: ".$sql->getLastError());
				}
			}
			else {
				return array("status"=>"fail", "reason"=>"Failed to save song to server.");
			}
		}
		else {
			return array("status"=>"fail", "reason"=>"Failed to find suitable source.");
		}
	}
}
?>