<?php 
namespace PHPPodLib;
use Medoo\Medoo;
use \DateTime;
use \DateTimeZone;
use Exception;
use \Spatie\YamlFrontMatter\YamlFrontMatter;

class PodcastDBWrapper {
    private $database, $debug;
    private $tableDefinitions = array(
        "podcasts" => array(
            // "id" => ["INTEGER", "PRIMARY_KEY"],   = ROWID
            "feed" => ["TEXT", "NOT NULL"],
            "title" => ["TEXT", "NOT NULL"],
            "authors" => ["TEXT", "NOT NULL"],
            "contact" => ["TEXT", "NOT NULL"],
            "categories" => ["VARCHAR(250)", "NOT NULL"],
            "website" => ["TEXT", "NOT NULL"],
            "cover" => ["TEXT", "NOT NULL"],
            "last_update" => ["DATETIME", "NOT NULL"],
            "hash" => ["VARCHAR(32)", "NOT NULL"],
            "description" => ["TEXT", "NOT NULL"],
            "shortname" => ["VARCHAR(3)"], 
            "color" => ["VARCHAR(20)", "NULL"],
            "colorcontrast" => ["VARCHAR(20)", "NULL"],
            "fullepisodesonly" => ["INT(1)"],
            "stripfromdescription" => ["TEXT", "NULL"],
            "stripfromshownotes" => ["TEXT", "NULL"],
            "stripepisodenumbers" => ["TEXT", "NULL"],
            "slug" => ["TEXT"],
            "summary" => ["TEXT"]
            // "PRIMARY KEY (<id>)"
        ),
        "episodes" => array(
            // "id" => ["INT", "PRIMARY_KEY"],   = ROWID
            "podcastid" => ["INT", "NOT NULL"],
            "name" => ["TEXT", "NOT NULL"],
            "season" => ["INT", "NOT NULL"],
            "number" => ["INT", "NOT NULL"],
            "link" => ["TEXT", "NOT NULL"],
            "mediafile" => ["TEXT", "NOT NULL"],
            "cover" => ["TEXT", "NOT NULL"],
            "type" => ["VARCHAR(10)", "NOT NULL"],
            "pubdate" => ["DATETIME", "NOT NULL"],
            "guid" => ["VARCHAR(250)", "NOT NULL"],
            "hash" => ["VARCHAR(32)", "NOT NULL"],
            "chapters" => ["TEXT", "NOT NULL"],
            "subtitle" => ["TEXT", "NOT NULL"],
            "description" => ["TEXT", "NOT NULL"],
            "last_updated" => ["DATETIME", "NOT NULL"],
            "shownotes" => ["TEXT", "NOT NULL"],
            "summary" => ["TEXT", "NOT NULL"],
            "duration" => ["INT", "NOT NULL"]
            // "PRIMARY KEY (<id>)"
        ),
        "tags" => array(
            // "id" => ["INT", "PRIMARY_KEY"],    = ROWID
            "tag" => ["VARCHAR(250)", "NOT NULL"],
            // "PRIMARY KEY (<id>)"
        ),
        "tags2episodes" => array(
            // "id" => ["INT", "PRIMARY_KEY"],
            "episodeguid" => ["VARCHAR(250)", "NOT NULL"],
            "tagid" => ["INT", "NOT NULL"],
            "podcastid" => ["INT", "NOT NULL"]
            // "PRIMARY KEY (<id>)"
        )
    );

    private $EPISODE_COLUMN_DEFINITION; 
    private $lastQueryNof;

    private function buildEPISODE_COLUMN_DEFINITION() {
        $fieldsArray = [ "podcasts.rowid as podcastid", "episodes.rowid as episodeid", "tags.rowid as tagsid", "group_concat(tags.tag, \", \")  as episodestaglist", "group_concat(tags.rowid, \", \")  as episodestagids" ];
        foreach ($this->tableDefinitions as $table => $tableDefinition):
            foreach ($tableDefinition as $column => $columDefinition):
                $fieldsArray[] = $table.".".$column." as ".$table.$column;
            endforeach;
        endforeach; 

        return ($fieldsArray);
    }

