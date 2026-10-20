<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Antenna Plugin
 * Copyright Matt Weinberg, www.VectorMediaGroup.com
 */

/**
 * The Antenna plugin will generate the embed code for a single
 * YouTube, Vimeo, or Wistia clip (or anything Embedly supports).
 * It will also give you back the author, their URL, the video
 * title, thumbnails, and more.
 *
 * @package Antenna
 */

class Antenna
{
	public $return_data = '';

	// Cache lifetime in minutes (default is 1 week)
	public $refresh_cache = 10080;

	// Prefix for ee()->cache keys. The leading slash makes the cache global rather than per-site.
	const CACHE_PREFIX = '/antenna/';

	// oEmbed field => template variable
	private $plugin_vars = array(
		'title'         => 'video_title',
		'html'          => 'embed_code',
		'author_name'   => 'video_author',
		'author_url'    => 'video_author_url',
		'thumbnail_url' => 'video_thumbnail',
		'medres_url'    => 'video_mediumres',
		'highres_url'   => 'video_highres',
		'description'   => 'video_description',
		'provider'      => 'video_provider',
		'width'         => 'video_width',
		'height'        => 'video_height',
		'video_id'      => 'video_provider_id'
	);

	// Optional YouTube player parameters, passed as youtube_{name}
	private $youtube_options = array(
		'autohide',
		'autoplay',
		'cc_lang_pref',
		'cc_load_policy',
		'color',
		'controls',
		'disablekb',
		'enablejsapi',
		'end',
		'fs',
		'hl',
		'iv_load_policy',
		'list',
		'listType',
		'loop',
		'modestbranding',
		'mute',
		'origin',
		'playlist',
		'playsinline',
		'rel',
		'showinfo',
		'start',
		'theme'
	);

	public function __construct()
	{
		$tagdata = (string) ee()->TMPL->tagdata;

		$video_data = array();

		foreach ($this->plugin_vars as $var)
		{
			$video_data[$var] = false;
		}

		$video_data['video_ratio'] = false;

		// Deal with the parameters
		$video_url = trim(html_entity_decode((string) ee()->TMPL->fetch_param('url', '')));
		$wmode = ee()->TMPL->fetch_param('wmode', '');

		// Check for embed.ly support
		$embedly_key = ee()->TMPL->fetch_param('embedly_key', false);
		if ( ! $embedly_key && ee()->config->item('antenna_embedly_key'))
		{
			$embedly_key = ee()->config->item('antenna_embedly_key');
		}

		// Cache can be disabled by setting 0 as the cache_minutes param
		$cache_minutes = ee()->TMPL->fetch_param('cache_minutes');
		if ($cache_minutes !== FALSE && is_numeric($cache_minutes))
		{
			$this->refresh_cache = (int) $cache_minutes;
		}

		$provider = $this->_detect_provider($video_url);

		$url = $this->_build_oembed_url($provider, $video_url, $embedly_key, $wmode);

		$video_info = $url ? $this->_fetch_video_info($url) : false;

		if ( ! $video_info)
		{
			$this->return_data = ($tagdata === '') ? '' : ee()->TMPL->parse_variables($tagdata, array($video_data));
			return;
		}

		// Inject wmode if required (only matters for legacy <embed> players)
		if (in_array($wmode, array('transparent', 'opaque', 'window'), true))
		{
			$video_info['html'] = $this->_inject_wmode($video_info['html'], $wmode);
		}

		// Inject YouTube parameters if required
		if ($provider == 'youtube')
		{
			$youtube_params = array();

			foreach ($this->youtube_options as $option)
			{
				$param = ee()->TMPL->fetch_param('youtube_' . $option, null);
				if ( ! is_null($param))
				{
					$youtube_params[$option] = $param;
				}
			}

			if ( ! empty($youtube_params))
			{
				$video_info['html'] = $this->_merge_into_src($video_info['html'], $youtube_params);
			}
		}

		$video_info = $this->_add_provider_details($provider, $video_url, $video_info);

		// Handle a single tag
		if ($tagdata === '')
		{
			$this->return_data = $video_info['html'];
			return;
		}

		// Handle a tag pair with tagdata
		foreach ($this->plugin_vars as $key => $var)
		{
			if (isset($video_info[$key]) && $video_info[$key] !== '')
			{
				$video_data[$var] = $video_info[$key];
			}
		}

		if ( ! empty($video_info['width']) && ! empty($video_info['height']) && is_numeric($video_info['width']) && is_numeric($video_info['height']))
		{
			$video_data['video_ratio'] = (float) $video_info['width'] / (float) $video_info['height'];
		}

		$this->return_data = ee()->TMPL->parse_variables($tagdata, array($video_data));
	}

