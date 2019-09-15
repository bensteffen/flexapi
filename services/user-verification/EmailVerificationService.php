<?php

include_once __DIR__ . '/IfVerificationService.php';
include_once __DIR__ . '/UserVerification.php';
include_once __DIR__ . '/../jwt/FirebaseJwtService.php';

class EmailVerificationService implements IfVerficationService {
    protected $jwtService;
    protected $acModel = null;
    protected $writeMail;
    protected $navigateTo;

    public function __construct($writeEmail, $jwtService = null) {
        $this->writeMail = $writeEmail;
        if (!$jwtService) {
            $jwtService = new FirebaseJwtService(FlexAPI::get('jwtSecret'));
        }
        $this->jwtService = $jwtService;
    }

    public function setDataModel($acModel) {
        $this->acModel = $acModel;
        $this->acModel->addEntity(new UserVerification());
    }

    public function startVerification($data) {
        $forwardTo = null;
        $settings = FlexAPI::get('userVerification');
        if (array_key_exists('forwardTo', $settings)) {
            $forwardTo = $settings['forwardTo'];
        }
        $token = $this->jwtService->encode([
            'validity' => $settings['validityDuration'],
            'payload' => [
                'username' => $data['username'],
                'address' => $data['address'],
                'forwardTo' => $forwardTo
            ]
        ]);
        $this->acModel->insert('userverification', [
            'expires' => time() + $settings['validityDuration'],
            'token' => $token
        ]);
        $this->sendVerificationMail($data['address'], $token);

        return [
            'verificationMailSent' => true
        ];
    }

    public function finishVerification($data) {
        $token = $data['token'];
        $decoded = $this->jwtService->decode($token);
        $payload = (array) $decoded['data'];

        $pendingVerification = $this->acModel->read('userverification', [
            'filter' => ['token' => $token],
            'flatten' => 'singleResult'
        ]);
        if (!$pendingVerification) {
            throw(new Exception('No pending verification found for given token.', 400));
        }

        $user = $this->acModel->read('user', [
            'filter' => [ 'name' => $payload['username'], 'isVerified' => false ],
            'flatten' => 'singleResult'
        ]);
        if (!$user) {
            throw(new Exception('Token user missmatch.', 400));
        }
        
        $this->acModel->delete('userverification', ['id' => $pendingVerification['id']]);

        return [
            'verificationSuccessfull' => true,
            'username' => $user['name'],
            'forwardTo' => $payload['forwardTo']
        ];
    }


    protected function sendVerificationMail($address, $token) {
        $url = FlexAPI::buildUrl([
            'endpoint' => 'portal.php',
            'queries' => [
                'verify' => $token
            ]
        ]);
        $writeMail = $this->writeMail;
        $body = $writeMail($address, $url);
        FlexAPI::sendMail([
            'from' => 'verification',
            'to' => $address,
            'subject' => 'Aktivierung Ihres Accounts',
            'body' => $body
        ]);
    }
}