    public function __construct(bool $debug = false) {
        $this->debug = $debug;
        $this->EPISODE_COLUMN_DEFINITION = $this->buildEPISODE_COLUMN_DEFINITION();
    }

    public function query(string $query) {
        $result = $this->database->query($query);
        if ($result) return $result->fetchAll(); else return [];
    }

   public function select($table, $fields = '*', $where_order = null) {
        $result = $this->database->select($table, $fields != null ? $fields : "*", $where_order);
        if ($result) return $result; else return [];
    }

    public function getPodcast(string $feed) {
        $result = $this->database->select('podcasts', ['rowid'], ["feed" => $feed]);
        
        $podcastId = $result[0]['rowid'];
        if (isset($podcastId) && is_numeric($podcastId)) return $podcastId; else return false;
    }

    public function getPodcasts() {
        $result = $this->database->select('podcasts', "*");
        return $result;
    }

    // Tag stuff
    public function getAllTags(int $atLeastUsedNTimes = 0) { 
        if ($atLeastUsedNTimes === 0) $result = $this->database->query("select * from tags order by lower(tag) ASC");
        if ($atLeastUsedNTimes > 0) $result = $this->database->query("select count(tagid) as usage,* from tags2episodes left join tags on (tags.rowid=tagid) group by (tagid) having usage > ".$atLeastUsedNTimes." order by lower(tag) ASC");
        return array_column($result->fetchAll(), "tag");
    }
    public function getMostCommonTags(int $limit = 25) { 
        $result = $this->database->query("select count(tagid) as usage,* from tags2episodes left join tags on (tags.rowid=tagid) group by (tagid) order by usage DESC ". ($limit > 0 ? "LIMIT 0, ".$limit : ""));
        return array_column($result->fetchAll(), "tag");
    }
    public function orderByUsageOfTags($tags) {
        #dump($tags);
        $select = "select count(tagid) as usage,* from tags2episodes left join tags on (tags.rowid=tagid) ";
        $order = " group by (tagid) ORDER by usage DESC";
        $or = " WHERE upper(tag) = upper(\"".implode ("\") OR upper(tag) = upper(\"", $tags)."\")";
        #die($select.$or.$order);
        $result = $this->database->query($select.$or.$order);
        return array_column($result->fetchAll(), "tag");
     }
    



    // Getting episodes and related information ==========================================

    public function getTags($guid) {
        $result = $this->database->query("SELECT * from tags2episodes  left join tags on (tagid=tags.rowid) where episodeguid=\"".$guid."\"");
        $tags = [];
        if ($result):
            $result = $result->fetchAll();
            foreach ($result as $row):
                if (!in_array($row["tag"], $tags)) $tags[] = $row["tag"];
            endforeach;
        endif;
        return $tags;
    }

    public function getEpisodes($filter = [], $order = "episodespubdate ASC", bool $andOrOr = true) {
        $where = array();
        
        foreach ($filter as $f):
            $key = $f[0]; 
            $value = $f[1];
            $where[] = $key.' = "'.addslashes($value).'"';
        endforeach;
       

        $where = "(". implode(($andOrOr === true ? " AND " : " OR "), $where).")";

        $query = "SELECT ".implode(", ", $this->EPISODE_COLUMN_DEFINITION)." FROM episodes LEFT JOIN tags2episodes on (episodeguid=episodes.guid) LEFT JOIN podcasts on (episodes.podcastid = podcasts.rowid) LEFT JOIN TAGS on (tagid=tags.rowid) WHERE ".$where. " GROUP BY (episodes.guid)".($order ? " ORDER BY ".$order : "");

        $results = $this->query($query);
        $this->lastQueryNof = count($results);

        return $results;
    }

    public function getLastQueryNof() { return $this->lastQueryNof; }

