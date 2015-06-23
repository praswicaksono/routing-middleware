<?php

namespace Jowy\Routing\Stub;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ControllerStub
{
    public function handleRequest(ServerRequestInterface $req, ResponseInterface $res, array $param = null)
    {
        return $res->withStatus(200);
    }

    public function invalidReturnHandler()
    {
        return true;
    }
}
