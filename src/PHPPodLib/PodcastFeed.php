<?php
/* 
    This class provides convenient access to a podcast's feed information. 
    It expects the feed itself to be already loaded elsewhere.
    It uses feed.io for feed parsing.

    Dependencies:
        - https://github.com/neitanod/forceutf8
        - https://phpunit.readthedocs.io/
*/

namespace PHPPodLib;

use Exception;
use \ForceUTF8\Encoding;

class PodcastFeed {
    private $feedUrl = "";
    private $feedXML;
    private $feedHash;
    private $isValidFeed;
    private $debug = false;
    private $episodes;
    private $meta = array (
            "stylesheet" => null,
            "generator" => null,
            "categories" => [],
            "link" => null,
            "title" => null,
            "pubdate" => null,
            "lastbuilddate" => null,
            "description" => null,
            "copyright" => null,
            "image" => null,
            "language" => null,
            "author" => null, 
            "type" => null, 
            "subtitle" => null, 
            "new-feed-url" => null, 
            "explicit" => null, 
            "complete" => null, 
            "block" => null,
            "locked" => null,
            "funding" => null,
            "ownername" => null,
            "owneremail" => null,
            "summary" => null
    );

    // Todo
    // $tagBlacklist
    // subtitle, description, or shownotes? || here and on episode level
    // player, subscribe button, cache, preview
    // pages feeds
    // filtering functions

    private $categoryNamesEN = "Arts
		Books
		Design
		Fashion & Beauty
		Food
		Performing Arts
		Visual Arts
		Business
		Careers
		Entrepreneurship
		Investing
		Management
		Marketing
		Non-Profit
		Comedy
		Comedy Interviews
		Improv
		Stand-Up
		Education
		Courses
		How To
		Language Learning
		Self-Improvement
		Fiction
		Comedy Fiction
		Drama
		Science Fiction
		Government
		Health & Fitness
		Alternative Health
		Fitness
		Medicine
		Mental Health
		Nutrition
		Sexuality
		History
		Kids & Family
		Education for Kids
		Parenting
		Pets & Animals
		Stories for Kids
		Leisure
		Animation & Manga
		Automotive
		Aviation
		Crafts
		Games
		Hobbies
		Home & Garden
		Video Games
		Music
		Music Commentary
		Music History
		Music Interviews
		News
		Business News
		Daily News
		Entertainment News
		News Commentary
		Politics
		Sports News
		Tech News
		Religion & Spirituality
		Buddhism
		Christianity
		Hinduism
		Islam
		Judaism
		Religion
		Spirituality
		Science
		Astronomy
		Chemistry
		Earth Sciences
		Life Sciences
		Mathematics
		Natural Sciences
		Nature
		Physics
		Social Sciences
		Society & Culture
		Documentary
		Personal Journals
		Philosophy
		Places & Travel
		Relationships
		Sports
		Baseball
		Basketball
		Cricket
		Fantasy Sports
		Football
		Golf
		Hockey
		Rugby
		Running
		Soccer
		Swimming
		Tennis
		Volleyball
		Wilderness
		Wrestling
		TV & Film
		After Shows
		Film History
		Film Interviews
		Film Reviews
		TV Reviews
		Technology
		True Crime";

