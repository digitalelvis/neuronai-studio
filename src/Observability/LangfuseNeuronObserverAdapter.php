<?php

namespace DigitalElvis\NeuronAIStudio\Observability;

use NeuronAI\Observability\ObserverInterface;
use ReflectionMethod;
use Throwable;

/**
 * Adapts axyr/laravel-langfuse NeuronAiObserver to Neuron ObserverInterface
 * including the optional $branchId parameter (package may omit it).
 */
class LangfuseNeuronObserverAdapter implements ObserverInterface
{
    protected static bool $missingPackageWarned = false;

    public function __construct(
        protected object $inner,
    ) {}

    public static function make(): ?self
    {
        $class = 'Axyr\\Langfuse\\NeuronAi\\NeuronAiObserver';

        if (! class_exists($class)) {
            return null;
        }

        try {
            if (method_exists($class, 'make')) {
                $inner = $class::make();
            } else {
                $inner = new $class;
            }
        } catch (Throwable) {
            return null;
        }

        if (! is_object($inner)) {
            return null;
        }

        return new self($inner);
    }

    public static function warnMissingPackageOnce(): void
    {
        if (self::$missingPackageWarned) {
            return;
        }

        self::$missingPackageWarned = true;

        if (function_exists('logger')) {
            logger()->warning(
                'Langfuse observability is enabled but axyr/laravel-langfuse is not installed. '.
                'Run: composer require axyr/laravel-langfuse'
            );
        }
    }

    public static function resetWarnings(): void
    {
        self::$missingPackageWarned = false;
    }

    public function onEvent(string $event, object $source, mixed $data = null, ?string $branchId = null): void
    {
        if (! method_exists($this->inner, 'onEvent')) {
            return;
        }

        try {
            $method = new ReflectionMethod($this->inner, 'onEvent');
            $paramCount = $method->getNumberOfParameters();

            if ($paramCount >= 4) {
                $this->inner->onEvent($event, $source, $data, $branchId);

                return;
            }

            $this->inner->onEvent($event, $source, $data);
        } catch (Throwable) {
            // Best-effort export — never break the run.
        }
    }
}
