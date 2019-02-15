<?php

include_once __DIR__ . "/../../bs-php-utils/utils.php";
include_once __DIR__ . '/../requestutils/url.php';
include_once __DIR__ . '/query.php';

class FilterParser {

    public $defaultEntity = null;
    public $defaultDatabase = null;

    public function parseFilter($filter) {
        if (is_array($filter)) {
            return FilterParser::parseFilterArray($filter);
        }
        if (is_string($filter)) {
            return FilterParser::parseQueryString($filter);
        }
        return $filter;
        // throw(new Exception('Cannot convert filter to query.', 500));
    }

    public function parseFilterArray($filterArray) {
        if (count($filterArray) === 0) {
            return new QueryVoid();
        }
        list($column, $value) = assocShift($filterArray);
        $colRes = FilterParser::parseColumn($column, $this->defaultEntity, $this->defaultDatabase);
        $queryCol = new QueryColumn($colRes[2], $colRes[1], $colRes[0]);
        $cond = new QueryCondition($queryCol, new QueryValue($value));

        if (count($filterArray) > 0) {
            return new QueryAnd($cond, FilterParser::parseFilterArray($filterArray));
        } else {
            return $cond;
        }
    }

    public function parseQueryString($str) {
        if (strlen($str) === 0) {
            return new QueryVoid();
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
        $groupRes = FilterParser::parseGroupExp($str);
        if ($groupRes) {
            return new QueryGroup(FilterParser::parseQueryString($groupRes));
        }
        $notRes = FilterParser::parseNotExp($str);
        if ($notRes) {
            return new QueryNot(FilterParser::parseQueryString($notRes));
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
        if ($valueRes !== null) {
            return new QueryValue($valueRes);
        }
        $colRes = FilterParser::parseColumn($str, $this->defaultEntity, $this->defaultDatabase);
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
        $split = preg_split("/(and|or)/", $str, null, PREG_SPLIT_DELIM_CAPTURE);
        $n = count($split);
        for ($i = 1; $i < $n; $i += 2) {
            $operator = $split[$i];
            $left = implode(array_slice($split, 0, $i));
            $right = implode(array_slice($split, $i+1));
            $leftOk = FilterParser::isAndOrOperand($split[$i-1]) || FilterParser::isAndOrOperand($left);
            $rightOk = FilterParser::isAndOrOperand($split[$i+1]) || FilterParser::isAndOrOperand($right);
            if ($leftOk && $rightOk) {
                return [
                    'leftExpression' => $left,
                    'rightExpression' => $right,
                    'operator' => $operator
                ];
            }
        }
        return null;
    }

    protected static function isAndOrOperand($str) {
        return FilterParser::parseGroupExp($str)
            || FilterParser::parseCondExp($str)
            || FilterParser::parseNotExp($str);
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

    protected static function parseColumn($str, $defaultEntity = null, $defaultDatabase = null) {
        if (preg_match("/^([a-zA-Z][\w_]+\.)*[a-zA-Z][\w_]*$/", $str, $res)) {
            $elements = explode('.', $str);
            if (count($elements) < 2) {
                $elements = array_merge([$defaultDatabase, $defaultEntity], $elements);
            }
            if (count($elements) < 3) {
                $elements = array_merge([$defaultDatabase], $elements);
            }
            return $elements;
        }
    }
}

?>