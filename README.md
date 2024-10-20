# MusixMatch-Lyrics (UPDATED)
Easily get music Synced Lyrics by using MusixMatch API written in PHP!

### Examples

#### Using Alternative Method

To retrieve lyrics using the alternative method, use the `/getLyricsMusix.php` endpoint with the following parameters:
- `t`: The title of the song
- `a`: The artist's name
- `d`: The duration of the song (you can specify the duration in either `mm:ss` format or in total seconds)
- `type`: The type for search the lyrics, `alternative` for search it by using metadata and `default` is only using song title and artist name

Example:

```
https://yourserver.com/getLyricsMusix.php?t=Hope&a=XXXTENTACION&d=1:50&type=alternative
```

#### Using Default Method

To retrieve lyrics using the default method, use the `/getLyricsMusix.php` endpoint with the following parameter:
- `q`: The query string containing the song title and artist name
- `type`: The type for search the lyrics, `alternative` for search it by using metadata and `default` is only using song title and artist name

Example:

```
https://yourserver.com/getLyricsMusix.php?q=Hope%20XXXTentacion&type=default
```

---

### Response:

```
[00:02.80]Yeah
[00:05.56]♪
[00:11.06]Rest in peace to all the kids that lost their lives in the Parkland shooting
[00:13.63]This song is dedicated to you
```

### How to Use

Choose getLyricsWithMEMCACHED.php or getLyricsWithoutMEMCACHED.php put it on your php server and use!
