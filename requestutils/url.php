<?php

include_once __DIR__ . "/../../bs-php-utils/utils.php";

function parseArrayString($str) {
    if (preg_match("/^\[.*\]$/", $str)) {
        $str = substr($str,1,strlen($str)-2);
        $chars = str_split($str);
        $inQuotes = false;
        $array = [];
        $buffer = '';
        foreach ($chars as $char) {
            $isQuote = $char === "'";
            $isComma = $char === ',';
            if ($isQuote) {
                $inQuotes = !$inQuotes;
            }
            if (!$isComma || $inQuotes) {
                $buffer .= $char;
            } else {
                array_push($array, $buffer);
                $buffer = '';
            }
        }
        array_push($array, $buffer);
        // foreach($array as $x) {
        //     if (!preg_match("/'.*'/", $x) && preg_match("/[]/", $x)) {
        //         return null;
        //     }
        // }
        return $array;
    }
    return null;
}

function parseAssocString($str) {
    preg_match("/\[([\w]+,[\w]+)(;([\w]+,[\w]+))*\]/", $str ,$result);
    if (count($result) > 0) {
        $content = preg_replace("/\[|\]/", "", $result[0]);
        $pairs = explode(';', $content);
        $assocArray = [];
        foreach($pairs as $pair) {
            $pairArray = parseArrayString("[$pair]");
            $assocArray[$pairArray[0]] = $pairArray[1];
        }
        return $assocArray;
    }
    return null;
}

function parseUrlParameters($queries) {
    $do = 'read';
    if (array_key_exists('do', $queries)) {
        $do = $queries['do'];
    }

    if (!array_key_exists('entity', $queries)) {
        throw(new Exception("URL parameter 'entity' not set.", 400));
    }
    $entityName = $queries['entity'];

    $filter = '';
    if (array_key_exists('filter', $queries)) {
        $filter = $queries['filter'];
    }

    $select = [];
    if (array_key_exists('select', $queries)) {
        $select = explode(',', $queries['select']);
    }

    $refs = [];
    if (array_key_exists('refs', $queries)) {
        $refs = getReferenceParameters($queries['refs']);
    }
    $refs = setFieldDefault($refs, 'format', 'url');

    $flatten = false;
    if (array_key_exists('flatten', $queries)) {
        if ($queries['flatten'] === 'singleResult' || $queries['flatten'] === 'singleField') {
            $flatten = $queries['flatten'];
        }
    }

    return [
        'do'      => $do,
        'entity'  => $entityName,
        'filter'  => $filter,
        'select'  => $select,
        'refs'    => $refs,
        'flatten' => $flatten
    ];
}

// function getSelectParameter($query) {
//     $select = [];

//     preg_match_all("/(and|or)?\([\w\s\-_]+,[\w\s\-_]+,?[\w\s\-_]*\)/", $query, $selectQuery);  
// }

function getReferenceParameters($query) {
    $refParameters = [];
    preg_match_all("/\([\w]+,[\w]+\)/", $query, $refQuery);
    $refQuery = $refQuery[0];
    foreach ($refQuery as $q) {
        $q = explode(",", preg_replace("/\(|\)/", "", $q));
        $refParameters[$q[0]] = $q[1];
    }
    return $refParameters;
}

