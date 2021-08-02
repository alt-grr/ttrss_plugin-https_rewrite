<?php

class https_rewrite extends Plugin {

	const BACKEND_URL = 'backend.php?op=pluginhandler&method=redirect&plugin=https_rewrite';

	private $host;

	function about() {
		return array("1.0.0",
			"Proxies requests to all non-HTTPS images. Based on af_refspoof plugin.",
			"kuc");
	}

	function api_version() {
		return 2;
	}

	function init($host) {

		if (!class_exists('PhCURL')) {
			require_once ("PhCURL.php");
		}

		$this->host = $host;
		$host->add_hook($host::HOOK_RENDER_ARTICLE_CDM, $this);
		$host->add_hook($host::HOOK_RENDER_ENCLOSURE, $this);
	}

	function hook_render_article_cdm($article) {

		$backendURL = self::BACKEND_URL;

		$doc = new DOMDocument();
		@$doc->loadHTML($article['content']);
		if (!$doc) {
			return $article;
		}

		$xpath = new DOMXPath($doc);
		$entries = $xpath->query("(//img[starts-with(@src,'http://')])");
		$entry = null;

		foreach ($entries as $entry){
			$origSrc = $entry->getAttribute("src");
			if ($origSrcSet = $entry->getAttribute("srcset")) {
				$srcSet = preg_replace_callback('#([^\s]+://[^\s]+)#', function ($m) use ($backendURL, $article) {
					return $backendURL . '&url=' . urlencode($m[0]) . '&ref=' . urlencode($article['link']);
				}, $origSrcSet);

				$entry->setAttribute("srcset", $srcSet);
			}
			$url = $backendURL . '&url=' . urlencode($origSrc) . '&ref=' . urlencode($article['link']);
			$entry->setAttribute("src", $url);
		}
		$article["content"] = $doc->saveXML();

		return $article;
	}

	function hook_render_enclosure($entry, $hide_images) {

		$rv = "";

		// Adapted from functions2.php -> format_article_enclosures()
		if (preg_match("/image/", $entry["type"]) ||
			preg_match("/\.(jpg|png|gif|bmp)/i", $entry["filename"])) {

			if (!$hide_images) {
				$encsize = '';
				if ($entry['height'] > 0)
					$encsize .= ' height="' . intval($entry['height']) . '"';
				if ($entry['width'] > 0)
					$encsize .= ' width="' . intval($entry['width']) . '"';
				$rv .= "<p><img
				alt=\"".htmlspecialchars($entry["filename"])."\"
				src=\"" . self::BACKEND_URL . '&url=' . urlencode($entry["url"]) . "\"
				" . $encsize . " /></p>";
			} else {
				$rv .= "<p><a target=\"_blank\"
				href=\"".htmlspecialchars($entry["url"])."\"
				>" .htmlspecialchars($entry["url"]) . "</a></p>";
			}

			if ($entry['title']) {
				$rv.= "<div class=\"enclosure_title\">${entry['title']}</div>";
			}
		}

		return $rv;
	}

	function redirect() {

		$client = new PhCURL($_REQUEST["url"]);
		$client->loadCommonSettings();
		$client->enableHeaderInOutput(false);
		$client->setReferer($_REQUEST["ref"]);
		$client->setUserAgent();

		$client->GET();
		ob_end_clean();

		header("Content-Type: ". $client->getContentType());
		header("Cache-Control: public, max-age=604800");
		header_remove("Expires");
		header_remove("Pragma");
		echo $client->getData();
		exit;
	}
}
