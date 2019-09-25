<?php

include_once __DIR__ . '/IfVerificationService.php';
include_once __DIR__ . '/TokenVerificationEntity.php';

class TokenVerificationService implements IfVerficationService {
    private $settings = [];
    private $writeHTMLMail = null;
    private $writePlainMail = null;
    private $acModel = null;
    private $mailService = null;
    private $tokenService = null;
    private $subject = null;

    public function __construct($settings, $subject, $writeHTMLMail, $writePlainMail, $mailService, $tokenService) {
        $this->settings = $settings;
        $this->subject = $subject;
        $this->writeHTMLMail = $writeHTMLMail;
        $this->writePlainMail = $writePlainMail;
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

        $writeHTMLMail = $this->writeHTMLMail;
        $writePlainMail = $this->writePlainMail;

        $url = FlexAPI::get('frontendBaseUrl').'/#/token/'.$data[username] ."/". $token;
        $messageHTML = $writeHTMLMail($token, $url);
        $messagePlain = $writePlainMail($token, $url);

        $this->mailService->send($data['address'], $this->subject, $messageHTML, $messagePlain);

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
