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
$context = context_module::instance($cm->id);
$fs = get_file_storage();
// Obtener el archivo del área 'content'
$files = $fs->get_area_files($context->id, 'mod_visorpdf', 'content', 0, 'id', false);

echo $OUTPUT->header();

if ($files) {
    $file = reset($files);
    // Generar URL interna que usa el OAuth2 configurado
    $url = moodle_url::make_pluginfile_url(
        $file->get_contextid(), $file->get_component(), $file->get_filearea(), 
        $file->get_itemid(), $file->get_filepath(), $file->get_filename()
    );

    echo $OUTPUT->heading(format_string($visorpdf->name));
    echo '<iframe src="'.$url->out().'" style="width:100%; height:800px; border:none;"></iframe>';
}

echo $OUTPUT->footer();
