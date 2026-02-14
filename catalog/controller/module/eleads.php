<?php
namespace Opencart\Catalog\Controller\Extension\Eleads\Module;

class Eleads extends \Opencart\System\Engine\Controller {
	public function index(): void {
		require_once DIR_EXTENSION . 'eleads/system/library/eleads/bootstrap.php';
		require_once DIR_EXTENSION . 'eleads/system/library/eleads/oc_adapter.php';

		$feed_lang = isset($this->request->get['lang']) ? (string)$this->request->get['lang'] : '';
		if ($feed_lang === '') {
			$feed_lang = $this->config->get('config_language');
		}
		$feed_lang = $this->normalizeFeedLang($feed_lang);

		$request_key = isset($this->request->get['key']) ? (string)$this->request->get['key'] : '';

		$adapter = new \EleadsOcAdapter($this->registry);
		$lang_code = $adapter->resolveLanguageCode($feed_lang);
		$engine = new \EleadsFeedEngine();
		$result = $engine->build($adapter, $lang_code, $feed_lang, $request_key);

		if (!$result['ok']) {
			$this->response->addHeader('HTTP/1.1 403 Forbidden');
			return;
		}

		$this->response->addHeader('Content-Type: application/xml; charset=utf-8');
		$this->response->setOutput($result['xml']);
	}

	public function eventSeoUrl(string &$route, array &$args): void {
		$path = '';
		if (isset($this->request->get['_route_'])) {
			$path = trim((string)$this->request->get['_route_'], '/');
		} elseif (!empty($this->request->server['REQUEST_URI'])) {
			$uri_path = parse_url((string)$this->request->server['REQUEST_URI'], PHP_URL_PATH);
			$path = trim((string)$uri_path, '/');
		}
		if ($path === '') {
			return;
		}
		if (strpos($path, 'index.php') === 0) {
			return;
		}
		if (preg_match('#^eleads-yml/([a-zA-Z_-]+)\.xml$#', $path, $m)) {
			$this->request->get['route'] = 'extension/eleads/module/eleads';
			$this->request->get['lang'] = $m[1];
			return;
		}
		if ($path === 'e-search/api/sitemap-sync') {
			$this->request->get['route'] = 'extension/eleads/module/eleads.sitemapSync';
			return;
		}
		if ($path === 'e-search/api/languages') {
			$this->request->get['route'] = 'extension/eleads/module/eleads.languages';
			return;
		}
		if (preg_match('#^([a-zA-Z0-9_-]+)/e-search/(.+)$#', $path, $m)) {
			$lang = $m[1];
			$tail = $m[2];
			if ($tail !== '' && strpos($tail, 'api/') !== 0 && $tail !== 'sitemap.xml') {
				$this->request->get['route'] = 'extension/eleads/module/eleads.seoPage';
				$this->request->get['slug'] = $tail;
				$this->request->get['lang'] = $lang;
				return;
			}
		}
		if (strpos($path, 'e-search/') === 0) {
			$tail = substr($path, strlen('e-search/'));
			if ($tail !== '' && strpos($tail, 'api/') !== 0 && $tail !== 'sitemap.xml') {
				$lang = '';
				$slug = $tail;
				$parts = explode('/', $tail, 2);
				if (count($parts) === 2) {
					$lang = $parts[0];
					$slug = $parts[1];
				}

				if ($slug !== '') {
					$this->request->get['route'] = 'extension/eleads/module/eleads.seoPage';
					$this->request->get['slug'] = $slug;
					if ($lang !== '') {
						$this->request->get['lang'] = $lang;
					}
				}
			}
		}
	}

