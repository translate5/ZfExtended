<?php

use GuzzleHttp\Psr7\Utils;
use Microsoft\Graph\Generated\Models\BodyType;
use Microsoft\Graph\Generated\Models\EmailAddress;
use Microsoft\Graph\Generated\Models\FileAttachment;
use Microsoft\Graph\Generated\Models\ItemBody;
use Microsoft\Graph\Generated\Models\Message;
use Microsoft\Graph\Generated\Models\Recipient;
use Microsoft\Graph\Generated\Users\Item\SendMail\SendMailPostRequestBody;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;

class ZfExtended_Zend_Mail_Transport_MSGraph extends \Zend_Mail_Transport_Abstract
{
    private string $email;

    private GraphServiceClient $graphServiceClient;

    public function __construct(array $config = [])
    {
        if (! isset($config['email'])) {
            throw new InvalidArgumentException("Please provide login email in \$config['email']");
        }

        if (! isset($config['clientId'])) {
            throw new InvalidArgumentException("Please provide oauth2 access token in \$config['clientId']");
        }

        if (! isset($config['tenantId'])) {
            throw new InvalidArgumentException("Please provide oauth2 access token in \$config['tenantId']");
        }

        if (! isset($config['clientSecret'])) {
            throw new InvalidArgumentException("Please provide oauth2 access token in \$config['clientSecret']");
        }

        $this->email = $config['email'];
        $tokenRequestContext = new ClientCredentialContext(
            $config['tenantId'],
            $config['clientId'],
            $config['clientSecret']
        );

        $this->graphServiceClient = new GraphServiceClient($tokenRequestContext);
    }

    public function send(Zend_Mail $mail): void
    {
        // Extract sender and recipients
        $sender = new EmailAddress();
        $sender->setAddress($mail->getFrom());
        $fromRecipient = new Recipient();
        $fromRecipient->setEmailAddress($sender);

        $recipients = [];
        foreach ($mail->getRecipients() as $recipient) {
            $recipientAddress = new EmailAddress();
            $recipientAddress->setAddress($recipient);
            $recipientObject = new Recipient();
            $recipientObject->setEmailAddress($recipientAddress);
            $recipients[] = $recipientObject;
        }

        // Extract attachments
        $attachments = [];

        /** @var \Zend_Mime_Part $attachment */
        foreach ($mail->getParts() as $attachment) {
            if (Zend_Mime::TYPE_OCTETSTREAM !== $attachment->type) {
                continue;
            }

            $fileAttachment = new FileAttachment();
            $fileAttachment->setName($attachment->filename);
            $fileAttachment->setContentBytes(
                $attachment->isStream()
                    ? $attachment->getEncodedStream()
                    : Utils::streamFor(base64_encode($attachment->getRawContent()))
            );
            $attachments[] = $fileAttachment;
        }

        $message = new Message();
        $message->setSubject($mail->getSubject());
        $message->setFrom($fromRecipient);
        $message->setToRecipients($recipients);
        $message->setBody($this->getBody($mail));

        if (! empty($attachments)) {
            $message->setAttachments($attachments);
        }

        $requestBody = new SendMailPostRequestBody();
        $requestBody->setMessage($message);

        $this->graphServiceClient
            ->users()
            ->byUserId($this->email)
            ->sendMail()
            ->post($requestBody)
            ->wait();
    }

    private function getBody(Zend_Mail $mail): ItemBody
    {
        $body = new ItemBody();

        if (! $mail->getBodyText() && ! $mail->getBodyHtml()) {
            return $body;
        }

        if (false !== $mail->getBodyHtml()) {
            $body->setContent($mail->getBodyHtml()->getRawContent());
            $body->setContentType(new BodyType(BodyType::HTML));

            return $body;
        }

        $body->setContent($mail->getBodyText()->getRawContent());
        $body->setContentType(new BodyType(BodyType::TEXT));

        return $body;
    }

    protected function _sendMail(): void
    {
        // nothing to do here
    }
}
