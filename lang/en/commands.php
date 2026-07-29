<?php

return [
    'install_start' => 'Installing NeuronAI Studio...',
    'install_success' => 'NeuronAI Studio installed successfully!',
    'install_set_credentials' => 'Set provider credentials in .env (for example OPENAI_KEY) — see config/neuron.php.',
    'install_visit' => 'Visit /:prefix to open the dashboard.',
    'install_assets_rebuild' => 'JS assets are pre-built. To rebuild after editing resources/js/, run: npm install && npm run build && php artisan vendor:publish --tag=neuronai-studio-assets --force',
    'install_views_note' => 'Views load from the package by default. Use --with-views on install (or vendor:publish --tag=neuronai-studio-views) only when customizing Blade templates.',
    'install_skills' => 'AI coding assistants: Agent Skill at vendor/digitalelvis/neuronai-studio/skills/neuronai-studio/',
    'install_skills_npx' => '  npx skills add ./vendor/digitalelvis/neuronai-studio/skills',
    'install_skills_docs' => '  Docs: vendor/digitalelvis/neuronai-studio/docs/guides/ai-assisted-development.md',
    'install_run_migrations' => 'Run migrations now?',
    'export_exported' => 'Exported: :path',
    'make_tool_created' => 'Tool class created: :path',
    'purge_checkpoints' => 'Purged :count checkpoint(s).',
];
