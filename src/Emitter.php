<?php

namespace SpinTheWheel;

use Psr\Http\Message\ResponseInterface;


/**
* Full credit to shadowhand / http-interop group github
*/
class Emitter {

    public function send(ResponseInterface $response) {
        $httpLine = $this->buildHttp($response);

        header($httpLine, true, $response->getStatusCode());

        $this->buildHeaders($response->getHeaders());

        $stream = $response->getBody();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        while (!$stream->eof()) {
            echo$stream->read(1024 * 8);
        }
    }

    protected function buildHttp(ResponseInterface $response) {
        return sprintf('HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );
    }

    protected function buildHeaders($headers){

        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                header("$name: $value", false);
            }
        }
    }
}
