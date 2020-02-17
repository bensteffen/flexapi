<?php

interface IfEntityDataPipe {
    public function transform($entity, $data, $index, $dataArray);
}