	/**
	 * Figure out which provider a video URL belongs to
	 *
	 * @param string $video_url
	 * @return string|bool youtube, vimeo, wistia, or FALSE if unrecognized
	 */
	private function _detect_provider($video_url)
	{
		if ($video_url === '')
		{
			return FALSE;
		}

		// Allow URLs without a scheme, e.g. "youtu.be/abc" or "//vimeo.com/123"
		$parse_url = preg_match('#^[a-z][a-z0-9+.-]*://#i', $video_url) ? $video_url : 'https://' . ltrim($video_url, '/');
		$host = strtolower((string) parse_url($parse_url, PHP_URL_HOST));

		if (preg_match('/(^|\.)(youtube\.com|youtu\.be|youtube-nocookie\.com)$/', $host))
		{
			return 'youtube';
		}

		if (preg_match('/(^|\.)vimeo\.com$/', $host))
		{
			return 'vimeo';
		}

		if (preg_match('/(^|\.)(wistia\.com|wistia\.net|wi\.st)$/', $host))
		{
			return 'wistia';
		}

		return FALSE;
	}

	/**
	 * Build the oEmbed request URL for a video
	 *
	 * @param string|bool $provider
	 * @param string $video_url
	 * @param string|bool $embedly_key
	 * @param string $wmode
	 * @return string|bool The request URL, or FALSE if there's no way to look up this video
	 */
	private function _build_oembed_url($provider, $video_url, $embedly_key, $wmode)
	{
		if ($video_url === '')
		{
			return FALSE;
		}

		$query = array();

		switch ($provider)
		{
			case 'youtube':
				$endpoint = 'https://www.youtube.com/oembed';
				$query['format'] = 'xml';
				break;
			case 'vimeo':
				$endpoint = 'https://vimeo.com/api/oembed.xml';
				break;
			case 'wistia':
				$endpoint = 'https://fast.wistia.com/oembed.xml';
				break;
			default:
				if ( ! $embedly_key)
				{
					return FALSE;
				}

				$endpoint = 'https://api.embedly.com/1/oembed';
				$query['format'] = 'xml';
				$query['key'] = $embedly_key;
		}

		$query['url'] = $video_url;

		$max_width = (int) ee()->TMPL->fetch_param('max_width');
		$max_height = (int) ee()->TMPL->fetch_param('max_height');

		if ($max_width > 0)
		{
			$query['maxwidth'] = $max_width;
		}

		if ($max_height > 0)
		{
			$query['maxheight'] = $max_height;
		}

		if ( ! empty($wmode))
		{
			$query['wmode'] = $wmode;
		}

		// Some optional Vimeo parameters
		if ($provider == 'vimeo')
		{
			if (ee()->TMPL->fetch_param('vimeo_byline') == 'false') $query['byline'] = 'false';
			if (ee()->TMPL->fetch_param('vimeo_title') == 'false') $query['title'] = 'false';
			if (ee()->TMPL->fetch_param('vimeo_autoplay') == 'true') $query['autoplay'] = 'true';
			if (ee()->TMPL->fetch_param('vimeo_portrait') == 'false') $query['portrait'] = '0';
			if (ee()->TMPL->fetch_param('vimeo_api') == 'true') $query['api'] = '1';
			if (ee()->TMPL->fetch_param('vimeo_loop') == 'true') $query['loop'] = 'true';

			$vimeo_color = preg_replace('/[^0-9a-f]/i', '', (string) ee()->TMPL->fetch_param('vimeo_color', ''));
			if ($vimeo_color !== '')
			{
				$query['color'] = $vimeo_color;
			}
		}

		return $endpoint . '?' . http_build_query($query, '', '&');
	}

