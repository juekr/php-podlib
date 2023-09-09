<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require_once('./vendor/autoload.php');

final class PodLibEpisode_test extends TestCase 
{
    private $itemXmls;
    private $debug = false;

    protected function setUp(): void {        
        $this->itemXmls = simplexml_load_string(file_get_contents("https://das-a.ch/feed/mp3"))->channel->item;
    }

    public function testDurationExtractionAndConversion() {
        $item = $this->itemXmls[165]; // duration should be: 2:07:52 || 7672
        $result = new PHPPodLib\PodcastEpisode($item, $this->debug);
        $duration_sec = ($result->getDuration(true));
        $duration = ($result->getDuration(false));
        $this->assertTrue(is_numeric($duration_sec) && $duration_sec > 0 && strlen($duration) > 2 && substr_count($duration, ":") > 1);
    }

    // Construction ------------------------------------------
    public function testItemConstruction() {
        $item = $this->itemXmls[random_int(0,count($this->itemXmls)-1)];
        $result = new PHPPodLib\PodcastEpisode($item, $this->debug);
        $this->assertTrue($result->isValid());
    }

    // Extraction ------------------------------------------ 
    public function testChapterExtraction() {
        $item = $this->itemXmls[random_int(0,count($this->itemXmls)-1)];
        $result = new PHPPodLib\PodcastEpisode($item, $this->debug);
        $this->assertGreaterThan(1, count($result->getChapters()));
    }

    public function testEpisodeCoverExtraction() {
        $item = $this->itemXmls[random_int(0,count($this->itemXmls)-1)];
        $result = new PHPPodLib\PodcastEpisode($item, $this->debug);
        $this->assertGreaterThan(-1, strpos($result->getCover(), "http"));
    }

    public function testPubDateExtraction() {
        $item = $this->itemXmls[random_int(0,count($this->itemXmls)-1)];
        $result = new PHPPodLib\PodcastEpisode($item, $this->debug);
        $this->assertEquals("20", substr($result->getPubdate("Y"),0,2));
    }

    public function testTagExtraction() {
        $item = $this->itemXmls[count($this->itemXmls)-1];
        $result = new PHPPodLib\PodcastEpisode($item, $this->debug);
        $this->assertGreaterThan(1, count($result->getKeywords()));
    }

    public function testShownoteExtraction() {
        $item = $this->itemXmls[random_int(0,count($this->itemXmls)-1)];
        $result = new PHPPodLib\PodcastEpisode($item, $this->debug);
        $this->assertGreaterThan(50, strlen($result->getContent()));
    }

    public function testShownoteExtractionEp0() {
        $item = $this->itemXmls[count($this->itemXmls)-1];
        $result = new PHPPodLib\PodcastEpisode($item, $this->debug);
        $this->assertGreaterThan(50, strlen($result->getContent()));
    }

    // ------------------------------------------ Extraction

    // Episode matching ------------------------------------
    public function testMatchingFunctionWithAString() {
        $item = $this->itemXmls[count($this->itemXmls)-1];
        $episode = new PHPPodLib\PodcastEpisode($item, $this->debug);
        $this->assertTrue(
            $episode->isMatch(
                "string",
                "title",
                "00 – Sorry, aber diese Episode ist echt beschissen"
            )
        );
    }

    public function testMatchingFunctionWithAStringFails() {
        $item = $this->itemXmls[count($this->itemXmls)-1];
        $episode = new PHPPodLib\PodcastEpisode($item, $this->debug);
        $this->assertFalse(
            $episode->isMatch(
                "string",
                "title",
                "00 – sorry, aber diese Episode ist echt beschissen"
            )
        );
    }

    public function testMatchingFunctionWithACaseInsensitiveString() {
        $item = $this->itemXmls[count($this->itemXmls)-1];
        $episode = new PHPPodLib\PodcastEpisode($item, $this->debug);
        $this->assertTrue(
            $episode->isMatch(
                "string_caseinsensitive",
                "title",
                "00 – sorry, aber diese Episode ist echt beschissen"
            )
        );
    }

    public function testMatchingFunctionWithRegex() {
        $item = $this->itemXmls[count($this->itemXmls)-1];
        $episode = new PHPPodLib\PodcastEpisode($item, $this->debug);
        $this->assertTrue(
            $episode->isMatch(
                "regex",
                "title",
                "!beschi!isU"
            )
        );
    }

    public function testMatchingFunctionContains() {
        $item = $this->itemXmls[count($this->itemXmls)-1];
        $episode = new PHPPodLib\PodcastEpisode($item, $this->debug);
        $this->assertFalse(
            $episode->isMatch(
                "contains_casesensitive",
                "tags",
                "Neuere Und Neuste GeschiChte"
            )
        );
    }

    public function testMatchingFunctionWithRegexButFails() {
        $item = $this->itemXmls[count($this->itemXmls)-1];
        $episode = new PHPPodLib\PodcastEpisode($item, $this->debug);
        $this->assertFalse(
            $episode->isMatch(
                "regex",
                "title",
                "!beschii!isU"
            )
        );
    }

    // ------------------------------------ Episode matching

    // Get differently sized content pieces -------------------
    public function testIntelligentContentMatchingNullEpisode() {
        $item = $this->itemXmls[count($this->itemXmls)-1];
        $episode = new PHPPodLib\PodcastEpisode($item, $this->debug);
        $l = $episode->intelligentGetContent("l");
        $s = $episode->intelligentGetContent("s");
        
        $this->assertGreaterThan(
            strlen($s), strlen($l)
        );
    }

    public function testIntelligentContentMatchingLatestEpisode() {
        $item = $this->itemXmls[0]; // 
        $episode = new PHPPodLib\PodcastEpisode($item, $this->debug);
        $l = $episode->intelligentGetContent("l");
        $s = $episode->intelligentGetContent("s");
        
        $this->assertGreaterThan(
            strlen($s), strlen($l)
        );
        $this->assertFalse(strpos($s, "<div>"));
    }

    // ------------------ -Get differently sized content pieces 
}