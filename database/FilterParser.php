<?php

include_once __DIR__ . "/../../bs-php-utils/utils.php";
include_once __DIR__ . '/../requestutils/url.php';
include_once __DIR__ . '/query.php';

class FilterParser {

    public $defaultEntity = null;
    public $defaultDatabase = null;
    public $dataModel = null;
    protected $referencedEntities = [];
    protected $tree;

    public function parseFilter($filter) {
        $this->referencedEntities = [];
        if (is_array($filter)) {
            if (array_key_exists('tree', $filter) && array_key_exists('references', $filter)) {
                return $filter;
            }
            $tree =  $this->parseFilterArray($filter);
        }
        if (is_string($filter)) {
            $tree = $this->parseQueryString($filter);
        }
        return [
            'tree' => $tree,
            'references' => $this->referencedEntities
        ];
        // throw(new Exception('Cannot convert filter to query.', 500));
    }

    protected function parseFilterArray($filterArray) {
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

    protected function parseQueryString($str) {
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
        $colRes = FilterParser::parseColumn($str, $this->dataModel, $this->defaultEntity, $this->defaultDatabase);
        if ($colRes) {
            if (count($colRes) === 4) {
                $refId = 'ref'.(count($this->referencedEntities)+1);
                $ref = (array) $colRes[3];
                $this->referencedEntities[$refId] = $ref;
                return new QueryColumn($colRes[2], $colRes[1], $colRes[0], [$refId => $ref]);
            }
            return new QueryColumn($colRes[2], $colRes[1], $colRes[0]);
        }
        throw(new Exception("Cannot parse '$str'"));
    }

    protected static function parseNotExp($str) {
        if (preg_match("/^!\(.*\)$/", $str)) {
            return substr($str, 1, strlen($str)-1);
        }
        if (preg_match("/^!\[.*\]$/", $str)) {
            return substr($str, 1, strlen($str)-1);
        }
        return null;
    }

    protected static function parseGroupExp($str) {
        if (preg_match("/^\(.*\)$/", $str)) {
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
        if (preg_match("/^'.*'$/", $str)) {
            return substr($str, 1, strlen($str)-2);
        }
        if (preg_match("/^[-+]?[0-9]*[.]?[0-9]+$/", $str)) {
            return $str;
        }
        return null;
    }

    protected static function parseColumn($str, $dataModel, $defaultEntity = null, $defaultDatabase = null) {
        if (preg_match("/^([a-zA-Z][\w_]+\.)*[a-zA-Z][\w_]*$/", $str)) {
            $referencedEntities = [];
            $dotSplit = explode('.', $str);
            $n = count($dotSplit);
            if ($n === 1 || !$dataModel) {
                return [$defaultDatabase, $defaultEntity, $dotSplit[0]];
            }
            $currentEntity = $defaultEntity;
            foreach (array_slice($dotSplit, 0, $n-1) as $refName) {
                $referencedEntityName = $dataModel->getReferenceSet()->fieldToEntityName($currentEntity, $refName);
                if ($referencedEntityName) {
                    array_push($referencedEntities, [
                        'referencingEntity' => $currentEntity,
                        'referencedEntity' => $referencedEntityName,
                        'referenceField' => $refName,
                        'referenceKeyName' => $dataModel->getEntity($referencedEntityName)->uniqueKey()
                    ]);
                    $currentEntity = $referencedEntityName;
                } else {
                    throw(new Exception(sprintf('There is no reference %s.%s', $currentEntity, $refName), 400));
                }
            }
            return [$defaultDatabase, $dotSplit[$n-2], $dotSplit[$n-1], $referencedEntities];
        }
    }
}

