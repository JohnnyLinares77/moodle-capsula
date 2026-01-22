<?php
require('../../config.php');

$id = required_param('id', PARAM_INT); // Course module ID.

// Obtener información del módulo y del curso
$cm       = get_coursemodule_from_id('visorpdf', $id, 0, false, MUST_EXIST);
$course   = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$visorpdf = $DB->get_record('visorpdf', ['id' => $cm->instance], '*', MUST_EXIST);
$context  = context_module::instance($cm->id);

// Permisos
require_login($course, true, $cm);
require_capability('mod/visorpdf:view', $context);

// Página
$PAGE->set_url('/mod/visorpdf/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($visorpdf->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Render
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($visorpdf->name));

// Intro
if (!empty($visorpdf->intro)) {
    echo $OUTPUT->box(
        format_module_intro('visorpdf', $visorpdf, $cm->id),
        'generalbox mod_introbox',
        'visorpdfintro'
    );
}

// Contenedor principal
echo html_writer::start_div('mod_visorpdf_wrapper');
echo html_writer::start_div('mod_visorpdf_viewer');

// Ícono del engranaje
$gearicon = new moodle_url('/mod/visorpdf/gear.svg');

// Overlay del engranaje (oculta el botón de Drive)
echo html_writer::start_div('mod_visorpdf_cover');
echo html_writer::empty_tag('img', [
    'src'   => $gearicon,
    'alt'   => '',
    'class' => 'mod_visorpdf_cover_icon'
]);
echo html_writer::end_div(); // mod_visorpdf_cover

// MARCA DE AGUA
$watermarktext =
    fullname($USER) . " - " .
    $USER->email . " - " .
    userdate(time(), '%d/%m/%Y %H:%M');

// Render de la marca de agua
echo html_writer::div(
    $watermarktext,
    'mod_visorpdf_watermark'
);

// Iframe (visor de Google Drive)
echo html_writer::tag('iframe', '', [
    'src'             => $visorpdf->embedurl,
    'frameborder'     => '0',
    'allowfullscreen' => 'true',
    'loading'         => 'lazy',
]);

echo html_writer::end_div(); // mod_visorpdf_viewer
echo html_writer::end_div(); // mod_visorpdf_wrapper

echo $OUTPUT->footer();
