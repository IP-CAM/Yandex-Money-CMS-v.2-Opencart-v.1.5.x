<?php

class ModelPaymentYaMoney extends Model
{
    private $paymentMethods;

    /**
     * @var Config
     */
    private $config;

    private $backupDirectory = 'yamodule/backup';
    private $versionDirectory = 'yamodule/updates';
    private $downloadDirectory = 'yamodule';

    public function init($config)
    {
        $this->config = $config;
        return $this;
    }

    public function install()
    {
        $this->log('info', 'install ya_money module');
        $this->db->query('
            CREATE TABLE IF NOT EXISTS `' . DB_PREFIX . 'ya_money_payment` (
                `order_id`          INTEGER  NOT NULL,
                `payment_id`        CHAR(36) NOT NULL,
                `status`            ENUM(\'pending\', \'waiting_for_capture\', \'succeeded\', \'canceled\') NOT NULL,
                `amount`            DECIMAL(11, 2) NOT NULL,
                `currency`          CHAR(3) NOT NULL,
                `payment_method_id` CHAR(36) NOT NULL,
                `paid`              ENUM(\'Y\', \'N\') NOT NULL,
                `created_at`        DATETIME NOT NULL,
                `captured_at`       DATETIME NOT NULL DEFAULT \'0000-00-00 00:00:00\',

                CONSTRAINT `' . DB_PREFIX . 'ya_money_payment_pk` PRIMARY KEY (`order_id`),
                CONSTRAINT `' . DB_PREFIX . 'ya_money_payment_unq_payment_id` UNIQUE (`payment_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=UTF8 COLLATE=utf8_general_ci;
        ');
    }

    public function uninstall()
    {
        $this->log('info', 'uninstall ya_money module');
        $this->db->query('DROP TABLE IF EXISTS `' . DB_PREFIX . 'ya_money_payment`;');
    }

    public function log($level, $message, $context = null)
    {
        if ($this->config === null || $this->config->get('ya_kassa_debug_mode')) {
            $log = new Log('yandex-money.log');
            $search = array();
            $replace = array();
            if (!empty($context)) {
                foreach ($context as $key => $value) {
                    $search[] = '{' . $key . '}';
                    $replace[] = $value;
                }
            }
            if (empty($search)) {
                $log->write('[' . $level . '] - ' . $message);
            } else {
                $log->write('[' . $level . '] - ' . str_replace($search, $replace, $message));
            }
        }
    }

    /**
     * @return YandexMoneyPaymentMethod[]
     */
    public function getPaymentMethods()
    {
        if ($this->paymentMethods === null) {
            $path = dirname(__FILE__) . '/../../../catalog/model/payment/yamoney/';
            require_once $path . 'autoload.php';
            require_once $path . 'YandexMoneyPaymentMethod.php';
            require_once $path . 'YandexMoneyPaymentKassa.php';
            require_once $path . 'YandexMoneyPaymentMoney.php';
            require_once $path . 'YandexMoneyPaymentBilling.php';
            $this->paymentMethods = array(
                YandexMoneyPaymentMethod::MODE_NONE    => new YandexMoneyPaymentMethod($this->config),
                YandexMoneyPaymentMethod::MODE_KASSA   => new YandexMoneyPaymentKassa($this->config),
                YandexMoneyPaymentMethod::MODE_MONEY   => new YandexMoneyPaymentMoney($this->config),
                YandexMoneyPaymentMethod::MODE_BILLING => new YandexMoneyPaymentBilling($this->config),
            );
        }
        return $this->paymentMethods;
    }

    /**
     * @param int $type
     * @return YandexMoneyPaymentMethod
     */
    public function getPaymentMethod($type)
    {
        $methods = $this->getPaymentMethods();
        if (array_key_exists($type, $methods)) {
            return $methods[$type];
        }
        echo 'Get mayment method#' . $type . PHP_EOL;
        return $methods[0];
    }

    public function getBackupList()
    {
        $result = array();

        $dir = DIR_DOWNLOAD . '/update_module';
        $handle = opendir($dir);
        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $ext = pathinfo($entry, PATHINFO_EXTENSION);
            if ($ext === 'zip') {
                $backup = array(
                    'name'    => pathinfo($entry, PATHINFO_FILENAME) . '.zip',
                    'size'    => $this->formatSize(filesize($dir . '/' . $entry)),
                );
                $parts = explode('-', $backup['name'], 3);
                $backup['version'] = $parts[0];
                $backup['time'] = $parts[1];
                $backup['date'] = date('d.m.Y H:i:s', $parts[1]);
                $backup['hash'] = $parts[2];
                $result[] = $backup;
            }
        }
        return $result;
    }

    public function createBackup($version)
    {
        $this->loadClasses();

        $sourceDirectory = dirname(realpath(DIR_CATALOG));
        $reader = new \YandexMoney\Updater\ProjectStructure\ProjectStructureReader();
        $root = $reader->readFile(dirname(__FILE__) . '/yamoney/opencart.map', $sourceDirectory);

        $dir = $version . '-' . time();
        $fileName = $dir . '-' . uniqid() . '.zip';

        $dir = DIR_DOWNLOAD . '/' . $this->backupDirectory;
        if (!file_exists($dir)) {
            if (!mkdir($dir)) {
                $this->log('error', 'Failed to create backup directory: ' . $dir);
                return false;
            }
        }

        try {
            $fileName = $dir . '/' . $fileName;
            $archive = new \YandexMoney\Updater\Archive\BackupZip($fileName, $dir);
            $archive->backup($root);
        } catch (Exception $e) {
            $this->log('error', 'Failed to create backup: ' . $e->getMessage());
            return false;
        }
        return true;
    }

    public function restoreBackup($fileName)
    {
        $this->loadClasses();

        $fileName = DIR_DOWNLOAD . '/' . $this->backupDirectory . '/' . $fileName;
        if (!file_exists($fileName)) {
            $this->log('error', 'File "' . $fileName . '" not exists');
            return false;
        }

        try {
            $sourceDirectory = dirname(realpath(DIR_CATALOG));
            $archive = new \YandexMoney\Updater\Archive\RestoreZip($fileName);
            $archive->restore('file_map.map', $sourceDirectory);
        } catch (Exception $e) {
            $this->log('error', $e->getMessage());
            if ($e->getPrevious() !== null) {
                $this->log('error', $e->getPrevious()->getMessage());
            }
            return false;
        }
        return true;
    }

    public function removeBackup($fileName)
    {
        $fileName = DIR_DOWNLOAD . '/' . $this->backupDirectory . '/' . str_replace(array('/', '\\'), array('', ''), $fileName);
        if (!file_exists($fileName)) {
            $this->log('error', 'File "' . $fileName . '" not exists');
            return false;
        }

        if (!unlink($fileName) || file_exists($fileName)) {
            $this->log('error', 'Failed to unlink file "' . $fileName . '"');
            return false;
        }
        return true;
    }

    public function checkModuleVersion($useCache = true)
    {
        $this->loadClasses();

        $file = DIR_DOWNLOAD . '/' . $this->downloadDirectory . '/version_log.txt';
        if ($useCache) {
            if (file_exists($file)) {
                $content = preg_replace('/\s+/', '', file_get_contents($file));
                if (!empty($content)) {
                    $parts = explode(':', $content);
                    if (count($parts) === 2) {
                        if (time() - $parts[1] < 3600 * 8) {
                            return array(
                                'tag'     => $parts[0],
                                'version' => preg_replace('/[^\d\.]+/', '', $parts[0]),
                                'time'    => $parts[1],
                                'date'    => $this->dateDiffToString($parts[1]),
                            );
                        }
                    }
                }
            }
        }

        $connector = new GitHubConnector();
        $version = $connector->getLatestRelease('actofgod/oc15-sdk-test');
        if (empty($version)) {
            return array();
        }

        $cache = $version . ':' . time();
        file_put_contents($file, $cache);

        return array(
            'tag'     => $version,
            'version' => preg_replace('/[^\d\.]+/', '', $version),
            'time'    => time(),
            'date'    => $this->dateDiffToString(time()),
        );
    }

    public function downloadLastVersion($tag, $useCache = true)
    {
        $this->loadClasses();

        $dir = DIR_DOWNLOAD . '/' . $this->versionDirectory;
        if (!file_exists($dir)) {
            if (!mkdir($dir)) {
                $this->log('error', 'Не удалось создать директорию ' . $dir);
                return false;
            }
        } elseif ($useCache) {
            $fileName = $dir . '/' . $tag . '.zip';
            if (file_exists($fileName)) {
                return $fileName;
            }
        }

        $connector = new GitHubConnector();
        $fileName = $connector->downloadRelease('actofgod/oc15-sdk-test', $tag, $dir);
        if (empty($fileName)) {
            $this->log('error', 'Не удалось загрузить архив с обновлением');
            return false;
        }

        return $fileName;
    }

    public function unpackLastVersion($fileName)
    {
        if (!file_exists($fileName)) {
            $this->log('error', 'File "' . $fileName . '" not exists');
            return false;
        }

        try {
            $sourceDirectory = dirname(realpath(DIR_CATALOG));
            $archive = new \YandexMoney\Updater\Archive\RestoreZip($fileName);
            $archive->restore('opencart.map', $sourceDirectory);
        } catch (Exception $e) {
            $this->log('error', $e->getMessage());
            if ($e->getPrevious() !== null) {
                $this->log('error', $e->getPrevious()->getMessage());
            }
            return false;
        }
        return true;
    }

    public function getChangeLog($currentVersion, $newVersion)
    {
        $connector = new GitHubConnector();

        $dir = DIR_DOWNLOAD . '/' . $this->downloadDirectory;
        $newChangeLog = $dir . '/CHANGELOG-' . $newVersion . '.md';
        if (!file_exists($newChangeLog)) {
            $fileName = $connector->downloadLatestChangeLog('actofgod/oc15-sdk-test', $dir);
            if (!empty($fileName)) {
                rename($dir . '/' . $fileName, $newChangeLog);
            }
        }

        $oldChangeLog = $dir . '/CHANGELOG-' . $currentVersion . '.md';
        if (!file_exists($oldChangeLog)) {
            $fileName = $connector->downloadLatestChangeLog('actofgod/oc15-sdk-test', $dir);
            if (!empty($fileName)) {
                rename($dir . '/' . $fileName, $oldChangeLog);
            }
        }

        $result = '';
        if (file_exists($newChangeLog)) {
            $result = $connector->diffChangeLog($oldChangeLog, $newChangeLog);
        }
        return $result;
    }

    private function formatSize($size)
    {
        static $sizes = array(
            'B', 'kB', 'MB', 'GB', 'TB',
        );

        $i = 0;
        while ($size > 1024) {
            $size /= 1024.0;
            $i++;
        }
        return number_format($size, 2, '.', ',') . '&nbsp;' . $sizes[$i];
    }

    private function loadClasses()
    {
        if (!class_exists('GitHubConnector')) {
            $path = dirname(__FILE__) . '/yamoney/Updater/';
            require_once $path . 'GitHubConnector.php';
            require_once $path . 'ProjectStructure/EntryInterface.php';
            require_once $path . 'ProjectStructure/DirectoryEntryInterface.php';
            require_once $path . 'ProjectStructure/FileEntryInterface.php';
            require_once $path . 'ProjectStructure/AbstractEntry.php';
            require_once $path . 'ProjectStructure/DirectoryEntry.php';
            require_once $path . 'ProjectStructure/FileEntry.php';
            require_once $path . 'ProjectStructure/ProjectStructureReader.php';
            require_once $path . 'ProjectStructure/ProjectStructureWriter.php';
            require_once $path . 'ProjectStructure/RootDirectory.php';
            require_once $path . 'Archive/BackupZip.php';
            require_once $path . 'Archive/RestoreZip.php';
        }
    }

    private function dateDiffToString($timestamp)
    {
        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'только что';
        } elseif ($diff < 120) {
            return 'минуту назад';
        } elseif ($diff < 180) {
            return 'две минуты назад';
        } elseif ($diff < 300) {
            return 'пару минут назад';
        }
        return date('d.m.Y H:i:s', $timestamp);
    }
}