	/**
	 * Get the oEmbed data for a request URL, from the cache if possible
	 *
	 * @param string $url The oEmbed request URL
	 * @return array|bool Array of oEmbed fields, or FALSE on failure
	 */
	private function _fetch_video_info($url)
	{
		$cache_key = self::CACHE_PREFIX . md5($url);

		if ($this->refresh_cache > 0)
		{
			$cached = ee()->cache->get($cache_key);

			if (is_array($cached) && ! empty($cached['html']))
			{
				return $cached;
			}
		}

		list($response, $status) = $this->curl($url);

		if ( ! $response || $status != 200)
		{
			return FALSE;
		}

		$video_info = $this->_parse_oembed_xml($response);

		if ( ! $video_info)
		{
			return FALSE;
		}

		if ($this->refresh_cache > 0)
		{
			ee()->cache->save($cache_key, $video_info, $this->refresh_cache * 60);
		}

		return $video_info;
	}

	/**
	 * Turn an oEmbed XML response into a flat array of strings
	 *
	 * @param string $xml
	 * @return array|bool FALSE if the response isn't usable oEmbed XML
	 */
	private function _parse_oembed_xml($xml)
	{
		$previous = libxml_use_internal_errors(TRUE);
		$oembed = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		if ($oembed === FALSE)
		{
			return FALSE;
		}

		$video_info = array();

		foreach ($oembed->children() as $key => $value)
		{
			$video_info[$key] = trim((string) $value);
		}

		if (empty($video_info['html']))
		{
			return FALSE;
		}

		return $video_info;
	}

	/**
	 * Add the provider name, video ID, and consistently sized thumbnails
	 *
	 * @param string|bool $provider
	 * @param string $video_url
	 * @param array $video_info
	 * @return array
	 */
	private function _add_provider_details($provider, $video_url, $video_info)
	{
		$thumbnail = isset($video_info['thumbnail_url']) ? $video_info['thumbnail_url'] : '';

		switch ($provider)
		{
			case 'youtube':
				$video_info['provider'] = 'youtube';

				if ($thumbnail !== '')
				{
					$video_info['highres_url'] = str_replace('hqdefault', 'maxresdefault', $thumbnail);
					$video_info['medres_url'] = $thumbnail;
					$video_info['thumbnail_url'] = str_replace('hqdefault', 'mqdefault', $thumbnail);
				}

				if (preg_match('#(?:youtu\.be/|/embed/|/shorts/|/live/|/v/|[?&]v=)([A-Za-z0-9_-]{11})#', $video_url, $matches))
				{
					$video_info['video_id'] = $matches[1];
				}
				break;

			case 'vimeo':
				$video_info['provider'] = 'vimeo';

				// Thumbnails look like .../12345-abc-d_640?region=us or .../12345_640.jpg
				if ($thumbnail !== '')
				{
					$size_pattern = '/_\d+(x\d+)?(?=(\.[a-z]+)?(\?|$))/i';
					$video_info['highres_url'] = preg_replace($size_pattern, '_1280', $thumbnail);
					$video_info['medres_url'] = preg_replace($size_pattern, '_640', $thumbnail);
					$video_info['thumbnail_url'] = preg_replace($size_pattern, '_295', $thumbnail);
				}
				break;

			case 'wistia':
				$video_info['provider'] = 'wistia';

				// Wistia sizes thumbnails with ?image_crop_resized=WxH; without it you get the original
				if ($thumbnail !== '')
				{
					$base = strtok($thumbnail, '?');
					$video_info['highres_url'] = $base;
					$video_info['medres_url'] = $base . '?image_crop_resized=640x360';
					$video_info['thumbnail_url'] = $base . '?image_crop_resized=240x135';
				}

				if (preg_match('#/(?:embed/iframe|medias)/([A-Za-z0-9]+)#', $video_info['html'] . ' ' . $video_url, $matches))
				{
					$video_info['video_id'] = $matches[1];
				}
				break;
		}

		return $video_info;
	}