    public function getEpisodesMatchingTags(array $tags) {
        $where = array();
        foreach ($tags as $tag):
            $where[] = 'upper(tag) = upper("'.addslashes($tag).'")';
        endforeach;
        $where = implode(" OR ", $where);
        $query = "SELECT episodes.guid as guid, episodes.link as eplink, number as epnumber, season as epseason, episodes.cover as epcover, podcasts.cover as podcover, episodes.pubdate as epdate, duration as tags  FROM tags2episodes LEFT JOIN episodes on (episodeguid=episodes.guid) LEFT JOIN podcasts on (tags2episodes.podcastid = podcasts.rowid) LEFT JOIN TAGS on (tagid=tags.rowid) WHERE ".$where. " GROUP BY (episodes.guid)";
        $results = $this->database->query($query);
        if ($results != null) return $results->fetchAll(); else return [];
        #return $results;
    }

    public function getFilteredEpisodes($where, $returnColumns = null, $order = null, $limit = "") {
        if ($returnColumns === null) $returnColumns = $this->EPISODE_COLUMN_DEFINITION;
        if ($order == null) $order = "ORDER BY episodes.pubdate DESC";
        $query = "SELECT ";
        $query .= implode(", ", $returnColumns);
        $query .= " FROM episodes 
        LEFT JOIN tags2episodes ON episodeguid = episodes.guid
        LEFT JOIN podcasts on episodes.podcastid = podcasts.rowid
        LEFT JOIN tags ON tagid = tags.rowid
        WHERE ";

        $and = array();
        if (isset($where["AND"])):
            foreach ($where["AND"] as $aaKey => $andArray):
                if (empty($andArray)) continue;
                if (isset($andArray["OR"])):
                    $or = array();
                    foreach ($andArray["OR"] as $orArray):
                        $or[] = " upper(".array_keys($orArray)[0].") = upper(\"".array_values($orArray)[0]."\") "; 
                    endforeach;
                    $and[] = implode( " OR ", $or);
                else:
                    $and[] = "upper($aaKey) = upper(\"$andArray\")";
                endif;
            endforeach;
        endif;
        $query .= ("(".implode(") AND (", $and).")");
        $query .= " GROUP BY episodes.guid ".$order;
        
        $totalResult = $this->query($query); 
        $this->lastQueryNof = count($totalResult);
        $result = $this->query($query.' '.$limit);
        // if (count($result) > 0) $result = $this->enrichEpisodesAfterDB($result);
        return $result;
    }

    // ================================================================================

    public function cleanUpPodcastsEpisodesAndTagsNotInList(array $podcasts) {
        if (count($podcasts) == 0) return false;
        $pids = [];
        foreach($podcasts as $podcast):
            $pids[] = "podcastid <> \"".$this->getField("podcasts", "rowid", ["feed" => $podcast["feed"]])."\"";
            $feedwhere[] = "feed <> \"".$podcast["feed"]."\"";
        endforeach;
        $where = implode(" AND ", $pids);
        $feedwhere = implode(" AND ", $feedwhere);
        if (count($pids) > 0):
            $del = $this->database->query("DELETE FROM episodes WHERE ".$where);
            if ($this->debug) echo ($del ? $del->rowCount()." deleted from episodes\n" : "nothing deleted from episodes\n");
            $del = $this->database->query("DELETE FROM tags2episodes WHERE ".$where);
            if ($this->debug) echo ($del ? $del->rowCount()." deleted from tags2episodes\n" : "nothing deleted from tags2episodes\n");
            $del = $this->database->query("DELETE FROM podcasts WHERE ".$feedwhere);
            if ($this->debug) echo ($del ? $del->rowCount()." deleted from podcasts\n" : "nothing deleted from podcasts\n");
        endif;
        // Clean up stray episodes as well
        $del = $this->database->query("DELETE FROM episodes WHERE podcastid NOT IN (SELECT rowid FROM podcasts)");
        if ($this->debug) echo ($del ? $del->rowCount()." deleted from episodes (orphans)\n" : "nothing deleted from episodes\n");
        // Clean up tags as well
        $del = $this->database->query("DELETE FROM tags WHERE rowid NOT IN (SELECT tagid FROM tags2episodes)");
        if ($this->debug) echo ($del ? $del->rowCount()." deleted from tags\n" : "nothing deleted from tags\n");
    }

