<?php
require('../../config.php');

$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_login($course);

$PAGE->set_url('/mod/drivevideo/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_drivevideo'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('modulenameplural', 'mod_drivevideo'));

$videos = $DB->get_records('drivevideo', ['course' => $id]);

if (!$videos) {
    echo $OUTPUT->notification(get_string('none'), 'info');
} else {
    $table = new html_table();
    $table->head = ['Nombre', 'Link'];

    foreach ($videos as $v) {
        $cm = get_coursemodule_from_instance('drivevideo', $v->id);
        $url = new moodle_url('/mod/drivevideo/view.php', ['id' => $cm->id]);

        $table->data[] = [
            html_writer::link($url, $v->name),
            s($v->videourl)
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
