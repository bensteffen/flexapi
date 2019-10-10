<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include_once __DIR__ . '/IfMailService.php';

class SmtpMailService implements IfMailService {
    private $mailer;

    public function __construct($smtpCredentials, $from) {
        $this->from = $from;

        $this->mailer = new PHPMailer(true);

        if ($smtpCredentials['host'] != '') {
            $this->mailer->isSMTP();
            $this->mailer->Host = $smtpCredentials['host'];
            $this->mailer->Port = $smtpCredentials['port'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $smtpCredentials['username'];
            $this->mailer->Password = $smtpCredentials['password'];
        }

        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->Encoding = 'quoted-printable';

        $this->mailer->setFrom($from['address'], $from['name']);
    }



    public function send($to, $subject, $htmlBody, $plainBody = '', $cc = []) {
        if (!is_array($to)) {
            $to = [$to];
        }
        foreach ($to as $address) {
            $this->mailer->addAddress($address, '');
        }
        if (!is_array($cc)) {
            $cc = [$cc];
        }
        foreach ($cc as $address) {
            $this->mailer->addCC($address, '');
        }

        if ($htmlBody) {
            $this->mailer->isHTML(true);
            $body = $htmlBody;
            $altBody = $plainBody;
        } else {
            $this->mailer->isHTML(false);
            $body = $plainBody;
            $altBody = '';
        }

        $this->mailer->Subject = $subject;
        $this->mailer->Body    = $body;
        $this->mailer->AltBody = $altBody; // for non-HTML clients

        $this->mailer->send();

        $this->mailer->ClearAllRecipients( );
    }
}