    public function insertOrUpdateEpisodes(PodcastFeed $podcast, string $remapTagMdFile = null) {
        $berlin = new DateTimeZone("Europe/Berlin");
        $now = new DateTime("now", $berlin);

        $episodes = $podcast->getEpisodes();
        if (count($episodes) == 0): 
            if ($this->debug) echo " err: no episodes in feed\n";
            return false;
        endif;

        $podcastId = @$this->database->select('podcasts', ['rowid'], ["feed" => $podcast->getFeedURL()])[0]['rowid'];
        if (!$podcastId || !is_numeric($podcastId)):
            if ($this->debug) echo " err: no podcastID found";
            return false;
        else:
            if ($this->debug) echo " info: PodcastID: ".$podcastId."\n";
        endif;

        foreach ($episodes as $i => $episode):
            $episodeNumber = $episode->getEpisodeNumber() ?  $episode->getEpisodeNumber() : -1;

            if ($episodeNumber == -1): 
                $episodeNumber = (count($episodes)-1) - $i;
            endif;

            $hash = $this->getField("episodes", "hash", ["AND" => ["podcastid" => $podcastId, "guid" => $episode->getGuid()],]);
            if ($hash == $episode->getHash()) continue; // if hashes match, everything is up to date
            $this->database->delete("episodes", ["AND" => ["podcastid" => $podcastId, "guid" => $episode->getGuid()],]);
            $this->database->delete("tags2episodes", ["AND" => ["podcastid" => $podcastId, "episodeguid" => $episode->getGuid()]]);
           
            $berlin = new DateTimeZone("Europe/Berlin");
            $now = new DateTime("now", $berlin);

            $values = [
                "podcastid" => $podcastId,
                "name" => $episode->getTitle() ?  $episode->getTitle() : "",
                "number" => $episodeNumber,
                "season" => $episode->getSeason() ?  $episode->getSeason() : -1,
                "link" => $episode->getLink() ?  $episode->getLink() : "",
                "mediafile" => isset($episode->getEnclosure()['url']) ?  $episode->getEnclosure()['url'] : "",
                "cover" => $episode->getImage() ?  $episode->getImage() : "",
                "type" => $episode->getEpisodeType() ?  $episode->getEpisodeType() : "",
                "pubdate" => $episode->getPubdate('Y-m-d H:i:s') ?  $episode->getPubdate('Y-m-d H:i:s') : "",
                "guid" => $episode->getGuid() ?  $episode->getGuid() : "",
                "hash" => $episode->getHash() ?  $episode->getHash() : "",
                "chapters" => $episode->getChapters() ?  $episode->getChapters() : "",
                "subtitle" => $episode->getSubtitle() ?  $episode->getSubtitle() : "",
                "description" => $episode->getDescription() ?  $episode->getDescription() : "",
                "summary" => $episode->getSummary() ?  $episode->getSummary() : "",
                "last_updated" => $now->format('Y-m-d H:i:s'),
                "shownotes" => $episode->getContent() ?  $episode->getContent() : "",
                "duration" => $episode->getDuration(true) ? ($episode->getDuration(true)) : 0
            ];
           
            try {
                if (!$this->database->insert("episodes", $values)):
                    if ($this->database->error):
                        die($this->database->error);
                    endif;
                endif; 
            } catch (Exception $e) {
                var_dump($values);die($e->getMessage());
            }
            
            // Now on to the tags!
            $episodeId = $this->database->id();
            $link = $episode->getLink();
            $tags = $episode->getTags();

            // NO TAGS? 
            // Case 1: for anchor feeds, look for hastags in shownotes
            if (empty($tags) && strpos($link, "anchor.fm") > 0): // still empty? look in the shownotes!
                $include_pattern = "!#([a-zA-Z0-9-_üÜöÖäÄß]+)[^\w]+!i";
                $exclude_pattern = "!#([0-9A-F]{3}){1,2}!i";
                $stripped_content = empty($episode->getContent()) ? "" : strip_tags($episode->getContent());
                if (preg_match_all($include_pattern, $stripped_content, $out)):
                    $tags = array_map("trim", $out[1]);
                    foreach($tags as $idx => $tag):
                        if (preg_match($exclude_pattern, $tag)) unset($tags[$idx]);
                        $tags[$idx] = ucwords(str_replace("_", " ", $tag));
                    endforeach;
                elseif (preg_match_all($include_pattern, strip_tags($episode->getDescription()), $out)):
                    $tags = array_map("trim", $out[1]);
                    foreach($tags as $idx => $tag):
                        if (preg_match($exclude_pattern, $tag)) unset($tags[$idx]);
                        $tags[$idx] = ucwords(str_replace("_", " ", $tag));
                    endforeach;
                endif;
            endif;

            // NO TAGS? 
            // Case 2: look for <meta keywords> on mentioned website
            if (empty($tags)): // no tags in feed? look in the website mentioned
                if (!empty($link)):
                    try {
                        $contents = @file_get_contents($link);
                        if (empty($contents)) throw new Exception('Site not reachable/found.');
                        $pattern = "!<meta[^>]*name=[\"']+keywords[\"']+[^>]*content=[\"']+([^\"^']*)[\"']+!isU";
                        if (preg_match_all($pattern, $contents, $out)):
                            $tags = array_map("trim", explode(",", $out[1][0]));
                        endif;
                   } catch (\Exception $e) {
                        $tags = [];
                    }
                endif; // check website for tags
            endif;
            
            // NO TAGS? 
            // Case 3: look for <a rel="tag"> on mentioned website
            if (empty($tags)): // no tags in feed or website – look for rel="tag" on website
                if (!empty($link)):
                    try {
                        if (empty($contents)) $contents = @file_get_contents($link);
                        if (empty($contents)) throw new Exception('Site not reachable/found.');
                        $pattern = "!<a[^>]*rel=[\"']+tag[\"']+[^>]*>([^<]*)</a>+!isU";
                        if (preg_match_all($pattern, $contents, $out)):
                            $tags = array_map("trim", $out[1]);
                        endif;
                   } catch (\Exception $e) {
                        $tags = [];
                    }
                endif; // check website for tags
            endif;

            // No Tags? Look for Podbean Tag widget
            if (empty($tags) && strpos($link, "podbean.com") > 0):
                if (!empty($link)):
                    try {
                        $contents = @file_get_contents($link);
                        if (empty($contents)) throw new Exception('Site not reachable/found.');
                        $pattern = "!<a[^>]*href=[\"']+/category/[^\"^']*[\"']+[^>]*>([^<]*)</a>+!isU";
                        if (preg_match_all($pattern, $contents, $out)):
                            $tags = array_map("trim", $out[1]);
                        endif;
                   } catch (\Exception $e) {
                        $tags = [];
                    }
                endif; // check website for tags
            endif;

            // Website tags sometimes don't fit, so we need to remap some of them
            if (!empty($tags)): // there are tags in the feed
                $i = 0;
                foreach ($tags as $tag):
                    $tag = trim($tag);
                    if (empty($tag)) continue;
                    if (file_exists($remapTagMdFile)): 
                        $tag = $this->remapTags($tag, $remapTagMdFile);
                    endif; 
                    if (empty(trim($tag))) continue;
                    #$result = $this->database->select("tags", "rowid", ["lower(tag)" => strtolower($tag)]);
                    $query = "SELECT rowid FROM tags where lower(tag) = \"".mb_strtolower($tag)."\"";
                    $result = $this->query($query); 
                    if (!empty($result) && count($result) > 0):
                        $tagId = $result["0"]["rowid"];
                    else:
                        $this->database->insert("tags", [
                            "tag" => ucwords($tag)
                        ]);
                        $tagId = $this->database->id();
                    endif;
                    $result = $this->database->select("tags2episodes", "rowid", ["AND" => [ "tagid" => $tagId, "episodeguid" => $episode->getGuid() ]]);
                    if (count($result) > 0) continue; //die("in there already"); // If relation already is in DB
                    if (!($this->database->insert("tags2episodes", [
                        "episodeguid" => $episode->getGuid(),
                        "tagid" => $tagId,
                        "podcastid" => $podcastId
                    ]))):
                        if ($this->debug) echo ' error inserting tag: '.$tag;
                        return false;
                    endif;
                endforeach; // tags
            endif; 
        endforeach; // episodes
        return true;
    }