	private $categoryNamesDE = "Kunst
		Bücher
		Design
		Mode und Schönheit
		Essen
		Darstellende Kunst
		Bildende Kunst
		Wirtschaft
		Karriere
		Firmengründung
		Geldanlage
		Management
		Marketing
		Gemeinnützig
		Comedy
		Comedy-Interviews
		Impro-Comedy
		Stand-Up-Comedy
		Bildung
		Kurse
		So geht’s
		Sprachen lernen
		Selbstverwirklichung
		Fiktion
		Comedy-Fiction
		Drama
		Science-Fiction
		Regierung
		Gesundheit und Fitness
		Alternative Therapien
		Fitness
		Medizin
		Mentale Gesundheit
		Ernährung
		Sexualität
		Geschichte
		Kinder und Familie
		Bildung für Kinder
		Kindererziehung
		Haus- und Wildtiere
		Geschichten für Kinder
		Freizeit
		Animation und Manga
		Rund ums Auto
		Luftfahrt
		Basteln
		Spiele
		Hobbys
		Heim und Garten
		Videospiele
		Musik
		Musikrezensionen
		Musikgeschichte
		Musikinterviews
		Nachrichten
		Wirtschaftsnachrichten
		Nachrichten des Tages
		Neues aus der Unterhaltung
		Kommentare
		Politik
		Sportnews
		Neues aus der Technik
		Religion und Spiritualität
		Buddhismus
		Christentum
		Hinduismus
		Islam
		Judentum
		Religion
		Spiritualität
		Wissenschaft
		Astronomie
		Chemie
		Geowissenschaften
		Biowissenschaften
		Mathematik
		Naturwissenschaften
		Natur
		Physik
		Sozialwissenschaften
		Gesellschaft und Kultur
		Dokumentation
		Tagebücher
		Philosophie
		Reisen und Orte
		Beziehungen
		Sport
		Baseball
		Basketball
		Cricket
		Fantasy Sport
		Football
		Golf
		Eishockey
		Rugby
		Laufen
		Fußball
		Schwimmen
		Tennis
		Volleyball
		Abenteuer Natur
		Wrestling
		TV und Film
		Backstage
		Filmgeschichte
		Filminterviews
		Filmrezensionen
		TV-Rezensionen
		Technologie
		Wahre Kriminalfälle";

        
    public function __construct(string $feed = null, bool $debug = false)
    {
        if ($feed != null) $this->setFeed($feed);
        if ($debug === true) $this->debug = true;
    }

    // Standard getter functions =============================
    public function getFeedURL() { return $this->feedUrl; }
    public function getFeedXML() { return $this->feedXML; }
    public function getHash() { return $this->feedHash; }
    public function getItems() { return $this->feedXML->channel->item; }
    public function getEpisodes() { return $this->episodes; }
    public function isValidFeed() { return $this->isValidFeed; }
    
    // Meta getter functions
    public function getStylesheet() { return $this->getMeta("stylesheet"); }
    public function getLink() { return $this->getMeta("link"); }
    public function getGenerator() { return $this->getMeta("generator"); }
    public function getDescription() { return $this->getMeta("description"); }
    public function getSummary() { return $this->getMeta("summary"); }
    public function getLanguage() { return $this->getMeta("language"); }
    public function getCopyright() { return $this->getMeta("copyright"); }
    public function getAuthor() { return $this->getMeta("author"); }
    public function getType() { return $this->getMeta("type"); }
    public function getSubtitle() { return $this->getMeta("subtitle"); }
    public function getNewFeedUrl() { return $this->getMeta("new-feed-url"); }
    public function getExplicit() { return $this->getMeta("explicit"); }
    public function getComplete() { return $this->getMeta("complete"); }
    public function getBlock() { return $this->getMeta("block"); }
    public function getOwnerName() { return $this->getMeta("ownername"); }
    public function getOwnerEmail() { return $this->getMeta("owneremail"); }
    public function getCover() { return $this->getMeta("cover"); }
    public function getTitle() { return $this->getMeta("title"); }
    public function getName() { return $this->getTitle(); }

    public function getPubdate(string $format = "r") { return $this->getDate("pubdate", $format); }
    public function getLastbuilddate(string $format = "r") { return $this->getDate("lastbuilddate", $format); }

    public function getDate(string $which = "pubdate", string $format = "r") {
        // When a pubdate is requested, we try to return the podcasts pubdate and if that fails, we try to return the first episode's pubdate
        if (isset($this->meta[$which])):
            try {
                return date($format, $this->meta[$which]);
            } catch (\Exception $e) {
                if ($this->debug) echo "WARNING: Could not convert pubdate: ".$e->getMessage()."\n";
            }
        endif;
        if ($which == "lastbuilddate") return null;
        if (is_array($this->episodes) && count($this->episodes) > 0):
            return $this->episodes[0]->getPubdate($format);
        endif;
        return null;
    }

    public function getPossibleCategoryNames(string $language) {
        if (strtolower($language) == "en") return array_map("trim", explode("\n", $this->categoryNamesEN));
        if (strtolower($language) == "de") return array_map("trim", explode("\n", $this->categoryNamesDE));
        return [];
    }

    public function getCategories(bool $mainOnly = false, bool $translated = false) {
        $categories = $this->getMeta("categories");
        if ($mainOnly === false) return $categories;
        if (is_array($categories) && count($categories) > 0) return $translated ? $this->translateCategory($categories[0], true): $categories[0];
    }

    public function getMeta(string $key = null) {
        if ($key == null) return $this->meta;
        if (in_array($key, array_keys($this->meta))) return $this->meta[$key];
        return null;
    }

