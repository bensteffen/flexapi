<?php

function parseUrlParameters($queries) {
    $do = 'read';
    if (array_key_exists('do', $queries)) {
        $do = $queries['do'];
    }

    if (!array_key_exists('entity', $queries)) {
        throw(new Exception("URL parameter 'entity' not set.", 400));
    }
    $entityName = $queries['entity'];

    $filter = [];
    if (array_key_exists('filter', $queries)) {
        $filter = getFilterParameter($queries['filter']);
    }

    $select = [];
    if (array_key_exists('select', $queries)) {
        $select = explode(',', $queries['select']);
    }

    $refs = [];
    if (array_key_exists('refs', $queries)) {
        $refs = getReferenceParameters($queries['refs']);
    }

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

function getFilterParameter($query) {
    $filters = [];

    preg_match_all("/(and|or)?\([\w\s\-_]+,[\w\s\-_]+,?[\w\s\-_]*\)/", $query, $filterQuery);
    $filterQuery = $filterQuery[0];
    foreach ($filterQuery as $q) {
        $concatOperator = "AND";
        preg_match("/^(and|or)?/", $q, $concatMatch);
        if (count($concatMatch) > 0  && $concatMatch[0] !== "") {
            if (strtolower($concatMatch[0]) === "or") {
                $concatOperator = "OR";
            }
            $q = preg_replace("/$concatMatch[0]/", "", $q);
        }
        $q = explode(",", preg_replace("/\(|\)/", "", $q));
        $field = trim($q[0]);
        if (count($q) === 2) {
            $operator = "eq";
            $value = $q[1];
        } elseif (count($q) === 3) {
            $operator = trim($q[1]);
            $value = $q[2];
        }
        $filters[$field] = ["concatOperator" => $concatOperator, "operator" => $operator, "value" => $value];
    }

    return $filters;
}

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

?>