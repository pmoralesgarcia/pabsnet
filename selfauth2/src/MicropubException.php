<?php

namespace Selfauth;

/** A client-facing Micropub error: HTTP status, spec error code, description. */
class MicropubException extends \RuntimeException
{
    public int $status;
    public string $error;

    public function __construct(int $status, string $error, string $description = '')
    {
        parent::__construct($description);
        $this->status = $status;
        $this->error = $error;
    }
}
