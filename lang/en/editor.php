<?php

return [
    'flows' => 'Flows',
    'back_to_flows' => '← Flows',
    'saved' => 'Saved',
    'save_draft' => 'Save draft',
    'publish' => 'Publish',
    'apply' => 'Apply',
    'cannot_publish' => "This flow can't be published yet:",
    'choose_trigger' => 'Choose a trigger',
    'choose_trigger_description' => 'What starts this flow?',
    'no_settings' => 'This step has no settings.',
    'flow_name' => 'Flow name',
    'new_flow_placeholder' => 'Name a new flow',
    'create_flow' => 'Create flow',
    'no_flows' => 'No flows yet',
    'no_flows_description' => 'Create one to automate a process.',
    'versions' => ':count version|:count versions',
    'add_step' => 'Add a step',
    'move_up' => 'Move up',
    'move_down' => 'Move down',
    'delete' => 'Delete',
    'confirm_delete' => 'Delete this step?',
    'choose' => 'Choose…',
    'required' => 'required',
    'variables_hint' => 'Use :placeholder to insert data from earlier steps.',
    'duration_placeholder' => 'P7D (7 days), PT2H (2 hours)',
    'step_settings' => 'Step settings',

    // The word above each step card.
    'badge' => [
        'trigger' => 'When',
        'branch' => 'If',
        'condition' => 'Check',
        'action' => 'Then',
    ],

    // Named branches of a branch step. Others fall back to their name.
    'branches' => [
        'yes' => 'Yes',
        'otherwise' => 'Otherwise',
    ],
];