    // Some tags need to be mapped onto others (due to limitations in keyword/hashtag notation, user error and some other effects)
    private function remapTags($tag, $tagfile) {
        $frontmatterFile = YamlFrontMatter::parse(file_get_contents($tagfile));
        $frontmatter = $frontmatterFile->matter();
        if (!empty($frontmatter["tagmap"])):
            foreach ($frontmatter["tagmap"] as $tm):
                if (isset($tm["casesensitive"]) && $tm["casesensitive"] == true):
                    $match = $tag == $tm["tag"];
                else:
                    $match = mb_strtolower($tag) == mb_strtolower($tm["tag"]);
                endif;
                if ($match === true) return $tm["replace"];
            endforeach;
        endif;
        return $tag;
    }

    public function getEpisodeCovers(string $podcastfeed) {
        $query = "SELECT episodes.cover as epcover FROM episodes LEFT JOIN podcasts on (podcasts.rowid = podcastid) WHERE feed=\"".$podcastfeed."\" AND episodes.cover != \"\"";
        $result = $this->database->query($query);
        if ($result):
            return $result->fetchAll();
        else:
            return [];
        endif;
    }
    
    public function testConnection() {
        try {
            $test = $this->database->query('SELECT name FROM sqlite_master WHERE type ="table" AND name NOT LIKE "sqlite_%"')->fetchAll();
            if ($test && count($test) > 0) return true;
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    public function insertOrUpdatePodcast(PodcastFeed $podcast, array $additionalFields = []) {
        $berlin = new DateTimeZone("Europe/Berlin");
        $now = new DateTime("now", $berlin);

        $color = "0,0,0";
        $contrast = "199,199,199";
        if (!empty($additionalFields["color"])): 
            $color = $additionalFields["color"];
            if (is_array($additionalFields["color"])): 
                $color = $additionalFields["color"][0].",".$additionalFields["color"][1].",".$additionalFields["color"][2]; 
            endif;
        endif;
        if (!empty($additionalFields["colorcontrast"])): 
            $contrast = $additionalFields["colorcontrast"];
            if (is_array($additionalFields["colorcontrast"])): 
                $contrast = $additionalFields["colorcontrast"][0].",".$additionalFields["colorcontrast"][1].",".$additionalFields["colorcontrast"][2]; 
            endif;
        endif;

        $values = [
            "title" => !empty($additionalFields["name"]) ? $additionalFields["name"] : $podcast->getTitle(),
            "authors" => !empty($additionalFields["author"]) ? $additionalFields["author"] : $podcast->getAuthor(),
            "contact" => !empty($additionalFields["contact"]) ? $additionalFields["contact"] : $podcast->getOwnerEmail(),
            "categories" => implode(", ", $podcast->getCategories(false)),
            "website" => !empty($additionalFields["website"]) ? $additionalFields["website"] : $podcast->getLink(),
            "cover" => $podcast->getCover(),
            "last_update" => $now->format('Y-m-d H:i:s'),
            "hash" => $podcast->getHash(),
            "description" => $podcast->getDescription(),
            "summary" => $podcast->getSummary(),
            "slug" => !empty($additionalFields["slug"]) ? $additionalFields["slug"] : $this->slugify($podcast->getTitle()),
            "shortname" => (count($additionalFields) > 0 && isset($additionalFields["shortname"]) ? $additionalFields["shortname"] : ""), 
            "color" => $color,
            "colorcontrast" => $contrast,
            "fullepisodesonly" => (count($additionalFields) > 0 && isset($additionalFields["fullepisodesonly"]) ? $additionalFields["fullepisodesonly"] : 0),
            "stripfromshownotes" => (count($additionalFields) > 0 && isset($additionalFields["stripfromshownotes"]) ? $additionalFields["stripfromshownotes"] : ""),
            "stripfromdescription" => (count($additionalFields) > 0 && isset($additionalFields["stripfromdescription"]) ? $additionalFields["stripfromdescription"] : ""),
            "stripepisodenumbers" => (count($additionalFields) > 0 && isset($additionalFields["stripepisodenumbers"]) ? $additionalFields["stripepisodenumbers"] : "")
        ];
        
        // Check if podcast is already in DB
        $doesPodcastAlreadyExist = $this->database->select('podcasts', ['last_update', 'hash', 'rowid'], ["feed" => $podcast->getFeedURL()]);

        if (count($doesPodcastAlreadyExist) == 0):
            if ($this->debug) echo " info: does NOT exist\n";
            // if not, insert it
            $values["feed"] = $podcast->getFeedURL();
            $wasSuccesful = $this->database->insert("podcasts", $values);
            $ID = $this->database->id();
            
        else:
            // Remove the hash check here – should be done completely in worker class. If this function gets called, the feed was already fully loaded, so we can just update the database no matter what.
            $wasSuccesful = $this->database->update("podcasts", $values, ["feed" => $podcast->getFeedURL()]);
            $ID = $this->database->id();
            
        endif;
// dump($additionalFields);dump($values);dump($this->database->select("podcasts", "*"));
        if ($wasSuccesful === false): 
            if ($this->database->error): 
                if ($this->debug) echo "  err: ".$this->database->error."\n";
                return false;
            endif;
            return false;
        endif;
        return $ID;
    }

    public function resetDatabase() {
        foreach ($this->tableDefinitions as $name => $table):
            $this->database->drop($name);
            if ($this->database->error) {
                if ($this->debug) echo " err:".$this->database->error."\n";
                return false;
            }
            if ($this->debug) echo " info: $name dropped\n";
        endforeach;
        foreach ($this->tableDefinitions as $name => $table):
            $this->database->create($name, $table);
            if ($this->database->error) {
                if ($this->debug) echo " err:".$this->database->error."\n";
                return false;
            }
            if ($this->debug) echo " info: $name created\n";
        endforeach;
        return true;
    }

    public function getFields($table, $where = false) {
        $result = $this->database->select($table, '*', ($where === false ? null : $where));
        return (isset($result[0]) ? $result[0] : []); 
    }

    public function getField($table, $field, $where = false) {
        $result = $this->database->select($table, [$field], ($where === false ? null : $where));
        return (isset($result[0][$field]) ? $result[0][$field] : ""); 
    }

    public function connectOrCreateDb($dbfile) {
        if (!file_exists($dbfile) && $this->debug) echo " info: DB does not exists\n";
        if (!file_exists(dirname($dbfile))): 
            if ($this->debug) echo " info: DB folder does not exists: ".dirname($dbfile)."\n"; 
            return false; 
        endif;
        try {
            $this->database = new Medoo([
                'type' => 'sqlite',
                'database' => $dbfile
            ]);
            if (!file_exists($dbfile) || $this->testConnection() === false) $this->resetDatabase();
            chmod($dbfile, 0775);
            return true;
        } catch (\Exception $e) {
            if ($this->debug) echo " err: ".$e."\n";
            return false;
        }
    } 

    public function slugify($text) {
        // Strip html tags
        $text=strip_tags($text);
        // Replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        // Transliterate
        setlocale(LC_ALL, 'en_US.utf8');
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        // Remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);
        // Trim
        $text = trim($text, '-');
        // Remove duplicate -
        $text = preg_replace('~-+~', '-', $text);
        // Lowercase
        $text = mb_strtolower($text);
        // Check if it is empty
        if (empty($text)) { return 'n-a'; }
        // Return result
        return $text;
    }

}

?>
