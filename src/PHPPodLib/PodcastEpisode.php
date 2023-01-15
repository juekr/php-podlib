<?php
/*
    Podcast episode class

*/

namespace PHPPodLib;

class PodcastEpisode {
    private $xmlItem;
    private $itemHash;
    private $namespaces;
    private $isValid = false;
    private $meta = array(
        "chapters" => null,
        "title" => null, 
        "subtitle" => null, 
        "duration" => null,
        "shownotes" => null, 
        "enclosure" => null, 
        "chapters" => array(),
        "pubdate" => null, 
        "guid" => null, 
        "summary" => null,
        "tags" => array(), 
        "contributors" => null,
        "cover" => null,
        "description" => null,
        "episode" => null,
        "episodeType" => null,
        "season" => null,
        "link" => null
    );

    public function __construct(\SimpleXMLElement $item = null, bool $debug = false) {
        $this->debug = $debug;
        if ($item == null || empty($item)):
            if ($debug):
                echo "ERROR: no xml item provided\n";
                return;
            endif;
        endif;
        $this->xmlItem = $item;
        $this->itemHash = md5($item);
        $this->namespaces = $this->xmlItem->getNamespaces(true);
        $this->extractMetaFromItem();
        $this->isValid = true;
    }

    // Simple getter functions
    public function isValid() { return $this->isValid; }
    public function getHash() { return $this->itemHash; }
    
    public function getDuration(bool $inSeconds = false) { 
        $duration = $this->getMeta("duration");
        if (empty($duration)) $duration = 0;
        if ($inSeconds === true) return $duration; 
        if (substr_count($duration, ":") == 0) return $this->convertSecondsToTimestring($duration);
        return $duration;
    }

    public function getChapters() { return $this->getMeta("chapters"); }
    public function getCover() { return $this->getMeta("cover"); }
    public function getImage() { return $this->getCover(); }
    public function getMedia() { return $this->getMeta("enclosure"); }
    public function getEnclosure() { return $this->getMedia(); }
    public function getTags() { return $this->getMeta("tags"); }
    public function getKeywords() { return $this->getTags(); }
    public function getContent() { return $this->getMeta("shownotes"); }
    public function getShownotes() { return $this->getContent(); }
    public function getTitle() { return $this->getMeta("title"); }
    public function getSubtitle() { return $this->getMeta("subtitle"); }
    public function getGuid() { return $this->getMeta("guid"); }
    public function getDescription() { return $this->getMeta("description"); }
    public function getSummary() { return $this->getMeta("description"); }
    public function getEpisodeNumber() { return $this->getMeta("episode"); }
    public function getEpisodeType() { return $this->getMeta("episodeType"); }
    public function getSeason() { return $this->getMeta("season"); }
    public function getLink() { return $this->getMeta("link"); }

    private function getMeta(string $key = null) {
        if ($key === null) return $this->meta;
        if (in_array($key, array_keys($this->meta))) return $this->meta[$key];
        return null;
    }

    function getPubdate(string $format = "r") {
        try {
            return date($format, $this->meta["pubdate"]);
        } catch (\Exception $e) {
            if ($this->debug) echo "WARNING: Could not convert pubdate: ".$e->getMessage()."\n";
            return null;
        }
    }

    // Collecting and handling item meta
    private function extractMetaFromItem(bool $debug = false)  {
        if (empty($this->xmlItem)) return false;

        $this->extractChapters();

        foreach (array("guid", "title", "description", "link") as $lowHangingFruit):
            $this->meta[$lowHangingFruit] = (string) $this->xmlItem->$lowHangingFruit;
        endforeach;
        
        $this->extractUnnestedtemsFromNamespace("itunes", array("subtitle", "author", "episode", "episodeType", "summary", "explicit", "block"));
        
        $this->extractCover();
        $this->extractPubdate();
        $this->extractDuration();
        $this->extractMedia();
        $this->extractShownotes();
        $this->extractKeywords();
    }

    private function extractShownotes() {
        // Shownotes are usually stored in in <content:encoded>
        if (!in_array("content", array_keys($this->namespaces))) return;
        $content = $this->xmlItem->xpath(".//content:encoded");
        if (is_array($content) && count($content) > 0):
            $content = (string)($content[0]);
        else:
            return;
        endif;
        $this->meta["shownotes"] = $content;
    }

