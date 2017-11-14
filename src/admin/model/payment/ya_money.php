<?php

class ModelPaymentYaMoney extends Model
{
    private $paymentMethods;

    /**
     * @var Config
     */
    private $config;

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

        $fileName = DIR_DOWNLOAD . '/update_module/' . $fileName;
        $archive = new \YandexMoney\Updater\Archive\BackupZip($fileName, $dir);
        $archive->backup($root);
    }

    public function restoreBackup($fileName)
    {
        $this->loadClasses();

        $fileName = DIR_DOWNLOAD . '/update_module/' . $fileName;
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
        $fileName = DIR_DOWNLOAD . '/update_module/' . str_replace(array('/', '\\'), array('', ''), $fileName);
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
}
