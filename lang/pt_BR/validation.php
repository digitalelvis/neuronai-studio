<?php

return [
    'attributes' => [
        'name' => 'nome',
        'type' => 'tipo',
        'value' => 'valor',
        'provider' => 'provedor',
        'model' => 'modelo',
        'instructions' => 'instruções',
    ],
    'node_title_unique' => 'Os nomes dos nós devem ser únicos neste workflow (sem diferenciar maiúsculas).',
    'node_title_slug_unique' => 'Os nomes dos nós geram classes PHP duplicadas na exportação. Escolha nomes diferentes.',
    'node_title_max' => 'O nome do nó deve ter no máximo :max caracteres.',
];
