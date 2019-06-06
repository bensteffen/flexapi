<?php

interface IfVerficationService {
    public function setDataModel($dataModel);

    public function startVerification($data);

    public function finishVerification($data);
}
