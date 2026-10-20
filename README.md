Antenna for ExpressionEngine 6 and 7
========

**Antenna** is a plugin that will generate the exact, most up-to-date YouTube, Vimeo, or Wistia embed code available. With an [embed.ly](https://embed.ly/) API key, you also have access to [hundreds more video providers](https://embed.ly/providers). In addition to the video's embed code, **Antenna** also provides you with the video's title, its author, the author's YouTube/Vimeo URL, and a thumbnail. All you have to do is pass it a single URL.

You can also output various pieces of metadata about the video.

Version 3 supports ExpressionEngine 6 and 7. Older versions are still available:

- ExpressionEngine 2–5: [v2.3.2](https://github.com/vector/Antenna/releases/tag/v2.3.2)
- ExpressionEngine 1: [v1.23](https://github.com/vector/Antenna/releases/tag/v1.23)

Installation
-------

- Copy the directory `/system/user/addons/antenna` to `/system/user/addons/` in your ExpressionEngine install.
- Go to the Add-on Manager and click "Install" next to Antenna.

### Upgrading from 2.x

- Replace your existing `/system/user/addons/antenna` directory with the new one.
- Antenna now uses ExpressionEngine's built-in cache (file, Memcached, or Redis, whatever your site is configured to use), so its cache is cleared along with the rest of your site's caches. You can delete the old `/system/user/cache/antenna_urls/` directory.
- Viddler support has been removed; Viddler's oEmbed service no longer exists.

Usage
-------

	{exp:antenna url='{the_youtube_or_vimeo_url}' max_width="232" max_height="323" wmode="transparent|opaque|window"}
	    {embed_code}
	    {video_title}
	    {video_author}
	    {video_author_url}
	    {video_thumbnail}
	    {video_mediumres}
	    {video_highres}
	    {video_provider}
	    {video_ratio}
	    {video_width}
	    {video_height}
	    {video_provider_id}

	    {!-- For Vimeo Only --}
	    {video_description}

	    {if embed_code}
	        It worked! {embed_code}
	    {if:else}
	        No video to display here.
	    {/if}
	{/exp:antenna}


Set the `max_width` and/or `max_height` for whatever size your website requires. The video will be resized to be within those dimensions, and will stay at the correct proportions.

The optional `wmode` parameter is a holdover from Flash-based players. It's only useful for legacy `<embed>` code, and modern iframe players ignore it. It accepts values of transparent, opaque and window.

If used as a single tag, it returns the HTML embed/object code for the video. If used as a pair, you get access to the variables above and can use them in conditionals.

There are three image sizes available for videos: `{video_thumbnail}`, `{video_mediumres}`, and `{video_highres}`. They are not consistent across services but they should fall into rough size brackets. `{video_thumbnail}` is going to be between 100-200px wide; `{video_mediumres}` will be around 400-500px wide; and `{video_highres}` will be at least the full size of your uploaded video and could be as wide as 1280px.

All oEmbed requests are made over HTTPS, and YouTube, Vimeo, and Wistia always return HTTPS embed code. The old `force_https` parameter is no longer needed and is ignored.

### YouTube

Antenna accepts regular watch URLs, `youtu.be` short links, Shorts, live, playlist, `m.` and `music.` URLs. Embed URLs (`youtube.com/embed/…` and `youtube-nocookie.com/embed/…`) also work; Antenna looks them up as regular watch URLs because YouTube's oEmbed service rejects them.

If you only set one of `max_width` or `max_height`, Antenna still sizes the YouTube player correctly. YouTube on its own would ignore a single dimension and return a tiny 200px-tall player.

If you're using YouTube, you get access to several more parameters, such as:

- `youtube_nocookie='true'` -- Use YouTube's privacy-enhanced mode (`youtube-nocookie.com`) for the embed.
- `youtube_rel='0/1'` -- With `0`, related videos at the end come from the same channel. YouTube no longer lets you turn related videos off completely. Defaults to 1.
- `youtube_start='30'` / `youtube_end='90'` -- Start and stop playback at these many seconds into the video.
- `youtube_color='red/white'` -- Color of the progress bar.

See [this guide from Google](https://developers.google.com/youtube/player_parameters#Parameters) for the full list of parameters available for YouTube videos. Prefix each parameter with `youtube_` when adding these parameters to your Antenna template tag.

YouTube no longer honours `youtube_showinfo`, `youtube_modestbranding`, `youtube_autohide`, or `youtube_theme`. Antenna still passes them along so existing templates don't break, but they have no effect. `youtube_listType='search'` no longer works at all.

*Note*: YouTube requires embeds to send a `Referer` header. If your site sets a strict `Referrer-Policy` (such as `no-referrer` or `same-origin`), the player may show "Error 153". The embed code YouTube returns already sets `referrerpolicy="strict-origin-when-cross-origin"` on the iframe, so don't strip that attribute.

### Vimeo

Antenna accepts regular, `player.vimeo.com`, channel, group, and showcase URLs. For unlisted videos, use the full unlisted link including the hash at the end (`vimeo.com/123456/abcdef1234`), or Vimeo will return nothing.

Antenna sends your site's URL (`site_url`) as the `Referer` when it asks Vimeo for video data. That lets Vimeo return full data for videos restricted to your domain. Videos with password privacy only return limited data.

If you're using Vimeo, you get access to more parameters and one more variable:

- `vimeo_byline='true/false'` -- Shows the byline on the video. Defaults to true.
- `vimeo_title='true/false'` -- Shows the title on the video. Defaults to true.
- `vimeo_portrait='true/false'` -- Shows the user's avatar on the video. Defaults to true.
- `vimeo_autoplay='true/false'` -- Automatically start playback of the video. Most browsers only allow this when the video is also muted. Defaults to false.
- `vimeo_muted='true/false'` -- Start the video muted. Defaults to false.
- `vimeo_loop='true/false'` -- Loops the video automatically. Defaults to false.
- `vimeo_autopause='true/false'` -- Pause this video when another Vimeo video on the page starts playing. Defaults to true.
- `vimeo_background='true/false'` -- Background mode: hides all controls, autoplays muted, and loops (plan restrictions apply). Defaults to false.
- `vimeo_controls='true/false'` -- Show the player controls (plan restrictions apply). Defaults to true.
- `vimeo_dnt='true/false'` -- "Do not track": stops the player from tracking session data and setting cookies. Defaults to false.
- `vimeo_playsinline='true/false'` -- Play inline on mobile instead of going fullscreen. Defaults to true.
- `vimeo_keyboard='true/false'`, `vimeo_pip='true/false'`, `vimeo_speed='true/false'`, `vimeo_transparent='true/false'`, `vimeo_quality='720p'`, `vimeo_texttrack='en'` -- Passed straight through to the player. See [Vimeo's player parameters](https://help.vimeo.com/hc/en-us/articles/12426260232977-About-Player-Parameters).
- `vimeo_color='EFEFEF'` -- Changes the color of the player controls, and the color of the video title (if enabled). The parameter here expects a hex color code.
- `vimeo_api='true/false'` -- *Deprecated.* Only for Vimeo's old "Froogaloop" JavaScript API, which Vimeo has retired. The current [Player.js](https://github.com/vimeo/player.js) API doesn't need it.
- `{video_description}` -- The description of the video, as set in Vimeo

The plugin automatically caches returned data for one week using ExpressionEngine's cache. You can control this setting with the `cache_minutes` parameter, or set `cache_minutes` to "`0`" to disable the cache.

**NOTE** For this to work with all urls please ensure that in Channel → Settings, you have 'Automatically turn URLs and email addresses into links?' set to 'No'.

Embed.ly Support
-------

By including an [embed.ly](https://embed.ly/) API key, you have access to [hundreds more video providers](http://embed.ly/providers). If your URL is not for YouTube, Vimeo, or Wistia, **Antenna** will check embed.ly to pull the correct relevant video embed and information.

Simply include your embed.ly API key as a parameter on your `{exp:antenna}` tag like:

```
{exp:antenna url='{example_vine_url}' embedly_key="xxxxxxxxxx" max_width="232" max_height="323" wmode="transparent|opaque|window"}
	...
{/exp:antenna}
```

or set your embed.ly API key globally by adding it to ExpressionEngine's `config.php` file:

```
$config['antenna_embedly_key'] = 'xxxxxxxxxx';
```

Compatibility
-------

**Antenna** 3 is compatible with ExpressionEngine 6 and 7, and works on PHP 7 through PHP 8.x.

You must have either the `cURL` extension installed or `allow_url_fopen` enabled.

*Note for Wistia users*: Wistia doesn't return as much data as the other providers, so not all of the above variables will work. Also, you may need to contact Wistia and ask them to enable "oEmbed support" on your account.

Warranty/License
-------

There's no warranty of any kind. If you find a bug, please tell me and I may try to fix it. It's provided completely as-is; if something breaks, you lose data, or something else bad happens, the author(s) and owner(s) of this plugin are in no way responsible.

This plugin is owned by Matt Weinberg. You can modify it and use it for your own personal or commercial projects, but you can't redistribute it. EE2 and Vimeo functionality added by Adam Wiggall (@turnandface). Wmode compatibility added by Tom Davies (@metadaptive).