    private function extractKeywords() {
        if (!in_array("itunes", array_keys($this->namespaces))) return;
        $tags = @(array)$this->xmlItem->children("itunes", true)->keywords;
        if (is_array($tags) && count($tags) > 0):
            $tags = (string)($tags[0]);
        else:
            // TODO: parse <link> for keywords in website meta
            return;
        endif;
        $this->meta["tags"] = array_map("ucwords", array_map("trim", explode(",", $tags))); // Trim all elements and applay ucwords
    }

    private function extractMedia() {
        // Extract the actual podcast episode media file from enclosure
        $attributes = $this->xmlItem->enclosure->attributes();
        if (isset($attributes["url"]) && !empty($attributes["url"])) {
            $this->meta["enclosure"]["url"] = (string) $attributes["url"];
        }
        if (isset($attributes["length"]) && !empty($attributes["length"])) {
            $this->meta["enclosure"]["length"] = (string) $attributes["length"];
        }
        if (isset($attributes["type"]) && !empty($attributes["type"])) {
            $this->meta["enclosure"]["type"] = (string) $attributes["type"];
        }
    }

    private function extractDuration() {
        // Check feed for pudate – and convert it to php dates
        $duration = $this->xmlItem->duration;
        if (!$duration || empty($duration)):
            if (in_array("itunes", array_keys($this->namespaces))):
                $duration = $this->xmlItem->xpath(".//itunes:duration");
                if (is_array($duration) && count($duration) > 0):
                    $duration = (string)($duration[0]);
                else:
                    $duration = null;
                endif;
            endif;
        endif;
        if (strpos($duration, ":") >= 0):
            $this->meta["duration"] = $this->convertTimstringToSeconds($duration);
        else:
            $this->meta["duration"] = $duration;
        endif;
    }

    private function extractPubdate() {
        // Check feed for pudate – and convert it to php dates
        $pubdate = $this->xmlItem->pubDate;
        if ($pubdate) $this->meta["pubdate"] = strtotime($pubdate);
    }

    private function extractChapters() {
        // Check feed for psc:-style chapter information
        if (empty($this->xmlItem)) return;
        if (!in_array("psc", array_keys($this->namespaces))) return;
        $chapters = $this->xmlItem->xpath(".//psc:chapters/psc:chapter");
        if (!$chapters || empty($chapters) || !is_array($chapters) || count($chapters) == 0) return;
        foreach ($chapters as $chapter):
            $this->meta["chapters"][(string) $chapter->attributes()["start"]] = (string) $chapter->attributes()["title"];
        endforeach;
    }

    private function extractUnnestedtemsFromNamespace(string $namespace, array $arrayOfKeys = []) {
        // Copy of the same function in podcast class
        // Extract unnested items from namespaces like podcast: or itunes: 
        if (in_array($namespace, array_keys($this->namespaces))):
            foreach ($arrayOfKeys as $lowHangingFruit):
                $fruit = $this->xmlItem->xpath(".//".$namespace.":".$lowHangingFruit);
                if (is_array($fruit) && count($fruit) > 0):
                    $this->meta[$lowHangingFruit] = (string)($fruit[0]);
                else:
                    $this->meta[$lowHangingFruit] = null;
                endif;
            endforeach;
        endif;
    }

    private function extractCover() {
        $image = null;
        try {
            $image = (string)$this->xmlItem->image->url;
        } catch (\Exception $e) {
            if ($this->debug) echo "WARNING: image not correctly referenced – ".$e->getMessage()."\n";
        }
        if ($image == null || $image == "") {
            if (in_array("itunes", array_keys($this->namespaces))):
                $image = $this->xmlItem->xpath(".//itunes:image");
                if (count($image) > 0):
                    $image = $image[0];
                    if (isset($image->attributes()["href"])):
                         $image = (string)$image->attributes()["href"];
                    endif;
                else:
                    $image = "";
                endif;
            endif;
        }
        $this->meta["cover"] = $image;
    }

    // Helper functions ===================================
    private function convertSecondsToTimestring($duration, $extralong = false) {
        // Convert duration in seconds to a standard time string (i. e. 01:34:26)
		$hours = floor($duration / 3600);
		$duration -= $hours * 3600;
		$minutes = floor($duration / 60);
		$duration -= $minutes * 60;
		$days = floor($hours / 24);
		if ($days > 0) $hours -= $days * 24;
		if ($extralong) return ($days > 0 ? $days." Tage, " : "") . $hours." Stunden, ".$minutes." Minuten, ".$duration." Sekunden";
		return ($days > 0 ? $days.":" : "") . $hours.":".sprintf("%02d", $minutes).":".sprintf("%02d", $duration);
	}

