<?php

namespace UniEngine\Utils\Migrations;

class Migrator {
    private $rootPath;

    //  $options:
    //      - rootPath (string)
    //
    function __construct($options) {
        $this->rootPath = $options["rootPath"];
    }

    public function runMigration($options) {
        $migrations = $this->loadMigrationEntries();

        $latestAppliedID;

        try {
            $latestAppliedID = $this->loadLastMigrationID();
        } catch (FileMissingException $exception) {
            $latestAppliedID = null;
        }

        if ($latestAppliedID !== null) {
            $this->printLog("> Last applied migration ID: \"{$latestAppliedID}\"");
        } else {
            $this->printLog("> No \"config/latest-migration\" file found, assuming no migrations have been applied yet");
        }

        $migrations = $this->getMigrationsNewerThan($migrations, $latestAppliedID);

        $lastAppliedID = $this->applyMigrations($migrations, $options);

        if ($lastAppliedID !== null) {
            $this->saveMigrationID($lastAppliedID);
        } else {
            $this->printLog("> No migrations applied");
        }
    }

    private function getRealPath($path) {
        return ($this->rootPath . $path);
    }

    private function loadMigrationEntries() {
        $migrationsPath = $this->getRealPath("./migrations");

        $list = scandir($migrationsPath);

        if ($list === false) {
            throw new FileIOException("Could not load migrations directory");
        }

        $migrationFiles = array_filter($list, function ($file) {
            // Migration scripts' filenames follow this pattern:
            // <4 digit year><2 digit month><2 digit day>_<2 digit hour><2 digit minute><2 digit second>_<short description>.php
            $isMatching = preg_match("/^\d{8}_\d{6}_.*?\.php$/", $file);

            return ($isMatching === 1);
        });

        return array_map(function ($file) {
            preg_match(
                "/^(\d{8}_\d{6})_(.*?)\.php$/",
                $file,
                $matches
            );

            $datetime = \DateTime::createFromFormat(
                "Ymd_His",
                $matches[1]
            );

            return [
                "filename" => $file,
                "id" => $datetime->format("Ymd_His"),
                "datetime" => $datetime,
                "desc" => $matches[2]
            ];
        }, $migrationFiles);
    }
}

?>
