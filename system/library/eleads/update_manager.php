<?php
class EleadsUpdateManager {
	private $registry;
	private $loader;

	public function __construct($registry) {
		$this->registry = $registry;
		$this->loader = $registry->get('load');
	}

	public function getUpdateInfo(): array {
		require_once DIR_EXTENSION . 'eleads/system/library/eleads/bootstrap.php';
		return \EleadsUpdateHelper::getUpdateInfo();
	}

	public function updateToLatest(): array {
		require_once DIR_EXTENSION . 'eleads/system/library/eleads/bootstrap.php';

		$root_path = rtrim(dirname(DIR_APPLICATION), '/\\') . '/';
		$result = \EleadsUpdateHelper::updateToLatest($root_path);
		if (!empty($result['ok'])) {
			$this->refreshAfterUpdate();
		}

		return $result;
	}

	private function refreshAfterUpdate(): void {
		$this->loader->controller('marketplace/modification.refresh');
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
}