	/**
	 * Merge query string arguments into the first src="" in some embed HTML,
	 * replacing any arguments the provider already set
	 *
	 * @param string $html
	 * @param array $args
	 * @return string
	 */
	private function _merge_into_src($html, $args)
	{
		return preg_replace_callback('/src="([^"]*)"/i', function ($matches) use ($args) {
			// Some providers (Vimeo) entity-encode the ampersands in their src
			$amp = (strpos($matches[1], '&amp;') !== FALSE) ? '&amp;' : '&';
			$src = str_replace('&amp;', '&', $matches[1]);

			$fragment = '';
			if (($hash_pos = strpos($src, '#')) !== FALSE)
			{
				$fragment = substr($src, $hash_pos);
				$src = substr($src, 0, $hash_pos);
			}

			$query = array();
			if (($query_pos = strpos($src, '?')) !== FALSE)
			{
				foreach (explode('&', substr($src, $query_pos + 1)) as $pair)
				{
					if ($pair === '') continue;
					$parts = explode('=', $pair, 2);
					$query[rawurldecode($parts[0])] = $pair;
				}
				$src = substr($src, 0, $query_pos);
			}

			foreach ($args as $key => $value)
			{
				$query[$key] = rawurlencode($key) . '=' . rawurlencode($value);
			}

			return 'src="' . $src . '?' . implode($amp, $query) . $fragment . '"';
		}, $html, 1);
	}

	/**
	 * Inject wmode into <embed>/<object> or <iframe> embed code
	 *
	 * @param string $html
	 * @param string $wmode
	 * @return string
	 */
	private function _inject_wmode($html, $wmode)
	{
		if (stripos($html, '<iframe') !== FALSE)
		{
			return $this->_merge_into_src($html, array('wmode' => $wmode));
		}

		$embed_pos = stripos($html, '<embed');

		if ($embed_pos === FALSE)
		{
			return $html;
		}

		$param_str = '<param name="wmode" value="' . $wmode . '"></param>';
		$embed_str = ' wmode="' . $wmode . '" ';

		$html = substr($html, 0, $embed_pos) . $param_str . substr($html, $embed_pos);
		$embed_pos = stripos($html, '<embed') + 6;

		return substr($html, 0, $embed_pos) . $embed_str . substr($html, $embed_pos);
	}

	/**
	 * Fetch a URL with cURL, falling back to file_get_contents
	 *
	 * @param string $vid_url The oEmbed request URL
	 * @return array Response body (or FALSE) and HTTP status code
	 */
	public function curl($vid_url)
	{
		$user_agent = 'ExpressionEngine Antenna/' . (defined('APP_VER') ? APP_VER : '');

		// Do we have curl?
		if (function_exists('curl_init'))
		{
			$curl = curl_init();

			// Our cURL options
			$options = array(
				CURLOPT_URL            => $vid_url,
				CURLOPT_RETURNTRANSFER => TRUE,
				CURLOPT_FOLLOWLOCATION => TRUE,
				CURLOPT_MAXREDIRS      => 3,
				CURLOPT_CONNECTTIMEOUT => 10,
				CURLOPT_TIMEOUT        => 15,
				CURLOPT_USERAGENT      => $user_agent
			);

			curl_setopt_array($curl, $options);

			$video_info = curl_exec($curl);
			$video_header = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

			// curl_close() is a no-op as of PHP 8 and deprecated in 8.5
			if (PHP_VERSION_ID < 80000)
			{
				curl_close($curl);
			}
		}
		// Do we have fopen?
		elseif (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN))
		{
			$context = stream_context_create(array(
				'http' => array(
					'timeout'       => 15,
					'user_agent'    => $user_agent,
					'ignore_errors' => TRUE
				)
			));

			$video_info = @file_get_contents($vid_url, FALSE, $context);
			$video_header = 0;

			$response_headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : (isset($http_response_header) ? $http_response_header : null);

			if (is_array($response_headers))
			{
				// Use the last status line, in case of redirects
				foreach ($response_headers as $header)
				{
					if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches))
					{
						$video_header = (int) $matches[1];
					}
				}
			}
		}
		else
		{
			$video_info = FALSE;
			$video_header = 0;
		}

		return array($video_info, $video_header);
	}
}