    // Even more advanced getter functions ========================
    public function getLatestEpisode() {
        if (is_array($this->episodes) && count($this->episodes) > 0):
            /* return $this->episodes[count($this->episodes)-1]; // <- this would be the oldest episode */
	    return $this->episodes[0];
        else:
            return null;
        endif;
    }
    
    public function getEpisode($numberOrGuid = null) { 
        if ($numberOrGuid === null) return null;
        if (is_string($numberOrGuid)): // it is an guid
            $result = ($this->getFilteredEpisodes("string", "guid", $numberOrGuid));
        elseif (is_int($numberOrGuid)): // it is an integer
            $result = ($this->getFilteredEpisodes("integer", "episode", $numberOrGuid)); // in the feed, the index for "episode number" is "episode"
        else:
            return null;
        endif;
        if (count($result) == 0) return null;
        return $result[0];
    }

    public function getDuration(bool $inSeconds = false, $itemList = false ) {
		// Calculate an entire podcasts duration by adding up each episodes play duration
        $items = [];
        if ($itemList !== false && is_array($itemList)):
			$items = $itemList;
		else:
			$items = $this->getEpisodes();
		endif;

        if ($items && count($items) == 0): 
            if ($this->debug) echo "WARNING: podcast XML has no items (=episodes)\n";
            return 0;
        endif;

		$duration = 0;
		foreach ($items as $episode):
			$duration += $episode->getDuration(true); // true = in seconds			
		endforeach; 
		if ($inSeconds) return $duration;
		return $this->convertSecondsToTimestring($duration, true);
	}

    public function getTags($itemList = false, bool $uniquesOnly = true) {
        // Loop through all episodes or an itemList of episodes and collect all tags // all ucwords, sorted, no duplicates
        if ($itemList !== false) $items = $itemList; else $items = $this->getEpisodes();
		if (!$items || count($items) == 0) return array();
        
        $return = [];
		foreach ($items as $ep):
			$tags = $ep->getTags();
			if (count($tags) > 0):
				foreach ($tags as $tag):
                    if (!$uniquesOnly):
                        $return[] = ucwords(strtolower(trim($tag)));
                        continue;
                    endif; 
                    if (!in_array(strtolower(trim($tag)), array_map("strtolower", $return))) $return[] = ucwords(strtolower(trim($tag)));
				endforeach;
			endif;
		endforeach;
		array_multisort($return);
		return $return;
    }

    public function getMostCommonTags($episodes = false, $nof = -1) {
        // Gather tags from selected or all (false) episodes and sort them by occurrence; return all or $nof
        if ($nof == 0) return [];
		$tags = $this->getTags($episodes, false); // if false, all episode tags are being taken into account
        $tagArrayByOccurrence = array_count_values($tags);
        array_multisort($tagArrayByOccurrence, SORT_NUMERIC, SORT_DESC);
        if ($nof > -1) $tagArrayByOccurrence = array_slice($tagArrayByOccurrence, 0, $nof);

        return $tagArrayByOccurrence;
	}

public function getFilteredEpisodes(string $matchtype = null, string $field = null, string $pattern = null) {
        // ToDo: UNTESTED
        // Loop through episodes and collect the ones that match our search criteria
        if (!is_array($this->episodes) || count($this->episodes) == 0):
            if ($this->debug) echo "WARNING: no episodes in feed\n";
            return [];
        endif;
        $return = [];
        foreach ($this->episodes as $idx => $episode):
            if ($field == "episode" && $episode->getEpisodeNumber() == 0) { 
                if (count($this->episodes)-($idx+1) == $pattern) $return[] = $episode;
            } else {
                if ($episode->isMatch($matchtype, $field, $pattern)) $return[] = $episode;
            }
        endforeach;
        return $return;
    }

    
    // Setter functions =========================================
    public function setFeed(string $feed) {
        // The feed can and should be set a) while class construction or via this function – only after setting the feed, that class has a unique identifier
        $this->feedUrl = $feed; 
        $this->isValidFeed = $this->isValidUrl($feed);
        return $this->isValidFeed;
    }

