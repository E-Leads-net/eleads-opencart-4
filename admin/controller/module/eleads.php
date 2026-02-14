<?php
namespace Opencart\Admin\Controller\Extension\Eleads\Module;

class Eleads extends \Opencart\System\Engine\Controller {
	public function index(): void {
		$this->load->language('extension/eleads/module/eleads');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/eleads/module/eleads', 'user_token=' . $this->session->data['user_token'])
		];

		$data['success'] = '';
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		}
		$data['error'] = '';
		if (isset($this->session->data['error'])) {
			$data['error'] = $this->session->data['error'];
			unset($this->session->data['error']);
		}

		$data['save'] = $this->url->link('extension/eleads/module/eleads.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_back'] = $this->language->get('button_back');
		$data['tab_export'] = $this->language->get('tab_export');
		$data['tab_seo'] = $this->language->get('tab_seo');
		$data['tab_api'] = $this->language->get('tab_api');
		$data['tab_update'] = $this->language->get('tab_update');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_api_key'] = $this->language->get('entry_api_key');
		$data['entry_access_key'] = $this->language->get('entry_access_key');
		$data['entry_categories'] = $this->language->get('entry_categories');
		$data['entry_filter_attributes'] = $this->language->get('entry_filter_attributes');
		$data['entry_filter_option_values'] = $this->language->get('entry_filter_option_values');
		$data['entry_filter_attributes_toggle'] = $this->language->get('entry_filter_attributes_toggle');
		$data['entry_filter_option_values_toggle'] = $this->language->get('entry_filter_option_values_toggle');
		$data['entry_grouped'] = $this->language->get('entry_grouped');
		$data['entry_sync_enabled'] = $this->language->get('entry_sync_enabled');
		$data['entry_shop_name'] = $this->language->get('entry_shop_name');
		$data['entry_email'] = $this->language->get('entry_email');
		$data['entry_shop_url'] = $this->language->get('entry_shop_url');
		$data['entry_currency'] = $this->language->get('entry_currency');
		$data['entry_picture_limit'] = $this->language->get('entry_picture_limit');
		$data['entry_image_size'] = $this->language->get('entry_image_size');
		$data['entry_short_description_source'] = $this->language->get('entry_short_description_source');
		$data['entry_seo_pages'] = $this->language->get('entry_seo_pages');
		$data['entry_sitemap_url'] = $this->language->get('entry_sitemap_url');
		$data['text_seo_url_disabled'] = $this->language->get('text_seo_url_disabled');
		$data['help_image_size'] = $this->language->get('help_image_size');
		$data['text_update'] = $this->language->get('text_update');
		$data['text_api_key_required'] = $this->language->get('text_api_key_required');
		$data['text_api_key_invalid'] = $this->language->get('text_api_key_invalid');
		$data['entry_api_key_title'] = $this->language->get('entry_api_key_title');
		$data['entry_api_key_hint'] = $this->language->get('entry_api_key_hint');

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');
		$this->load->model('catalog/category');
		$this->load->model('catalog/attribute');
		$this->load->model('catalog/option');
		$this->load->model('catalog/product');

		require_once DIR_EXTENSION . 'eleads/system/library/eleads/api_routes.php';

		$api_key = trim((string)$this->config->get('module_eleads_api_key'));
		$api_key_valid = false;
		$api_key_error = '';
		$seo_available = true;
		$seo_status = null;
		if ($api_key !== '') {
			$status = $this->getApiKeyStatusData($api_key);
			$api_key_valid = !empty($status['ok']);
			$seo_status = $status !== null ? (!empty($status['seo_status'])) : null;
			if (!$api_key_valid) {
				$api_key_error = 'invalid';
			}
		}
		if ($seo_status === false) {
			$seo_available = false;
			$settings_current = $this->model_setting_setting->getSetting('module_eleads');
			if (!empty($settings_current['module_eleads_seo_pages_enabled'])) {
				$settings_current['module_eleads_seo_pages_enabled'] = 0;
				$this->model_setting_setting->editSetting('module_eleads', $settings_current);
			}
			$this->syncSeoSitemap(false, (string)$api_key, $settings_current);
		}

		$settings = $this->model_setting_setting->getSetting('module_eleads');
		$data = array_merge($data, $this->prepareSettingsData($settings));
		if (!$seo_available) {
			$data['module_eleads_seo_pages_enabled'] = 0;
		}
		$data['seo_url_enabled'] = (bool)$this->config->get('config_seo_url');
		$data['sitemap_url_full'] = $this->getCatalogBaseUrl() . '/e-search/sitemap.xml';
		$data['seo_tab_available'] = $seo_available;
		$data['api_key_required'] = !$api_key_valid;
		$data['api_key_value'] = $api_key;
		$data['api_key_error'] = $api_key_error;
		$data['api_key_action'] = $this->url->link('extension/eleads/module/eleads.apikey', 'user_token=' . $this->session->data['user_token']);

		if ($api_key_valid) {
			$data['languages'] = $this->model_localisation_language->getLanguages();
			$tree = $this->getCategoriesTreeNodes();
			$selected = array_flip(array_map('intval', (array)$data['module_eleads_categories']));
			$data['categories_tree_html'] = $this->renderCategoriesTreeHtml($tree, $selected);
			$data['attributes'] = $this->model_catalog_attribute->getAttributes();
			$options = $this->model_catalog_option->getOptions();
			foreach ($options as &$option) {
				$option['option_value'] = $this->model_catalog_product->getOptionValuesByOptionId((int)$option['option_id']);
			}
			unset($option);
			$data['options'] = $options;
			$data['feed_urls'] = $this->buildFeedUrls($data['languages'], $data['module_eleads_access_key']);

			require_once DIR_EXTENSION . 'eleads/system/library/eleads/bootstrap.php';
			$data['update_info'] = \EleadsUpdateHelper::getUpdateInfo();
			$data['update_url'] = $this->url->link('extension/eleads/module/eleads.update', 'user_token=' . $this->session->data['user_token']);
		} else {
			$data['languages'] = [];
			$data['categories_tree_html'] = '';
			$data['attributes'] = [];
			$data['options'] = [];
			$data['feed_urls'] = [];
			$data['update_info'] = [];
			$data['update_url'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/eleads/module/eleads', $data));
	}

	public function save(): void {
		$this->load->language('extension/eleads/module/eleads');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/eleads/module/eleads')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		if (!$json) {
			require_once DIR_EXTENSION . 'eleads/system/library/eleads/api_routes.php';
			$api_key = trim((string)$this->config->get('module_eleads_api_key'));
			if ($api_key === '' || !$this->checkApiKeyStatus($api_key)) {
				$json['error']['warning'] = $this->language->get('text_api_key_required');
			}
		}

		if (!$json) {
			$this->load->model('setting/setting');
			$settings_current = $this->model_setting_setting->getSetting('module_eleads');
			$seo_prev = !empty($settings_current['module_eleads_seo_pages_enabled']);
			$this->model_setting_setting->editSetting('module_eleads', $this->request->post);
			$this->syncWidgetLoaderTag(
				!empty($this->request->post['module_eleads_status']),
				(string)($this->request->post['module_eleads_api_key'] ?? $api_key)
			);
			$seo_new = !empty($this->request->post['module_eleads_seo_pages_enabled']);
			if ($seo_prev !== $seo_new || $seo_new) {
				$this->syncSeoSitemap($seo_new, (string)$api_key, $this->request->post);
			}
			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function update(): void {
		$this->load->language('extension/eleads/module/eleads');
		require_once DIR_EXTENSION . 'eleads/system/library/eleads/bootstrap.php';

		$root_path = rtrim(dirname(DIR_APPLICATION), '/\\') . '/';
		$result = \EleadsUpdateHelper::updateToLatest($root_path);

		if (!empty($result['ok'])) {
			$this->refreshAfterUpdate();
			$this->session->data['success'] = $this->language->get('text_update_success');
		} else {
			$this->session->data['error'] = isset($result['message']) ? $result['message'] : $this->language->get('text_update_error');
		}

		$this->response->redirect($this->url->link('extension/eleads/module/eleads', 'user_token=' . $this->session->data['user_token']));
	}

	private function refreshAfterUpdate(): void {
		// Rebuild OCMOD cache
		$this->load->controller('marketplace/modification.refresh');

		// Clear template/data cache
		$this->clearDirectoryFiles(DIR_CACHE);
	}

	private function clearDirectoryFiles(string $dir): void {
		if (!is_dir($dir)) {
			return;
		}
		$items = glob(rtrim($dir, '/\\') . '/*');
		if (!$items) {
			return;
		}
		foreach ($items as $item) {
			$name = basename($item);
			if ($name === 'index.html' || $name === '.htaccess') {
				continue;
			}
			if (is_dir($item)) {
				$this->removeDirectory($item);
			} else {
				@unlink($item);
			}
		}
	}

	private function removeDirectory(string $dir): void {
		$items = glob(rtrim($dir, '/\\') . '/*');
		if ($items) {
			foreach ($items as $item) {
				if (is_dir($item)) {
					$this->removeDirectory($item);
				} else {
					@unlink($item);
				}
			}
		}
		@rmdir($dir);
	}

	public function apikey(): void {
		$this->load->language('extension/eleads/module/eleads');
		require_once DIR_EXTENSION . 'eleads/system/library/eleads/api_routes.php';

		if (!$this->user->hasPermission('modify', 'extension/eleads/module/eleads')) {
			$this->session->data['error'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('extension/eleads/module/eleads', 'user_token=' . $this->session->data['user_token']));
			return;
		}

		$api_key = trim((string)($this->request->post['module_eleads_api_key'] ?? ''));
		$this->load->model('setting/setting');

		if ($api_key !== '' && $this->checkApiKeyStatus($api_key)) {
			$settings = $this->model_setting_setting->getSetting('module_eleads');
			$settings['module_eleads_api_key'] = $api_key;
			$this->model_setting_setting->editSetting('module_eleads', $settings);
			$this->session->data['success'] = $this->language->get('text_api_key_saved');
		} else {
			$this->session->data['error'] = $this->language->get('text_api_key_invalid');
		}

		$this->response->redirect($this->url->link('extension/eleads/module/eleads', 'user_token=' . $this->session->data['user_token']));
	}

	private function checkApiKeyStatus(string $api_key): bool {
		$status = $this->getApiKeyStatusData($api_key);
		return is_array($status) && !empty($status['ok']);
	}

	private function getApiKeyStatusData(string $api_key): ?array {
		if ($api_key === '') {
			return null;
		}
		$ch = curl_init();
		if ($ch === false) {
			return null;
		}
		$headers = [
			'Authorization: Bearer ' . $api_key,
			'Accept: application/json',
		];
		curl_setopt($ch, CURLOPT_URL, \EleadsApiRoutes::TOKEN_STATUS);
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
		if ($httpCode === 401 || $httpCode === 403) {
			return ['ok' => false, 'seo_status' => false];
		}
		if ($httpCode < 200 || $httpCode >= 300) {
			return null;
		}
		$data = json_decode($response, true);
		if (!is_array($data) || !isset($data['ok'])) {
			return null;
		}
		return [
			'ok' => !empty($data['ok']),
			'seo_status' => isset($data['seo_status']) ? (bool)$data['seo_status'] : null
		];
	}

	public function install(): void {
		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/eleads/module/eleads');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/eleads/module/eleads');
		// Backward compatibility if extension folder name differs in existing installs.
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/eleads-opencart-4.x/module/eleads');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/eleads-opencart-4.x/module/eleads');

		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('eleads_product_add');
		$this->model_setting_event->deleteEventByCode('eleads_product_edit');
		$this->model_setting_event->deleteEventByCode('eleads_product_delete');
		$this->model_setting_event->deleteEventByCode('eleads_seo_route');
		$this->model_setting_event->addEvent([
			'code' => 'eleads_product_add',
			'description' => 'E-Leads product add',
			'trigger' => 'admin/model/catalog/product.addProduct/after',
			'action' => 'extension/eleads/module/eleads.eventProductAdd',
			'status' => true,
			'sort_order' => 0
		]);
		$this->model_setting_event->addEvent([
			'code' => 'eleads_product_edit',
			'description' => 'E-Leads product edit',
			'trigger' => 'admin/model/catalog/product.editProduct/after',
			'action' => 'extension/eleads/module/eleads.eventProductEdit',
			'status' => true,
			'sort_order' => 0
		]);
		$this->model_setting_event->addEvent([
			'code' => 'eleads_product_delete',
			'description' => 'E-Leads product delete',
			'trigger' => 'admin/model/catalog/product.deleteProduct/after',
			'action' => 'extension/eleads/module/eleads.eventProductDelete',
			'status' => true,
			'sort_order' => 0
		]);
		$this->model_setting_event->addEvent([
			'code' => 'eleads_seo_route',
			'description' => 'E-Leads SEO routes',
			'trigger' => 'catalog/controller/startup/seo_url/before',
			'action' => 'extension/eleads/module/eleads.eventSeoUrl',
			'status' => true,
			'sort_order' => 0
		]);
	}

	public function uninstall(): void {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('eleads_product_add');
		$this->model_setting_event->deleteEventByCode('eleads_product_edit');
		$this->model_setting_event->deleteEventByCode('eleads_product_delete');
		$this->model_setting_event->deleteEventByCode('eleads_seo_route');
		$this->syncWidgetLoaderTag(false, '');
	}

	public function eventProductAdd(string $route, array $args, mixed $output): void {
		$product_id = $output ? (int)$output : (isset($args[0]) ? (int)$args[0] : 0);
		$this->syncProduct($product_id, 'create');
	}

	public function eventProductEdit(string $route, array $args, mixed $output): void {
		$product_id = isset($args[0]) ? (int)$args[0] : 0;
		$this->syncProduct($product_id, 'update');
	}

	public function eventProductDelete(string $route, array $args, mixed $output): void {
		$product_id = isset($args[0]) ? (int)$args[0] : 0;
		$this->syncProduct($product_id, 'delete');
	}

	private function syncProduct(int $product_id, string $mode): void {
		if ($product_id <= 0) {
			return;
		}

		require_once DIR_EXTENSION . 'eleads/system/library/eleads/bootstrap.php';
		require_once DIR_EXTENSION . 'eleads/system/library/eleads/oc_adapter.php';

		$adapter = new \EleadsOcAdapter($this->registry);
		$settings = $adapter->getSettings();
		$payload_builder = new \EleadsSyncPayloadBuilder();
		$api_client = new \EleadsApiClient();
		$service = new \EleadsSyncService($settings, $adapter, $payload_builder, $api_client);
		$languages = ($mode === 'delete') ? $this->getEnabledSyncLanguages() : $this->getProductSyncLanguages($product_id);
		if (empty($languages)) {
			return;
		}

		foreach ($languages as $lang_code) {
			if ($mode === 'create') {
				$service->syncProductCreated($product_id, $lang_code);
			} elseif ($mode === 'delete') {
				$service->syncProductDeleted($product_id, $lang_code);
			} else {
				$service->syncProductUpdated($product_id, $lang_code);
			}
		}
	}

	private function getProductSyncLanguages(int $product_id): array {
		$languages = [];
		$query = $this->db->query(
			"SELECT DISTINCT l.code FROM `" . DB_PREFIX . "product_description` pd LEFT JOIN `" . DB_PREFIX . "language` l ON (pd.language_id = l.language_id) WHERE pd.product_id = '" . (int)$product_id . "' AND l.status = '1'"
		);
		foreach ((array)$query->rows as $row) {
			$normalized = $this->normalizeFeedLang(isset($row['code']) ? (string)$row['code'] : '');
			if ($normalized !== '') {
				$languages[$normalized] = $normalized;
			}
		}
		if (!empty($languages)) {
			return array_values($languages);
		}
		return $this->getEnabledSyncLanguages();
	}

	private function getEnabledSyncLanguages(): array {
		$languages = [];
		$query = $this->db->query("SELECT `code` FROM `" . DB_PREFIX . "language` WHERE `status` = '1'");
		foreach ((array)$query->rows as $row) {
			$normalized = $this->normalizeFeedLang(isset($row['code']) ? (string)$row['code'] : '');
			if ($normalized !== '') {
				$languages[$normalized] = $normalized;
			}
		}
		return array_values($languages);
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

	private function prepareSettingsData(array $settings): array {
		$default_shop_url = '';
		if (defined('HTTPS_CATALOG') && HTTPS_CATALOG) {
			$default_shop_url = rtrim(HTTPS_CATALOG, '/');
		} elseif (defined('HTTP_CATALOG') && HTTP_CATALOG) {
			$default_shop_url = rtrim(HTTP_CATALOG, '/');
		} else {
			$ssl = (string)$this->config->get('config_ssl');
			$default_shop_url = $ssl !== '' ? rtrim($ssl, '/') : rtrim((string)$this->config->get('config_url'), '/');
		}
		$defaults = [
			'module_eleads_status' => 1,
			'module_eleads_access_key' => '',
			'module_eleads_categories' => [],
			'module_eleads_filter_attributes' => [],
			'module_eleads_filter_option_values' => [],
			'module_eleads_filter_attributes_enabled' => 0,
			'module_eleads_filter_option_values_enabled' => 0,
			'module_eleads_grouped' => 1,
			'module_eleads_sync_enabled' => 0,
			'module_eleads_shop_name' => (string)$this->config->get('config_name'),
			'module_eleads_email' => (string)$this->config->get('config_email'),
			'module_eleads_shop_url' => $default_shop_url,
			'module_eleads_currency' => (string)$this->config->get('config_currency'),
			'module_eleads_picture_limit' => 5,
			'module_eleads_image_size' => 'original',
			'module_eleads_short_description_source' => 'meta_description',
			'module_eleads_seo_pages_enabled' => 0,
			'module_eleads_api_key' => '',
		];

		$data = [];
		$fallback_on_empty = [
			'module_eleads_shop_name',
			'module_eleads_email',
			'module_eleads_shop_url',
			'module_eleads_currency',
		];
		foreach ($defaults as $key => $value) {
			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} elseif (isset($settings[$key])) {
				$setting_value = $settings[$key];
				if (in_array($key, $fallback_on_empty, true) && trim((string)$setting_value) === '') {
					$data[$key] = $value;
				} else {
					$data[$key] = $setting_value;
				}
			} else {
				$data[$key] = $value;
			}
		}
		return $data;
	}

	private function syncSeoSitemap(bool $enabled, string $api_key, array $settings): void {
		$path = $this->getSeoSitemapPath();
		if ($enabled) {
			$slugs = $this->fetchSeoSlugs($api_key);
			$base_url = $this->getSeoBaseUrl($settings);
			$dir = dirname($path);
			if (!is_dir($dir)) {
				@mkdir($dir, 0755, true);
			}
			$content = $this->buildSeoSitemapXml($base_url, $slugs);
			@file_put_contents($path, $content);
		} else {
			if (is_file($path)) {
				@unlink($path);
			}
		}
	}

	private function getSeoSitemapPath(): string {
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
		$this->load->model('localisation/language');
		$map = [];

		foreach ((array)$this->model_localisation_language->getLanguages() as $language) {
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

	private function getCategoriesTreeNodes(int $parent_id = 0, int $level = 0, array &$visited = []): array {
		$result = [];
		if ($level > 50) {
			return $result;
		}
		$rows = $this->db->query("SELECT c.category_id, c.parent_id, c.sort_order, cd.name FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) WHERE c.parent_id = '" . (int)$parent_id . "' AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY c.sort_order, LCASE(cd.name)")->rows;
		foreach ($rows as $row) {
			$category_id = (int)$row['category_id'];
			if (isset($visited[$category_id])) {
				continue;
			}
			$visited[$category_id] = true;
			$children = $this->getCategoriesTreeNodes($category_id, $level + 1, $visited);
			$result[] = [
				'category_id' => $category_id,
				'name' => $row['name'],
				'level' => $level,
				'children' => $children,
			];
		}
		return $result;
	}

	private function renderCategoriesTreeHtml(array $nodes, array $selected_set): string {
		$html = '<ul class="eleads-tree-list">';
		foreach ($nodes as $node) {
			$id = (int)$node['category_id'];
			$name = htmlspecialchars($node['name'], ENT_QUOTES, 'UTF-8');
			$has_children = !empty($node['children']);
			$checked = isset($selected_set[$id]) ? ' checked' : '';
			$html .= '<li class="eleads-tree-item' . ($has_children ? ' has-children' : '') . '" data-id="' . $id . '">';
			if ($has_children) {
				$html .= '<button type="button" class="eleads-tree-toggle" aria-label="Toggle"></button>';
			} else {
				$html .= '<span class="eleads-tree-spacer"></span>';
			}
			$html .= '<label class="eleads-tree-label"><input type="checkbox" name="module_eleads_categories[]" value="' . $id . '"' . $checked . '><span class="eleads-tree-box"></span><span class="eleads-tree-text">' . $name . '</span></label>';
			if ($has_children) {
				$html .= '<div class="eleads-tree-children">' . $this->renderCategoriesTreeHtml($node['children'], $selected_set) . '</div>';
			}
			$html .= '</li>';
		}
		$html .= '</ul>';
		return $html;
	}

	private function buildFeedUrls(array $languages, string $access_key): array {
		$root = $this->getCatalogBaseUrl();
		$seo_enabled = (bool)$this->config->get('config_seo_url');
		$urls = [];
		foreach ($languages as $language) {
			$label = $this->mapFeedLangCode($language['code'], $language['name']);
			if ($seo_enabled) {
				$url = $root . '/eleads-yml/' . $label . '.xml';
			} else {
				$url = $root . '/index.php?route=extension/eleads/module/eleads&lang=' . rawurlencode($label);
			}
			if ($access_key) {
				$url .= ($seo_enabled ? '?' : '&') . 'key=' . rawurlencode($access_key);
			}
			$urls[] = [
				'name' => $language['name'],
				'code' => $label,
				'url' => $url,
			];
		}
		return $urls;
	}

	private function getCatalogBaseUrl(): string {
		$root = '';
		if (defined('HTTPS_CATALOG') && HTTPS_CATALOG) {
			$root = HTTPS_CATALOG;
		} elseif (defined('HTTP_CATALOG') && HTTP_CATALOG) {
			$root = HTTP_CATALOG;
		} else {
			$root = $this->config->get('config_ssl') ? $this->config->get('config_ssl') : $this->config->get('config_url');
		}
		if ($root === null) {
			$root = '';
		}
		return rtrim((string)$root, '/');
	}

	private function syncWidgetLoaderTag(bool $enabled, string $api_key): void {
		$files = $this->getFooterTemplateFiles();
		if (!$files) {
			return;
		}

		$block = '';
		if ($enabled) {
			$tag = $this->fetchWidgetLoaderTag($api_key);
			if ($tag !== '') {
				$block = "<!-- ELeads Widgets Loader Tag Start -->\n" . $this->stripWidgetLoaderMarkers($tag) . "\n<!-- ELeads Widgets Loader Tag End -->";
			}
		}

		foreach ($files as $file) {
			$content = @file_get_contents($file);
			if ($content === false) {
				continue;
			}
			$updated = $this->removeWidgetLoaderBlock($content);
			if ($enabled && $block !== '') {
				if (preg_match('/<\/body>/i', $updated)) {
					$updated = (string)preg_replace('/<\/body>/i', "\n" . $block . "\n</body>", $updated, 1);
				} else {
					$updated = rtrim($updated) . "\n\n" . $block . "\n";
				}
			}
			if ($updated !== $content) {
				@file_put_contents($file, $updated);
			}
		}
	}

	private function fetchWidgetLoaderTag(string $api_key): string {
		$api_key = trim($api_key);
		if ($api_key === '') {
			return '';
		}

		require_once DIR_EXTENSION . 'eleads/system/library/eleads/api_routes.php';

		$ch = curl_init();
		if ($ch === false) {
			return '';
		}
		curl_setopt($ch, CURLOPT_URL, \EleadsApiRoutes::WIDGETS_LOADER_TAG);
		curl_setopt($ch, CURLOPT_HTTPGET, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . $api_key,
			'Accept: application/json, text/plain',
		]);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
		curl_setopt($ch, CURLOPT_TIMEOUT, 8);
		$response = curl_exec($ch);
		$http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($response === false || $http_code < 200 || $http_code >= 300) {
			return '';
		}

		$body = trim((string)$response);
		$json = json_decode($body, true);
		if (is_array($json)) {
			if (!empty($json['tag'])) {
				return (string)$json['tag'];
			}
			if (!empty($json['data']['tag'])) {
				return (string)$json['data']['tag'];
			}
			if (!empty($json['html'])) {
				return (string)$json['html'];
			}
		}

		return $body;
	}

	private function getFooterTemplateFiles(): array {
		$root = rtrim(dirname(DIR_APPLICATION), '/\\') . '/';
		$theme = (string)$this->config->get('config_theme');
		$candidates = [
			$root . 'extension/' . $theme . '/catalog/view/template/common/footer.twig',
			$root . 'catalog/view/template/common/footer.twig',
			$root . 'catalog/view/template/common/footer.tpl',
		];

		$files = [];
		foreach ($candidates as $file) {
			if (is_file($file)) {
				$files[] = $file;
			}
		}
		return array_values(array_unique($files));
	}

	private function removeWidgetLoaderBlock(string $content): string {
		return (string)preg_replace('/\\s*<!--\\s*ELeads Widgets Loader Tag Start\\s*-->.*?<!--\\s*ELeads Widgets Loader Tag End\\s*-->\\s*/is', "\n", $content);
	}

	private function stripWidgetLoaderMarkers(string $content): string {
		$content = (string)preg_replace('/<!--\\s*ELeads Widgets Loader Tag Start\\s*-->/i', '', $content);
		$content = (string)preg_replace('/<!--\\s*ELeads Widgets Loader Tag End\\s*-->/i', '', $content);
		return trim($content);
	}

	private function mapFeedLangCode(string $code, string $name): string {
		$code = strtolower((string)$code);
		$name = strtolower((string)$name);
		if (strpos($code, 'en') === 0 || strpos($name, 'english') !== false) {
			return 'en';
		}
		if (strpos($code, 'ru') === 0 || strpos($name, 'рус') !== false) {
			return 'ru';
		}
		if (strpos($code, 'uk') === 0 || strpos($code, 'ua') === 0 || strpos($name, 'ukr') !== false || strpos($name, 'укр') !== false) {
			return 'uk';
		}
		return $code;
	}
}
