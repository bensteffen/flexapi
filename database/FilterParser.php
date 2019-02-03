<?php

include_once __DIR__ . '/../requestutils/url.php';
include_once __DIR__ . '/query.php';

class FilterParser {
    public static function parseFilterArray($filterArray) {
        foreach (array_shift($filterArray) as $column => $value) {
            $colRes = FilterParser::parseColumn($column);
            $queryCol = new QueryColumn($colRes[2], $colRes[1], $colRes[0]);
            $cond = new QueryCondition($queryCol, new QueryValue($value));
        }
        if (count($filterArray) > 0) {
            return new QueryAnd($cond, FilterParser::parseFilterArray($filterArray));
        } else {
            return $cond;
        }
    }

    public static function parseQueryString($str) {
        $groupRes = FilterParser::parseGroupExp($str);
        if ($groupRes) {
            return new QueryGroup(FilterParser::parseQueryString($groupRes));
        }
        $notRes = FilterParser::parseNotExp($str);
        if ($notRes) {
            return new QueryNot(FilterParser::parseQueryString($notRes));
        }
        $andOrRes = FilterParser::parseAndOrExp($str);
        if ($andOrRes) {
            $exp1 = FilterParser::parseQueryString($andOrRes['leftExpression']);
            $exp2 = FilterParser::parseQueryString($andOrRes['rightExpression']);
            if ($andOrRes['operator'] === 'and') {
                return new QueryAnd($exp1, $exp2);
            } else {
                return new QueryOr($exp1, $exp2);
            }
        }
        $condRes = FilterParser::parseCondExp($str);
        if ($condRes) {
            if (count($condRes) === 2) {
                $expStr1 = $condRes[0];
                $expStr2 = $condRes[1];
                $op = 'eq';
            }
            if (count($condRes) === 3) {
                $expStr1 = $condRes[0];
                $expStr2 = $condRes[2];
                $op = $condRes[1];         
            }
            $exp1 = FilterParser::parseQueryString($expStr1);
            $exp2 = FilterParser::parseQueryString($expStr2);
            return new QueryCondition($exp1, $exp2, $op);
        }
        $valueRes = FilterParser::parseValue($str);
        if ($valueRes) {
            return new QueryValue($valueRes);
        }
        $colRes = FilterParser::parseColumn($str);
        if ($colRes) {
            return new QueryColumn($colRes[2], $colRes[1], $colRes[0]);
        }
        throw(new Exception("Cannot parse '$str'"));
    }

    protected static function parseNotExp($str) {
        if (preg_match("/^!\(.*\)$/", $str, $res)) {
            return substr($str, 1, strlen($str)-1);
        }
        if (preg_match("/^!\[.*\]$/", $str, $res)) {
            return substr($str, 1, strlen($str)-1);
        }
        return null;
    }

    protected static function parseGroupExp($str) {
        if (preg_match("/^\(.*\)$/", $str, $res)) {
            return substr($str, 1, strlen($str)-2);
        }
        return null;
    }

    protected static function parseAndOrExp($str) {
        $checks = [];
        foreach(['and','or'] as $operator) {
            foreach(['', '!'] as $not) {
                $checks = array_merge($checks, [
                    [ 'operator' => $operator, "pattern" => "/\]$operator$not\[/"],
                    [ 'operator' => $operator, "pattern" => "/\)$operator$not\(/"],
                    [ 'operator' => $operator, "pattern" => "/\)$operator$not\[/"],
                    [ 'operator' => $operator, "pattern" => "/\]$operator$not\(/"]
                ]);
            }
        }
        $l = strlen($str);
        $first = [ "offset" => $l ];
        foreach ($checks as $check) {
            $hit = preg_match($check['pattern'], $str, $res, PREG_OFFSET_CAPTURE);
            if ($hit && $res[0][1] < $first['offset']) {
                $first = [
                    'offset' => $res[0][1],
                    'operator' => $check['operator']
                ];
            }
        }
        if ($first['offset'] < $l) {
            $offset = $first['offset'];
            $gap = strlen($first['operator']);
            return [
                'leftExpression' => substr($str,0,$offset+1),
                'rightExpression' => substr($str,$offset+1+$gap),
                'operator' => $first['operator']
            ];
        }
        return null;
    }

    protected static function parseCondExp($str) {
        return parseArrayString($str);
    }

    protected static function parseValue($str) {
        if (preg_match("/^'.*'$/", $str, $res)) {
            return substr($str, 1, strlen($str)-2);
        }
        if (preg_match("/^[-+]?[0-9]*[.]?[0-9]+$/", $str, $res)) {
            return $str;
        }
        return null;
    }

    protected static function parseColumn($str) {
        if (preg_match("/^([a-z][\w_]+\.)*[a-z][\w_]*$/", $str, $res)) {
            $elements = explode('.', $str);
            if (count($elements) < 2) {
                $elements = array_merge([null, null], $elements);
            }
            if (count($elements) < 3) {
                $elements = array_merge([null], $elements);
            }
            return $elements;
        }
    }
}

?>