<?php

interface IfJwtService {
    public function encode($data);

    public function decode($token);
}
