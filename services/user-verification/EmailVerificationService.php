<?php

include_once __DIR__ . '/IfVerificationService.php';
include_once __DIR__ . '/UserVerification.php';
include_once __DIR__ . '/../jwt/FirebaseJwtService.php';

class EmailVerificationService implements IfVerficationService {
    protected $jwtService;
    protected $acModel = null;
    protected $validityDuration;
    protected $writeMail;
    protected $navigateTo;

    public function __construct($writeEmail, $jwtService = null) {
        $this->writeMail = $writeEmail;
        $settings = FlexAPI::get('userVerification');
        $this->validityDuration = $settings['validityDuration'];
        if (!$jwtService) {
            $jwtService = new FirebaseJwtService(
                FlexAPI::get('jwtSecret'),
                $this->validityDuration
            );
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
        $token = $this->jwtService->encode([ 'payload' => [
            'username' => $data['username'],
            'address' => $data['address'],
            'forwardTo' => $forwardTo
        ]]);
        $this->acModel->insert('userverification', [
            'expires' => time() + $this->validityDuration,
            'token' => $token
        ]);
        $this->sendVerificationMail($data['address'], $token);

        return [];
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
        $user['isVerified'] = true;
        $this->acModel->update('user', $user);

        return [
            'username' => $user['name'],
            'forwardTo' => $payload['forwardTo']
        ];
    }


    protected function sendVerificationMail($address, $token) {
        $url = sprintf("http://%s:%s%s%s/portal.php?verify=%s",
            $_SERVER['SERVER_NAME'],
            $_SERVER['SERVER_PORT'],
            FlexAPI::get('basePath'),
            FlexAPI::get('apiPath'),
            $token
        );
        $writeMail = $this->writeMail;
        $body = $writeMail($address, $url);
        // echo $body;
        // $body = sprintf('<a href="%s">aktivieren</a>', $url);
        FlexAPI::sendMail([
            'from' => 'verification',
            'to' => $address,
            'subject' => 'Aktivierung Ihres Accounts',
            'body' => $body
        ]);
    }
}

// public function cleanUp() {
//     $settings = FlexAPI::get('userVerification');
//     $expired = $this->acModel->read('userverification', '[expire,ls,'.time().']');
//     foreach($expired as $verification) {
//         $decoded = (array) JWT::decode($verification['token'], base64_decode($settings['jwtSecret']), array('HS256'));
//         $this->acModel->delete('user', [ 'name' => $decoded['username'] ]);
//         $this->acModel->delete('userverification', [ 'id' => $verification['id'] ]);
//     }
// }
