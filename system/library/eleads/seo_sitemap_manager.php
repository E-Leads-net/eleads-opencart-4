<?php
class EleadsSeoSitemapManager {
	private $registry;
	private $config;
	private $loader;

	public function __construct($registry) {
		$this->registry = $registry;
		$this->config = $registry->get('config');
		$this->loader = $registry->get('load');
	}

	public function sync(bool $enabled, string $api_key, array $settings): void {
		$path = $this->getSitemapPath();
		if ($enabled) {
			$slugs = $this->fetchSeoSlugs($api_key);
			$base_url = $this->getSeoBaseUrl($settings);
			$dir = dirname($path);
			if (!is_dir($dir)) {
				@mkdir($dir, 0755, true);
			}
			$content = $this->buildSeoSitemapXml($base_url, $slugs);
			@file_put_contents($path, $content);
		} elseif (is_file($path)) {
			@unlink($path);
		}
	}

	private function getSitemapPath(): string {
		$root = rtrim(dirname(DIR_CATALOG), '/\\');
		return $root . '/e-search/sitemap.xml';
	}

	private function getSeoBaseUrl(array $settings): string {
		$url = isset($settings['module_eleads_shop_url']) ? trim((string)$settings['module_eleads_shop_url']) : '';
		if ($url !== '') {
			return rtrim($url, '/');
		}
		if (defined('HTTPS_CATALOG') && HTTPS_CATALOG) {
			return rtrim(HTTPS_CATALOG, '/');
		}
		if (defined('HTTP_CATALOG') && HTTP_CATALOG) {
			return rtrim(HTTP_CATALOG, '/');
		}
		$ssl = (string)$this->config->get('config_ssl');
		return $ssl !== '' ? rtrim($ssl, '/') : rtrim((string)$this->config->get('config_url'), '/');
	}

	private function fetchSeoSlugs(string $api_key): array {
		$api_key = trim($api_key);
		if ($api_key === '') {
			return [];
		}
		require_once DIR_EXTENSION . 'eleads/system/library/eleads/api_routes.php';
		$ch = curl_init();
		if ($ch === false) {
			return [];
		}
		$headers = [
			'Authorization: Bearer ' . $api_key,
			'Accept: application/json',
		];
		curl_setopt($ch, CURLOPT_URL, \EleadsApiRoutes::SEO_SLUGS);
		curl_setopt($ch, CURLOPT_HTTPGET, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
		curl_setopt($ch, CURLOPT_TIMEOUT, 6);
		$response = curl_exec($ch);
		if ($response === false) {
			curl_close($ch);
			return [];
		}
		$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($httpCode < 200 || $httpCode >= 300) {
			return [];
		}
		$data = json_decode($response, true);
		if (!is_array($data) || empty($data['slugs']) || !is_array($data['slugs'])) {
			return [];
		}

		$items = [];
		foreach ($data['slugs'] as $row) {
			if (is_string($row)) {
				$slug = trim($row);
				if ($slug !== '') {
					$items[] = ['slug' => $slug, 'lang' => ''];
				}
				continue;
			}
			if (!is_array($row)) {
				continue;
			}
			$slug = isset($row['slug']) ? trim((string)$row['slug']) : '';
			$lang = isset($row['lang']) ? trim((string)$row['lang']) : '';
			if ($slug === '') {
				continue;
			}
			$items[] = ['slug' => $slug, 'lang' => $this->normalizeFeedLang($lang)];
		}

		return $items;
	}

	private function buildSeoSitemapXml(string $base_url, array $slugs): string {
		$base_url = rtrim($base_url, '/');
		$lang_map = $this->getSeoUrlLangMap();
		$rows = [];
		foreach ($slugs as $item) {
			$slug = '';
			$api_lang = '';
			if (is_array($item)) {
				$slug = trim(isset($item['slug']) ? (string)$item['slug'] : '');
				$api_lang = $this->normalizeFeedLang(isset($item['lang']) ? (string)$item['lang'] : '');
			} else {
				$slug = trim((string)$item);
			}
			if ($slug === '') {
				continue;
			}
			if ($api_lang === '') {
				$api_lang = $this->normalizeFeedLang((string)$this->config->get('config_language'));
			}
			$url_lang = $lang_map[$api_lang] ?? $api_lang;
			$loc = $base_url . '/' . rawurlencode($url_lang) . '/e-search/' . rawurlencode($slug);
			$rows[] = '  <url><loc>' . htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') . '</loc></url>';
		}
		$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
		if ($rows) {
			$xml .= implode("\n", $rows) . "\n";
		}
		$xml .= "</urlset>\n";
		return $xml;
	}

	private function getSeoUrlLangMap(): array {
		$this->loader->model('localisation/language');
		$model = $this->registry->get('model_localisation_language');
		$map = [];

		foreach ((array)$model->getLanguages() as $language) {
			if (isset($language['status']) && !$language['status']) {
				continue;
			}
			$code = strtolower(isset($language['code']) ? (string)$language['code'] : '');
			$normalized = $this->normalizeFeedLang($code);
			if ($normalized === '') {
				continue;
			}
			$url_lang = $normalized;
			if ($url_lang === 'uk') {
				$url_lang = 'ua';
			}
			if (!isset($map[$normalized])) {
				$map[$normalized] = $url_lang;
			}
		}

		if (!isset($map['uk'])) {
			$map['uk'] = 'ua';
		}

		return $map;
	}

	private function normalizeFeedLang(string $lang): string {
		$lang = strtolower(trim((string)$lang));
		if ($lang === '') {
			return '';
		}
		if (strpos($lang, 'en') === 0) {
			return 'en';
		}
		if (strpos($lang, 'ru') === 0) {
			return 'ru';
		}
		if (strpos($lang, 'uk') === 0 || strpos($lang, 'ua') === 0) {
			return 'uk';
		}
		$lang = str_replace('_', '-', $lang);
		$pos = strpos($lang, '-');
		if ($pos !== false) {
			$lang = substr($lang, 0, $pos);
		}
		return $lang;
	}
}
