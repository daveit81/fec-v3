<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JDispatcher of Joomla 2.5, reduced to what the plugins need: the plugin
 * objects register themselves (JPluginHelper::importPlugin instantiates
 * them) and trigger($event, $args) calls the method of that name on each of
 * them, in order, returning their results. The arguments travel by reference,
 * as they did ($article->text is rewritten in place).
 */
final class Dispatcher
{
    private static ?Dispatcher $instance = null;

    /** @var list<object> */
    private array $observers = [];

    public static function getInstance(): Dispatcher
    {
        return self::$instance ??= new Dispatcher;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function attach(object $observer): void
    {
        foreach ($this->observers as $o) {
            if ($o === $observer) {
                return;
            }
        }
        $this->observers[] = $observer;
    }

    /** @return list<object> */
    public function getObservers(): array
    {
        return $this->observers;
    }

    /**
     * @param  list<mixed>  $args
     * @return list<mixed> one entry per plugin that implements the event
     */
    public function trigger(string $event, array $args = []): array
    {
        $results = [];
        foreach ($this->observers as $plugin) {
            if (! method_exists($plugin, $event)) {
                continue;
            }
            $results[] = $plugin->{$event}(...$args);
        }

        return $results;
    }
}
