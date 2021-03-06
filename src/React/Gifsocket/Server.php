<?php

namespace React\Gifsocket;

use React\EventLoop\LoopInterface;

class Server
{
    private $gifStreams;
    private $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->gifStreams = new \SplObjectStorage();
        $this->loop = $loop;
    }

    // Add the frame twice because some browsers (safari, opera)
    // would start lagging behind otherwise
    // add timeout so that first frame can flush
    public function addFrame($frame)
    {
        foreach ($this->gifStreams as $gif) {
            $gif->addFrame($frame);
            $gif->lastFrame = $frame;

            $this->loop->addTimer(0.001, function () use ($gif, $frame) {
                $this->resendFrame($gif, $frame);
            });
        }
    }

    public function __invoke($request, $response)
    {
        $response->writeHead(200, [
            'Content-Type'  => 'image/gif',
            'Cache-Control' => 'no-cache, no-store',
            'Pragma'        => 'no-cache',
        ]);

        $gif = $this->createGifStream();
        $gif->pipe($response);

        $this->gifStreams->attach($gif);

        $response->on('close', function () use ($gif) {
            $this->gifStreams->detach($gif);
            $gif->close();
        });
    }

    public function resendFrame(GifStream $gif, $frame)
    {
        if ($gif->lastFrame !== $frame) {
            return;
        }

        $gif->addFrame($frame);
    }

    private function createGifStream()
    {
        $encoder = new GifEncoder();
        $gif = new GifStream($encoder);

        return $gif;
    }
}
