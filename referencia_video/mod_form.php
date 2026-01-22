<?php
require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_drivevideo_mod_form extends moodleform_mod {

    function definition() {
        $mform = $this->_form;

        // Sección general
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Nombre
        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Intro normal Moodle
        $this->standard_intro_elements();

        // Campo para pegar el link del video
        $mform->addElement('text', 'videourl', get_string('videourl', 'mod_drivevideo'), ['size' => 100]);
        $mform->setType('videourl', PARAM_RAW);
        $mform->addRule('videourl', null, 'required', null, 'client');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
