<?php
defined('MOODLE_INTERNAL') || die();

function drivevideo_add_instance($data, $mform) {
    global $DB;
    $data->timecreated = time();
    $data->timemodified = time();
    return $DB->insert_record('drivevideo', $data);
}

function drivevideo_update_instance($data, $mform) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('drivevideo', $data);
}

function drivevideo_delete_instance($id) {
    global $DB;
    return $DB->delete_records('drivevideo', ['id' => $id]);
}
