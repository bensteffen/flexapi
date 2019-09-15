<?php

include_once __DIR__ . '/IfVerificationService.php';
include_once __DIR__ . '/TokenVerificationEntity.php';

class TokenVerificationService implements IfVerficationService {
    private $settings = [];
    private $writeMail = null;
    private $acModel = null;
    private $mailService = null;
    private $tokenService = null;

    public function __construct($settings, $writeMail, $mailService, $tokenService) {
        $this->settings = $settings;
        $this->writeMail = $writeMail;
        $this->mailService = $mailService;
        $this->tokenService = $tokenService;
    }

    public function setDataModel($dataModel) {
        $this->acModel = $dataModel;
        $this->acModel->addEntity(new UserVerificationToken());
    }

    public function startVerification($data) {
        $token = $this->tokenService->generate();

        // gnereiere Neues, falls generiertes Token zufälligerweise in Verwendung ist:
        while ($this->acModel->read('userverificationtoken', ['filter' => [ 'token' => $token ] ])) {
            $token = $this->tokenService->generate();
        }

        $this->acModel->insert('userverificationtoken', [
            'user' => $data['username'],
            'token' => $token,
            'expires' => time() + $this->settings['validityDuration']
        ]);

        $writeMail = $this->writeMail;
        $message = $writeMail($token);

        $this->mailService->send($data['address'], 'Aktivierungs-Token', $message);

        return [
            'verificationMailSent' => true
        ];
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
