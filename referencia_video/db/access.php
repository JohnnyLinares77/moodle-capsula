<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Ver la actividad (alumnos, profesores, etc.).
    'mod/drivevideo:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => [
            'student'        => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ]
    ],

    // Añadir nuevas instancias de DriveVideo al curso (solo profesores/manager).
    'mod/drivevideo:addinstance' => [
        'riskbitmask'  => RISK_XSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/course:manageactivities'
    ],
];
