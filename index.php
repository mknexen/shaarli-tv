<?php

/**
 * ShaarliTV
 * @author nexen (nexen@jappix.com, http://nexen.mkdir.fr/shaarli)
 * @version 0.3 beta
 * @website http://nexen.mkdir.fr/shaarli-tv/
 *
 * Shaarli: http://sebsauvage.net/wiki/doku.php?id=php:shaarli
 * Shaarlo: http://shaarli.fr/
 * tubalr: http://tubalr.com
 *
 * XMPP channel: shaarli@conference.dukgo.com
 */

// Le flux RSS
define('RSS_FEED', 'http://shaarli.fr/index.php?q=youtube&do=rss'); // Execute "/index.php?do=fetch" to fetch feed

// Le chemin complet pour le stockage
define('STORAGE_PATH', __DIR__ . '/data/storage');

// Le title de la page
define('TITLE', 'shaarliTV');



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
	public function insert( Entry $entry ) {

		if( $entry->getYoutubeVideoId() != null ) {

			if( !empty($this->rows) ) {

				foreach( $this->rows as $i => $row ) {

					if( $row->getYoutubeVideoId() == $entry->getYoutubeVideoId() ) {

						unset($this->rows[$i]);
					}
				}
			}

			$this->rows[] = $entry;
		}
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

	protected function _sort( $a, $b ) {

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
	 * getTitle
	 */
	public function getTitle() {

		if( preg_match('/youtube/i', $this->title) ) {

			$parts = explode(' - ', $this->title);

			foreach( $parts as $i => $part ) {
				if( $part == 'YouTube' )
					unset($parts[$i]);
			}

			return implode(' - ', $parts);			
		}

		return $this->title;
	}

	/**
	 * getDescription
	 */
	public function getDescription() {
		return preg_replace('#<script(.*?)>(.*?)</script>#is', '', $this->description);
	}

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
		return 'https://www.youtube.com/watch?v=' . $this->	getYoutubeVideoId();
	}

	/**
	 * getTimestamp
	 */
	public function getTimestamp() {

		return strtotime($this->pubDate);
	}
}

// build storage
$storage = new Storage( STORAGE_PATH );

/**
 * Execute action
 */
if( isset($_GET['do']) ) {

	/**
	 * Fetch shaarlo
	 */
	if( $_GET['do'] == 'fetch' ) {

		// create request
		$options = array(
		  'http' => array(
		    'method' => "GET",
		    'header' => "Accept-language: fr\r\n" .
		              "User-Agent: shaarliTV (https://github.com/mknexen/shaarli-tv)\r\n"
		  )
		);

		$context = stream_context_create($options);

		$body = @file_get_contents(RSS_FEED, false, $context);

		if( !empty($body) ) {

			// parse xml feed
		    $xml = @simplexml_load_string($body);

		    if( !empty($xml )) {

		    	// search youtube videos
		        foreach ($xml->channel->item as $item) {

		        	if( isset($item->link)
		        		&& preg_match('/(http(s)\:\/\/|)www.youtube.(com|fr)\//', $item->link) ) {

		        		// create new entry
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
	}
	/**
	 * VLC Playlist
	 */
	elseif ( $_GET['do'] == 'vlc' ) {

		if( !empty($storage->rows) ) {

			header('Content-Disposition: attachment; filename="shaarliTV-' . date('Y-m-d') . '.xspf"');

			echo '<?xml version="1.0" encoding="UTF-8"?><playlist xmlns="http://xspf.org/ns/0/" xmlns:vlc="http://www.videolan.org/vlc/playlist/ns/0/" version="1"><title>playlist</title><trackList>';

			foreach( $storage->rows as $row ) {

				echo '<track><title>', htmlspecialchars(strip_tags($row->getTitle())), '</title><location>https://www.youtube.com/watch?v=', $row->getYoutubeVideoId() , '</location></track>';
			}

			echo '</trackList></playlist>';			
		}

	}

	exit();
}

?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>shaarliTV</title>
<link href="./favicon.ico" rel="shortcut icon" type="image/x-icon" />
<style type="text/css">
* {
	margin: 0;
	padding: 0;
}
body {
	font-family: helvetica;
	font-size: 11px;
	color: #eee;
	background-color: #202020;
}
h1 {
	font-size: 30px;
	color: #ddd;
}
h1 a {
	text-decoration: none;
}
a {
	color: #ddd;
}
#page {
	width: 800px;
	margin: 20px auto 0 auto;
}
#playlist li {
	list-style: none;
}
#playlist li a {
	font-size: 14px;
	border-left: 1px solid #999;
	border-bottom: 1px solid #444;
    color: #eee;
    padding: 3px 10px;
    text-decoration: none;
    display: block;
}
#playlist li a:hover {
	color: #FF0000;
	cursor: pointer;
	transition: color 0.3s;
}
#playlist li a.active {
	color: #FF0000;
	border-left: 2px solid #FF0000;
}
#playlist .description {
	display: none;
}
#description {
	font-size: 11px;
	margin: 0 0 10px 0;
	padding: 5px 20px 20px 20px;
	max-height: 200px;
	overflow-y: auto;
	border-left: 1px solid #444;
	border-bottom: 1px solid #444;
	display: none;
}
#description a {
	color: #eee;
}
#description .actions{
	float: right;
}
#footer {
	text-align: center;
}
#footer img {
	vertical-align: top;
	border: none;
}
</style>
</head>
<body>
<div id="page">
<h1><a href=""><?php echo TITLE; ?></a></h1>
<div id="ytplayer"></div>
<div id="description"></div>
<ul id="playlist">
<?php if( !empty($storage->rows) ): ?>
<?php foreach( $storage->rows as $row ): ?>
<li>
<a href="<?php echo $row->getPermalink(); ?>" data-videoid="<?php echo $row->getYoutubeVideoId(); ?>">[<?php echo date('d/m/Y H:m:s', $row->getTimestamp()); ?>] <?php echo htmlspecialchars($row->getTitle()); ?></a>
<div class="description">
	<div class="actions">
		(<a class="permalink" href="<?php echo $row->getPermalink(); ?>">Vidéo permalink</a>) (<a href="#" onclick="window.prompt('Download with youtube-dl\n(http://rg3.github.io/youtube-dl/)', 'youtube-dl -f 43 -o &quot;%(title)s.%(ext)s&quot; <?php echo $row->getPermalink(); ?>');" title="Download with youtube-dl">DL</a>)
	</div>
	<?php echo $row->getDescription(); ?>
