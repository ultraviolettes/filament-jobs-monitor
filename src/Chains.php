<?php

namespace Croustibat\FilamentJobsMonitor;

use Croustibat\FilamentJobsMonitor\Models\FailedJob;
use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Gives every step of a job chain the same identifier.
 *
 * Laravel has none: a chain is a linked list, each job carrying the remaining
 * steps as serialized strings in `$command->chained`, and
 * `Queueable::dispatchNextJobInChain()` mutates the next job before dispatching
 * it — so the identifier cannot be derived after the fact and has to be carried
 * forward at dispatch time.
 *
 * The id is written into the payload (never onto the job object, which would
 * mean a dynamic property on a user class) by a `Queue::createPayloadUsing()`
 * callback:
 *
 *   - a job dispatched with a non-empty chain starts a chain and gets a new id;
 *   - while a chained job runs, the worker remembers its id and the class of the
 *     step that must come next, so the continuation inherits the id. The
 *     expectation is consumed on the first match, so an unrelated job dispatched
 *     from inside a chained job does not join the chain.
 *
 * Opt-in: it unserializes the command of every dispatched job.
 */
class Chains
{
    public const PAYLOAD_KEY = 'filament_jobs_monitor_chain_id';

    /**
     * The chain the job currently being processed belongs to.
     */
    protected static ?string $currentChainId = null;

    /**
     * The class the next step of that chain must have, consumed on first match.
     */
    protected static ?string $expectedNextClass = null;

    public static function enabled(): bool
    {
        return (bool) config('filament-jobs-monitor.chains.enabled', false);
    }

    /**
     * The payload entries to add to a job being dispatched.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public static function payloadFor(array $payload): array
    {
        if (! static::enabled()) {
            return [];
        }

        // Payload hooks run before the command is serialized, so `command`
        // here is the job object itself, not the string a worker later reads.
        $command = $payload['data']['command'] ?? null;

        if ($command === null) {
            return [];
        }

        $class = is_object($command) ? $command::class : ($payload['data']['commandName'] ?? null);

        if (static::$currentChainId !== null
            && static::$expectedNextClass !== null
            && $class === static::$expectedNextClass
        ) {
            $chainId = static::$currentChainId;
            static::$expectedNextClass = null;

            return [static::PAYLOAD_KEY => $chainId];
        }

        return static::hasChainedSteps($command)
            ? [static::PAYLOAD_KEY => (string) Str::uuid()]
            : [];
    }

    /**
     * Remember the chain of the job about to run, and what has to come next.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function enter(array $payload): ?string
    {
        if (! static::enabled()) {
            return null;
        }

        $chainId = $payload[static::PAYLOAD_KEY] ?? null;

        static::$currentChainId = is_string($chainId) ? $chainId : null;
        static::$expectedNextClass = static::$currentChainId === null
            ? null
            : static::nextChainedClass($payload['data']['command'] ?? null);

        return static::$currentChainId;
    }

    public static function leave(): void
    {
        static::$currentChainId = null;
        static::$expectedNextClass = null;
    }

    /**
     * The steps of the chain a job belongs to, for the detail timeline.
     *
     * `steps` are the monitored runs, in order. `neverReached` are the classes
     * that would have come after the last one: a chain that broke leaves no row
     * for them, so they are read off the serialized chain of the failed job.
     *
     * @return array{steps: Collection<int, QueueMonitor>, neverReached: array<int, string>, total: int, truncated: bool}
     */
    public static function timelineFor(QueueMonitor $record, int $limit = 50): array
    {
        $empty = ['steps' => new Collection, 'neverReached' => [], 'total' => 0, 'truncated' => false];

        if (blank($record->chain_id)) {
            return $empty;
        }

        $steps = $record->chain();
        $total = $steps->count();

        if ($total === 0) {
            return $empty;
        }

        return [
            'steps' => $steps->take($limit),
            'neverReached' => static::neverReachedAfter($steps->last()),
            'total' => $total,
            'truncated' => $total > $limit,
        ];
    }

    /**
     * The classes the chain would have run next, if it stopped on a failure.
     *
     * @return array<int, string>
     */
    protected static function neverReachedAfter(?QueueMonitor $last): array
    {
        if ($last === null || ! $last->hasFailed()) {
            return [];
        }

        $payload = resolve(FailedJob::class)::where('uuid', $last->job_id)->first()?->payload;
        $command = $payload['data']['command'] ?? null;
        $instance = static::unserializeCommand($command);

        if (! is_object($instance) || ! property_exists($instance, 'chained')) {
            return [];
        }

        $classes = [];

        foreach ((array) $instance->chained as $entry) {
            if (is_string($entry) && preg_match('/^O:\d+:"([^"]+)"/', $entry, $matches)) {
                $classes[] = $matches[1];
            }
        }

        return $classes;
    }

    public static function currentChainId(): ?string
    {
        return static::$currentChainId;
    }

    /**
     * Does this command still carry steps after itself?
     */
    protected static function hasChainedSteps(mixed $command): bool
    {
        return static::nextChainedClass($command) !== null;
    }

    /**
     * The class of the next step of a chain.
     *
     * Takes either the job object (payload hooks) or its serialized form (a
     * worker reading a payload). The chained entries are serialized objects, so
     * their class name is read off the string rather than unserializing them.
     */
    protected static function nextChainedClass(mixed $command): ?string
    {
        $instance = is_object($command) ? $command : static::unserializeCommand($command);

        if (! is_object($instance) || ! property_exists($instance, 'chained')) {
            return null;
        }

        /** @var array<int, string> $chained */
        $chained = $instance->chained;
        $next = $chained[0] ?? null;

        if (! is_string($next) || ! preg_match('/^O:\d+:"([^"]+)"/', $next, $matches)) {
            return null;
        }

        return $matches[1];
    }

    protected static function unserializeCommand(mixed $command): ?object
    {
        if (! is_string($command)) {
            return null;
        }

        try {
            // An encrypted command is simply not readable here, and chains then
            // stay untracked rather than breaking the dispatch.
            $instance = @unserialize($command);
        } catch (Throwable) {
            return null;
        }

        // A job class that no longer exists unserializes to an incomplete
        // object, which throws on any property access.
        if (! is_object($instance) || $instance instanceof \__PHP_Incomplete_Class) {
            return null;
        }

        return $instance;
    }
}
