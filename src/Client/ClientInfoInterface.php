<?php

declare(strict_types=1);

namespace IpLogger\Client;

interface ClientInfoInterface
{
    public function getIp(): ?string;

    public function getUserAgent(): ?string;
}
