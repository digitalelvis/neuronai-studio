<?php

return [
    'install_start' => 'Instalando NeuronAI Studio...',
    'install_success' => 'NeuronAI Studio instalado com sucesso!',
    'install_set_credentials' => 'Defina as credenciais dos provedores no .env (por exemplo OPENAI_KEY) — veja config/neuron.php.',
    'install_visit' => 'Acesse /:prefix para abrir o painel.',
    'install_assets_rebuild' => 'Os assets JS já vêm compilados. Para recompilar após editar resources/js/: npm install && npm run build && php artisan vendor:publish --tag=neuronai-studio-assets --force',
    'install_views_note' => 'As views carregam do pacote por padrão. Use --with-views na instalação (ou vendor:publish --tag=neuronai-studio-views) apenas ao personalizar templates Blade.',
    'install_skills' => 'Assistentes de código: Agent Skill em vendor/digitalelvis/neuronai-studio/skills/neuronai-studio/',
    'install_skills_npx' => '  npx skills add ./vendor/digitalelvis/neuronai-studio/skills',
    'install_skills_docs' => '  Docs: vendor/digitalelvis/neuronai-studio/docs/guides/ai-assisted-development.md',
    'install_run_migrations' => 'Executar migrations agora?',
    'export_exported' => 'Exportado: :path',
    'make_tool_created' => 'Classe de ferramenta criada: :path',
    'purge_checkpoints' => 'Removido(s) :count checkpoint(s).',
];
