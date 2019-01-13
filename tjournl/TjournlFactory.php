<?php

include_once __DIR__ . '/TjournlDataModel.php';
include_once __DIR__ . '/../datamodel/DataModelFactory.php';

class TjournlFactory extends DataModelFactory {
    public function buildDataModel() {
        return tjournl();
    }
}

?>