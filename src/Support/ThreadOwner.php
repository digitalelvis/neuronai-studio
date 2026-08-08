<?php

namespace DigitalElvis\NeuronAIStudio\Support;

use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\ThreadOwnerMismatchException;
use Illuminate\Database\Eloquent\Model;

final class ThreadOwner
{
    public const TYPE_STATE_KEY = '__studio_owner_type';

    public const ID_STATE_KEY = '__studio_owner_id';

    public const TYPE_INPUT_KEY = 'owner_type';

    public const ID_INPUT_KEY = 'owner_id';

    public const MODEL_INPUT_KEY = 'owner';

    public function __construct(
        public readonly string $type,
        public readonly string $id,
    ) {}

    public static function fromModel(Model $model): self
    {
        $key = $model->getKey();

        if ($key === null || $key === '') {
            throw new \InvalidArgumentException('Owner model must have a primary key.');
        }

        return new self($model->getMorphClass(), (string) $key);
    }

    /**
     * Resolve owner from invoke payload/input. Strips non-serializable `owner` Model from $input.
     *
     * @param  array<string, mixed>  $input
     */
    public static function consumeFromInput(array &$input): ?self
    {
        if (isset($input[self::MODEL_INPUT_KEY]) && $input[self::MODEL_INPUT_KEY] instanceof Model) {
            $owner = self::fromModel($input[self::MODEL_INPUT_KEY]);
            unset($input[self::MODEL_INPUT_KEY]);
            $input[self::TYPE_INPUT_KEY] = $owner->type;
            $input[self::ID_INPUT_KEY] = $owner->id;

            return $owner;
        }

        $type = $input[self::TYPE_INPUT_KEY] ?? null;
        $id = $input[self::ID_INPUT_KEY] ?? null;

        if (! is_string($type) || $type === '' || $id === null || $id === '') {
            return null;
        }

        return new self($type, (string) $id);
    }

    public static function fromThread(?StudioThread $thread): ?self
    {
        if ($thread === null) {
            return null;
        }

        $type = $thread->ownerable_type;
        $id = $thread->ownerable_id;

        if (! is_string($type) || $type === '' || $id === null || $id === '') {
            return null;
        }

        return new self($type, (string) $id);
    }

    public function matches(?StudioThread $thread): bool
    {
        if ($thread === null) {
            return false;
        }

        return $thread->ownerable_type === $this->type
            && (string) $thread->ownerable_id === $this->id;
    }

    public function isSameAs(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }

    /**
     * Assign owner when empty; no-op when same; throw when different.
     *
     * @throws ThreadOwnerMismatchException
     */
    public function bindTo(StudioThread $thread): void
    {
        $existing = self::fromThread($thread);

        if ($existing === null) {
            $thread->forceFill([
                'ownerable_type' => $this->type,
                'ownerable_id' => $this->id,
            ])->save();

            return;
        }

        if ($existing->isSameAs($this)) {
            return;
        }

        throw new ThreadOwnerMismatchException(
            (string) $thread->id,
            $existing->type,
            $existing->id,
            $this->type,
            $this->id,
        );
    }

    /** @return array{__studio_owner_type: string, __studio_owner_id: string} */
    public function toState(): array
    {
        return [
            self::TYPE_STATE_KEY => $this->type,
            self::ID_STATE_KEY => $this->id,
        ];
    }

    /** @return array{owner_type: string, owner_id: string} */
    public function toInput(): array
    {
        return [
            self::TYPE_INPUT_KEY => $this->type,
            self::ID_INPUT_KEY => $this->id,
        ];
    }
}
