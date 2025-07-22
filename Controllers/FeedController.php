<?php

require_once './Models/Feed.php';
require_once './Controllers/BaseController.php';
require_once './Utils/normalizeDate.php';

class FeedController extends BaseController
{
    private $db;
    private $feedModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->feedModel = new Feed($db);
    }


    public function getFeeds()
    {

        $skipVerification = false;

        if (isset($_SERVER['HTTP_X_EMBED_REQUEST']) && $_SERVER['HTTP_X_EMBED_REQUEST'] === 'true') {
            $skipVerification = true;
        }
        if (!$skipVerification) {

            $user = $this->verifyToken();
        } else {
            $user = null;
        }

        // $data = json_decode(file_get_contents("php://input"), true);
        $options = [
            'rssFeedLink' => !empty($_GET['rssFeed']) ? $_GET["rssFeed"] : null,
            'search' => !empty($_GET['search']) ?  htmlspecialchars($_GET["search"]) : null,
            'topic' => !empty($_GET['topic']) ?  htmlspecialchars($_GET["topic"]) : null,
            'source' => !empty($_GET['source']) ?  htmlspecialchars($_GET["source"]) : null,
            'author' => !empty($_GET['author']) ?  htmlspecialchars($_GET["author"]) : null,
            'limit' => !empty($_GET['limit']) ? htmlspecialchars($_GET["limit"]) : null
        ];
        try {
            if (is_null($options['rssFeedLink'])) {
                $feeds = $this->feedModel->getFeeds($options);

                if (!$feeds) {
                    $allFeeds = $this->feedModel->getFeeds($options, false);
                    if (!$allFeeds) {
                        header('Content-Type: application/json');

                        echo json_encode([
                            "success" => false,
                            'filter_applied' => $options,
                            "message" => "Unable to find feeds for you."
                        ]);
                        return;
                    } else {
                        header('Content-Type: application/json');

                        echo json_encode([
                            'success' => true,
                            'data' => $allFeeds,
                            'count' => count($allFeeds),
                            'filter_applied' => $options,
                            'message' => 'No results found for your search. Showing all feeds instead.',
                            'is_fallback' => true
                        ]);
                    }
                } else {
                    header('Content-Type: application/json');

                    echo json_encode([
                        'success' => true,
                        'data' => $feeds,
                        'count' => count($feeds),
                        'filter_applied' => $options,
                        'message' => 'Filtered feeds retrieved successfully',
                        'is_fallback' => false
                    ]);
                }
            } else {
                $feeds = $this->parseRSSFeeds($options['rssFeedLink']);
                if (!$feeds) {
                    header('Content-Type: application/json');
                    http_response_code(400);
                    echo json_encode([
                        "success" => false,
                        'filter_applied' => $options,
                        "message" => "Unable to find feeds for you."
                    ]);
                    return;
                } else {
                    header('Content-Type: application/json');
                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'data' => $feeds,
                        'count' => count($feeds),
                        'filter_applied' => $options,
                        'message' => 'Filtered feeds retrieved successfully',
                        'is_fallback' => false
                    ]);
                }
            }
        } catch (Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'errorMessage' => 'Error filtering feeds: ' . $e->getMessage()
            ]);
        }
    }

    private function parseRSSFeeds($rssURL)
    {
        $dcNS = 'http://purl.org/dc/elements/1.1/';

        try {
            libxml_use_internal_errors(true);
            $rss = @simplexml_load_file($rssURL);

            if (!$rss || !isset($rss->channel->item)) {
                return [
                    "success" => false,
                    "errorMessage" => "Invalid or empty RSS feed."
                ];
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
            return $feedData;
        } catch (Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'errorMessage' => 'Error filtering feeds: ' . $e->getMessage()
            ]);
        }
    }
    private function extractMediaImage($item)
    {
        $mediaNS = "http://search.yahoo.com/mrss/";
        $media = $item->children($mediaNS);

        if (isset($media->thumbnail) && count($media->thumbnail) > 0) {
            return (string)$media->thumbnail[0]->attributes()->url;
        }

        if (empty($image) && isset($media->group->thumbnail[0]['url'])) {
            return (string)$media->group->thumbnail[0]['url'];
        }

        if (empty($image) && isset($media->group->content[0]['url'])) {
            return (string)$media->group->content[0]['url'];
        }

        if (empty($image) && isset($media->content[0]['url'])) {
            return (string)$media->content[0]['url'];
        }

        if (empty($image) && isset($media->content) && $media->content->attributes()->url) {
            return (string)$media->content->attributes()->url;
        }

        if (empty($image) && isset($item->{'content:encoded'})) {
            $html = (string) $item->{'content:encoded'};
            if (preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i', $html, $matches)) {
                $image = $matches[1];
            }
        }

        if (empty($image) && isset($item->enclosure)) {
            $enclosure = $item->enclosure;
            $type = (string) $enclosure['type'];
            if (str_starts_with($type, 'image/')) {
                $image = (string) $enclosure['url'];
            }
        }

        if (empty($image) && isset($item->image)) {
            $image = (string) $item->image;
        }

        if (empty($image) && isset($item->description)) {
            $descHtml = (string) $item->description;
            if (preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i', $descHtml, $matches)) {
                $image = $matches[1];
            }
        }

        return null;
    }

    private function stripStrings($string){
        $trimmedString = trim($string);
        $cleaned = preg_replace('/\s+/', ' ', $trimmedString);
        return $cleaned;
    }

    public function getTopics()
    {
        $user = $this->verifyToken();
        try {
            $topics = $this->feedModel->getTopics();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $topics,
                'count' => count($topics),
                'message' => 'Topics retrieved successfully'
            ]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'errorMessage' => 'Error retrieving topics: ' . $e->getMessage()
            ]);
        }
    }
}