    private function convertTimstringToSeconds($string) {
        if (substr_count($string, ":") == 0):
            try { 
                $string = intval($string);
            } catch (\Exception $e) {
                $string = 0;
            }
            return $string;
        endif;
        $parts = explode(":", $string);
        $len = (count($parts));
        if ($len >= 1) $seconds = $parts[$len-1]; #seconds
        if ($len >= 2) $seconds += 60 * $parts[$len-2]; #minutes
        if ($len >= 3) $seconds += 60 * 60 * $parts[$len-3]; #hours
        if ($len >= 4) $seconds += 24 * 60 * 60 * $parts[$len-4]; #days
        return $seconds;
    }

    public function isMatch(string $matchtype = null, string $field = null, string $pattern = null) {
        // Currently the function for matching tags, title, episodetype and basically every other xml tag content support searching for "string", substring or subarray "contains" or "regex" patterns
        if (!isset($this->meta[$field]) || $this->meta[$field] === null) return false;
        if ($matchtype == null) return false;
        if ($field == null) return false;
        $target = $this->meta[$field];

        if (strpos($matchtype, "_caseinsensitive")): 
            $pattern = strtolower($pattern);
            if (is_array($target)):
                $target = array_map("strtolower", $target);
            elseif (is_string($target)):
                $target = strtolower($target);
            endif;
        endif;

        switch($matchtype):
            case "string_caseinsensitive":
            case "string":
            case "integer":
            case "int":
                return $pattern == $target;
                break;
            case "regex":
                return (bool) preg_match($pattern, $target);
                break;
            case "contains_casesensitive":
            case "contains":
                if (is_array($target)):
                    return in_array($pattern, $target);
                elseif (is_string($target)):
                    return strpos(strtolower($target), strtolower($pattern)) >= 0;
                endif;
                return false;
                break;
            default:
                return false;
                break;
        endswitch;
    }

    public function intelligentGetContent(string $length = "l", bool $stripHtml = true, bool $reduceLineBreaks = true) {
        /*  
            !EXPERIMENTAL!
            The problem is: there are at least 4 different content fields (<content:encoded>, <summary>, <description>, <subtitle>) with varying length, presence of html tags and so on. 
            
            We try to figure out the user's intent and the content piece in the format that fits best
            Parameters:
              - length: s|sm, m|md, l|lg|xl
              - reduceLineBreaks: reduce multiple \n to a single \n
              - stripHtml: remove all HTML tags

            Preparation: sort by length, filter out duplicates and empties, remove tags if necessary and reindexes the array
        */
        $contentPieces = array(
            $this->getSubtitle(),
            $this->getSummary(),
            $this->getDescription(),
            $this->getShownotes()
        );
        // Only keep uniques
        $contentPieces = array_unique($contentPieces);
        // Sort by string length
        usort($contentPieces, function($a, $b){
            return strlen($a) > strlen($b);
         });
         // Strip html
        if ($stripHtml === true) $$contentPieces = array_map("trim", array_map("strip_tags", $contentPieces));
        // Remove empties and double line breaks
        foreach ($contentPieces as $i => $piece): 
            if (empty($piece)): 
                unset($contentPieces[$i]);
                continue;
            endif;
            if ($reduceLineBreaks) $contentPieces[$i] = preg_replace("/(\n{2,})/ius", "\n", $piece);
        endforeach;
        // Reindex
        $contentPieces = array_values($contentPieces);
        // return 0th, 1st, or last content piece item
        if (strtolower(substr($length, 0, 1)) == "s"):
            return $contentPieces[0];
        elseif (strtolower(substr($length, 0, 1)) == "m"):
            return count($contentPieces) > 2 ? $contentPieces[1] : $contentPieces[count($contentPieces)-1];
        else:
            return $contentPieces[count($contentPieces)-1];
        endif;
    }

