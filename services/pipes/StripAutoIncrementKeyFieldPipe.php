<?php

include_once __DIR__ . '/IfEntityDataPipe.php';

class StripAutoIncrementKeyFieldPipe implements IfEntityDataPipe {
    public function transform($entity, $data, $index = null, $dataArray = null) {
        $autoIncremFields = array_filter($entity->getFieldSet(), function($field) {
            return array_key_exists('autoIncrement', $field) && $field['autoIncrement'] == true;
        });
        if (count($autoIncremFields)) {
            foreach ($autoIncremFields as $field) {
                unset($data[$field['name']]);
            }
        }
        return $data;
    }
}
