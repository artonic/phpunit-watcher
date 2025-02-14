<?php

namespace Spatie\PhpUnitWatcher;

use Clue\React\Stdio\Stdio;
use React\EventLoop\Factory;
use React\Stream\ThroughStream;
use Symfony\Component\Finder\Finder;
use Spatie\PhpUnitWatcher\Screens\Phpunit;
use Yosymfony\ResourceWatcher\ResourceWatcher;
use Yosymfony\ResourceWatcher\ResourceCacheMemory;

class Watcher
{
    /** @var \Symfony\Component\Finder\Finder */
    protected $finder;

    /** @var \React\EventLoop\LibEventLoop */
    protected $loop;

    /** @var \Spatie\PhpUnitWatcher\Terminal */
    protected $terminal;

    /** @var array */
    protected $options;

    public function __construct(Finder $finder, array $options)
    {
        $this->finder = $finder;

        $this->loop = Factory::create();

        $this->terminal = new Terminal($this->buildStdio());

        $this->options = $options;
    }

    public function startWatching()
    {
        $this->terminal->displayScreen(new Phpunit($this->options), false);

        $watcher = new ResourceWatcher(new ResourceCacheMemory());

        $watcher->setFinder($this->finder);

        $this->loop->addPeriodicTimer(1 / 4, function () use ($watcher) {
            if (! $this->terminal->isDisplayingScreen(Phpunit::class)) {
                return;
            }

            $watcher->findChanges();

            if ($watcher->hasChanges()) {
                $this->terminal->refreshScreen($watcher);
            }
        });

        $this->loop->run();
    }

    protected function buildStdio()
    {
        $output = null;

        if (OS::isOnWindows()) {
            // Interaction on windows is currently not supported
            fclose(STDIN);

            // Simple fix for windows compatibility since we don't write a lot of data at once
            // https://github.com/clue/reactphp-stdio/issues/83#issuecomment-546678609
            $output = new ThroughStream(static function ($data) {
                echo $data;
            });
        }

        return new Stdio($this->loop, null, $output);
    }
}
