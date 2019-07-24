<?php

include_once __DIR__ . '/IfEntityDataPipe.php';

class StripHtmlPipe implements IfEntityDataPipe {
    public function transform($entity, $data) {
        foreach($data as $key => $value) {
            $field = $entity->getField($key);
            $allowedHtml = null;
            if (array_key_exists('allowedHtml', $field)) {
                $allowedHtml = $field['allowedHtml'];
            }
            $data[$key] = strip_tags($value, $allowedHtml);
        }
        return $data;
    }
}
