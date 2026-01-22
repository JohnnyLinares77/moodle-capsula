<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_capsula_mod_form extends moodleform_mod {
    function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', 'General');
        $mform->addElement('text', 'name', 'Nombre de la Cápsula', array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'content_header', 'Contenido Multimedia');

        // Selector de Video
        $mform->addElement('filemanager', 'video_file', '1. Subir Video (.mp4)', null, 
            array('subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => array('video')));
        
        // Selector de PDF
        $mform->addElement('filemanager', 'pdf_file', '2. Subir PDF', null, 
            array('subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => array('.pdf')));

        // Selector de Modo
        $options = array(0 => 'Video + PDF (Ambos)', 1 => 'Solo Video', 2 => 'Solo PDF');
        $mform->addElement('select', 'showmode', 'Modo de visualización', $options);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