    public function getSlimPodlovePlayer(PodcastFeed $p, array $colors = []) {
        $player = '
        <div id="podlove-player-slim">
            <root style="border-radius: 0px 0px 40px 0px; width: 100%; min-width: 320px; max-width: 440px; overflow: hidden; max-height: 80px;" class="pproot grid grid-rows-2 grid-flow-col gap-2 pt-1 pe-0 me-0">
                <div class="row-span-2 p-2col-span-1 content-start ">
                    <poster class="w-16 h-16 pl-1"></poster>
                </div>
                <div class="row-span-1 p-2 col-span-2 content-start pt-1 ">
                    <play-button variant="simple"></play-button>
                </div>
                <div class="row-span-1 col-span-8 content-center pt-0">
                    <progress-bar class="place-self-start"></progress-bar>
                </div>
                <div class="row-span-1 p-2 col-span-6 gap-1 content-start pt-1 mt-0">
                    <episode-title></episode-title>
                    <episode-subtitle></episode-subtitle>
                </div>
            </root>
        </div>';
        $podcastName = $p->getTitle();
        $podcastSubtitle = $p->getSubtitle();
        $podcastSummary = $p->intelligentGetContent("l", true, true);
        $podcastCover = $p->getCover();
        $podcastFeed = $p->getFeedURL();
        $subscribe = true;
        $chapterstring = $this->chapters2string();
        $enclosure = $this->getMedia();
        $podcastLink = $p->getLink();
        $player .= '<script type="text/javascript">podlovePlayer("#podlove-player-slim", {
            "version": 5,
            "activeTab": "chapters",
            "show": {
                "title": "'.$podcastName.'",
                "subtitle": "'.$podcastSubtitle.'",
                "summary": '.json_encode($podcastSummary).',
                "poster": "'.($podcastCover ? $podcastCover : '').'",
                "link": "'.$podcastLink.'", '.
                // "link": "https://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'"
                '
            },
            "subscribe-button": '.($subscribe ? '{ feed: "'.$podcastFeed.'",
            clients: [
                {
                    id: "google-podcasts",
                    service: "'.$podcastFeed.'" // feed
                },
                {
                    id: "pocket-casts",
                    service: "'.$podcastFeed.'" // feed
                },
                {
                    id: "podcast-addict"
                },
                {
                    id: "podcat"
                },
                {
                    id: "rss",
                    service: "'.$podcastFeed.'"
                }
                ]
            }':'false') .'
            ,
            "title": '.json_encode($this->getTitle()).',
            "subtitle": "'.$this->intelligentGetContent("s", true, true).'",
            "summary": '.json_encode($this->intelligentGetContent()).',
            "publicationDate": "'.$this->getPubDate().'",
            "poster": "'.($this->getImage() ? $this->getImage() : $podcastCover).'",
            "duration": "'.$this->getDuration().'",
            "link": "'.$this->getLink().'",
            "audio": [
                {
                "url": "'.(isset($enclosure['url']) ? ($enclosure['url']) : '""').'",
                "size": '.(isset($enclosure['length']) ? ($enclosure['length']) : '0').',
                "title": "'.(isset($enclosure['url']) ? substr(pathinfo($enclosure['url'])['extension'],0,strpos(pathinfo($enclosure['url'])['extension'], '?')) : '""').'",
                "mimeType": "'.(isset($enclosure['type']) ? ($enclosure['type']) : '""').'"
                }
            ]'.
            $chapterstring.',
            "runtime": {
                "locale": "de-De",
                "language": "de"
            },'.
            '"visibleComponents": [
                "tabInfo",
                "tabChapters",
                "tabDownload",
                "tabAudio",
                "tabShare",
                "poster",
                "showTitle",
                "episodeTitle",
                "subtitle",
                "progressbar",
                "subscribe",
                "controlSteppers",
                "controlChapters"
            ]}, {
                "version": 5,
                "theme": {
                    "tokens": {
                    "brand": "'.(isset($color['brand']) ? $color['brand'] : '#E64415').'",
                    "brandDark": "'.(isset($color['brandDark']) ? $color['brandDark'] : '#235973').'",
                    "brandDarkest": "'.(isset($color['brandDarkest']) ? $color['brandDarkest'] : '#000').'",
                    "brandLightest": "'.(isset($color['brandLightest']) ? $color['brandLightest'] : '#DDCFB4').'",
                    "shadeDark": "'.(isset($color['shadeDark']) ? $color['shadeDark'] : '#888').'",
                    "shadeBase": "'.(isset($color['shadeBase']) ? $color['shadeBase'] : '#444').'",
                    "contrast": "'.(isset($color['contrast']) ? $color['contrast'] : '#111').'",
                    "alt": "'.(isset($color['alt']) ? $color['alt'] : '#FFF').'"
                    }
                },
            })</script>';

            return $player;
    }

    private function chapters2string(array $chapters = null) {
        if (is_null($chapters)) $chapters = $this->getChapters();
        if (empty($chapters)) return "";
        $charr = array();
        foreach ($chapters as $key => $c) {
            $temp = array();
            $start = "";
            if (isset($c["start"]) && $c['start'] != "") { $temp[] = '"start" : "'.$c['start'].'"'; $start = $c["start"]; }
            if (strpos($key, ":") > 0) { $temp[] = '"start" : "'.$key.'"'; $start = $key; }
            if (isset($c["title"]) && $c['title'] != "") $temp[] = '"title" : "'.addslashes($c['title']).'"'; else $temp[] = '"title": "'.addslashes($c).'"';;
            if (isset($c['href']) && $c['href'] != "") $temp[] = '"href" : "'.$c['href'].'"';
            if (isset($c['image']) && $c['image'] != "") $temp[] = '"image" : "'.$c['image'].'"'; 
            $charr[$start] = implode(", ", $temp);
        }
        $chapterstring = ", \"chapters\": [ {";
        $chapterstring .= implode("} , \n		{", $charr);
        $chapterstring .= "} ]";
        return $chapterstring;

        // We could read chapters from the mp3 files ... but that's way to resourceful
			// } else {
			// 	require_once($this->id3HelperPath."getid3.php");
			// 	$getID3 = new \getID3();
			// 	if (isset($enclosure['url'])): 
			// 		$mp3 = $this->cache->fetchFile($enclosure['url']);
			// 		$ThisFileInfo = $getID3->analyze($mp3);
			// 		$getID3->CopyTagsToComments($ThisFileInfo);
					
			// 		$chapters = $this->recursiveFind($ThisFileInfo, "chapters");
			// 		if ($chapters != false && count($chapters) > 0):
			// 			$chapterstring = ", \"chapters\": [ ";
			// 			$charr = array();
			// 			foreach ($chapters as $chapter):
			// 				$charrline = array();
			// 				$charrline[] = '"start": "'.(isset($chapter['time_begin']) ? $this->formatSeconds($chapter['time_begin']/1000):"").'"' ;
			// 				$charrline[] = '"title": "'.(isset($chapter['chapter_name']) ? addslashes($chapter['chapter_name']):"").'"' ;
			// 				$charrline[] = '"href": "'.(isset($chapter['chapter_url']) ? (is_array($chapter['chapter_url']) ? array_shift($chapter['chapter_url']) : $chapter['chapter_url']) :"").'"' ;
			// 				$charrline[] = '"image": "'.(isset($chapter['chapter_image']) ? (is_array($chapter['chapter_image']) ? array_shift($chapter['chapter_image']) : $chapter['chapter_image']):"").'"' ;
			// 				$charr[$chapter['time_begin']/1000] = "{". implode(",\n				", $charrline)."}";
			// 			endforeach;
			// 			ksort($charr);
			// 			$chapterstring .=  implode(",\n			", $charr);
			// 				#$chapterstring .= implode("} , {", $charr);
			// 			$chapterstring .= " ]";
			// 		endif;

			// 	endif;
			// }
    }

    public function getPlayer(array $podcast = array("feed" => "", "link" => "", "name" => "", "subtitle" => "", "summary" => "", "cover" => ""), $type = 'podlove', $color = [
							    "brand" => "#E64415",
							    "brandDark" => "#235973",
							    "brandDarkest" => "#000",
							    "brandLightest" => "#DDCFB4",
							    "shadeDark" => "#888",
							    "shadeBase" => "#444",
							    "contrast" => "#111",
							    "alt" => "#FFF"
							] , $size = "l", $subscribe = true) {
        if (empty($podcast["feed"])) return "";
		$enclosure = $this->getEnclosure();

		if ($type == 'podigee' && substr_count($this->original_feed_url, '.podigee.io')) {
			$base = substr($this->original_feed_url, 0, strpos($this->original_feed_url, '.podigee.io')+11);
			$et = strtolower($this->getEpisodeType());
			if ($et == 'full') $et = ""; else $et = substr($et,0,1);
			$script = '<script class="podigee-podcast-player ppplayer" src="https://cdn.podigee.com/podcast-player/javascripts/podigee-podcast-player.js" data-configuration="'.$base.'/'.$et.$this->getEpisodeNumber().'-latest/embed?context=external"></script>';
			return $script;
		} else {
			$chapterstring = $this->chapters2string();
            
            if (!(is_array($color))) $color = null;

			if ($size == "xs") {
				$player = '<div id="pom-podlove-player-'.$size.'"><root style="align-items: center;" class="p-4 flex justify-center">'.
				'<play-button></play-button>&nbsp;&nbsp;&nbsp;'.
				'</root></div>';
			} elseif ($size == "special") {
				$player = '<div id="pom-podlove-player-'.$size.'"><root style="align-items: center; width: 100%; height: auto;" class="">'.
				'<poster style="width: 100%; height: auto;"></poster>'.
				'<play-button variant="details" style="position: absolute; left: calc(50% - 25px - 30px); top: calc(50% - 25px); z-index: 2000;"></play-button>'.
				'</root></div>';
			} elseif ($size == "s") {
				$player = '<div id="pom-podlove-player-'.$size.'"><root style="min-width:260px; align-items: center;" class="p-4 flex justify-left">'.
						'<play-button></play-button>&nbsp;&nbsp;&nbsp;'.
						' <episode-title></episode-title>'.
						''.
						'</root></div>';
			} else {
				$player = '<div id="pom-podlove-player-'.$size.'" data-variant="'.$size.'"><chapter-previous class="mr-2 block"></chapter-previous>
				<chapter-next class="mr-2 block"></chapter-next>
				<current-chapter class="mx-2 block"></current-chapter></div>';
			}

            // podcast information is missing!
                return $player.'<script type="text/javascript">podlovePlayer("#pom-podlove-player-'.$size.'", {
                "version": 5,
                "activeTab": "chapters",
                "show": {
                    "title": '.json_encode($podcast["name"]).',
                    "subtitle": '.json_encode($podcast["subtitle"]).',
                    "summary": '.json_encode($podcast["summary"]).',
                    "poster": "'.($podcast["cover"] ? $podcast["cover"] : '').'",
                    "link": "'.$podcast["link"].'", '.
                    // "link": "https://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'"
                    '
                },
                "subscribe-button": '.($subscribe ? '{ feed: "'.$podcast["feed"].'",
                clients: [
                    {
                        id: "google-podcasts",
                        service: "'.$podcast["feed"].'" // feed
                    },
                    {
                        id: "pocket-casts",
                        service: "'.$podcast["feed"].'" // feed
                    },
                    {
                        id: "podcast-addict"
                    },
                    {
                        id: "podcat"
                    },
                    {
                        id: "rss",
                        service: "'.$podcast["feed"].'"
                    }
                    ]
                }':'false') .'
                ,
                "title": '.json_encode($this->getTitle()).',
                "subtitle": "",
                "summary": '.json_encode($this->getDescription()).',
                "publicationDate": "'.$this->getPubDate().'",
                "poster": "'.($this->getImage() ? $this->getImage() : $podcast["cover"]).'",
                "duration": "'.$this->getDuration().'",
                "link": "'.$this->getLink().'",
                "audio": [
                    {
                    "url": "'.(isset($enclosure['url']) ? ($enclosure['url']) : '""').'",
                    "size": '.(isset($enclosure['length']) ? ($enclosure['length']) : '0').',
                    "title": "'.(isset($enclosure['url']) ? substr(pathinfo($enclosure['url'])['extension'],0,strpos(pathinfo($enclosure['url'])['extension'], '?')) : '""').'",
                    "mimeType": "'.(isset($enclosure['type']) ? ($enclosure['type']) : '""').'"
                    }
                ]'.
                $chapterstring.',
                "runtime": {
                    "locale": "de-De",
                    "language": "de"
                },'.
                '"visibleComponents": [
                    "tabInfo",
                    "tabChapters",
                    "tabDownload",
                    "tabAudio",
                    "tabShare",
                    "poster",
                    "showTitle",
                    "episodeTitle",
                    "subtitle",
                    "progressbar",
                    "subscribe",
                    "controlSteppers",
                    "controlChapters"
                ]}, {
                    "version": 5,
                    "theme": {
                        "tokens": {
                        "brand": "'.(isset($color['brand']) ? $color['brand'] : '#E64415').'",
                        "brandDark": "'.(isset($color['brandDark']) ? $color['brandDark'] : '#235973').'",
                        "brandDarkest": "'.(isset($color['brandDarkest']) ? $color['brandDarkest'] : '#000').'",
                        "brandLightest": "'.(isset($color['brandLightest']) ? $color['brandLightest'] : '#DDCFB4').'",
                        "shadeDark": "'.(isset($color['shadeDark']) ? $color['shadeDark'] : '#888').'",
                        "shadeBase": "'.(isset($color['shadeBase']) ? $color['shadeBase'] : '#444').'",
                        "contrast": "'.(isset($color['contrast']) ? $color['contrast'] : '#111').'",
                        "alt": "'.(isset($color['alt']) ? $color['alt'] : '#FFF').'"
                        }
                    },
                })</script>';
		}
		return false;
	}
}

?>
