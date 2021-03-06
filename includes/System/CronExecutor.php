<?php
namespace App\System;

use App\Services\ExpiredUserServiceService;
use App\Support\Database;
use App\Support\FileSystemContract;
use App\Support\Path;

class CronExecutor
{
    /** @var Database */
    private $db;

    /** @var Settings */
    private $settings;

    /** @var Path */
    private $path;

    /** @var FileSystemContract */
    private $fileSystem;

    /** @var ExpiredUserServiceService */
    private $expiredUserServiceService;

    public function __construct(
        Database $db,
        Settings $settings,
        Path $path,
        FileSystemContract $fileSystem,
        ExpiredUserServiceService $expiredServiceService
    ) {
        $this->db = $db;
        $this->settings = $settings;
        $this->path = $path;
        $this->fileSystem = $fileSystem;
        $this->expiredUserServiceService = $expiredServiceService;
    }

    public function run()
    {
        $this->expiredUserServiceService->deleteExpiredUserServices();

        // Remove old logs
        if (intval($this->settings['delete_logs']) != 0) {
            $this->db
                ->statement(
                    "DELETE FROM `ss_logs` " . "WHERE `timestamp` < DATE_SUB(NOW(), INTERVAL ? DAY)"
                )
                ->execute([$this->settings['delete_logs']]);
        }

        // Remove files older than 30 days from data/transfers
        $path = $this->path->to('data/transfers');
        foreach ($this->fileSystem->scanDirectory($path) as $file) {
            $filepath = rtrim($path, "/") . "/" . ltrim($file, "/");
            if (filectime($filepath) < time() - 60 * 60 * 24 * 30) {
                $this->fileSystem->delete($filepath);
            }
        }
        unset($path);
    }
}
