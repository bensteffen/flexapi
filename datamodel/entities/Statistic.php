<?php

include_once __DIR__ . '/../DataEntity.php';

class Statistic extends DataEntity {

    public function __construct() {
        parent::__construct('statistic');

        $this->addField(
            ['name' => 'sample'    , 'type' => 'varchar', 'notNoll' => true, 'length' => 32, 'primary' => true]
        );
        $this->addField(
            ['name' => 'size'      , 'type' => 'int'    , 'notNoll' => false]
        );
        $this->addField(
            ['name' => 'averageAge', 'type' => 'int'    , 'notNoll' => false]
        );
    }

    public function observationUpdate($event) {
        if ($event['subjectName'] !== 'statistic') {
            $groups = $this->dataModel->read('group', [
                'references' => ['depth' => 1]
            ]);
            foreach($groups as $group) {
                $stats = $this->groupStatistic($group['name'], $group['members']);
                $this->dataModel->update('statistic', $stats);
            }
        }
    }

    public function groupStatistic($groupName, $members) {
        return [
            'sample' => $groupName,
            'size' => count($members),
            'averageAge' => $this->caclulateAverage($members, 'age')
        ];
    }

    public function caclulateAverage($data, $columnName) {
        $size = count($data);
        $col = array_values(array_column($data, $columnName));
        $colSum = 0;
        foreach($col as $c) { $colSum += $c; }
        if ($size > 0) {
            return $colSum/$size;
        }
        return null;
    }
}

?>
