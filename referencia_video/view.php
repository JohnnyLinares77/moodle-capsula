<?php
require('../../config.php');

$id = required_param('id', PARAM_INT);

$cm         = get_coursemodule_from_id('drivevideo', $id, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$drivevideo = $DB->get_record('drivevideo', ['id' => $cm->instance], '*', MUST_EXIST);

$context = context_module::instance($cm->id);

require_login($course, true, $cm);
global $USER;

$PAGE->set_url('/mod/drivevideo/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($drivevideo->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($drivevideo->name));

// Intro
if (!empty($drivevideo->intro)) {
    echo $OUTPUT->box(
        format_module_intro('drivevideo', $drivevideo, $cm->id),
        'generalbox mod_introbox'
    );
}

// Procesar URL de video
$url = trim($drivevideo->videourl);

// Detectar si es YouTube y convertir
if (preg_match('/youtu\.be\/([^\?]+)/', $url, $m) ||
    preg_match('/youtube\.com.*v=([^&]+)/', $url, $m)) {

    $embed = "https://www.youtube.com/embed/" . $m[1];
}
// Google Drive video
else if (preg_match('/\/d\/([^\/]+)/', $url, $m)) {
    $embed = "https://drive.google.com/file/d/{$m[1]}/preview";
}
// Por defecto: usar tal cual
else {
    $embed = $url;
}

// Texto de marca de agua
$watermarktext =
    fullname($USER) . ' - ' .
    $USER->email . ' - ' .
    userdate(time(), '%d/%m/%Y %H:%M');

// Sanear src
$embedsrc = s($embed);

// Contenedor principal
echo html_writer::start_div('mod_drivevideo_wrapper');
echo html_writer::start_div('mod_drivevideo_viewer');

// Ícono del engranaje
$gearicon = new moodle_url('/mod/drivevideo/gear.svg');

// Overlay del engranaje (oculta el botón de abrir externo)
echo html_writer::start_div('mod_drivevideo_cover');
echo html_writer::empty_tag('img', [
    'src'   => $gearicon,
    'alt'   => '',
    'class' => 'mod_drivevideo_cover_icon'
]);
echo html_writer::end_div(); // mod_drivevideo_cover

// Marca de agua
echo html_writer::div(
    $watermarktext,
    'mod_drivevideo_watermark'
);

// Iframe del video
echo html_writer::tag('iframe', '', [
    'src'             => $embedsrc,
    'frameborder'     => '0',
    'allowfullscreen' => 'true',
    'loading'         => 'lazy',
]);

echo html_writer::end_div(); // mod_drivevideo_viewer
echo html_writer::end_div(); // mod_drivevideo_wrapper

echo $OUTPUT->footer();
