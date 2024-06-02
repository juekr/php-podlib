# php-podlib
a podcast parsing library for PHP

`./vendor/bin/phpunit vendor/juekr/php-podlib/*_test.php`

==IMPORTANT==
 You have to do a `composer update` first

## Instructions

Currently, you have to initialize the class `$x = new PodcastFeed($feed_address)`, but also load the XML (because you might wanna cache it externally – see below) – branch `downloader` is set out to change that behaviour (but shold stay compatible).

==UPDATE==

Now there is an autoload-xml functionality – to stay compatible, the parameter has to follow the debug option like this:

```php
$podcast = new PodcastFeed($feed, $debug, $autoload, $from_cache);
```

### Caching

#### Caching helper function (requiring Symfony's cache component)

```bash
composer require symfony/cache
```

```php
<?php
require_once __DIR__."/../../vendor/autoload.php";
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use PHPPodLib\PodcastFeed;

function get_feed_from_cache($feedUrl, $forceFresh = false, $cache_retention_time = 60 * 60 * 12) { // 1/2 day
        $p = new PodcastFeed($feedUrl);
    
        // Instantiate the caching adapter
        $cachePool = new FilesystemAdapter(
            $namespace = "",
            $defaultLifetime = 0,
            $directory = __DIR__."/../../cache"
        );
        
        // Generate a unique cache key based on the image URL
        $cacheKey = 'feed_' . md5($feedUrl);
        
        // clear cache if forced to
        if ($forceFresh === true) $cachePool->clear();
        
        // Try to fetch the image from the cache
        $cachedItem = $cachePool->getItem($cacheKey);
        
        if (!$cachedItem->isHit()):
            # fetch fresh
            try {
                $grabbed = $p->download_feed_and_return_xml($feedUrl);
            } catch (Exception $e) {
                die($e);
            }
    
            // Store the image data and MIME type in the cache
            $cachedItem->set($grabbed);
            $cachedItem->expiresAfter($cache_retention_time); 
            $cachePool->save($cachedItem);
        else:
            // Extract the image data and MIME type from the cached item
            $grabbed = $cachedItem->get();
        endif;
        
        #$p->loadFeedXml($grabbed);
        return $grabbed;
    }
?>
```

#### Set random cache age for feeds
```php
$p = new PodcastFeed($feedUrl);
$retention = $p->setFeedCacheDuration(24*60*60, 30); # sets a minimum cache age of 1 day and a maximum of 30 days
$p->loadFeedXml($p->get_feed_from_cache($feed));
```

### Description

There are several ways to obtain a podcast's or (to keep things consistent) episode's description: 
- `get_stripped_description($keep_urls = true, $override_description = null, $keep = ["<br>", "<p>"])` (tries to match the best description for your individual purpose)
- `getDescription()` (as it is)
- `intelligentGetContent(string $length = "l", bool $stripHtml = true, bool $reduceLineBreaks = true)` (still kinda experimental)
