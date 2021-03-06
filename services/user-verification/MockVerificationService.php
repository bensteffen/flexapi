<?php

include_once __DIR__ . '/IfVerificationService.php';
include_once __DIR__ . '/UserVerification.php';
include_once __DIR__ . '/../jwt/FirebaseJwtService.php';

class MockVerificationService implements IfVerficationService {
    protected $jwtService;
    protected $acModel = null;

    public function __construct($jwtService = null) {
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
        $settings = FlexAPI::get('userVerification');
        $token = $this->jwtService->encode([
            'payload' => [
                'username' => $data['username'],
                'address' => $data['address'] 
            ]
        ]);
        $this->acModel->insert('userverification', [
            'expires' => time() + $settings['validityDuration'],
            'token' => $token
        ]);
        $this->sendVerificationMail($data['address'], $token);
        return [
            'token' => $token
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
            'username' => $user['name']
        ];
    }

    protected function sendVerificationMail($address, $token) {

    }
}
