<?php

require_once './Config/redis.php';
require_once './Models/Feed.php';
require_once './Controllers/BaseController.php';
require_once './Utils/normalizeDate.php';

class FeedController extends BaseController
{
    private $db;
    private $redis;
    private $feedModel;
    private $cacheExpiry = 3600; // 1 hour for feeds
    private $rssCacheExpiry = 900; // 15 minutes for RSS feeds 
    private $topicCacheExpiry = 3600; // 1 hours for topics 

    public function __construct($db)
    {
        $this->db = $db;
        $this->redis = Redis::getClient();
        $this->feedModel = new Feed($db);
    }

    private function getFeedCacheKey($options)
    {
        $keyParts = [];
        foreach ($options as $key => $value) {
            if (!is_null($value) && $key !== 'rssFeedLink') {
                $keyParts[] = "{$key}:{$value}";
            }
        }
        return 'feeds:' . (empty($keyParts) ? 'all' : implode(':', $keyParts));
    }

    private function getRSSFeedCacheKey($rssUrl)
    {
        return 'rss_feed:' . md5($rssUrl);
    }

    private function getTopicsCacheKey()
    {
        return 'topics:all';
    }

    private function getAllFeedsCacheKey()
    {
        return 'feeds:all';
    }

    private function cacheFeedsData($cacheKey, $feeds, $expiry = null)
    {
        try {
            if (empty($feeds)) {
                return false;
            }

            $expiry = $expiry ?? $this->cacheExpiry;

            // Store as JSON string instead of individual set members for better performance
            $this->redis->setex($cacheKey, $expiry, json_encode($feeds));
            return true;
        } catch (Exception $e) {
            error_log("Failed to cache feeds data: " . $e->getMessage());
            return false;
        }
    }

    private function getCachedFeedsData($cacheKey)
    {
        try {
            $cachedData = $this->redis->get($cacheKey);
            if ($cachedData) {
                $feeds = json_decode($cachedData, true);
                return is_array($feeds) ? $feeds : null;
            }
            return null;
        } catch (Exception $e) {
            error_log("Failed to get cached feeds data: " . $e->getMessage());
            return null;
        }
    }


