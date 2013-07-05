<?php

/**
 * ShaarliTV
 * @author nexen (nexen@jappix.com)
 * @version 0.1beta
 *
 * Shaarli: http://sebsauvage.net/wiki/doku.php?id=php:shaarli
 * Shaarlo: http://shaarli.fr/
 * tubalr: http://tubalr.com
 */

/**
 * Storage
 */
class Storage {

	private $filename = null;

	public $rows = array();

	/**
	 * Constructor
	 */
	public function __construct( $filename ) {

		if( file_exists($filename) ) {

			$this->rows = unserialize(file_get_contents($filename));
		}

		$this->filename = $filename;
	}

	/**
	 * Insert
	 */
	public function insert( $entry ) {

		if( !empty($this->rows) ) {

			foreach( $this->rows as $i => $row ) {

				if( $row->getYoutubeVideoId() == $entry->getYoutubeVideoId() ) {

					unset($this->rows[$i]);
				}
			}
		}

		$this->rows[] = $entry;
	}

	/**
	 * Exist
	 */
	public function exist( $entry ) {

		if( !empty($this->rows) ) {

			foreach( $this->rows as $row ) {

				if( $row->getYoutubeVideoId() == $entry->getYoutubeVideoId() ) {

					return true;
				}
			}
		}

		return false; // not exist
	}

	/**
	 * Save
	 */
	public function save() {

		file_put_contents($this->filename, serialize($this->rows));
	}

	/**
	 * Sort
	 */
	public function sort() {

		usort($this->rows, array($this, '_sort'));
	}

	public function _sort( $a, $b ) {

		$x = $a->getTimestamp();
		$y = $b->getTimestamp();

		if ($x == $y) return 0;

		if( true ) // desc order
			return ($x < $y) ? 1 : -1;
		else
			return ($x < $y) ? -1 : 1;
	}
}

/**
 * Feed entry
 */
class Entry {

	public $title = null;
	public $link = null;
	public $pubDate = null;
	public $description = null;
	public $category = null;

	public $videoID = false;

	/**
	 * getYoutubeVideoId
	 * Source : http://stackoverflow.com/questions/3737634/regex-to-match-youtube-urls
	 */
	public function getYoutubeVideoId() {

		if( $this->videoID === false ) {

			$query_string = parse_url($this->link, PHP_URL_QUERY); // v=Zu4WXiPRek

			$query_string_parsed = array();
			parse_str($query_string, $query_string_parsed); // an array with all GET params

			$this->videoID = isset($query_string_parsed["v"]) ? $query_string_parsed["v"] : null;
		}

		return $this->videoID;
	}

	/**
	 * getPermalink
	 */
	public function getPermalink() {
		
	}

	/**
	 * getTimestamp
	 */
	public function getTimestamp() {

		return strtotime($this->pubDate);
	}
}

// build storage
$storage = new Storage( __DIR__ . '/data/storage' );

/**
 * Fetch shaarlo
 */
if( isset($_GET['fetch']) ) {

	$feed = 'http://shaarli.fr/?do=rss';

	$results = array();

	$body = @file_get_contents($feed);

	if( !empty($body) ) {

	    $xml = @simplexml_load_string($body);

	    if( !empty($xml )) {

	        foreach ($xml->channel->item as $item) {

	        	if( isset($item->link) && preg_match('/http(s)\:\/\/www.youtube.(com|fr)\//', $item->link) ) {

	        		$entry = new Entry();
	        		$entry->title = (string) $item->title;
	        		$entry->link = (string) $item->link;
	        		$entry->pubDate = (string) $item->pubDate;
	        		$entry->description = (string) $item->description;
	        		$entry->category = (string) $item->category;

	        		$storage->insert( $entry );       		
	        	}
	        }
	    }
	}

	$storage->sort();

	// echo '<pre>'; print_r($storage);
	$storage->save();
	exit();
}

?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>shaarliTV</title>
<style type="text/css">
* {
	margin: 0;
	padding: 0;
}
body {
	font-family: helvetica;
	font-size: 15px;
	color: #eee;
	background-color: #202020;
}
h1 {
	color: #ddd;
}
#page {
	width: 800px;
	margin: 20px auto 0 auto;
}
#playlist li {
	list-style: none;
    border-bottom: 1px solid #999;
    color: #eee;
    padding: 2px 10px;
}
#playlist li.active {
	color: #FF0000;
}
#playlist .description {
	display: none;
}
#description {
	font-size: 11px;
	padding: 5px 20px;
}
#description a {
	color: #eee;	
}
#footer a {
	color: #eee;
	font-size: 10px;
	text-align: right;
}
</style>
</head>
<body>
<div id="page">
<h1>shaarliTV</h1>
<div id="ytplayer"></div>
<div id="description"></div>
<ul id="playlist">
<?php if( !empty($storage->rows) ): ?>
<?php foreach( $storage->rows as $row ): ?>
<li data-videoid="<?php echo $row->getYoutubeVideoId(); ?>">
	[<?php echo date('d/m/Y H:m:s', $row->getTimestamp()); ?>] <?php echo $row->title ?>
	<div class="description">
		<?php echo $row->description; ?>
	</div>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<br />
<div id="footer">
	<a href="http://sebsauvage.net/wiki/doku.php?id=php:shaarli">Shaarli</a> - <a href="http://shaarli.fr/">Shaarlo</a> - <a href="">ShaarliTV 0.1 beta - Source code</a>
</div>
</div>
</div>
<script type="text/javascript" src="http://code.jquery.com/jquery-1.10.2.min.js"></script>
<script type="text/javascript">
var Player = { // module from tubalr.com
  self: null,
  listeners: [],
  init: function () {

    var tag = document.createElement('script');
    tag.src = "http://www.youtube.com/player_api?version=3";

    var firstScriptTag = document.getElementsByTagName('script')[0];
    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);    
  },

  youtubeAPIReady: function () {

    Player.self = new YT.Player('ytplayer', {
      width:    800,
      height:   400,
      version:  3,
      playerVars: { 'autoplay': 1, 'rel': 0, 'theme': 'dark', 'showinfo': 0, 'autohide': 1, 'wmode': 'opaque', 'allowScriptAccess': 'always', 'version': 3, 'restriction': 'US' },
      events: {
        'onReady':        Player.onPlayerReady,
        'onStateChange':  Player.onPlayerStateChange,
        'onError':        Player.onPlayerError
      }
    });
  },

  onPlayerReady: function () {
    $('#playlist li:first').click();
    Player.self.stopVideo();
  },

  onPlayerStateChange: function (newState) {
  },

  onPlayerError: function (errorCode) {
  }
};

function onYouTubePlayerAPIReady () { Player.youtubeAPIReady(); };

$(function() {

	Player.init();

	$('#playlist li').click(function() {

		$('#playlist li.active').removeClass('active');
		$(this).addClass('active');

		$("#description").html($(this).find('.description').html());

		Player.self.loadVideoById($(this).attr('data-videoid'), 0);
	});
});
</script>
</body>
</html>
