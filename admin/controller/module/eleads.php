<?php
namespace Opencart\Admin\Controller\Extension\Eleads\Module;

class Eleads extends \Opencart\System\Engine\Controller {
	public function index(): void {
		$this->ensureLegacyAdminLanguageBridge();
		$this->ensureCoreSeoRoutePatch();
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
		$this->load->model('setting/event');
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

			require_once DIR_EXTENSION . 'eleads/system/library/eleads/update_manager.php';
			$update_manager = new \EleadsUpdateManager($this->registry);
			$data['update_info'] = $update_manager->getUpdateInfo();
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
		require_once DIR_EXTENSION . 'eleads/system/library/eleads/update_manager.php';
		$update_manager = new \EleadsUpdateManager($this->registry);
		$result = $update_manager->updateToLatest();

		if (!empty($result['ok'])) {
			$this->session->data['success'] = $this->language->get('text_update_success');
		} else {
			$this->session->data['error'] = isset($result['message']) ? $result['message'] : $this->language->get('text_update_error');
		}

		$this->response->redirect($this->url->link('extension/eleads/module/eleads', 'user_token=' . $this->session->data['user_token']));
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
		$this->ensureLegacyAdminLanguageBridge();
		$this->ensureCoreSeoRoutePatch();
		$this->grantModulePermissions();

		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('eleads_product_add');
		$this->model_setting_event->deleteEventByCode('eleads_product_edit');
		$this->model_setting_event->deleteEventByCode('eleads_product_delete');
		$this->model_setting_event->deleteEventByCode('eleads_seo_route');
		$this->model_setting_event->deleteEventByCode('eleads_seo_route_index');
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
	}

	private function ensureLegacyAdminLanguageBridge(): void {
		$source_base = DIR_EXTENSION . 'eleads/admin/language/';
		$target_base = DIR_APPLICATION . 'language/';

		if (!is_dir($source_base) || !is_dir($target_base)) {
			return;
		}

		$lang_dirs = scandir($source_base);
		if (!is_array($lang_dirs)) {
			return;
		}

		foreach ($lang_dirs as $lang_dir) {
			if ($lang_dir === '.' || $lang_dir === '..') {
				continue;
			}

			$source_file = $source_base . $lang_dir . '/extension/eleads/module/eleads.php';
			if (!is_file($source_file)) {
				$fallback = $source_base . $lang_dir . '/module/eleads.php';
				if (is_file($fallback)) {
					$source_file = $fallback;
				} else {
					continue;
				}
			}

			$lang_variants = [
				$lang_dir,
				str_replace('_', '-', $lang_dir),
				str_replace('-', '_', $lang_dir),
				strtolower($lang_dir),
				strtolower(str_replace('_', '-', $lang_dir)),
				strtolower(str_replace('-', '_', $lang_dir))
			];
			$lang_variants = array_values(array_unique(array_filter($lang_variants)));

			$destinations = [];
			foreach ($lang_variants as $lang_variant) {
				$destinations[] = $target_base . $lang_variant . '/extension/eleads/module/eleads.php';
				$destinations[] = $target_base . $lang_variant . '/module/eleads.php';
			}

			foreach ($destinations as $destination_file) {
				$destination_dir = dirname($destination_file);
				if (!is_dir($destination_dir) && !mkdir($destination_dir, 0755, true) && !is_dir($destination_dir)) {
					continue;
				}
				@copy($source_file, $destination_file);
			}
		}
	}

	private function ensureCoreSeoRoutePatch(): void {
		if (!defined('DIR_CATALOG')) {
			return;
		}

		$needle = "if (isset(\$this->request->get['_route_'])) {";
		$insert = $this->getSeoRoutesPatchInsert();
		foreach ($this->getSeoStartupControllerFiles() as $file) {
			if (!is_file($file)) {
				continue;
			}

			$content = (string)file_get_contents($file);
			if ($content === '') {
				continue;
			}

			if (strpos($content, '// ELeads SEO Routes Start') !== false) {
				continue;
			}

			if (strpos($content, $needle) === false) {
				continue;
			}

			$backup = $file . '.eleads.bak';
			if (!is_file($backup)) {
				@file_put_contents($backup, $content);
			}

			$patched = str_replace($needle, $needle . $insert, $content);
			@file_put_contents($file, $patched);
		}
	}

