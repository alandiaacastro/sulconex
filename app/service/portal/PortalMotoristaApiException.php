<?php

class PortalMotoristaApiException extends Exception
{
    private int $httpStatus;
    private string $errorCode;
    private array $details;

    public function __construct(string $message, int $httpStatus = 400, string $errorCode = 'bad_request', array $details = [])
    {
        parent::__construct($message);

        $this->httpStatus = $httpStatus;
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}