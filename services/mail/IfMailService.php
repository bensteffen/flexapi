<?php

interface IfMailService {
    public function send($to, $subject, $body, $altBody);
}
