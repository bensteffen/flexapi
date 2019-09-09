<?php

include_once __DIR__ . '/IfVerificationService.php';
include_once __DIR__ . '/TokenVerificationEntity.php';

class TokenVerificationService implements IfVerficationService {
    private $settings = [];
    private $acModel = null;
    private $mailService = null;
    private $tokenService = null;

    public function __construct($settings, $mailService, $tokenService) {
        $this->settings = $settings;
        $this->mailService = $mailService;
        $this->tokenService = $tokenService;
    }

    public function setDataModel($dataModel) {
        $this->acModel = $dataModel;
        $this->acModel->addEntity(new UserVerificationToken());
    }

    public function startVerification($data) {
        $token = $this->tokenService->generate();
        $this->acModel->insert('userverificationtoken', [
            'user' => $data['username'],
            'token' => $token,
            'expires' => time() + $this->settings['validityDuration']
        ]);

        $message = 'Hallo,<br>ihr Aktivierungs-Code lautet: '.$token.'<br>';

        $this->mailService->send($data['address'], 'Aktivierungs-Token', $message);
    }

    public function finishVerification($data) {
        $pendingVerification = $this->acModel->read('userverificationtoken', [
            'filter' => [ 'user' => $data['user'], 'token' => $data['token'] ],
            'flatten' => 'singleResult'
        ]);
        
        if (!$pendingVerification) {
            throw(new Exception('No pending verification found for given user and token.', 400));
        }

        $this->acModel->delete('userverificationtoken', ['id' => $pendingVerification['id']]);

        return [
            'verificationSuccessfull' => true,
            'username' => $data['user']
        ];        
    }
}
