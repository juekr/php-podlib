<?php 
/*
    Markdown exporting class

*/

namespace PHPPodLib;
use League\HTMLToMarkdown\HtmlConverter;

class MarkdownExporter extends HtmlConverter {
    private $podcast = null;


    public function load_podcast(string $feed = null)  : bool
    {
        if (empty($feed)) return false;
        $this->podcast = new PodcastFeed($feed, true, true, true);
        if ($this->podcast->get_autoloading_success() == false) return false;
        return true;
    }

    private function _build_frontmatter(array $selection = [], $prefix = "", $list_as_list = true) : string
    {
        $episodes = $this->podcast->getEpisodes();
        $return = [];
        $return["date"] = date("Y-m-d, H:i");
        $return["modified"] = date("Y-m-d, H:i");
        $return["created"] = date("Y-m-d, H:i");
        $return["pubdate"] = $this->podcast->getPubdate("Y-m-d, H:i");
        $return["first_episode"] = date("Y-m-d, H:i", strtotime($episodes[count($episodes)-1]->getPubdate()));
        $return["last_episode"] = date("Y-m-d, H:i", strtotime($episodes[0]->getPubdate()));
        $return["website"] = $this->podcast->getLink();
        $return["feed"] = $this->podcast->getFeedUrl();
        $return["cover"] = $this->podcast->getCover();
        $return["author"] = $this->podcast->getOwnerName();
        $return["contact"] = $this->podcast->getOwnerEmail();
        #$return["itunes_id"] = $this->podcast->get;
        $return["frequency"] = $this->podcast->estimatePublishingFrequency(1);
        $return["number_of_episodes"] = count($episodes);
        $return["category"] = $this->podcast->getCategories();
        $return["tags"] = (count($this->podcast->getTags()) > 0) ? $this->_flatten(
            array_slice(array_keys($this->podcast->getMostCommonTags()), 0, 10),
            false, 0, "", ", "
        ): [];
        $return["generator"] = $this->podcast->getGenerator();
        $return["language"] = $this->podcast->getLanguage();
        $return["subtitle"] = $this->podcast->getSubtitle();
        $return["title"] = $this->podcast->getTitle();
        $return["type"] = $this->podcast->getType();

        if (count($selection) > 0):
            $return = array_filter($return, function($v) use ($selection) {
                return in_array($v, $selection);
            }, ARRAY_FILTER_USE_KEY);
        endif;

        $output = implode("\n", array_map(
            function ($v, $k) {
                global $list_as_list;
                if(is_array($v)){
                    if (count($v) == 0) return "".$k.":";
                    return "".$k.":".($list_as_list ? "\n" : "").$this->_flatten($v, false, 0, "", $list_as_list ? "\n" : ", ");
                } else {
                    if (!empty($v) && strpos($v, ":") != false)  return "".$k.": \"".$v."\"";
                    return "".$k.": ".$v."";
                }
            }, 
            $return, 
            array_keys($return)
        ));

        return empty($prefix) ? trim($output) : implode("\n".$prefix, explode("\n", $output));
    }

    private function _flatten(
        array $array,
        bool $with_keys = true,
        int $level_of_intend = 1,
        string $list_char = "-",
        string $list_oneline_char = "\n"
    ) : string
    {
        $return = [];
        foreach ($array as $idx => $element):
            $return[] = str_repeat(" ", $level_of_intend).$list_char." ".($with_keys ? $idx.": " : "").$element;
        endforeach;
        return implode($list_oneline_char, $return);
    }

    public function to_markdown(
        int $include_episodes = -1 ,
        array $frontmatter_keys = [
        ]
    ) : string
    {
        $return = "";

        $return .= "---\n".$this->_build_frontmatter($frontmatter_keys);
        $return .= "\n---";
        $return .= "\n\n# ".$this->podcast->getTitle();
        $return .= "\n🔙 [[_CONTACTS/Podcast-Brause-Verwaltung/Podcasts - eine Übersicht|Übersicht]]";
        $return .= "\n🔗 [Webseite 🌐](".$this->podcast->getLink().")";
        $return .= "\n🔗 [Feed 📃](".$this->podcast->getFeedUrl().")";
        if (!empty($this->podcast->getOwnerEmail())):
            $return .= "\n🔗 [".$this->podcast->getOwnerName()." 📨](mailto:".$this->podcast->getOwnerEmail().")";
        else:
            $return .= "\n❌ ".$this->podcast->getOwnerName()." 🗣️";
        endif;
        // $return .= !empty($itunesid) ? "\n[🍏 Apple]($ituneslink)" : "";
        
        $return .= "\n\n![".$this->podcast->getTitle()."|200](".$this->podcast->getCover().")";

        $return .= "\n\n## Description";
        $return .= "\n\n".$this->convert($this->podcast->getDescription());

        $return .= "\n\n## Details";
        $return .= "\n\n> [!example]- Details\n> ";
        $return .= $this->_build_frontmatter([], "> ", false);


        //     $itunesid = (empty($item["itunesId"]) ? "" : "".$item['itunesId']);
	    // $ituneslink = empty($itunesid) ? "" : "https://podcasts.apple.com/de/podcast/id".$itunesid;

        // $return .= "\n\n## Tags\n\n".implode(", ", $this->podcast->getTags());

        if ($include_episodes != 0):
            $return .= "\n\n## Latest episode".($include_episodes != 1 ? "s" : "");
            $counter = 0;
            foreach ($this->podcast->getEpisodes() as $episode):
                $return .= "\n\n### ".str_replace("#", "\#", $episode->getTitle());
                $return .= "\n\n".$episode->getPubdate("d.m.Y");
                $return .= empty($episode->getSubtitle()) ? "" : "\n\n**".$episode->getSubtitle()."**";
                $return .= "\n\n".$this->convert($episode->getDescription());

                if (++$counter == $include_episodes) break;
            endforeach;

        endif; // $include_episodes != 0

        return $return;
    }
}
?>