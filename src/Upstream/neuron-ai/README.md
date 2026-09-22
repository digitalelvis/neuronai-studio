# Gemini media drivers staged for neuron-ai

These classes use the `NeuronAI\Providers\Gemini\...` namespace so they match
[neuron-core/neuron-ai](https://github.com/neuron-core/neuron-ai). They are
autoloaded from this package until that repository ships `GeminiImage`,
`GeminiTextToSpeech`, `GeminiSpeechToText`, and `GeminiVideo`.

Do not copy them into `vendor/`. When the upstream release includes the same
classes, remove the matching `psr-4` entries from this package's `composer.json`.
