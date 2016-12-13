<?php
class musicDownloader {
	
	public $sql;
	private $source;
	
	public function __construct($sql) {
		$this->sql = $sql;
		include_once("download-sources/YouTube.php");
	}
	
	public function getSong($song, $artist) {
		$this->searchSong = $song;
		$this->searchArtist = $artist;
		
		$this->source = new YouTubeSource($this->sql);
		return $this->source->getMusicFile($song, $artist);
	}
	
	public function reSourceSong($song, $exclude) {
		
		$this->source = new YouTubeSource($this->sql);
		return $this->source->reSourceMusicFile($song, $exclude);
	}
}