    public function isValidUrl(string $url = null) {
        // Just checking to see if it possibly is an URL – we don't care if it is reachable or not
        if ($url == null) {
            if ($this->debug) echo "INFO: no url specified, using (hopefully) pre-set feed url instead\n".$this->feedUrl."\n";
            $url = $this->feedUrl;
        }
        if (trim($url) == "") {
			if ($this->debug) echo "ERROR: no url specified\n";
			return false;
		}
        if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
			if ($this->debug) echo "ERROR: not a valid URL | $url\n";
			return false;
		}
        return true;
    }

    public function isValidUTF8XML(string $string = null) { 
        // Check the feed string for XML tags and encoding
        if ($string == null) return false;
        if (trim($string) == "") return false;
        if (mb_detect_encoding($string) != "UTF-8") return false;
        if (strpos($string, "<?xml") === false) return false;
        return true;
    }

    public function loadFeedXml(string $rawContent = null) {
        // Loading XML from a string (not a file!) into a simple_xml object.
        if (!isset($rawContent) || $rawContent == null || trim($rawContent) == "") {
            if ($this->debug) echo "ERROR: feed is empty | $rawContent\n";
            return false;
        }
       # try {
            $utf8feedContent = Encoding::toUTF8($rawContent);
        // } catch (Exception $e) {
        //     if ($this->debug) echo "ERROR: ".$e->getMessage();
        //     return false;
        // }
        if ($this->isValidUTF8XML($utf8feedContent)):  
            try {
                $this->feedXML = simplexml_load_string($utf8feedContent); 
                $this->episodes = array();
                if ($this->feedXML->channel->item->count() > 0):
                   # echo "count: ".$this->feedXML->channel->item->count()."\n";
                    foreach ($this->feedXML->channel->item as $item):
                       # echo $item->guid."\n";continue;
                        $episode = new PodcastEpisode($item, $this->debug);
                        $this->episodes[] = $episode; 
                    endforeach;
                endif;

                // We need to get a possible xml stylesheet here, so we don't have to save the entire raw XML
                $pattern = "/<\?xml-stylesheet\s[^>]*href=[\"\']{1}([^\"^\']*)[\"\']{1}/miu";
                if (preg_match($pattern, $utf8feedContent, $match)):
                    $this->meta["stylesheet"] = $match[1];
                endif;
            } catch (Exception $e) {
                if ($this->debug) echo "ERROR: error while loading feed xml: ".$e->getMessage()."\n";
                return false;
            }
           
            // Saving feed string hash – for determining changes later on
            $this->feedHash = md5($rawContent);
            $this->namespaces = $this->feedXML->getNamespaces(true);
            $this->extractMetaFromFeed();
            return true;
        endif;
        return false;
    }

    // Alle meta gathering functions come together here
    private function extractMetaFromFeed(string $key = null) {
        /*
            Stylesheet is the only piece of information that gets collected during loadFeedXML() – because it is not part of the simpleXML dom and we don't want to save the entire raw feed.
            $this->stylesheet = $this->extractStylesheet();
        */
        foreach (array("generator", "link", "title", "description", "copyright", "language") as $lowHangingFruit):
            $this->meta[$lowHangingFruit] = (string) $this->feedXML->channel->$lowHangingFruit;
        endforeach;

        $this->extractUnnestedtemsFromNamespace("itunes", array("author", "type", "subtitle", "summary", "new-feed-url", "explicit", "complete", "block"));
        $this->extractUnnestedtemsFromNamespace("podcast", array("locked", "funding"));
        
        $this->extractCategories();
        $this->extractDates();
        $this->extractOwner();
        $this->extractCover();

        if ($key != null && isset($this->meta["key"])) return $this->meta[$key]; return $this->meta;
    }

    private function extractUnnestedtemsFromNamespace(string $namespace, array $arrayOfKeys = []) {
        // Extract unnested items from namespaces like podcast: or itunes: 
        if (in_array($namespace, array_keys($this->namespaces))):
            foreach ($arrayOfKeys as $lowHangingFruit):
                $fruit = $this->feedXML->xpath("//channel/".$namespace.":".$lowHangingFruit);
                if (is_array($fruit) && count($fruit) > 0):
                    $this->meta[$lowHangingFruit] = (string)($fruit[0]);
                else:
                    $this->meta[$lowHangingFruit] = null;
                endif;
            endforeach;
        endif;
    }

    private function extractOwner() {
        // Extract owner name and email from itunes: namespace tags
        if (in_array("itunes", array_keys($this->namespaces))):
            foreach (array("name", "email") as $lowHangingFruit):
                $fruit = $this->feedXML->xpath("//channel/itunes:owner/itunes:".$lowHangingFruit);
                if (is_array($fruit) && count($fruit) > 0):
                    $this->meta["owner".$lowHangingFruit] = (string)($fruit[0]);
                else:
                    $this->meta["owner".$lowHangingFruit] = null;
                endif;
            endforeach;
        endif;
    }

    private function extractCategories() {
        // Extract categories from the itunes namespace – might to work for 100 % of all podcasts but seams reasonable
        if (in_array("itunes", array_keys($this->namespaces))):
            $categories = $this->feedXML->xpath("//channel/itunes:category");
            if (is_array($categories) && count($categories) > 0):
                foreach ($categories as $category):
                    $this->meta["categories"][] = (string)$category->attributes()["text"];
                endforeach;
            endif;
        endif;
    }

    private function extractDates() {
        // Check feed for pudate and lastbuilddate – and convert it to php dates
        $pubdate = $this->feedXML->channel->pubDate;
        if ($pubdate) $this->meta["pubdate"] = strtotime($pubdate);
        $lastbuild = $this->feedXML->channel->lastBuildDate;
        if ($lastbuild) $this->meta["lastbuilddate"] = strtotime($lastbuild);
    }
    
    private function extractCover() {
        $image = null;
        try {
            $image = (string)$this->feedXML->channel->image->url;
        } catch (Exception $e) {
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

    // HELPER FUNCTIONS
    public function convertSecondsToTimestring($duration, $extralong = false) {
        // Convert an episodes play length in seconds to a human-readable timestring
        if (! is_int($duration)) return false;
		$hours = floor($duration / 3600);
		$duration -= $hours * 3600;
		$minutes = floor($duration / 60);
		$duration -= $minutes * 60;
		$days = floor($hours / 24);
		if ($days > 0) $hours -= $days * 24;
		
        // If extralong display with long text instead of a short numeric string
        if ($extralong): 
            $returnstring = "";
            if ($days > 0) $returnstring .= $days." Tag".($days > 1 ? "e" : "").", "; 
            $returnstring .= $hours. " Stunde".($hours > 1 || $hours == 0 ? "n" : "").", ";
            $returnstring .= $minutes. " Minute".($minutes > 1 || $minutes == 0 ? "n" : "").", ";
            $returnstring .= $duration. " Sekunde".($duration > 1 || $duration == 0 ? "n" : "");
            return $returnstring;
        endif;

        // else return short string instead
		return ($days > 0 ? $days.":" : "") . $hours.":".sprintf("%02d", $minutes).":".sprintf("%02d", $duration);
	}

    private function translateCategory($category, $en2de = true) {
        // Translate English iTunes category names to German (or the other way round)
        if ($en2de):
			$search = array_map('trim', array_map('strtolower', explode("\n", $this->categoryNamesEN)));
			$match = array_map('trim', explode("\n", $this->categoryNamesDE));
		else:
			$search = array_map('trim', array_map('strtolower', explode("\n", $this->categoryNamesDE)));
			$match = array_map('trim', explode("\n", $this->categoryNamesEN));
		endif;
		for ($i = 0; $i < count($search); $i++) if ($search[$i] == strtolower(trim($category))) return $match[$i];
		return $category;
	}


    public function estimatePublishingFrequency($roundTo = -1) {
		// TODO: check is this works in all cases | UNTESTED
        // We try to find out, what the publishing frequency is based on the median difference between episodes' publishing dates
		if (count($this->items) == 0) return false;
		$lastdate = false;
		$avgdate = false;
		$counter = 0;
		foreach ($this->items as $episode):
			$epdate = \DateTime::createFromFormat("Y-m-d", $episode->getPubDate("Y-m-d"));
			if (!$lastdate):
				$lastdate = $epdate;
				$counter++; 
				continue;
			endif;
			$diff = date_diff($lastdate, $epdate)->format("%a");
			if ($diff == 0) continue;
			$avgdate = (($avgdate * ($counter-1)) + $diff) / $counter;
			$counter++;
			$lastdate = $epdate;
		endforeach;
		if ($roundTo > -1) return round($avgdate, $roundTo);
		return $avgdate;
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
            $this->getDescription()
        );
        // Only keep uniques
        $contentPieces = array_unique($contentPieces);
        // Sort by string length
        usort($contentPieces, function($a, $b){
            return strlen($a) > strlen($b);
         });
         // Strip html
        if ($stripHtml === true) $contentPieces = array_map("trim", array_map("strip_tags", $contentPieces));
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

}



?>