	private function grantModulePermissions(): void {
		$this->load->model('user/user_group');
		$groups = (array)$this->model_user_user_group->getUserGroups();
		$routes = [
			'extension/eleads/module/eleads',
			// Backward compatibility if extension folder name differs in existing installs.
			'extension/eleads-opencart-4.x/module/eleads',
		];

		foreach ($groups as $group) {
			$group_id = isset($group['user_group_id']) ? (int)$group['user_group_id'] : 0;
			if ($group_id <= 0) {
				continue;
			}
			foreach ($routes as $route) {
				$this->model_user_user_group->addPermission($group_id, 'access', $route);
				$this->model_user_user_group->addPermission($group_id, 'modify', $route);
			}
		}
	}

	public function uninstall(): void {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('eleads_product_add');
		$this->model_setting_event->deleteEventByCode('eleads_product_edit');
		$this->model_setting_event->deleteEventByCode('eleads_product_delete');
		$this->model_setting_event->deleteEventByCode('eleads_seo_route');
		$this->model_setting_event->deleteEventByCode('eleads_seo_route_index');
		$this->removeCoreSeoRoutePatch();
		$this->syncWidgetLoaderTag(false, '');
	}

	private function removeCoreSeoRoutePatch(): void {
		if (!defined('DIR_CATALOG')) {
			return;
		}
		$pattern = '/\\n\\t\\t\\t\\t\\/\\/ ELeads SEO Routes Start.*?\\/\\/ ELeads SEO Routes End/s';
		foreach ($this->getSeoStartupControllerFiles() as $file) {
			if (!is_file($file)) {
				continue;
			}

			$content = (string)file_get_contents($file);
			if ($content === '') {
				continue;
			}

			$cleaned = preg_replace($pattern, '', $content);

			if (is_string($cleaned) && $cleaned !== $content) {
				@file_put_contents($file, $cleaned);
			}
			$backup = $file . '.eleads.bak';
			if (is_file($backup)) {
				@unlink($backup);
			}
		}
	}

	private function getSeoStartupControllerFiles(): array {
		return array(
			DIR_CATALOG . 'controller/startup/seo_url.php',
			DIR_CATALOG . 'controller/startup/seo_pro.php',
		);
	}

	private function getSeoRoutesPatchInsert(): string {
		return <<<'PATCH'

				// ELeads SEO Routes Start
				$route = trim((string)$this->request->get['_route_'], '/');
				if (preg_match('#^eleads-yml/([a-zA-Z_-]+)\.xml$#', $route, $m)) {
					$this->request->get['route'] = 'extension/eleads/module/eleads';
					$this->request->get['lang'] = $m[1];
					return null;
				}
				if ($route === 'eleads-yml/api/feeds') {
					$this->request->get['route'] = 'extension/eleads/module/eleads.feeds';
					return null;
				}
				if ($route === 'e-search/api/sitemap-sync') {
					$this->request->get['route'] = 'extension/eleads/module/eleads.sitemapSync';
					return null;
				}
				if ($route === 'e-search/api/languages') {
					$this->request->get['route'] = 'extension/eleads/module/eleads.languages';
					return null;
				}
				if (preg_match('#^([a-zA-Z0-9_-]+)/e-search/(.+)$#', $route, $m)) {
					$lang = $m[1];
					$tail = $m[2];
					if ($tail !== '' && strpos($tail, 'api/') !== 0 && $tail !== 'sitemap.xml') {
						$this->request->get['route'] = 'extension/eleads/module/eleads.seoPage';
						$this->request->get['slug'] = $tail;
						$this->request->get['lang'] = $lang;
						return null;
					}
				}
				if (strpos($route, 'e-search/') === 0) {
					$tail = substr($route, strlen('e-search/'));
					if ($tail !== '' && strpos($tail, 'api/') !== 0 && $tail !== 'sitemap.xml') {
						$this->request->get['route'] = 'extension/eleads/module/eleads.seoPage';
						$this->request->get['slug'] = $tail;
						return null;
					}
				}
				// ELeads SEO Routes End
PATCH;
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
		require_once DIR_EXTENSION . 'eleads/system/library/eleads/sync_manager.php';
		$manager = new \EleadsSyncManager($this->registry);
		$manager->syncProduct((int)$product_id, (string)$mode);
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
		require_once DIR_EXTENSION . 'eleads/system/library/eleads/seo_sitemap_manager.php';
		$manager = new \EleadsSeoSitemapManager($this->registry);
		$manager->sync($enabled, $api_key, $settings);
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
		require_once DIR_EXTENSION . 'eleads/system/library/eleads/widget_tag_manager.php';
		$manager = new \EleadsWidgetTagManager($this->registry);
		$manager->sync($enabled, $api_key);
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