    public function getFeeds()
    {
        $skipVerification = false;

        if (isset($_SERVER['HTTP_X_EMBED_REQUEST']) && $_SERVER['HTTP_X_EMBED_REQUEST'] === 'true') {
            $skipVerification = true;
        }

        if (!$skipVerification) {
            $user = $this->verifyToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'errorMessage' => 'Unauthorized']);
                return;
            }
        }

        // Parse and sanitize options
        $options = [
            'rssFeedLink' => !empty($_GET['rssFeed']) ? $_GET['rssFeed'] : null,
            'topic' => !empty($_GET['topic']) ? htmlspecialchars($_GET['topic']) : null,
            'source' => !empty($_GET['source']) ? htmlspecialchars($_GET['source']) : null,
            'author' => !empty($_GET['author']) ? htmlspecialchars($_GET['author']) : null,
        ];

        try {
            if (is_null($options['rssFeedLink'])) {
                // Handle database feeds
                $this->handleDatabaseFeeds($options);
            } else {
                // Handle RSS feeds
                $this->handleRSSFeeds($options['rssFeedLink'], $options);
            }
        } catch (Exception $e) {
            $this->sendErrorResponse(500, 'Error retrieving feeds: ' . $e->getMessage());
        }
    }

    private function handleDatabaseFeeds($options)
    {
        $feedCacheKey = $this->getFeedCacheKey($options);
        $allFeedsCacheKey = $this->getAllFeedsCacheKey();

        // Try to get filtered feeds from cache
        $feeds = $this->getCachedFeedsData($feedCacheKey);
        $fromCache = true;

        if (is_null($feeds)) {
            // Cache miss - fetch from database
            $feeds = $this->feedModel->getFeeds($options);
            $fromCache = false;

            if ($feeds) {
                $this->cacheFeedsData($feedCacheKey, $feeds);
            }
        }

        if (empty($feeds)) {
            // No filtered results found, try to get all feeds
            $this->handleFallbackFeeds($allFeedsCacheKey, $options);
            return;
        }

        // Success response
        $this->sendSuccessResponse([
            'success' => true,
            'data' => $feeds,
            'count' => count($feeds),
            'filter_applied' => $options,
            'message' => 'Filtered feeds retrieved successfully',
            'is_fallback' => false,
            'from_cache' => $fromCache
        ]);
    }

    private function handleFallbackFeeds($allFeedsCacheKey, $options)
    {
        // Try to get all feeds from cache
        $allFeeds = $this->getCachedFeedsData($allFeedsCacheKey);
        $fromCache = true;

        if (is_null($allFeeds)) {
            // Cache miss - fetch all feeds from database
            $allFeeds = $this->feedModel->getFeeds([], false);
            $fromCache = false;

            if ($allFeeds) {
                $this->cacheFeedsData($allFeedsCacheKey, $allFeeds);
            }
        }

        if (empty($allFeeds)) {
            $this->sendErrorResponse(404, 'Unable to find feeds for you.', $options);
            return;
        }

        // Fallback response
        $this->sendSuccessResponse([
            'success' => true,
            'data' => $allFeeds,
            'count' => count($allFeeds),
            'filter_applied' => $options,
            'message' => 'No results found for your search. Showing all feeds instead.',
            'is_fallback' => true,
            'from_cache' => $fromCache
        ]);
    }

    private function handleRSSFeeds($rssUrl, $options)
    {
        $rssCacheKey = $this->getRSSFeedCacheKey($rssUrl);

        $feeds = $this->getCachedFeedsData($rssCacheKey);
        $fromCache = true;

        if (is_null($feeds)) {
            // Cache miss - parse RSS feed
            $feeds = $this->parseRSSFeeds($rssUrl);
            $fromCache = false;

            if ($feeds) {
                $this->cacheFeedsData($rssCacheKey, $feeds, $this->rssCacheExpiry);
            }
        }

        if (empty($feeds)) {
            $this->sendErrorResponse(404, 'Invalid or empty RSS Feed', $options);
            return;
        }

        // Success response
        $this->sendSuccessResponse([
            'success' => true,
            'data' => $feeds,
            'count' => count($feeds),
            'filter_applied' => $options,
            'message' => 'RSS feeds retrieved successfully',
            'is_fallback' => false,
            'from_cache' => $fromCache
        ]);
    }

    private function parseRSSFeeds($rssURL)
    {
        $dcNS = 'http://purl.org/dc/elements/1.1/';

        try {
            libxml_use_internal_errors(true);
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Mozilla/5.0 (compatible; FeedReader/1.0)',
                    'method' => 'GET'
                ]
            ]);

            $xmlContent = @file_get_contents($rssURL, false, $context);
            if (!$xmlContent) {
                return [];
            }

            $rss = @simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);

            if (!$rss || !isset($rss->channel->item)) {
                libxml_clear_errors();
                return [];
            }

            $feedData = [];
            $source = (string)($rss->channel->title ? $this->stripStrings($rss->channel->title) : "Untitled Feed");
            $i = 1;

            foreach ($rss->channel->item as $item) {
                $dc = $item->children($dcNS);

                $image = $this->extractMediaImage($item);

                $rawDescription = (string)($item->description ?? '');
                $plainDescription = strip_tags($rawDescription);
                $clean_desc = $this->stripStrings($plainDescription);
                $snippet = mb_substr($clean_desc, 0, 300) . (strlen($clean_desc) > 300 ? '...' : '');

                $feedData[] = [
                    "id" => $i++,
                    "title" => (string)($item->title ? $this->stripStrings($item->title) : ""),
                    "description" => $snippet,
                    "topic" => null,
                    "source" => $source,
                    "author" => (string)($dc->creator ? $this->stripStrings($dc->creator) : ""),
                    "link" => (string)($item->link ?? ""),
                    "image" => $image,
                    "published_at" => isset($item->pubDate) ? normalizePubDate((string)$item->pubDate) : null
                ];
            }

            libxml_clear_errors();
            return $feedData;
        } catch (Exception $e) {
            error_log("RSS parsing error for URL {$rssURL}: " . $e->getMessage());
            return [];
        }
    }

    private function extractMediaImage($item)
    {
        $mediaNS = "http://search.yahoo.com/mrss/";
        $media = $item->children($mediaNS);
        $image = null;

        // Try media:thumbnail first
        if (isset($media->thumbnail) && count($media->thumbnail) > 0) {
            return (string)$media->thumbnail[0]->attributes()->url;
        }

        // Try media:group thumbnail
        if (empty($image) && isset($media->group->thumbnail[0]['url'])) {
            return (string)$media->group->thumbnail[0]['url'];
        }

        // Try media:group content
        if (empty($image) && isset($media->group->content[0]['url'])) {
            $type = (string)$media->group->content[0]['type'];
            if (str_starts_with($type, 'image/')) {
                return (string)$media->group->content[0]['url'];
            }
        }

        // Try media:content
        if (empty($image) && isset($media->content[0]['url'])) {
            $type = (string)$media->content[0]['type'];
            if (str_starts_with($type, 'image/')) {
                return (string)$media->content[0]['url'];
            }
        }

        // Try media:content with attributes
        if (empty($image) && isset($media->content) && $media->content->attributes()->url) {
            $type = (string)$media->content->attributes()->type;
            if (empty($type) || str_starts_with($type, 'image/')) {
                return (string)$media->content->attributes()->url;
            }
        }

        // Try content:encoded
        if (empty($image) && isset($item->{'content:encoded'})) {
            $html = (string) $item->{'content:encoded'};
            if (preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i', $html, $matches)) {
                return $matches[1];
            }
        }

        // Try enclosure
        if (empty($image) && isset($item->enclosure)) {
            $enclosure = $item->enclosure;
            $type = (string) $enclosure['type'];
            if (str_starts_with($type, 'image/')) {
                return (string) $enclosure['url'];
            }
        }

        // Try image element
        if (empty($image) && isset($item->image)) {
            return (string) $item->image;
        }

        // Try description HTML
        if (empty($image) && isset($item->description)) {
            $descHtml = (string) $item->description;
            if (preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i', $descHtml, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function stripStrings($string)
    {
        $trimmedString = trim($string);
        $cleaned = preg_replace('/\s+/', ' ', $trimmedString);
        return $cleaned;
    }

    public function getTopics()
    {
        try {
            // Skip verification for embed requests
            $skipVerification = isset($_SERVER['HTTP_X_EMBED_REQUEST']) &&
                $_SERVER['HTTP_X_EMBED_REQUEST'] === 'true';

            if (!$skipVerification) {
                $user = $this->verifyToken();
                if (!$user) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'errorMessage' => 'Unauthorized']);
                    return;
                }
            }

            $topicsCacheKey = $this->getTopicsCacheKey();

            // Try to get topics from cache
            $topics = $this->getCachedFeedsData($topicsCacheKey);
            $fromCache = true;

            if (is_null($topics)) {
                // Cache miss - fetch from database
                $topics = $this->feedModel->getTopics();
                $fromCache = false;

                if ($topics) {
                    $this->cacheFeedsData($topicsCacheKey, $topics, $this->topicCacheExpiry);
                }
            }

            if (empty($topics)) {
                $this->sendErrorResponse(404, 'No topics found');
                return;
            }

            $this->sendSuccessResponse([
                'success' => true,
                'data' => $topics,
                'count' => count($topics),
                'message' => 'Topics retrieved successfully',
                'from_cache' => $fromCache
            ]);
        } catch (Exception $e) {
            $this->sendErrorResponse(500, 'Error retrieving topics: ' . $e->getMessage());
        }
    }

    /**
     * Admin method to clear feed caches (if needed)
     */


    private function sendSuccessResponse($data, $httpCode = 200)
    {
        header('Content-Type: application/json');
        http_response_code($httpCode);
        echo json_encode($data);
    }

    private function sendErrorResponse($httpCode, $message, $filterApplied = null)
    {
        header('Content-Type: application/json');
        http_response_code($httpCode);

        $response = [
            'success' => false,
            'errorMessage' => $message
        ];

        if ($filterApplied) {
            $response['filter_applied'] = $filterApplied;
        }

        echo json_encode($response);
    }
}