	public function sitemapSync(): void {
		if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST') {
			$this->response->addHeader('HTTP/1.1 405 Method Not Allowed');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(['error' => 'method_not_allowed']));
			return;
		}

		$api_key = trim((string)$this->config->get('module_eleads_api_key'));
		if ($api_key === '') {
			$this->response->addHeader('HTTP/1.1 401 Unauthorized');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(['error' => 'api_key_missing']));
			return;
		}

		$auth = $this->getBearerToken();
		if ($auth === '' || !hash_equals($api_key, $auth)) {
			$this->response->addHeader('HTTP/1.1 401 Unauthorized');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(['error' => 'unauthorized']));
			return;
		}

		$payload = json_decode((string)file_get_contents('php://input'), true);
		if (!is_array($payload)) {
			$this->response->addHeader('HTTP/1.1 422 Unprocessable Entity');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(['error' => 'invalid_payload']));
			return;
		}

		$action = isset($payload['action']) ? trim((string)$payload['action']) : '';
		$slug = isset($payload['slug']) ? trim((string)$payload['slug']) : '';
		$new_slug = isset($payload['new_slug']) ? trim((string)$payload['new_slug']) : '';

		if (!in_array($action, ['create', 'update', 'delete'], true)) {
			$this->response->addHeader('HTTP/1.1 422 Unprocessable Entity');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(['error' => 'invalid_action']));
			return;
		}
		if ($slug === '' || ($action === 'update' && $new_slug === '')) {
			$this->response->addHeader('HTTP/1.1 422 Unprocessable Entity');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(['error' => 'invalid_payload']));
			return;
		}

		$query_lang = isset($this->request->get['lang']) ? (string)$this->request->get['lang'] : '';
		$source_lang_input = $query_lang !== '' ? $query_lang : (isset($payload['lang']) ? (string)$payload['lang'] : (isset($payload['language']) ? (string)$payload['language'] : ''));
		$target_lang_input = isset($payload['new_lang']) ? (string)$payload['new_lang'] : (isset($payload['new_language']) ? (string)$payload['new_language'] : '');

		$source_lang_explicit = trim($source_lang_input) !== '';
		$target_lang_explicit = trim($target_lang_input) !== '';

		$source_lang = $this->resolveSeoSitemapLanguage($source_lang_input);
		if ($source_lang === '') {
			$source_lang = $this->resolveSeoSitemapLanguage((string)$this->config->get('config_language'));
		}
		$target_lang = $this->resolveSeoSitemapLanguage($target_lang_input);
		if ($target_lang === '') {
			$target_lang = $source_lang;
		}

		$entries = $this->readSeoSitemapEntries();
		$url = $this->buildSeoPageUrl($source_lang, $slug);

		if ($action === 'create') {
			$exists = false;
			foreach ($entries as $entry) {
				if ($entry['slug'] === $slug && $entry['lang'] === $source_lang) {
					$exists = true;
					break;
				}
			}
			if (!$exists) {
				$entries[] = ['lang' => $source_lang, 'slug' => $slug];
			}
		} elseif ($action === 'delete') {
			$entries = array_values(array_filter($entries, function (array $entry) use ($slug, $source_lang, $source_lang_explicit): bool {
				if ($entry['slug'] !== $slug) {
					return true;
				}
				if (!$source_lang_explicit) {
					return false;
				}
				return $entry['lang'] !== $source_lang;
			}));
		} else {
			$matched = [];
			$kept = [];
			foreach ($entries as $entry) {
				$is_match = $entry['slug'] === $slug && (!$source_lang_explicit || $entry['lang'] === $source_lang);
				if ($is_match) {
					$matched[] = $entry;
				} else {
					$kept[] = $entry;
				}
			}
			$entries = $kept;

			if (empty($matched)) {
				$matched[] = ['lang' => $source_lang, 'slug' => $slug];
			}

			foreach ($matched as $entry) {
				$new_lang = $entry['lang'];
				if ($target_lang_explicit) {
					$new_lang = $target_lang;
				} elseif ($source_lang_explicit) {
					$new_lang = $source_lang;
				}
				$entries[] = ['lang' => $new_lang, 'slug' => $new_slug];
			}

			$url = $this->buildSeoPageUrl($target_lang_explicit ? $target_lang : $source_lang, $new_slug);
		}

		if (!$this->writeSeoSitemapEntries($entries)) {
			$this->response->addHeader('HTTP/1.1 500 Internal Server Error');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(['error' => 'sitemap_update_failed']));
			return;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode([
			'status' => 'ok',
			'url' => $url,
		]));
	}

	public function languages(): void {
		if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'GET') {
			$this->response->addHeader('HTTP/1.1 405 Method Not Allowed');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(['error' => 'method_not_allowed']));
			return;
		}

		$api_key = (string)$this->config->get('module_eleads_api_key');
		if ($api_key === '') {
			$this->response->addHeader('HTTP/1.1 401 Unauthorized');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(['error' => 'api_key_missing']));
			return;
		}

		$auth = $this->getBearerToken();
		if ($auth === '' || !hash_equals($api_key, $auth)) {
			$this->response->addHeader('HTTP/1.1 401 Unauthorized');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(['error' => 'unauthorized']));
			return;
		}

		$this->load->model('localisation/language');
		$items = [];
		foreach ((array)$this->model_localisation_language->getLanguages() as $language) {
			if (isset($language['status']) && !$language['status']) {
				continue;
			}
			$code = isset($language['code']) ? (string)$language['code'] : '';
			$items[] = [
				'id' => isset($language['language_id']) ? (int)$language['language_id'] : 0,
				'label' => $code,
				'code' => $code,
				'href_lang' => $this->normalizeFeedLang($code),
				'enabled' => true,
			];
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode([
			'status' => 'ok',
			'count' => count($items),
			'items' => $items,
		]));
	}

	public function seoPage(): void {
		$slug = isset($this->request->get['slug']) ? trim((string)$this->request->get['slug']) : '';
		$lang = isset($this->request->get['lang']) ? trim((string)$this->request->get['lang']) : '';
		$requested_store_code = isset($this->request->get['language']) ? (string)$this->request->get['language'] : (isset($this->session->data['language']) ? (string)$this->session->data['language'] : (string)$this->config->get('config_language'));
		if ($slug === '') {
			$this->response->addHeader('HTTP/1.1 404 Not Found');
			return;
		}

		$slugs = $this->readSeoSitemapSlugs();
		if (!in_array($slug, $slugs, true)) {
			$this->response->addHeader('HTTP/1.1 404 Not Found');
			return;
		}

		if ($lang === '') {
			$lang = $requested_store_code;
		}
		$lang = $this->normalizeFeedLang($lang);
		if ($lang === '') {
			$lang = $this->normalizeFeedLang((string)$this->config->get('config_language'));
		}

		$page = $this->fetchSeoPage($slug, $lang);
		if (!$page) {
			$this->response->addHeader('HTTP/1.1 404 Not Found');
			return;
		}

		$current_short = $this->resolveSeoSitemapLanguage($lang);
		$selected_short = $this->resolveSeoSitemapLanguage($requested_store_code);
		if ($selected_short !== '' && $current_short !== '' && $selected_short !== $current_short) {
			$alternate_url = $this->findAlternateUrl($page['alternate'], $selected_short);
			if ($alternate_url !== '') {
				$this->response->redirect($alternate_url);
				return;
			}
		}

		$store_language_code = $this->resolveStoreLanguageCode($lang);
		$this->applyStoreLanguage($store_language_code);

		$self_lang = $this->resolveSeoSitemapLanguage($lang);
		$self_url = $this->buildSeoPageUrl($self_lang, $slug);
		$this->document->addLink($self_url, 'canonical');
		$alternate_links = [$self_url => true];
		foreach ((array)$page['alternate'] as $alt) {
			$alt_url = isset($alt['url']) ? trim((string)$alt['url']) : '';
			$alt_lang = isset($alt['lang']) ? (string)$alt['lang'] : '';
			if ($alt_url === '' && $alt_lang !== '') {
				$alt_url = $this->buildSeoPageUrl($this->resolveSeoSitemapLanguage($alt_lang), $slug);
			}
			if ($alt_url === '' || isset($alternate_links[$alt_url])) {
				continue;
			}
			$this->document->addLink($alt_url, 'alternate');
			$alternate_links[$alt_url] = true;
		}

		$this->load->language('product/search');
		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		$this->load->model('tool/image');

		$title = $page['meta_title'] !== '' ? $page['meta_title'] : ($page['h1'] !== '' ? $page['h1'] : $page['query']);
		$this->document->setTitle($title);
		if ($page['meta_description'] !== '') {
			$this->document->setDescription($page['meta_description']);
		}
		if ($page['meta_keywords'] !== '') {
			$this->document->setKeywords($page['meta_keywords']);
		}

		$data = [];
		$data['breadcrumbs'] = [
			[
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home', 'language=' . $store_language_code)
			]
		];
		$data['breadcrumbs'][] = [
			'text' => $title,
			'href' => $this->url->link('extension/eleads/module/eleads.seoPage', 'language=' . $store_language_code . '&slug=' . urlencode($slug))
		];

		$data['heading_title'] = $page['h1'] !== '' ? $page['h1'] : $page['query'];
		$data['entry_search'] = $this->language->get('entry_search');
		$data['text_keyword'] = $this->language->get('text_keyword');
		$data['text_category'] = $this->language->get('text_category');
		$data['text_sub_category'] = $this->language->get('text_sub_category');
		$data['entry_description'] = $this->language->get('entry_description');
		$data['button_search'] = $this->language->get('button_search');
		$data['text_search'] = $this->language->get('text_search');
		$data['text_empty'] = $this->language->get('text_empty');
		$data['text_sort'] = $this->language->get('text_sort');
		$data['text_limit'] = $this->language->get('text_limit');
		$data['text_compare'] = sprintf($this->language->get('text_compare'), isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0);
		$data['compare'] = $this->url->link('product/compare', 'language=' . $store_language_code);
		$data['button_cart'] = $this->language->get('button_cart');
		$data['button_wishlist'] = $this->language->get('button_wishlist');
		$data['button_compare'] = $this->language->get('button_compare');
		$data['button_list'] = $this->language->get('button_list');
		$data['button_grid'] = $this->language->get('button_grid');
		$data['text_tax'] = $this->language->get('text_tax');

		$data['search'] = $page['query'];
		$data['tag'] = '';
		$data['description'] = false;
		$data['category_id'] = 0;
		$data['sub_category'] = false;
		$data['categories'] = [];
		$data['seo_category_module'] = $this->load->controller('extension/opencart/module/category');
		$data['sort'] = 'p.sort_order';
		$data['order'] = 'ASC';
		$data['limit'] = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit');
		$data['sorts'] = [];
		$data['limits'] = [];

		$product_ids = $this->normalizeProductIds($page['product_ids']);
		$data['products'] = $this->buildProducts($product_ids, $store_language_code);
		$data['pagination'] = '';
		$data['results'] = '';

		$data['seo_description'] = html_entity_decode($page['description'], ENT_QUOTES, 'UTF-8');
		$data['seo_short_description'] = html_entity_decode($page['short_description'], ENT_QUOTES, 'UTF-8');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/eleads/seo', $data));
	}

	private function normalizeFeedLang(string $lang): string {
		$lang = strtolower((string)$lang);
		if (strpos($lang, 'en') === 0) {
			return 'en';
		}
		if (strpos($lang, 'ru') === 0) {
			return 'ru';
		}
		if (strpos($lang, 'uk') === 0 || strpos($lang, 'ua') === 0) {
			return 'uk';
		}
		return $lang;
	}

	private function getBearerToken(): string {
		$headers = [];
		if (function_exists('getallheaders')) {
			$headers = getallheaders();
		}
		$auth = '';
		if (isset($headers['Authorization'])) {
			$auth = $headers['Authorization'];
		} elseif (isset($headers['authorization'])) {
			$auth = $headers['authorization'];
		} elseif (isset($this->request->server['HTTP_AUTHORIZATION'])) {
			$auth = $this->request->server['HTTP_AUTHORIZATION'];
		} elseif (isset($this->request->server['REDIRECT_HTTP_AUTHORIZATION'])) {
			$auth = $this->request->server['REDIRECT_HTTP_AUTHORIZATION'];
		}
		if (stripos($auth, 'Bearer ') === 0) {
			return trim(substr($auth, 7));
		}
		return '';
	}

	private function getSeoSitemapPath(): string {
		$root = rtrim(dirname(DIR_APPLICATION), '/\\');
		return $root . '/e-search/sitemap.xml';
	}

	private function getSeoBaseUrl(): string {
		if (defined('HTTPS_SERVER') && HTTPS_SERVER) {
			return rtrim(HTTPS_SERVER, '/');
		}
		if (defined('HTTP_SERVER') && HTTP_SERVER) {
			return rtrim(HTTP_SERVER, '/');
		}
		$ssl = (string)$this->config->get('config_ssl');
		return $ssl !== '' ? rtrim($ssl, '/') : rtrim((string)$this->config->get('config_url'), '/');
	}

	private function fetchSeoPage(string $slug, string $lang): ?array {
		$api_key = trim((string)$this->config->get('module_eleads_api_key'));
		if ($api_key === '') {
			return null;
		}
		$lang = $this->normalizeFeedLang($lang);
		if ($lang === '') {
			$lang = $this->normalizeFeedLang((string)$this->config->get('config_language'));
		}
		require_once DIR_EXTENSION . 'eleads/system/library/eleads/api_routes.php';
		$ch = curl_init();
		if ($ch === false) {
			return null;
		}
		$headers = [
			'Authorization: Bearer ' . $api_key,
			'Accept: application/json',
		];
		$url = \EleadsApiRoutes::SEO_PAGE . rawurlencode($slug) . '?lang=' . rawurlencode($lang);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPGET, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
		curl_setopt($ch, CURLOPT_TIMEOUT, 6);
		$response = curl_exec($ch);
		if ($response === false) {
			curl_close($ch);
			return null;
		}
		$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($httpCode < 200 || $httpCode >= 300) {
			return null;
		}
		$data = json_decode($response, true);
		if (!is_array($data) || empty($data['page']) || !is_array($data['page'])) {
			return null;
		}
		$page = $data['page'];
		$alternate = [];
		if (isset($page['alternate']) && is_array($page['alternate'])) {
			foreach ($page['alternate'] as $item) {
				if (!is_array($item)) {
					continue;
				}
				$alternate[] = [
					'url' => isset($item['url']) ? trim((string)$item['url']) : '',
					'lang' => isset($item['lang']) ? trim((string)$item['lang']) : '',
				];
			}
		}
		return [
			'query' => isset($page['query']) ? (string)$page['query'] : '',
			'seo_slug' => isset($page['seo_slug']) ? (string)$page['seo_slug'] : '',
			'url' => isset($page['url']) ? (string)$page['url'] : '',
			'language' => isset($page['language']) ? (string)$page['language'] : '',
			'alternate' => $alternate,
			'h1' => isset($page['h1']) ? (string)$page['h1'] : '',
			'meta_title' => isset($page['meta_title']) ? (string)$page['meta_title'] : '',
			'meta_description' => isset($page['meta_description']) ? (string)$page['meta_description'] : '',
			'meta_keywords' => isset($page['meta_keywords']) ? (string)$page['meta_keywords'] : '',
			'short_description' => isset($page['short_description']) ? (string)$page['short_description'] : '',
			'description' => isset($page['description']) ? (string)$page['description'] : '',
			'product_ids' => isset($page['product_ids']) && is_array($page['product_ids']) ? $page['product_ids'] : [],
		];
	}

	private function normalizeProductIds(array $product_ids): array {
		$ids = [];
		foreach ($product_ids as $value) {
			$id = (int)$value;
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		return array_values(array_unique($ids));
	}

	private function buildProducts(array $product_ids, string $store_language_code): array {
		$products = [];
		$size = $this->getProductImageSize();
		$description_limit = $this->getProductDescriptionLength();
		foreach ($product_ids as $product_id) {
			$result = $this->model_catalog_product->getProduct($product_id);
			if (!$result) {
				continue;
			}

			$description = trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8')));
			if (oc_strlen($description) > $description_limit) {
				$description = oc_substr($description, 0, $description_limit) . '..';
			}

			if ($result['image'] && is_file(DIR_IMAGE . html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'))) {
				$image = $result['image'];
			} else {
				$image = 'placeholder.png';
			}

			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$price = false;
			}

			if ((float)$result['special']) {
				$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$special = false;
			}

			if ($this->config->get('config_tax')) {
				$tax = $this->currency->format((float)$result['special'] ? $result['special'] : $result['price'], $this->session->data['currency']);
			} else {
				$tax = false;
			}

			$product_data = [
				'thumb'       => $this->model_tool_image->resize($image, $size['width'], $size['height']),
				'description' => $description,
				'price'       => $price,
				'special'     => $special,
				'tax'         => $tax,
				'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
				'href'        => $this->url->link('product/product', 'language=' . $store_language_code . '&product_id=' . $result['product_id'])
			] + $result;

			$products[] = $this->load->controller('product/thumb', $product_data);
		}
		return $products;
	}

	private function getProductImageSize(): array {
		$width = (int)$this->config->get('config_image_product_width');
		$height = (int)$this->config->get('config_image_product_height');
		if ($width <= 0) {
			$width = 200;
		}
		if ($height <= 0) {
			$height = 200;
		}
		return ['width' => $width, 'height' => $height];
	}

	private function getProductDescriptionLength(): int {
		$length = (int)$this->config->get('config_product_description_length');
		if ($length <= 0) {
			$length = 100;
		}
		return $length;
	}

	private function readSeoSitemapSlugs(): array {
		$entries = $this->readSeoSitemapEntries();
		$slugs = [];
		foreach ($entries as $entry) {
			$slugs[] = $entry['slug'];
		}
		return array_values(array_unique($slugs));
	}

	private function writeSeoSitemapSlugs(array $slugs): bool {
		$lang = $this->resolveSeoSitemapLanguage((string)$this->config->get('config_language'));
		$entries = [];
		foreach ($slugs as $slug) {
			$slug = trim((string)$slug);
			if ($slug === '') {
				continue;
			}
			$entries[] = ['lang' => $lang, 'slug' => $slug];
		}
		return $this->writeSeoSitemapEntries($entries);
	}

	private function readSeoSitemapEntries(): array {
		$path = $this->getSeoSitemapPath();
		if (!is_file($path)) {
			return [];
		}
		$content = (string)file_get_contents($path);
		if ($content === '') {
			return [];
		}
		$matches = [];
		preg_match_all('#<loc>([^<]+)</loc>#i', $content, $matches);
		if (empty($matches[1])) {
			return [];
		}
		$entries = [];
		foreach ($matches[1] as $value) {
			$loc = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
			$path = parse_url($loc, PHP_URL_PATH);
			if (!is_string($path)) {
				continue;
			}
			$path = trim($path, '/');
			$lang = '';
			$slug = '';
			if (preg_match('#^([^/]+)/e-search/(.+)$#', $path, $m)) {
				$lang = $this->resolveSeoSitemapLanguage($m[1]);
				$slug = $m[2];
			} elseif (strpos($path, 'e-search/') === 0) {
				$tail = substr($path, strlen('e-search/'));
				if ($tail === false || $tail === '') {
					continue;
				}
				$parts = explode('/', $tail, 2);
				if (count($parts) === 2) {
					$lang = $this->resolveSeoSitemapLanguage($parts[0]);
					$slug = $parts[1];
				} else {
					$slug = $parts[0];
				}
			} else {
				continue;
			}
			$slug = trim((string)urldecode($slug));
			if ($slug === '') {
				continue;
			}
			if ($lang === '') {
				$lang = $this->resolveSeoSitemapLanguage((string)$this->config->get('config_language'));
			}
			$key = $lang . '|' . $slug;
			$entries[$key] = ['lang' => $lang, 'slug' => $slug];
		}
		return array_values($entries);
	}

	private function writeSeoSitemapEntries(array $entries): bool {
		$path = $this->getSeoSitemapPath();
		$dir = dirname($path);
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		$base_url = $this->getSeoBaseUrl();
		$rows = [];
		$unique = [];
		foreach ($entries as $entry) {
			$lang = $this->resolveSeoSitemapLanguage(isset($entry['lang']) ? (string)$entry['lang'] : '');
			$slug = trim(isset($entry['slug']) ? (string)$entry['slug'] : '');
			if ($slug === '') {
				continue;
			}
			if ($lang === '') {
				$lang = $this->resolveSeoSitemapLanguage((string)$this->config->get('config_language'));
			}
			$key = $lang . '|' . $slug;
			if (isset($unique[$key])) {
				continue;
			}
			$unique[$key] = true;
			$loc = $base_url . '/' . rawurlencode($lang) . '/e-search/' . rawurlencode($slug);
			$rows[] = '  <url><loc>' . htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') . '</loc></url>';
		}
		$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
		if ($rows) {
			$xml .= implode("\n", $rows) . "\n";
		}
		$xml .= "</urlset>\n";
		return @file_put_contents($path, $xml) !== false;
	}

	private function resolveSeoSitemapLanguage(string $lang): string {
		$lang = strtolower(trim($lang));
		$normalized = $this->normalizeFeedLang($lang);
		if ($normalized === 'en') {
			return 'en';
		}
		if ($normalized === 'ru') {
			return 'ru';
		}
		if ($normalized === 'uk') {
			return 'ua';
		}

		if (preg_match('/^[a-z]{2}/', $lang, $m)) {
			return $m[1];
		}

		return 'en';
	}

	private function resolveStoreLanguageCode(string $lang): string {
		$this->load->model('localisation/language');
		$languages = (array)$this->model_localisation_language->getLanguages();

		$lang = strtolower(trim($lang));
		$normalized = $this->normalizeFeedLang($lang);
		$fallback = (string)$this->config->get('config_language');

		foreach ($languages as $language) {
			if (isset($language['status']) && !$language['status']) {
				continue;
			}
			$code = strtolower(isset($language['code']) ? (string)$language['code'] : '');
			if ($code === '') {
				continue;
			}
			if ($code === $lang) {
				return $code;
			}
			if ($this->normalizeFeedLang($code) === $normalized) {
				if ($normalized === 'uk' && strpos($code, 'ua') === 0) {
					return $code;
				}
				if ($fallback === '' || $fallback === (string)$this->config->get('config_language')) {
					$fallback = $code;
				}
			}
		}

		return $fallback !== '' ? $fallback : (string)$this->config->get('config_language');
	}

	private function applyStoreLanguage(string $code): void {
		$code = trim($code);
		if ($code === '') {
			return;
		}

		$this->load->model('localisation/language');
		$languages = (array)$this->model_localisation_language->getLanguages();
		if (!isset($languages[$code])) {
			return;
		}

		$this->session->data['language'] = $code;
		$this->config->set('config_language', $code);
		$this->config->set('config_language_id', (int)$languages[$code]['language_id']);

		$language = new \Opencart\System\Library\Language($code);
		$language->load($code);
		$this->registry->set('language', $language);
		$this->language = $language;
	}

	private function findAlternateUrl(array $alternates, string $target_short): string {
		foreach ($alternates as $alternate) {
			if (!is_array($alternate)) {
				continue;
			}
			$alt_short = $this->resolveSeoSitemapLanguage(isset($alternate['lang']) ? (string)$alternate['lang'] : '');
			$url = isset($alternate['url']) ? trim((string)$alternate['url']) : '';
			if ($url !== '' && $alt_short === $target_short) {
				return $url;
			}
		}
		return '';
	}

	private function buildSeoPageUrl(string $lang, string $slug): string {
		$base = $this->getSeoBaseUrl();
		$lang = $this->resolveSeoSitemapLanguage($lang);
		return $base . '/' . rawurlencode($lang) . '/e-search/' . rawurlencode($slug);
	}
}
