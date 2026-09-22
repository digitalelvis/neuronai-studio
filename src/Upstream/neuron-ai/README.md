# Gemini media drivers — local path, upstream PR later

`GeminiImage`, `GeminiTextToSpeech`, `GeminiSpeechToText`, and `GeminiVideo`
are under test here. They use the `NeuronAI\Providers\Gemini\...` namespace so
they match [neuron-core/neuron-ai](https://github.com/neuron-core/neuron-ai),
and this package loads them only through the `psr-4` / `classmap` entries in
`composer.json` (path `src/Upstream/neuron-ai`). There is no fork install and
no patch in `vendor/`.

Do not open the neuron-ai pull request yet. Contribute these classes upstream
only after this path has been exercised and is working well. When that release
ships the same classes, remove the matching autoload entries from this package.
