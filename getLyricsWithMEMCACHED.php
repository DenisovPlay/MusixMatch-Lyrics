<?php

class MemcachedCache {
    private $memcached;

    public function __construct() {
        $this->memcached = new Memcached();
        $this->memcached->addServer('127.0.0.1', 11211);
    }

    public function get($key) {
        return $this->memcached->get($key);
    }

    public function set($key, $value, $expiration = 600) {
        $this->memcached->set($key, $value, $expiration);
    }

    public function delete($key) {
        $this->memcached->delete($key);
    }
}

class Musix {
    private $cache;
    private $token_url = 'https://apic-desktop.musixmatch.com/ws/1.1/token.get?app_id=web-desktop-app-v1.0';
    private $search_term_url = 'https://apic-desktop.musixmatch.com/ws/1.1/macro.search?app_id=web-desktop-app-v1.0&page_size=5&page=1&s_track_rating=desc&quorum_factor=1.0';
    private $lyrics_url = 'https://apic-desktop.musixmatch.com/ws/1.1/track.subtitle.get?app_id=web-desktop-app-v1.0&subtitle_format=lrc';
    private $lyrics_alternative = 'https://apic-desktop.musixmatch.com/ws/1.1/macro.subtitles.get?format=json&namespace=lyrics_richsynched&subtitle_format=mxm&app_id=web-desktop-app-v1.0';

    public function __construct() {
        $this->cache = new \MemcachedCache();
    }

    function get($url): string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'authority: apic-desktop.musixmatch.com',
            'cookie: AWSELBCORS=0; AWSELB=0;'
        ]);
        curl_setopt($ch, CURLOPT_URL, $url);
        $result = curl_exec($ch);

        return $result;
    }

    function getToken(): void {
        $cachedToken = $this->cache->get('musix_token');
        if ($cachedToken) {
            return;
        }

        $result = $this->get($this->token_url);
        if (!$result) {
            throw new \Exception('Failed to retrieve the access token.');
        }

        $token_json = json_decode($result, true);
        if (!$token_json['message']['header']['status_code'] == 200) {
            throw new \Exception($result);
        }

        $current_time = time();
        $new_token = $token_json["message"]["body"]["user_token"];
        $expiration_time = $current_time + 600;
        $token_data = ["user_token" => $new_token, "expiration_time" => $expiration_time];

        $this->cache->set('musix_token', json_encode($token_data), 600);
    }

    function checkTokenExpire(): void {
        $cachedToken = $this->cache->get('musix_token');
        $timeNow = time();

        if ($cachedToken) {
            $timeLeft = json_decode($cachedToken, true)['expiration_time'];
        }
        if (!$cachedToken || $timeLeft < $timeNow) {
            $this->getToken();
        }
    }

    function getLyrics($track_id): string {
        $cacheKey = 'lyrics_' . $track_id;
        $cachedLyrics = $this->cache->get($cacheKey);
        if ($cachedLyrics) {
            return $cachedLyrics;
        }

        $json = $this->cache->get('musix_token');
        $token = json_decode($json, true)['user_token'];
        $formatted_url = $this->lyrics_url . '&track_id=' . $track_id . '&usertoken=' . $token;
        $result = $this->get($formatted_url);

        $lyrics = json_decode($result, true)['message']['body']['subtitle']['subtitle_body'];
        $this->cache->set($cacheKey, $lyrics, 600);

        return $lyrics;
    }

    function getLyricsAlternative($title, $artist, $duration = null): string {
        $cacheKey = 'lyrics_alt_' . $title . '_' . $artist . '_' . $duration;
        $cachedLyrics = $this->cache->get($cacheKey);
        if ($cachedLyrics) {
            return $cachedLyrics;
        }

        $json = $this->cache->get('musix_token');
        $token = json_decode($json, true)['user_token'];
        if($duration != null) {
           $formatted_url = $this->lyrics_alternative . '&usertoken=' . $token . '&q_album=&q_artist=' . $artist . '&q_artists=' . $artist . '&q_track=' . $title . '&q_duration=' . $duration . '&f_subtitle_length=' . $duration;
        } else {
           $formatted_url = $this->lyrics_alternative . '&usertoken=' . $token . '&q_album=&q_artist=' . $artist . '&q_artists=' . $artist . '&q_track=' . $title;
        }

        $result = $this->get($formatted_url);    
        $lyrics = json_decode($result, true);   
        $yeee = $lyrics['message']['body']['macro_calls']['track.subtitles.get'];    
        $track2 = $yeee['message']['body']['subtitle_list'][0]['subtitle']['subtitle_body'];
        $lyricsText = $this->getLrcLyrics($track2);

        $this->cache->set($cacheKey, $lyricsText, 600);

        return $lyricsText;
    }

    function searchTrack($query): string {
        $cacheKey = 'search_' . $query;
        $cachedTrackId = $this->cache->get($cacheKey);
        if ($cachedTrackId) {
            return $cachedTrackId;
        }

        $json = $this->cache->get('musix_token');
        $token = json_decode($json, true)['user_token'];
        $formatted_url = $this->search_term_url . '&q=' . $query . '&usertoken=' . $token;
        $result = $this->get($formatted_url);

        $listResult = json_decode($result, true);
        if (!isset($listResult['message']['body']['macro_result_list']['track_list'])) {
          throw new \Exception($result);
        }

        $track_id = $listResult['message']['body']['macro_result_list']['track_list'][0]['track']['track_id'];
        $this->cache->set($cacheKey, $track_id, 600);

        return $track_id;
    }

    function getLrcLyrics($lyrics): string {
        $data = json_decode($lyrics, true);
        $lrc = '';
        if(isset($data)) {
           foreach ($data as $item) {
              $minutes = $item['time']['minutes'];
              $seconds = $item['time']['seconds'];
              $hundredths = $item['time']['hundredths'];
              $text = empty($item['text']) ? '♪' : $item['text'];
              $lrc .= sprintf("[%02d:%02d.%02d]%s\n", $minutes, $seconds, $hundredths, $text);
           }
        }
        return $lrc;
    }
}

$type = $_GET['type'] ?? 'default';
$musix = new Musix();
$musix->checkTokenExpire();

if($type === 'default') {
    $query = urlencode($_GET['q'] ?? '');
    $track_id = $musix->searchTrack($query);
    if($track_id != null) {
        $response = $musix->getLyrics($track_id);
        if(isset($response)) {
            echo $response;
        } else {
            echo json_encode(["error" => "Lyrics seems like doesn't exist.", "isError" => true]);
        }
    } else {
        echo json_encode(["error" => "Track id seems like doesn't exist.", "isError" => true]);
    }
} else {
    $title = urlencode($_GET['t'] ?? '');
    $artist = urlencode($_GET['a'] ?? '');
    $duration = $_GET['d'] ?? null;
    if($duration != null) {
        $lyrics = $musix->getLyricsAlternative($title, $artist, convertDuration($duration));
    } else {
        $lyrics = $musix->getLyricsAlternative($title, $artist);
    }
    if($lyrics != null) {
        echo $lyrics;
    } else {
        echo json_encode(["error" => "Lyrics not found.", "isError" => true]);
    }
}

function convertDuration($time): int {
    sscanf($time, "%d:%d", $minutes, $seconds);
    return $minutes * 60 + $seconds;
}

?>
