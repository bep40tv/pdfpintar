<?php

namespace App\Service;

use Closure;
use Symfony\Component\Process\Process;

class PdfinfoService
{

    protected string $binPath;

    public function __construct(?string $binPath = null)
    {
        $this->binPath = $binPath ?? '/usr/bin/pdftotext';
    }

    public function getInfo($path)
    {
        $process = new Process([$this->binPath, $path]);
        $process->setTimeout(3000);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \Exception("Failed to get the data!");
        }

        return trim($process->getOutput(), " \t\n\r\0\x0B\x0C");
    }

    protected function extractPageCount($inputString)
    {
        $pattern = '/Pages:\s+(\d+)/';

        if (preg_match($pattern, $inputString, $matches)) {
            return intval($matches[1]);
        }

        return 0;
    }

    public function getTotalPage($path)
    {
        $result = $this->getInfo($path);
        return $this->extractPageCount($result);
    }

    public function tapPage($path, Closure $callback) {
        $page = $this->getTotalPage($path);
        for ($i = 1; $i <= $page; $i++) {
            $callback($i);
        }
    }
}
