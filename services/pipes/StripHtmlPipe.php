<?php

include_once __DIR__ . '/IfEntityDataPipe.php';

class StripHtmlPipe implements IfEntityDataPipe {
    public function transform($entity, $data) {
        foreach($data as $key => $value) {
            $field = $entity->getField($key);
            if ($field) {
                $allowedHtml = null;
                if (array_key_exists('allowedHtml', $field)) {
                    $allowedHtml = $field['allowedHtml'];
                }
                if (is_string($value)) {
                    $data[$key] = strip_tags($value, $allowedHtml);
                }
            } else {
                if (is_string($value)) {
                    $data[$key] = strip_tags($value);
                }
            }
        }
        return $data;
    }
}
