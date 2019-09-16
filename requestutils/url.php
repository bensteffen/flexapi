<?php

include_once __DIR__ . "/../../../bensteffen/bs-php-utils/utils.php";

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
            $assocArray[$pairArray[0]] = json_decode($pairArray[1], JSON_NUMERIC_CHECK);
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

    $sort = [];
    if (array_key_exists('sort', $queries)) {
        $values = parseParameterValue($queries['sort']);
        foreach($values as $value) {
            $sortItem = ['by' => $value[0]];
            if (count($value) === 2) {
                $sortItem['direction'] = $value[1];
            }
            array_push($sort, $sortItem);
        }
    }

    $pages = [];
    if (array_key_exists('pages', $queries)) {
        $pages = pairs2assoc(parseParameterValue($queries['pages']));
    }

    $flatten = false;
    if (array_key_exists('flatten', $queries)) {
        if ($queries['flatten'] === 'singleResult' || $queries['flatten'] === 'singleField') {
            $flatten = $queries['flatten'];
        }
    }

    if (array_key_exists('nores', $queries)) {
        $noresult = json_decode($queries['nores']);
    } else {
        $noresult = null;
    }

    if (array_key_exists('onlyOwn', $queries)) {
        $onlyOwn = true;
    } else {
        $onlyOwn = false;
    }

    return [
        'do'       => $do,
        'entity'   => $entityName,
        'filter'   => $filter,
        'select'   => $select,
        'refs'     => $refs,
        'flatten'  => $flatten,
        'nores'    => $noresult,
        'sort'     => $sort,
        'pages'    => $pages,
        'onlyOwn'  => $onlyOwn
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


function parseParameterValue($queryStr) {
    preg_match_all("/(\([\w]+(,[\w]+)*?\))+/", $queryStr, $result);
    if (!$result) {
        return null;
    }
    $values = [];
    $result = $result[0][0];
    $result = explode(')(', $result);
    foreach($result as $resultItem) {
        $resultItem = explode(",", preg_replace("/\(|\)/", "", $resultItem));
        array_push($values, $resultItem);
    }
    return $values;
}

function pairs2assoc($pairs) {
    $assoc = [];
    foreach($pairs as $pair) {
        if (count($pair > 1)) {
            $assoc[$pair[0]] = json_decode($pair[1], JSON_NUMERIC_CHECK);
        }
    }
    return $assoc;
}
