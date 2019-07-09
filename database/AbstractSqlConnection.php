<?php

class AbstractSqlConnection {
    public function prepareData($entity, $data) {
        $objectKeys = extractByKey('name', array_filter($entity->getFieldSet(), function($f) {
            return $f['type'] === 'object';
        }));
        foreach(array_intersect($objectKeys, array_keys($data)) as $key) {
            $data[$key] = jsenc($data[$key]);
        }
        return $data;
    }
    
    public function finishData($entity, $data) {
        $objectKeys = extractByKey('name', array_filter($entity->getFieldSet(), function($f) {
            return $f['type'] === 'object';
        }));
        foreach($data as $i => $d) {
            foreach(array_intersect($objectKeys, array_keys($d)) as $key) {
                $data[$i][$key] = (array) json_decode($d[$key]);
            }
        }
        $objectKeys = extractByKey('name', array_filter($entity->getFieldSet(), function($f) {
            return $f['type'] === 'bool' || $f['type'] === 'boolean';
        }));
        foreach($data as $i => $d) {
            foreach(array_intersect($objectKeys, array_keys($d)) as $key) {
                $v = false;
                if ($data[$i][$key]) { $v = true; }
                $data[$i][$key] = $v;
            }
        }
        return $data;
    }
}