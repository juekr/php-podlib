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
$podcast = new PodcastFeed($feed, $debug, $autoload);
```
Note: This is always a hot-load without caching!

## Caching helper function (requiring Symfony's cache component)

```php
<?php
require_once __DIR__."/../vendor/autoload.php";
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use PHPPodLib\PodcastFeed;

function get_feed_from_cache($feedUrl, $forceFresh = false) {
    $p = new PodcastFeed($feedUrl);

	// Instantiate the caching adapter
	$cachePool = new FilesystemAdapter();
	
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
		$cachedItem->expiresAfter(60 * 60 * 12); // 1/2 day
		$cachePool->save($cachedItem);
	else:
		// Extract the image data and MIME type from the cached item
		$grabbed = $cachedItem->get();
	endif;
    
    $p->loadFeedXml($grabbed);
	return $p;
}
?>
```