<?php
require('../../config.php');

$id = required_param('id', PARAM_INT); // Course id.

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

$PAGE->set_url('/mod/visorpdf/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_visorpdf'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_visorpdf'));

if (!$visorpdfs = get_all_instances_in_course('visorpdf', $course)) {
    notice(get_string('novisorpdfs', 'mod_visorpdf'), new moodle_url('/course/view.php', ['id' => $course->id]));
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head  = [get_string('name'), get_string('lastmodified')];
$table->align = ['left', 'left'];

foreach ($visorpdfs as $visorpdf) {
    $link = html_writer::link(
        new moodle_url('/mod/visorpdf/view.php', ['id' => $visorpdf->coursemodule]),
        format_string($visorpdf->name)
    );

    $row = [];
    $row[] = $link;
    $row[] = userdate($visorpdf->timemodified);
    $table->data[] = $row;
}

echo html_writer::table($table);
echo $OUTPUT->footer();
