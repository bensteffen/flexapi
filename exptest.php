<?php

include_once __DIR__ . '/database/FilterParser.php';
include_once __DIR__ . '/database/sql.php';

// // $testStr = "[abc,not,0]and(![xyz,gr,2]or!([name,eq,floderflo]and[id,1]))";
// $testStr = "[date,not,mod]and(![xyz,gr,2]or!([permission.name,eq,'floderflo...bs']and[id,1]))";
// // $testStr = "[abc,eq,0]and[xyz,gr,2]or![name,eq,floderflo]";

// $testExp = FilterParser::parseQueryString($testStr);
// $testExp->setCreator(new SqlCreator());
// $query = $testExp->toQuery();
// echo $query;


$fromArr = FilterParser::parseFilterArray([
    [ 'id' => 1 ],
    [ 'permission.name' => 'abc' ],
    [ 'date' => '2018-05' ]
]);
$fromArr->setCreator(new SqlCreator());
$queryfromArr = $fromArr->toQuery();
echo $queryfromArr;

?>