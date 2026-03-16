<?php

namespace Blocks\Database\Command;

use Blocks\System\Component;
use Blocks\Filesystem\Filesystem;
use Blocks\Database\Syncer\SchemaSynchronizer;

class CheckDatabaseStructureCommand extends Component {

    public function get() {
        echo "Starting Database Structure Check..." . PHP_EOL;

        $synchronizer = new SchemaSynchronizer();
        $synchronizer->run();

        echo "Finished." . PHP_EOL;
    }
}
