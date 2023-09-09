<?php
declare(strict_types=1);
require_once('vendor/autoload.php');
use \PHPUnit\Framework\TestCase;


final class PodLib_test extends TestCase
{
    private $debug = false;

    private $validFeeds = [ 
        "https://das-a.ch/feed/mp3",
        "https://geschichteeuropas.podigee.io/feed/mp3",
        "https://heldendumm.de/feed/podcast",
        "https://feed.schwarz-code-gold.de",
        "https://podcastpastete.de/feed/mp3/"
        ];
    private $validUrlButNotAFeed = "https://das-a.ch";
    private $invalidUrl = "xxx";
    private $emptyFeedXml = '<?xml version="1.0" encoding="UTF-8"?>
    <?xml-stylesheet href="/stylesheet.xsl" type="text/xsl"?>
    <rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:psc="http://podlove.org/simple-chapters" xmlns:media="http://search.yahoo.com/mrss/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:fh="http://purl.org/syndication/history/1.0" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:sy="http://purl.org/rss/1.0/modules/syndication/" xmlns:feedpress="https://feed.press/xmlns" xmlns:googleplay="http://www.google.com/schemas/play-podcasts/1.0" xmlns:podcast="https://github.com/Podcastindex-org/podcast-namespace/blob/main/docs/1.0.md">
      <channel>
        <feedpress:locale>en</feedpress:locale>
        <language>de</language>
        <title>Ach? Triumvirat für historisch inspirierte Humorvermittlung</title>
        <description>Lasst euch von uns auf eine Reise mitnehmen durch die aberwitzigsten Begebenheiten in unserer Geschichte: Wenn hochengagierte Darwin-Award-Kandidaten auf ausgemachte Pechvögel treffen. Wenn verkannte Visionäre den großen Durchbruch haarscharf verfehlen. Wenn „das ist ja interessant“ viel wichtiger ist als „das war ein historischer Tag“. Der Clou: Wenn der Aufnahmeknopf gedrückt wird, haben zwei Drittel des Podcast-Trios keinen Schimmer, worum es in der Folge gehen wird. Ein großer Spaß für die ganze Familie … oder zumindest für eure Podgastgeber Philipp, Dominik und Jürgen!</description>
        <link>https://www.das-a.ch</link>
        <lastBuildDate>Wed, 15 Dec 2021 19:41:41 +0100</lastBuildDate>
        <copyright>Jürgen Krauß</copyright>
        <podcast:locked owner="schreib-dem-juergen@das-a.ch">yes</podcast:locked>
        <podcast:funding url="https://das-a.ch/support">Support this podcast on Patreon</podcast:funding>
        <image>
          <url>https://lcdn.letscast.fm/media/podcast/c88eaa64/artwork-3000x3000.jpg?t=1639593701</url>
          <title>Ach? Triumvirat für historisch inspirierte Humorvermittlung</title>
          <link>https://www.das-a.ch</link>
        </image>
        <atom:contributor>
          <atom:name>Jürgen, Dominik, Philipp</atom:name>
        </atom:contributor>
        <generator>LetsCast.fm (https://letscast.fm)</generator>
        <itunes:subtitle>Geschichte, wie ihr sie noch nie gehört habt!</itunes:subtitle>
        <itunes:author>Jürgen, Dominik, Philipp</itunes:author>
        <itunes:type>episodic</itunes:type>
        <itunes:new-feed-url>https://letscast.fm/podcasts/ach-triumvirat-fuer-historisch-inspirierte-humorvermittlung-29091bb5/feed</itunes:new-feed-url>
        <itunes:keywords>Geschichte,Unterhaltung,Überraschung,Geschichten,Improvisation,Comedy,Spaß,Biografie,Lebensgeschichte,außergewöhnlich</itunes:keywords>
        <itunes:category text="History"/>
        <itunes:category text="Comedy"/>
        <itunes:owner>
          <itunes:name>Jürgen, Dominik, Philipp</itunes:name>
          <itunes:email>schreib-dem-juergen@das-a.ch</itunes:email>
        </itunes:owner>
        <itunes:image href="https://lcdn.letscast.fm/media/podcast/c88eaa64/artwork-3000x3000.jpg?t=1639593701"/>
        <itunes:explicit>no</itunes:explicit>
        <itunes:complete>no</itunes:complete>
        <itunes:block>no</itunes:block>
        <googleplay:author>Jürgen, Dominik, Philipp</googleplay:author>
        <googleplay:summary>Lasst euch von uns auf eine Reise mitnehmen durch die aberwitzigsten Begebenheiten in unserer Geschichte: Wenn hochengagierte Darwin-Award-Kandidaten auf ausgemachte Pechvögel treffen. Wenn verkannte Visionäre den großen Durchbruch haarscharf verfehlen. Wenn „das ist ja interessant“ viel wichtiger ist als „das war ein historischer Tag“. Der Clou: Wenn der Aufnahmeknopf gedrückt wird, haben zwei Drittel des Podcast-Trios keinen Schimmer, worum es in der Folge gehen wird. Ein großer Spaß für die ganze Familie … oder zumindest für eure Podgastgeber Philipp, Dominik und Jürgen!</googleplay:summary>
        <googleplay:image href="https://lcdn.letscast.fm/media/podcast/c88eaa64/artwork-3000x3000.jpg?t=1639593701"/>
        <googleplay:explicit>no</googleplay:explicit>
        <googleplay:block>no</googleplay:block></channel></rss></xml>';

    // Construction ----------------------------------------
    public function testInstanciatePodcastFeedClassWithoutParameter() {
        $podcast = new \PHPPodLib\PodcastFeed(null, $this->debug);
        $this->assertInstanceOf(
            \PHPPodLib\PodcastFeed::class,
            $podcast
        );
    }

    public function testInstanciatePodcastFeedClassWithValidFeed() {
        $podcast = new \PHPPodLib\PodcastFeed($this->validFeeds[random_int(0, count($this->validFeeds)-1)], $this->debug);
        $this->assertInstanceOf(
            \PHPPodLib\PodcastFeed::class,
            $podcast
        );
    }

    public function testInstanciatePodcastFeedClassWithInvalidFeed() {
        $podcast = new \PHPPodLib\PodcastFeed($this->invalidUrl, $this->debug);
        $this->assertInstanceOf(
            \PHPPodLib\PodcastFeed::class,
            $podcast
        );
    }
    // -------------------------------------- Construction
    
    // Feed validity -------------------------------------
    public function testFeedValidityWithValidFeedUrl() {
        $podcast = new \PHPPodLib\PodcastFeed(null, $this->debug);
        $result = $podcast->setFeed($this->validFeeds[random_int(0, count($this->validFeeds)-1)]);
        $this->assertTrue($result);
    }
    public function testFeedValidityWithInValidFeedUrl() {
        $podcast = new \PHPPodLib\PodcastFeed($this->invalidUrl, $this->debug);
        $result = $podcast->isValidUrl();
        $this->assertFalse($result);
    }
    // ------------------------------------- Feed validity

    // Load feed content -----------------------------------
    public function testLoadingFeedContentWithEmptyXML() {
        $feedUrl = $this->validFeeds[random_int(0, count($this->validFeeds)-1)];
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug);
        $result = $podcast->loadFeedXml();
        $this->assertFalse($result);
    }