</div>
</li>
<?php endforeach; ?>
<?php endif; ?>
</ul>
<br />
<div id="footer">
	<p><a href="./index.php?do=vlc"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAA8AAAAPCAYAAAA71pVKAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3QcFExYiMBJgaQAAAlFJREFUKM+Fk01IVGEUhp/vuz/j3PHfEZWs1LKMMKMwggoMd6EYVEREiUEQCFlSEIz5AwpBLlqUIBGELYpoJ61aSAUtapKw6AeFSCodGp2c24zjzNz7tRDCkax3dw7PCy8v5wjW0LtWSrNz6U4keP1znrsvyk47nTdHMhixlnmijaayIkbtKCF7js11j9QvRCau/814qy9ghAqjjfkFNvaCWxJMVNUgRHA1J1cvAr0DFO3Yq8erW5qj9Vex93Ti+LccBxjuOvVv80BvgOmwXen1WpsQEl030DzekwCp/nsZrLZyGKo3ePzd5Zwx1l6VF2nwJWexIuPKeHk/p/bH9MOOOcL8T08O83HmPMq+iFrsRk2dQY3sox/gcqBn7dhdLQc3+Ay2SlCuAhewPODziCMA1wf6MtsOX5L4B10AzPrGE4sl+7FTH0gnwghLJ1xWquzKivVnG8qqb3e3TwJMHJXoXy/48A/GiFzR/J7PzsbhnNzWVMV2wnlNQpMajuMQiycQM7Nm9ZtnHe8PMCTXaVM1D5ykLL8RW74Wx+mcVASLRGybxFWukjjSIC10FAhTpY3c+an2BR9BKd3aP7EjfbJYJdzd0SRUPu9R3qVmoZXvAqsY010iO/oF6+1TQp9eKbcAr+Gjbq7PGBcA0WtiJyk1FvpGfnweLAO8EgwJhrVcayQKkSSqtByRWyJGdQ/HhD2UJQxtqY20ukMalmYBB1QalAuaCVoWKLmc0/SD8OEgzGLdzE+aUueQJgGJ0gsRxAG14nUECB1EHmACBlo6lWr4DVQ716xVEmGWAAAAAElFTkSuQmCC" /> Download VLC playlist</a></p>
	<br />
	<p><a href="http://sebsauvage.net/wiki/doku.php?id=php:shaarli">Shaarli</a> - <a href="http://shaarli.fr/">Shaarlo</a> - <a href="https://github.com/mknexen/shaarli-tv">ShaarliTV 0.3b github</a></p>
</div>
<br />
</div>
</div>
<script type="text/javascript" src="./js/jquery-1.10.2.min.js"></script>
<script type="text/javascript">
var Player = { // module from tubalr.com

  self: null,

  init: function () {

    var tag = document.createElement('script');
    tag.src = "https://www.youtube.com/player_api?version=3";

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
    $('#playlist li a.active').click();
  },

  onPlayerStateChange: function (newState) {
  },

  onPlayerError: function (errorCode) {
  	alert('Unable to load Youtube API');
  }
};

function onYouTubePlayerAPIReady () { Player.youtubeAPIReady(); };

$(function() {

	function loadVideo( node ) {

		$("#description").html($(node).parent().find('div.description').html()).show();

		$('html,body').scrollTop(0);

		Player.self.loadVideoById($(node).attr('data-videoid'), 0);
	}

	$('#playlist li a').click(function() {

		$('#playlist li a.active').removeClass('active');
		$(this).addClass('active');

		if( Player.self == null ) {
			Player.init();
		}
		else {
			loadVideo(this);
		}

		return false;
	});
});
</script>
</body>
</html>