    public function testLoadingFeedContentWithXMLThatHasNoItems() {
        $feedUrl = $this->validFeeds[random_int(0, count($this->validFeeds)-1)];
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug);
        $result = $podcast->loadFeedXml($this->emptyFeedXml);
        $this->assertFalse($result);
    } 

    public function testDownloadXmlFunction() {
        $podcast = $this->setupValidFeed(4);
        $result = $podcast->download_feed_and_return_xml();
        $this->assertNotEmpty($result);
    }

    public function testAutoloadAndDownloadXmlFunction() {
        $feedUrl = $this->validFeeds[random_int(0, count($this->validFeeds)-1)];
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug, true);
        $this->assertNotEmpty($podcast->getTitle());
    }

    public function testLoadingFeedContentWithANonXMLString() {
        $feedUrl = $this->validUrlButNotAFeed;
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug);
        $result = $podcast->loadFeedXml($podcast->download_feed_and_return_xml($feedUrl));
        $this->assertFalse($result);
    }

    public function testLoadingFeedContentWithValidXML() {
        $feedUrl = $this->validFeeds[random_int(0, count($this->validFeeds)-1)];
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug);#
        $result = $podcast->loadFeedXml($podcast->download_feed_and_return_xml($feedUrl));
        $this->assertTrue($result);
    }
    // ----------------------------------- Load feed content
    
    // Seconds 2 Timestring ----------------------------------- 
    public function testSecondToTimestringConversionShort() {
        $feedUrl = $this->validFeeds[random_int(0, count($this->validFeeds)-1)];
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug);
        $result = $podcast->convertSecondsToTimestring(60*60*25 + 121, false);
        $this->assertEquals("1:1:02:01", $result);
    }

    public function testSecondToTimestringConversionLong() {
        $feedUrl = $this->validFeeds[random_int(0, count($this->validFeeds)-1)];
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug);
        $result = $podcast->convertSecondsToTimestring(60*60*25 + 121, true);
        $this->assertEquals("1 Tag, 1 Stunde, 2 Minuten, 1 Sekunde", $result);
    }
    
    public function testSecondToTimestringConversionNoneInt() {
        $feedUrl = $this->validFeeds[random_int(0, count($this->validFeeds)-1)];
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug);
        $result = $podcast->convertSecondsToTimestring("false", false);
        $this->assertFalse($result);
    }
    // ----------------------------------- Seconds 2 Timestring

    // Podcast entire play length ------------------------------
    public function testGetDurationOfPodcastInSecondsWithoutItemList() {
        $feedUrl = $this->validFeeds[random_int(0, count($this->validFeeds)-1)];
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug);
        $podcast->loadFeedXml(file_get_contents($feedUrl));        $result = $podcast->getDuration(true);
        $this->assertTrue(is_int($result) && $result > 1);
    }

    public function testGetDurationOfPodcastAsStringWithoutItemList() {
        $feedUrl = $this->validFeeds[random_int(0, count($this->validFeeds)-1)];
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug);
        $podcast->loadFeedXml(file_get_contents($feedUrl));        $result = $podcast->getDuration();
        $this->assertTrue(strpos($result, "Sekunde") > -1);
    }

    public function testGetDurationOfPodcastInSecondsWithItemList() {
        $feedUrl = $this->validFeeds[3];
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug);
        $podcast->loadFeedXml(file_get_contents($feedUrl));        
        $result = $podcast->getDuration(true, array_slice( $podcast->getEpisodes(), 1, 4));
        $this->assertTrue(is_int($result) && $result > 1);
    }

    public function testGetDurationOfPodcastAsStringWithItemList() {
        $feedUrl = $this->validFeeds[random_int(0, count($this->validFeeds)-1)];
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug);
        $podcast->loadFeedXml(file_get_contents($feedUrl));        $result = $podcast->getDuration(false, array_slice( $podcast->getEpisodes(), 2));
        $this->assertTrue(strpos($result, "Sekunde") > -1);
    }

    public function testGetDurationOfPodcastInSecondsWithEmptyItemList() {
        $feedUrl = $this->validFeeds[random_int(0, count($this->validFeeds)-1)];
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug);
        $podcast->loadFeedXml(file_get_contents($feedUrl));  
        $result = $podcast->getDuration(true, []);
        $this->assertEquals(0, $result);
    }

    public function testGetDurationOfPodcastAsStringWithEmptyItemList() {
        $feedUrl = $this->validFeeds[random_int(0, count($this->validFeeds)-1)];
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug);
        $podcast->loadFeedXml(file_get_contents($feedUrl));  
        $result = $podcast->getDuration(false, []);
        $this->assertEquals("0 Stunden, 0 Minuten, 0 Sekunden", $result);
    }
    // ------------------------------ Podcast entire play length
    
    // Collect episode tags ------------------------------
    public function testCollectEpisodeTagsWithoutList() {
        $feedUrl = $this->validFeeds[0];
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug);
        $podcast->loadFeedXml(file_get_contents($feedUrl));  
        $result = $podcast->getTags();
        $this->assertContains("Zeitgeschichte", $result);
    }

    public function testCollectEpisodeTagsFromEmptyFeed() {
        $feedUrl = $this->validFeeds[random_int(0, count($this->validFeeds)-1)];
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug);
        $podcast->loadFeedXml();  
        $result = $podcast->getTags();
        $this->assertEmpty($result);
    }

    public function testCollectEpisodeTagsWithList() {
        $feedUrl = $this->validFeeds[0];
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug);
        $podcast->loadFeedXml(file_get_contents($feedUrl));  
        $result = $podcast->getTags(array_slice( $podcast->getEpisodes(), 2));
        $this->assertContains("Zeitgeschichte", $result);
    }

    public function testCollectEpisodeTagsWithoutListNoDuplicates() {
        $feedUrl = $this->validFeeds[0];
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug);
        $podcast->loadFeedXml(file_get_contents($feedUrl));  
        $result = $podcast->getTags($podcast->getEpisodes());
        $this->assertEquals(array_count_values($result)["Zeitgeschichte"], 1);
    }

    public function testCollectEpisodeTagsWithoutListWithDuplicates() {
        $feedUrl = $this->validFeeds[random_int(0, count($this->validFeeds)-1)];
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug);
        $podcast->loadFeedXml(file_get_contents($feedUrl));  
        $result = $podcast->getTags($podcast->getEpisodes(), false);
        $this->assertGreaterThan(1, count($result));
    }

    public function testGetMostCommonTags() {
        $podcast = $this->setupValidFeed(0);
        $result = $podcast->getMostCommonTags(false, 2);
        $this->assertArrayHasKey("Europa", $result);
    }
    // ------------------------------ Collect episode tags 

    // Metadata -------------------------------------------
    // Stylesheet
    public function testGetStylesheetSuccess() {
        $podcast = $this->setupValidFeed(0);
        $result = $podcast->getStylesheet();
        $this->assertStringContainsString(".xsl", $result);
    }

    public function testGetStylesheetFailure() {
        $podcast = $this->setupValidFeed(1);
        $result = $podcast->getStylesheet();
        $this->assertEquals(null, $result);
    }

    // Categories
    public function testGetCategoriesAll() {
        $podcast = $this->setupValidFeed();
        $result = $podcast->getCategories();
        $this->assertGreaterThanOrEqual(1, count($result));
    }

    public function testGetCategoriesMain() {
        $podcast = $this->setupValidFeed();
        $result = $podcast->getCategories(true);
        $this->assertIsString($result);
    }

    public function testGetCategoryNames() {
        $podcast = $this->setupValidFeed();
        $result = $podcast->getPossibleCategoryNames("de");
        $this->assertContains("Gemeinnützig", $result);
    }

    public function testCategoryTranslation() {
        $podcast = $this->setupValidFeed();
        $result = $podcast->getCategories(true, true);
        $this->assertContains($result, $podcast->getPossibleCategoryNames('de'));
    }

    public function testLinkExtraction() {
        $podcast = $this->setupValidFeed();
        $result = $podcast->getLink();
        $this->assertNotEmpty($result);
    }

    public function testLanguageExtraction() {
        $podcast = $this->setupValidFeed();
        $result = $podcast->getLanguage();
        $this->assertNotEmpty($result);
    }

    public function testAuthorExtraction() {
        $podcast = $this->setupValidFeed();
        $result = $podcast->getAuthor();
        $this->assertNotEmpty($result);
    }

    public function testOwnerExtraction() {
        $podcast = $this->setupValidFeed();
        $result = $podcast->getOwnerEmail();
        $this->assertNotEmpty($result);
    }

    public function testOwnerExtractionInAFakeFeed() {
        $podcast = $this->setupInvalidFeed();
        $result = $podcast->getOwnerEmail();
        $this->assertNull($result);
    }

    public function testCoverExtraction() {
        $podcast = $this->setupValidFeed();
        $result = $podcast->getCover();
        $this->assertNotEmpty($result);
    }

    public function testCoverExtractionSpecial() {
        $podcast = $this->setupValidFeed(4);
        $result = $podcast->getCover();
        $this->assertNotEmpty($result);
    }

    public function testPubdateExtraction() {
        $podcast = $this->setupValidFeed();
        $result = $podcast->getPubdate("Y");
        $this->assertEquals("20", substr($result,0,2));
    }

    // ------------------------------------------- Metadata

    // ------------------------------------------- Episode matching
    public function testEpisodeFilterRegexNumbersInTitle() {
        $podcast = $this->setupValidFeed();
        $result = $podcast->getFilteredEpisodes("regex", "title", "![0-9]+!isU");
        $this->assertGreaterThan(2, count($result));
    }

    public function testEpisodeFilterRegexNoReturn() {
        $podcast = $this->setupValidFeed();
        $result = $podcast->getFilteredEpisodes("regex", "title", "!thisWillSurelyFail!isU");
        $this->assertEquals(0, count($result));
    }

    public function testEpisodeFilterSearchStringFails() {
        $podcast = $this->setupValidFeed();
        $result = $podcast->getFilteredEpisodes("string", "description", ",");
        $this->assertEquals(0, count($result));
    }

    public function testEpisodeFilterSearchStringContainsTrue() {
        $podcast = $this->setupValidFeed();
        $result = $podcast->getFilteredEpisodes("contains_casesensitive", "description", " ");
        $this->assertGreaterThan(2, count($result));
    }

    // ------------------------------------------- Episode matching


    private function setupInvalidFeed() {
        return new \PHPPodLib\PodcastFeed($this->validUrlButNotAFeed, $this->debug);
    }

    private function setupValidFeed($key = -1) {
        if ($key == -1 || $key >= count($this->validFeeds)) $feedUrl = $this->validFeeds[random_int(0, count($this->validFeeds)-1)]; else $feedUrl = $this->validFeeds[$key];
        $podcast = new \PHPPodLib\PodcastFeed($feedUrl, $this->debug);
        $podcast->loadFeedXml($podcast->download_feed_and_return_xml($feedUrl)); 
        return $podcast;
    }
}
?>